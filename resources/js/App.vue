<script setup lang="ts">
import { watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import NotificationToasts from '@/components/NotificationToasts.vue';
import { requestError } from '@/http';
import { startRealtime, stopRealtime } from '@/realtime';
import { useAuthStore } from '@/stores/auth';

const auth = useAuthStore();
const route = useRoute();
const router = useRouter();

watch(
    () => auth.user,
    (user) => {
        stopRealtime();

        if (user) {
            startRealtime(user);
        }
    },
    { immediate: true },
);

async function logout() {
    await auth.logout();
    await router.push({ name: 'login' });
}
</script>

<template>
    <header v-if="auth.user" class="border-b border-slate-200 bg-white">
        <div class="mx-auto flex max-w-5xl items-center justify-between gap-4 px-4 py-3">
            <RouterLink :to="{ name: 'proposals' }" class="shrink-0 font-semibold whitespace-nowrap">
                Talk Proposals
            </RouterLink>

            <div class="flex min-w-0 items-center gap-4 text-sm">
                <span class="truncate">
                    {{ auth.user.name }}
                    <span class="hidden text-slate-500 sm:inline">({{ auth.user.role }})</span>
                </span>
                <button
                    type="button"
                    class="shrink-0 whitespace-nowrap text-indigo-600 hover:underline"
                    @click="logout"
                >
                    Log out
                </button>
            </div>
        </div>
    </header>

    <div v-if="requestError" class="mx-auto mt-4 max-w-5xl px-4">
        <div
            role="alert"
            class="flex items-start justify-between gap-4 rounded-md bg-red-50 px-4 py-3 text-sm text-red-800 ring-1 ring-red-200"
        >
            <span>{{ requestError }}</span>
            <button type="button" aria-label="Dismiss" class="text-red-500 hover:text-red-800" @click="requestError = null">
                ×
            </button>
        </div>
    </div>

    <main class="mx-auto max-w-5xl px-4 py-8">
        <RouterView :key="route.path" />
    </main>

    <NotificationToasts />
</template>
