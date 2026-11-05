<?php

namespace App\Support\BadgeForge\Analytics;

use App\Enums\BadgeViewSubject;
use App\Models\BadgeView;
use App\Models\User;
use Illuminate\Http\Request;
use Throwable;

/**
 * Grava uma visualizacao de imagem com dono (F7, ADR-086).
 *
 * Sincrono e best-effort: qualquer falha (banco fora, migracao pendente,
 * GeoIP ilegivel) e engolida para que a imagem nunca deixe de ser servida.
 * Nunca loga IP, UA ou o conteudo da requisicao.
 */
final class BadgeViewRecorder
{
    public function __construct(private readonly CountryReader $countries) {}

    public function record(
        Request $request,
        BadgeViewSubject $subject,
        string $subjectId,
        User $owner,
        ?string $definition = null,
        ?bool $cacheHit = null,
        string $format = 'svg',
        ?string $style = null,
    ): void {
        try {
            $ip = $request->ip();
            $userAgent = $request->userAgent();

            BadgeView::query()->create([
                'subject_type' => $subject,
                'subject_id' => $subjectId,
                'user_id' => $owner->getKey(),
                'definition' => $definition,
                'ip_hash' => PrivacyHash::of($ip),
                'user_agent_hash' => PrivacyHash::of($userAgent),
                'user_agent' => $this->truncate($userAgent),
                'referer_domain' => RefererDomain::from($request->headers->get('referer')),
                'country' => $this->countries->country($ip),
                'format' => $format,
                'style' => $style,
                'cache_hit' => $cacheHit,
                'viewed_at' => now(),
            ]);
        } catch (Throwable) {
            // Analytics nunca derruba a imagem.
        }
    }

    private function truncate(?string $userAgent): ?string
    {
        if ($userAgent === null) {
            return null;
        }

        $max = max(1, (int) config('badgeforge.analytics.user_agent_max_length', 512));

        return mb_substr($userAgent, 0, $max, 'UTF-8');
    }
}
