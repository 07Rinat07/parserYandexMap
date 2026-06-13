import { apiClient, ensureCsrfCookie } from './client';

export async function login(credentials) {
    await ensureCsrfCookie();
    const { data } = await apiClient.post('/login', credentials);
    return data.data;
}

export async function logout() {
    const { data } = await apiClient.post('/logout');
    return data;
}

export async function me() {
    const { data } = await apiClient.get('/me');
    return data.data;
}
