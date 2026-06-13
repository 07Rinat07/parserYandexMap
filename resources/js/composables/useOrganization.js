import { ref } from 'vue';
import * as organizationApi from '../api/organizationApi';

export function useOrganization() {
    const organization = ref(null);
    const organizations = ref([]);
    const ratingHistory = ref([]);
    const monitoring = ref(null);
    const loading = ref(false);
    const saving = ref(false);
    const refreshing = ref(false);
    const error = ref('');

    async function load() {
        loading.value = true;
        error.value = '';
        try {
            organization.value = await organizationApi.getOrganization();
        } catch (exception) {
            error.value = exception.response?.data?.message || 'Не удалось загрузить организацию.';
        } finally {
            loading.value = false;
        }
    }

    async function loadAll() {
        loading.value = true;
        error.value = '';
        try {
            organizations.value = await organizationApi.getOrganizations();
            if (!organization.value && organizations.value.length) {
                organization.value = organizations.value[0];
            }
        } catch (exception) {
            error.value = exception.response?.data?.message || 'Не удалось загрузить организации.';
        } finally {
            loading.value = false;
        }
    }

    async function select(id) {
        loading.value = true;
        error.value = '';
        try {
            organization.value = await organizationApi.getOrganizationById(id);
        } catch (exception) {
            error.value = exception.response?.data?.message || 'Не удалось выбрать организацию.';
        } finally {
            loading.value = false;
        }
    }

    async function save(url) {
        saving.value = true;
        error.value = '';
        try {
            organization.value = await organizationApi.saveOrganization(url);
            await loadAll();
        } catch (exception) {
            error.value = exception.response?.data?.errors?.yandex_url?.[0] || exception.response?.data?.message || 'Не удалось сохранить ссылку.';
            throw exception;
        } finally {
            saving.value = false;
        }
    }

    async function refresh(id = organization.value?.id) {
        refreshing.value = true;
        error.value = '';
        try {
            organization.value = await organizationApi.refreshOrganization(id);
            await loadAll();
        } catch (exception) {
            error.value = exception.response?.data?.message || 'Не удалось запустить обновление.';
        } finally {
            refreshing.value = false;
        }
    }

    async function loadHistory(id = organization.value?.id) {
        if (!id) {
            ratingHistory.value = [];
            return;
        }

        ratingHistory.value = await organizationApi.getRatingHistory(id);
    }

    async function loadMonitoring() {
        monitoring.value = await organizationApi.getParserMonitoring();
    }

    return {
        organization,
        organizations,
        ratingHistory,
        monitoring,
        loading,
        saving,
        refreshing,
        error,
        load,
        loadAll,
        select,
        save,
        refresh,
        loadHistory,
        loadMonitoring,
    };
}
