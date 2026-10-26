<script setup lang="ts">
import { computed, ref, useId } from 'vue';

const props = defineProps<{
    label: string;
    suggestions: string[];
    allowNew?: boolean;
    error?: string;
}>();

const model = defineModel<string[]>({ required: true });
const listId = useId();
const draft = ref('');
const open = ref(false);
const highlighted = ref(-1);

const options = computed(() => {
    const query = draft.value.trim().toLowerCase();

    return props.suggestions.filter((name) => name.toLowerCase().includes(query) && !isAdded(name));
});

function isAdded(name: string) {
    return model.value.some((tag) => tag.toLowerCase() === name.toLowerCase());
}

function add(name: string) {
    const tag = props.allowNew
        ? name.trim()
        : props.suggestions.find((suggestion) => suggestion.toLowerCase() === name.trim().toLowerCase());

    if (tag && !isAdded(tag)) {
        model.value = [...model.value, tag];
    }

    draft.value = '';
    highlighted.value = -1;
}

function remove(name: string) {
    model.value = model.value.filter((tag) => tag !== name);
}

function addHighlightedOrDraft() {
    add(options.value[highlighted.value] ?? draft.value);
}

function move(step: number) {
    open.value = true;

    if (options.value.length) {
        highlighted.value = (highlighted.value + step + options.value.length) % options.value.length;
    }
}

function onInput() {
    open.value = true;
    highlighted.value = -1;
}

function onBlur() {
    add(draft.value);
    open.value = false;
}
</script>

<template>
    <div>
        <span class="text-sm font-medium text-slate-700">{{ label }}</span>
        <div class="relative mt-1">
            <div
                class="flex flex-wrap items-center gap-2 rounded-md border border-slate-300 bg-white px-2 py-1.5 focus-within:border-indigo-500 focus-within:ring-2 focus-within:ring-indigo-200"
            >
                <span
                    v-for="tag in model"
                    :key="tag"
                    class="inline-flex items-center gap-1 rounded bg-indigo-50 px-2 py-0.5 text-sm text-indigo-700"
                >
                    {{ tag }}
                    <button
                        type="button"
                        class="text-indigo-400 hover:text-indigo-700"
                        :aria-label="`Remove ${tag}`"
                        @click="remove(tag)"
                    >
                        ×
                    </button>
                </span>
                <input
                    v-model="draft"
                    role="combobox"
                    :aria-label="label"
                    :aria-controls="listId"
                    :aria-expanded="open && options.length > 0"
                    placeholder="Type or pick a tag"
                    class="min-w-40 flex-1 border-0 p-1 text-sm focus:outline-none"
                    @focus="open = true"
                    @input="onInput"
                    @blur="onBlur"
                    @keydown.enter.prevent="addHighlightedOrDraft"
                    @keydown.down.prevent="move(1)"
                    @keydown.up.prevent="move(-1)"
                    @keydown.esc="open = false"
                />
            </div>

            <ul
                v-if="open && options.length"
                :id="listId"
                role="listbox"
                class="absolute inset-x-0 top-full z-10 mt-1 max-h-48 overflow-auto rounded-md border border-slate-200 bg-white py-1 text-sm shadow-lg"
            >
                <li
                    v-for="(name, index) in options"
                    :key="name"
                    role="option"
                    :aria-selected="index === highlighted"
                    :class="index === highlighted ? 'bg-indigo-50 text-indigo-700' : 'text-slate-700'"
                    class="cursor-pointer px-3 py-1.5 hover:bg-indigo-50"
                    @mousedown.prevent="add(name)"
                >
                    {{ name }}
                </li>
            </ul>
        </div>
        <span v-if="error" class="mt-1 block text-sm text-red-600">{{ error }}</span>
    </div>
</template>
