import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { mount } from '@vue/test-utils';
import { nextTick } from 'vue';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import TokenImportDialog from '@/components/colors/TokenImportDialog.vue';
import { applyTranslations, createI18nInstance } from '@/lib/i18n';
import type { TokenImportLimits } from '@/types/colors';

/**
 * Dialogo de importacao de tokens (F5, Etapa 6).
 *
 * O parser tem teste proprio; aqui o que importa e a tela: escolher o arquivo
 * nao altera nada, o diagnostico aparece antes de aplicar, o teto de exibicao
 * vale e "Aplicar" so emite quando a leitura terminou sem erro.
 */

const FIXTURES = resolve(process.cwd(), 'tests/Fixtures/colors/tokens');

const LIMITS: TokenImportLimits = {
    maxBytes: 512 * 1024,
    maxTokens: 5000,
    maxDepth: 40,
    maxReferenceDepth: 10,
    maxDiagnostics: 20,
};

function fixture(name: string): string {
    return readFileSync(resolve(FIXTURES, name), 'utf8');
}

function i18n() {
    const instance = createI18nInstance();

    applyTranslations(instance, {
        locale: 'pt-BR',
        translations: {
            tools: {
                shared: {
                    token_import: {
                        button: 'Importar tokens',
                        title: 'Importar tokens de design',
                        description: 'Leia um JSON no navegador.',
                        choose: 'Escolher um arquivo JSON',
                        hint: 'Até {size}.',
                        reading: 'Lendo o arquivo…',
                        file_errors: {
                            file_too_large: 'O arquivo passa do limite de {limit}.',
                            file_type: 'Escolha um arquivo .json.',
                            file_unreadable:
                                'Não foi possível ler o arquivo. Tente outro.',
                        },
                        diagnostics: {
                            title: 'Diagnóstico',
                            errors: 'Erros',
                            warnings: 'Avisos',
                            hidden: 'Mais {count} diagnóstico(s) não exibidos.',
                            path: 'em {path}',
                        },
                        preview: {
                            title: 'Prévia',
                            format: 'Formato',
                            kind: 'Tipo',
                            base: 'Cor base',
                            colors_label: 'Cores',
                            colors: '{count} cores',
                            roles: 'Papéis semânticos incluídos',
                            no_roles: 'Sem papéis semânticos completos',
                            name: 'Nome no arquivo: {name}',
                            filename: 'Arquivo: {name}',
                            apply: 'Aplicar',
                            cancel: 'Cancelar',
                            replace: 'Escolher outro arquivo',
                            apply_hint: 'A paleta atual só muda ao aplicar.',
                        },
                        formats: {
                            native: 'JSON da ferramenta',
                            dtcg: 'W3C DTCG',
                            'style-dictionary': 'Style Dictionary',
                        },
                        kinds: {
                            scale: 'Escala de 11 tons',
                            extracted: 'Paleta extraída',
                        },
                        codes: {
                            empty_file: 'O arquivo está vazio.',
                            too_large: 'O arquivo passa do limite de {max}.',
                            invalid_json:
                                'O JSON não é válido (linha {line}, coluna {column}).',
                            root_not_object:
                                'A raiz do arquivo precisa ser um objeto JSON.',
                            unsupported_format:
                                'Nenhum token de cor reconhecível foi encontrado.',
                            ambiguous_format:
                                'O arquivo mistura mais de um estilo de token.',
                            unsupported_format_version:
                                'A versão do formato ({found}) não é compatível; esta ferramenta lê a versão {expected}.',
                            too_deep:
                                'A estrutura passa da profundidade máxima de {max}.',
                            too_many_tokens:
                                'O arquivo tem mais de {max} tokens.',
                            forbidden_key: 'A chave “{key}” não é permitida.',
                            mixed_token_styles:
                                'Tokens de estilos diferentes aparecem no mesmo grupo.',
                            invalid_structure:
                                'A estrutura da paleta não é reconhecível.',
                            no_colors: 'O arquivo não tem cores para importar.',
                            ambiguous_palette:
                                'Há mais de uma paleta possível; deixe apenas uma para importar.',
                            unsupported_color_count:
                                'O grupo tem {count} cores; use 5, 7, 9 ou 11.',
                            missing_tones: 'Faltam tons na escala: {missing}.',
                            invalid_color:
                                'Um valor de cor não é um HEX válido.',
                            unknown_reference:
                                'A referência “{reference}” não existe no arquivo.',
                            reference_not_color:
                                'A referência “{reference}” não aponta para uma cor.',
                            reference_cycle: 'As referências formam um ciclo.',
                            reference_depth:
                                'As referências passam da profundidade máxima de {max}.',
                            algorithm_version_mismatch:
                                'O arquivo foi gerado com o algoritmo v{found}; as cores serão mantidas como estão.',
                            non_color_ignored:
                                'Tokens que não são cor foram ignorados.',
                            semantic_incomplete:
                                'O bloco de papéis semânticos está incompleto; os papéis atuais serão mantidos.',
                            extra_colors_ignored:
                                'Cores além da paleta foram ignoradas.',
                        },
                    },
                },
            },
        },
    });

    return instance;
}

