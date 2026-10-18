export type User = {
    id: number;
    name: string;
    username?: string | null;
    email: string;
    avatar?: string;
    photo_uuid?: string | null;
    two_factor_enabled?: boolean;
    email_verified_at: string | null;
    locale?: string | null;
    appearance?: string | null;
    created_at: string;
    updated_at: string;
    [key: string]: unknown;
};

export type Auth = {
    user: User;
    /** Pode abrir o painel administrativo (ADR-025). */
    isAdmin: boolean;
};
