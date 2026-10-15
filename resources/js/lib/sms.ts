export type SmsLimits = {
    max_segments: number;
    max_recipients: number;
    gsm_single: number;
    gsm_multi: number;
    ucs2_single: number;
    ucs2_multi: number;
};

export type SmsLength = {
    encoding: 'gsm-7' | 'ucs-2';
    units: number;
    segments: number;
};

const GSM_BASIC =
    '@£$¥èéùìòÇ\nØø\rÅåΔ_ΦΓΛΩΠΨΣΘΞÆæßÉ !"#¤%&\'()*+,-./0123456789:;<=>?¡ABCDEFGHIJKLMNOPQRSTUVWXYZÄÖÑÜ§¿abcdefghijklmnopqrstuvwxyzäöñüà';
const GSM_EXTENDED = '^{}\\[~]|€\f';
const BMP_MAX = 0xffff;

const basic = new Set(Array.from(GSM_BASIC));
const extended = new Set(Array.from(GSM_EXTENDED));

function count(units: number[], single: number, multi: number): number {
    const total = units.reduce((sum, unit) => sum + unit, 0);

    if (total === 0) {
        return 0;
    }

    if (total <= single) {
        return 1;
    }

    let segments = 1;
    let used = 0;

    for (const unit of units) {
        if (used + unit > multi) {
            segments++;
            used = 0;
        }

        used += unit;
    }

    return segments;
}

export function smsLength(body: string, limits: SmsLimits): SmsLength {
    const characters = Array.from(body.replace(/\r\n?/g, '\n').trim());
    const gsm: number[] = [];
    let isGsm = true;

    for (const character of characters) {
        if (basic.has(character)) {
            gsm.push(1);
        } else if (extended.has(character)) {
            gsm.push(2);
        } else {
            isGsm = false;
            break;
        }
    }

    if (isGsm) {
        return {
            encoding: 'gsm-7',
            units: gsm.reduce((sum, unit) => sum + unit, 0),
            segments: count(gsm, limits.gsm_single, limits.gsm_multi),
        };
    }

    const ucs = characters.map((character) =>
        (character.codePointAt(0) ?? 0) > BMP_MAX ? 2 : 1,
    );

    return {
        encoding: 'ucs-2',
        units: ucs.reduce((sum, unit) => sum + unit, 0),
        segments: count(ucs, limits.ucs2_single, limits.ucs2_multi),
    };
}
