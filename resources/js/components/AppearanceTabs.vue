<script setup lang="ts">
import { Monitor, Moon, Sun } from '@lucide/vue';
import { useAppearance } from '@/composables/useAppearance';

const { appearance, updateAppearance } = useAppearance();

const tabs = [
    { value: 'light', Icon: Sun, label: 'Terang' },
    { value: 'dark', Icon: Moon, label: 'Gelap' },
    { value: 'system', Icon: Monitor, label: 'Sistem' },
] as const;
</script>

<template>
    <div
        class="inline-flex gap-1 rounded-[6px] border border-border bg-stone-100 p-1 dark:bg-stone-900"
    >
        <button
            v-for="{ value, Icon, label } in tabs"
            :key="value"
            @click="updateAppearance(value)"
            :class="[
                'flex items-center rounded-[6px] px-3.5 py-1.5 min-h-[36px] text-sm font-medium transition-colors cursor-pointer',
                appearance === value
                    ? 'bg-card text-foreground shadow-xs border border-border/60'
                    : 'text-muted-foreground hover:bg-stone-200/60 hover:text-foreground dark:hover:bg-stone-800/60',
            ]"
        >
            <component :is="Icon" class="-ml-1 h-4 w-4" />
            <span class="ml-1.5">{{ label }}</span>
        </button>
    </div>
</template>
