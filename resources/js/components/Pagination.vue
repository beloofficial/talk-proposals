<script setup lang="ts">
defineProps<{
    page: number;
    lastPage: number;
}>();

const emit = defineEmits<{ change: [page: number] }>();
</script>

<template>
    <nav v-if="lastPage > 1" aria-label="Pagination" class="mt-6 flex items-center justify-between gap-4">
        <button
            type="button"
            :disabled="page <= 1"
            class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50"
            @click="emit('change', page - 1)"
        >
            Previous
        </button>
        <span class="text-sm text-slate-600">Page {{ page }} of {{ lastPage }}</span>
        <button
            type="button"
            :disabled="page >= lastPage"
            class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50"
            @click="emit('change', page + 1)"
        >
            Next
        </button>
    </nav>
</template>
