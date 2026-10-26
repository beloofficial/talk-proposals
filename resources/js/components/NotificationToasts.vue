<script setup lang="ts">
import { useNotificationsStore } from '@/stores/notifications';

const notifications = useNotificationsStore();
</script>

<template>
    <div
        aria-live="polite"
        class="pointer-events-none fixed inset-x-4 bottom-4 z-50 flex flex-col gap-2 sm:right-4 sm:left-auto sm:w-80"
    >
        <div
            v-for="item in notifications.items"
            :key="item.id"
            class="pointer-events-auto rounded-lg bg-white p-4 text-sm shadow-lg ring-1 ring-slate-200"
        >
            <div class="flex items-start justify-between gap-3">
                <p class="text-slate-800">{{ item.message }}</p>
                <button
                    type="button"
                    aria-label="Dismiss"
                    class="text-slate-400 hover:text-slate-700"
                    @click="notifications.dismiss(item.id)"
                >
                    ×
                </button>
            </div>
            <RouterLink
                :to="{ name: 'proposals.show', params: { id: item.proposalId } }"
                class="mt-2 inline-block font-medium text-indigo-600 hover:underline"
                @click="notifications.dismiss(item.id)"
            >
                Open proposal
            </RouterLink>
        </div>
    </div>
</template>
