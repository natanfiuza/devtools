import { formatColor, formatHex, type Rgba } from '@/lib/color';
import { formatRatio } from '../lib/boardView';
import { LAYOUT_THEMES, type LayoutTheme } from '../engine';
import type { LayoutSnapshot } from '../lib/snapshot';
import { formatCssOklch } from './colors';
import { markdownFileName } from './filename';
import { escapeMarkdown, message } from './messages';
import {
    LAYOUT_EXPORT_MIME,
    type LayoutExportFile,
    type LayoutExportInput,
} from './types';
import { buildLayoutExportWarnings } from './warnings';

/**
 * Documento Markdown de instrucoes para IA (F5, ADR-148/ADR-177).
 *
 * Autossuficiente e gerado no idioma escolhido: metadados e versoes, origem
 * apenas quando o credito publico existe, instrucoes de uso, os 33 tokens de
 * cada tema com finalidade, contrastes, avisos e a nota de geracao. Nenhum
 * valor e recalculado e nenhuma data entra no texto.
 */

function themeLabel(input: LayoutExportInput, theme: LayoutTheme): string {
    return message(input.messages, `view.board_${theme}`);
}

function targetLabel(
    snapshot: LayoutSnapshot,
    input: LayoutExportInput,
): string {
    return message(input.messages, `export.targets.${snapshot.target}`);
}

function seedText(color: Rgba): string {
    return formatColor(color, color.a < 1 ? 'rgba' : 'hex');
}

function metadataLines(
    input: LayoutExportInput,
    snapshot: LayoutSnapshot,
): string[] {
    const lines = [
        `- ${message(input.messages, 'export.md.language', {
            language: input.language,
        })}`,
        `- ${message(input.messages, 'export.md.strategy', {
            strategy: message(
                input.messages,
                `strategy.${snapshot.strategy}.name`,
            ),
        })}`,
        `- ${message(input.messages, 'export.md.target', {
            target: targetLabel(snapshot, input),
        })}`,
        `- ${message(input.messages, 'export.md.seed_original', {
            hex: seedText(snapshot.seed.original),
        })}`,
        `- ${message(input.messages, 'export.md.seed_effective', {
            hex: formatHex(snapshot.seed.effective),
        })}`,
    ];

    if (snapshot.seed.background !== null) {
        lines.push(
            `- ${message(input.messages, 'export.md.seed_background', {
                hex: formatHex({ ...snapshot.seed.background, a: 1 }),
            })}`,
        );
    }

    lines.push(
        `- ${message(input.messages, 'export.md.secondary', {
            mode: message(
                input.messages,
                `export.md.secondary_${snapshot.secondarySource}`,
            ),
        })}`,
    );

    // Secundaria com alpha: o original nunca e descartado no arquivo exportado.
    if (snapshot.secondary && snapshot.secondary.original.a < 1) {
        lines.push(
            `- ${message(input.messages, 'export.md.secondary_original', {
                hex: seedText(snapshot.secondary.original),
            })}`,
            `- ${message(input.messages, 'export.md.secondary_effective', {
                hex: formatHex(snapshot.secondary.effective),
            })}`,
            `- ${message(input.messages, 'export.md.secondary_background', {
                hex: snapshot.secondary.background
                    ? formatHex({ ...snapshot.secondary.background, a: 1 })
                    : '',
            })}`,
        );
    }

    lines.push(
        `- ${message(input.messages, 'export.md.versions', {
            state: input.state.stateVersion,
            schema: snapshot.schemaVersion,
            algorithm: snapshot.algorithmVersion,
            suggestions: snapshot.suggestionsVersion,
            snapshot: snapshot.snapshotVersion,
        })}`,
    );

    if (input.origin !== null && input.origin.public) {
        lines.push(
            `- ${message(input.messages, 'export.md.origin', {
                name: escapeMarkdown(input.origin.name),
            })}`,
        );
    }

    return lines;
}

function tokenTable(
    input: LayoutExportInput,
    snapshot: LayoutSnapshot,
    theme: LayoutTheme,
): string[] {
    const board = snapshot.themes.find((entry) => entry.theme === theme);
    const locked = new Set(
        snapshot.locks.map((lock) => `${lock.theme}:${lock.token}`),
    );
    const header = [
        message(input.messages, 'export.md.columns.token'),
        message(input.messages, 'export.md.columns.purpose'),
        message(input.messages, 'export.md.columns.hex'),
        message(input.messages, 'export.md.columns.oklch'),
        message(input.messages, 'export.md.columns.source'),
        message(input.messages, 'export.md.columns.locked'),
    ].map(escapeMarkdown);
    const lines = [
        `| ${header.join(' | ')} |`,
        `| ${header.map(() => '---').join(' | ')} |`,
    ];

    for (const token of board?.tokens ?? []) {
        const cells = [
            escapeMarkdown(token.id),
            escapeMarkdown(message(input.messages, `tokens.${token.id}`)),
            token.hex,
            formatCssOklch(token.oklch, token.rgba.a),
            escapeMarkdown(
                message(input.messages, `provenance.${token.source}`),
            ),
            escapeMarkdown(
                message(
                    input.messages,
                    locked.has(`${theme}:${token.id}`)
                        ? 'export.md.locked_yes'
                        : 'export.md.locked_no',
                ),
            ),
        ];

        lines.push(`| ${cells.join(' | ')} |`);
    }

    return lines;
}

