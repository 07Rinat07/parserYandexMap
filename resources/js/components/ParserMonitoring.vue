<template>
    <section class="panel">
        <div class="table-header">
            <div>
                <p class="eyebrow">Мониторинг parser</p>
                <h2>{{ totalProblems }} проблем</h2>
            </div>
        </div>

        <div v-if="monitoring" class="monitor-grid">
            <div><span>Готово</span><strong>{{ monitoring.counts.success }}</strong></div>
            <div><span>Ожидает</span><strong>{{ monitoring.counts.pending }}</strong></div>
            <div><span>В работе</span><strong>{{ monitoring.counts.processing }}</strong></div>
            <div><span>Ошибки</span><strong>{{ monitoring.counts.failed }}</strong></div>
        </div>

        <div v-if="monitoring?.recent_errors?.length" class="error-list">
            <article v-for="error in monitoring.recent_errors" :key="error.organization_id">
                <strong>{{ error.organization_name || error.normalized_yandex_url }}</strong>
                <p>{{ error.parsing_error }}</p>
                <button type="button" @click="$emit('retry', error.organization_id)">
                    <RefreshCw :size="16" />
                    <span>Retry</span>
                </button>
            </article>
        </div>
    </section>
</template>

<script setup>
import { computed } from 'vue';
import { RefreshCw } from '@lucide/vue';

const props = defineProps({
    monitoring: {
        type: Object,
        default: null,
    },
});

defineEmits(['retry']);

const totalProblems = computed(() => props.monitoring?.counts?.failed || 0);
</script>
