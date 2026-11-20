<?php

namespace App\Support\Colors;

use App\Support\Locales;
use Illuminate\Support\Arr;

/**
 * Traducoes do Layout Harmonizer entregues ao navegador para exportar (F5).
 *
 * O cliente recebe so o idioma ativo; o documento Markdown e os comentarios do
 * CSS podem sair em qualquer um dos cinco idiomas. O servidor manda, para
 * cada locale, apenas o subtree que os exportadores usam - `export` (novo),
 * os nomes dos tokens, estrategias, proveniencia, familias, rotulos de tema,
 * contrastes e o resumo da seed. O resto de `tools` fica de fora para o
 * payload nao crescer a cada chave nova de tela.
 */
final class LayoutSystemExportMessages
{
    /**
     * Subarvores copiadas inteiras do grupo `tools.layout-harmonizer`.
     *
     * @var list<string>
     */
    private const BLOCKS = [
        'export',
        'tokens',
        'strategy',
        'provenance',
        'families',
        'view',
        'contrast',
    ];

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function all(): array
    {
        $messages = [];

        foreach (Locales::codes() as $locale) {
            $block = trans('tools.layout-harmonizer', [], $locale);
            $block = is_array($block) ? $block : [];

            $messages[$locale] = [
                ...Arr::only($block, self::BLOCKS),
                'seed' => [
                    'summary' => is_array($block['seed'] ?? null)
                        ? Arr::only($block['seed'], ['summary'])
                        : [],
                ],
            ];
        }

        return $messages;
    }
}
