<?php

namespace App\Support\BadgeForge\Endpoint;

/**
 * Parse e canonicalizacao da URL de endpoint (F5, ADR-084).
 *
 * Aceita apenas HTTPS na porta permitida, host DNS ASCII (ou IPv4 literal),
 * sem userinfo, fragmento, controle, espaco ou barra invertida. A canonical
 * descarta a porta explicita 443 e o ponto final do host, para que a mesma
 * origem gere a mesma chave de cache.
 */
final class EndpointUrlGuard
{
    /**
     * @throws EndpointUrlException
     */
    public function parse(string $raw): SafeEndpointUrl
    {
        $raw = trim($raw);
        $maxLength = max(1, (int) config('badgeforge.endpoint.max_url_length', 2048));

        if ($raw === '') {
            throw new EndpointUrlException(EndpointUrlReason::Length);
        }

        if (mb_strlen($raw, 'UTF-8') > $maxLength) {
            throw new EndpointUrlException(EndpointUrlReason::Length);
        }

        // Controle, espaco cru, barra invertida e fragmento: nenhum tem uso
        // legitimo numa URL de JSON e todos confundem parser/proxy.
        if (preg_match('/[\x00-\x20\x7F\\\\#]/', $raw) === 1) {
            throw new EndpointUrlException(EndpointUrlReason::Chars);
        }

        try {
            $parts = parse_url($raw);
        } catch (\ValueError) {
            throw new EndpointUrlException(EndpointUrlReason::Chars);
        }

        if (! is_array($parts)) {
            throw new EndpointUrlException(EndpointUrlReason::Chars);
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $allowedScheme = strtolower((string) config('badgeforge.endpoint.allowed_scheme', 'https'));

        if ($scheme !== $allowedScheme) {
            throw new EndpointUrlException(EndpointUrlReason::Scheme);
        }

        if (isset($parts['user']) || isset($parts['pass'])) {
            throw new EndpointUrlException(EndpointUrlReason::UserInfo);
        }

        $host = strtolower((string) ($parts['host'] ?? ''));

        if ($host === '') {
            throw new EndpointUrlException(EndpointUrlReason::Host);
        }

        // Um ponto final e a mesma origem; descartar evita chaves duplicadas.
        $host = rtrim($host, '.');

        $port = (int) config('badgeforge.endpoint.allowed_port', 443);

        if (isset($parts['port']) && (int) $parts['port'] !== $port) {
            throw new EndpointUrlException(EndpointUrlReason::Port);
        }

        $this->assertHost($host);

        $path = (string) ($parts['path'] ?? '');
        $path = $path === '' ? '/' : $path;
        $query = isset($parts['query']) ? '?'.(string) $parts['query'] : '';

        return new SafeEndpointUrl(
            canonical: $scheme.'://'.$host.$path.$query,
            host: $host,
            port: $port,
        );
    }

    /**
     * @throws EndpointUrlException
     */
    private function assertHost(string $host): void
    {
        if (strlen($host) > max(1, (int) config('badgeforge.endpoint.max_host_length', 253))) {
            throw new EndpointUrlException(EndpointUrlReason::Host);
        }

        // IPv4 literal e aceito e classificado depois; IPv6 literal fica de
        // fora porque o pin exige outro formato e o caso pratico e o DNS.
        if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
            return;
        }

        if (str_contains($host, ':') || str_contains($host, '%')) {
            throw new EndpointUrlException(EndpointUrlReason::Host);
        }

        // DNS ASCII (punycode `xn--` incluido); sem underline, sem rotulo
        // vazio e sem rotulo com hifen nas pontas.
        if (preg_match('/^[a-z0-9](?:[a-z0-9-]*[a-z0-9])?(?:\.[a-z0-9](?:[a-z0-9-]*[a-z0-9])?)*$/', $host) !== 1) {
            throw new EndpointUrlException(EndpointUrlReason::Host);
        }

        if ($host === 'localhost' || ! str_contains($host, '.')) {
            throw new EndpointUrlException(EndpointUrlReason::Host);
        }
    }
}
