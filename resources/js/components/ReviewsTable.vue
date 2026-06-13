<template>
    <section class="panel reviews-panel">
        <div class="table-header">
            <div>
                <p class="eyebrow">Отзывы</p>
                <h2>{{ meta.total }} записей</h2>
            </div>
        </div>

        <LoadingState v-if="loading" label="Загружаем отзывы..." />
        <ErrorState v-else-if="error" :message="error" />
        <EmptyState v-else-if="reviews.length === 0" message="Отзывы пока не сохранены." />

        <div v-else class="reviews-list">
            <article v-for="review in reviews" :key="review.id" class="review-item">
                <header>
                    <strong>{{ review.author_name || 'Автор не указан' }}</strong>
                    <span>{{ review.review_date || 'Дата не указана' }}</span>
                </header>
                <p>{{ review.text || 'Текст отзыва отсутствует.' }}</p>
                <div class="rating">
                    <Star :size="16" />
                    <span>{{ review.rating ?? '-' }}</span>
                </div>
            </article>
        </div>

        <ReviewsPagination :meta="meta" @change="$emit('page-change', $event)" />
    </section>
</template>

<script setup>
import { Star } from '@lucide/vue';
import EmptyState from './EmptyState.vue';
import ErrorState from './ErrorState.vue';
import LoadingState from './LoadingState.vue';
import ReviewsPagination from './ReviewsPagination.vue';

defineProps({
    reviews: {
        type: Array,
        required: true,
    },
    meta: {
        type: Object,
        required: true,
    },
    loading: {
        type: Boolean,
        default: false,
    },
    error: {
        type: String,
        default: '',
    },
});

defineEmits(['page-change']);
</script>
