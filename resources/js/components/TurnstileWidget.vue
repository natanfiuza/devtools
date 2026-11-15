<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { onBeforeUnmount, onMounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';

/**
 * Cloudflare Turnstile no cadastro (ADR-052).
 *
 * O widget so aparece quando a chave publica existe: sem as duas chaves o
 * recurso esta desligado no servidor e nao ha o que renderizar aqui. O token
 * resolvido viaja no campo oculto `turnstile`, junto com o resto do formulario
 * do Inertia - a regra `ValidTurnstile` confere no servidor.
 *
 * O token e de uso unico: quando o cadastro volta com erro (outro campo
 * invalido, por exemplo), o formulario chama `reset()` para o usuario resolver
 * o desafio de novo em vez de reenviar um token ja consumido.
 */

interface TurnstileApi {
    render: (
        container: HTMLElement,
        options: Record<string, unknown>,
    ) => string | number;
    reset: (widgetId?: string | number) => void;
    remove: (widgetId: string | number) => void;
}

declare global {
    interface Window {
        turnstile?: TurnstileApi;
    }
}

const SCRIPT_ID = 'cf-turnstile-script';
const SCRIPT_URL =
    'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit';

const { t } = useI18n();
const page = usePage();
const siteKey: string | null = page.props.turnstileSiteKey;

const container = ref<HTMLElement | null>(null);
const token = ref('');
const unavailable = ref(false);
let widgetId: string | number | null = null;

/** Carrega o api.js uma unica vez, mesmo com remontagens do componente. */
let scriptPromise: Promise<void> | null = null;

function loadTurnstile(): Promise<void> {
    if (window.turnstile) {
        return Promise.resolve();
    }

    if (scriptPromise) {
        return scriptPromise;
    }

    scriptPromise = new Promise<void>((resolve, reject) => {
        const existing = document.getElementById(
            SCRIPT_ID,
        ) as HTMLScriptElement | null;
        const script = existing ?? document.createElement('script');

        script.addEventListener('load', () => resolve(), { once: true });
        script.addEventListener(
            'error',
            () => {
                scriptPromise = null;
                reject(new Error('turnstile_load_failed'));
            },
            { once: true },
        );

        if (!existing) {
            script.id = SCRIPT_ID;
            script.src = SCRIPT_URL;
            script.async = true;
            script.defer = true;
            document.head.appendChild(script);
        }
    });

    return scriptPromise;
}

onMounted(async () => {
    if (!siteKey) {
        return;
    }

    try {
        await loadTurnstile();
    } catch {
        unavailable.value = true;

        return;
    }

    if (!container.value || !window.turnstile) {
        unavailable.value = true;

        return;
    }

    widgetId = window.turnstile.render(container.value, {
        sitekey: siteKey,
        theme: 'auto',
        language: page.props.locale,
        callback: (value: string) => {
            token.value = value;
        },
        'expired-callback': () => {
            token.value = '';
        },
        'timeout-callback': () => {
            token.value = '';
        },
        'error-callback': () => {
            token.value = '';
        },
    });
});

onBeforeUnmount(() => {
    if (widgetId !== null && window.turnstile) {
        window.turnstile.remove(widgetId);
    }

    widgetId = null;
});

function reset(): void {
    token.value = '';

    if (widgetId !== null && window.turnstile) {
        window.turnstile.reset(widgetId);
    }
}

defineExpose({ reset });
</script>

<template>
    <div v-if="siteKey" class="grid gap-2" data-test="turnstile">
        <div ref="container" />
        <!-- Campo oculto: o Inertia serializa junto com os demais inputs. -->
        <input
            type="hidden"
            name="turnstile"
            :value="token"
            data-test="turnstile-token"
        />
        <p
            v-if="unavailable"
            class="text-xs leading-relaxed text-destructive"
            role="alert"
        >
            {{ t('auth.turnstile.failed') }}
        </p>
    </div>
</template>
