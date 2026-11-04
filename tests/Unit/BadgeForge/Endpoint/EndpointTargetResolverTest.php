<?php

use App\Support\BadgeForge\Endpoint\EndpointTargetResolver;
use App\Support\BadgeForge\Endpoint\EndpointUrlException;
use App\Support\BadgeForge\Endpoint\EndpointUrlGuard;
use App\Support\BadgeForge\Endpoint\EndpointUrlReason;
use App\Support\BadgeForge\Endpoint\HostResolver;
use App\Support\BadgeForge\Endpoint\IpAddressClassifier;
use App\Support\BadgeForge\Endpoint\SafeEndpointUrl;
use Tests\TestCase;

uses(TestCase::class);

function endpointTestResolverWith(array $ips): EndpointTargetResolver
{
    $resolver = new class($ips) implements HostResolver
    {
        /**
         * @param  list<string>  $ips
         */
        public function __construct(private readonly array $ips) {}

        public function resolve(string $host): array
        {
            return $this->ips;
        }
    };

    return new EndpointTargetResolver($resolver, new IpAddressClassifier);
}

function endpointTestSafeUrl(string $raw): SafeEndpointUrl
{
    return (new EndpointUrlGuard)->parse($raw);
}

it('prefere IPv4 e devolve a entrada de CURLOPT_RESOLVE', function () {
    $resolved = endpointTestResolverWith(['2001:4860:4860::8888', '8.8.8.8'])
        ->resolve(endpointTestSafeUrl('https://example.com/status.json'));

    expect($resolved->ip)->toBe('8.8.8.8')
        ->and($resolved->resolveEntry())->toBe('example.com:443:8.8.8.8');
});

it('entrega IPv6 entre colchetes quando nao ha IPv4', function () {
    $resolved = endpointTestResolverWith(['2001:4860:4860::8888'])
        ->resolve(endpointTestSafeUrl('https://example.com/status.json'));

    expect($resolved->resolveEntry())->toBe('example.com:443:[2001:4860:4860::8888]');
});

it('recusa quando qualquer resposta e privada', function (array $ips) {
    try {
        endpointTestResolverWith($ips)->resolve(endpointTestSafeUrl('https://example.com/status.json'));
        $this->fail('O destino deveria ter sido recusado.');
    } catch (EndpointUrlException $exception) {
        expect($exception->reason)->toBe(EndpointUrlReason::UnsafeIp);
    }
})->with([
    'loopback' => [['127.0.0.1']],
    'misto' => [['8.8.8.8', '10.0.0.1']],
    'metadata' => [['169.254.169.254']],
]);

it('recusa IP literal privado e host sem resposta', function () {
    try {
        endpointTestResolverWith([])->resolve(endpointTestSafeUrl('https://10.0.0.1/status.json'));
        $this->fail('O IP literal privado deveria ter sido recusado.');
    } catch (EndpointUrlException $exception) {
        expect($exception->reason)->toBe(EndpointUrlReason::UnsafeIp);
    }

    try {
        endpointTestResolverWith([])->resolve(endpointTestSafeUrl('https://example.com/status.json'));
        $this->fail('O host sem resposta deveria ter sido recusado.');
    } catch (EndpointUrlException $exception) {
        expect($exception->reason)->toBe(EndpointUrlReason::Unresolvable);
    }
});
