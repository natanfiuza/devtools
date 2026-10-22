<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { Button } from '@/components/ui/button';
import { useRoutes } from '@/composables/useRoutes';

/**
 * Pagina de erro (F7): componente padrao do Inertia para 404, servido pelo
 * fallback das rotas ou pelo handler de excecao. Sai noindex (app.blade.php) e
 * usa a casca publica; o codigo do status fica visivel, o texto vem do i18n.
 */
defineProps<{ status: number }>();

const { t } = useI18n();
const routes = useRoutes();
</script>

<template>
    <Head :title="t('errors.not_found.title')" />

    <div
        class="mx-auto flex w-full max-w-[1120px] flex-col items-start gap-6 px-4 py-24"
    >
        <p
            class="rounded-md border bg-surface px-2.5 py-1 text-sm font-medium text-muted-foreground tabular-nums"
            data-test="error-status"
        >
            {{ status }}
        </p>

        <h1 class="text-4xl leading-tight font-semibold tracking-tight">
            {{ t('errors.not_found.title') }}
        </h1>

        <p class="max-w-2xl text-lg text-muted-foreground">
            {{ t('errors.not_found.description') }}
        </p>

        <Button as-child>
            <Link :href="routes.home.value" data-test="error-home">
                {{ t('errors.not_found.back_home') }}
            </Link>
        </Button>
    </div>
</template>
