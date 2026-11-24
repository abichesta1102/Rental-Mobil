<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { AlertCircle, Printer } from '@lucide/vue';
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatDateTime, rupiah } from '@/lib/format';

interface PaymentMethod {
    id: number;
    type: 'qris' | 'bank_transfer';
    label: string;
    account_number: string | null;
    account_name: string | null;
    qris_image_url: string | null;
}

interface Props {
    booking: any;
    paymentMethods: PaymentMethod[];
}

const props = defineProps<Props>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Booking Saya', href: '/bookings' },
            { title: 'Detail Booking', href: '#' },
        ],
    },
});

// Perhitungan kekurangan / pelunasan
const totalPaid = (props.booking.payments ?? [])
    .filter((p: any) => p.status === 'verified')
    .reduce((sum: number, p: any) => sum + Number(p.amount), 0);

const damagesTotal = (props.booking.damages ?? [])
    .reduce((sum: number, d: any) => sum + Number(d.fine_amount), 0);

// Rincian durasi pokok dan kelebihan jam awal
const pricePerDay = Number(props.booking.price_per_day);
const lateFeePerHour = Number(props.booking.vehicle?.late_fee_per_hour || 0);

const durationBreakdown = computed(() => {
    const pickup = new Date(props.booking.pickup_at).getTime();
    const ret = new Date(props.booking.return_at).getTime();
    if (isNaN(pickup) || isNaN(ret) || ret <= pickup) {
        return { days: props.booking.duration_days, extraHours: 0, basePrice: Number(props.booking.total_price), overtimeFee: 0 };
    }
    const totalMinutes = Math.ceil(Math.max(0, (ret - pickup) / 1000) / 60);
    const totalHours = totalMinutes / 60;
    if (totalHours <= 24) {
        return { days: 1, extraHours: 0, basePrice: pricePerDay, overtimeFee: 0 };
    }
    const days = Math.floor(totalHours / 24);
    const remainingMinutes = totalMinutes - (days * 24 * 60);
    const extraHours = Math.ceil(remainingMinutes / 60);
    if (extraHours >= 24) {
        return { days: days + 1, extraHours: 0, basePrice: (days + 1) * pricePerDay, overtimeFee: 0 };
    }
    return {
        days,
        extraHours,
        basePrice: days * pricePerDay,
        overtimeFee: extraHours * lateFeePerHour,
    };
});

const grandTotal = Number(props.booking.total_price) + Number(props.booking.late_fee_total) + damagesTotal;
const remainingBalance = Math.max(0, grandTotal - totalPaid);

const hasPendingPayment = computed(() => {
    return (props.booking.payments ?? []).some((p: any) => p.status === 'pending');
});

// Form Pembayaran (Type & Amount ditentukan oleh server)
const selectedMethod = ref<PaymentMethod | null>(props.paymentMethods[0] ?? null);

const paymentForm = useForm({
    payment_method_id: props.paymentMethods[0]?.id ?? '',
    proof: null as File | null,
});

function onProofChange(e: Event): void {
    const input = e.target as HTMLInputElement;
    paymentForm.proof = input.files?.[0] ?? null;
}

function submitPayment(): void {
    paymentForm.post(`/bookings/${props.booking.id}/pay`, {
        forceFormData: true,
        onSuccess: () => paymentForm.reset('proof'),
    });
}

// GPS Tracking Realtime (15 detik) saat status on_rent
const isTracking = ref(false);
const trackingError = ref<string | null>(null);
let trackingInterval: any = null;

function getXsrfToken(): string {
    const match = document.cookie.match(/(^|;\s*)XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[2]) : '';
}

function sendLocation(): void {
    if (!navigator.geolocation) {
        trackingError.value = 'Perangkat / browser Anda tidak mendukung Geolocation.';
        return;
    }

    navigator.geolocation.getCurrentPosition(
        async (position) => {
            try {
                const response = await fetch(`/bookings/${props.booking.id}/location`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-XSRF-TOKEN': getXsrfToken(),
                    },
                    body: JSON.stringify({
                        lat: position.coords.latitude,
                        lng: position.coords.longitude,
                    }),
                });

                if (!response.ok) {
                    const data = await response.json().catch(() => ({}));
                    trackingError.value = data.message || `Gagal menyinkronkan lokasi (HTTP ${response.status}).`;
                } else {
                    trackingError.value = null;
                }
            } catch (err: any) {
                trackingError.value = err.message || 'Koneksi terputus saat sinkronisasi lokasi GPS.';
            }
        },
        (error) => {
            if (error.code === error.PERMISSION_DENIED) {
                trackingError.value = 'Izin akses lokasi ditolak oleh browser. Mohon izinkan akses lokasi pada browser Anda.';
            } else if (error.code === error.POSITION_UNAVAILABLE) {
                trackingError.value = 'Informasi lokasi GPS tidak tersedia saat ini.';
            } else if (error.code === error.TIMEOUT) {
                trackingError.value = 'Permintaan lokasi GPS melebihi batas waktu (timeout).';
            } else {
                trackingError.value = error.message || 'Terjadi kesalahan saat membaca lokasi GPS.';
            }
        },
        { enableHighAccuracy: true, timeout: 10000 }
    );
}

