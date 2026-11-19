import type { LayoutSystemStateV1 } from '@/tools/layout-harmonizer/lib/state';

/**
 * Shapes do backend de sistema de layout (F4, ADR-168/ADR-174).
 *
 * Espelham `LayoutSystemPresenter`/`LayoutSystemToolProps`. O publico nunca
 * recebe `source_palette_id`; o credito de origem privada nem aparece.
 */

export type LayoutSystemVisibility = 'private' | 'public';

export interface LayoutSystemTag {
    id: string;
    name: string;
}

export interface LayoutSystemRow {
    id: string;
    name: string;
    description: string | null;
    visibility: LayoutSystemVisibility;
    tokens_count: number;
    duplications_count: number;
    archived: boolean;
    slug: string | null;
    /** Ate 6 HEX do primeiro tema, na ordem do catalogo. */
    preview: string[];
    tags: LayoutSystemTag[];
    updated_at: string | null;
}

export interface LayoutSystemCreditSource {
    palette_name?: string;
    palette_slug?: string | null;
    palette_public?: boolean;
    author_name?: string | null;
    author_username?: string | null;
    captured_at?: string;
}

export interface LayoutSystemOrigin {
    id: string;
    name: string;
    slug: string | null;
    public: boolean;
}

export interface LayoutSystemEditor extends LayoutSystemRow {
    state: LayoutSystemStateV1;
    schema_version: number;
    algorithm_version: number;
    suggestions_version: number;
    snapshot_version: number;
    archived_at: string | null;
    credit: {
        source: LayoutSystemCreditSource | null;
        origin: LayoutSystemOrigin | null;
    };
}

export interface LayoutSystemPublicCredit {
    palette_name: string;
    palette_slug: string | null;
    palette_url: string | null;
}

export interface LayoutSystemAuthor {
    id: number;
    name: string;
    username: string;
    avatar: string | null;
}

export interface LayoutSystemPublic {
    id: string;
    name: string;
    description: string | null;
    schema_version: number;
    algorithm_version: number;
    suggestions_version: number;
    snapshot_version: number;
    tokens_count: number;
    duplications_count: number;
    slug: string;
    state: LayoutSystemStateV1;
    tags: LayoutSystemTag[];
    author: LayoutSystemAuthor | null;
    credit: LayoutSystemPublicCredit | null;
    updated_at: string | null;
}

export interface LayoutSystemLimits {
    nameMaxLength: number;
    descriptionMaxLength: number;
    tagsPerSystem: number;
    tagNameMaxLength: number;
}

export interface LayoutSystemVersions {
    state: number;
    schema: number;
    snapshot: number;
    algorithm: number;
    suggestions: number;
}

export interface LayoutSystemQuota {
    limit: number | null;
    used: number;
    remaining: number | null;
}

/** Origem opcional vinda do CTA da paleta (`?palette={ULID}`). */
export interface LayoutSystemToolOrigin {
    id: string;
    name: string;
    author: string | null;
    public: boolean;
    /** Cor base da paleta (`colors.base`), nunca o papel `primary`. */
    seedHex: string;
    url: string | null;
}

export interface LayoutSystemToolProps {
    authenticated: boolean;
    limits: LayoutSystemLimits;
    quota: LayoutSystemQuota | null;
    tags: LayoutSystemTag[];
    origin: LayoutSystemToolOrigin | null;
    currentVersions: LayoutSystemVersions;
}

export interface LayoutSystemFilters {
    q: string;
    status: 'active' | 'archived';
    tag: string | null;
    sort: LayoutSystemSort;
    direction: 'asc' | 'desc';
}

export type LayoutSystemSort =
    'updated_at' | 'created_at' | 'name' | 'duplications_count';

/** Paginacao do Laravel, so o que as telas usam. */
export interface LayoutSystemPaginator<T> {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
}

export interface LayoutSystemsIndexProps {
    systems: LayoutSystemPaginator<LayoutSystemRow>;
    filters: LayoutSystemFilters;
    tags: LayoutSystemTag[];
    limits: LayoutSystemQuota;
    form: LayoutSystemFormLimits;
}

export interface LayoutSystemEditorProps {
    system: LayoutSystemEditor;
    tags: LayoutSystemTag[];
    limits: LayoutSystemQuota;
    form: LayoutSystemFormLimits;
    currentVersions: LayoutSystemVersions;
}

export interface PublicLayoutSystemProps {
    system: LayoutSystemPublic;
    authenticated: boolean;
    canDuplicate: boolean;
}

/** Limites de formulario que o servidor publica nas telas. */
export type LayoutSystemFormLimits = LayoutSystemLimits;
