import { defineStore } from 'pinia';
import * as authApi from '../api/authApi';

export const useAuthStore = defineStore('auth', {
    state: () => ({
        user: null,
        loading: false,
        bootstrapped: false,
    }),
    getters: {
        isAuthenticated: (state) => Boolean(state.user),
    },
    actions: {
        async fetchUser() {
            this.loading = true;
            try {
                this.user = await authApi.me();
            } catch {
                this.user = null;
            } finally {
                this.loading = false;
                this.bootstrapped = true;
            }
        },
        async login(credentials) {
            this.user = await authApi.login(credentials);
        },
        async logout() {
            await authApi.logout();
            this.user = null;
        },
    },
});
