/**
 * Tipos da doacao por Pix (F6).
 *
 * A pagina publica recebe os valores ja formatados pelo servidor; a tela so
 * converte o valor livre digitado em centavos antes de enviar. A pagina de
 * status nunca recebe nome nem e-mail do doador.
 */

export type DonationSuggestion = {
    cents: number;
    formatted: string;
};

/** Props de `/donate`. */
export type DonatePageProps = {
    suggested: DonationSuggestion[];
    minCents: number;
    maxCents: number;
    min: string;
    max: string;
    currency: string;
    /** Ha credenciais do provedor: Pix dinamico com confirmacao automatica. */
    online: boolean;
    /** Sem credenciais, mas com chave Pix: QR estatico sem confirmacao. */
    staticPix: boolean;
    name: string | null;
    email: string | null;
};

export type DonationPublicStatus =
    'pending' | 'paid' | 'expired' | 'refunded' | 'failed';

/** Props de `/donate/{ulid}`. */
export type DonationStatusProps = {
    donation: {
        ulid: string;
        status: DonationPublicStatus;
        amount: string;
        createdAt: string | null;
        paidAt: string | null;
        expiresAt: string | null;
        qrCode: string | null;
        qrImage: string | null;
        ticketUrl: string | null;
        /** Pix estatico de configuracao, sem confirmacao automatica. */
        static: boolean;
    };
};
