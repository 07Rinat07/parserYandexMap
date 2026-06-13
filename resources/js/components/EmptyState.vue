<template>
    <div class="state state-empty">
        {{ message }}
    </div>
</template>

<script setup>
defineProps({
    message: {
        type: String,
        default: 'Данных пока нет.',
    },
});
</script>
