<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import {
    AlertCircle,
    ArrowRight,
    Car,
    CheckCircle2,
    Clock,
    Truck,
    Users,
} from '@lucide/vue';
import { computed } from 'vue';
import EmptyState from '@/components/EmptyState.vue';
import PageHeader from '@/components/PageHeader.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { formatDateTime, rupiah } from '@/lib/format';

interface Props {
    stats?: {
        total_vehicles?: number;
        available_vehicles?: number;
        rented_vehicles?: number;
        maintenance_vehicles?: number;
        total_bookings?: number;
        pending_payments?: number;
        active_rentals?: number;
        total_customers?: number;
        blacklisted_customers?: number;
        total_employees?: number;
        active_tasks?: number;
        completed_tasks?: number;
        active_count?: number;
        completed_count?: number;
    };
    recent_bookings?: any[];
    assigned_deliveries?: any[];
    active_bookings?: any[];
    overdue_rentals?: any[];
}

const props = defineProps<Props>();

const page = usePage();
const auth = computed(() => (page.props.auth as any)?.user);
const role = computed(() => auth.value?.role);

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Dashboard', href: '/dashboard' }],
    },
});

const pendingDpBooking = computed(() => {
    return props.active_bookings?.find((b: any) => b.status === 'pending_dp');
});
</script>

