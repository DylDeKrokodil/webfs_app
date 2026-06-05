export const fetchPublicMenuItems = async ({ csrfToken = '' } = {}) => {
    const headers = { Accept: 'application/json' };
    if (csrfToken) headers['X-CSRF-TOKEN'] = csrfToken;

    const response = await fetch('/api/menu-items', { headers });
    if (!response.ok) throw new Error('Menukaart kon niet worden geladen.');

    const payload = await response.json();
    return payload.data ?? [];
};
