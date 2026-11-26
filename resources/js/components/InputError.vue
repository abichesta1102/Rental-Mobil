<script setup lang="ts">
import { translateError } from '@/lib/validation';

defineProps<{
    message?: string;
}>();
</script>

<template>
    <div v-show="message">
        <p class="text-sm font-medium text-destructive mt-1">
            {{ translateError(message) }}
        </p>
    </div>
</template>
