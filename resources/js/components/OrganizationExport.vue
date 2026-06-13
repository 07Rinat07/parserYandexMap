<template>
    <section class="panel export-panel">
        <div class="table-header">
            <div>
                <p class="eyebrow">Экспорт данных</p>
                <h2>{{ reviewsTotal }} отзывов</h2>
            </div>
        </div>

        <div class="export-actions">
            <button
                v-for="format in formats"
                :key="format.value"
                type="button"
                :disabled="disabled || activeFormat === format.value"
                @click="download(format.value)"
            >
                <Download :size="18" />
                <span>{{ activeFormat === format.value ? 'Готовим' : format.label }}</span>
            </button>
        </div>

        <p v-if="disabled" class="muted">Экспорт станет доступен после первого успешного сбора данных.</p>
        <ErrorState v-if="error" :message="error" />
    </section>
</template>

<script setup>
import { computed, ref } from 'vue';
import { Download } from '@lucide/vue';
import ErrorState from './ErrorState.vue';
import { exportOrganization } from '../api/organizationApi';

const props = defineProps({
    organization: {
        type: Object,
        required: true,
    },
    reviewsTotal: {
        type: Number,
        default: 0,
    },
});

const formats = [
    { value: 'csv', label: 'CSV для Excel' },
    { value: 'json', label: 'JSON' },
    { value: 'txt', label: 'TXT' },
];
const activeFormat = ref('');
const error = ref('');
const disabled = computed(() => !props.organization?.id || !props.organization?.last_parsed_at);

async function download(format) {
    activeFormat.value = format;
    error.value = '';

    try {
        const response = await exportOrganization(props.organization.id, format);
        const blob = new Blob([response.data], {
            type: response.headers['content-type'] || 'application/octet-stream',
        });
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');

        link.href = url;
        link.download = filenameFromHeader(response.headers['content-disposition']) || `yandex-reviews.${format}`;
        document.body.appendChild(link);
        link.click();
        link.remove();
        URL.revokeObjectURL(url);
    } catch (exception) {
        error.value = exception.response?.data?.message || 'Не удалось выгрузить данные.';
    } finally {
        activeFormat.value = '';
    }
}

function filenameFromHeader(header) {
    if (!header) {
        return '';
    }

    const utfMatch = header.match(/filename\*=UTF-8''([^;]+)/i);
    if (utfMatch) {
        return decodeURIComponent(utfMatch[1]);
    }

    const match = header.match(/filename="?([^"]+)"?/i);
    return match?.[1] || '';
}
</script>
