<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import { Badge } from '@/components/ui/badge';
import { toolIcon } from '@/tools/icons';
import { statusKey, toolKeys } from '@/tools/registry';
import type { ToolManifest } from '@/tools/types';

/**
 * Card de uma ferramenta no grid da home (DESIGN: icone de linha, nome e uma
 * linha de descricao).
 *
 * Na F1 nenhuma tool funciona, entao o card **nao** e um link: nao ha pagina
 * para onde ir e um link morto seria pior que a ausencia dele. Quando o status
 * virar `available` (F2), e aqui que entra o `Link` - o componente ja recebe
 * `tool` inteiro, com o que a decisao precisa.
 *
 * A raiz e `<li>` porque quem o usa e uma lista (`ToolGrid`).
 */
defineProps<{ tool: ToolManifest }>();

const { t } = useI18n();
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
            <h3 class="truncate text-base font-medium">
                {{ t(toolKeys.name(tool)) }}
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
