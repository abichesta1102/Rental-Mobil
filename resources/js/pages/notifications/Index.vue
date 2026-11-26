<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Bell, CheckCheck, Clock } from '@lucide/vue';
import EmptyState from '@/components/EmptyState.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { formatDateTime } from '@/lib/format';

interface NotificationItem {
    id: string;
    type: string;
    data: {
        title?: string;
        message?: string;
        url?: string;
        booking_id?: number;
        amount?: number;
        type?: string;
    };
    read_at: string | null;
    created_at: string;
}

interface PaginatedNotifications {
    data: NotificationItem[];
    current_page: number;
    last_page: number;
    next_page_url: string | null;
    prev_page_url: string | null;
}

defineProps<{
    notifications: PaginatedNotifications;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Notifikasi', href: '/notifications' }],
    },
});

function markAllAsRead(): void {
    router.post('/notifications/read-all');
}

function markAsRead(id: string, url?: string): void {
    if (url) {
        router.post(`/notifications/${id}/read`, { redirect: true });
    } else {
        router.post(`/notifications/${id}/read`);
    }
}
</script>

<template>
    <Head title="Daftar Notifikasi" />

    <div class="flex flex-col gap-6 p-4 sm:p-6 max-w-4xl mx-auto w-full">
        <PageHeader
            title="Notifikasi"
            description="Pemberitahuan aktivitas pemesanan, verifikasi pembayaran, dan tugas operasional."
        >
            <template #actions>
                <Button variant="outline" size="sm" class="h-9 min-h-[36px]" @click="markAllAsRead">
                    <CheckCheck class="mr-1.5 size-4" />
                    Tandai Semua Dibaca
                </Button>
            </template>
        </PageHeader>

        <div v-if="notifications.data.length === 0" class="rounded-[6px] border border-border bg-card p-6">
            <EmptyState
                title="Belum ada notifikasi"
                description="Anda belum menerima pemberitahuan apa pun saat ini."
                :icon="Bell"
            />
        </div>

        <div v-else class="space-y-3">
            <div
                v-for="item in notifications.data"
                :key="item.id"
                class="flex items-start justify-between gap-4 rounded-[6px] border p-4 transition-colors cursor-pointer"
                :class="item.read_at ? 'bg-card border-border' : 'bg-muted/40 border-primary/40'"
                @click="markAsRead(item.id, item.data.url)"
            >
                <div class="space-y-1">
                    <div class="flex items-center gap-2">
                        <span class="font-semibold text-xs text-foreground">
                            {{ item.data.title || 'Pemberitahuan' }}
                        </span>
                        <Badge v-if="!item.read_at" variant="default" class="text-[10px] px-1.5 py-0 rounded-[4px]">
                            Baru
                        </Badge>
                    </div>
                    <p class="text-xs text-muted-foreground leading-normal">
                        {{ item.data.message }}
                    </p>
                    <div class="flex items-center gap-1 text-[11px] text-muted-foreground pt-1">
                        <Clock class="size-3" />
                        <span>{{ formatDateTime(item.created_at) }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
