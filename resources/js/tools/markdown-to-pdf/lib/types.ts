/**
 * Tipos e configuracoes do conversor de Markdown para PDF.
 */

export type PaperFormat = 'a4' | 'letter' | 'a5';
export type PageOrientation = 'portrait' | 'landscape';
export type MarginSize = 'normal' | 'compact' | 'wide';
export type FontFamily = 'sans' | 'serif' | 'mono' | 'fira-code';
export type FontSize = 'sm' | 'md' | 'lg';
export type ColorTheme = 'modern' | 'editorial' | 'minimal' | 'academic';

export interface DocumentConfig {
    format: PaperFormat;
    orientation: PageOrientation;
    margin: MarginSize;
    font: FontFamily;
    fontSize: FontSize;
    theme: ColorTheme;
}

export const PAPER_FORMATS: PaperFormat[] = ['a4', 'letter', 'a5'];
export const PAGE_ORIENTATIONS: PageOrientation[] = ['portrait', 'landscape'];
export const MARGIN_SIZES: MarginSize[] = ['normal', 'compact', 'wide'];
export const FONT_FAMILIES: FontFamily[] = [
    'sans',
    'serif',
    'mono',
    'fira-code',
];
export const FONT_SIZES: FontSize[] = ['sm', 'md', 'lg'];
export const COLOR_THEMES: ColorTheme[] = [
    'modern',
    'editorial',
    'minimal',
    'academic',
];

export const DEFAULT_CONFIG: DocumentConfig = {
    format: 'a4',
    orientation: 'portrait',
    margin: 'normal',
    font: 'sans',
    fontSize: 'md',
    theme: 'modern',
};
