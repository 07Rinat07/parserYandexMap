<template>
    <div class="state state-loading">
        <span class="spinner" aria-hidden="true"></span>
        <span>{{ label }}</span>
    </div>
</template>

<script setup>
defineProps({
    label: {
        type: String,
        default: 'Загрузка...',
    },
});
</script>
