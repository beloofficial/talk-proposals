import { defineStore } from 'pinia';
import { ref } from 'vue';
import { http } from '@/http';
import type { Resource, Role, User } from '@/types';

export interface LoginForm {
    email: string;
    password: string;
}

export interface RegisterForm extends LoginForm {
    name: string;
    password_confirmation: string;
    role: Role;
}

export const useAuthStore = defineStore('auth', () => {
    const user = ref<User | null>(null);

    async function fetchUser() {
        try {
            const { data } = await http.get<Resource<User>>('/api/me');
            user.value = data.data;
        } catch {
            user.value = null;
        }
    }

    async function login(form: LoginForm) {
        const { data } = await http.post<Resource<User>>('/login', form);
        user.value = data.data;
    }

    async function register(form: RegisterForm) {
        const { data } = await http.post<Resource<User>>('/register', form);
        user.value = data.data;
    }

    async function logout() {
        await http.post('/logout');
        user.value = null;
    }

    return { user, fetchUser, login, register, logout };
});
