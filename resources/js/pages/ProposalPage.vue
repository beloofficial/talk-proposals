<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import ReviewForm from '@/components/ReviewForm.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { http } from '@/http';
import { useAuthStore } from '@/stores/auth';
import type { Proposal, ProposalStatus, Resource, Review } from '@/types';

const props = defineProps<{ id: string }>();

const statusOptions: Record<ProposalStatus, { label: string; activeClass: string }> = {
    pending: { label: 'Pending', activeClass: 'bg-amber-100 text-amber-800 ring-amber-300' },
    approved: { label: 'Approved', activeClass: 'bg-green-100 text-green-800 ring-green-300' },
    rejected: { label: 'Rejected', activeClass: 'bg-red-100 text-red-800 ring-red-300' },
};

const auth = useAuthStore();
const proposal = ref<Proposal | null>(null);
const maxRating = ref(5);
const failed = ref(false);
const savingStatus = ref(false);

const myReview = computed(() => proposal.value?.reviews?.find((review) => review.reviewer.id === auth.user?.id));
const averageRating = computed(() => {
    const reviews = proposal.value?.reviews ?? [];

    return reviews.length ? reviews.reduce((sum, review) => sum + review.rating, 0) / reviews.length : null;
});

onMounted(async () => {
    try {
        const { data } = await http.get<Resource<Proposal> & { meta: { max_rating: number } }>(
            `/api/proposals/${props.id}`,
        );
        proposal.value = data.data;
        maxRating.value = data.meta.max_rating;
    } catch {
        failed.value = true;
    }
});

async function changeStatus(status: ProposalStatus) {
    if (status === proposal.value!.status) {
        return;
    }

    savingStatus.value = true;

    try {
        const { data } = await http.patch<Resource<Proposal>>(`/api/proposals/${props.id}/status`, { status });
        proposal.value!.status = data.data.status;
    } finally {
        savingStatus.value = false;
    }
}

function reviewSaved(review: Review) {
    const reviews = proposal.value!.reviews ?? [];

    proposal.value!.reviews = [review, ...reviews.filter((existing) => existing.id !== review.id)];
}
</script>

<template>
    <RouterLink :to="{ name: 'proposals' }" class="text-sm text-indigo-600 hover:underline">← Back to proposals</RouterLink>

    <p v-if="failed" class="mt-6 text-slate-500">This proposal does not exist or you have no access to it.</p>
    <p v-else-if="!proposal" class="mt-6 text-slate-500">Loading…</p>

    <template v-else>
        <article class="mt-4 rounded-lg bg-white p-6 shadow-sm">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <h1 class="text-2xl font-semibold">{{ proposal.title }}</h1>
                <StatusBadge :status="proposal.status" />
            </div>
            <p class="mt-1 text-sm text-slate-500">
                {{ proposal.speaker.name }} · {{ new Date(proposal.created_at).toLocaleDateString() }}
            </p>
            <div v-if="proposal.tags.length" class="mt-3 flex flex-wrap gap-1">
                <span
                    v-for="tag in proposal.tags"
                    :key="tag.id"
                    class="rounded bg-slate-100 px-2 py-0.5 text-xs text-slate-600"
                >
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

        <section v-if="auth.user?.role === 'admin'" class="mt-6 rounded-lg bg-white p-6 shadow-sm">
            <h2 id="status-heading" class="font-semibold">Status</h2>
            <div
                role="group"
                aria-labelledby="status-heading"
                class="mt-3 inline-flex flex-wrap gap-1 rounded-lg bg-slate-100 p-1"
            >
                <button
                    v-for="(option, value) in statusOptions"
                    :key="value"
                    type="button"
                    :aria-pressed="proposal.status === value"
                    :disabled="savingStatus"
                    :class="
                        proposal.status === value
                            ? `${option.activeClass} shadow-sm ring-1`
                            : 'text-slate-600 hover:bg-white hover:text-slate-900'
                    "
                    class="rounded-md px-4 py-1.5 text-sm font-medium transition disabled:cursor-wait"
                    @click="changeStatus(value)"
                >
                    {{ option.label }}
                </button>
            </div>
        </section>

        <section v-if="auth.user?.role === 'reviewer'" class="mt-6 rounded-lg bg-white p-6 shadow-sm">
            <h2 class="font-semibold">Your review</h2>
            <ReviewForm
                class="mt-3"
                :proposal-id="proposal.id"
                :max-rating="maxRating"
                :review="myReview"
                @saved="reviewSaved"
            />
        </section>

        <section v-if="proposal.reviews" class="mt-6 rounded-lg bg-white p-6 shadow-sm">
            <div class="flex flex-wrap items-baseline justify-between gap-2">
                <h2 class="font-semibold">Reviews</h2>
                <p v-if="averageRating !== null" class="text-sm text-slate-500">
                    Average {{ averageRating.toFixed(1) }} / {{ maxRating }} from {{ proposal.reviews.length }}
                </p>
            </div>

            <p v-if="proposal.reviews.length === 0" class="mt-3 text-sm text-slate-500">No reviews yet.</p>

            <ul v-else class="mt-3 divide-y divide-slate-100">
                <li v-for="review in proposal.reviews" :key="review.id" class="py-3">
                    <div class="flex items-baseline justify-between gap-4">
                        <span class="text-sm font-medium">{{ review.reviewer.name }}</span>
                        <span class="text-sm font-semibold text-indigo-700">{{ review.rating }} / {{ maxRating }}</span>
                    </div>
                    <p v-if="review.comment" class="mt-1 whitespace-pre-line text-sm text-slate-600">
                        {{ review.comment }}
                    </p>
                </li>
            </ul>
        </section>
    </template>
</template>
