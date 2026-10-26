<script setup lang="ts">
import { useRouter } from 'vue-router';
import { useAuthStore } from '@/stores/auth';

const auth = useAuthStore();
const router = useRouter();

async function logout() {
    await auth.logout();
    await router.push({ name: 'login' });
}
</script>

<template>
    <header v-if="auth.user" class="border-b border-slate-200 bg-white">
        <div class="mx-auto flex max-w-5xl items-center justify-between gap-4 px-4 py-3">
            <RouterLink :to="{ name: 'proposals' }" class="font-semibold">Talk Proposals</RouterLink>

            <div class="flex items-center gap-4 text-sm">
                <span>
                    {{ auth.user.name }}
                    <span class="text-slate-500">({{ auth.user.role }})</span>
                </span>
                <button type="button" class="text-indigo-600 hover:underline" @click="logout">Log out</button>
            </div>
        </div>
    </header>

    <main class="mx-auto max-w-5xl px-4 py-8">
        <RouterView />
    </main>
</template>
