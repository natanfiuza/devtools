<?php

namespace App\Support\BadgeForge\Endpoint;

/**
 * Classificador de IP publico (F5, ADR-084).
 *
 * Recusa loopback, RFC1918, link-local, CGNAT, multicast, documentacao e
 * reservados; IPv6 exige global unicast (2000::/3) e rejeita ULA, link-local
 * e multicast. IPv4 mapeado em IPv6 e normalizado antes, porque o kernel
 * conecta o v4 de qualquer forma. As faixas vem de `config/badgeforge.php`.
 */
final class IpAddressClassifier
{
    public function isPublic(string $ip): bool
    {
        $normalized = $this->normalize($ip);

        if ($normalized === null) {
            return false;
        }

        if (filter_var($normalized, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
            return ! $this->inAnyCidr($normalized, $this->cidrs('blocked_ipv4_cidrs'));
        }

        if (filter_var($normalized, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false) {
            if ($this->inAnyCidr($normalized, $this->cidrs('blocked_ipv6_cidrs'))) {
                return false;
            }

            if ((bool) config('badgeforge.endpoint.require_ipv6_global_unicast', true)) {
                return $this->inCidr($normalized, '2000::/3');
            }

            return true;
        }

        return false;
    }

    /**
     * Converte `::ffff:127.0.0.1` (e a forma hexadecimal equivalente) para
     * IPv4, alem de validar o formato. Devolve `null` para lixo.
     */
    private function normalize(string $ip): ?string
    {
        $binary = @inet_pton($ip);

        if ($binary === false) {
            return null;
        }

        if (strlen($binary) === 16
            && substr($binary, 0, 10) === str_repeat("\0", 10)
            && substr($binary, 10, 2) === "\xff\xff") {
            $mapped = @inet_ntop(substr($binary, 12, 4));

            return $mapped === false ? null : $mapped;
        }

        return $ip;
    }

    /**
     * @return list<string>
     */
    private function cidrs(string $key): array
    {
        $value = config('badgeforge.endpoint.'.$key, []);

        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter($value, static fn (mixed $cidr): bool => is_string($cidr) && $cidr !== ''));
    }

    /**
     * @param  list<string>  $cidrs
     */
    private function inAnyCidr(string $ip, array $cidrs): bool
    {
        foreach ($cidrs as $cidr) {
            if ($this->inCidr($ip, $cidr)) {
                return true;
            }
        }

        return false;
    }

    private function inCidr(string $ip, string $cidr): bool
    {
        $parts = explode('/', $cidr, 2);

        if (count($parts) !== 2) {
            return false;
        }

        $network = @inet_pton($parts[0]);
        $address = @inet_pton($ip);

        if ($network === false || $address === false || strlen($network) !== strlen($address)) {
            return false;
        }

        $bits = (int) $parts[1];

        if ($bits < 0 || $bits > strlen($network) * 8) {
            return false;
        }

        $wholeBytes = intdiv($bits, 8);
        $remainder = $bits % 8;

        if ($wholeBytes > 0 && strncmp($address, $network, $wholeBytes) !== 0) {
            return false;
        }

        if ($remainder === 0) {
            return true;
        }

        $mask = (0xFF << (8 - $remainder)) & 0xFF;

        return (ord($address[$wholeBytes]) & $mask) === (ord($network[$wholeBytes]) & $mask);
    }
}
