import { ref } from 'vue';
import { getReviews } from '../api/reviewApi';

export function useReviews() {
    const reviews = ref([]);
    const meta = ref({ current_page: 1, per_page: 50, total: 0, last_page: 1 });
    const loading = ref(false);
    const error = ref('');

    async function load(page = 1) {
        loading.value = true;
        error.value = '';
        try {
            const response = await getReviews(page, meta.value.per_page);
            reviews.value = response.data;
            meta.value = response.meta;
        } catch (exception) {
            error.value = exception.response?.data?.message || 'Не удалось загрузить отзывы.';
        } finally {
            loading.value = false;
        }
    }

    return { reviews, meta, loading, error, load };
}
