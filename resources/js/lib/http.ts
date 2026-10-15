const XSRF_COOKIE = 'XSRF-TOKEN=';
const UNPROCESSABLE = 422;

export type JsonResult<T> =
    | { ok: true; data: T }
    | { ok: false; status: number; errors: Record<string, string> };

function xsrfToken(): string {
    const cookie = document.cookie
        .split('; ')
        .find((entry) => entry.startsWith(XSRF_COOKIE));

    return cookie ? decodeURIComponent(cookie.slice(XSRF_COOKIE.length)) : '';
}

function firstErrors(errors: Record<string, string[]>): Record<string, string> {
    return Object.fromEntries(
        Object.entries(errors).map(([field, messages]) => [
            field,
            messages[0] ?? '',
        ]),
    );
}

export async function getJson<T>(url: string): Promise<T | null> {
    const response = await fetch(url, {
        headers: { Accept: 'application/json' },
    });

    return response.ok ? ((await response.json()) as T) : null;
}

export async function postJson<T>(
    url: string,
    body: unknown,
): Promise<JsonResult<T>> {
    const response = await fetch(url, {
        method: 'POST',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-XSRF-TOKEN': xsrfToken(),
        },
        body: JSON.stringify(body),
    });

    if (response.ok) {
        return { ok: true, data: (await response.json()) as T };
    }

    const payload =
        response.status === UNPROCESSABLE
            ? ((await response.json()) as { errors?: Record<string, string[]> })
            : {};

    return {
        ok: false,
        status: response.status,
        errors: firstErrors(payload.errors ?? {}),
    };
}

export async function postFormData<T>(
    url: string,
    body: FormData,
): Promise<JsonResult<T>> {
    const response = await fetch(url, {
        method: 'POST',
        headers: {
            Accept: 'application/json',
            'X-XSRF-TOKEN': xsrfToken(),
        },
        body,
    });

    if (response.ok) {
        return { ok: true, data: (await response.json()) as T };
    }

    const payload = (await response.json().catch(() => ({}))) as {
        errors?: Record<string, string[]>;
        error?: { message?: string };
    };
    const errors = firstErrors(payload.errors ?? {});

    if (payload.error?.message) {
        errors.pdf = payload.error.message;
    }

    return { ok: false, status: response.status, errors };
}
