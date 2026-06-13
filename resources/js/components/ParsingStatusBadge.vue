<template>
    <span class="status-badge" :class="`status-${status}`">{{ label }}</span>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    status: {
        type: String,
        default: 'pending',
    },
});

const label = computed(() => ({
    pending: 'Ожидает',
    processing: 'Обновляется',
    success: 'Готово',
    failed: 'Ошибка',
}[props.status] || props.status));
</script>
