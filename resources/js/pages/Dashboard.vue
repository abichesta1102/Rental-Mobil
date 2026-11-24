<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import {
    AlertCircle,
    ArrowRight,
    Car,
    CheckCircle2,
    Clock,
    CreditCard,
    DollarSign,
    ShieldAlert,
    Truck,
    UserCheck,
    Users,
} from '@lucide/vue';
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';
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

const pendingDpBooking = computed(() => {
    return props.active_bookings?.find((b: any) => b.status === 'pending_dp');
});
</script>

<template>
    <Head title="Dashboard" />

    <div class="flex flex-col gap-6 p-4">
        <!-- Header Sambutan -->
        <div class="flex flex-col justify-between gap-3 rounded-2xl bg-gradient-to-r from-slate-900 via-slate-800 to-indigo-950 p-6 text-white shadow-sm sm:flex-row sm:items-center">
            <div>
                <span class="rounded-full bg-white/10 px-3 py-1 text-xs font-medium uppercase tracking-wider text-slate-200">
                    Peran: {{ role?.toUpperCase() }}
                </span>
                <h1 class="mt-2 text-2xl font-bold">Halo, {{ auth?.name }}!</h1>
                <p class="mt-1 text-sm text-slate-300">
                    Selamat datang di Sistem Manajemen Rental Mobil.
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                <Button v-if="role === 'admin'" as-child variant="secondary" size="sm">
                    <Link href="/admin/bookings">Lihat Semua Booking</Link>
                </Button>
                <Button v-else-if="role === 'pegawai'" as-child variant="secondary" size="sm">
                    <Link href="/pegawai/deliveries">Lihat Tugas Saya</Link>
                </Button>
                <Button v-else as-child variant="secondary" size="sm">
                    <Link href="/vehicles">Sewa Kendaraan Sekarang</Link>
                </Button>
            </div>
        </div>

        <!-- Peringatan Masa Sewa Berakhir (Admin & Pegawai) -->
        <div v-if="(props.overdue_rentals?.length ?? 0) > 0" class="flex flex-col gap-2 rounded-xl border border-destructive/40 bg-destructive/10 p-4 text-destructive">
            <div class="flex items-center gap-2 font-semibold text-sm">
                <AlertCircle class="h-5 w-5 shrink-0" />
                <span>Pemberitahuan: Terdapat {{ props.overdue_rentals?.length }} unit sewa yang telah melewati batas waktu (harus segera dijemput)!</span>
            </div>
            <div class="flex flex-wrap gap-2 pt-1">
                <Link
                    v-for="b in props.overdue_rentals"
                    :key="b.id"
                    :href="role === 'admin' ? `/admin/bookings/${b.id}` : `/pegawai/deliveries/${b.delivery?.id ?? ''}`"
                    class="inline-flex items-center gap-1.5 rounded-md border border-destructive/30 bg-background px-3 py-1 text-xs font-semibold text-foreground hover:bg-muted"
                >
                    <span>{{ b.booking_code }} · {{ b.vehicle?.brand }} {{ b.vehicle?.model }} ({{ b.user?.name }})</span>
                    <span class="text-destructive font-mono">Batas: {{ formatDateTime(b.return_at) }} ↗</span>
                </Link>
            </div>
        </div>

        <!-- ======================= TAMPILAN ADMIN ======================= -->
        <div v-if="role === 'admin'" class="flex flex-col gap-6">
            <!-- Statistik Utama -->
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div class="flex items-center gap-4 rounded-xl border bg-card p-4 shadow-xs">
                    <div class="rounded-xl bg-blue-100 p-3 text-blue-700 dark:bg-blue-900/40 dark:text-blue-400">
                        <Car class="h-6 w-6" />
                    </div>
                    <div>
                        <div class="text-[11px] font-medium text-muted-foreground uppercase tracking-wider">Total Kendaraan</div>
                        <div class="text-2xl font-bold">{{ props.stats?.total_vehicles ?? 0 }}</div>
                        <div class="text-xs text-muted-foreground">
                            {{ props.stats?.available_vehicles ?? 0 }} Di Garasi · {{ props.stats?.rented_vehicles ?? 0 }} Keluar
                        </div>
                    </div>
                </div>

                <div class="flex items-center gap-4 rounded-xl border bg-card p-4 shadow-xs">
                    <div class="rounded-xl bg-amber-100 p-3 text-amber-700 dark:bg-amber-900/40 dark:text-amber-400">
                        <Clock class="h-6 w-6" />
                    </div>
                    <div>
                        <div class="text-[11px] font-medium text-muted-foreground uppercase tracking-wider">Total Booking</div>
                        <div class="text-2xl font-bold">{{ props.stats?.total_bookings ?? 0 }}</div>
                        <div class="text-xs text-muted-foreground">
                            {{ props.stats?.pending_payments ?? 0 }} Menunggu Verifikasi
                        </div>
                    </div>
                </div>

                <div class="flex items-center gap-4 rounded-xl border bg-card p-4 shadow-xs">
                    <div class="rounded-xl bg-emerald-100 p-3 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-400">
                        <Truck class="h-6 w-6" />
                    </div>
                    <div>
                        <div class="text-[11px] font-medium text-muted-foreground uppercase tracking-wider">Sewa Aktif</div>
                        <div class="text-2xl font-bold">{{ props.stats?.active_rentals ?? 0 }}</div>
                        <div class="text-xs text-muted-foreground">Sedang Disewa (On Rent)</div>
                    </div>
                </div>

                <div class="flex items-center gap-4 rounded-xl border bg-card p-4 shadow-xs">
                    <div class="rounded-xl bg-purple-100 p-3 text-purple-700 dark:bg-purple-900/40 dark:text-purple-400">
                        <Users class="h-6 w-6" />
                    </div>
                    <div>
                        <div class="text-[11px] font-medium text-muted-foreground uppercase tracking-wider">Pelanggan</div>
                        <div class="text-2xl font-bold">{{ props.stats?.total_customers ?? 0 }}</div>
                        <div class="text-xs text-muted-foreground">
                            {{ props.stats?.total_employees ?? 0 }} Pegawai · {{ props.stats?.blacklisted_customers ?? 0 }} Blacklist
                        </div>
                    </div>
                </div>
            </div>

            <!-- Booking Terkini -->
            <div class="flex flex-col gap-3 rounded-xl border bg-card p-5">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-lg font-semibold">Pesanan Booking Terbaru</h2>
                        <p class="text-xs text-muted-foreground">Daftar transaksi sewa yang baru masuk</p>
                    </div>
                    <Button as-child variant="outline" size="sm">
                        <Link href="/admin/bookings">Kelola Semua</Link>
                    </Button>
                </div>

                <div class="overflow-x-auto rounded-lg border">
                    <table class="w-full text-sm">
                        <thead class="bg-muted/50 text-left">
                            <tr>
                                <th class="p-3">Kode Booking</th>
                                <th class="p-3">Pelanggan</th>
                                <th class="p-3">Kendaraan</th>
                                <th class="p-3">Durasi</th>
                                <th class="p-3">Total Harga</th>
                                <th class="p-3">Status</th>
                                <th class="p-3 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="!props.recent_bookings || props.recent_bookings.length === 0">
                                <td colspan="7" class="p-6 text-center text-muted-foreground">
                                    Belum ada transaksi booking.
                                </td>
                            </tr>
                            <tr v-for="b in props.recent_bookings" :key="b.id" class="border-t">
                                <td class="p-3 font-mono font-medium">{{ b.booking_code }}</td>
                                <td class="p-3">
                                    <div class="font-medium">{{ b.user?.name }}</div>
                                    <div class="text-xs text-muted-foreground">{{ b.user?.phone ?? b.user?.email }}</div>
                                </td>
                                <td class="p-3">
                                    {{ b.vehicle?.brand }} {{ b.vehicle?.model }}
                                    <span class="block text-xs text-muted-foreground font-mono">{{ b.vehicle?.plate }}</span>
                                </td>
                                <td class="p-3">
                                    <div>{{ b.duration_days }} Hari</div>
                                    <div class="text-xs text-muted-foreground">{{ formatDateTime(b.pickup_at) }}</div>
                                </td>
                                <td class="p-3 font-medium">{{ rupiah(b.total_price) }}</td>
                                <td class="p-3">
                                    <Badge :variant="statusVariant[b.status] ?? 'outline'">
                                        {{ statusLabel[b.status] ?? b.status }}
                                    </Badge>
                                </td>
                                <td class="p-3 text-right">
                                    <Button as-child variant="ghost" size="sm">
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
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="flex items-center gap-4 rounded-xl border bg-card p-5">
                    <div class="rounded-xl bg-blue-100 p-3 text-blue-700 dark:bg-blue-900/40">
                        <Truck class="h-6 w-6" />
                    </div>
                    <div>
                        <div class="text-2xl font-bold">{{ props.stats?.active_tasks ?? 0 }}</div>
                        <div class="text-sm text-muted-foreground">Tugas Pengiriman / Penjemputan Aktif</div>
                    </div>
                </div>

                <div class="flex items-center gap-4 rounded-xl border bg-card p-5">
                    <div class="rounded-xl bg-emerald-100 p-3 text-emerald-700 dark:bg-emerald-900/40">
                        <CheckCircle2 class="h-6 w-6" />
                    </div>
                    <div>
                        <div class="text-2xl font-bold">{{ props.stats?.completed_tasks ?? 0 }}</div>
                        <div class="text-sm text-muted-foreground">Tugas Selesai (Kembali ke Garasi)</div>
                    </div>
                </div>
            </div>

            <div class="flex flex-col gap-3 rounded-xl border bg-card p-5">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-lg font-semibold">Tugas Pengiriman Anda</h2>
                        <p class="text-xs text-muted-foreground">Segera konfirmasi dan lakukan pengantaran unit ke pelanggan</p>
                    </div>
                    <Button as-child variant="outline" size="sm">
                        <Link href="/pegawai/deliveries">Lihat Semua Tugas</Link>
                    </Button>
                </div>

                <div v-if="!props.assigned_deliveries || props.assigned_deliveries.length === 0" class="rounded-lg border p-8 text-center text-muted-foreground">
                    Tidak ada tugas pengiriman aktif saat ini.
                </div>

                <div v-else class="grid gap-3 sm:grid-cols-2">
                    <div
                        v-for="del in props.assigned_deliveries"
                        :key="del.id"
                        class="flex flex-col justify-between rounded-xl border p-4 shadow-xs"
                    >
                        <div class="flex flex-col gap-2">
                            <div class="flex items-center justify-between">
                                <span class="font-mono text-xs font-semibold">{{ del.booking?.booking_code }}</span>
                                <Badge variant="outline">{{ del.status }}</Badge>
                            </div>
                            <div class="font-semibold">{{ del.booking?.vehicle?.brand }} {{ del.booking?.vehicle?.model }} ({{ del.booking?.vehicle?.plate }})</div>
                            <div class="text-xs text-muted-foreground">
                                <strong>Penyewa:</strong> {{ del.booking?.user?.name }} ({{ del.booking?.user?.phone }})
                            </div>
                            <div class="text-xs text-muted-foreground">
                                <strong>Alamat:</strong> {{ del.booking?.delivery_address }}
                            </div>
                        </div>
                        <div class="mt-4 flex justify-end">
                            <Button as-child size="sm">
                                <Link :href="`/pegawai/deliveries/${del.id}`">Proses Pengiriman</Link>
                            </Button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ======================= TAMPILAN PELANGGAN ======================= -->
        <div v-else class="flex flex-col gap-6">
            <!-- Banner Perhatian Booking Menunggu DP -->
            <div
                v-if="pendingDpBooking"
                class="flex flex-col justify-between gap-3 rounded-xl border border-amber-500/40 bg-amber-500/10 p-4 text-amber-900 dark:text-amber-200 sm:flex-row sm:items-center"
            >
                <div class="flex items-center gap-3">
                    <AlertCircle class="h-6 w-6 shrink-0 text-amber-600 dark:text-amber-400" />
                    <div>
                        <div class="font-bold text-sm">Menunggu Pembayaran DP (Uang Muka)</div>
                        <div class="text-xs text-amber-800 dark:text-amber-300">
                            Booking <strong>{{ pendingDpBooking.booking_code }}</strong> ({{ pendingDpBooking.vehicle?.brand }} {{ pendingDpBooking.vehicle?.model }}) ditahan selama 24 jam. Segera selesaikan pembayaran agar unit terkunci.
                        </div>
                    </div>
                </div>
                <Button as-child size="sm" class="bg-amber-600 hover:bg-amber-700 text-white shrink-0">
                    <Link :href="`/bookings/${pendingDpBooking.id}`">Bayar DP Sekarang</Link>
                </Button>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div class="flex items-center gap-4 rounded-xl border bg-card p-5">
                    <div class="rounded-xl bg-indigo-100 p-3 text-indigo-700 dark:bg-indigo-900/40">
                        <Car class="h-6 w-6" />
                    </div>
                    <div>
                        <div class="text-2xl font-bold">{{ props.stats?.active_count ?? 0 }}</div>
                        <div class="text-sm text-muted-foreground">Booking Sedang Berjalan</div>
                    </div>
                </div>

                <div class="flex items-center gap-4 rounded-xl border bg-card p-5">
                    <div class="rounded-xl bg-emerald-100 p-3 text-emerald-700 dark:bg-emerald-900/40">
                        <CheckCircle2 class="h-6 w-6" />
                    </div>
                    <div>
                        <div class="text-2xl font-bold">{{ props.stats?.completed_count ?? 0 }}</div>
                        <div class="text-sm text-muted-foreground">Sewa Berhasil Diselesaikan</div>
                    </div>
                </div>
            </div>

            <!-- Booking Berjalan -->
            <div class="flex flex-col gap-3 rounded-xl border bg-card p-5">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-lg font-semibold">Pesanan Aktif Anda</h2>
                        <p class="text-xs text-muted-foreground">Pantau status konfirmasi DP, jadwal kirim, dan masa sewa</p>
                    </div>
                    <Button as-child variant="outline" size="sm">
                        <Link href="/bookings">Semua Pesanan</Link>
                    </Button>
                </div>

                <div v-if="!props.active_bookings || props.active_bookings.length === 0" class="rounded-lg border p-8 text-center text-muted-foreground">
                    Belum ada pesanan aktif. Silakan pilih mobil impian Anda di katalog!
                    <div class="mt-3">
                        <Button as-child>
                            <Link href="/vehicles">Buka Katalog Kendaraan</Link>
                        </Button>
                    </div>
                </div>

                <div v-else class="grid gap-4 sm:grid-cols-2">
                    <div
                        v-for="b in props.active_bookings"
                        :key="b.id"
                        class="flex flex-col justify-between rounded-xl border p-4 shadow-xs"
                    >
                        <div>
                            <div class="flex items-center justify-between">
                                <span class="font-mono text-xs text-muted-foreground">{{ b.booking_code }}</span>
                                <Badge :variant="statusVariant[b.status] ?? 'outline'">
                                    {{ statusLabel[b.status] ?? b.status }}
                                </Badge>
                            </div>
                            <h3 class="mt-2 text-base font-semibold">{{ b.vehicle?.brand }} {{ b.vehicle?.model }}</h3>
                            <p class="text-xs text-muted-foreground">Tujuan: {{ b.destination }}</p>
                            <p class="text-xs text-muted-foreground">
                                Jadwal: {{ formatDateTime(b.pickup_at) }} s/d {{ formatDateTime(b.return_at) }}
                            </p>
                            <p class="mt-2 text-sm font-semibold text-primary">Total: {{ rupiah(b.total_price) }}</p>
                        </div>

                        <div class="mt-4 flex items-center justify-between border-t pt-3">
                            <span v-if="b.status === 'pending_dp'" class="text-xs text-amber-600 font-medium">
                                Silakan upload bukti bayar DP
                            </span>
                            <span v-else-if="b.status === 'on_rent'" class="text-xs text-emerald-600 font-medium">
                                Unit sedang Anda sewa
                            </span>
                            <span v-else class="text-xs text-muted-foreground">
                                Status: {{ statusLabel[b.status] }}
                            </span>

                            <Button as-child size="sm">
                                <Link :href="`/bookings/${b.id}`">Detail & Bayar</Link>
                            </Button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
