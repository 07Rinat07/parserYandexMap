<template>
    <section class="panel list-panel">
        <div class="table-header">
            <div>
                <p class="eyebrow">Организации</p>
                <h2>{{ organizations.length }}</h2>
            </div>
        </div>

        <EmptyState v-if="organizations.length === 0" message="Организации пока не добавлены." />

        <div v-else class="organization-list">
            <button
                v-for="item in organizations"
                :key="item.id"
                type="button"
                class="organization-row"
                :class="{ active: activeId === item.id }"
                @click="$emit('select', item.id)"
            >
                <span>{{ item.name || item.normalized_yandex_url }}</span>
                <ParsingStatusBadge :status="item.parsing_status" />
            </button>
        </div>
    </section>
</template>

<script setup>
import EmptyState from './EmptyState.vue';
import ParsingStatusBadge from './ParsingStatusBadge.vue';

defineProps({
    organizations: {
        type: Array,
        required: true,
    },
    activeId: {
        type: Number,
        default: null,
    },
});

defineEmits(['select']);
</script>
