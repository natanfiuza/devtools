import { router, usePage } from '@inertiajs/vue3';
import { ref, type Ref } from 'vue';
import { useI18n } from 'vue-i18n';
import {
    paletteOgPng,
    type PaletteOgSource,
    type PaletteOgTexts,
} from '@/lib/paletteOg';
import { store as storeOg } from '@/routes/dashboard/colors/palettes/og';

export type PaletteOgState = {
    busy: Ref<boolean>;
    error: Ref<boolean>;
    /** Houve upload nesta sessao: o servidor ja tem uma imagem nova. */
    uploaded: Ref<boolean>;
    generate: (source?: PaletteOgSource) => Promise<boolean>;
};

/**
 * Uploads em andamento por paleta. Duas instancias do editor podem tentar
 * gerar a mesma OG ao mesmo tempo (a visita depois do PATCH remonta a pagina
 * enquanto o callback do salvamento ainda corre); sem isto o cartao subiria
 * duas vezes e `og_version` pularia dois numeros.
 */
const uploading = new Set<string>();

/**
 * Geracao e envio da imagem OG no navegador (F4, ADR-125).
 *
 * O PNG e desenhado pelo mesmo renderizador dos prototipos e vai como
 * multipart para o endpoint autenticado. Falha de geracao ou de upload nunca
 * derruba o salvamento da paleta: o estado fica em `error` e o botao permite
 * tentar de novo. `generate` aceita uma fonte explicita porque, logo apos o
 * PATCH, as props do editor ainda nao refletem o que acabou de ser gravado.
 */
export function usePaletteOg(
    paletteId: () => string,
    source: () => PaletteOgSource | null,
): PaletteOgState {
    const { t } = useI18n();
    const page = usePage();

    const busy = ref(false);
    const error = ref(false);
    const uploaded = ref(false);

    function texts(count: number): PaletteOgTexts {
        const site = String(page.props.name ?? '');

        return {
            brand: site,
            tagline: t('colors.og.tagline', { count }),
            footer: t('colors.og.footer', { site }),
        };
    }

    function upload(id: string, blob: Blob): Promise<void> {
        const image = new File([blob], `palette-og-${id}.png`, {
            type: 'image/png',
        });

        return new Promise((resolve, reject) => {
            router.post(
                storeOg.url({ colorPalette: id }),
                { image },
                {
                    forceFormData: true,
                    preserveScroll: true,
                    preserveState: true,
                    onSuccess: () => resolve(),
                    onError: () => reject(new Error('og_upload_failed')),
                },
            );
        });
    }

    async function generate(override?: PaletteOgSource): Promise<boolean> {
        const current = override ?? source();
        const id = paletteId();

        if (current === null || busy.value || uploading.has(id)) {
            return false;
        }

        uploading.add(id);
        busy.value = true;
        error.value = false;

        try {
            const png = await paletteOgPng(
                current,
                texts(current.colors.items.length),
            );

            await upload(id, png);
            uploaded.value = true;

            return true;
        } catch {
            // Canvas bloqueado, memoria curta ou upload recusado: o aviso
            // aparece e o resto da pagina segue funcionando.
            error.value = true;

            return false;
        } finally {
            uploading.delete(id);
            busy.value = false;
        }
    }

    return { busy, error, uploaded, generate };
}
