/** Idioma suportado, como exposto pelo backend (App\Support\Locales). */
export type Locale = {
    /** Codigo usado nos diretorios de lang/ (ex.: pt_BR). */
    code: string;
    /** Slug usado na URL (ex.: pt-br). */
    slug: string;
    /** Valor do atributo lang do <html> (ex.: pt-BR). */
    html: string;
    dir: 'ltr' | 'rtl';
    /** Endonimo do idioma, traduzido pelo proprio idioma ativo. */
    name: string;
};

/** Mensagens do locale ativo, por namespace (ex.: { app: { brand: ... } }). */
export type Translations = Record<string, Record<string, unknown>>;
