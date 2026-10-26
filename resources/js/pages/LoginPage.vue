<script setup lang="ts">
import { reactive, ref } from 'vue';
import { useRouter } from 'vue-router';
import TextInput from '@/components/TextInput.vue';
import { validationErrors, type ValidationErrors } from '@/http';
import { useAuthStore, type LoginForm } from '@/stores/auth';

const auth = useAuthStore();
const router = useRouter();

const form = reactive<LoginForm>({ email: '', password: '' });
const errors = ref<ValidationErrors>({});
const processing = ref(false);

async function submit() {
    processing.value = true;
    errors.value = {};

    try {
        await auth.login(form);
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
        <h1 class="text-2xl font-semibold">Log in</h1>

        <form class="mt-6 space-y-4 rounded-lg bg-white p-6 shadow-sm" novalidate @submit.prevent="submit">
            <TextInput v-model="form.email" label="Email" type="email" autocomplete="email" :error="errors.email" />
            <TextInput
                v-model="form.password"
                label="Password"
                type="password"
                autocomplete="current-password"
                :error="errors.password"
            />

            <button
                type="submit"
                :disabled="processing"
                class="w-full rounded-md bg-indigo-600 px-4 py-2 font-medium text-white hover:bg-indigo-500 disabled:opacity-50"
            >
                Log in
            </button>
        </form>

        <p class="mt-4 text-center text-sm text-slate-600">
            No account?
            <RouterLink :to="{ name: 'register' }" class="text-indigo-600 hover:underline">Register</RouterLink>
        </p>
    </div>
</template>
