<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import EmptyState from '@/components/EmptyState.vue';
import PageHeader from '@/components/PageHeader.vue';
import StatusBadge from '@/components/StatusBadge.vue';
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

function resetFilter(): void {
    search.value = '';
    selectedStatus.value = '';
    applyFilter();
}
</script>

<template>
    <Head title="Kelola Data Booking" />

    <div class="flex flex-col gap-6 p-4 sm:p-6">
        <PageHeader
            title="Data Booking"
            :description="`${bookings.total} transaksi pemesanan tercatat di dalam sistem.`"
        />

        <!-- Filter bar -->
        <div class="flex flex-col gap-3 rounded-[6px] border border-border bg-card p-4 sm:flex-row sm:items-center">
            <div class="flex-1">
                <Input
                    v-model="search"
                    placeholder="Cari kode booking, nama penyewa, plat nomor..."
                    class="h-10 min-h-[40px]"
                    @keyup.enter="applyFilter"
                />
            </div>
            <div class="w-full sm:w-56">
                <select
                    v-model="selectedStatus"
                    class="h-10 min-h-[40px] w-full rounded-[6px] border border-input bg-background px-3 text-sm shadow-none outline-none focus-visible:ring-[2px] focus-visible:ring-ring"
                    @change="applyFilter"
                >
                    <option value="">Semua Status</option>
                    <option value="pending_dp">Menunggu DP</option>
                    <option value="booked">Dipesan (Terkonfirmasi)</option>
                    <option value="assigned">Ditugaskan</option>
                    <option value="delivering">Sedang Dikirim</option>
                    <option value="on_rent">Sedang Disewa</option>
                    <option value="returned">Unit Kembali</option>
                    <option value="completed">Selesai</option>
                    <option value="cancelled">Dibatalkan</option>
                </select>
            </div>
            <div class="flex gap-2">
                <Button size="sm" class="h-10 min-h-[40px] px-4" @click="applyFilter">
                    Terapkan
                </Button>
                <Button variant="outline" size="sm" class="h-10 min-h-[40px] px-4" @click="resetFilter">
                    Reset
                </Button>
            </div>
        </div>

        <!-- Tabel Booking -->
        <div class="overflow-x-auto rounded-[6px] border border-border bg-card">
            <table class="w-full text-left text-xs">
                <thead class="border-b border-border bg-muted/40 text-muted-foreground font-semibold">
                    <tr>
                        <th class="p-3">Kode Booking</th>
                        <th class="p-3">Penyewa</th>
                        <th class="p-3">Kendaraan</th>
                        <th class="p-3">Jadwal Sewa</th>
                        <th class="p-3">Total Biaya</th>
                        <th class="p-3">Status</th>
                        <th class="p-3">Pengiriman</th>
                        <th class="p-3 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    <tr v-if="bookings.data.length === 0">
                        <td colspan="8" class="p-6 text-center">
                            <EmptyState
                                title="Tidak ada data booking"
                                description="Tidak ada transaksi yang cocok dengan filter pencarian saat ini."
                            />
                        </td>
                    </tr>
                    <tr v-for="b in bookings.data" :key="b.id" class="hover:bg-muted/30 transition-colors">
                        <td class="p-3 font-mono font-medium whitespace-nowrap">
                            <Link :href="`/admin/bookings/${b.id}`" class="text-primary hover:underline font-semibold">
                                {{ b.booking_code }}
                            </Link>
                        </td>
                        <td class="p-3">
                            <div class="font-medium text-foreground">{{ b.user?.name }}</div>
                            <div class="text-[11px] text-muted-foreground">{{ b.user?.phone ?? b.user?.email }}</div>
                        </td>
                        <td class="p-3">
                            <div class="font-medium text-foreground">{{ b.vehicle?.brand }} {{ b.vehicle?.model }}</div>
                            <div class="text-[11px] text-muted-foreground font-mono">{{ b.vehicle?.plate }}</div>
                        </td>
                        <td class="p-3 text-muted-foreground whitespace-nowrap">
                            <div>{{ formatDateTime(b.pickup_at) }}</div>
                            <div class="text-[11px]">s/d {{ formatDateTime(b.return_at) }} ({{ b.duration_days }} hr)</div>
                        </td>
                        <td class="p-3 font-medium whitespace-nowrap">
                            <div>{{ rupiah(b.total_price) }}</div>
                            <div class="text-[11px] text-muted-foreground">DP: {{ rupiah(b.dp_amount) }}</div>
                        </td>
                        <td class="p-3 whitespace-nowrap">
                            <StatusBadge :status="b.status" />
                        </td>
                        <td class="p-3 text-muted-foreground whitespace-nowrap">
                            <div v-if="b.delivery">
                                <div class="font-medium text-foreground">{{ b.delivery.employee?.name ?? 'Pegawai' }}</div>
                                <div class="text-[11px]"><StatusBadge :status="b.delivery.status" class="scale-90 origin-left" /></div>
                            </div>
                            <span v-else class="text-stone-400 italic">Belum ditugaskan</span>
                        </td>
                        <td class="p-3 text-right whitespace-nowrap">
                            <Button as-child variant="outline" size="sm" class="h-8 text-xs">
                                <Link :href="`/admin/bookings/${b.id}`">Detail</Link>
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