async function mountDialog(tokens: TokenImportLimits = LIMITS) {
    const wrapper = mount(TokenImportDialog, {
        props: { open: true, tokens, sourceLabelMaxLength: 80 },
        global: { plugins: [i18n()] },
        attachTo: document.body,
    });

    // O conteudo do Dialog vai por teleport e so entra no DOM no proximo tick.
    await nextTick();

    return wrapper;
}

function query<T extends HTMLElement>(selector: string): T | null {
    return document.querySelector<T>(selector);
}

async function choose(
    content: string,
    name = 'tokens.json',
    type = 'application/json',
): Promise<void> {
    const input = query<HTMLInputElement>('[data-test="token-import-file"]');

    if (input === null) {
        throw new Error('seletor de arquivo ausente');
    }

    Object.defineProperty(input, 'files', {
        value: [new File([content], name, { type })],
        configurable: true,
    });
    input.dispatchEvent(new Event('change', { bubbles: true }));
}

async function waitFor(selector: string): Promise<HTMLElement> {
    await vi.waitFor(
        () => {
            expect(query(selector)).not.toBeNull();
        },
        { timeout: 2000 },
    );

    const element = query(selector);

    if (element === null) {
        throw new Error(`elemento ausente: ${selector}`);
    }

    return element;
}

beforeEach(() => {
    document.body.innerHTML = '';
});

afterEach(() => {
    document.body.innerHTML = '';
});

