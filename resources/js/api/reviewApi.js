import { apiClient } from './client';

export async function getReviews(page = 1, perPage = 50, organizationId = null) {
    const endpoint = organizationId ? `/organizations/${organizationId}/reviews` : '/organization/reviews';
    const { data } = await apiClient.get(endpoint, {
        params: { page, per_page: perPage },
    });

    return data;
}
