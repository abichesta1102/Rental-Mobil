<script setup lang="ts">
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { ArrowLeft, Calendar, Car, Check, Clock, ShieldCheck } from '@lucide/vue';
import { computed, watch } from 'vue';
import DateTimePicker from '@/components/DateTimePicker.vue';
import InputError from '@/components/InputError.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { formatDateTime, formatDayLabel, rupiah } from '@/lib/format';

interface Vehicle {
    id: number;
    plate: string;
    brand: string;
    model: string;
    year: number | null;
    color: string | null;
    transmission: 'manual' | 'matic';
    seats: number;
    price_per_day: string;
    late_fee_per_hour: string;
    photo_url: string | null;
}

const props = defineProps<{
    vehicle: Vehicle;
    bookable: boolean;
    days: { date: string; busy: boolean }[];
    busy: { pickup_at: string; return_at: string }[];
    prefill: { pickup_at: string | null; return_at: string | null };
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Katalog Kendaraan', href: '/vehicles' },
            { title: 'Detail & Booking', href: '#' },
        ],
    },
});

const page = usePage();
const auth = computed(() => (page.props.auth as any)?.user);
const role = computed(() => auth.value?.role);

function padZero(n: number | string): string {
    return String(n).padStart(2, '0');
}

function getNowDateTimeStr(): string {
    const now = new Date();
    const y = now.getFullYear();
    const m = padZero(now.getMonth() + 1);
    const d = padZero(now.getDate());
    const h = padZero(now.getHours());
    return `${y}-${m}-${d}T${h}:00`;
}

function add24Hours(isoString: string): string {
    if (!isoString) return '';
    const date = new Date(isoString);
    if (Number.isNaN(date.getTime())) return '';
    const nextDay = new Date(date.getTime() + 24 * 60 * 60 * 1000);
    const y = nextDay.getFullYear();
    const m = padZero(nextDay.getMonth() + 1);
    const d = padZero(nextDay.getDate());
    const h = padZero(nextDay.getHours());
    const min = padZero(nextDay.getMinutes());
    return `${y}-${m}-${d}T${h}:${min}`;
}

const initialPickup = props.prefill.pickup_at || getNowDateTimeStr();
const initialReturn = props.prefill.return_at || add24Hours(initialPickup);

const form = useForm({
    vehicle_id: props.vehicle.id,
    pickup_at: initialPickup,
    return_at: initialReturn,
    destination: '',
    delivery_address: '',
});

watch(
    () => form.pickup_at,
    (newVal) => {
        if (newVal) {
            form.return_at = add24Hours(newVal);
        }
    }
);

const estimate = computed(() => {
    if (!form.pickup_at || !form.return_at) return null;

    const start = new Date(form.pickup_at).getTime();
    const end = new Date(form.return_at).getTime();
    if (Number.isNaN(start) || Number.isNaN(end) || end <= start) return null;

    const diffHours = (end - start) / (1000 * 60 * 60);
    const pricePerDay = Number(props.vehicle.price_per_day);
    const lateFeePerHour = Number(props.vehicle.late_fee_per_hour);

    let days = 0;
    let extraHours = 0;
    let basePrice = 0;
    let overtimeFee = 0;
    let total = 0;

    if (diffHours <= 24) {
        days = 1;
        extraHours = 0;
        basePrice = pricePerDay;
        overtimeFee = 0;
        total = pricePerDay;
    } else {
        days = Math.floor(diffHours / 24);
        const remainder = diffHours % 24;
        extraHours = Math.ceil(remainder);

        if (extraHours >= 24) {
            days += 1;
            extraHours = 0;
            basePrice = days * pricePerDay;
            overtimeFee = 0;
            total = days * pricePerDay;
        } else {
            basePrice = days * pricePerDay;
            overtimeFee = extraHours * lateFeePerHour;
            total = basePrice + overtimeFee;
        }
    }

    const dp = Math.round(total * 0.3);
    return { days, extraHours, basePrice, overtimeFee, total, dp };
});

function submit(): void {
    form.post('/bookings');
}
</script>

