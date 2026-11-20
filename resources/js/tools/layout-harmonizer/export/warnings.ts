import { formatHex } from '@/lib/color';
import { formatRatio } from '../lib/boardView';
import { hydrationMode } from '../lib/state';
import type { LayoutTheme } from '../engine';
import { message } from './messages';
import type { LayoutExportInput } from './types';

/**
 * Avisos das exportacoes (F5, ADR-177).
 *
 * Markdown e CSS listam os mesmos avisos: falhas de contraste medidas contra
 * `snapshot.target` (o snapshot guarda `diagnostics` so de AA, por isso os
 * checks sao filtrados pelo alvo), bloqueios, valores manuais, ajustes
 * aceitos, cromas reduzidos e a versao do estado. Nunca bloqueiam a
 * exportacao: aparecem no arquivo.
 */

function themeLabel(input: LayoutExportInput, theme: LayoutTheme): string {
    return message(input.messages, `view.board_${theme}`);
}

function targetLabel(input: LayoutExportInput): string {
    return message(
        input.messages,
        `export.targets.${input.state.snapshot.target}`,
    );
}

/** Falha no alvo escolhido; `diagnostics` e insuficiente no alvo AAA. */
function failsTarget(
    verdict: { aaNormal: boolean; aaaNormal: boolean },
    target: 'aa' | 'aaa',
): boolean {
    return target === 'aa' ? !verdict.aaNormal : !verdict.aaaNormal;
}

export function buildLayoutExportWarnings(input: LayoutExportInput): string[] {
    const snapshot = input.state.snapshot;
    const ratioLocale = input.language;
    const target = targetLabel(input);
    const lines: string[] = [];
    const mode = hydrationMode(input.state);

    for (const board of snapshot.themes) {
        for (const check of board.checks) {
            if (!failsTarget(check.verdict, snapshot.target)) {
                continue;
            }

            lines.push(
                message(input.messages, 'export.warnings.contrast', {
                    theme: themeLabel(input, board.theme),
                    foreground: check.foreground,
                    background: check.background,
                    ratio: formatRatio(check.verdict.ratio, ratioLocale),
                    target,
                }),
            );
        }
    }

    for (const lock of snapshot.locks) {
        lines.push(
            message(input.messages, 'export.warnings.locked', {
                theme: themeLabel(input, lock.theme),
                token: lock.token,
            }),
        );
    }

    for (const override of snapshot.overrides) {
        if (override.origin !== 'edit') {
            continue;
        }

        lines.push(
            message(input.messages, 'export.warnings.manual', {
                theme: themeLabel(input, override.theme),
                token: override.token,
                hex: override.hex,
            }),
        );
    }

    for (const adjustment of snapshot.acceptedAdjustments) {
        lines.push(
            message(input.messages, 'export.warnings.accepted', {
                theme: themeLabel(input, adjustment.theme),
                token: adjustment.token,
                from: formatHex({ ...adjustment.from, a: 1 }),
                to: formatHex({ ...adjustment.to, a: 1 }),
                target: message(
                    input.messages,
                    `export.targets.${adjustment.target}`,
                ),
            }),
        );
    }

    for (const board of snapshot.themes) {
        for (const token of board.tokens) {
            if (!token.clipped) {
                continue;
            }

            lines.push(
                message(input.messages, 'export.warnings.clipped', {
                    theme: themeLabel(input, board.theme),
                    token: token.id,
                }),
            );
        }
    }

    if (mode === 'compatible') {
        lines.push(
            message(input.messages, 'export.warnings.compatible', {
                algorithm: snapshot.algorithmVersion,
                suggestions: snapshot.suggestionsVersion,
            }),
        );
    }

    if (mode === 'readonly') {
        lines.push(
            message(input.messages, 'export.warnings.readonly', {
                schema: snapshot.schemaVersion,
                algorithm: snapshot.algorithmVersion,
                suggestions: snapshot.suggestionsVersion,
            }),
        );
    }

    return lines;
}
