<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Base64ImageConversion;
use App\Models\Plan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * Endpoints da ferramenta Base64 Image (ADR-055).
 */
class Base64ImageController extends Controller
{
    /**
     * Converte uma imagem enviada para Base64 Data URI.
     */
    public function convert(Request $request): JsonResponse
    {
        $user = $request->user();
        $plan = $user?->effectivePlan() ?? Plan::free();
        $isPaid = ! $plan->isFree();

        $freeLimit = (int) config('tools.limits.base64_image.free_max_bytes', 2 * 1024 * 1024);
        $paidLimit = (int) config('tools.limits.base64_image.paid_max_bytes', 25 * 1024 * 1024);

        $maxBytes = $isPaid
            ? ($plan->max_image_upload_bytes > 0 ? $plan->max_image_upload_bytes : $paidLimit)
            : ($plan->max_image_upload_bytes > 0 ? $plan->max_image_upload_bytes : $freeLimit);

        $request->validate([
            'file' => [
                'required',
                'file',
                'max:'.(int) ceil($maxBytes / 1024),
            ],
        ]);

        /** @var UploadedFile $file */
        $file = $request->file('file');
        $mimeType = $file->getMimeType() ?: 'application/octet-stream';

        if (! str_starts_with($mimeType, 'image/')) {
            return response()->json([
                'message' => __('tools.base64-image.errors.invalid_image'),
                'errors' => ['file' => [__('tools.base64-image.errors.invalid_image')]],
            ], 422);
        }

        $ulid = (string) Str::ulid();
        $originalFilename = $file->getClientOriginalName();
        $extension = $file->getClientOriginalExtension() ?: 'bin';
        $fileSizeBytes = $file->getSize();

        $dir = $user !== null
            ? storage_path("app/data/base64_images/{$user->id}")
            : storage_path('app/data/base64_images/anonymous');

        File::ensureDirectoryExists($dir);

        $contents = File::get($file->getRealPath());
        $base64 = base64_encode($contents);
        $dataUri = "data:{$mimeType};base64,{$base64}";
        $base64SizeBytes = strlen($dataUri);

        $originalPath = "{$dir}/{$ulid}.original.{$extension}";
        $convertedPath = "{$dir}/{$ulid}.txt";

        File::put($originalPath, $contents);
        File::put($convertedPath, $dataUri);

        if ($user !== null) {
            Base64ImageConversion::create([
                'id' => $ulid,
                'user_id' => $user->id,
                'filename' => $originalFilename,
                'mime_type' => $mimeType,
                'file_size_bytes' => $fileSizeBytes,
                'base64_size_bytes' => $base64SizeBytes,
                'is_paid_plan' => $isPaid,
                'disk_path_original' => $originalPath,
                'disk_path_converted' => $convertedPath,
            ]);
        }

        $threshold = (int) config('tools.limits.base64_image.download_threshold_bytes', 2 * 1024 * 1024);
        $directDownload = $isPaid && ($base64SizeBytes > $threshold);

        $retentionHours = $isPaid
            ? (int) config('tools.limits.base64_image.paid_retention_days', 30) * 24
            : (int) config('tools.limits.base64_image.free_retention_hours', 24);

        return response()->json([
            'id' => $ulid,
            'filename' => $originalFilename,
            'mime_type' => $mimeType,
            'file_size_bytes' => $fileSizeBytes,
            'base64_size_bytes' => $base64SizeBytes,
            'direct_download' => $directDownload,
            'data_uri' => $directDownload ? null : $dataUri,
            'download_url' => route('tools.base64-image.download', ['id' => $ulid]),
            'preview_url' => route('tools.base64-image.preview', ['id' => $ulid]),
            'expires_in_hours' => $retentionHours,
        ]);
    }

    /**
     * Retorna o historico de conversoes do usuario autenticado.
     */
    public function history(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user === null) {
            return response()->json([]);
        }

        $conversions = Base64ImageConversion::query()
            ->forUser($user->id)
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        return response()->json($conversions->map(fn (Base64ImageConversion $c) => [
            'id' => $c->id,
            'filename' => $c->filename,
            'mime_type' => $c->mime_type,
            'file_size_bytes' => $c->file_size_bytes,
            'base64_size_bytes' => $c->base64_size_bytes,
            'is_paid_plan' => $c->is_paid_plan,
            'is_expired' => $c->isExpired(),
            'created_at' => $c->created_at?->toIso8601String(),
            'download_url' => route('tools.base64-image.download', ['id' => $c->id]),
            'preview_url' => route('tools.base64-image.preview', ['id' => $c->id]),
        ]));
    }

    /**
     * Exclui manualmente uma conversao e seus arquivos fisicos.
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $user = $request->user();

        if ($user !== null) {
            $conversion = Base64ImageConversion::query()
                ->where('user_id', $user->id)
                ->where('id', $id)
                ->first();

            if ($conversion !== null) {
                $conversion->deletePhysicalFiles();
                $conversion->delete();

                return response()->json(['deleted' => true]);
            }
        }

        // Remocao para arquivos anonimos no disco
        $anonymousDir = storage_path('app/data/base64_images/anonymous');
        $matches = glob("{$anonymousDir}/{$id}.*");

        if (! empty($matches)) {
            foreach ($matches as $filePath) {
                File::delete($filePath);
            }
        }

        return response()->json(['deleted' => true]);
    }
}
