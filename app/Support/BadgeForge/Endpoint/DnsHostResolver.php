<?php

namespace App\Support\BadgeForge\Endpoint;

/**
 * Resolucao A/AAAA via PHP (F5).
 *
 * `dns_get_record` cobre os dois tipos numa consulta; `gethostbynamel` fica
 * como fallback em ambientes que nao devolvem registros estruturados. O
 * resultado e sempre uma lista deduplicada - a validacao de IP e do
 * `EndpointTargetResolver`.
 */
final class DnsHostResolver implements HostResolver
{
    public function resolve(string $host): array
    {
        $records = @dns_get_record($host, DNS_A | DNS_AAAA);
        $ips = [];

        foreach (is_array($records) ? $records : [] as $record) {
            if (isset($record['ip']) && is_string($record['ip'])) {
                $ips[] = $record['ip'];
            }

            if (isset($record['ipv6']) && is_string($record['ipv6'])) {
                $ips[] = $record['ipv6'];
            }
        }

        if ($ips === []) {
            $fallback = @gethostbynamel($host);

            if (is_array($fallback)) {
                $ips = $fallback;
            }
        }

        return array_values(array_unique($ips));
    }
}
