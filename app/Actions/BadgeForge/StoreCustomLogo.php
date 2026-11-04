<?php

namespace App\Actions\BadgeForge;

use App\Models\BadgeCustomLogo;
use App\Models\User;
use App\Support\BadgeForge\BadgeForgeQuota;
use App\Support\BadgeForge\LogoPath;
use App\Support\BadgeForge\ParsedCustomLogo;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Persiste o resultado normalizado de um SVG (ADR-078).
 *
 * A linha do usuario e travada durante a checagem do limite Free: duas abas
 * enviando ao mesmo tempo nao passam do teto. O arquivo original nao entra no
 * banco nem no disco - so `viewBox`, paths e o tamanho do upload.
 */
final class StoreCustomLogo
{
    public function __construct(private readonly BadgeForgeQuota $quota) {}

    /**
     * @throws ValidationException
     */
    public function __invoke(User $user, string $name, ParsedCustomLogo $logo): BadgeCustomLogo
    {
        return DB::transaction(function () use ($user, $name, $logo): BadgeCustomLogo {
            // No MySQL isto serializa a checagem por conta; no SQLite (testes)
            // e no-op, mas a transacao mantem o comportamento unico.
            User::query()->whereKey($user->getKey())->lockForUpdate()->first();

            if ($this->quota->customLogoLimitReached($user)) {
                throw ValidationException::withMessages([
                    'logo' => __('validation.custom_logo.limit_reached'),
                ]);
            }

            $paths = array_map(static function (LogoPath $path): array {
                $item = ['d' => $path->d];

                if ($path->fillRule !== null) {
                    $item['fillRule'] = $path->fillRule;
                }

                if ($path->clipRule !== null) {
                    $item['clipRule'] = $path->clipRule;
                }

                return $item;
            }, $logo->paths);

            return $user->badgeCustomLogos()->create([
                'name' => $name,
                'view_box' => $logo->viewBox,
                'paths' => $paths,
                'path_count' => count($paths),
                'size_bytes' => $logo->sizeBytes,
            ]);
        });
    }
}
