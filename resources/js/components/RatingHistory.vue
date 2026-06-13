<template>
    <section class="panel">
        <div class="table-header">
            <div>
                <p class="eyebrow">История рейтинга</p>
                <h2>{{ history.length }} снимков</h2>
            </div>
        </div>

        <EmptyState v-if="history.length === 0" message="История появится после успешных обновлений." />

        <div v-else class="history-list">
            <div v-for="snapshot in history" :key="snapshot.id" class="history-row">
                <strong>{{ snapshot.rating ?? '-' }}</strong>
                <span>{{ snapshot.ratings_count ?? '-' }} оценок</span>
                <span>{{ snapshot.reviews_count ?? '-' }} отзывов</span>
                <time>{{ formatDate(snapshot.captured_at) }}</time>
            </div>
        </div>
    </section>
</template>

<script setup>
import EmptyState from './EmptyState.vue';

defineProps({
    history: {
        type: Array,
        required: true,
    },
});

function formatDate(value) {
    if (!value) {
        return '-';
    }

    return new Intl.DateTimeFormat('ru-RU', {
        dateStyle: 'short',
        timeStyle: 'short',
    }).format(new Date(value));
}
</script>
