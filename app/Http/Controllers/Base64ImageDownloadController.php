<?php

namespace App\Http\Controllers;

use App\Models\Base64ImageConversion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Download e preview de arquivos gerados pela ferramenta Base64 Image (ADR-055).
 */
class Base64ImageDownloadController extends Controller
{
    /**
     * Download do arquivo .txt contendo a Data URI gerada.
     */
    public function download(Request $request, string $id): BinaryFileResponse
    {
        $conversion = Base64ImageConversion::find($id);

        if ($conversion !== null) {
            $user = $request->user();

            if ($conversion->user_id !== null && ($user === null || $user->id !== $conversion->user_id)) {
                abort(403);
            }

            if ($conversion->isExpired() || empty($conversion->disk_path_converted) || ! File::exists($conversion->disk_path_converted)) {
                abort(410, __('tools.base64-image.errors.file_expired'));
            }

            return response()->download(
                $conversion->disk_path_converted,
                "{$conversion->filename}.base64.txt",
                ['Content-Type' => 'text/plain; charset=UTF-8']
            );
        }

        // Caso anonimo
        $anonPath = storage_path("app/data/base64_images/anonymous/{$id}.txt");

        if (File::exists($anonPath)) {
            $freeHours = (int) config('tools.limits.base64_image.free_retention_hours', 24);

            if (File::lastModified($anonPath) < now()->subHours($freeHours)->getTimestamp()) {
                File::delete($anonPath);
                abort(410, __('tools.base64-image.errors.file_expired'));
            }

            return response()->download(
                $anonPath,
                'image.base64.txt',
                ['Content-Type' => 'text/plain; charset=UTF-8']
            );
        }

        abort(404);
    }

    /**
     * Previa da imagem original para exibicao segura sem onerar a arvore DOM.
     */
    public function preview(Request $request, string $id): BinaryFileResponse
    {
        $conversion = Base64ImageConversion::find($id);

        if ($conversion !== null) {
            $user = $request->user();

            if ($conversion->user_id !== null && ($user === null || $user->id !== $conversion->user_id)) {
                abort(403);
            }

            if ($conversion->isExpired() || empty($conversion->disk_path_original) || ! File::exists($conversion->disk_path_original)) {
                abort(404);
            }

            return response()->file(
                $conversion->disk_path_original,
                ['Content-Type' => $conversion->mime_type]
            );
        }

        // Caso anonimo
        $matches = glob(storage_path("app/data/base64_images/anonymous/{$id}.original.*"));

        if (! empty($matches) && File::exists($matches[0])) {
            $path = $matches[0];
            $freeHours = (int) config('tools.limits.base64_image.free_retention_hours', 24);

            if (File::lastModified($path) < now()->subHours($freeHours)->getTimestamp()) {
                File::delete($path);
                abort(404);
            }

            $mime = File::mimeType($path) ?: 'application/octet-stream';

            return response()->file($path, ['Content-Type' => $mime]);
        }

        abort(404);
    }
}
