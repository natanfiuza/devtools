import { formatCssOklch, cssFallback } from './colors';
import { LAYOUT_CSS_FILENAME } from './filename';
import { escapeCssComment, message } from './messages';
import { layoutCssSelectors } from './options';
import {
    LAYOUT_EXPORT_MIME,
    type LayoutCssOptions,
    type LayoutExportFile,
    type LayoutExportInput,
} from './types';
import { buildLayoutExportWarnings } from './warnings';
import type { LayoutTheme } from '../engine';
import type { LayoutSnapshotToken } from '../lib/snapshot';

/**
 * CSS do sistema de layout (F5, ADR-155/ADR-176).
 *
 * Claro no escopo base (sem atributo e `data-theme="light"`), escuro em
 * `data-theme="dark"`. O bloco base entrega HEX/rgba e o `@supports`
 * redeclara tudo em OKLCH quando o navegador entende o formato. Os seletores
 * e o `@supports` sao emitidos pelo codigo, nunca vindos de `lang/`.
 */

function declarationLines(
    input: LayoutExportInput,
    theme: LayoutTheme,
    prefix: string,
    indent: string,
    value: (token: LayoutSnapshotToken) => string,
): string[] {
    const board = input.state.snapshot.themes.find(
        (entry) => entry.theme === theme,
    );

    return (board?.tokens ?? []).map(
        (token) => `${indent}--${prefix}-${token.id}: ${value(token)};`,
    );
}

export function buildLayoutCss(
    input: LayoutExportInput,
    options: LayoutCssOptions,
): LayoutExportFile {
    const snapshot = input.state.snapshot;
    const prefix = options.prefix.trim();
    const selectors = layoutCssSelectors(options.scope);
    const lines: string[] = [];

    lines.push(
        `/* ${message(input.messages, 'export.css.header', {
            name: escapeCssComment(input.name),
        })} */`,
    );
    lines.push(
        `/* ${message(input.messages, 'export.css.meta', {
            state: input.state.stateVersion,
            language: input.language,
            schema: snapshot.schemaVersion,
            algorithm: snapshot.algorithmVersion,
            suggestions: snapshot.suggestionsVersion,
            snapshot: snapshot.snapshotVersion,
        })} */`,
    );
    lines.push(`/* ${message(input.messages, 'export.css.theme_default')} */`);

    for (const theme of ['light', 'dark'] as const) {
        lines.push('');
        lines.push(
            theme === 'light' ? `${selectors.light} {` : `${selectors.dark} {`,
        );
        lines.push(
            ...declarationLines(input, theme, prefix, '    ', (token) =>
                cssFallback(token.rgba, token.hex),
            ),
        );
        lines.push('}');
    }

    lines.push('');
    lines.push('@supports (color: oklch(0 0 0)) {');

    for (const theme of ['light', 'dark'] as const) {
        lines.push(
            `    ${theme === 'light' ? selectors.light : selectors.dark} {`,
        );
        lines.push(
            ...declarationLines(input, theme, prefix, '        ', (token) =>
                formatCssOklch(token.oklch, token.rgba.a),
            ),
        );
        lines.push('    }');
    }

    lines.push('}');

    const warnings = buildLayoutExportWarnings(input);

    lines.push('');

    if (warnings.length === 0) {
        lines.push(`/* ${message(input.messages, 'export.warnings.none')} */`);
    } else {
        lines.push(`/* ${message(input.messages, 'export.warnings.title')}`);

        for (const warning of warnings) {
            lines.push(`   - ${escapeCssComment(warning)}`);
        }

        lines.push('*/');
    }

    return {
        format: 'css',
        filename: LAYOUT_CSS_FILENAME,
        mime: LAYOUT_EXPORT_MIME.css,
        contents: `${lines.join('\n')}\n`,
    };
}
