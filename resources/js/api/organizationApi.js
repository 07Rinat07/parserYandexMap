import { apiClient } from './client';

export async function getOrganization() {
    const { data } = await apiClient.get('/organization');
    return data.data;
}

export async function getOrganizations() {
    const { data } = await apiClient.get('/organizations');
    return data.data;
}

export async function getOrganizationById(id) {
    const { data } = await apiClient.get(`/organizations/${id}`);
    return data.data;
}

export async function saveOrganization(yandexUrl) {
    const { data } = await apiClient.post('/organization', { yandex_url: yandexUrl });
    return data.data;
}

export async function refreshOrganization(id = null) {
    const endpoint = id ? `/organizations/${id}/refresh` : '/organization/refresh';
    const { data } = await apiClient.post(endpoint);
    return data.data;
}

export async function getRatingHistory(id) {
    const { data } = await apiClient.get(`/organizations/${id}/rating-history`);
    return data.data;
}

export async function getParserMonitoring() {
    const { data } = await apiClient.get('/parser-monitoring');
    return data.data;
}

export async function exportOrganization(id, format) {
    const response = await apiClient.get(`/organizations/${id}/export`, {
        params: { format },
        responseType: 'blob',
    });

    return response;
}
