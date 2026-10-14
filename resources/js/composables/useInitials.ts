export type UseInitialsReturn = {
    getInitials: (fullName?: string) => string;
};

function getInitial(name: string): string {
    return Array.from(name)[0] ?? '';
}

export function getInitials(fullName?: string): string {
    if (!fullName) {
        return '';
    }

    const names = fullName.trim().split(/\s+/u).filter(Boolean);
    const first = names[0];

    if (first === undefined) {
        return '';
    }

    if (names.length === 1) {
        return getInitial(first).toUpperCase();
    }

    const last = names[names.length - 1] ?? first;

    return `${getInitial(first)}${getInitial(last)}`.toUpperCase();
}

export function useInitials(): UseInitialsReturn {
    return { getInitials };
}
