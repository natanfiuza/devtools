<?php

namespace App\Actions\BadgeForge;

use App\Models\BadgeCustomProvider;
use App\Support\BadgeForge\Custom\CustomMetricInput;
use App\Support\BadgeForge\Custom\CustomProviderGuard;
use App\Support\BadgeForge\Custom\CustomProviderProbe;
use App\Support\BadgeForge\Custom\CustomProviderUrlTemplate;
use App\Support\BadgeForge\Custom\JsonPointer;
use Illuminate\Support\Facades\DB;

/**
 * Edita nome e metricas de um provider customizado (F7, ADR-087).
 *
 * Template e conjunto de slugs sao imutaveis. Label, pointer e formatter
 * mudam e o probe roda de novo; `metrics_version` sobe para que o cache
 * antigo deixe de ser lido. Tudo em uma transacao.
 */
final class UpdateBadgeCustomProvider
{
    public function __construct(
        private readonly CustomProviderGuard $guard,
        private readonly CustomProviderProbe $probe,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function __invoke(BadgeCustomProvider $provider, array $data): BadgeCustomProvider
    {
        $metrics = CustomMetricInput::listFrom($data['metrics'] ?? null);

        if ($metrics === null) {
            $this->guard->fail('metrics', 'metrics');
        }

        $definitions = $provider->definitions()->get()->keyBy('slug');
        $incomingSlugs = array_column($metrics, 'slug');
        $existingSlugs = $definitions->keys()->map(static fn (mixed $slug): string => (string) $slug)->all();

        sort($incomingSlugs);
        sort($existingSlugs);

        if ($incomingSlugs !== $existingSlugs) {
            $this->guard->fail('metrics', 'metrics_fixed');
        }

        $template = CustomProviderUrlTemplate::from((string) $provider->url_template);

        if ($template === null) {
            $this->guard->fail('url_template', 'template');
        }

        foreach ($metrics as $metric) {
            if (! JsonPointer::isValid($metric['pointer'])) {
                $this->guard->fail('metrics', 'pointer');
            }
        }

        $this->probe->probe(
            $template,
            $provider->pathParamNames(),
            (string) $provider->example_path,
            $metrics,
        );

        return DB::transaction(function () use ($provider, $data, $metrics, $definitions): BadgeCustomProvider {
            $provider->forceFill([
                'name' => (string) ($data['name'] ?? $provider->name),
                'metrics_version' => max(1, (int) $provider->metrics_version) + 1,
            ])->save();

            foreach ($metrics as $sort => $metric) {
                $definition = $definitions->get($metric['slug']);

                if ($definition === null) {
                    continue;
                }

                $definition->forceFill([
                    'label' => $metric['label'],
                    'pointer' => $metric['pointer'],
                    'formatter' => $metric['formatter'],
                    'sort' => $sort,
                ])->save();
            }

            return $provider->load('definitions');
        });
    }
}
