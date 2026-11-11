import type { ImageLimits } from '@/tools/palette-generator/lib/imageLimits';

/**
 * Tipos das paletas persistidas (F4, ADR-123/ADR-124/ADR-126).
 *
 * Espelham o que `App\Support\Colors\PalettePresenter` entrega ao Inertia e o
 * payload aceito pelos requests. As cores efetivas sao gravadas como HEX pelo
 * navegador e **nunca** recalculadas ao abrir: o editor reconstroi a escala a
 * partir destes valores.
 */

export type PaletteKind = 'scale' | 'extracted';

export type PaletteVisibility = 'private' | 'public';

export type PaletteTheme = 'light' | 'dark';

export type PaletteRole =
    | 'primary'
    | 'secondary'
    | 'accent'
    | 'background'
    | 'surface'
    | 'text'
    | 'text_muted'
    | 'border';

/** Cor de tom da escala: o tom faz parte do shape persistido. */
export type PaletteScaleItem = {
    hex: string;
    tone: number;
};

/** Cor extraida: a ordem e o peso, nao ha tom. */
export type PaletteExtractedItem = {
    hex: string;
};

export type PaletteColorItem = PaletteScaleItem | PaletteExtractedItem;

export type PaletteColors = {
    base: string;
    items: PaletteColorItem[];
};

/** Os 8 papeis de um tema, todos HEX6/HEX8. */
export type PaletteRoleColors = Record<PaletteRole, string>;

export type PaletteRoles = Record<PaletteTheme, PaletteRoleColors>;

/** Origem da extracao: so o rotulo do arquivo ou o host da URL, nunca a URL. */
export type PaletteSource =
    { kind: 'file'; label: string } | { kind: 'url'; host: string };

export type PaletteTag = {
    id: string;
    name: string;
};

export type PaletteSort =
    | 'updated_at'
    | 'created_at'
    | 'name'
    | 'colors_count'
    | 'duplications_count';

export type PaletteStatus = 'active' | 'archived';

/** Filtros aceitos na querystring de Minhas Paletas (ADR-109). */
export type PaletteFilters = {
    q: string;
    status: PaletteStatus;
    tag: string | null;
    sort: PaletteSort;
    direction: 'asc' | 'desc';
};

/** Cota do plano; `null` em `limit`/`remaining` significa ilimitado. */
export type PaletteLimits = {
    limit: number | null;
    used: number;
    remaining: number | null;
};

/** Linha da lista de Minhas Paletas. */
export type PaletteRow = {
    id: string;
    name: string;
    description: string | null;
    kind: PaletteKind;
    visibility: PaletteVisibility;
    colors_count: number;
    duplications_count: number;
    archived: boolean;
    slug: string | null;
    preview: string[];
    tags: PaletteTag[];
    updated_at: string | null;
};

/** Payload completo do editor. */
export type PaletteEditor = {
    id: string;
    name: string;
    description: string | null;
    visibility: PaletteVisibility;
    kind: PaletteKind;
    algorithm_version: number;
    colors: PaletteColors;
    roles: PaletteRoles;
    source: PaletteSource | null;
    colors_count: number;
    duplications_count: number;
    archived: boolean;
    archived_at: string | null;
    slug: string | null;
    has_og: boolean;
    tags: PaletteTag[];
    updated_at: string | null;
};

/** O que a pagina publica recebe: sem papeis, origem ou arquivamento. */
export type PalettePublic = {
    id: string;
    name: string;
    description: string | null;
    kind: PaletteKind;
    /** Versao que gerou as cores; so informativa (comentario do export). */
    algorithm_version: number;
    colors: PaletteColors;
    colors_count: number;
    duplications_count: number;
    slug: string | null;
    tags: PaletteTag[];
    updated_at: string | null;
};

/** Props da pagina publica (`PublicPaletteController@show`). */
export type PublicPaletteProps = {
    palette: PalettePublic;
    /** URL da OG propria; `null` quando o dono ainda nao gerou. */
    ogUrl: string | null;
};

/** Paginacao do Laravel ja serializada pelo Inertia. */
export type PalettePaginator = {
    data: PaletteRow[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
};

/** Metadados editaveis de uma paleta, comuns a criacao e edicao. */
export type PaletteDetails = {
    name: string;
    description: string | null;
    visibility: PaletteVisibility;
    tags: string[];
};

/**
 * Limites e tags que o gerador recebe do servidor para o modal de salvar.
 *
 * Nenhum limite mora no cliente (CLAUDE.md); as tags sao as da conta, na ordem
 * global do usuario, e servem de sugestao - a digitada e criada ao salvar.
 */
export type PaletteToolProps = {
    nameMaxLength: number;
    descriptionMaxLength: number;
    tagsPerPalette: number;
    tagNameMaxLength: number;
    tags: PaletteTag[];
};

/** Tetos do formulario de paleta (ColorsToolProps::formLimits). */
export type PaletteFormLimits = Pick<
    PaletteToolProps,
    'nameMaxLength' | 'descriptionMaxLength' | 'tagsPerPalette' | 'tagNameMaxLength'
>;

/** Props de Minhas Paletas (`PaletteController@index`). */
export type PalettesIndexProps = {
    palettes: PalettePaginator;
    filters: PaletteFilters;
    tags: PaletteTag[];
    limits: PaletteLimits;
    form: PaletteFormLimits;
};

/** Props do editor (`PaletteController@edit`). */
export type PaletteEditorProps = {
    palette: PaletteEditor;
    tags: PaletteTag[];
    limits: PaletteLimits;
    form: PaletteFormLimits;
    image: ImageLimits;
};

/** Corpo enviado a `POST/PATCH /dashboard/colors/palettes`. */
export type PalettePayload = {
    name: string;
    description: string | null;
    visibility: PaletteVisibility;
    kind: PaletteKind;
    algorithm_version: number;
    colors: PaletteColors;
    roles: PaletteRoles;
    source: PaletteSource | null;
    tags: string[];
};
