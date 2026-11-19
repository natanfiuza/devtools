import type { PrototypeCopy } from './types';

/**
 * Texto de exemplo dos prototipos (F4, ADR-173).
 *
 * O renderizador e puro: ele nao conhece i18n. Quem monta o SVG traduz o
 * sample aqui e passa pronto - assim a aba Prototipo da paleta e o painel do
 * Layout Harmonizer mostram exatamente o mesmo texto.
 */

/** `t` do vue-i18n reduzido ao que o sample usa. */
export type PrototypeTranslator = (
    key: string,
    named?: Record<string, string | number>,
) => string;

export function prototypeSampleCopy(t: PrototypeTranslator): PrototypeCopy {
    return {
        brand: t('colors.prototype.sample.brand'),
        nav: [
            t('colors.prototype.sample.nav_1'),
            t('colors.prototype.sample.nav_2'),
            t('colors.prototype.sample.nav_3'),
        ],
        heading: t('colors.prototype.sample.heading'),
        body: t('colors.prototype.sample.body'),
        primaryAction: t('colors.prototype.sample.primary'),
        secondaryAction: t('colors.prototype.sample.secondary'),
        links: [
            t('colors.prototype.sample.link_1'),
            t('colors.prototype.sample.link_2'),
            t('colors.prototype.sample.link_3'),
        ],
        sectionTitle: t('colors.prototype.sample.section'),
        cards: [
            t('colors.prototype.sample.card_1'),
            t('colors.prototype.sample.card_2'),
            t('colors.prototype.sample.card_3'),
        ],
        cardBody: t('colors.prototype.sample.card_body'),
        cardLink: t('colors.prototype.sample.card_link'),
        formTitle: t('colors.prototype.sample.form_title'),
        formLabel: t('colors.prototype.sample.form_label'),
        formPlaceholder: t('colors.prototype.sample.form_placeholder'),
        formAction: t('colors.prototype.sample.form_action'),
        footer: t('colors.prototype.sample.footer'),
        tabs: [
            t('colors.prototype.sample.tab_1'),
            t('colors.prototype.sample.tab_2'),
            t('colors.prototype.sample.tab_3'),
        ],
        tagline: t('colors.prototype.sample.tagline'),
    };
}
