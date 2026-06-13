<template>
    <nav v-if="meta.last_page > 1" class="pagination" aria-label="Страницы отзывов">
        <button type="button" :disabled="meta.current_page <= 1" @click="$emit('change', meta.current_page - 1)">
            <ChevronLeft :size="18" />
        </button>
        <span>Страница {{ meta.current_page }} из {{ meta.last_page }}</span>
        <button type="button" :disabled="meta.current_page >= meta.last_page" @click="$emit('change', meta.current_page + 1)">
            <ChevronRight :size="18" />
        </button>
    </nav>
</template>

<script setup>
import { ChevronLeft, ChevronRight } from '@lucide/vue';

defineProps({
    meta: {
        type: Object,
        required: true,
    },
});

defineEmits(['change']);
</script>
