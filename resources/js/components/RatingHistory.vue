<template>
    <section class="panel">
        <div class="table-header">
            <div>
                <p class="eyebrow">История рейтинга</p>
                <h2>{{ history.length }} снимков</h2>
            </div>
        </div>

        <EmptyState v-if="history.length === 0" message="История появится после успешных обновлений." />

        <template v-else>
            <svg class="rating-chart" viewBox="0 0 640 180" role="img" aria-label="График динамики рейтинга">
                <line x1="36" y1="20" x2="36" y2="150" class="chart-axis" />
                <line x1="36" y1="150" x2="610" y2="150" class="chart-axis" />
                <polyline :points="linePoints" class="chart-line" />
                <g v-for="point in points" :key="point.id">
                    <circle :cx="point.x" :cy="point.y" r="4" class="chart-point" />
                    <title>{{ point.label }}</title>
                </g>
                <text x="8" y="25" class="chart-label">5</text>
                <text x="8" y="153" class="chart-label">1</text>
            </svg>

            <div class="history-list">
                <div v-for="snapshot in history" :key="snapshot.id" class="history-row">
                <strong>{{ snapshot.rating ?? '-' }}</strong>
                <span>{{ snapshot.ratings_count ?? '-' }} оценок</span>
                <span>{{ snapshot.reviews_count ?? '-' }} отзывов</span>
                <time>{{ formatDate(snapshot.captured_at) }}</time>
                </div>
            </div>
        </template>
    </section>
</template>

<script setup>
import { computed } from 'vue';
import EmptyState from './EmptyState.vue';

const props = defineProps({
    history: {
        type: Array,
        required: true,
    },
});

const chronological = computed(() => [...props.history].reverse().filter((snapshot) => snapshot.rating));
const points = computed(() => {
    const count = Math.max(chronological.value.length - 1, 1);

    return chronological.value.map((snapshot, index) => {
        const x = 36 + (574 * index / count);
        const y = 150 - (((snapshot.rating - 1) / 4) * 130);

        return {
            id: snapshot.id,
            x: Number(x.toFixed(2)),
            y: Number(y.toFixed(2)),
            label: `${snapshot.rating} - ${formatDate(snapshot.captured_at)}`,
        };
    });
});
const linePoints = computed(() => points.value.map((point) => `${point.x},${point.y}`).join(' '));

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
