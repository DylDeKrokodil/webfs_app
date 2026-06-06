// fallow-ignore-next-line unused-export
export const apiRequest = async (url, { method = 'GET', body = null, csrfToken = '', errorMessage = 'Fout bij verwerken aanvraag.' } = {}) => {
    const headers = {
        Accept: 'application/json',
        'Content-Type': 'application/json',
    };

    if (csrfToken) {
        headers['X-CSRF-TOKEN'] = csrfToken;
    }

    const options = {
        method,
        headers,
    };

    if (body) {
        options.body = JSON.stringify(body);
    }

    const response = await fetch(url, options);
    const payload = await response.json().catch(() => ({}));

    if (!response.ok) {
        throw {
            message: payload.message || errorMessage,
            status: payload.status || null,
            response,
            payload,
        };
    }

    return payload;
};

export const postRequest = (url, options = {}) => apiRequest(url, { ...options, method: 'POST' });
