<script setup lang="ts">
import { computed, defineAsyncComponent } from 'vue';
import { Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import ClientOnly from '@/components/ClientOnly.vue';
import PageHead from '@/components/PageHead.vue';
import {
    Breadcrumb,
    BreadcrumbItem,
    BreadcrumbLink,
    BreadcrumbList,
    BreadcrumbPage,
    BreadcrumbSeparator,
} from '@/components/ui/breadcrumb';
import { useRoutes } from '@/composables/useRoutes';
import { toolComponentLoader } from '@/tools/components';
import { toolIcon } from '@/tools/icons';
import { allTools, toolKeys } from '@/tools/registry';

/**
 * Pagina generica de uma ferramenta (`/{locale}/tools/{slug}`, docs/SPEC.md
 * secao 4): cabecalho, metas e o componente da propria tool.
 *
 * Quem decide se a pagina existe e o servidor (`ToolController`): aqui so
 * chega slug de tool `available`. A pagina nao conhece ferramenta nenhuma -
 * ela resolve o componente pelo slug no registro e o encaixa, entao a F3
 * acrescenta tools sem tocar neste arquivo.
 *
 * O componente da tool entra por um glob **lazy**, dentro do `ClientOnly`: o
 * editor (CodeMirror) e o parser (Web Worker) dependem do DOM, e o SSR desta
 * pagina entrega a casca e as metas - que e o que o robo le.
 */
const props = defineProps<{
    slug: string;
    /** Teto de entrada das tools, em bytes (config/tools.php). */
    maxInputBytes: number;
    /** A partir de quantas linhas uma lista passa a ser virtualizada. */
    virtualizeFrom: number;
}>();

const { t } = useI18n();
const routes = useRoutes();

/** O manifest da tool: nome, descricao e icone saem do registro. */
const tool = computed(
    () => allTools.find((item) => item.slug === props.slug) ?? null,
);

/**
 * `null` quando o diretorio da tool nao traz um `Tool.vue`. Nao acontece com o
 * registro em ordem, mas sem uma guarda o erro seria de runtime, no meio da
 * pagina.
 */
const toolComponent = computed(() => {
    const loader = toolComponentLoader(props.slug);

    return loader === null
        ? null
        : defineAsyncComponent({ loader, suspensible: false });
});

const name = computed(() =>
    tool.value === null ? '' : t(toolKeys.name(tool.value)),
);

const description = computed(() =>
    tool.value === null ? '' : t(toolKeys.description(tool.value)),
);

const intro = computed(() => t(`tools.${props.slug}.intro`));
</script>

<template>
    <PageHead :title="name" :description="description" />

    <div class="mx-auto w-full max-w-[1120px] px-4 py-8">
        <Breadcrumb class="mb-6">
            <BreadcrumbList>
                <BreadcrumbItem>
                    <BreadcrumbLink as-child>
                        <Link :href="routes.home.value">
                            {{ t('app.nav.tools') }}
                        </Link>
                    </BreadcrumbLink>
                </BreadcrumbItem>
                <BreadcrumbSeparator />
                <BreadcrumbItem>
                    <BreadcrumbPage>{{ name }}</BreadcrumbPage>
                </BreadcrumbItem>
            </BreadcrumbList>
        </Breadcrumb>

        <header class="max-w-3xl">
            <div class="flex items-center gap-3">
                <component
                    :is="toolIcon(tool?.icon ?? '')"
                    class="size-6 shrink-0 text-brand"
                    aria-hidden="true"
                />
                <h1 class="text-3xl font-semibold tracking-tight">
                    {{ name }}
                </h1>
            </div>
            <p class="mt-3 text-muted-foreground">{{ description }}</p>
            <p class="mt-2 text-sm text-subtle">{{ intro }}</p>
        </header>

        <div class="mt-8">
            <ClientOnly>
                <component
                    :is="toolComponent"
                    v-if="toolComponent"
                    :max-input-bytes="maxInputBytes"
                    :virtualize-from="virtualizeFrom"
                />
                <p v-else class="text-sm text-destructive">
                    {{ t('tools.ui.states.unavailable') }}
                </p>
            </ClientOnly>
        </div>
    </div>
</template>
