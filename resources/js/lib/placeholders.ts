const OPEN = '{'.repeat(2);
const CLOSE = '}'.repeat(2);
const FIELD_NAMESPACE = 'field';
const KEY_SEPARATOR = '_';
const KEY_MAX_LENGTH = 40;
const FALLBACK_PREFIX = 'f_';

export function wrapToken(token: string): string {
    return `${OPEN}${token}${CLOSE}`;
}

export function fieldToken(key: string): string {
    return `${FIELD_NAMESPACE}.${key}`;
}

export function fieldKeyFromLabel(label: string): string {
    let key = label
        .trim()
        .replace(/[^A-Za-z0-9]+/g, KEY_SEPARATOR)
        .replace(/^_+|_+$/g, '')
        .toLowerCase();

    if (key === '' || !/^[a-z]/.test(key)) {
        key = `${FALLBACK_PREFIX}${key}`;
    }

    return key.slice(0, KEY_MAX_LENGTH).replace(/_+$/, '');
}