onMounted(() => {
    if (props.booking.status === 'on_rent') {
        isTracking.value = true;
        sendLocation();
        trackingInterval = setInterval(sendLocation, 15000);
    }
});

onUnmounted(() => {
    if (trackingInterval) clearInterval(trackingInterval);
});

const statusLabel: Record<string, string> = {
    pending_dp: 'Menunggu Pembayaran DP',
    booked: 'DP Terkonfirmasi (Jadwal Terkunci)',
    assigned: 'Unit Siap Dikirim oleh Pegawai',
    delivering: 'Unit Sedang Dalam Perjalanan ke Alamat Anda',
    on_rent: 'Unit Sedang Disewa',
    returned: 'Unit Telah Kembali (Menunggu Pelunasan)',
    completed: 'Sewa Selesai & Lunas',
    cancelled: 'Pesanan Dibatalkan',
};

// Stepper alur sewa
const STEPS = [
    { key: 'pending_dp', label: 'Menunggu DP' },
    { key: 'booked',     label: 'DP Terkonfirmasi' },
    { key: 'delivering', label: 'Unit Dikirim' },
    { key: 'on_rent',    label: 'Sedang Disewa' },
    { key: 'returned',   label: 'Unit Kembali' },
    { key: 'completed',  label: 'Selesai & Lunas' },
];
const stepOrder = ['pending_dp', 'booked', 'assigned', 'delivering', 'on_rent', 'returned', 'completed'];
const activeStepIndex = computed(() => {
    const idx = stepOrder.indexOf(props.booking.status);
    if (idx === -1) return -1;
    // map delivering/assigned ke step index 2
    if (['assigned', 'delivering'].includes(props.booking.status)) return 2;
    const keyMap: Record<string, number> = { pending_dp: 0, booked: 1, on_rent: 3, returned: 4, completed: 5 };
    return keyMap[props.booking.status] ?? -1;
});

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
    <Head :title="`Booking ${booking.booking_code}`" />

    <div class="flex flex-col gap-6 p-4">
        <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-center">
            <div>
                <div class="flex items-center gap-3">
                    <h1 class="text-2xl font-bold font-mono">{{ booking.booking_code }}</h1>
                    <Badge :variant="statusVariant[booking.status] ?? 'outline'">
                        {{ statusLabel[booking.status] ?? booking.status }}
                    </Badge>
                </div>
                <p class="text-sm text-muted-foreground mt-1">
                    Jadwal sewa: {{ formatDateTime(booking.pickup_at) }} s/d {{ formatDateTime(booking.return_at) }} ({{ booking.duration_days }} hari)
                </p>
            </div>
            <div class="flex items-center gap-2">
                <Button as-child variant="outline">
                    <Link :href="`/bookings/${booking.id}/invoice`">
                        <Printer class="mr-1.5 h-4 w-4" />
                        Cetak Invoice
                    </Link>
                </Button>
                <Button as-child variant="outline">
                    <Link href="/bookings">Kembali ke Daftar</Link>
                </Button>
            </div>
        </div>

        <!-- Stepper Alur Sewa -->
        <div class="rounded-xl border bg-card p-4 shadow-xs">
            <h2 class="text-xs font-semibold uppercase tracking-wider text-muted-foreground mb-3">Tahapan Pesanan Anda</h2>
            <div class="grid grid-cols-2 gap-2 sm:grid-cols-6">
                <div
                    v-for="(st, idx) in STEPS"
                    :key="st.key"
                    class="flex flex-col items-center rounded-lg border p-2 text-center text-xs transition-colors"
                    :class="[
                        idx === activeStepIndex
                            ? 'border-primary bg-primary/10 font-bold text-primary ring-1 ring-primary'
                            : idx < activeStepIndex
                            ? 'border-emerald-300 bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-400'
                            : 'bg-muted/30 text-muted-foreground opacity-60'
                    ]"
                >
                    <span class="text-[10px]">Langkah {{ idx + 1 }}</span>
                    <span>{{ st.label }}</span>
                    <span v-if="idx < activeStepIndex" class="text-[10px] text-emerald-600 font-semibold">✓ Selesai</span>
                    <span v-else-if="idx === activeStepIndex" class="text-[10px] text-primary font-bold">● Aktif</span>
                </div>
            </div>
        </div>

        <!-- Peringatan Batas DP (24 Jam) -->
        <div
            v-if="booking.status === 'pending_dp' && !hasPendingPayment"
            class="flex items-center gap-3 rounded-xl bg-amber-500/10 border border-amber-500/30 p-4 text-amber-800 dark:text-amber-300"
        >
            <AlertCircle class="h-5 w-5 shrink-0" />
            <div class="text-xs sm:text-sm">
                <strong>Batas Waktu Pembayaran DP:</strong> Slot mobil ini direservasi untuk Anda selama <strong>24 jam</strong> sejak booking dibuat. Harap segera lakukan transfer DP dan unggah buktinya agar unit tidak dilepas ke pelanggan lain.
            </div>
        </div>

        <!-- Banner GPS Tracking Aktif & Peringatan Error -->
        <div v-if="trackingError" class="flex items-center gap-3 rounded-xl bg-destructive/10 border border-destructive/30 p-4 text-destructive">
            <AlertCircle class="h-5 w-5 shrink-0" />
            <div class="text-xs sm:text-sm">
                <strong>Peringatan Pelacakan Lokasi:</strong> {{ trackingError }}
            </div>
        </div>

        <div v-else-if="isTracking" class="flex items-center gap-3 rounded-xl bg-emerald-500/10 border border-emerald-500/30 p-4 text-emerald-800 dark:text-emerald-300">
            <span class="relative flex h-3 w-3 shrink-0">
                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                <span class="relative inline-flex rounded-full h-3 w-3 bg-emerald-500"></span>
            </span>
            <div class="text-xs sm:text-sm">
                <strong>Pelacakan GPS Aktif:</strong> Lokasi Anda sedang disinkronkan secara aman setiap 15 detik demi keamanan berkendara.
            </div>
        </div>

        <div class="grid gap-6 lg:grid-cols-3">
            <!-- Informasi Kendaraan & Rincian Tagihan -->
            <div class="flex flex-col gap-6 lg:col-span-1">
                <div class="rounded-xl border bg-card p-5 shadow-xs">
                    <img
                        v-if="booking.vehicle?.photo_url"
                        :src="booking.vehicle?.photo_url"
                        class="aspect-video w-full rounded-lg object-cover mb-4 border"
                    />
                    <h2 class="text-lg font-bold">{{ booking.vehicle?.brand }} {{ booking.vehicle?.model }}</h2>
                    <p class="text-xs text-muted-foreground font-mono mt-0.5">Plat: {{ booking.vehicle?.plate }}</p>

                    <div class="mt-4 flex flex-col gap-2 border-t pt-3 text-sm">
                        <div class="flex justify-between">
                            <span class="text-muted-foreground">Tarif Sewa Pokok:</span>
                            <span class="font-medium">{{ rupiah(booking.price_per_day) }} / hari</span>
                        </div>
                        <div class="flex justify-between text-xs">
                            <span class="text-muted-foreground">Durasi Rencana:</span>
                            <span class="font-medium">
                                {{ durationBreakdown.days }} hari ({{ durationBreakdown.days * 24 }} jam)
                                <template v-if="durationBreakdown.extraHours > 0">
                                    + {{ durationBreakdown.extraHours }} jam
                                </template>
                            </span>
                        </div>
                        <div class="flex justify-between text-xs">
                            <span class="text-muted-foreground">Sewa Pokok ({{ durationBreakdown.days }} hari):</span>
                            <span>{{ rupiah(durationBreakdown.basePrice) }}</span>
                        </div>
                        <div v-if="durationBreakdown.extraHours > 0" class="flex justify-between text-xs text-amber-600 dark:text-amber-400">
                            <span>Lebihan Sewa ({{ durationBreakdown.extraHours }} jam x {{ rupiah(booking.vehicle?.late_fee_per_hour) }}):</span>
                            <span>+{{ rupiah(durationBreakdown.overtimeFee) }}</span>
                        </div>
                        <div class="flex justify-between font-bold border-t pt-2 text-base">
                            <span>Total Sewa Pesanan:</span>
                            <span class="text-primary">{{ rupiah(booking.total_price) }}</span>
                        </div>
                        <div class="flex justify-between text-xs text-muted-foreground">
                            <span>Kewajiban DP (30%):</span>
                            <span>{{ rupiah(booking.dp_amount) }}</span>
                        </div>

                        <!-- Denda jika ada -->
                        <div v-if="Number(booking.late_fee_total) > 0" class="flex justify-between text-xs text-destructive font-medium border-t pt-2">
                            <span>Denda Telat Pengembalian:</span>
                            <span>+{{ rupiah(booking.late_fee_total) }}</span>
                        </div>
                        <div v-if="damagesTotal > 0" class="flex justify-between text-xs text-destructive font-medium">
                            <span>Denda Kerusakan Unit:</span>
                            <span>+{{ rupiah(damagesTotal) }}</span>
                        </div>

                        <!-- Sisa Tagihan -->
                        <div v-if="['returned', 'completed'].includes(booking.status)" class="flex justify-between font-bold border-t pt-2 text-sm">
                            <span>Sisa Pelunasan:</span>
                            <span :class="remainingBalance > 0 ? 'text-destructive' : 'text-emerald-600'">
                                {{ remainingBalance > 0 ? rupiah(remainingBalance) : 'Lunas' }}
                            </span>
                        </div>
                    </div>
                </div>

                <div class="rounded-xl border bg-card p-5 shadow-xs text-sm">
                    <h3 class="font-semibold mb-2">Alamat Pengiriman Unit</h3>
                    <p class="text-muted-foreground">{{ booking.delivery_address }}</p>
                    <div class="mt-3 text-xs text-muted-foreground">
                        Kota / Wilayah Tujuan: <strong>{{ booking.destination }}</strong>
                    </div>
                </div>
            </div>

            <!-- Form Pembayaran & Riwayat -->
            <!-- Form Pembayaran & Riwayat -->
            <div class="flex flex-col gap-6 lg:col-span-2">
                <!-- Status Menunggu Verifikasi Pembayaran -->
                <div
                    v-if="hasPendingPayment"
                    class="rounded-xl border border-blue-200 bg-blue-50/80 p-5 dark:border-blue-900/60 dark:bg-blue-950/30 text-blue-950 dark:text-blue-200"
                >
                    <div class="flex items-center gap-2 font-bold text-base">
                        <span class="inline-block h-3 w-3 animate-ping rounded-full bg-blue-500"></span>
                        Bukti Pembayaran Sedang Diverifikasi Admin
                    </div>
                    <p class="text-xs sm:text-sm mt-2 leading-relaxed text-blue-900/80 dark:text-blue-300">
                        Terima kasih! Bukti transfer Anda telah terkirim dan saat ini sedang ditinjau oleh tim Admin Rental. Kami akan segera memperbarui status booking Anda setelah pembayaran diverifikasi.
                    </p>
                </div>

                <!-- Form Bayar (DP atau Pelunasan) -->
                <div
                    v-else-if="booking.status === 'pending_dp' || (booking.status === 'returned' && remainingBalance > 0)"
                    class="rounded-xl border bg-card p-5 shadow-xs"
                >
                    <h2 class="text-base font-semibold mb-1">
                        {{ booking.status === 'pending_dp' ? 'Pembayaran Uang Muka (DP)' : 'Pembayaran Pelunasan & Denda' }}
                    </h2>
                    <p class="text-xs text-muted-foreground mb-4">
                        Pilih metode pembayaran dan unggah foto/screenshot bukti transfer Anda.
                    </p>

                    <!-- Pilihan Rekening / QRIS -->
                    <div class="grid gap-3 sm:grid-cols-2 mb-5">
                        <div
                            v-for="method in paymentMethods"
                            :key="method.id"
                            class="flex flex-col justify-between rounded-lg border p-3 cursor-pointer transition-all"
                            :class="selectedMethod?.id === method.id ? 'border-primary bg-primary/5 ring-2 ring-primary/20' : 'hover:bg-muted/40'"
                            @click="selectedMethod = method; paymentForm.payment_method_id = method.id"
                        >
                            <div class="flex items-center justify-between">
                                <span class="font-semibold text-sm">{{ method.label }}</span>
                                <Badge variant="outline" class="text-[10px] uppercase">{{ method.type === 'qris' ? 'QRIS' : 'Bank' }}</Badge>
                            </div>
                            <div v-if="method.type === 'bank_transfer'" class="mt-2 text-xs">
                                <div class="font-mono font-bold text-sm text-foreground">{{ method.account_number }}</div>
                                <div class="text-muted-foreground">A.N. {{ method.account_name }}</div>
                            </div>
                            <div v-if="method.type === 'qris' && method.qris_image_url" class="mt-2 text-center">
                                <img :src="method.qris_image_url" class="h-28 w-28 mx-auto rounded object-contain border" />
                            </div>
                        </div>
                    </div>

                    <!-- Form Upload Bukti -->
                    <form class="flex flex-col gap-4 border-t pt-4" @submit.prevent="submitPayment">
                        <div class="grid gap-3 sm:grid-cols-2">
                            <div>
                                <Label>Nominal Tagihan</Label>
                                <div class="mt-1 flex h-9 w-full items-center rounded-md border border-input bg-muted px-3 text-sm font-bold text-foreground">
                                    {{ rupiah(booking.status === 'pending_dp' ? booking.dp_amount : remainingBalance) }}
                                </div>
                                <span class="text-[11px] text-muted-foreground mt-0.5 block">
                                    {{ booking.status === 'pending_dp' ? 'Pembayaran Uang Muka DP (30%)' : 'Sisa pelunasan rental dan denda' }}
                                </span>
                            </div>
                            <div>
                                <Label for="proof">Unggah Foto Bukti Transfer</Label>
                                <Input id="proof" type="file" accept="image/*" class="mt-1" required @change="onProofChange" />
                            </div>
                        </div>

                        <div class="flex justify-end pt-2">
                            <Button type="submit" size="sm" :disabled="paymentForm.processing">
                                <span v-if="paymentForm.processing" class="mr-2 inline-block h-3.5 w-3.5 animate-spin rounded-full border-2 border-current border-t-transparent"></span>
                                Kirim Bukti Pembayaran
                            </Button>
                        </div>
                    </form>
                </div>

                <!-- Riwayat Pembayaran -->
                <div class="rounded-xl border bg-card p-5 shadow-xs">
                    <h2 class="text-base font-semibold mb-3">Riwayat Pembayaran</h2>
                    <div v-if="!booking.payments || booking.payments.length === 0" class="rounded-lg border p-6 text-center text-xs text-muted-foreground">
                        Belum ada riwayat pembayaran yang diunggah.
                    </div>

                    <div v-else class="flex flex-col gap-3">
                        <div
                            v-for="p in booking.payments"
                            :key="p.id"
                            class="flex items-center justify-between rounded-lg border p-3 text-sm"
                        >
                            <div class="flex items-center gap-3">
                                <a v-if="p.proof_url" :href="p.proof_url" target="_blank">
                                    <img :src="p.proof_url" class="h-12 w-16 rounded object-cover border" />
                                </a>
                                <div>
                                    <div class="font-bold">{{ rupiah(p.amount) }}</div>
                                    <div class="text-xs text-muted-foreground uppercase font-medium">
                                        {{ p.type }} · {{ p.payment_method?.label ?? '-' }}
                                    </div>
                                    <div class="text-[11px] text-muted-foreground">{{ formatDateTime(p.created_at) }}</div>
                                </div>
                            </div>

                            <Badge :variant="p.status === 'verified' ? 'default' : p.status === 'rejected' ? 'destructive' : 'secondary'">
                                {{ p.status === 'verified' ? 'Terverifikasi' : p.status === 'rejected' ? 'Ditolak' : 'Menunggu Verifikasi' }}
                            </Badge>
                        </div>
                    </div>
                </div>

                <!-- Riwayat Kondisi & Foto Serah Terima -->
                <div v-if="booking.delivery?.photos && booking.delivery.photos.length > 0" class="rounded-xl border bg-card p-5 shadow-xs">
                    <h2 class="text-base font-semibold mb-3">Foto Kondisi Unit Saat Serah Terima</h2>
                    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                        <div v-for="photo in booking.delivery.photos" :key="photo.id" class="rounded-lg border p-2">
                            <a :href="photo.photo_url" target="_blank">
                                <img :src="photo.photo_url" class="h-24 w-full rounded object-cover" />
                            </a>
                            <div class="mt-1 text-xs font-semibold uppercase">{{ photo.stage }}</div>
                            <div class="text-[11px] text-muted-foreground">{{ photo.note ?? '-' }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
