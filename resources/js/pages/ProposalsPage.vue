<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import { useRouter } from 'vue-router';
import Pagination from '@/components/Pagination.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import TagInput from '@/components/TagInput.vue';
import { http } from '@/http';
import { onProposalChange } from '@/realtime';
import { useAuthStore } from '@/stores/auth';
import type { Paginated, Proposal, Resource, ReviewTab, Tag } from '@/types';

const reviewTabs: Record<ReviewTab, string> = {
    all: 'All',
    pending: 'To review',
    done: 'Reviewed',
};

const auth = useAuthStore();
const router = useRouter();
const isSpeaker = computed(() => auth.user?.role === 'speaker');
const isReviewer = computed(() => auth.user?.role === 'reviewer');

const proposals = ref<Proposal[]>([]);
const loading = ref(true);
const tags = ref<Tag[]>([]);
const search = ref('');
const selectedTagNames = ref<string[]>([]);
const reviewTab = ref<ReviewTab>('all');
const filtered = computed(
    () => search.value !== '' || selectedTagNames.value.length > 0 || reviewTab.value !== 'all',
);
const page = ref(1);
const lastPage = ref(1);

let latestRequest = 0;

async function loadProposals() {
    const request = ++latestRequest;

    try {
        const { data } = await http.get<Paginated<Proposal>>('/api/proposals', {
            params: {
                page: page.value,
                search: search.value || undefined,
                tags: tags.value.filter((tag) => selectedTagNames.value.includes(tag.name)).map((tag) => tag.id),
                review: reviewTab.value === 'all' ? undefined : reviewTab.value,
            },
        });

        if (request === latestRequest) {
            proposals.value = data.data;
            lastPage.value = data.meta.last_page;
        }
    } finally {
        if (request === latestRequest) {
            loading.value = false;
        }
    }
}

function applyFilters() {
    page.value = 1;
    loadProposals();
}

function goToPage(target: number) {
    page.value = target;
    loadProposals();
    window.scrollTo({ top: 0 });
}

function openProposal(id: number) {
    router.push({ name: 'proposals.show', params: { id } });
}

async function loadTags() {
    const { data } = await http.get<Resource<Tag[]>>('/api/tags');
    tags.value = data.data;
}

let searchTimer: ReturnType<typeof setTimeout> | undefined;

watch(search, () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(applyFilters, 300);
});
watch([selectedTagNames, reviewTab], applyFilters);

onMounted(() => Promise.all([loadProposals(), loadTags()]));

onProposalChange(loadProposals);
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

    <div
        v-if="isReviewer"
        role="group"
        aria-label="Review progress"
        class="mt-4 grid grid-cols-3 gap-1 rounded-lg bg-slate-100 p-1 sm:inline-grid"
    >
        <button
            v-for="(label, tab) in reviewTabs"
            :key="tab"
            type="button"
            :aria-pressed="reviewTab === tab"
            :class="reviewTab === tab ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-600 hover:text-slate-900'"
            class="rounded-md px-4 py-1.5 text-sm font-medium whitespace-nowrap"
            @click="reviewTab = tab"
        >
            {{ label }}
        </button>
    </div>

    <p v-if="loading" class="mt-6 text-slate-500">Loading…</p>
    <p v-else-if="proposals.length === 0" class="mt-6 text-slate-500">
        {{ filtered ? 'No proposals match your filters.' : 'No proposals yet.' }}
    </p>

    <div v-else class="mt-6 overflow-hidden rounded-lg bg-white shadow-sm">
        <table class="w-full text-left text-sm">
            <thead class="border-b border-slate-200 text-xs text-slate-500">
                <tr>
                    <th scope="col" class="px-4 py-3 font-medium">Proposal</th>
                    <th scope="col" class="px-4 py-3 font-medium">Status</th>
                    <th v-if="!isSpeaker" scope="col" class="px-4 py-3 text-right font-medium">Reviews</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <tr
                    v-for="proposal in proposals"
                    :key="proposal.id"
                    class="cursor-pointer hover:bg-slate-50"
                    @click="openProposal(proposal.id)"
                >
                    <td class="px-4 py-3">
                        <RouterLink
                            :to="{ name: 'proposals.show', params: { id: proposal.id } }"
                            class="font-medium text-slate-900 hover:text-indigo-700"
                            @click.stop
                        >
                            {{ proposal.title }}
                        </RouterLink>
                        <p class="mt-0.5 text-xs text-slate-500">
                            <template v-if="!isSpeaker">{{ proposal.speaker.name }} · </template>
                            {{ new Date(proposal.created_at).toLocaleDateString() }}
                            <template v-if="proposal.has_file"> · PDF</template>
                        </p>
                        <div v-if="proposal.tags.length" class="mt-1.5 flex flex-wrap gap-1">
                            <span
                                v-for="tag in proposal.tags"
                                :key="tag.id"
                                class="rounded bg-slate-100 px-2 py-0.5 text-xs text-slate-600"
                            >
                                {{ tag.name }}
                            </span>
                        </div>
                    </td>
                    <td class="px-4 py-3 align-top">
                        <StatusBadge :status="proposal.status" />
                    </td>
                    <td v-if="!isSpeaker" class="px-4 py-3 text-right align-top text-slate-700">
                        {{ proposal.reviews_count }}
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    <Pagination :page="page" :last-page="lastPage" @change="goToPage" />
</template>
