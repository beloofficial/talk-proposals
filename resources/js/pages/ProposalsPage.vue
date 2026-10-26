<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import StatusBadge from '@/components/StatusBadge.vue';
import TagInput from '@/components/TagInput.vue';
import { http } from '@/http';
import { useAuthStore } from '@/stores/auth';
import type { Proposal, Resource, Tag } from '@/types';

const auth = useAuthStore();
const isSpeaker = computed(() => auth.user?.role === 'speaker');

const proposals = ref<Proposal[]>([]);
const loading = ref(true);
const tags = ref<Tag[]>([]);
const search = ref('');
const selectedTagNames = ref<string[]>([]);
const filtered = computed(() => search.value !== '' || selectedTagNames.value.length > 0);

let latestRequest = 0;

async function loadProposals() {
    const request = ++latestRequest;
    const { data } = await http.get<Resource<Proposal[]>>('/api/proposals', {
        params: {
            search: search.value || undefined,
            tags: tags.value.filter((tag) => selectedTagNames.value.includes(tag.name)).map((tag) => tag.id),
        },
    });

    if (request === latestRequest) {
        proposals.value = data.data;
        loading.value = false;
    }
}

async function loadTags() {
    const { data } = await http.get<Resource<Tag[]>>('/api/tags');
    tags.value = data.data;
}

let searchTimer: ReturnType<typeof setTimeout> | undefined;

watch(search, () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(loadProposals, 300);
});
watch(selectedTagNames, loadProposals);

onMounted(() => Promise.all([loadProposals(), loadTags()]));
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

    <div class="mt-6 grid gap-4 sm:grid-cols-2">
        <label class="block">
            <span class="text-sm font-medium text-slate-700">Search by title</span>
            <input
                v-model="search"
                type="search"
                class="mt-1 block w-full rounded-md border border-slate-300 bg-white px-3 py-2.5 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 focus:outline-none"
            />
        </label>
        <TagInput v-model="selectedTagNames" label="Filter by tags" :suggestions="tags.map((tag) => tag.name)" />
    </div>

    <p v-if="loading" class="mt-6 text-slate-500">Loading…</p>
    <p v-else-if="proposals.length === 0" class="mt-6 text-slate-500">
        {{ filtered ? 'No proposals match your filters.' : 'No proposals yet.' }}
    </p>

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
                <div v-if="proposal.tags.length" class="mt-2 flex flex-wrap gap-1">
                    <span
                        v-for="tag in proposal.tags"
                        :key="tag.id"
                        class="rounded bg-slate-100 px-2 py-0.5 text-xs text-slate-600"
                    >
                        {{ tag.name }}
                    </span>
                </div>
                <p class="mt-2 text-xs text-slate-500">
                    <template v-if="!isSpeaker">{{ proposal.speaker.name }} · </template>
                    {{ new Date(proposal.created_at).toLocaleDateString() }}
                    <template v-if="proposal.has_file"> · PDF attached</template>
                </p>
            </RouterLink>
        </li>
    </ul>
</template>
