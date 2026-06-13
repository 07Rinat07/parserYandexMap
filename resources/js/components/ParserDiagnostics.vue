<template>
    <details v-if="metadata" class="parser-diagnostics">
        <summary>
            <span>Parser diagnostics</span>
            <strong>{{ confidenceLabel }}</strong>
        </summary>

        <div v-if="progress" class="diagnostics-grid">
            <div>
                <span>Стадия</span>
                <strong>{{ progress.stage || '-' }}</strong>
            </div>
            <div>
                <span>Прогресс</span>
                <strong>{{ progress.message || '-' }}</strong>
            </div>
            <div>
                <span>Найдено отзывов</span>
                <strong>{{ progress.reviews_seen ?? '-' }}</strong>
            </div>
        </div>

        <div class="diagnostics-grid">
            <div>
                <span>Strategy</span>
                <strong>{{ metadata.strategy || '-' }}</strong>
            </div>
            <div>
                <span>Contract</span>
                <strong>{{ metadata.contract_version || '-' }}</strong>
            </div>
            <div>
                <span>Elapsed</span>
                <strong>{{ elapsedLabel }}</strong>
            </div>
        </div>

        <div v-if="warnings.length" class="parser-warnings diagnostics-warnings">
            <strong>Warnings</strong>
            <span v-for="warning in warnings" :key="warning">{{ warning }}</span>
        </div>

        <div v-if="selectorHits.length" class="selector-hits">
            <div v-for="[selector, count] in selectorHits" :key="selector">
                <code>{{ selector }}</code>
                <span>{{ count }}</span>
            </div>
        </div>
    </details>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    metadata: {
        type: Object,
        default: null,
    },
    confidence: {
        type: Number,
        default: null,
    },
});

const progress = computed(() => props.metadata?.progress || null);
const warnings = computed(() => props.metadata?.warnings || []);
const elapsedLabel = computed(() => {
    const elapsed = props.metadata?.diagnostics?.elapsed_ms;
    return elapsed ? `${(elapsed / 1000).toFixed(1)} сек.` : '-';
});
const confidenceLabel = computed(() => props.confidence === null || props.confidence === undefined
    ? '-'
    : `${props.confidence}/100`);
const selectorHits = computed(() => Object.entries(props.metadata?.diagnostics?.selector_hits || {})
    .sort((a, b) => b[1] - a[1])
    .slice(0, 12));
</script>
