<script setup lang="ts">
import { reactive, ref } from 'vue';
import { http, validationErrors, type ValidationErrors } from '@/http';
import type { Resource, Review } from '@/types';

const props = defineProps<{
    proposalId: number;
    maxRating: number;
    review?: Review;
}>();

const emit = defineEmits<{ saved: [review: Review] }>();

const form = reactive<{ rating: number | null; comment: string }>({
    rating: props.review?.rating ?? null,
    comment: props.review?.comment ?? '',
});
const errors = ref<ValidationErrors>({});
const processing = ref(false);

async function submit() {
    processing.value = true;
    errors.value = {};

    try {
        const { data } = await http.put<Resource<Review>>(`/api/proposals/${props.proposalId}/review`, form);
        emit('saved', data.data);
    } catch (error) {
        errors.value = validationErrors(error);
    } finally {
        processing.value = false;
    }
}
</script>

<template>
    <form class="space-y-4" novalidate @submit.prevent="submit">
        <fieldset>
            <legend class="text-sm font-medium text-slate-700">Rating</legend>
            <div class="mt-1 flex flex-wrap gap-2">
                <button
                    v-for="value in maxRating"
                    :key="value"
                    type="button"
                    :aria-pressed="form.rating === value"
                    :class="
                        form.rating === value
                            ? 'border-indigo-600 bg-indigo-600 text-white'
                            : 'border-slate-300 bg-white text-slate-700 hover:bg-slate-50'
                    "
                    class="size-10 rounded-md border text-sm font-medium"
                    @click="form.rating = value"
                >
                    {{ value }}
                </button>
            </div>
            <span v-if="errors.rating" class="mt-1 block text-sm text-red-600">{{ errors.rating[0] }}</span>
        </fieldset>

        <label class="block">
            <span class="text-sm font-medium text-slate-700">Comment (optional)</span>
            <textarea
                v-model="form.comment"
                rows="4"
                class="mt-1 block w-full rounded-md border border-slate-300 bg-white px-3 py-2 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 focus:outline-none"
            />
            <span v-if="errors.comment" class="mt-1 block text-sm text-red-600">{{ errors.comment[0] }}</span>
        </label>

        <button
            type="submit"
            :disabled="processing"
            class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-500 disabled:opacity-50"
        >
            {{ review ? 'Update review' : 'Submit review' }}
        </button>
    </form>
</template>
