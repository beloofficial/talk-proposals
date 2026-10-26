<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { http } from '@/http';
import { useAuthStore } from '@/stores/auth';
import type { Proposal, ProposalStatus, Resource } from '@/types';

const statusClasses: Record<ProposalStatus, string> = {
    pending: 'bg-amber-100 text-amber-800',
    approved: 'bg-green-100 text-green-800',
    rejected: 'bg-red-100 text-red-800',
};

const auth = useAuthStore();
const proposals = ref<Proposal[]>([]);
const loading = ref(true);

onMounted(async () => {
    const { data } = await http.get<Resource<Proposal[]>>('/api/proposals');
    proposals.value = data.data;
    loading.value = false;
});
</script>

<template>
    <div class="flex items-center justify-between gap-4">
        <h1 class="text-2xl font-semibold">My proposals</h1>
        <RouterLink
            v-if="auth.user?.role === 'speaker'"
            :to="{ name: 'proposals.create' }"
            class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-500"
        >
            New proposal
        </RouterLink>
    </div>

    <p v-if="loading" class="mt-6 text-slate-500">Loading…</p>
    <p v-else-if="proposals.length === 0" class="mt-6 text-slate-500">No proposals yet.</p>

    <ul v-else class="mt-6 space-y-3">
        <li v-for="proposal in proposals" :key="proposal.id" class="rounded-lg bg-white p-4 shadow-sm">
            <div class="flex items-start justify-between gap-4">
                <h2 class="font-medium">{{ proposal.title }}</h2>
                <span
                    :class="statusClasses[proposal.status]"
                    class="shrink-0 rounded-full px-2 py-0.5 text-xs font-medium capitalize"
                >
                    {{ proposal.status }}
                </span>
            </div>
            <p class="mt-1 line-clamp-2 text-sm text-slate-600">{{ proposal.description }}</p>
            <p class="mt-2 text-xs text-slate-500">
                {{ new Date(proposal.created_at).toLocaleDateString() }}
                <template v-if="proposal.has_file"> · PDF attached</template>
            </p>
        </li>
    </ul>
</template>
