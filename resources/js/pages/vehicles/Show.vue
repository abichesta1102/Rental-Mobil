<script setup lang="ts">
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import DateTimePicker from '@/components/DateTimePicker.vue';
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
            { title: 'Detail', href: '#' },
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

// Otomatis atur pengembalian pas 24 jam saat pengambilan dipilih/diubah
watch(
    () => form.pickup_at,
    (newVal) => {
        if (newVal) {
            form.return_at = add24Hours(newVal);
        }
    }
);

// Kalkulasi tarif sewa dan denda keterlambatan jika melebihi 24 jam
const estimate = computed(() => {
    if (!form.pickup_at || !form.return_at) return null;

    const start = new Date(form.pickup_at).getTime();
    const end = new Date(form.return_at).getTime();
    if (Number.isNaN(start) || Number.isNaN(end) || end <= start) return null;

    const totalSeconds = Math.max(0, (end - start) / 1000);
    const totalMinutes = Math.ceil(totalSeconds / 60);
    const totalHours = totalMinutes / 60;

    const pricePerDay = Number(props.vehicle.price_per_day);
    const lateFeePerHour = Number(props.vehicle.late_fee_per_hour);

    let days = 1;
    let extraHours = 0;
    let basePrice = pricePerDay;
    let overtimeFee = 0;

    if (totalHours <= 24) {
        days = 1;
        extraHours = 0;
        basePrice = pricePerDay;
        overtimeFee = 0;
    } else {
        days = Math.floor(totalHours / 24);
        const remainingMinutes = totalMinutes - (days * 24 * 60);
        extraHours = Math.ceil(remainingMinutes / 60);

        if (extraHours >= 24) {
            days += 1;
            extraHours = 0;
        }

        basePrice = days * pricePerDay;
        overtimeFee = extraHours * lateFeePerHour;
    }

    const total = basePrice + overtimeFee;
    const dp = Math.ceil((total * 0.3) / 10_000) * 10_000;

    return {
        days,
        extraHours,
        basePrice,
        overtimeFee,
        total,
        dp,
    };
});

function submit(): void {
    form.post('/bookings');
}

const textareaClass =
    'border-input bg-background min-h-20 w-full rounded-md border px-3 py-2 text-sm shadow-xs outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50';
</script>