<template>
    <Head :title="`${vehicle.brand} ${vehicle.model} - Detail`" />

    <div class="flex flex-col gap-6 p-4 sm:p-6 max-w-5xl mx-auto w-full">
        <PageHeader
            :title="`${vehicle.brand} ${vehicle.model}`"
            :description="`${vehicle.year ?? '-'} &middot; Transmisi ${vehicle.transmission} &middot; ${vehicle.seats} kursi penumpang &middot; Plat: ${vehicle.plate}`"
        >
            <template #actions>
                <Button as-child variant="outline" size="sm" class="h-9 min-h-[36px]">
                    <Link href="/vehicles">
                        <ArrowLeft class="mr-1.5 size-4" />
                        Kembali ke Katalog
                    </Link>
                </Button>
            </template>
        </PageHeader>

        <div class="grid gap-6 lg:grid-cols-3">
            <!-- Kolom Kiri: Foto, Spesifikasi & Kalender Ketersediaan -->
            <div class="flex flex-col gap-6 lg:col-span-1">
                <!-- Foto Unit -->
                <div class="rounded-[6px] border border-border bg-card overflow-hidden">
                    <img
                        v-if="vehicle.photo_url"
                        :src="vehicle.photo_url"
                        :alt="`${vehicle.brand} ${vehicle.model}`"
                        class="aspect-video w-full object-cover"
                    />
                    <div v-else class="flex aspect-video w-full items-center justify-center bg-muted text-xs text-muted-foreground">
                        Tanpa Foto
                    </div>
                    <div class="p-4 space-y-2 text-xs">
                        <div class="flex justify-between py-1 border-b border-border">
                            <span class="text-muted-foreground">Tarif Sewa Harian:</span>
                            <span class="font-bold text-sm text-primary">{{ rupiah(vehicle.price_per_day) }}</span>
                        </div>
                        <div class="flex justify-between py-1 border-b border-border">
                            <span class="text-muted-foreground">Denda Telat / Jam:</span>
                            <span class="font-medium text-foreground">{{ rupiah(vehicle.late_fee_per_hour) }}</span>
                        </div>
                        <div class="flex justify-between py-1 border-b border-border">
                            <span class="text-muted-foreground">Warna Unit:</span>
                            <span class="font-medium text-foreground">{{ vehicle.color ?? '-' }}</span>
                        </div>
                    </div>
                </div>

                <!-- Kalender Ketersediaan 14 Hari -->
                <div class="rounded-[6px] border border-border bg-card p-4">
                    <h3 class="text-xs font-semibold uppercase tracking-wider text-muted-foreground mb-3 flex items-center gap-1.5">
                        <Calendar class="size-4 text-primary" />
                        <span>Ketersediaan 14 Hari ke Depan</span>
                    </h3>
                    <div class="grid grid-cols-2 sm:grid-cols-2 gap-2 text-xs">
                        <div
                            v-for="d in days"
                            :key="d.date"
                            class="flex items-center justify-between rounded-[4px] border px-2.5 py-1.5 min-h-[36px]"
                            :class="d.busy ? 'border-destructive/30 bg-destructive/10 text-destructive' : 'border-emerald-600/30 bg-emerald-50 dark:bg-emerald-950/40 text-emerald-800 dark:text-emerald-300'"
                        >
                            <span class="font-medium">{{ formatDayLabel(d.date) }}</span>
                            <span class="text-[10px] uppercase font-semibold">{{ d.busy ? 'Disewa' : 'Ready' }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Kolom Kanan: Form Booking & Estimasi Biaya (Responsif 375px) -->
            <div class="flex flex-col gap-6 lg:col-span-2">
                <div class="rounded-[6px] border border-border bg-card p-4 sm:p-6">
                    <h2 class="text-sm font-semibold tracking-tight text-foreground mb-4 pb-2 border-b border-border">
                        Form Pemesanan Kendaraan
                    </h2>

                    <!-- Peringatan untuk Tamu / Role Bukan Pelanggan -->
                    <div
                        v-if="!auth"
                        class="mb-4 rounded-[6px] border border-amber-600/30 bg-amber-50 dark:bg-amber-950/40 p-4 text-xs text-amber-900 dark:text-amber-200"
                    >
                        <span>Silakan </span>
                        <Link href="/login" class="font-semibold underline">masuk ke akun</Link>
                        <span> atau </span>
                        <Link href="/register" class="font-semibold underline">mendaftar</Link>
                        <span> terlebih dahulu untuk memesan kendaraan.</span>
                    </div>

                    <form class="space-y-4" @submit.prevent="submit">
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <Label for="pickup_at" class="text-xs">Waktu Pengambilan</Label>
                                <div class="mt-1">
                                    <DateTimePicker v-model="form.pickup_at" />
                                </div>
                                <InputError :message="form.errors.pickup_at" />
                            </div>

                            <div>
                                <Label for="return_at" class="text-xs">Waktu Pengembalian</Label>
                                <div class="mt-1">
                                    <DateTimePicker v-model="form.return_at" />
                                </div>
                                <InputError :message="form.errors.return_at" />
                            </div>
                        </div>

                        <div>
                            <Label for="destination" class="text-xs">Kota / Wilayah Tujuan Perjalanan</Label>
                            <Input
                                id="destination"
                                v-model="form.destination"
                                placeholder="Contoh: Surabaya - Malang - Batu"
                                class="h-10 min-h-[40px] text-xs mt-1"
                                required
                            />
                            <InputError :message="form.errors.destination" />
                        </div>

                        <div>
                            <Label for="delivery_address" class="text-xs">Alamat Pengantaran Unit Kendaraan</Label>
                            <Input
                                id="delivery_address"
                                v-model="form.delivery_address"
                                placeholder="Contoh: Jl. Panglima Sudirman No. 45, RT 02/03"
                                class="h-10 min-h-[40px] text-xs mt-1"
                                required
                            />
                            <InputError :message="form.errors.delivery_address" />
                        </div>

                        <!-- Rincian Estimasi Biaya -->
                        <div v-if="estimate" class="rounded-[6px] border border-border bg-muted/40 p-4 space-y-2 text-xs">
                            <h3 class="font-semibold text-foreground text-xs uppercase tracking-wider mb-2">
                                Rincian Perhitungan Biaya
                            </h3>
                            <div class="flex justify-between">
                                <span class="text-muted-foreground">Sewa Pokok:</span>
                                <span class="font-medium">{{ estimate.days }} Hari &times; {{ rupiah(vehicle.price_per_day) }} = {{ rupiah(estimate.basePrice) }}</span>
                            </div>
                            <div v-if="estimate.extraHours > 0" class="flex justify-between text-amber-700 dark:text-amber-400">
                                <span>Kelebihan Jam:</span>
                                <span class="font-medium">{{ estimate.extraHours }} Jam &times; {{ rupiah(vehicle.late_fee_per_hour) }} = {{ rupiah(estimate.overtimeFee) }}</span>
                            </div>
                            <div class="flex justify-between pt-2 border-t border-border font-bold text-sm text-foreground">
                                <span>Total Estimasi Biaya:</span>
                                <span class="text-primary">{{ rupiah(estimate.total) }}</span>
                            </div>
                            <div class="flex justify-between text-xs text-muted-foreground">
                                <span>Uang Muka (DP 30%):</span>
                                <span class="font-medium text-foreground">{{ rupiah(estimate.dp) }}</span>
                            </div>
                        </div>

                        <Button
                            type="submit"
                            class="w-full h-11 min-h-[44px] text-sm font-medium mt-4"
                            :disabled="form.processing || !bookable || !auth"
                        >
                            <Spinner v-if="form.processing" class="mr-2" />
                            <span v-if="!auth">Masuk untuk Booking</span>
                            <span v-else-if="!bookable">Unit Tidak Tersedia di Tanggal Tersebut</span>
                            <span v-else>Konfirmasi & Pesan Kendaraan</span>
                        </Button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</template>
