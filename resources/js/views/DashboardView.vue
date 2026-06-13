<template>
    <main class="app-shell">
        <header class="topbar">
            <div>
                <p class="eyebrow">Настройки интеграции</p>
                <h1>Отзывы Яндекс.Карт</h1>
            </div>
            <button type="button" class="ghost-button" @click="signOut">
                <LogOut :size="18" />
                <span>Выйти</span>
            </button>
        </header>

        <OrganizationForm
            :initial-url="organization?.yandex_url"
            :saving="saving"
            @save="handleSave"
        />

        <ErrorState v-if="error" :message="error" />
        <LoadingState v-if="loading" label="Загружаем настройки..." />
        <EmptyState v-else-if="!organization" message="Добавьте ссылку на организацию, чтобы запустить первый сбор отзывов." />

        <template v-else>
            <OrganizationSummary :organization="organization" />

            <div class="actions-row">
                <button type="button" :disabled="refreshing || isProcessing" @click="handleRefresh">
                    <RefreshCw :size="18" />
                    <span>{{ refreshing ? 'Запускаем' : 'Обновить данные' }}</span>
                </button>
                <span v-if="isProcessing" class="muted">Парсер работает, статус обновляется автоматически.</span>
            </div>

            <ReviewsTable
                v-if="organization.parsing_status === 'success'"
                :reviews="reviews"
                :meta="meta"
                :loading="reviewsLoading"
                :error="reviewsError"
                @page-change="loadReviews"
            />
        </template>
    </main>
</template>

<script setup>
import { computed, onMounted, watch } from 'vue';
import { useRouter } from 'vue-router';
import { LogOut, RefreshCw } from '@lucide/vue';
import EmptyState from '../components/EmptyState.vue';
import ErrorState from '../components/ErrorState.vue';
import LoadingState from '../components/LoadingState.vue';
import OrganizationForm from '../components/OrganizationForm.vue';
import OrganizationSummary from '../components/OrganizationSummary.vue';
import ReviewsTable from '../components/ReviewsTable.vue';
import { useOrganization } from '../composables/useOrganization';
import { usePolling } from '../composables/usePolling';
import { useReviews } from '../composables/useReviews';
import { useAuthStore } from '../stores/auth';

const router = useRouter();
const auth = useAuthStore();
const { organization, loading, saving, refreshing, error, load, save, refresh } = useOrganization();
const { reviews, meta, loading: reviewsLoading, error: reviewsError, load: loadReviews } = useReviews();

const isProcessing = computed(() => ['pending', 'processing'].includes(organization.value?.parsing_status));
const polling = usePolling(async () => {
    await load();
}, 4000);

onMounted(async () => {
    await load();
    if (organization.value?.parsing_status === 'success') {
        await loadReviews(1);
    }
});

watch(isProcessing, (value) => {
    if (value) {
        polling.start();
    } else {
        polling.stop();
    }
}, { immediate: true });

watch(() => organization.value?.parsing_status, async (status, previous) => {
    if (status === 'success' && previous !== 'success') {
        await loadReviews(1);
    }
});

async function handleSave(url) {
    await save(url);
    reviews.value = [];
    meta.value = { current_page: 1, per_page: 50, total: 0, last_page: 1 };
}

async function handleRefresh() {
    await refresh();
}

async function signOut() {
    await auth.logout();
    await router.push({ name: 'login' });
}
</script>
