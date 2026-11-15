<script setup lang="ts">
import { Button } from '@/components/ui/button';

/**
 * Botao da barra de acoes de uma tool: rotulo, icone opcional e o atalho ao
 * lado.
 *
 * O atalho aparece no proprio botao (DESIGN: "atalhos visiveis") em vez de
 * morar so na linha de ajuda: quem procura "como eu formato isso" olha para o
 * botao. O texto do atalho nao passa pelo i18n - ele muda com o teclado, nao
 * com o idioma.
 */
defineProps<{
    label: string;
    /**
     * O nome acessivel, quando o rotulo visivel nao diz o que o botao faz.
     *
     * O alternador de flag do Regex Tester mostra so a letra (`i`), que e o nome
     * da coisa na lingua do motor e nao uma palavra: quem le por leitor de tela
     * precisa da descricao, que vem traduzida por aqui.
     */
    ariaLabel?: string;
    /** Atalho ja formatado (`Ctrl Enter`, `c`), opcional. */
    shortcut?: string;
    disabled?: boolean;
    /** Para os botoes de alternancia (formatar/minificar, ordenar). */
    pressed?: boolean;
    variant?: 'default' | 'outline' | 'ghost' | 'secondary';
}>();

defineEmits<{ click: [] }>();
</script>

<template>
    <Button
        type="button"
        size="sm"
        :variant="variant ?? 'outline'"
        :disabled="disabled"
        :aria-pressed="pressed"
        :aria-label="ariaLabel"
        @click="$emit('click')"
    >
        <slot name="icon" />
        {{ label }}
        <kbd
            v-if="shortcut"
            class="ml-1 hidden rounded border px-1 font-mono text-[10px] text-subtle sm:inline"
        >
            {{ shortcut }}
        </kbd>
    </Button>
</template>
