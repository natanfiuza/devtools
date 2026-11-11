import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import TagInput from '@/components/colors/TagInput.vue';
import { applyTranslations, createI18nInstance } from '@/lib/i18n';
import type { PaletteTag } from '@/types/colors';

/**
 * Campo de tags do modal de salvar (F4, ADR-103).
 *
 * O componente e controlado: ele nunca guarda a lista, so emite
 * `update:modelValue`. Normalizacao, limite e sugestoes sao o que importa
 * aqui; o modal e quem decide o que vai no payload.
 */

const TAGS: PaletteTag[] = [
    { id: 'tag-web', name: 'web' },
    { id: 'tag-marca', name: 'Marca' },
];

function i18n() {
    const instance = createI18nInstance();

    applyTranslations(instance, {
        locale: 'pt-BR',
        translations: {
            colors: {
                form: {
                    tags_label: 'Tags',
                    tags_placeholder: 'Adicionar uma tag',
                    tags_hint: '{count}/{max} tags · até {length} caracteres',
                    tags_limit: 'Você atingiu o limite de tags ({max}).',
                    tags_too_long: 'Uma tag pode ter até {length} caracteres.',
                    tags_remove: 'Remover {name}',
                    tags_suggestions: 'Suas tags:',
                },
            },
        },
    });

    return instance;
}

function mountTags(modelValue: string[] = [], max = 3) {
    return mount(TagInput, {
        props: {
            id: 'tags',
            modelValue,
            suggestions: TAGS,
            max,
            nameMaxLength: 30,
        },
        global: { plugins: [i18n()] },
    });
}

type Wrapper = ReturnType<typeof mountTags>;

function lastEmitted(wrapper: Wrapper): string[] | undefined {
    const events = wrapper.emitted('update:modelValue') ?? [];

    return events[events.length - 1]?.[0] as string[] | undefined;
}

async function type(wrapper: Wrapper, value: string): Promise<void> {
    await wrapper.get('[data-test="tag-input-field"]').setValue(value);
}

describe('TagInput', () => {
    it('acrescenta no Enter, normaliza espaços e limpa o campo', async () => {
        const wrapper = mountTags();

        await type(wrapper, '  design   system ');
        await wrapper
            .get('[data-test="tag-input-field"]')
            .trigger('keydown', { key: 'Enter' });

        expect(lastEmitted(wrapper)).toEqual(['design system']);
        expect(
            (
                wrapper.get('[data-test="tag-input-field"]')
                    .element as HTMLInputElement
            ).value,
        ).toBe('');
    });

    it('acrescenta na vírgula', async () => {
        const wrapper = mountTags();

        await type(wrapper, 'cores');
        await wrapper
            .get('[data-test="tag-input-field"]')
            .trigger('keydown', { key: ',' });

        expect(lastEmitted(wrapper)).toEqual(['cores']);
    });

    it('ignora tag repetida sem diferenciar caixa', async () => {
        const wrapper = mountTags(['web']);

        await type(wrapper, 'WEB');
        await wrapper
            .get('[data-test="tag-input-field"]')
            .trigger('keydown', { key: 'Enter' });

        expect(wrapper.emitted('update:modelValue')).toBeUndefined();
    });

    it('Backspace no campo vazio remove a última tag', async () => {
        const wrapper = mountTags(['a', 'b']);

        await wrapper
            .get('[data-test="tag-input-field"]')
            .trigger('keydown', { key: 'Backspace' });

        expect(lastEmitted(wrapper)).toEqual(['a']);
    });

    it('Backspace com texto digitado não remove nada', async () => {
        const wrapper = mountTags(['a']);

        await type(wrapper, 'x');
        await wrapper
            .get('[data-test="tag-input-field"]')
            .trigger('keydown', { key: 'Backspace' });

        expect(wrapper.emitted('update:modelValue')).toBeUndefined();
    });

    it('respeita o limite de tags por paleta', async () => {
        const wrapper = mountTags(['a', 'b', 'c'], 3);

        await type(wrapper, 'd');
        await wrapper
            .get('[data-test="tag-input-field"]')
            .trigger('keydown', { key: 'Enter' });

        expect(wrapper.emitted('update:modelValue')).toBeUndefined();
        expect(wrapper.get('[data-test="tag-input-message"]').text()).toContain(
            'limite de tags (3)',
        );
    });

    it('recusa tag maior que o nome permitido', async () => {
        const wrapper = mountTags();

        await type(wrapper, 'x'.repeat(31));
        await wrapper.get('[data-test="tag-input-field"]').trigger('blur');

        expect(wrapper.emitted('update:modelValue')).toBeUndefined();
        expect(wrapper.get('[data-test="tag-input-message"]').text()).toContain(
            'até 30',
        );
    });

    it('commita o rascunho ao sair do campo', async () => {
        const wrapper = mountTags();

        await type(wrapper, 'oklch');
        await wrapper.get('[data-test="tag-input-field"]').trigger('blur');

        expect(lastEmitted(wrapper)).toEqual(['oklch']);
    });

    it('sugestões já usadas somem e clicar acrescenta', async () => {
        const wrapper = mountTags(['web']);

        const suggestions = wrapper.findAll('[data-test="tag-suggestion"]');

        expect(suggestions).toHaveLength(1);
        expect(suggestions[0]?.text()).toBe('Marca');

        await suggestions[0]?.trigger('click');

        expect(lastEmitted(wrapper)).toEqual(['web', 'Marca']);
    });

    it('remove uma tag pelo chip', async () => {
        const wrapper = mountTags(['web', 'Marca']);

        const chips = wrapper.findAll('[data-test="tag-chip"]');

        expect(chips).toHaveLength(2);
        expect(
            chips[0]
                ?.get('[data-test="tag-chip-remove"]')
                .attributes('aria-label'),
        ).toBe('Remover web');

        await chips[0]?.get('[data-test="tag-chip-remove"]').trigger('click');

        expect(lastEmitted(wrapper)).toEqual(['Marca']);
    });

    it('mostra o contador e as tags escolhidas', async () => {
        const wrapper = mountTags(['web', 'Marca']);

        expect(wrapper.text()).toContain('2/3 tags · até 30 caracteres');
        expect(
            wrapper
                .findAll('[data-test="tag-chip"]')
                .map((chip) => chip.text()),
        ).toEqual(['web ×', 'Marca ×']);
    });
});
