/**
 * Token de acesso a API publica v1 (ADR-050).
 *
 * O valor em texto puro nunca vem aqui: a lista so mostra nome, abilities e as
 * datas. O valor aparece uma vez, em `plainTextToken`, logo depois de criado.
 */
export type ApiToken = {
    id: number;
    name: string;
    abilities: string[];
    lastUsedAt: string | null;
    createdAt: string | null;
};

export type ApiTokensProps = {
    tokens: ApiToken[];
    plainTextToken: string | null;
};
