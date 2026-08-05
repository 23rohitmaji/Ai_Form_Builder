export async function apiRequest(path, options = {}) {
    const { responseType, token, ...fetchOptions } = options;
    const headers = {
        Accept: responseType === 'blob' ? 'text/csv' : 'application/json',
        ...(fetchOptions.body instanceof FormData ? {} : { 'Content-Type': 'application/json' }),
        ...(token ? { Authorization: `Bearer ${token}` } : {}),
        ...(fetchOptions.headers || {}),
    };

    const response = await fetch(path, {
        ...fetchOptions,
        headers,
    });

    if (responseType === 'blob') {
        if (!response.ok) {
            throw await formatApiError(response);
        }

        return {
            code: 'success',
            status: response.status,
            data: await response.blob(),
            errors: [],
            message: 'Request completed.',
        };
    }

    const text = await response.text();
    const payload = text ? safeJson(text) : null;

    if (!response.ok) {
        throw formatPayloadError(payload, response.status);
    }

    return {
        code: 'success',
        status: response.status,
        data: payload,
        errors: [],
        message: payload?.message || 'Request completed.',
    };
}

export function formatErrors(error) {
    if (!error) {
        return ['Something went wrong.'];
    }

    if (Array.isArray(error.errors) && error.errors.length) {
        return error.errors;
    }

    const validation = error.raw?.errors || error.errors || {};
    const messages = Object.entries(validation).flatMap(([field, fieldErrors]) => {
        const values = Array.isArray(fieldErrors) ? fieldErrors : [fieldErrors];
        return values.map((message) => `${field}: ${message}`);
    });

    if (messages.length) {
        return messages;
    }

    if (error.message) {
        return [error.message];
    }

    return [JSON.stringify(error)];
}

async function formatApiError(response) {
    const text = await response.text();

    return formatPayloadError(text ? safeJson(text) : null, response.status);
}

function formatPayloadError(payload, status) {
    return {
        code: 'error',
        status,
        message: payload?.message || `HTTP ${status}`,
        errors: formatErrors({ message: payload?.message, raw: payload }),
        raw: payload,
    };
}

function safeJson(text) {
    try {
        return JSON.parse(text);
    } catch {
        return { message: text };
    }
}
