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
    requestsCount?: number;
    sqlQueriesCount?: number;
    lastUsedAt: string | null;
    createdAt: string | null;
};

export type ApiTokensStats = {
    totalRequests: number;
    totalSqlQueries: number;
    activeTokens: number;
};

export type ApiTokenUsageBreakdown = {
    tokenId: number | 'revoked';
    tokenName: string;
    tokens: number;
    requests: number;
    isRevoked: boolean;
};

export type ApiTokenDailyUsage = {
    date: string;
    dayLabel: string;
    formattedDate: string;
    totalTokens: number;
    totalRequests: number;
    breakdown: ApiTokenUsageBreakdown[];
};

export type ApiTokenUsageTokenItem = {
    id: number | 'revoked';
    name: string;
    isRevoked: boolean;
};

export type ApiTokenUsagePeriod = {
    type: 'current_month' | 'previous_month' | 'custom';
    startDate: string;
    endDate: string;
};

export type ApiTokenUsageStats = {
    period: ApiTokenUsagePeriod;
    days: ApiTokenDailyUsage[];
    tokens: ApiTokenUsageTokenItem[];
    summary: {
        totalTokens: number;
        totalRequests: number;
    };
};

export type ApiTokensProps = {
    tokens: ApiToken[];
    plainTextToken: string | null;
    stats?: ApiTokensStats;
    usageStats?: ApiTokenUsageStats;
};
