<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { getStatusClass, getStatusLabel, getStatusVariant } from '@/lib/status';
import { cn } from '@/lib/utils';

const props = defineProps<{
    status: string;
    class?: string;
}>();
</script>

<template>
    <Badge
        :variant="getStatusVariant(status)"
        :class="cn('rounded-[6px] font-medium text-xs px-2.5 py-0.5 border shadow-none', getStatusClass(status), props.class)"
    >
        {{ getStatusLabel(status) }}
    </Badge>
</template>
