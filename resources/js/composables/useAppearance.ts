import type { ComputedRef, Ref } from 'vue';
import { computed, onMounted, ref } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { setCookie } from '@/lib/cookies';
import type { Appearance, ResolvedAppearance } from '@/types';

export type { Appearance, ResolvedAppearance };

export type UseAppearanceReturn = {
    appearance: Ref<Appearance>;
    resolvedAppearance: ComputedRef<ResolvedAppearance>;
    updateAppearance: (value: Appearance) => void;
};

const VALUES: readonly Appearance[] = ['light', 'dark', 'system'];

export function isAppearance(value: unknown): value is Appearance {
    return typeof value === 'string' && VALUES.includes(value as Appearance);
}

/** Estado compartilhado pela pagina (o <html> e a fonte da verdade). */
const appearance = ref<Appearance>('system');

/** Aplica o tema no <html>. */
export function updateTheme(value: Appearance): void {
    if (typeof window === 'undefined') {
        return;
    }

    if (value === 'system') {
        const systemTheme = window.matchMedia('(prefers-color-scheme: dark)')
            .matches
            ? 'dark'
            : 'light';

        document.documentElement.classList.toggle(
            'dark',
            systemTheme === 'dark',
        );

        return;
    }

    document.documentElement.classList.toggle('dark', value === 'dark');
}

/**
 * Tema que o servidor ja aplicou no <html> (blade). Serve para o cliente nao
 * "corrigir" a decisao do servidor quando nao ha preferencia local.
 */
function serverAppearance(): Appearance | null {
    if (typeof document === 'undefined') {
        return null;
    }

    const value = document.documentElement.dataset.appearance ?? null;

    return isAppearance(value) ? value : null;
}

/**
 * Fecha a preferencia a partir do que o cliente guardou: cookie/localStorage
 * mandam (o cookie e o mesmo que o servidor le), senao vale o que ele ja
 * renderizou.
 */
function resolveInitialAppearance(): Appearance {
    const stored = readStoredAppearance();

    return stored ?? serverAppearance() ?? 'system';
}

function readStoredAppearance(): Appearance | null {
    if (typeof window === 'undefined') {
        return null;
    }

    const value = localStorage.getItem('appearance');

    return isAppearance(value) ? value : null;
}

function prefersDark(): boolean {
    if (typeof window === 'undefined') {
        return false;
    }

    return window.matchMedia('(prefers-color-scheme: dark)').matches;
}

/**
 * Aplica o tema antes da primeira pintura e passa a acompanhar o sistema
 * enquanto a preferencia for "system".
 */
export function initializeTheme(): void {
    if (typeof window === 'undefined') {
        return;
    }

    const initial = resolveInitialAppearance();

    appearance.value = initial;
    updateTheme(initial);

    window
        .matchMedia('(prefers-color-scheme: dark)')
        .addEventListener('change', () => {
            if (appearance.value === 'system') {
                updateTheme('system');
            }
        });
}

export function useAppearance(): UseAppearanceReturn {
    const page = usePage();

    onMounted(() => {
        const stored = readStoredAppearance();

        // Sem preferencia local, a conta (coluna users.appearance) decide.
        // Vale a cada navegacao: depois do login a conta pode trazer um tema
        // diferente do que estava no <html>.
        appearance.value =
            stored ??
            (isAppearance(page.props.appearance)
                ? page.props.appearance
                : 'system');

        updateTheme(appearance.value);
    });

    const resolvedAppearance = computed<ResolvedAppearance>(() =>
        appearance.value === 'system'
            ? prefersDark()
                ? 'dark'
                : 'light'
            : appearance.value,
    );

    function updateAppearance(value: Appearance): void {
        appearance.value = value;

        // Cache local para o boot seguinte e cookie para o servidor (SSR).
        localStorage.setItem('appearance', value);
        setCookie('appearance', value);

        updateTheme(value);
    }

    return {
        appearance,
        resolvedAppearance,
        updateAppearance,
    };
}
