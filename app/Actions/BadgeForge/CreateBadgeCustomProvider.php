<?php

namespace App\Actions\BadgeForge;

use App\Models\BadgeCustomProvider;
use App\Models\User;
use App\Support\BadgeForge\Custom\CustomMetricInput;
use App\Support\BadgeForge\Custom\CustomProviderGuard;
use App\Support\BadgeForge\Custom\CustomProviderProbe;
use App\Support\BadgeForge\Custom\CustomProviderUrlTemplate;
use App\Support\BadgeForge\Custom\JsonPointer;
use Illuminate\Support\Facades\DB;

/**
 * Cadastra um provider customizado (F7, ADR-087).
 *
 * Valida limite, template + placeholders, `example_path` e aponta o probe para
 * a origem real. Shape errado vira 422; rede fora nao bloqueia. Provider e
 * definicoes entram na mesma transacao.
 */
final class CreateBadgeCustomProvider
{
    public function __construct(
        private readonly CustomProviderGuard $guard,
        private readonly CustomProviderProbe $probe,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function __invoke(User $user, array $data): BadgeCustomProvider
    {
        $this->ensureLimit($user);

        $template = CustomProviderUrlTemplate::from((string) ($data['url_template'] ?? ''));

        if ($template === null) {
            $this->guard->fail('url_template', 'template');
        }

        $pathParams = $this->pathParams($data['path_params'] ?? null);

        if ($pathParams === null || ! $template->matchesPathParams($pathParams)) {
            $this->guard->fail('path_params', 'path_params');
        }

        $metrics = CustomMetricInput::listFrom($data['metrics'] ?? null);

        if ($metrics === null) {
            $this->guard->fail('metrics', 'metrics');
        }

        foreach ($metrics as $metric) {
            if (! JsonPointer::isValid($metric['pointer'])) {
                $this->guard->fail('metrics', 'pointer');
            }
        }

        $examplePath = (string) ($data['example_path'] ?? '');
        $this->probe->probe($template, $pathParams, $examplePath, $metrics);

        return DB::transaction(function () use ($user, $data, $template, $pathParams, $metrics): BadgeCustomProvider {
            $provider = $user->badgeCustomProviders()->create([
                'name' => (string) ($data['name'] ?? ''),
                // Guarda a forma limpa, sem espacos acidentais nas pontas.
                'url_template' => $template->template,
                'path_params' => $pathParams,
                'example_path' => (string) ($data['example_path'] ?? ''),
                'metrics_version' => 1,
                'enabled' => true,
            ]);

            foreach ($metrics as $sort => $metric) {
                $provider->definitions()->create([
                    'slug' => $metric['slug'],
                    'label' => $metric['label'],
                    'pointer' => $metric['pointer'],
                    'formatter' => $metric['formatter'],
                    'sort' => $sort,
                ]);
            }

            return $provider->load('definitions');
        });
    }

    private function ensureLimit(User $user): void
    {
        $limit = config('badgeforge.custom_providers.limit');

        if ($limit === null) {
            return;
        }

        $current = $user->badgeCustomProviders()->count();

        if ($current >= (int) $limit) {
            $this->guard->fail('name', 'limit');
        }
    }

    /**
     * @return list<string>|null
     */
    private function pathParams(mixed $params): ?array
    {
        if (! is_array($params) || $params === []) {
            return null;
        }

        $names = [];

        foreach ($params as $param) {
            if (! is_string($param) || $param === '') {
                return null;
            }

            $names[] = $param;
        }

        return $names;
    }
}
