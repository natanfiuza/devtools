import {
    Binary,
    Braces,
    Clock,
    Database,
    FileCode,
    FileText,
    KeyRound,
    Regex,
    ShieldCheck,
    Sparkles,
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
    binary: Binary,
    braces: Braces,
    clock: Clock,
    database: Database,
    'file-code': FileCode,
    'file-text': FileText,
    'key-round': KeyRound,
    regex: Regex,
    'shield-check': ShieldCheck,
    sparkles: Sparkles,
    type: Type,
};

export function toolIcon(name: string): Component {
    return icons[name] ?? Wrench;
}

export function hasIcon(name: string): boolean {
    return name in icons;
}
