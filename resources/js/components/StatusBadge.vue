<script setup lang="ts">
import type { ProposalStatus } from '@/types';

defineProps<{ status: ProposalStatus }>();

const classes: Record<ProposalStatus, string> = {
    pending: 'bg-amber-100 text-amber-800',
    approved: 'bg-green-100 text-green-800',
    rejected: 'bg-red-100 text-red-800',
};
</script>

<template>
    <span :class="classes[status]" class="shrink-0 rounded-full px-2 py-0.5 text-xs font-medium capitalize">
        {{ status }}
    </span>
</template>
