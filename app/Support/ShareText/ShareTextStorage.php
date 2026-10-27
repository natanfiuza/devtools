<?php

namespace App\Support\ShareText;

use App\Models\SharedText;
use App\Models\User;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Gerenciador de armazenamento fisico e ciclo de vida de textos compartilhados (ADR-058).
 */
class ShareTextStorage
{
    /**
     * Retorna o teto de tamanho em bytes para o usuario especificado (ou visitante).
     */
    public static function maxBytesFor(?User $user): int
    {
        if ($user === null) {
            return (int) config('tools.limits.share_text.anonymous_max_bytes', 1024 * 1024);
        }

        $plan = $user->effectivePlan();

        if ($plan->isFree()) {
            return (int) config('tools.limits.share_text.free_max_bytes', 5 * 1024 * 1024);
        }

        return (int) config('tools.limits.share_text.paid_max_bytes', 25 * 1024 * 1024);
    }

    /**
     * Armazena um novo texto no disco e registra no banco de dados.
     *
     * @throws ValidationException
     */
    public static function store(string $content, ?User $user = null, ?string $extension = null): SharedText
    {
        $content = trim($content);

        if ($content === '') {
            throw ValidationException::withMessages([
                'data' => [__('tools.share-text.errors.empty_content')],
            ]);
        }

        $sizeBytes = strlen($content);
        $maxBytes = self::maxBytesFor($user);

        if ($sizeBytes > $maxBytes) {
            $maxMb = (int) round($maxBytes / (1024 * 1024));
            throw ValidationException::withMessages([
                'data' => [__('tools.share-text.errors.size_exceeded', ['max' => $maxMb])],
            ]);
        }

        $ulid = (string) Str::ulid();
        $cleanedExtension = $extension !== null ? strtolower(ltrim($extension, '.')) : null;

        if ($cleanedExtension !== null && strlen($cleanedExtension) > 16) {
            $cleanedExtension = substr($cleanedExtension, 0, 16);
        }

        $dir = $user !== null
            ? storage_path("app/data/sharetext/{$user->id}")
            : storage_path('app/data/sharetext/anonymous');

        File::ensureDirectoryExists($dir);

        $filePath = "{$dir}/{$ulid}.txt";
        File::put($filePath, $content);

        $retentionHours = (int) config('tools.limits.share_text.anonymous_retention_hours', 72);
        $expiresAt = $user === null ? now()->addHours($retentionHours) : null;

        return SharedText::create([
            'id' => $ulid,
            'user_id' => $user?->id,
            'file_path' => $filePath,
            'size_bytes' => $sizeBytes,
            'extension' => $cleanedExtension,
            'expires_at' => $expiresAt,
        ]);
    }

    /**
     * Localiza um texto compartilhado pelo ULID, garantindo que nao esteja expirado.
     */
    public static function find(string $id): ?SharedText
    {
        $ulid = self::extractUlid($id);

        if (! Str::isUlid($ulid)) {
            return null;
        }

        /** @var SharedText|null $text */
        $text = SharedText::query()->find($ulid);

        if ($text === null || $text->isExpired()) {
            return null;
        }

        return $text;
    }

    /**
     * Extrai o identificador ULID caso venha sufixado com extensao (ex: "01HXYZ...ABC.js" -> "01HXYZ...ABC").
     */
    public static function extractUlid(string $idWithOptionalExtension): string
    {
        $parts = explode('.', $idWithOptionalExtension, 2);

        return $parts[0];
    }

    /**
     * Extrai a extensao caso informada (ex: "01HXYZ...ABC.js" -> "js").
     */
    public static function extractExtension(string $idWithOptionalExtension): ?string
    {
        $parts = explode('.', $idWithOptionalExtension, 2);

        return isset($parts[1]) && $parts[1] !== '' ? $parts[1] : null;
    }

    /**
     * Exclui o arquivo fisico e o registro do banco de dados.
     */
    public static function delete(SharedText $sharedText): void
    {
        $sharedText->deletePhysicalFile();
        $sharedText->delete();
    }
}
