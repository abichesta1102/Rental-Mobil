<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
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
    user: { id: number; name: string; email: string; phone?: string } | null;
    vehicle: { id: number; brand: string; model: string; plate: string } | null;
    delivery?: { status: string; employee?: { name: string } } | null;
    payments?: any[];
}

interface PageLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface Props {
    bookings: {
        data: Booking[];
        links: PageLink[];
        total: number;
    };
    filters: {
        status?: string;
        search?: string;
    };
}

const props = defineProps<Props>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Data Booking', href: '/admin/bookings' }],
    },
});

const search = ref(props.filters.search ?? '');
const selectedStatus = ref(props.filters.status ?? '');

function applyFilter(): void {
    router.get(
        '/admin/bookings',
        {
            search: search.value || undefined,
            status: selectedStatus.value || undefined,
        },
        { preserveState: true, replace: true }
    );
}

const statusLabel: Record<string, string> = {
    pending_dp: 'Menunggu DP',
    booked: 'Terkonfirmasi',
    assigned: 'Siap Kirim',
    delivering: 'Sedang Dikirim',
    on_rent: 'Sedang Disewa',
    returned: 'Kembali (Cek Denda)',
    completed: 'Selesai',
    cancelled: 'Dibatalkan',
};

const statusVariant: Record<string, 'default' | 'secondary' | 'destructive' | 'outline'> = {
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
    <Head title="Kelola Booking" />

    <div class="flex flex-col gap-5 p-4">
        <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-center">
            <div>
                <h1 class="text-xl font-semibold">Kelola Data Booking</h1>
                <p class="text-sm text-muted-foreground">{{ bookings.total }} pesanan terdaftar di sistem</p>
            </div>
        </div>

        <!-- Filter bar -->
        <div class="flex flex-col gap-3 rounded-xl border bg-card p-4 sm:flex-row sm:items-center">
            <div class="flex-1">
                <Input
                    v-model="search"
                    placeholder="Cari kode booking, nama penyewa, plat..."
                    @keyup.enter="applyFilter"
                />
            </div>
            <div class="w-full sm:w-48">
                <select
                    v-model="selectedStatus"
                    class="h-9 w-full rounded-md border border-input bg-background px-3 text-sm shadow-xs outline-none focus-visible:ring-1 focus-visible:ring-ring"
                    @change="applyFilter"
                >
                    <option value="">Semua Status</option>
                    <option value="pending_dp">Menunggu DP</option>
                    <option value="booked">Terkonfirmasi</option>
                    <option value="assigned">Siap Kirim</option>
                    <option value="delivering">Sedang Dikirim</option>
                    <option value="on_rent">Sedang Disewa</option>
                    <option value="returned">Kembali</option>
                    <option value="completed">Selesai</option>
                    <option value="cancelled">Dibatalkan</option>
                </select>
            </div>
            <Button variant="secondary" @click="applyFilter">Cari</Button>
        </div>

        <!-- Tabel Booking -->
        <div class="overflow-x-auto rounded-xl border bg-card">
            <table class="w-full text-sm">
                <thead class="bg-muted/50 text-left">
                    <tr>
                        <th class="p-3">Kode Booking</th>
                        <th class="p-3">Pelanggan</th>
                        <th class="p-3">Kendaraan</th>
                        <th class="p-3">Jadwal Sewa</th>
                        <th class="p-3">Total Tagihan</th>
                        <th class="p-3">Status</th>
                        <th class="p-3">Pengiriman</th>
                        <th class="p-3 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="bookings.data.length === 0">
                        <td colspan="8" class="p-8 text-center text-muted-foreground">
                            Tidak ditemukan data booking yang sesuai filter.
                        </td>
                    </tr>
                    <tr v-for="b in bookings.data" :key="b.id" class="border-t align-top">
                        <td class="p-3 font-mono font-medium">{{ b.booking_code }}</td>
                        <td class="p-3">
                            <div class="font-medium">{{ b.user?.name }}</div>
                            <div class="text-xs text-muted-foreground">{{ b.user?.phone ?? b.user?.email }}</div>
                        </td>
                        <td class="p-3">
                            <div class="font-medium">{{ b.vehicle?.brand }} {{ b.vehicle?.model }}</div>
                            <div class="text-xs font-mono text-muted-foreground">{{ b.vehicle?.plate }}</div>
                        </td>
                        <td class="p-3">
                            <div class="text-xs">{{ formatDateTime(b.pickup_at) }}</div>
                            <div class="text-xs text-muted-foreground">s/d {{ formatDateTime(b.return_at) }}</div>
                            <div class="text-xs font-medium text-slate-500">({{ b.duration_days }} hari)</div>
                        </td>
                        <td class="p-3">
                            <div class="font-medium">{{ rupiah(b.total_price) }}</div>
                            <div class="text-xs text-muted-foreground">DP: {{ rupiah(b.dp_amount) }}</div>
                        </td>
                        <td class="p-3">
                            <Badge :variant="statusVariant[b.status] ?? 'outline'">
                                {{ statusLabel[b.status] ?? b.status }}
                            </Badge>
                        </td>
                        <td class="p-3">
                            <div v-if="b.delivery" class="text-xs">
                                <span class="font-semibold">{{ b.delivery.employee?.name ?? 'Pegawai' }}</span>
                                <div class="text-muted-foreground">Status: {{ b.delivery.status }}</div>
                            </div>
                            <span v-else class="text-xs text-muted-foreground">Belum ditugaskan</span>
                        </td>
                        <td class="p-3 text-right">
                            <Button as-child size="sm">
                                <Link :href="`/admin/bookings/${b.id}`">Kelola</Link>
                            </Button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
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
            </template>
        </nav>
    </div>
</template>
