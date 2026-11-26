<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import EmptyState from '@/components/EmptyState.vue';
import PageHeader from '@/components/PageHeader.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { formatDateTime, rupiah } from '@/lib/format';

interface Booking {
    id: number;
    booking_code: string;
    destination: string;
    pickup_at: string;
    return_at: string;
    duration_days: number;
    total_price: string;
    dp_amount: string;
    status: string;
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
</script>

<template>
    <Head title="Booking Saya" />

    <div class="flex flex-col gap-6 p-4 sm:p-6 max-w-5xl mx-auto w-full">
        <PageHeader
            title="Booking Saya"
            :description="`${bookings.total} pesanan sewa kendaraan dalam riwayat akun Anda.`"
        >
            <template #actions>
                <Button as-child size="sm">
                    <Link href="/vehicles">+ Pesan Kendaraan</Link>
                </Button>
            </template>
        </PageHeader>

        <div class="overflow-x-auto rounded-[6px] border border-border bg-card">
            <table class="w-full text-left text-xs">
                <thead class="border-b border-border bg-muted/40 text-muted-foreground font-semibold">
                    <tr>
                        <th class="p-3">Kode Booking</th>
                        <th class="p-3">Kendaraan</th>
                        <th class="p-3">Jadwal Sewa</th>
                        <th class="p-3">Total Biaya</th>
                        <th class="p-3">DP (Uang Muka)</th>
                        <th class="p-3">Status</th>
                        <th class="p-3 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    <tr v-if="bookings.data.length === 0">
                        <td colspan="7" class="p-6 text-center">
                            <EmptyState
                                title="Belum ada pesanan rental"
                                description="Anda belum melakukan pemesanan kendaraan. Jelajahi katalog armada kami sekarang."
                            >
                                <Button as-child size="sm" class="mt-2">
                                    <Link href="/vehicles">Buka Katalog Kendaraan</Link>
                                </Button>
                            </EmptyState>
                        </td>
                    </tr>
                    <tr v-for="b in bookings.data" :key="b.id" class="hover:bg-muted/30 transition-colors">
                        <td class="p-3 font-mono font-medium whitespace-nowrap">
                            <Link :href="`/bookings/${b.id}`" class="text-primary hover:underline font-semibold">
                                {{ b.booking_code }}
                            </Link>
                        </td>
                        <td class="p-3">
                            <div class="font-medium text-foreground">{{ b.vehicle?.brand }} {{ b.vehicle?.model }}</div>
                            <div class="text-[11px] font-mono text-muted-foreground">{{ b.vehicle?.plate }}</div>
                        </td>
                        <td class="p-3 text-muted-foreground whitespace-nowrap">
                            <div>{{ formatDateTime(b.pickup_at) }}</div>
                            <div class="text-[11px]">s/d {{ formatDateTime(b.return_at) }} ({{ b.duration_days }} hr)</div>
                        </td>
                        <td class="p-3 font-medium whitespace-nowrap">
                            {{ rupiah(b.total_price) }}
                        </td>
                        <td class="p-3 text-muted-foreground whitespace-nowrap">
                            {{ rupiah(b.dp_amount) }}
                        </td>
                        <td class="p-3 whitespace-nowrap">
                            <StatusBadge :status="b.status" />
                        </td>
                        <td class="p-3 text-right whitespace-nowrap">
                            <Button as-child variant="outline" size="sm" class="h-8 text-xs">
                                <Link :href="`/bookings/${b.id}`">Detail & Bayar</Link>
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
                    class="rounded-[6px] border border-border px-3 py-1.5 text-xs font-medium min-h-[36px] flex items-center justify-center"
                    :class="link.active ? 'bg-primary text-primary-foreground border-primary' : 'bg-background hover:bg-muted text-foreground'"
                    preserve-scroll
                    v-html="link.label"
                />
                <span
                    v-else
                    class="rounded-[6px] border border-border px-3 py-1.5 text-xs text-muted-foreground opacity-40 min-h-[36px] flex items-center justify-center"
                    v-html="link.label"
                />
            </template>
        </nav>
    </div>
</template>
