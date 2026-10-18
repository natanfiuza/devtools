import type { ToolCategory, ToolCategoryId } from './types';

/**
 * Catalogo de categorias, na ordem dos chips da home.
 *
 * O rotulo nao esta aqui: vem do i18n (`tools.categories.<id>`), como todo
 * texto visivel. `order` aqui e a ordem dos chips, nao a das tools.
 */
export const categories: ToolCategory[] = [
    { id: 'json', icon: 'braces' },
    { id: 'sql', icon: 'database' },
    { id: 'encode', icon: 'binary' },
    { id: 'security', icon: 'shield-check' },
    { id: 'time', icon: 'clock' },
    { id: 'text', icon: 'type' },
];

export const categoryOrder: ToolCategoryId[] = categories.map(
    (category) => category.id,
);

export function categoryById(id: ToolCategoryId): ToolCategory | undefined {
    return categories.find((category) => category.id === id);
}
