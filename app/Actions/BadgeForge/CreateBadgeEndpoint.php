<?php

namespace App\Actions\BadgeForge;

use App\Models\BadgeEndpoint;
use App\Models\User;
use App\Support\BadgeForge\Endpoint\EndpointFetchException;
use App\Support\BadgeForge\Endpoint\EndpointFetchReason;
use App\Support\BadgeForge\Endpoint\EndpointFetchRequest;
use App\Support\BadgeForge\Endpoint\EndpointGuard;
use App\Support\BadgeForge\Endpoint\EndpointHttpClient;
use App\Support\BadgeForge\Endpoint\EndpointTargetResolver;
use App\Support\BadgeForge\Endpoint\EndpointUrlException;
use App\Support\BadgeForge\Endpoint\EndpointUrlGuard;
use App\Support\BadgeForge\Endpoint\EndpointUrlReason;
use App\Support\BadgeForge\Endpoint\ResolvedEndpoint;
use App\Support\BadgeForge\Endpoint\SafeEndpointUrl;
use Illuminate\Validation\ValidationException;

/**
 * Cadastra um endpoint JSON (F5, ADR-083/ADR-084).
 *
 * Validacao + fetch de amostra: URL insegura ou payload fora do contrato e
 * 422; origem fora do ar, timeout, 429 ou DNS que nao resolve **nao** bloqueia
 * a criacao - a imagem passa a servir stale/fallback ate a origem voltar. A
 * linha guarda a URL canonica (e nunca o payload da amostra).
 */
final class CreateBadgeEndpoint
{
    public function __construct(
        private readonly EndpointUrlGuard $urlGuard,
        private readonly EndpointGuard $guard,
        private readonly EndpointTargetResolver $resolver,
        private readonly EndpointHttpClient $client,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public function __invoke(User $user, array $data): BadgeEndpoint
    {
        try {
            $url = $this->urlGuard->parse(trim((string) ($data['url'] ?? '')));
        } catch (EndpointUrlException $exception) {
            $this->guard->fail($exception);
        }

        $target = $this->resolve($url);

        if ($target !== null) {
            $this->probe($target);
        }

        return $user->badgeEndpoints()->create([
            'name' => (string) ($data['name'] ?? ''),
            'url' => $url->canonical,
        ]);
    }

    /**
     * Host sem resposta e tolerado: nada a conectar agora. IP privado e
     * recusado, porque a origem e publica por definicao.
     */
    private function resolve(SafeEndpointUrl $url): ?ResolvedEndpoint
    {
        try {
            return $this->resolver->resolve($url);
        } catch (EndpointUrlException $exception) {
            if ($exception->reason === EndpointUrlReason::Unresolvable) {
                return null;
            }

            $this->guard->fail($exception);
        }
    }

    private function probe(ResolvedEndpoint $target): void
    {
        try {
            $this->client->fetch(new EndpointFetchRequest($target));
        } catch (EndpointFetchException $exception) {
            if (in_array($exception->reason, [
                EndpointFetchReason::InvalidResponse,
                EndpointFetchReason::UnsupportedContentType,
                EndpointFetchReason::TooLarge,
                EndpointFetchReason::Redirected,
            ], true)) {
                $this->guard->fail($exception);
            }

            // Rede, timeout, 429 e curl ausente nao bloqueiam o cadastro.
        } catch (EndpointUrlException $exception) {
            $this->guard->fail($exception);
        }
    }
}
