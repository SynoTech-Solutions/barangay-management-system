const CENTAVOS_PER_PESO = 100;
const DECIMAL_PATTERN = /^(-?)(\d+)(?:\.(\d{1,2}))?$/;

export function toCentavos(amount: string | number): number {
    const match = DECIMAL_PATTERN.exec(String(amount).trim());

    if (match === null) {
        return 0;
    }

    const value =
        Number(match[2]) * CENTAVOS_PER_PESO +
        Number((match[3] ?? '').padEnd(2, '0'));

    return match[1] === '-' ? -value : value;
}

export function fromCentavos(centavos: number): string {
    const sign = centavos < 0 ? '-' : '';
    const absolute = Math.abs(centavos);
    const pesos = Math.floor(absolute / CENTAVOS_PER_PESO);
    const cents = String(absolute % CENTAVOS_PER_PESO).padStart(2, '0');

    return `${sign}${pesos}.${cents}`;
}
