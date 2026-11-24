<script setup lang="ts">
import { AlertTriangle, CheckCircle, Info } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

interface Props {
    open: boolean;
    title: string;
    description: string;
    confirmText?: string;
    cancelText?: string;
    variant?: 'default' | 'destructive' | 'secondary' | 'outline';
    loading?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    confirmText: 'Lanjutkan',
    cancelText: 'Batal',
    variant: 'default',
    loading: false,
});

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
    (e: 'confirm'): void;
    (e: 'cancel'): void;
}>();

function onCancel(): void {
    emit('update:open', false);
    emit('cancel');
}

function onConfirm(): void {
    emit('confirm');
}
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent class="sm:max-w-[425px]">
            <DialogHeader class="gap-2">
                <div class="flex items-center gap-3">
                    <div
                        v-if="variant === 'destructive'"
                        class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-red-100 text-red-600 dark:bg-red-950 dark:text-red-400"
                    >
                        <AlertTriangle class="h-5 w-5" />
                    </div>
                    <div
                        v-else
                        class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary"
                    >
                        <Info class="h-5 w-5" />
                    </div>
                    <div>
                        <DialogTitle class="text-base">{{ title }}</DialogTitle>
                    </div>
                </div>
                <DialogDescription class="pt-2 text-sm text-muted-foreground leading-relaxed">
                    {{ description }}
                </DialogDescription>
            </DialogHeader>

            <DialogFooter class="mt-4 gap-2 sm:gap-0">
                <Button variant="outline" :disabled="loading" @click="onCancel">
                    {{ cancelText }}
                </Button>
                <Button :variant="variant" :disabled="loading" @click="onConfirm">
                    <span v-if="loading" class="mr-2 inline-block h-4 w-4 animate-spin rounded-full border-2 border-current border-t-transparent"></span>
                    {{ confirmText }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