describe('TokenImportDialog', () => {
    it('mostra a prévia de um arquivo válido e aplica', async () => {
        const wrapper = await mountDialog();

        await choose(fixture('native-scale.json'), 'native-scale.json');
        await waitFor('[data-test="token-import-preview"]');

        expect(query('[data-test="token-import-format"]')?.textContent).toBe(
            'JSON da ferramenta',
        );
        expect(query('[data-test="token-import-kind"]')?.textContent).toBe(
            'Escala de 11 tons',
        );
        expect(query('[data-test="token-import-base"]')?.textContent).toBe(
            '#1212aa',
        );
        expect(query('[data-test="token-import-count"]')?.textContent).toContain(
            '11',
        );
        expect(query('[data-test="token-import-roles"]')?.textContent).toContain(
            'Papéis semânticos incluídos',
        );
        expect(query('[data-test="token-import-name"]')?.textContent).toContain(
            'aurora',
        );
        expect(
            query('[data-test="token-import-filename"]')?.textContent,
        ).toContain('native-scale.json');

        const apply = query<HTMLButtonElement>(
            '[data-test="token-import-apply"]',
        );

        expect(apply?.disabled).toBe(false);
        apply?.click();
        await nextTick();

        const applied = wrapper.emitted('apply');

        expect(applied).toHaveLength(1);

        const palette = applied?.[0]?.[0] as {
            kind: string;
            base: string;
            roles: unknown;
        };

        expect(palette.kind).toBe('scale');
        expect(palette.base).toBe('#1212aa');
        expect(palette.roles).not.toBeNull();
        expect(wrapper.emitted('close')).toHaveLength(1);
    });

    it('um arquivo inválido vira diagnóstico e não aplica', async () => {
        const wrapper = await mountDialog();

        await choose(fixture('invalid-json.txt'), 'quebrado.json');
        const error = await waitFor('[data-test="token-import-error"]');

        expect(error.textContent).toContain('linha');
        expect(
            query('[data-test="token-import-preview"]'),
        ).toBeNull();

        const apply = query<HTMLButtonElement>(
            '[data-test="token-import-apply"]',
        );

        expect(apply?.disabled).toBe(true);
        apply?.click();
        await nextTick();

        expect(wrapper.emitted('apply')).toBeUndefined();
    });

    it('exibe só o teto de diagnósticos e conta os restantes', async () => {
        await mountDialog({ ...LIMITS, maxDiagnostics: 1 });

        const colors = Object.fromEntries(
            ['a', 'b', 'c', 'd', 'e'].map((key) => [
                key,
                { $value: '{color.missing.' + key + '}' },
            ]),
        );

        await choose(
            JSON.stringify({
                color: { group: { $type: 'color', ...colors } },
            }),
            'refs.json',
        );
        const error = await waitFor('[data-test="token-import-error"]');

        expect(
            error.querySelectorAll('[data-test="token-import-diagnostic"]'),
        ).toHaveLength(1);
        expect(
            query('[data-test="token-import-hidden"]')?.textContent,
        ).toContain('4');
    });

    it('recusa o arquivo grande demais antes de ler', async () => {
        await mountDialog({ ...LIMITS, maxBytes: 10 });

        await choose('{"color": {}}', 'grande.json');
        const error = await waitFor('[data-test="token-import-file-error"]');

        expect(error.textContent).toContain('limite');
        expect(
            query('[data-test="token-import-error"]'),
        ).toBeNull();
        expect(
            query('[data-test="token-import-preview"]'),
        ).toBeNull();
    });

    it('recusa um arquivo que não é JSON', async () => {
        await mountDialog();

        await choose('select 1;', 'consulta.txt', 'text/plain');
        const error = await waitFor('[data-test="token-import-file-error"]');

        expect(error.textContent).toContain('.json');
    });

    it('reabrir o diálogo limpa a leitura anterior', async () => {
        const wrapper = await mountDialog();

        await choose(fixture('native-scale.json'), 'native-scale.json');
        await waitFor('[data-test="token-import-preview"]');

        await wrapper.setProps({ open: false });
        await wrapper.setProps({ open: true });
        await nextTick();

        expect(query('[data-test="token-import-filename"]')).toBeNull();
        expect(query('[data-test="token-import-preview"]')).toBeNull();
        expect(
            query<HTMLButtonElement>('[data-test="token-import-apply"]')
                ?.disabled,
        ).toBe(true);
    });

    it('escolher outro arquivo limpa a prévia sem fechar', async () => {
        await mountDialog();

        await choose(fixture('native-scale.json'), 'native-scale.json');
        await waitFor('[data-test="token-import-preview"]');

        query<HTMLButtonElement>('[data-test="token-import-replace"]')?.click();
        await nextTick();

        expect(query('[data-test="token-import-preview"]')).toBeNull();
        expect(
            query<HTMLButtonElement>('[data-test="token-import-apply"]')
                ?.disabled,
        ).toBe(true);
        expect(query('[data-test="token-import-dialog"]')).not.toBeNull();
    });
});
