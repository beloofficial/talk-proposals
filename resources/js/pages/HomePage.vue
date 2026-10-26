<script setup lang="ts">
import { useAuthStore } from '@/stores/auth';

const auth = useAuthStore();
</script>

<template>
    <h1 class="text-2xl font-semibold">Welcome, {{ auth.user?.name }}</h1>
</template>
