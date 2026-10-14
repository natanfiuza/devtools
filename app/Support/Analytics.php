<?php

namespace App\Support;

/**
 * Medicao (GA4).
 *
 * O id vem de config/analytics.php e vazio desliga a medicao. Ele vai como
 * prop compartilhada e a tag so e injetada no cliente, depois do aceite
 * (ADR-013): nem o HTML do servidor nem o SSR tem uma linha de GA.
 */
final class Analytics
{
    /** Id do GA4 configurado, ou nulo quando a medicao esta desligada. */
    public static function ga4Id(): ?string
    {
        $id = trim((string) config('analytics.ga4_id'));

        return $id === '' ? null : $id;
    }
}
