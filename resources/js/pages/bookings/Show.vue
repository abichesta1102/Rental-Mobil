<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import {
    AlertCircle,
    ArrowLeft,
    Check,
    CreditCard,
    FileText,
    MapPin,
    Navigation,
    Printer,
    Upload,
} from '@lucide/vue';
import { computed, onMounted, onUnmounted, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import PageHeader from '@/components/PageHeader.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { formatDateTime, rupiah } from '@/lib/format';
import { getStatusLabel } from '@/lib/status';

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

const totalPaid = (props.booking.payments ?? [])
    .filter((p: any) => p.status === 'verified')
    .reduce((sum: number, p: any) => sum + Number(p.amount), 0);

const damagesTotal = (props.booking.damages ?? [])
    .reduce((sum: number, d: any) => sum + Number(d.fine_amount), 0);

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
        trackingError.value = 'Browser Anda tidak mendukung pelacakan Geolocation.';
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
                    trackingError.value = data.message || `Gagal menyinkronkan lokasi (${response.status}).`;
                } else {
                    trackingError.value = null;
                }
            } catch (err: any) {
                trackingError.value = err.message || 'Koneksi terputus saat sinkronisasi lokasi GPS.';
            }
        },
        (error) => {
            if (error.code === error.PERMISSION_DENIED) {
                trackingError.value = 'Izin akses lokasi ditolak oleh browser. Mohon izinkan akses lokasi agar sistem operasional dapat memantau perjalanan unit.';
            } else if (error.code === error.POSITION_UNAVAILABLE) {
                trackingError.value = 'Informasi lokasi GPS tidak tersedia.';
            } else if (error.code === error.TIMEOUT) {
                trackingError.value = 'Permintaan lokasi GPS melebihi batas waktu (timeout).';
            } else {
                trackingError.value = error.message || 'Gagal membaca koordinat GPS.';
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

// Stepper alur sewa
const STEPS = [
    { key: 'pending_dp', label: 'Menunggu DP' },
    { key: 'booked', label: 'DP Terkonfirmasi' },
    { key: 'delivering', label: 'Unit Dikirim' },
    { key: 'on_rent', label: 'Sedang Disewa' },
    { key: 'returned', label: 'Unit Kembali' },
    { key: 'completed', label: 'Selesai & Lunas' },
];

const stepOrder = ['pending_dp', 'booked', 'assigned', 'delivering', 'on_rent', 'returned', 'completed'];
const activeStepIndex = computed(() => {
    const idx = stepOrder.indexOf(props.booking.status);
    if (idx === -1) return -1;
    if (['assigned', 'delivering'].includes(props.booking.status)) return 2;
    const keyMap: Record<string, number> = { pending_dp: 0, booked: 1, on_rent: 3, returned: 4, completed: 5 };
    return keyMap[props.booking.status] ?? -1;
});
</script>

<template>
    <Head :title="`Booking ${booking.booking_code}`" />

    <div class="flex flex-col gap-6 p-4 sm:p-6 max-w-5xl mx-auto w-full">
        <PageHeader
            :title="`Booking ${booking.booking_code}`"
            :description="`Jadwal: ${formatDateTime(booking.pickup_at)} s/d ${formatDateTime(booking.return_at)} (${booking.duration_days} hari)`"
        >
            <template #actions>
                <StatusBadge :status="booking.status" class="mr-2" />
                <Button as-child variant="outline" size="sm" class="h-9 min-h-[36px]">
                    <Link :href="`/bookings/${booking.id}/invoice`">
                        <Printer class="mr-1.5 size-4" />
                        Cetak Invoice
                    </Link>
                </Button>
                <Button as-child variant="outline" size="sm" class="h-9 min-h-[36px]">
                    <Link href="/bookings">Kembali ke Daftar</Link>
                </Button>
            </template>
        </PageHeader>

        <!-- Stepper Alur Sewa (Responsif di 375px) -->
        <div class="rounded-[6px] border border-border bg-card p-4">
            <h2 class="text-xs font-semibold uppercase tracking-wider text-muted-foreground mb-3">
                Tahapan Proses Pemesanan
            </h2>
            <div class="grid grid-cols-2 gap-2 sm:grid-cols-6">
                <div
                    v-for="(st, idx) in STEPS"
                    :key="st.key"
                    class="flex flex-col items-center justify-center rounded-[6px] border p-2 text-center text-xs transition-colors min-h-[52px]"
                    :class="[
                        idx === activeStepIndex
                            ? 'border-primary bg-primary/10 font-bold text-primary ring-1 ring-primary'
                            : idx < activeStepIndex
                            ? 'border-emerald-600/30 bg-emerald-50 text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300'
                            : 'border-border bg-muted/40 text-muted-foreground opacity-60'
                    ]"
                >
                    <span class="text-[10px] text-muted-foreground">Langkah {{ idx + 1 }}</span>
                    <span class="font-medium mt-0.5">{{ st.label }}</span>
                    <span v-if="idx === activeStepIndex" class="text-[10px] font-bold text-primary mt-0.5">Sedang Berjalan</span>
                    <span v-else-if="idx < activeStepIndex" class="text-[10px] font-medium text-emerald-700 dark:text-emerald-400 mt-0.5">Selesai</span>
                </div>
            </div>
        </div>

        <!-- Peringatan Batas DP (24 Jam) -->
        <div
            v-if="booking.status === 'pending_dp' && !hasPendingPayment"
            class="flex items-center gap-3 rounded-[6px] bg-amber-50 dark:bg-amber-950/40 border border-amber-600/30 p-4 text-amber-900 dark:text-amber-200"
        >
            <AlertCircle class="size-5 shrink-0 text-amber-600" />
            <div class="text-xs sm:text-sm">
                <strong>Batas Waktu Pembayaran DP:</strong> Slot kendaraan ini direservasi selama <strong>24 jam</strong> sejak dibuat. Selesaikan transfer DP dan unggah buktinya agar unit tidak dilepas ke pelanggan lain.
            </div>
        </div>

        <!-- Banner GPS Tracking Aktif & Peringatan Error -->
        <div v-if="trackingError" class="flex items-center gap-3 rounded-[6px] bg-destructive/10 border border-destructive/30 p-4 text-destructive">
            <AlertCircle class="size-5 shrink-0" />
            <div class="text-xs sm:text-sm">
                <strong>Pemberitahuan Pelacakan Lokasi:</strong> {{ trackingError }}
            </div>
        </div>

        <div v-else-if="isTracking" class="flex items-center gap-3 rounded-[6px] bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-600/30 p-4 text-emerald-900 dark:text-emerald-200">
            <Navigation class="size-4 shrink-0 text-emerald-600 animate-pulse" />
            <div class="text-xs sm:text-sm">
                <strong>Pelacakan GPS Berjalan:</strong> Lokasi Anda disinkronkan secara berkala setiap 15 detik demi keamanan operasional armada.
            </div>
        </div>

        <div class="grid gap-6 lg:grid-cols-3">
            <!-- Informasi Kendaraan & Rincian Tagihan -->
            <div class="flex flex-col gap-6 lg:col-span-1">
                <div class="rounded-[6px] border border-border bg-card p-4">
                    <img
                        v-if="booking.vehicle?.photo_url"
                        :src="booking.vehicle?.photo_url"
                        :alt="booking.vehicle?.model"
                        class="aspect-video w-full rounded-[4px] object-cover mb-3 border border-border"
                    />
                    <h2 class="text-base font-bold text-foreground">{{ booking.vehicle?.brand }} {{ booking.vehicle?.model }}</h2>
                    <p class="text-xs text-muted-foreground font-mono mt-0.5">Plat: {{ booking.vehicle?.plate }}</p>

                    <div class="mt-4 flex flex-col gap-2 border-t border-border pt-3 text-xs">
                        <div class="flex justify-between">
                            <span class="text-muted-foreground">Tarif Sewa Pokok:</span>
                            <span class="font-medium text-foreground">{{ rupiah(booking.price_per_day) }} / hari</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-muted-foreground">Durasi:</span>
                            <span class="font-medium text-foreground">
                                {{ durationBreakdown.days }} hari
                                <template v-if="durationBreakdown.extraHours > 0">
                                    + {{ durationBreakdown.extraHours }} jam
                                </template>
                            </span>
                        </div>
                        <div class="flex justify-between pt-2 border-t border-border font-bold text-sm text-foreground">
                            <span>Total Biaya Sewa:</span>
                            <span class="text-primary">{{ rupiah(booking.total_price) }}</span>
                        </div>
                        <div class="flex justify-between text-muted-foreground">
                            <span>Kewajiban DP (30%):</span>
                            <span>{{ rupiah(booking.dp_amount) }}</span>
                        </div>

                        <!-- Denda jika ada -->
                        <div v-if="Number(booking.late_fee_total) > 0" class="flex justify-between text-destructive font-medium border-t border-border pt-2">
                            <span>Denda Keterlambatan:</span>
                            <span>+{{ rupiah(booking.late_fee_total) }}</span>
                        </div>
                        <div v-if="damagesTotal > 0" class="flex justify-between text-destructive font-medium">
                            <span>Denda Kerusakan Unit:</span>
                            <span>+{{ rupiah(damagesTotal) }}</span>
                        </div>

                        <!-- Sisa Tagihan -->
                        <div v-if="['returned', 'completed'].includes(booking.status)" class="flex justify-between font-bold border-t border-border pt-2 text-sm">
                            <span>Sisa Pelunasan:</span>
                            <span :class="remainingBalance > 0 ? 'text-destructive' : 'text-emerald-700'">
                                {{ remainingBalance > 0 ? rupiah(remainingBalance) : 'Lunas' }}
                            </span>
                        </div>
                    </div>
                </div>

                <div class="rounded-[6px] border border-border bg-card p-4 text-xs space-y-2">
                    <h3 class="font-semibold text-foreground pb-2 border-b border-border">Alamat Pengantaran Unit</h3>
                    <p class="text-muted-foreground leading-normal">{{ booking.delivery_address }}</p>
                    <div class="text-muted-foreground pt-1">
                        Tujuan Perjalanan: <strong class="text-foreground">{{ booking.destination }}</strong>
                    </div>
                </div>
            </div>

            <!-- Form Pembayaran & Riwayat -->
            <div class="flex flex-col gap-6 lg:col-span-2">
                <!-- Status Menunggu Verifikasi Pembayaran -->
                <div
                    v-if="hasPendingPayment"
                    class="rounded-[6px] border border-blue-600/30 bg-blue-50 dark:bg-blue-950/40 p-4 text-blue-950 dark:text-blue-200"
                >
                    <div class="flex items-center gap-2 font-semibold text-sm">
                        <Check class="size-4 text-blue-600" />
                        Bukti Pembayaran Sedang Diverifikasi Admin
                    </div>
                    <p class="text-xs mt-1.5 leading-relaxed text-blue-900/80 dark:text-blue-300">
                        Bukti transfer Anda telah diterima dan sedang diperiksa oleh tim Admin. Status pesanan akan diperbarui secara otomatis setelah diverifikasi.
                    </p>
                </div>

                <!-- Form Bayar (DP atau Pelunasan) -->
                <div
                    v-else-if="booking.status === 'pending_dp' || (booking.status === 'returned' && remainingBalance > 0)"
                    class="rounded-[6px] border border-border bg-card p-4 sm:p-5"
                >
                    <h2 class="text-sm font-semibold tracking-tight text-foreground mb-1">
                        {{ booking.status === 'pending_dp' ? 'Pembayaran Uang Muka (DP)' : 'Pembayaran Pelunasan & Denda' }}
                    </h2>
                    <p class="text-xs text-muted-foreground mb-4">
                        Pilih metode transfer bank atau QRIS di bawah ini, lalu unggah foto/tangkapan layar bukti pembayaran Anda.
                    </p>

                    <!-- Pilihan Rekening / QRIS -->
                    <div class="grid gap-3 sm:grid-cols-2 mb-5">
                        <div
                            v-for="method in paymentMethods"
                            :key="method.id"
                            class="flex flex-col justify-between rounded-[6px] border p-3 cursor-pointer transition-colors"
                            :class="selectedMethod?.id === method.id
                                ? 'border-primary bg-primary/5 ring-1 ring-primary'
                                : 'border-border bg-background hover:bg-muted/40'"
                            @click="selectedMethod = method; paymentForm.payment_method_id = method.id"
                        >
                            <div class="space-y-1">
                                <div class="flex items-center justify-between">
                                    <span class="rounded-[4px] border border-border bg-muted/60 px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wider text-muted-foreground">
                                        {{ method.type === 'qris' ? 'QRIS' : 'Transfer Bank' }}
                                    </span>
                                    <span v-if="selectedMethod?.id === method.id" class="text-xs font-bold text-primary">Dipilih</span>
                                </div>
                                <div class="font-medium text-xs text-foreground mt-1">{{ method.label }}</div>
                                <div v-if="method.type === 'bank_transfer'" class="font-mono text-sm font-bold text-foreground">
                                    {{ method.account_number }}
                                </div>
                                <div v-if="method.account_name" class="text-[11px] text-muted-foreground">
                                    A.N. {{ method.account_name }}
                                </div>
                            </div>

                            <div v-if="method.type === 'qris' && method.qris_image_url" class="mt-2 text-center">
                                <img
                                    :src="method.qris_image_url"
                                    alt="QRIS Barcode"
                                    class="h-28 w-28 object-contain mx-auto rounded-[4px] border border-border p-1 bg-white"
                                />
                            </div>
                        </div>
                    </div>

                    <!-- Upload Form -->
                    <form class="space-y-3.5 border-t border-border pt-4" @submit.prevent="submitPayment">
                        <div>
                            <Label for="proof" class="text-xs font-medium">Unggah Bukti Transfer / Pembayaran</Label>
                            <Input
                                id="proof"
                                type="file"
                                accept="image/*"
                                class="h-10 min-h-[40px] mt-1 text-xs cursor-pointer"
                                required
                                @change="onProofChange"
                            />
                            <InputError :message="paymentForm.errors.proof" />
                        </div>

                        <Button
                            type="submit"
                            class="w-full h-11 min-h-[44px] text-sm font-medium"
                            :disabled="paymentForm.processing || !selectedMethod"
                        >
                            <Spinner v-if="paymentForm.processing" class="mr-2" />
                            Kirim Bukti Pembayaran
                        </Button>
                    </form>
                </div>

                <!-- Riwayat Pembayaran -->
                <div class="rounded-[6px] border border-border bg-card p-4">
                    <h3 class="text-sm font-semibold tracking-tight text-foreground mb-3 pb-2 border-b border-border">
                        Riwayat Pembayaran Booking Ini
                    </h3>

                    <div v-if="!booking.payments || booking.payments.length === 0" class="p-4 text-center text-xs text-muted-foreground">
                        Belum ada riwayat pembayaran yang tercatat.
                    </div>

                    <div v-else class="overflow-x-auto">
                        <table class="w-full text-left text-xs">
                            <thead class="border-b border-border bg-muted/40 text-muted-foreground font-semibold">
                                <tr>
                                    <th class="p-2.5">Tipe Tagihan</th>
                                    <th class="p-2.5">Nominal</th>
                                    <th class="p-2.5">Metode</th>
                                    <th class="p-2.5">Waktu</th>
                                    <th class="p-2.5">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border">
                                <tr v-for="pay in booking.payments" :key="pay.id" class="hover:bg-muted/30">
                                    <td class="p-2.5 uppercase font-medium">{{ pay.type }}</td>
                                    <td class="p-2.5 font-medium whitespace-nowrap">{{ rupiah(pay.amount) }}</td>
                                    <td class="p-2.5 text-muted-foreground">{{ pay.payment_method?.label ?? '-' }}</td>
                                    <td class="p-2.5 text-muted-foreground whitespace-nowrap">{{ formatDateTime(pay.created_at) }}</td>
                                    <td class="p-2.5 whitespace-nowrap">
                                        <StatusBadge :status="pay.status" />
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
