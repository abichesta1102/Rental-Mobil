<script setup lang="ts">
import type { Component } from 'vue';
import { Inbox } from '@lucide/vue';

type Props = {
    title: string;
    description?: string;
    icon?: Component;
};

withDefaults(defineProps<Props>(), {
    icon: Inbox,
});
</script>

<template>
    <div class="flex flex-col items-center justify-center rounded-[6px] border border-dashed border-border p-8 text-center bg-card">
        <div class="flex h-10 w-10 items-center justify-center rounded-[6px] bg-muted text-muted-foreground mb-3">
            <component :is="icon" class="h-5 w-5" />
        </div>
        <h3 class="text-sm font-semibold text-foreground">{{ title }}</h3>
        <p v-if="description" class="mt-1 text-xs text-muted-foreground max-w-sm leading-relaxed">
            {{ description }}
        </p>
        <div v-if="$slots.default" class="mt-4">
            <slot />
        </div>
    </div>
</template>
