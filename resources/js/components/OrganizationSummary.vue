<template>
    <section class="panel summary-panel">
        <div class="summary-header">
            <div>
                <p class="eyebrow">Организация</p>
                <h2>{{ organization.name || 'Название пока не получено' }}</h2>
            </div>
            <ParsingStatusBadge :status="organization.parsing_status" />
        </div>

        <a class="source-link" :href="organization.normalized_yandex_url" target="_blank" rel="noreferrer">
            {{ organization.normalized_yandex_url }}
        </a>

        <div class="metrics">
            <div>
                <span>Рейтинг</span>
                <strong>{{ organization.rating ?? '-' }}</strong>
            </div>
            <div>
                <span>Оценок</span>
                <strong>{{ organization.ratings_count ?? '-' }}</strong>
            </div>
            <div>
                <span>Отзывов</span>
                <strong>{{ organization.reviews_count ?? '-' }}</strong>
            </div>
            <div>
                <span>Обновлено</span>
                <strong>{{ lastParsed }}</strong>
            </div>
        </div>

        <ErrorState v-if="organization.parsing_error" :message="organization.parsing_error" />
    </section>
</template>

<script setup>
import { computed } from 'vue';
import ErrorState from './ErrorState.vue';
import ParsingStatusBadge from './ParsingStatusBadge.vue';

const props = defineProps({
    organization: {
        type: Object,
        required: true,
    },
});

const lastParsed = computed(() => {
    if (!props.organization.last_parsed_at) {
        return '-';
    }

    return new Intl.DateTimeFormat('ru-RU', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(props.organization.last_parsed_at));
});
</script>
