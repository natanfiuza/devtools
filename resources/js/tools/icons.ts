import {
    ArrowLeftRight,
    Binary,
    Braces,
    CalendarClock,
    Clock,
    Database,
    Diff,
    FileCode,
    FileKey,
    FileText,
    Image,
    KeyRound,
    LayoutPanelLeft,
    Palette,
    Regex,
    ShieldCheck,
    Sparkles,
    SwatchBook,
    Terminal,
    Type,
    Wrench,
} from '@lucide/vue';
import type { Component } from 'vue';

/**
 * Mapa nome -> componente de icone.
 *
 * O `manifest.json` e dado puro, entao guarda o nome (`"braces"`); quem vira
 * componente e este mapa. Um nome fora dele e erro de registro (o teste
 * `tools-registry.test.ts` cobra que todo manifest tenha icone aqui); a
 * chave generica evita que um erro de registro derrube a tela.
 */
const icons: Record<string, Component> = {
    'arrow-left-right': ArrowLeftRight,
    binary: Binary,
    braces: Braces,
    'calendar-clock': CalendarClock,
    clock: Clock,
    database: Database,
    diff: Diff,
    'file-code': FileCode,
    'file-key': FileKey,
    'file-text': FileText,
    image: Image,
    'key-round': KeyRound,
    'layout-panel-left': LayoutPanelLeft,
    palette: Palette,
    regex: Regex,
    'shield-check': ShieldCheck,
    sparkles: Sparkles,
    'swatch-book': SwatchBook,
    terminal: Terminal,
    type: Type,
};

export function toolIcon(name: string): Component {
    return icons[name] ?? Wrench;
}

export function hasIcon(name: string): boolean {
    return name in icons;
}
