import { describe, expect, it } from 'vitest';
import { categoryById, categoryOrder } from '@/tools/categories';
import { hasIcon } from '@/tools/icons';
import {
    allTools,
    availableTools,
    categoriesInUse,
    statusKey,
    toolKeys,
    toolPath,
    toolsByCategory,
} from '@/tools/registry';
import { normalize, searchTools, type TranslateFn } from '@/tools/search';
import type { ToolManifest } from '@/tools/types';

/**
 * Registro de tools: o que a home, a busca e o sitemap assumem sobre os
 * `manifest.json`. A contraparte PHP (App\Support\Tools) e testada em
 * tests/Feature/Tools/ToolsRegistryTest.php.
 */

const MVP = [
    'json-viewer',
    'ai-sql-builder',
    'json-to-code',
    'sql-formatter',
    'base64-url-hash',
    'jwt-decoder',
    'timestamp-uuid',
    'regex-tester',
];

/** Tool registrada por indice, com guarda: `noUncheckedIndexedAccess` esta ligado. */
function toolAt(index: number): ToolManifest {
    const tool = allTools[index];

    if (tool === undefined) {
        throw new Error(`Tool ${index} nao registrada`);
    }

    return tool;
}

describe('registro', () => {
    it('le as 8 tools do MVP na ordem de exibicao', () => {
        expect(allTools.map((tool) => tool.slug)).toEqual(MVP);
    });

    it('deixa todas as tools no ciclo "soon" na F1', () => {
        expect(allTools.every((tool) => tool.status === 'soon')).toBe(true);
        expect(availableTools).toEqual([]);
    });

    it('registra so uma tool por slug', () => {
        const slugs = allTools.map((tool) => tool.slug);

        expect(new Set(slugs).size).toBe(slugs.length);
    });

    it('aponta icone e categoria que existem', () => {
        for (const tool of allTools) {
            expect(hasIcon(tool.icon), `icone ${tool.icon}`).toBe(true);
            expect(
                categoryById(tool.category),
                `categoria ${tool.category}`,
            ).toBeDefined();
        }
    });

    it('lista as categorias usadas na ordem do catalogo', () => {
        const used = categoriesInUse();

        // F1: as seis categorias do catalogo ja tem tool. Quando uma categoria
        // ficar sem tool, os chips param de mostra-la e esta linha muda.
        expect(used).toEqual(categoryOrder);
        expect(new Set(used).size).toBe(used.length);

        // Chip so existe com tool: categoria sem tool nao vira filtro vazio.
        for (const category of used) {
            expect(toolsByCategory(category).length).toBeGreaterThan(0);
        }
    });

    it('nao devolve caminho enquanto a tool nao funciona', () => {
        const soon = toolAt(0);
        const available: ToolManifest = { ...soon, status: 'available' };

        expect(toolPath(soon, 'pt-br')).toBeNull();
        expect(toolPath(available, 'pt-br')).toBe(`/pt-br/tools/${soon.slug}`);
    });

    it('monta as chaves de traducao pelo slug', () => {
        const tool = toolAt(0);

        expect(toolKeys.name(tool)).toBe(`tools.${tool.slug}.name`);
        expect(toolKeys.description(tool)).toBe(
            `tools.${tool.slug}.description`,
        );
        expect(toolKeys.keywords(tool)).toBe(`tools.${tool.slug}.keywords`);
        expect(statusKey(tool)).toBe('tools.status.soon');
    });
});

/*
| A busca e testada com um registro de mentira e traducao de mentira: o que
| importa aqui e o rankeamento, o casamento sem acento e a regra de nunca
| inventar texto que nao veio do i18n.
*/
describe('busca', () => {
    const alpha: ToolManifest = {
        slug: 'alpha',
        status: 'available',
        category: 'json',
        icon: 'braces',
        order: 10,
        keywords: ['tree', 'jsonpath'],
    };
    const beta: ToolManifest = {
        slug: 'beta',
        status: 'soon',
        category: 'encode',
        icon: 'binary',
        order: 20,
        keywords: ['checksum'],
    };
    const gamma: ToolManifest = {
        slug: 'gamma',
        status: 'soon',
        category: 'json',
        icon: 'braces',
        order: 30,
        keywords: ['modelo'],
    };
    const tools = [alpha, beta, gamma];

    // So as chaves de beta e gamma: alpha nao tem traducao, de proposito.
    const messages: Record<string, string> = {
        'tools.categories.encode': 'Codificação',
        'tools.beta.name': 'Codificador',
        'tools.beta.description': 'D_BETA',
        'tools.beta.keywords': 'codificação, resumo',
        'tools.gamma.name': 'Gerador de código',
        'tools.gamma.description': 'D_GAMMA',
        'tools.gamma.keywords': 'modelo, tipos',
    };

    // O vue-i18n devolve a propria chave quando ela nao existe.
    const t: TranslateFn = (key) => messages[key] ?? key;

    it('abre com a lista inteira quando a consulta esta vazia', () => {
        expect(searchTools(tools, '', t)).toEqual(tools);
        expect(searchTools(tools, '   ', t)).toEqual(tools);
    });

    it('rankeia nome inteiro antes de nome parcial e de palavra-chave', () => {
        // "cod": beta comeca com o termo, gamma contem.
        expect(searchTools(tools, 'cod', t).map((tool) => tool.slug)).toEqual([
            'beta',
            'gamma',
        ]);
    });

    it('casa palavra-chave do manifest', () => {
        expect(
            searchTools(tools, 'jsonpath', t).map((tool) => tool.slug),
        ).toEqual(['alpha']);
    });

    it('ignora acento e caixa', () => {
        expect(normalize('Codificação')).toBe('codificacao');
        expect(
            searchTools(tools, 'CODIFICACAO', t).map((tool) => tool.slug),
        ).toEqual(['beta']);
    });

    it('nao inventa texto a partir da chave de traducao', () => {
        // Sem traducao para alpha, a chave `tools.alpha.name` nao pode virar
        // texto de busca - senao "alpha" acharia a tool pelo slug.
        expect(searchTools(tools, 'alpha', t)).toEqual([]);
    });

    it('devolve vazio quando nada casa', () => {
        expect(searchTools(tools, 'kubernetes', t)).toEqual([]);
    });

    it('acha a tool "soon" (ela so nao navega)', () => {
        const found = searchTools(tools, 'codificador', t);

        expect(found[0]?.slug).toBe('beta');
        expect(toolPath(beta, 'pt-br')).toBeNull();
    });
});
