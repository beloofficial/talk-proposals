<script setup lang="ts">
defineOptions({ inheritAttrs: false });

defineProps<{
    label: string;
    error?: string[];
}>();

const model = defineModel<string>({ required: true });
</script>

<template>
    <label class="block">
        <span class="text-sm font-medium text-slate-700">{{ label }}</span>
        <input
            v-model="model"
            v-bind="$attrs"
            class="mt-1 block w-full rounded-md border border-slate-300 bg-white px-3 py-2 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 focus:outline-none"
        />
        <span v-if="error" class="mt-1 block text-sm text-red-600">{{ error[0] }}</span>
    </label>
</template>
