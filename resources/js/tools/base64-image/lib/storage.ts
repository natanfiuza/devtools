import type { AnonymousConversionRecord } from '../types';

const STORAGE_KEY = 'devtools_base64_image_history';
const MAX_ANONYMOUS_AGE_MS = 24 * 60 * 60 * 1000; // 24 horas

export function getAnonymousHistory(): AnonymousConversionRecord[] {
    try {
        const raw = localStorage.getItem(STORAGE_KEY);
        if (!raw) {
            return [];
        }

        const items: AnonymousConversionRecord[] = JSON.parse(raw);
        if (!Array.isArray(items)) {
            return [];
        }

        const now = Date.now();
        const valid = items.filter((item) => {
            const time = new Date(item.created_at).getTime();
            return !isNaN(time) && now - time < MAX_ANONYMOUS_AGE_MS;
        });

        if (valid.length !== items.length) {
            localStorage.setItem(STORAGE_KEY, JSON.stringify(valid));
        }

        return valid;
    } catch {
        return [];
    }
}

export function saveAnonymousConversion(
    record: AnonymousConversionRecord,
): void {
    try {
        const current = getAnonymousHistory();
        const updated = [record, ...current.filter((item) => item.id !== record.id)].slice(
            0,
            50,
        );
        localStorage.setItem(STORAGE_KEY, JSON.stringify(updated));
    } catch {
        // localStorage indisponivel ou cheio
    }
}

export function removeAnonymousConversion(id: string): void {
    try {
        const current = getAnonymousHistory();
        const updated = current.filter((item) => item.id !== id);
        localStorage.setItem(STORAGE_KEY, JSON.stringify(updated));
    } catch {
        // falha silenciosa
    }
}
