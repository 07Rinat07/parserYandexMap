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
                <g v-for="annotation in annotations" :key="annotation.id">
                    <line :x1="annotation.x" y1="24" :x2="annotation.x" y2="150" class="chart-annotation-line" />
                    <text :x="annotation.x + 6" y="36" class="chart-annotation-label">{{ annotation.label }}</text>
                    <title>{{ annotation.title }}</title>
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
const annotations = computed(() => chronological.value
    .map((snapshot, index, rows) => {
        if (index === 0) {
            return null;
        }

        const previous = rows[index - 1];
        const point = points.value[index];
        const ratingDelta = Number((snapshot.rating - previous.rating).toFixed(2));
        const reviewDelta = (snapshot.reviews_count || 0) - (previous.reviews_count || 0);

        if (Math.abs(ratingDelta) >= 0.1) {
            return {
                id: `rating-${snapshot.id}`,
                x: point.x,
                label: ratingDelta > 0 ? `+${ratingDelta}` : String(ratingDelta),
                title: `Изменение рейтинга: ${ratingDelta}`,
            };
        }

        if (reviewDelta >= 25) {
            return {
                id: `reviews-${snapshot.id}`,
                x: point.x,
                label: `+${reviewDelta} отзывов`,
                title: `Прирост отзывов: ${reviewDelta}`,
            };
        }

        return null;
    })
    .filter(Boolean));

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
