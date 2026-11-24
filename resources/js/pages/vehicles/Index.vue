<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import DateTimePicker from '@/components/DateTimePicker.vue';
import { rupiah } from '@/lib/format';

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
    late_fee_per_hour?: string;
    photo_url: string | null;
}

const props = defineProps<{
    vehicles: Vehicle[];
    filtered: boolean;
    filters: { pickup_at: string | null; return_at: string | null };
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Katalog Kendaraan', href: '/vehicles' }],
    },
});

const page = usePage();
const errors = computed(() => page.props.errors as Record<string, string>);
const role = computed(() => (page.props.auth as any)?.user?.role);

const pickupAt = ref(props.filters.pickup_at ?? '');
const returnAt = ref(props.filters.return_at ?? '');

function add24Hours(isoString: string): string {
    if (!isoString) return '';
    const date = new Date(isoString);
    if (Number.isNaN(date.getTime())) return '';
    const nextDay = new Date(date.getTime() + 24 * 60 * 60 * 1000);
    const y = nextDay.getFullYear();
    const m = String(nextDay.getMonth() + 1).padStart(2, '0');
    const d = String(nextDay.getDate()).padStart(2, '0');
    const h = String(nextDay.getHours()).padStart(2, '0');
    const min = String(nextDay.getMinutes()).padStart(2, '0');
    return `${y}-${m}-${d}T${h}:${min}`;
}

// Otomatis sesuaikan jam dan tanggal pengembalian pas 24 jam setelah pengambilan
watch(pickupAt, (newVal) => {
    if (newVal) {
        returnAt.value = add24Hours(newVal);
    }
});

function search(): void {
    router.get(
        '/vehicles',
        {
            pickup_at: pickupAt.value || undefined,
            return_at: returnAt.value || undefined,
        },
        { preserveState: true },
    );
}

function reset(): void {
    pickupAt.value = '';
    returnAt.value = '';
    router.get('/vehicles');
}

function detailUrl(vehicle: Vehicle): string {
    if (props.filtered && props.filters.pickup_at && props.filters.return_at) {
        const query = new URLSearchParams({
            pickup_at: props.filters.pickup_at,
            return_at: props.filters.return_at,
        });
        return `/vehicles/${vehicle.id}?${query.toString()}`;
    }
    return `/vehicles/${vehicle.id}`;
}
</script>

<template>
    <Head title="Katalog Kendaraan" />

    <div class="flex flex-col gap-6 p-4">
        <div>
            <h1 class="text-xl font-semibold">Katalog Kendaraan</h1>
            <p class="text-sm text-muted-foreground">
                Pilih tanggal dan jam untuk melihat kendaraan yang ready pada waktu itu.
            </p>
        </div>

        <form class="grid gap-3 rounded-xl border p-4 sm:grid-cols-[1fr_1fr_auto]" @submit.prevent="search">
            <div class="grid gap-2">
                <Label for="pickup_at">Tanggal dan jam pengambilan</Label>
                <DateTimePicker id="pickup_at" v-model="pickupAt" placeholder="Pilih waktu pengambilan" />
            </div>
            <div class="grid gap-2">
                <Label for="return_at">Tanggal dan jam pengembalian</Label>
                <DateTimePicker id="return_at" v-model="returnAt" :min="pickupAt" placeholder="Pilih waktu pengembalian" />
                <p v-if="errors.return_at" class="text-sm text-destructive">{{ errors.return_at }}</p>
            </div>
            <div class="flex items-end gap-2">
                <Button type="submit">Cari</Button>
                <Button type="button" variant="outline" @click="reset">Reset</Button>
            </div>
        </form>

        <p v-if="filtered" class="text-sm text-muted-foreground">
            {{ vehicles.length }} kendaraan ready pada rentang waktu yang dipilih.
        </p>

        <div
            v-if="vehicles.length === 0"
            class="rounded-xl border p-8 text-center text-muted-foreground"
        >
            Tidak ada kendaraan yang tersedia{{ filtered ? ' pada rentang waktu itu. Coba tanggal lain.' : '.' }}
        </div>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            <div
                v-for="vehicle in vehicles"
                :key="vehicle.id"
                class="flex flex-col overflow-hidden rounded-xl border"
            >
                <img
                    v-if="vehicle.photo_url"
                    :src="vehicle.photo_url"
                    :alt="`${vehicle.brand} ${vehicle.model}`"
                    class="aspect-video w-full object-cover"
                />
                <div
                    v-else
                    class="flex aspect-video w-full items-center justify-center bg-muted text-sm text-muted-foreground"
                >
                    Tanpa foto
                </div>

                <div class="flex flex-1 flex-col gap-3 p-4">
                    <div>
                        <div class="flex items-center justify-between gap-2">
                            <h2 class="font-semibold">{{ vehicle.brand }} {{ vehicle.model }}</h2>
                            <Badge v-if="filtered">Ready</Badge>
                        </div>
                        <p class="text-xs text-muted-foreground">
                            {{ vehicle.year ?? '-' }} · {{ vehicle.transmission }} · {{ vehicle.seats }} kursi
                        </p>
                        <p v-if="vehicle.late_fee_per_hour" class="text-[11px] text-muted-foreground mt-1">
                            Denda telat: {{ rupiah(vehicle.late_fee_per_hour) }} / jam
                        </p>
                    </div>

                    <div class="mt-auto flex items-center justify-between">
                        <div>
                            <div class="text-lg font-semibold">{{ rupiah(vehicle.price_per_day) }}</div>
                            <div class="text-xs text-muted-foreground">per hari</div>
                        </div>
                        <Button as-child size="sm">
                            <Link :href="detailUrl(vehicle)">
                                {{ role === 'pelanggan' ? 'Lihat dan pesan' : 'Lihat detail unit' }}
                            </Link>
                        </Button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
