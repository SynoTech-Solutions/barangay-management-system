import messages from '@/i18n/en.json';

type Replacements = Record<string, string | number>;

function lookup(key: string): string {
    const value = key
        .split('.')
        .reduce<unknown>(
            (node, part) =>
                node !== null && typeof node === 'object'
                    ? (node as Record<string, unknown>)[part]
                    : undefined,
            messages,
        );

    return typeof value === 'string' ? value : key;
}

export function t(key: string, replacements: Replacements = {}): string {
    return Object.entries(replacements).reduce(
        (text, [name, value]) => text.replaceAll(`{${name}}`, String(value)),
        lookup(key),
    );
}

export function useI18n() {
    return { t };
}
