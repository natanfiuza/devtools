<?php

namespace App\Support\Health;

use App\Support\BadgeForge\PngCache;
use Closure;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Throwable;

/**
 * Sondas do `/up` (F8, ADR-089): banco, cache e o disco do PNG.
 *
 * Cada sonda falha isolada: o payload diz qual dependencia caiu e o status
 * vira 503, sem detalhe da excecao. E o que o balanceador e o cron de deploy
 * usam para tirar uma instancia quebrada da rotacao.
 */
final class HealthProbe
{
    /**
     * @return array<string, bool>
     */
    public function run(): array
    {
        return [
            'database' => $this->passes(static fn () => DB::select('select 1')),
            'cache' => $this->passes(fn (): bool => $this->cacheRoundTrip()),
            'storage' => $this->passes(static fn (): bool => self::storageRoundTrip()),
        ];
    }

    /**
     * @param  Closure(): mixed  $probe
     */
    private function passes(Closure $probe): bool
    {
        try {
            return (bool) $probe();
        } catch (Throwable) {
            return false;
        }
    }

    private function cacheRoundTrip(): bool
    {
        $key = 'badgeforge:health:'.Str::random(8);

        Cache::put($key, 'ok', 5);

        $value = Cache::get($key);

        Cache::forget($key);

        return $value === 'ok';
    }

    private static function storageRoundTrip(): bool
    {
        $directory = PngCache::directory();

        File::ensureDirectoryExists($directory);

        $path = $directory.'/health-'.Str::random(8).'.tmp';

        try {
            if (File::put($path, 'ok') === false) {
                return false;
            }

            return File::get($path) === 'ok';
        } finally {
            File::delete($path);
        }
    }
}
