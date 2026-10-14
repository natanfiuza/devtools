<script setup lang="ts">
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { Badge } from '@/components/ui/badge';
import { useRoutes } from '@/composables/useRoutes';
import { toolIcon } from '@/tools/icons';
import { statusKey, toolKeys, toolPath } from '@/tools/registry';
import type { ToolManifest } from '@/tools/types';

/**
 * Card de uma ferramenta no grid da home (DESIGN: icone de linha, nome e uma
 * linha de descricao).
 *
 * Tool que funciona e um link para a pagina dela; as que ainda nao funcionam
 * aparecem com o selo "em breve" e **sem** link - nao ha para onde ir, e um
 * link morto seria pior que a ausencia dele. Quem decide e o proprio registro,
 * pelo status (ADR-007).
 *
 * A raiz e `<li>` porque quem o usa e uma lista (`ToolGrid`).
 */
const props = defineProps<{ tool: ToolManifest }>();

const { t } = useI18n();
const routes = useRoutes();

/** `null` enquanto a tool nao funciona. */
const href = computed(() => toolPath(props.tool, routes.localeSlug.value));
</script>

<template>
    <li
        :data-test="`tool-card-${tool.slug}`"
        class="flex flex-col gap-2 rounded-xl border bg-card p-5 transition-colors hover:border-border-strong"
    >
        <div class="flex items-center gap-2">
            <component
                :is="toolIcon(tool.icon)"
                class="size-5 shrink-0 text-brand"
                aria-hidden="true"
            />
            <!-- O nome e o link (e nao o card inteiro): area de clique do
                 tamanho do texto, sem transformar o selo em parte do alvo. -->
            <h3 class="truncate text-base font-medium">
                <Link
                    v-if="href"
                    :href="href"
                    data-test="tool-card-link"
                    class="rounded-sm outline-none hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                >
                    {{ t(toolKeys.name(tool)) }}
                </Link>
                <template v-else>{{ t(toolKeys.name(tool)) }}</template>
            </h3>
            <Badge
                v-if="tool.status !== 'available'"
                variant="secondary"
                class="ml-auto shrink-0"
                data-test="tool-card-badge"
            >
                {{ t(statusKey(tool)) }}
            </Badge>
        </div>

        <p class="line-clamp-1 text-sm text-muted-foreground">
            {{ t(toolKeys.description(tool)) }}
        </p>
    </li>
</template>