<template>
    <Head :title="`${vehicle.brand} ${vehicle.model}`" />

    <div class="grid gap-6 p-4 lg:grid-cols-5">
        <div class="flex flex-col gap-5 lg:col-span-3">
            <img
                v-if="vehicle.photo_url"
                :src="vehicle.photo_url"
                :alt="`${vehicle.brand} ${vehicle.model}`"
                class="aspect-video w-full rounded-xl border object-cover"
            />
            <div
                v-else
                class="flex aspect-video w-full items-center justify-center rounded-xl border bg-muted text-muted-foreground"
            >
                Tanpa foto
            </div>

            <div>
                <h1 class="text-2xl font-semibold">{{ vehicle.brand }} {{ vehicle.model }}</h1>
                <p class="text-sm text-muted-foreground">
                    {{ vehicle.year ?? '-' }} · {{ vehicle.color ?? '-' }} · {{ vehicle.transmission }} ·
                    {{ vehicle.seats }} kursi · {{ vehicle.plate }}
                </p>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div class="rounded-xl border p-3">
                    <div class="text-xs text-muted-foreground">Harga sewa</div>
                    <div class="text-lg font-semibold">{{ rupiah(vehicle.price_per_day) }} / hari</div>
                </div>
                <div class="rounded-xl border p-3">
                    <div class="text-xs text-muted-foreground">Denda keterlambatan</div>
                    <div class="text-lg font-semibold">{{ rupiah(vehicle.late_fee_per_hour) }} / jam</div>
                </div>
            </div>

            <section class="flex flex-col gap-3 rounded-xl border p-4">
                <div>
                    <h2 class="font-semibold">Ketersediaan 30 hari ke depan</h2>
                    <p class="text-xs text-muted-foreground">
                        Hijau berarti ready sepanjang hari. Merah berarti sudah ada pesanan di hari itu
                        (sebagian atau penuh).
                    </p>
                </div>

                <div class="grid grid-cols-3 gap-2 sm:grid-cols-5">
                    <div
                        v-for="day in days"
                        :key="day.date"
                        class="rounded-md px-2 py-1.5 text-center text-xs"
                        :class="
                            day.busy
                                ? 'bg-destructive/15 text-destructive'
                                : 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-400'
                        "
                    >
                        {{ formatDayLabel(day.date) }}
                    </div>
                </div>

                <div v-if="busy.length > 0" class="text-sm">
                    <div class="mb-1 font-medium">Sudah dipesan pada:</div>
                    <ul class="flex flex-col gap-1 text-muted-foreground">
                        <li v-for="(period, index) in busy" :key="index">
                            {{ formatDateTime(period.pickup_at) }} sampai {{ formatDateTime(period.return_at) }}
                        </li>
                    </ul>
                </div>
                <p v-else class="text-sm text-muted-foreground">Belum ada pesanan. Semua tanggal ready.</p>
            </section>
        </div>

        <!-- Kolom Kanan: Jika Pegawai/Admin, Tampilkan Informasi Saja Tanpa Form Booking -->
        <div v-if="role !== 'pelanggan'" class="flex flex-col gap-4 self-start rounded-xl border bg-card p-5 lg:col-span-2 shadow-xs">
            <h2 class="text-lg font-semibold">Informasi Pemesanan</h2>
            <p class="text-sm text-muted-foreground">
                Akun Anda saat ini masuk sebagai <strong class="text-foreground capitalize">{{ role }}</strong>.
                Formulir pemesanan sewa mobil hanya dapat diisi oleh akun <strong>Pelanggan</strong>.
            </p>
            <div v-if="role === 'pegawai'" class="rounded-lg bg-muted p-3 text-xs text-muted-foreground">
                ℹ️ Sebagai petugas pegawai, Anda bertugas menerima notifikasi, menyetujui penugasan, dan mengantarkan mobil ke pelanggan.
            </div>
            <div class="flex gap-2 pt-2">
                <Button v-if="role === 'pegawai'" as-child size="sm">
                    <Link href="/pegawai/deliveries">Lihat Tugas Pengiriman</Link>
                </Button>
                <Button as-child variant="outline" size="sm">
                    <Link href="/vehicles">Kembali ke Katalog</Link>
                </Button>
            </div>
        </div>

        <!-- Kolom Kanan: Form Pemesanan Khusus Pelanggan -->
        <form v-else class="flex flex-col gap-4 self-start rounded-xl border p-4 lg:col-span-2" @submit.prevent="submit">
            <h2 class="font-semibold text-lg">Pesan kendaraan ini</h2>

            <p v-if="!bookable" class="rounded-md bg-muted p-3 text-sm text-muted-foreground">
                Kendaraan ini sedang tidak tersedia untuk disewa.
            </p>

            <div class="grid gap-2">
                <Label for="pickup_at">Tanggal dan jam pengambilan</Label>
                <DateTimePicker id="pickup_at" v-model="form.pickup_at" placeholder="Pilih waktu pengambilan" />
                <InputError :message="form.errors.pickup_at" />
            </div>

            <div class="grid gap-2">
                <Label for="return_at">Tanggal dan jam pengembalian (Otomatis +24 jam)</Label>
                <DateTimePicker id="return_at" v-model="form.return_at" :min="form.pickup_at" placeholder="Pilih waktu pengembalian" />
                <InputError :message="form.errors.return_at" />
            </div>

            <div class="grid gap-2">
                <Label for="destination">Tujuan Perjalanan</Label>
                <Input id="destination" v-model="form.destination" placeholder="Contoh: Wisata Malang / Keperluan Keluarga Solo" />
                <p class="text-[11px] text-muted-foreground">Kota atau rute perjalanan yang akan Anda kunjungi.</p>
                <InputError :message="form.errors.destination" />
            </div>

            <div class="grid gap-2">
                <Label for="delivery_address">Alamat Pengantaran Unit</Label>
                <textarea
                    id="delivery_address"
                    v-model="form.delivery_address"
                    :class="textareaClass"
                    placeholder="Contoh: Jl. Diponegoro No. 12, Kel. Wonokromo, Surabaya (Dekat RS Darmo)"
                />
                <p class="text-[11px] text-muted-foreground">Lokasi tempat mobil diantarkan langsung oleh tim pegawai kami ke tangan Anda.</p>
                <InputError :message="form.errors.delivery_address" />
            </div>

            <InputError :message="form.errors.vehicle_id" />

            <!-- Kotak Rincian Biaya Realtime -->
            <div v-if="estimate" class="grid gap-2 rounded-xl border bg-muted/40 p-4 text-sm">
                <div class="flex justify-between items-center">
                    <span class="text-muted-foreground">Sewa Pokok ({{ estimate.days }} hari x 24 jam)</span>
                    <span class="font-medium">{{ rupiah(estimate.basePrice) }}</span>
                </div>

                <!-- Baris Keterlambatan Per Jam -->
                <div v-if="estimate.extraHours > 0" class="flex justify-between items-center text-amber-600 dark:text-amber-400 font-medium">
                    <span>Keterlambatan (+{{ estimate.extraHours }} jam x {{ rupiah(vehicle.late_fee_per_hour) }})</span>
                    <span>+ {{ rupiah(estimate.overtimeFee) }}</span>
                </div>

                <div class="flex justify-between items-center font-bold border-t pt-2 text-base">
                    <span>Total Sewa</span>
                    <span class="text-primary">{{ rupiah(estimate.total) }}</span>
                </div>

                <div class="flex justify-between items-center text-xs text-muted-foreground border-t pt-1">
                    <span>Kewajiban Uang Muka (DP 30%)</span>
                    <span class="font-semibold text-foreground text-sm">{{ rupiah(estimate.dp) }}</span>
                </div>

                <div v-if="estimate.extraHours > 0" class="mt-1 rounded-md bg-amber-500/10 p-2 text-[11px] text-amber-700 dark:text-amber-300">
                    ⚠️ Pengembalian melebihi batas 24 jam sebanyak {{ estimate.extraHours }} jam, biaya keterlambatan langsung terkalkulasi per jam.
                </div>
            </div>

            <div class="flex gap-2">
                <Button type="submit" :disabled="form.processing || !bookable">
                    {{ form.processing ? 'Memproses...' : 'Pesan sekarang' }}
                </Button>
                <Button as-child variant="outline" type="button">
                    <Link href="/vehicles">Kembali</Link>
                </Button>
            </div>
        </form>
    </div>
</template>
