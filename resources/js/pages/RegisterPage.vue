<script setup lang="ts">
import { reactive, ref } from 'vue';
import { useRouter } from 'vue-router';
import TextInput from '@/components/TextInput.vue';
import { validationErrors, type ValidationErrors } from '@/http';
import { useAuthStore, type RegisterForm } from '@/stores/auth';
import type { Role } from '@/types';

const roles: Record<Role, string> = {
    speaker: 'Speaker',
    reviewer: 'Reviewer',
    admin: 'Administrator',
};

const auth = useAuthStore();
const router = useRouter();

const form = reactive<RegisterForm>({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    role: 'speaker',
});
const errors = ref<ValidationErrors>({});
const processing = ref(false);

async function submit() {
    processing.value = true;
    errors.value = {};

    try {
        await auth.register(form);
        await router.push({ name: 'home' });
    } catch (error) {
        errors.value = validationErrors(error);
    } finally {
        processing.value = false;
    }
}
</script>

<template>
    <div class="mx-auto max-w-sm">
        <h1 class="text-2xl font-semibold">Create an account</h1>

        <form class="mt-6 space-y-4 rounded-lg bg-white p-6 shadow-sm" novalidate @submit.prevent="submit">
            <TextInput v-model="form.name" label="Name" autocomplete="name" :error="errors.name" />
            <TextInput v-model="form.email" label="Email" type="email" autocomplete="email" :error="errors.email" />
            <TextInput
                v-model="form.password"
                label="Password"
                type="password"
                autocomplete="new-password"
                :error="errors.password"
            />
            <TextInput
                v-model="form.password_confirmation"
                label="Confirm password"
                type="password"
                autocomplete="new-password"
            />

            <label class="block">
                <span class="text-sm font-medium text-slate-700">Role</span>
                <select
                    v-model="form.role"
                    class="mt-1 block w-full rounded-md border border-slate-300 bg-white px-3 py-2 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 focus:outline-none"
                >
                    <option v-for="(label, value) in roles" :key="value" :value="value">{{ label }}</option>
                </select>
                <span v-if="errors.role" class="mt-1 block text-sm text-red-600">{{ errors.role[0] }}</span>
            </label>

            <button
                type="submit"
                :disabled="processing"
                class="w-full rounded-md bg-indigo-600 px-4 py-2 font-medium text-white hover:bg-indigo-500 disabled:opacity-50"
            >
                Register
            </button>
        </form>

        <p class="mt-4 text-center text-sm text-slate-600">
            Already registered?
            <RouterLink :to="{ name: 'login' }" class="text-indigo-600 hover:underline">Log in</RouterLink>
        </p>
    </div>
</template>
