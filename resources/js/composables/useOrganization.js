import { ref } from 'vue';
import * as organizationApi from '../api/organizationApi';

export function useOrganization() {
    const organization = ref(null);
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

    async function save(url) {
        saving.value = true;
        error.value = '';
        try {
            organization.value = await organizationApi.saveOrganization(url);
        } catch (exception) {
            error.value = exception.response?.data?.errors?.yandex_url?.[0] || exception.response?.data?.message || 'Не удалось сохранить ссылку.';
            throw exception;
        } finally {
            saving.value = false;
        }
    }

    async function refresh() {
        refreshing.value = true;
        error.value = '';
        try {
            organization.value = await organizationApi.refreshOrganization();
        } catch (exception) {
            error.value = exception.response?.data?.message || 'Не удалось запустить обновление.';
        } finally {
            refreshing.value = false;
        }
    }

    return { organization, loading, saving, refreshing, error, load, save, refresh };
}
