<script setup lang="ts">
type Props = {
    title: string;
    description?: string;
};

defineProps<Props>();
</script>

<template>
    <div class="mb-6 flex flex-col gap-3 pb-4 border-b border-border sm:flex-row sm:items-center sm:justify-between">
        <div class="space-y-1">
            <h1 class="text-xl font-semibold tracking-tight text-foreground sm:text-2xl">
                {{ title }}
            </h1>
            <p v-if="description" class="text-sm text-muted-foreground">
                {{ description }}
            </p>
        </div>
        <div v-if="$slots.actions || $slots.default" class="flex flex-wrap items-center gap-2">
            <slot name="actions">
                <slot />
            </slot>
        </div>
    </div>
</template>
