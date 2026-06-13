<template>
    <section class="panel">
        <div class="table-header">
            <div>
                <p class="eyebrow">История рейтинга</p>
                <h2>{{ history.length }} снимков</h2>
            </div>
            <button type="button" class="ghost-button compact-button" :disabled="capturing" @click="$emit('capture')">
                <Camera :size="16" />
                <span>{{ capturing ? 'Создаем' : 'Снимок' }}</span>
            </button>
        </div>

        <p class="muted history-hint">
            История пополняется после успешного парсинга. Для демонстрации можно создать снимок текущих счетчиков вручную.
        </p>

        <EmptyState v-if="history.length === 0" message="История появится после успешных обновлений или ручного снимка." />

        <template v-else>
            <div class="history-stats">
                <div>
                    <span>Текущий рейтинг</span>
                    <strong>{{ latestSnapshot.rating ?? '-' }}</strong>
                    <small :class="ratingDeltaClass">{{ formatSigned(ratingDelta) }}</small>
                </div>
                <div>
                    <span>Оценок</span>
                    <strong>{{ latestSnapshot.ratings_count ?? '-' }}</strong>
                    <small :class="ratingsDeltaClass">{{ formatSigned(ratingsDelta) }}</small>
                </div>
                <div>
                    <span>Отзывов</span>
                    <strong>{{ latestSnapshot.reviews_count ?? '-' }}</strong>
                    <small :class="reviewsDeltaClass">{{ formatSigned(reviewsDelta) }}</small>
                </div>
            </div>

            <svg class="rating-chart" viewBox="0 0 700 260" role="img" aria-label="График динамики рейтинга и отзывов">
                <line x1="54" y1="28" x2="54" y2="154" class="chart-axis" />
                <line x1="54" y1="154" x2="650" y2="154" class="chart-axis" />
                <text x="10" y="34" class="chart-label">{{ maxRatingLabel }}</text>
                <text x="10" y="158" class="chart-label">{{ minRatingLabel }}</text>

                <line
                    v-if="points.length === 1"
                    :x1="54"
                    :y1="points[0].y"
                    :x2="650"
                    :y2="points[0].y"
                    class="chart-line chart-line-muted"
                />
                <polyline v-else :points="linePoints" class="chart-line" />

                <g v-for="point in points" :key="point.id">
                    <circle :cx="point.x" :cy="point.y" r="4" class="chart-point" />
                    <title>{{ point.label }}</title>
                </g>

                <g v-for="annotation in annotations" :key="annotation.id">
                    <line :x1="annotation.x" y1="28" :x2="annotation.x" y2="154" class="chart-annotation-line" />
                    <text :x="annotation.x + 6" y="42" class="chart-annotation-label">{{ annotation.label }}</text>
                    <title>{{ annotation.title }}</title>
                </g>

                <line x1="54" y1="222" x2="650" y2="222" class="chart-axis" />
                <text x="10" y="226" class="chart-label">Отзывы</text>
                <g v-for="bar in reviewBars" :key="bar.id">
                    <rect :x="bar.x" :y="bar.y" :width="bar.width" :height="bar.height" class="chart-bar" />
                    <title>{{ bar.label }}</title>
                </g>
                <text v-if="reviewBars.length === 0" x="60" y="202" class="chart-empty-label">
                    Количество отзывов между снимками не менялось
                </text>
            </svg>

            <div class="history-list">
                <div v-for="snapshot in displayRows" :key="snapshot.id" class="history-row">
                    <strong>{{ snapshot.rating ?? '-' }}</strong>
                    <span>
                        {{ snapshot.ratings_count ?? '-' }} оценок
                        <small :class="deltaClass(snapshot.ratings_delta)">{{ formatSigned(snapshot.ratings_delta) }}</small>
                    </span>
                    <span>
                        {{ snapshot.reviews_count ?? '-' }} отзывов
                        <small :class="deltaClass(snapshot.reviews_delta)">{{ formatSigned(snapshot.reviews_delta) }}</small>
                    </span>
                    <time>{{ formatDate(snapshot.captured_at) }}</time>
                </div>
            </div>
        </template>
    </section>
</template>

<script setup>
import { computed } from 'vue';
import { Camera } from '@lucide/vue';
import EmptyState from './EmptyState.vue';

const props = defineProps({
    history: {
        type: Array,
        required: true,
    },
    capturing: {
        type: Boolean,
        default: false,
    },
});

