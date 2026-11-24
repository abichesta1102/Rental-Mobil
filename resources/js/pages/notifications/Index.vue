<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Bell, CheckCheck, Clock, ExternalLink } from '@lucide/vue';
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

function markAllAsRead() {
    router.post('/notifications/read-all');
}

function markAsRead(id: string, url?: string) {
    if (url) {
        router.post(`/notifications/${id}/read`, { redirect: true });
    } else {
        router.post(`/notifications/${id}/read`);
    }
}
</script>

<template>
    <Head title="Daftar Notifikasi" />

    <div class="space-y-6 p-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold tracking-tight">Notifikasi</h1>
                <p class="text-sm text-muted-foreground">
                    Semua pemberitahuan dan aktivitas terkait akun Anda.
                </p>
            </div>
            <div>
                <Button variant="outline" size="sm" @click="markAllAsRead">
                    <CheckCheck class="mr-2 h-4 w-4" />
                    Tandai Semua Dibaca
                </Button>
            </div>
        </div>

        <div v-if="notifications.data.length === 0" class="flex flex-col items-center justify-center rounded-lg border border-dashed py-12 text-center">
            <Bell class="h-10 w-10 text-muted-foreground/60" />
            <h3 class="mt-4 text-base font-semibold">Belum Ada Notifikasi</h3>
            <p class="mt-1 text-sm text-muted-foreground">Anda belum menerima notifikasi apa pun saat ini.</p>
        </div>

        <div v-else class="space-y-3">
            <div
                v-for="item in notifications.data"
                :key="item.id"
                class="flex items-start justify-between gap-4 rounded-lg border p-4 transition-colors"
                :class="item.read_at ? 'bg-background' : 'bg-muted/40 border-primary/30'"
            >
                <div class="space-y-1">
                    <div class="flex items-center gap-2">
                        <span class="font-semibold text-foreground">
                            {{ item.data.title || 'Pemberitahuan' }}
                        </span>
                        <Badge v-if="!item.read_at" variant="default" class="text-xs">
                            Baru
                        </Badge>
                    </div>
                    <p class="text-sm text-muted-foreground">
                        {{ item.data.message }}
                    </p>
                    <div class="flex items-center gap-1 text-xs text-muted-foreground/80">
                        <Clock class="h-3.5 w-3.5" />
                        <span>{{ formatDateTime(item.created_at) }}</span>
                    </div>
                </div>

                <div class="flex items-center gap-2 shrink-0">
                    <Button
                        v-if="!item.read_at"
                        variant="ghost"
                        size="sm"
                        @click="markAsRead(item.id)"
                    >
                        Tandai Dibaca
                    </Button>
                    <Button
                        v-if="item.data.url"
                        variant="outline"
                        size="sm"
                        @click="markAsRead(item.id, item.data.url)"
                    >
                        <ExternalLink class="mr-1 h-3.5 w-3.5" />
                        Buka
                    </Button>
                </div>
            </div>
        </div>

        <div v-if="notifications.last_page > 1" class="flex items-center justify-between pt-4">
            <Button
                variant="outline"
                size="sm"
                :disabled="!notifications.prev_page_url"
                as-child
            >
                <Link v-if="notifications.prev_page_url" :href="notifications.prev_page_url">
                    Sebelumnya
                </Link>
                <span v-else>Sebelumnya</span>
            </Button>
            <span class="text-xs text-muted-foreground">
                Halaman {{ notifications.current_page }} dari {{ notifications.last_page }}
            </span>
            <Button
                variant="outline"
                size="sm"
                :disabled="!notifications.next_page_url"
                as-child
            >
                <Link v-if="notifications.next_page_url" :href="notifications.next_page_url">
                    Berikutnya
                </Link>
                <span v-else>Berikutnya</span>
            </Button>
        </div>
    </div>
</template>
