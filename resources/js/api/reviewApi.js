import { apiClient } from './client';

export async function getReviews(page = 1, perPage = 50) {
    const { data } = await apiClient.get('/organization/reviews', {
        params: { page, per_page: perPage },
    });

    return data;
}
