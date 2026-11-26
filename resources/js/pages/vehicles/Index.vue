<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { Car, Filter, RotateCcw } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import DateTimePicker from '@/components/DateTimePicker.vue';
import EmptyState from '@/components/EmptyState.vue';
import PageHeader from '@/components/PageHeader.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { Button } from '@/components/ui/button';
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
    status?: string;
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

    <div class="flex flex-col gap-6 p-4 sm:p-6 max-w-6xl mx-auto w-full">
        <PageHeader
            title="Katalog Kendaraan"
            description="Pilih tanggal dan jam sewa untuk mengecek ketersediaan unit mobil yang siap digunakan."
        >
            <template #actions>
                <Button v-if="role === 'admin'" as-child size="sm">
                    <Link href="/admin/vehicles/create">+ Tambah Kendaraan</Link>
                </Button>
            </template>
        </PageHeader>

        <!-- Filter Bar Ketersediaan -->
        <div class="rounded-[6px] border border-border bg-card p-4 sm:p-5">
            <h2 class="text-xs font-semibold uppercase tracking-wider text-muted-foreground mb-3">
                Cek Ketersediaan Tanggal & Jam
            </h2>
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <div>
                    <label class="block text-xs font-medium text-foreground mb-1">Waktu Ambil</label>
                    <DateTimePicker v-model="pickupAt" />
                    <p v-if="errors.pickup_at" class="mt-1 text-xs text-destructive">
                        {{ errors.pickup_at }}
                    </p>
                </div>
                <div>
                    <label class="block text-xs font-medium text-foreground mb-1">Waktu Kembali</label>
                    <DateTimePicker v-model="returnAt" />
                    <p v-if="errors.return_at" class="mt-1 text-xs text-destructive">
                        {{ errors.return_at }}
                    </p>
                </div>
                <div class="flex items-end gap-2 sm:col-span-2 lg:col-span-1">
                    <Button class="flex-1 h-10 min-h-[40px]" @click="search">
                        <Filter class="mr-1.5 size-4" />
                        Cek Ketersediaan
                    </Button>
                    <Button
                        v-if="filtered"
                        variant="outline"
                        class="h-10 min-h-[40px]"
                        @click="reset"
                        title="Reset filter"
                    >
                        <RotateCcw class="size-4" />
                    </Button>
                </div>
            </div>
        </div>

        <!-- Grid Kendaraan -->
        <div v-if="vehicles.length === 0" class="rounded-[6px] border border-border bg-card p-8">
            <EmptyState
                title="Tidak ada kendaraan tersedia"
                :description="filtered
                    ? 'Semua kendaraan sedang disewa pada rentang tanggal tersebut. Coba pilih tanggal atau jam yang lain.'
                    : 'Belum ada kendaraan yang terdaftar dalam katalog saat ini.'"
                :icon="Car"
            >
                <Button v-if="filtered" variant="outline" size="sm" class="mt-2" @click="reset">
                    Tampilkan Semua Unit
                </Button>
            </EmptyState>
        </div>

        <div v-else class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <div
                v-for="vehicle in vehicles"
                :key="vehicle.id"
                class="flex flex-col justify-between rounded-[6px] border border-border bg-card overflow-hidden hover:border-border/80 transition-colors"
            >
                <div>
                    <!-- Foto Kendaraan -->
                    <div class="relative aspect-video w-full bg-muted overflow-hidden">
                        <img
                            v-if="vehicle.photo_url"
                            :src="vehicle.photo_url"
                            :alt="`${vehicle.brand} ${vehicle.model}`"
                            class="h-full w-full object-cover"
                            loading="lazy"
                        />
                        <div v-else class="flex h-full w-full items-center justify-center text-xs text-muted-foreground">
                            Tanpa Foto
                        </div>
                        <div class="absolute top-2 right-2">
                            <StatusBadge :status="vehicle.status ?? 'available'" />
                        </div>
                    </div>

                    <!-- Spesifikasi -->
                    <div class="p-4 space-y-2">
                        <div class="flex items-baseline justify-between">
                            <h3 class="text-base font-semibold tracking-tight text-foreground">
                                {{ vehicle.brand }} {{ vehicle.model }}
                            </h3>
                            <span class="text-xs font-mono text-muted-foreground">{{ vehicle.plate }}</span>
                        </div>

                        <div class="text-xs text-muted-foreground flex items-center gap-2">
                            <span>{{ vehicle.year ?? '-' }}</span>
                            <span>&middot;</span>
                            <span class="capitalize">{{ vehicle.transmission }}</span>
                            <span>&middot;</span>
                            <span>{{ vehicle.seats }} Kursi</span>
                        </div>

                        <div class="pt-3 border-t border-border flex items-baseline justify-between">
                            <div>
                                <span class="text-lg font-bold text-primary">{{ rupiah(vehicle.price_per_day) }}</span>
                                <span class="text-xs text-muted-foreground"> / hari</span>
                            </div>
                            <span v-if="vehicle.late_fee_per_hour" class="text-[11px] text-muted-foreground">
                                Denda: {{ rupiah(vehicle.late_fee_per_hour) }}/jam
                            </span>
                        </div>
                    </div>
                </div>

                <div class="p-4 pt-0">
                    <Button as-child class="w-full h-10 min-h-[40px] text-sm">
                        <Link :href="detailUrl(vehicle)">
                            {{ filtered ? 'Pesan Sekarang' : 'Lihat Detail & Jadwal' }}
                        </Link>
                    </Button>
                </div>
            </div>
        </div>
    </div>
</template>
