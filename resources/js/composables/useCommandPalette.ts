import { ref, type Ref } from 'vue';

/**
 * Estado da palette de busca, compartilhado por quem a abre: o gatilho do
 * header, o do hero, o item do menu mobile e o atalho global (Cmd/Ctrl+K).
 * A palette em si e montada uma vez, no PublicLayout.
 *
 * O ref vive no modulo, entao no SSR ele nasce fechado e ninguem o abre no
 * servidor: a primeira abertura e sempre no cliente.
 */
const isOpen = ref(false);

export interface CommandPaletteControls {
    isOpen: Ref<boolean>;
    open: () => void;
    close: () => void;
    toggle: () => void;
}

export function useCommandPalette(): CommandPaletteControls {
    return {
        isOpen,
        open: (): void => {
            isOpen.value = true;
        },
        close: (): void => {
            isOpen.value = false;
        },
        toggle: (): void => {
            isOpen.value = !isOpen.value;
        },
    };
}
