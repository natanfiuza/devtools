import { execFileSync } from 'node:child_process';
import { resolve } from 'node:path';

/**
 * Le as traducao de `lang/` como o HandleInertiaRequests faz: `config/locales.php`
 * mais um arquivo por namespace, sem bootstrap do Laravel.
 *
 * Tipos e leitura ficam num helper porque mais de um teste compara o que o
 * front usa com o que o PHP realmente entrega.
 */

export type NamespaceMessages = Record<string, unknown>;
export type ExportedMessages = Record<
    string,
    Record<string, NamespaceMessages>
>;

const root = resolve(process.cwd());

const phpScript = String.raw`
$root = $argv[1];
$config = require $root . '/config/locales.php';

$messages = [];

foreach (array_keys($config['supported']) as $locale) {
    $messages[$locale] = [];

    foreach ($config['namespaces'] as $namespace) {
        $path = $root . '/lang/' . $locale . '/' . $namespace . '.php';

        if (! is_file($path)) {
            continue;
        }

        $contents = require $path;
        $messages[$locale][$namespace] = is_array($contents) ? $contents : [];
    }
}

echo json_encode(
    $messages,
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
);
`;

/**
 * Bloco `tools.layout-harmonizer` de cada locale, no shape de
 * `LayoutSystemExportMessages::all()`.
 *
 * O servidor enxuga o subtree; aqui o bloco inteiro serve porque os
 * exportadores so leem chaves e o teste de paridade cobre as cinco locales.
 */
export function layoutExportMessages(): Record<
    string,
    Record<string, unknown>
> {
    const all = exportMessages();
    const result: Record<string, Record<string, unknown>> = {};

    for (const [locale, namespaces] of Object.entries(all)) {
        const block = namespaces.tools?.['layout-harmonizer'];

        if (typeof block === 'object' && block !== null) {
            result[locale] = block as Record<string, unknown>;
        }
    }

    return result;
}

let cached: ExportedMessages | null = null;

export function exportMessages(): ExportedMessages {
    if (cached !== null) {
        return cached;
    }

    let raw: string;

    try {
        raw = execFileSync('php', ['-r', phpScript, root], {
            encoding: 'utf8',
            maxBuffer: 32 * 1024 * 1024,
        });
    } catch (error) {
        const stderr =
            error instanceof Error && 'stderr' in error
                ? String(error.stderr ?? '')
                : '';

        throw new Error(
            `Falha ao ler lang/ com php -r (o PHP precisa estar no PATH): ${stderr.trim()}`,
            { cause: error },
        );
    }

    try {
        // O `lang/` nao muda durante a suite: uma leitura por arquivo de teste
        // evita um spawn de PHP por caso (que estourava os 5 s no suite cheio).
        cached = JSON.parse(raw) as ExportedMessages;

        return cached;
    } catch (error) {
        throw new Error(
            `Saida invalida do PHP ao ler lang/: ${raw.slice(0, 200)}`,
            { cause: error },
        );
    }
}
