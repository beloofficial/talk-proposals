<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue';
import { useRouter } from 'vue-router';
import TagInput from '@/components/TagInput.vue';
import TextInput from '@/components/TextInput.vue';
import { http, validationErrors, type ValidationErrors } from '@/http';
import type { Resource, Tag } from '@/types';

const router = useRouter();

const form = reactive<{ title: string; description: string; tags: string[]; file: File | null }>({
    title: '',
    description: '',
    tags: [],
    file: null,
});
const errors = ref<ValidationErrors>({});
const processing = ref(false);
const tagSuggestions = ref<string[]>([]);

const tagsError = computed(
    () => Object.entries(errors.value).find(([field]) => field === 'tags' || field.startsWith('tags.'))?.[1][0],
);

onMounted(async () => {
    const { data } = await http.get<Resource<Tag[]>>('/api/tags');
    tagSuggestions.value = data.data.map((tag) => tag.name);
});

function pickFile(event: Event) {
    form.file = (event.target as HTMLInputElement).files?.[0] ?? null;
}

async function submit() {
    processing.value = true;
    errors.value = {};

    try {
        await http.postForm('/api/proposals', form);
        await router.push({ name: 'proposals' });
    } catch (error) {
        errors.value = validationErrors(error);
    } finally {
        processing.value = false;
    }
}
</script>

<template>
    <div class="mx-auto max-w-2xl">
        <h1 class="text-2xl font-semibold">New proposal</h1>

        <form class="mt-6 space-y-4 rounded-lg bg-white p-6 shadow-sm" novalidate @submit.prevent="submit">
            <TextInput v-model="form.title" label="Title" :error="errors.title" />

            <label class="block">
                <span class="text-sm font-medium text-slate-700">Description</span>
                <textarea
                    v-model="form.description"
                    rows="6"
                    class="mt-1 block w-full rounded-md border border-slate-300 bg-white px-3 py-2 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 focus:outline-none"
                />
                <span v-if="errors.description" class="mt-1 block text-sm text-red-600">
                    {{ errors.description[0] }}
                </span>
            </label>

            <TagInput
                v-model="form.tags"
                label="Tags (optional)"
                :suggestions="tagSuggestions"
                allow-new
                :error="tagsError"
            />

            <label class="block">
                <span class="text-sm font-medium text-slate-700">Attachment (PDF up to 4 MB, optional)</span>
                <input
                    type="file"
                    accept="application/pdf"
                    class="mt-1 block w-full text-sm file:mr-4 file:rounded-md file:border-0 file:bg-slate-100 file:px-4 file:py-2 file:font-medium hover:file:bg-slate-200"
                    @change="pickFile"
                />
                <span v-if="errors.file" class="mt-1 block text-sm text-red-600">{{ errors.file[0] }}</span>
            </label>

            <div class="flex justify-end gap-3">
                <RouterLink
                    :to="{ name: 'proposals' }"
                    class="rounded-md px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-100"
                >
                    Cancel
                </RouterLink>
                <button
                    type="submit"
                    :disabled="processing"
                    class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-500 disabled:opacity-50"
                >
                    Submit proposal
                </button>
            </div>
        </form>
    </div>
</template>
