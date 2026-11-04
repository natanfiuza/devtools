<?php

namespace App\Support\BadgeForge\Providers;

/**
 * Resolve o `url_template` da definicao com os params ja validados
 * (F4, ADR-080).
 *
 * O template e a unica fonte de host: o resultado precisa ser HTTPS, manter
 * o mesmo host, nao ter userinfo/porta e nao deixar placeholder sobrando.
 * Valores sao `rawurlencode`; `allowSlash` preserva `/` entre segmentos.
 */
final class ProviderUrlBuilder
{
    /**
     * @param  array<string, string>  $params
     *
     * @throws ProviderFetchException
     */
    public function build(ProviderPair $pair, array $params): string
    {
        $paramList = ProviderParam::listFrom($pair->definition->required_params);

        if ($paramList === null) {
            throw new ProviderFetchException('unsafe_url');
        }

        $template = (string) $pair->definition->url_template;
        $templateParts = parse_url($template);

        if (! is_array($templateParts) || ! $this->isSafeParts($templateParts)) {
            throw new ProviderFetchException('unsafe_url');
        }

        $url = $template;

        foreach ($paramList as $param) {
            $raw = $params[$param->name] ?? null;

            if (! is_string($raw) || $raw === '') {
                throw new ProviderFetchException('unsafe_url');
            }

            // Pacote com escopo npm é `@scope/name`: o `@` é literal no path
            // e o `/` separa segmentos, cada um encodado individualmente.
            $encoded = $param->allowSlash
                ? implode('/', array_map(
                    static fn (string $segment): string => str_replace('%40', '@', rawurlencode($segment)),
                    explode('/', $raw),
                ))
                : rawurlencode($raw);

            $url = str_replace('{'.$param->name.'}', $encoded, $url);
        }

        if (preg_match('/\{[a-z0-9_]+\}/i', $url) === 1) {
            throw new ProviderFetchException('unsafe_url');
        }

        $parts = parse_url($url);

        if (! is_array($parts) || ! $this->isSafeParts($parts)) {
            throw new ProviderFetchException('unsafe_url');
        }

        if (($parts['host'] ?? null) !== ($templateParts['host'] ?? null)) {
            throw new ProviderFetchException('unsafe_url');
        }

        return $url;
    }

    /**
     * @param  array<string, int|string>  $parts
     */
    private function isSafeParts(array $parts): bool
    {
        if (($parts['scheme'] ?? null) !== 'https') {
            return false;
        }

        $host = $parts['host'] ?? null;

        if (! is_string($host) || $host === '') {
            return false;
        }

        foreach (['user', 'pass', 'port'] as $forbidden) {
            if (isset($parts[$forbidden]) && $parts[$forbidden] !== '') {
                return false;
            }
        }

        return true;
    }
}
