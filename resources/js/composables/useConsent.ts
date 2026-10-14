import { computed, ref, type ComputedRef, type Ref } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { writeConsent } from '@/lib/analytics';
import type { AnalyticsProps, ConsentDecision } from '@/types';

/**
 * Decisao do visitante sobre cookies de medicao (ADR-013).
 *
 * O servidor resolve a decisao pelo cookie e manda pronta nas props
 * compartilhadas: quem ja respondeu nao ve o banner piscar. O estado vive no
 * modulo porque tres pecas olham para ele - o banner, a tag do GA e o botao
 * "Cookies" do rodape.
 *
 * A prop e a verdade a cada carregamento: quando o objeto muda (outra pagina,
 * outro documento), a decisao local e substituida por ela. Dentro do mesmo
 * carregamento vale o que o visitante acabou de escolher, sem esperar o
 * servidor.
 */

export type UseConsentReturn = {
    /** Decisao atual: nula enquanto o visitante nao respondeu. */
    consent: Ref<ConsentDecision | null>;
    /** O banner esta na tela? Sem decisao sim; com decisao, so se o rodape reabriu. */
    bannerOpen: ComputedRef<boolean>;
    accept: () => void;
    reject: () => void;
    /** Reabre o banner depois de uma decisao (botao "Cookies" do rodape). */
    open: () => void;
};

const consent = ref<ConsentDecision | null>(null);

/** O rodape reabriu o banner depois de uma decisao. */
const reopened = ref(false);

/**
 * Props de medicao do carregamento atual. A identidade do objeto e o sinal de
 * "carregamento novo": o Inertia remonta as props a cada resposta.
 */
let current: AnalyticsProps | null = null;

export function useConsent(): UseConsentReturn {
    const page = usePage();
    const analytics: AnalyticsProps | null = page.props.analytics ?? null;

    if (analytics !== current) {
        current = analytics;
        consent.value = analytics?.consent ?? null;
        reopened.value = false;
    }

    function decide(decision: ConsentDecision): void {
        consent.value = decision;
        reopened.value = false;

        if (current !== null) {
            // Nome e validade do cookie vem do servidor: uma fonte so.
            writeConsent(current.cookie, decision, current.days);
        }
    }

    return {
        consent,
        bannerOpen: computed<boolean>(
            () => consent.value === null || reopened.value,
        ),
        accept: (): void => decide('granted'),
        reject: (): void => decide('denied'),
        open: (): void => {
            reopened.value = true;
        },
    };
}