function contrastTable(
    input: LayoutExportInput,
    snapshot: LayoutSnapshot,
    theme: LayoutTheme,
): string[] {
    const board = snapshot.themes.find((entry) => entry.theme === theme);
    const header = [
        message(input.messages, 'export.md.columns.foreground'),
        message(input.messages, 'export.md.columns.background'),
        message(input.messages, 'export.md.columns.ratio'),
        message(input.messages, 'export.md.columns.aa'),
        message(input.messages, 'export.md.columns.aaa'),
    ].map(escapeMarkdown);
    const pass = escapeMarkdown(message(input.messages, 'export.md.pass'));
    const fail = escapeMarkdown(message(input.messages, 'export.md.fail'));
    const lines = [
        `| ${header.join(' | ')} |`,
        `| ${header.map(() => '---').join(' | ')} |`,
    ];

    for (const check of board?.checks ?? []) {
        const cells = [
            escapeMarkdown(check.foreground),
            escapeMarkdown(check.background),
            formatRatio(check.verdict.ratio, input.language),
            check.verdict.aaNormal ? pass : fail,
            check.verdict.aaaNormal ? pass : fail,
        ];

        lines.push(`| ${cells.join(' | ')} |`);
    }

    return lines;
}

export function buildLayoutMarkdown(
    input: LayoutExportInput,
): LayoutExportFile {
    const snapshot = input.state.snapshot;
    const warnings = buildLayoutExportWarnings(input);
    const lines: string[] = [];

    lines.push(`# ${escapeMarkdown(input.name)}`);
    lines.push('');
    lines.push(message(input.messages, 'export.md.purpose'));
    lines.push('');
    lines.push(`## ${message(input.messages, 'export.md.metadata_title')}`);
    lines.push('');
    lines.push(...metadataLines(input, snapshot));
    lines.push('');
    lines.push(`## ${message(input.messages, 'export.md.instructions_title')}`);
    lines.push('');
    lines.push(message(input.messages, 'export.md.instructions_intro'));
    lines.push('');
    lines.push(
        `- ${message(input.messages, 'export.md.instructions_semantic')}`,
    );
    lines.push(`- ${message(input.messages, 'export.md.instructions_manual')}`);
    lines.push(
        `- ${message(input.messages, 'export.md.instructions_contrast', {
            target: targetLabel(snapshot, input),
        })}`,
    );
    lines.push(`- ${message(input.messages, 'export.md.instructions_map')}`);
    lines.push('');
    lines.push(`## ${message(input.messages, 'export.md.themes_title')}`);
    lines.push('');
    lines.push(message(input.messages, 'export.md.themes_note'));

    for (const theme of LAYOUT_THEMES) {
        lines.push('');
        lines.push(
            `## ${message(input.messages, 'export.md.tokens_title', {
                theme: themeLabel(input, theme),
            })}`,
        );
        lines.push('');
        lines.push(...tokenTable(input, snapshot, theme));
    }

    lines.push('');
    lines.push(`## ${message(input.messages, 'export.md.rules_title')}`);
    lines.push('');

    for (const rule of [
        'backgrounds',
        'text',
        'buttons',
        'links',
        'feedback',
        'focus',
    ]) {
        lines.push(`- ${message(input.messages, `export.md.rules.${rule}`)}`);
    }

    for (const theme of LAYOUT_THEMES) {
        lines.push('');
        lines.push(
            `## ${message(input.messages, 'export.md.contrast_title', {
                theme: themeLabel(input, theme),
            })}`,
        );
        lines.push('');
        lines.push(...contrastTable(input, snapshot, theme));
    }

    lines.push('');
    lines.push(`## ${message(input.messages, 'export.warnings.title')}`);
    lines.push('');

    if (warnings.length === 0) {
        lines.push(`- ${message(input.messages, 'export.warnings.none')}`);
    } else {
        for (const warning of warnings) {
            lines.push(`- ${warning}`);
        }
    }

    lines.push(`- ${message(input.messages, 'export.warnings.limitations')}`);
    lines.push('');
    lines.push(`## ${message(input.messages, 'export.md.generated_title')}`);
    lines.push('');
    lines.push(
        message(input.messages, 'export.md.generation_note', {
            state: input.state.stateVersion,
            snapshot: snapshot.snapshotVersion,
        }),
    );

    return {
        format: 'markdown',
        filename: markdownFileName(input.language),
        mime: LAYOUT_EXPORT_MIME.markdown,
        contents: `${lines.join('\n')}\n`,
    };
}
