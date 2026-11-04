<?php

namespace App\Support\BadgeForge\Endpoint;

/**
 * Resolve o host e escolhe o IP da conexao (F5, ADR-084).
 *
 * A regra e tudo-ou-nada: se qualquer resposta de DNS for privada/loopback,
 * a URL e recusada, mesmo que outra seja publica. Isso fecha o caso de um
 * registro apontando para a rede interna e outro para fora. IPv4 tem
 * preferencia quando existe.
 */
final class EndpointTargetResolver
{
    public function __construct(
        private readonly HostResolver $resolver,
        private readonly IpAddressClassifier $classifier,
    ) {}

    /**
     * @throws EndpointUrlException
     */
    public function resolve(SafeEndpointUrl $url): ResolvedEndpoint
    {
        if (filter_var($url->host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
            if (! $this->classifier->isPublic($url->host)) {
                throw new EndpointUrlException(EndpointUrlReason::UnsafeIp);
            }

            return new ResolvedEndpoint($url, $url->host);
        }

        $ips = $this->resolver->resolve($url->host);

        if ($ips === []) {
            throw new EndpointUrlException(EndpointUrlReason::Unresolvable);
        }

        foreach ($ips as $ip) {
            if (! $this->classifier->isPublic($ip)) {
                throw new EndpointUrlException(EndpointUrlReason::UnsafeIp);
            }
        }

        $ipv4 = array_values(array_filter(
            $ips,
            static fn (string $ip): bool => filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false,
        ));

        return new ResolvedEndpoint($url, $ipv4[0] ?? $ips[0]);
    }
}
