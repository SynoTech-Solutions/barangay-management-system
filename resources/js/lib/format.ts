const DISPLAY_LOCALE = 'en-US';
const LONG_DATE: Intl.DateTimeFormatOptions = {
    month: 'long',
    day: 'numeric',
    year: 'numeric',
};

export function longDate(isoDate: string | null): string {
    if (!isoDate) {
        return '';
    }

    const [year, month, day] = isoDate.slice(0, 10).split('-').map(Number);

    return new Date(year, month - 1, day).toLocaleDateString(
        DISPLAY_LOCALE,
        LONG_DATE,
    );
}

export function personName(parts: Array<string | null | undefined>): string {
    return parts.filter((part) => part).join(' ');
}

export function todayIsoDate(): string {
    const now = new Date();
    const pad = (value: number): string => String(value).padStart(2, '0');

    return `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())}`;
}
