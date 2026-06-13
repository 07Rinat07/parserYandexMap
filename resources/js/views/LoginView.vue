<template>
    <main class="auth-shell">
        <section class="auth-card">
            <div>
                <p class="eyebrow">Yandex Reviews Parser</p>
                <h1>Вход</h1>
            </div>

            <form @submit.prevent="submit">
                <label>
                    Email
                    <input v-model.trim="email" type="email" autocomplete="email" required>
                </label>
                <label>
                    Пароль
                    <input v-model="password" type="password" autocomplete="current-password" required>
                </label>

                <ErrorState v-if="error" :message="error" />

                <button type="submit" :disabled="loading">
                    <LogIn :size="18" />
                    <span>{{ loading ? 'Входим' : 'Войти' }}</span>
                </button>
            </form>
        </section>
    </main>
</template>

<script setup>
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import { LogIn } from '@lucide/vue';
import ErrorState from '../components/ErrorState.vue';
import { useAuthStore } from '../stores/auth';

const router = useRouter();
const auth = useAuthStore();
const email = ref('test@example.com');
const password = ref('password');
const loading = ref(false);
const error = ref('');

async function submit() {
    loading.value = true;
    error.value = '';

    try {
        await auth.login({ email: email.value, password: password.value });
        await router.push({ name: 'dashboard' });
    } catch (exception) {
        error.value = exception.response?.data?.message || 'Неверный email или пароль.';
    } finally {
        loading.value = false;
    }
}
</script>
