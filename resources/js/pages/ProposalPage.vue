<script setup lang="ts">
import { onMounted, ref } from 'vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { http } from '@/http';
import type { Proposal, Resource } from '@/types';

const props = defineProps<{ id: string }>();

const proposal = ref<Proposal | null>(null);
const failed = ref(false);

onMounted(async () => {
    try {
        const { data } = await http.get<Resource<Proposal>>(`/api/proposals/${props.id}`);
        proposal.value = data.data;
    } catch {
        failed.value = true;
    }
});
</script>

<template>
    <RouterLink :to="{ name: 'proposals' }" class="text-sm text-indigo-600 hover:underline">← Back to proposals</RouterLink>

    <p v-if="failed" class="mt-6 text-slate-500">This proposal does not exist or you have no access to it.</p>
    <p v-else-if="!proposal" class="mt-6 text-slate-500">Loading…</p>

    <article v-else class="mt-4 rounded-lg bg-white p-6 shadow-sm">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <h1 class="text-2xl font-semibold">{{ proposal.title }}</h1>
            <StatusBadge :status="proposal.status" />
        </div>
        <p class="mt-1 text-sm text-slate-500">
            {{ proposal.speaker.name }} · {{ new Date(proposal.created_at).toLocaleDateString() }}
        </p>
        <div v-if="proposal.tags.length" class="mt-3 flex flex-wrap gap-1">
            <span v-for="tag in proposal.tags" :key="tag.id" class="rounded bg-slate-100 px-2 py-0.5 text-xs text-slate-600">
                {{ tag.name }}
            </span>
        </div>

        <p class="mt-4 whitespace-pre-line text-slate-700">{{ proposal.description }}</p>

        <a
            v-if="proposal.has_file"
            :href="`/api/proposals/${proposal.id}/file`"
            class="mt-6 inline-block rounded-md border border-slate-300 px-4 py-2 text-sm font-medium hover:bg-slate-50"
        >
            Download PDF
        </a>
    </article>
</template>