<template>
    <Head title="Dashboard" />

    <div class="flex flex-col gap-6 p-4 sm:p-6">
        <!-- Page Header -->
        <PageHeader
            :title="`Dashboard ${role === 'admin' ? 'Admin' : role === 'pegawai' ? 'Pegawai' : 'Pelanggan'}`"
            :description="`Selamat datang, ${auth?.name}. Ringkasan operasional dan aktivitas rental mobil.`"
        >
            <template #actions>
                <Button v-if="role === 'admin'" as-child size="sm">
                    <Link href="/admin/bookings">Kelola Booking</Link>
                </Button>
                <Button v-else-if="role === 'pegawai'" as-child size="sm">
                    <Link href="/pegawai/deliveries">Tugas Pengiriman</Link>
                </Button>
                <Button v-else as-child size="sm">
                    <Link href="/vehicles">Katalog Kendaraan</Link>
                </Button>
            </template>
        </PageHeader>

        <!-- Peringatan Masa Sewa Berakhir (Admin & Pegawai) -->
        <div
            v-if="(props.overdue_rentals?.length ?? 0) > 0"
            class="flex flex-col gap-2 rounded-[6px] border border-destructive/40 bg-destructive/10 p-4 text-destructive"
        >
            <div class="flex items-center gap-2 font-semibold text-sm">
                <AlertCircle class="h-4 w-4 shrink-0" />
                <span>Pemberitahuan: Ada {{ props.overdue_rentals?.length }} sewa yang telah melewati batas waktu pengembalian.</span>
            </div>
            <div class="flex flex-wrap gap-2 pt-1">
                <Link
                    v-for="b in props.overdue_rentals"
                    :key="b.id"
                    :href="role === 'admin' ? `/admin/bookings/${b.id}` : `/pegawai/deliveries/${b.delivery?.id ?? ''}`"
                    class="inline-flex items-center gap-2 rounded-[6px] border border-destructive/30 bg-background px-3 py-1.5 text-xs font-medium text-foreground hover:bg-muted"
                >
                    <span>{{ b.booking_code }} &middot; {{ b.vehicle?.brand }} {{ b.vehicle?.model }} ({{ b.user?.name }})</span>
                    <span class="text-destructive font-mono text-[11px]">Batas: {{ formatDateTime(b.return_at) }}</span>
                </Link>
            </div>
        </div>

        <!-- ======================= TAMPILAN ADMIN ======================= -->
        <div v-if="role === 'admin'" class="flex flex-col gap-6">
            <!-- Statistik Operasional Admin -->
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <div class="rounded-[6px] border border-border bg-card p-4">
                    <div class="flex items-center justify-between text-muted-foreground mb-1">
                        <span class="text-xs font-semibold uppercase tracking-wider">Kendaraan</span>
                        <Car class="size-4 text-primary" />
                    </div>
                    <div class="text-2xl font-bold tracking-tight text-foreground">{{ props.stats?.total_vehicles ?? 0 }}</div>
                    <div class="mt-1 text-xs text-muted-foreground">
                        {{ props.stats?.available_vehicles ?? 0 }} di garasi &middot; {{ props.stats?.rented_vehicles ?? 0 }} keluar
                    </div>
                </div>

                <div class="rounded-[6px] border border-border bg-card p-4">
                    <div class="flex items-center justify-between text-muted-foreground mb-1">
                        <span class="text-xs font-semibold uppercase tracking-wider">Total Booking</span>
                        <Clock class="size-4 text-primary" />
                    </div>
                    <div class="text-2xl font-bold tracking-tight text-foreground">{{ props.stats?.total_bookings ?? 0 }}</div>
                    <div class="mt-1 text-xs text-muted-foreground">
                        {{ props.stats?.pending_payments ?? 0 }} menunggu verifikasi bayar
                    </div>
                </div>

                <div class="rounded-[6px] border border-border bg-card p-4">
                    <div class="flex items-center justify-between text-muted-foreground mb-1">
                        <span class="text-xs font-semibold uppercase tracking-wider">Sewa Aktif</span>
                        <Truck class="size-4 text-primary" />
                    </div>
                    <div class="text-2xl font-bold tracking-tight text-foreground">{{ props.stats?.active_rentals ?? 0 }}</div>
                    <div class="mt-1 text-xs text-muted-foreground">
                        Unit sedang digunakan pelanggan
                    </div>
                </div>

                <div class="rounded-[6px] border border-border bg-card p-4">
                    <div class="flex items-center justify-between text-muted-foreground mb-1">
                        <span class="text-xs font-semibold uppercase tracking-wider">Pengguna</span>
                        <Users class="size-4 text-primary" />
                    </div>
                    <div class="text-2xl font-bold tracking-tight text-foreground">{{ props.stats?.total_customers ?? 0 }}</div>
                    <div class="mt-1 text-xs text-muted-foreground">
                        {{ props.stats?.total_employees ?? 0 }} pegawai &middot; {{ props.stats?.blacklisted_customers ?? 0 }} blacklist
                    </div>
                </div>
            </div>

            <!-- Tabel Booking Terkini -->
            <div class="rounded-[6px] border border-border bg-card">
                <div class="flex items-center justify-between p-4 border-b border-border">
                    <div>
                        <h2 class="text-sm font-semibold tracking-tight text-foreground">Pesanan Booking Terbaru</h2>
                        <p class="text-xs text-muted-foreground">Daftar transaksi booking yang baru masuk</p>
                    </div>
                    <Button as-child variant="outline" size="sm">
                        <Link href="/admin/bookings">Lihat Semua</Link>
                    </Button>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="border-b border-border bg-muted/40 text-muted-foreground font-semibold">
                            <tr>
                                <th class="p-3">Kode</th>
                                <th class="p-3">Pelanggan</th>
                                <th class="p-3">Kendaraan</th>
                                <th class="p-3">Jadwal Ambil</th>
                                <th class="p-3">Total Biaya</th>
                                <th class="p-3">Status</th>
                                <th class="p-3 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            <tr v-if="!props.recent_bookings || props.recent_bookings.length === 0">
                                <td colspan="7" class="p-6 text-center">
                                    <EmptyState
                                        title="Belum ada data booking"
                                        description="Transaksi booking yang masuk akan ditampilkan pada tabel ini."
                                    />
                                </td>
                            </tr>
                            <tr v-for="b in props.recent_bookings" :key="b.id" class="hover:bg-muted/30 transition-colors">
                                <td class="p-3 font-mono font-medium">{{ b.booking_code }}</td>
                                <td class="p-3">
                                    <div class="font-medium text-foreground">{{ b.user?.name }}</div>
                                    <div class="text-[11px] text-muted-foreground">{{ b.user?.phone ?? b.user?.email }}</div>
                                </td>
                                <td class="p-3">
                                    <div class="font-medium text-foreground">{{ b.vehicle?.brand }} {{ b.vehicle?.model }}</div>
                                    <div class="text-[11px] text-muted-foreground font-mono">{{ b.vehicle?.plate }}</div>
                                </td>
                                <td class="p-3 text-muted-foreground whitespace-nowrap">
                                    {{ formatDateTime(b.pickup_at) }}
                                </td>
                                <td class="p-3 font-medium text-foreground whitespace-nowrap">
                                    {{ rupiah(b.total_price) }}
                                </td>
                                <td class="p-3 whitespace-nowrap">
                                    <StatusBadge :status="b.status" />
                                </td>
                                <td class="p-3 text-right whitespace-nowrap">
                                    <Button as-child variant="ghost" size="sm" class="h-8 text-xs">
                                        <Link :href="`/admin/bookings/${b.id}`">Detail</Link>
                                    </Button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- ======================= TAMPILAN PEGAWAI ======================= -->
        <div v-else-if="role === 'pegawai'" class="flex flex-col gap-6">
            <div class="grid gap-3 sm:grid-cols-2">
                <div class="rounded-[6px] border border-border bg-card p-4">
                    <div class="flex items-center justify-between text-muted-foreground mb-1">
                        <span class="text-xs font-semibold uppercase tracking-wider">Tugas Aktif</span>
                        <Truck class="size-4 text-primary" />
                    </div>
                    <div class="text-2xl font-bold tracking-tight text-foreground">{{ props.stats?.active_tasks ?? 0 }}</div>
                    <div class="mt-1 text-xs text-muted-foreground">Tugas pengiriman atau penjemputan berjalan</div>
                </div>

                <div class="rounded-[6px] border border-border bg-card p-4">
                    <div class="flex items-center justify-between text-muted-foreground mb-1">
                        <span class="text-xs font-semibold uppercase tracking-wider">Tugas Selesai</span>
                        <CheckCircle2 class="size-4 text-emerald-600" />
                    </div>
                    <div class="text-2xl font-bold tracking-tight text-foreground">{{ props.stats?.completed_tasks ?? 0 }}</div>
                    <div class="mt-1 text-xs text-muted-foreground">Unit telah kembali ke garasi</div>
                </div>
            </div>

            <div class="rounded-[6px] border border-border bg-card">
                <div class="flex items-center justify-between p-4 border-b border-border">
                    <div>
                        <h2 class="text-sm font-semibold tracking-tight text-foreground">Tugas Pengiriman Anda</h2>
                        <p class="text-xs text-muted-foreground">Daftar unit yang harus dikirim atau diambil</p>
                    </div>
                    <Button as-child variant="outline" size="sm">
                        <Link href="/pegawai/deliveries">Semua Tugas</Link>
                    </Button>
                </div>

                <div v-if="!props.assigned_deliveries || props.assigned_deliveries.length === 0" class="p-6">
                    <EmptyState
                        title="Tidak ada tugas aktif"
                        description="Saat ini Anda tidak memiliki tugas pengiriman yang menunggu tindakan."
                    />
                </div>

                <div v-else class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="border-b border-border bg-muted/40 text-muted-foreground font-semibold">
                            <tr>
                                <th class="p-3">Kode Booking</th>
                                <th class="p-3">Kendaraan</th>
                                <th class="p-3">Pelanggan</th>
                                <th class="p-3">Alamat Antar</th>
                                <th class="p-3">Status</th>
                                <th class="p-3 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            <tr v-for="del in props.assigned_deliveries" :key="del.id" class="hover:bg-muted/30 transition-colors">
                                <td class="p-3 font-mono font-medium">{{ del.booking?.booking_code }}</td>
                                <td class="p-3">
                                    <div class="font-medium text-foreground">{{ del.booking?.vehicle?.brand }} {{ del.booking?.vehicle?.model }}</div>
                                    <div class="text-[11px] text-muted-foreground font-mono">{{ del.booking?.vehicle?.plate }}</div>
                                </td>
                                <td class="p-3">
                                    <div class="font-medium text-foreground">{{ del.booking?.user?.name }}</div>
                                    <div class="text-[11px] text-muted-foreground">{{ del.booking?.user?.phone }}</div>
                                </td>
                                <td class="p-3 text-muted-foreground max-w-xs truncate">
                                    {{ del.booking?.delivery_address }}
                                </td>
                                <td class="p-3 whitespace-nowrap">
                                    <StatusBadge :status="del.status" />
                                </td>
                                <td class="p-3 text-right whitespace-nowrap">
                                    <Button as-child size="sm" class="h-8 text-xs">
                                        <Link :href="`/pegawai/deliveries/${del.id}`">Proses</Link>
                                    </Button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- ======================= TAMPILAN PELANGGAN ======================= -->
        <div v-else class="flex flex-col gap-6">
            <!-- Banner Perhatian DP -->
            <div
                v-if="pendingDpBooking"
                class="flex flex-col justify-between gap-3 rounded-[6px] border border-amber-600/30 bg-amber-50 dark:bg-amber-950/40 p-4 text-amber-900 dark:text-amber-200 sm:flex-row sm:items-center"
            >
                <div class="flex items-center gap-3">
                    <AlertCircle class="size-5 shrink-0 text-amber-600 dark:text-amber-400" />
                    <div>
                        <div class="font-semibold text-sm">Menunggu Pembayaran Uang Muka (DP)</div>
                        <div class="text-xs text-amber-800 dark:text-amber-300 mt-0.5">
                            Booking <strong>{{ pendingDpBooking.booking_code }}</strong> ({{ pendingDpBooking.vehicle?.brand }} {{ pendingDpBooking.vehicle?.model }}) ditahan 24 jam. Selesaikan pembayaran DP agar unit terkunci.
                        </div>
                    </div>
                </div>
                <Button as-child size="sm" class="shrink-0 bg-primary hover:bg-primary/90 text-primary-foreground">
                    <Link :href="`/bookings/${pendingDpBooking.id}`">Bayar DP Sekarang</Link>
                </Button>
            </div>

            <!-- Statistik Pelanggan -->
            <div class="grid gap-3 sm:grid-cols-2">
                <div class="rounded-[6px] border border-border bg-card p-4">
                    <div class="flex items-center justify-between text-muted-foreground mb-1">
                        <span class="text-xs font-semibold uppercase tracking-wider">Booking Berjalan</span>
                        <Car class="size-4 text-primary" />
                    </div>
                    <div class="text-2xl font-bold tracking-tight text-foreground">{{ props.stats?.active_count ?? 0 }}</div>
                    <div class="mt-1 text-xs text-muted-foreground">Pesanan yang sedang aktif</div>
                </div>

                <div class="rounded-[6px] border border-border bg-card p-4">
                    <div class="flex items-center justify-between text-muted-foreground mb-1">
                        <span class="text-xs font-semibold uppercase tracking-wider">Sewa Selesai</span>
                        <CheckCircle2 class="size-4 text-emerald-600" />
                    </div>
                    <div class="text-2xl font-bold tracking-tight text-foreground">{{ props.stats?.completed_count ?? 0 }}</div>
                    <div class="mt-1 text-xs text-muted-foreground">Riwayat rental yang berhasil diselesaikan</div>
                </div>
            </div>

            <!-- Pesanan Aktif Pelanggan -->
            <div class="rounded-[6px] border border-border bg-card">
                <div class="flex items-center justify-between p-4 border-b border-border">
                    <div>
                        <h2 class="text-sm font-semibold tracking-tight text-foreground">Pesanan Aktif Anda</h2>
                        <p class="text-xs text-muted-foreground">Status pemesanan, verifikasi, dan jadwal sewa kendaraan</p>
                    </div>
                    <Button as-child variant="outline" size="sm">
                        <Link href="/bookings">Semua Pesanan</Link>
                    </Button>
                </div>

                <div v-if="!props.active_bookings || props.active_bookings.length === 0" class="p-6">
                    <EmptyState
                        title="Belum ada pesanan aktif"
                        description="Pilih kendaraan yang tersedia di katalog untuk memulai booking rental mobil."
                    >
                        <Button as-child size="sm" class="mt-2">
                            <Link href="/vehicles">Buka Katalog Kendaraan</Link>
                        </Button>
                    </EmptyState>
                </div>

                <div v-else class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="border-b border-border bg-muted/40 text-muted-foreground font-semibold">
                            <tr>
                                <th class="p-3">Kode Booking</th>
                                <th class="p-3">Kendaraan</th>
                                <th class="p-3">Tujuan</th>
                                <th class="p-3">Jadwal Sewa</th>
                                <th class="p-3">Total Biaya</th>
                                <th class="p-3">Status</th>
                                <th class="p-3 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            <tr v-for="b in props.active_bookings" :key="b.id" class="hover:bg-muted/30 transition-colors">
                                <td class="p-3 font-mono font-medium">{{ b.booking_code }}</td>
                                <td class="p-3 font-medium text-foreground">
                                    {{ b.vehicle?.brand }} {{ b.vehicle?.model }}
                                </td>
                                <td class="p-3 text-muted-foreground">{{ b.destination }}</td>
                                <td class="p-3 text-muted-foreground whitespace-nowrap">
                                    {{ formatDateTime(b.pickup_at) }} s/d {{ formatDateTime(b.return_at) }}
                                </td>
                                <td class="p-3 font-medium text-foreground whitespace-nowrap">
                                    {{ rupiah(b.total_price) }}
                                </td>
                                <td class="p-3 whitespace-nowrap">
                                    <StatusBadge :status="b.status" />
                                </td>
                                <td class="p-3 text-right whitespace-nowrap">
                                    <Button as-child size="sm" class="h-8 text-xs">
                                        <Link :href="`/bookings/${b.id}`">Detail & Bayar</Link>
                                    </Button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</template>
