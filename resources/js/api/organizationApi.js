import { apiClient } from './client';

export async function getOrganization() {
    const { data } = await apiClient.get('/organization');
    return data.data;
}

export async function saveOrganization(yandexUrl) {
    const { data } = await apiClient.post('/organization', { yandex_url: yandexUrl });
    return data.data;
}

export async function refreshOrganization() {
    const { data } = await apiClient.post('/organization/refresh');
    return data.data;
}
