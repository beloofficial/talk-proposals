<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { http } from '@/http';
import { useAuthStore } from '@/stores/auth';
import type { Proposal, Resource } from '@/types';

const auth = useAuthStore();
const isSpeaker = computed(() => auth.user?.role === 'speaker');

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
        <h1 class="text-2xl font-semibold">{{ isSpeaker ? 'My proposals' : 'All proposals' }}</h1>
        <RouterLink
            v-if="isSpeaker"
            :to="{ name: 'proposals.create' }"
            class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-500"
        >
            New proposal
        </RouterLink>
    </div>

    <p v-if="loading" class="mt-6 text-slate-500">Loading…</p>
    <p v-else-if="proposals.length === 0" class="mt-6 text-slate-500">No proposals yet.</p>

    <ul v-else class="mt-6 space-y-3">
        <li v-for="proposal in proposals" :key="proposal.id">
            <RouterLink
                :to="{ name: 'proposals.show', params: { id: proposal.id } }"
                class="block rounded-lg bg-white p-4 shadow-sm hover:ring-2 hover:ring-indigo-200"
            >
                <div class="flex items-start justify-between gap-4">
                    <h2 class="font-medium">{{ proposal.title }}</h2>
                    <StatusBadge :status="proposal.status" />
                </div>
                <p class="mt-1 line-clamp-2 text-sm text-slate-600">{{ proposal.description }}</p>
                <p class="mt-2 text-xs text-slate-500">
                    <template v-if="!isSpeaker">{{ proposal.speaker.name }} · </template>
                    {{ new Date(proposal.created_at).toLocaleDateString() }}
                    <template v-if="proposal.has_file"> · PDF attached</template>
                </p>
            </RouterLink>
        </li>
    </ul>
</template>
