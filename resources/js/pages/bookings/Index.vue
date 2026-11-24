<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { formatDateTime, rupiah } from '@/lib/format';

type Status =
    | 'pending_dp'
    | 'booked'
    | 'assigned'
    | 'delivering'
    | 'on_rent'
    | 'returned'
    | 'completed'
    | 'cancelled';

interface Booking {
    id: number;
    booking_code: string;
    destination: string;
    pickup_at: string;
    return_at: string;
    duration_days: number;
    total_price: string;
    dp_amount: string;
    status: Status;
    vehicle: { brand: string; model: string; plate: string } | null;
}

interface PageLink {
    url: string | null;
    label: string;
    active: boolean;
}

defineProps<{
    bookings: { data: Booking[]; links: PageLink[]; total: number };
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Booking Saya', href: '/bookings' }],
    },
});

const statusLabel: Record<Status, string> = {
    pending_dp: 'Menunggu DP',
    booked: 'Terkonfirmasi',
    assigned: 'Siap dikirim',
    delivering: 'Dalam pengiriman',
    on_rent: 'Sedang disewa',
    returned: 'Dikembalikan',
    completed: 'Selesai',
    cancelled: 'Dibatalkan',
};

const statusVariant: Record<Status, 'default' | 'secondary' | 'destructive' | 'outline'> = {
    pending_dp: 'secondary',
    booked: 'default',
    assigned: 'default',
    delivering: 'default',
    on_rent: 'default',
    returned: 'outline',
    completed: 'outline',
    cancelled: 'destructive',
};
</script>

<template>
    <Head title="Booking Saya" />

    <div class="flex flex-col gap-4 p-4">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-xl font-semibold">Booking Saya</h1>
                <p class="text-sm text-muted-foreground">{{ bookings.total }} pesanan</p>
            </div>
            <Button as-child>
                <Link href="/vehicles">Pesan kendaraan</Link>
            </Button>
        </div>

        <div class="overflow-x-auto rounded-xl border">
            <table class="w-full text-sm">
                <thead class="bg-muted/50 text-left">
                    <tr>
                        <th class="p-3">Kode</th>
                        <th class="p-3">Kendaraan</th>
                        <th class="p-3">Jadwal</th>
                        <th class="p-3">Total</th>
                        <th class="p-3">DP</th>
                        <th class="p-3">Status</th>
                        <th class="p-3 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="bookings.data.length === 0">
                        <td colspan="7" class="p-6 text-center text-muted-foreground">
                            Belum ada pesanan. Klik "Pesan kendaraan" untuk mulai.
                        </td>
                    </tr>
                    <tr v-for="booking in bookings.data" :key="booking.id" class="border-t align-top">
                        <td class="p-3 font-mono">{{ booking.booking_code }}</td>
                        <td class="p-3">
                            <div class="font-medium">
                                {{ booking.vehicle?.brand }} {{ booking.vehicle?.model }}
                            </div>
                            <div class="text-xs text-muted-foreground">
                                {{ booking.vehicle?.plate }} · tujuan {{ booking.destination }}
                            </div>
                        </td>
                        <td class="p-3">
                            <div>{{ formatDateTime(booking.pickup_at) }}</div>
                            <div class="text-xs text-muted-foreground">
                                sampai {{ formatDateTime(booking.return_at) }} ({{ booking.duration_days }} hari)
                            </div>
                        </td>
                        <td class="p-3">{{ rupiah(booking.total_price) }}</td>
                        <td class="p-3">{{ rupiah(booking.dp_amount) }}</td>
                        <td class="p-3">
                            <Badge :variant="statusVariant[booking.status]">
                                {{ statusLabel[booking.status] }}
                            </Badge>
                        </td>
                        <td class="p-3 text-right">
                            <Button as-child size="sm">
                                <Link :href="`/bookings/${booking.id}`">Detail & Bayar</Link>
                            </Button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <nav v-if="bookings.links.length > 3" class="flex flex-wrap gap-1">
            <template v-for="(link, index) in bookings.links" :key="index">
                <Link
                    v-if="link.url"
                    :href="link.url"
                    class="rounded-md border px-3 py-1 text-sm"
                    :class="link.active ? 'bg-primary text-primary-foreground' : 'hover:bg-muted'"
                    preserve-scroll
                    v-html="link.label"
                />
                <span
                    v-else
                    class="rounded-md border px-3 py-1 text-sm text-muted-foreground opacity-50"
                    v-html="link.label"
                />
            </template>
        </nav>
    </div>
</template>