defineEmits(['capture']);

const chronological = computed(() => [...props.history].reverse());
const ratedSnapshots = computed(() => chronological.value.filter((snapshot) => snapshot.rating !== null && snapshot.rating !== undefined));
const latestSnapshot = computed(() => props.history[0] || {});
const previousSnapshot = computed(() => props.history[1] || null);
const ratingDelta = computed(() => latestSnapshot.value.rating !== null && previousSnapshot.value?.rating !== null
    ? Number((latestSnapshot.value.rating - previousSnapshot.value.rating).toFixed(2))
    : null);
const ratingsDelta = computed(() => delta(latestSnapshot.value.ratings_count, previousSnapshot.value?.ratings_count));
const reviewsDelta = computed(() => delta(latestSnapshot.value.reviews_count, previousSnapshot.value?.reviews_count));
const ratingDeltaClass = computed(() => deltaClass(ratingDelta.value));
const ratingsDeltaClass = computed(() => deltaClass(ratingsDelta.value));
const reviewsDeltaClass = computed(() => deltaClass(reviewsDelta.value));
const minRating = computed(() => Math.min(...ratedSnapshots.value.map((snapshot) => snapshot.rating)));
const maxRating = computed(() => Math.max(...ratedSnapshots.value.map((snapshot) => snapshot.rating)));
const minRatingLabel = computed(() => Number.isFinite(minRating.value) ? formatNumber(minRating.value) : '1');
const maxRatingLabel = computed(() => Number.isFinite(maxRating.value) ? formatNumber(maxRating.value) : '5');
const points = computed(() => {
    const count = Math.max(ratedSnapshots.value.length - 1, 1);
    const range = maxRating.value - minRating.value;

    return ratedSnapshots.value.map((snapshot, index) => {
        const x = 54 + (596 * index / count);
        const y = range === 0
            ? 91
            : 154 - (((snapshot.rating - minRating.value) / range) * 126);

        return {
            id: snapshot.id,
            x: Number(x.toFixed(2)),
            y: Number(y.toFixed(2)),
            label: `${snapshot.rating} - ${formatDate(snapshot.captured_at)}`,
        };
    });
});
const linePoints = computed(() => points.value.map((point) => `${point.x},${point.y}`).join(' '));
const annotations = computed(() => ratedSnapshots.value
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
const reviewBars = computed(() => {
    const deltas = chronological.value
        .map((snapshot, index, rows) => {
            if (index === 0) {
                return null;
            }

            const previous = rows[index - 1];
            const value = Math.max(0, (snapshot.reviews_count || 0) - (previous.reviews_count || 0));

            return {
                id: snapshot.id,
                value,
                label: `+${value} отзывов - ${formatDate(snapshot.captured_at)}`,
            };
        })
        .filter((item) => item?.value > 0);
    const max = Math.max(...deltas.map((item) => item.value), 0);
    const width = Math.max(10, Math.min(34, 520 / Math.max(deltas.length, 1)));

    return deltas.map((item, index) => {
        const height = max === 0 ? 0 : Math.max(4, (item.value / max) * 52);
        const x = 64 + (index * (586 / Math.max(deltas.length - 1, 1)));

        return {
            ...item,
            x: Number((x - (width / 2)).toFixed(2)),
            y: Number((222 - height).toFixed(2)),
            width,
            height: Number(height.toFixed(2)),
        };
    });
});
const displayRows = computed(() => props.history.map((snapshot, index, rows) => {
    const previous = rows[index + 1];

    return {
        ...snapshot,
        ratings_delta: delta(snapshot.ratings_count, previous?.ratings_count),
        reviews_delta: delta(snapshot.reviews_count, previous?.reviews_count),
    };
}));

function delta(current, previous) {
    if (current === null || current === undefined || previous === null || previous === undefined) {
        return null;
    }

    return Number(current) - Number(previous);
}

function formatNumber(value) {
    return new Intl.NumberFormat('ru-RU', {
        maximumFractionDigits: 2,
    }).format(value);
}

function formatSigned(value) {
    if (value === null || value === undefined) {
        return 'нет сравнения';
    }

    if (value === 0) {
        return 'без изменений';
    }

    return value > 0 ? `+${formatNumber(value)}` : formatNumber(value);
}

function deltaClass(value) {
    if (value > 0) {
        return 'delta-positive';
    }

    if (value < 0) {
        return 'delta-negative';
    }

    return 'delta-neutral';
}

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
