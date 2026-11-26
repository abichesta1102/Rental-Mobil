<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

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
    status: 'available' | 'maintenance' | 'inactive';
}

interface PageLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface Paginator {
    data: Vehicle[];
    links: PageLink[];
    total: number;
}

defineProps<{ vehicles: Paginator }>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Kendaraan', href: '/admin/vehicles' },
];

const statusLabel = {
    available: 'Tersedia',
    maintenance: 'Perawatan',
    inactive: 'Nonaktif',
} as const;

const statusVariant = {
    available: 'default',
    maintenance: 'secondary',
    inactive: 'destructive',
} as const;

function rupiah(value: string | number): string {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        maximumFractionDigits: 0,
    }).format(Number(value));
}

function remove(vehicle: Vehicle): void {
    if (confirm(`Hapus kendaraan ${vehicle.brand} ${vehicle.model} (${vehicle.plate})?`)) {
        router.delete(`/admin/vehicles/${vehicle.id}`, { preserveScroll: true });
    }
}
</script>

<template>
    <Head title="Kendaraan" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-4 p-4">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-xl font-semibold">Data Kendaraan</h1>
                    <p class="text-sm text-muted-foreground">{{ vehicles.total }} kendaraan terdaftar</p>
                </div>
                <Button as-child>
                    <Link href="/admin/vehicles/create">Tambah Kendaraan</Link>
                </Button>
            </div>

            <div class="overflow-x-auto rounded-xl border">
                <table class="w-full text-sm">
                    <thead class="bg-muted/50 text-left">
                        <tr>
                            <th class="p-3">Foto</th>
                            <th class="p-3">Kendaraan</th>
                            <th class="p-3">Plat</th>
                            <th class="p-3">Harga / hari</th>
                            <th class="p-3">Denda / jam</th>
                            <th class="p-3">Status</th>
                            <th class="p-3 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="vehicles.data.length === 0">
                            <td colspan="7" class="p-6 text-center text-muted-foreground">
                                Belum ada kendaraan. Klik "Tambah Kendaraan" untuk menambahkan.
                            </td>
                        </tr>
                        <tr v-for="vehicle in vehicles.data" :key="vehicle.id" class="border-t">
                            <td class="p-3">
                                <img
                                    v-if="vehicle.photo_url"
                                    :src="vehicle.photo_url"
                                    :alt="`${vehicle.brand} ${vehicle.model}`"
                                    class="h-12 w-20 rounded-md object-cover"
                                />
                                <div v-else class="flex h-12 w-20 items-center justify-center rounded-md bg-muted text-xs text-muted-foreground">
                                    Tanpa foto
                                </div>
                            </td>
                            <td class="p-3">
                                <div class="font-medium">{{ vehicle.brand }} {{ vehicle.model }}</div>
                                <div class="text-xs text-muted-foreground">
                                    {{ vehicle.year ?? '-' }} · {{ vehicle.transmission }} · {{ vehicle.seats }} kursi
                                </div>
                            </td>
                            <td class="p-3 font-mono">{{ vehicle.plate }}</td>
                            <td class="p-3">{{ rupiah(vehicle.price_per_day) }}</td>
                            <td class="p-3">{{ rupiah(vehicle.late_fee_per_hour) }}</td>
                            <td class="p-3">
                                <Badge :variant="statusVariant[vehicle.status]">
                                    {{ statusLabel[vehicle.status] }}
                                </Badge>
                            </td>
                            <td class="p-3">
                                <div class="flex justify-end gap-2">
                                    <Button as-child variant="outline" size="sm">
                                        <Link :href="`/admin/vehicles/${vehicle.id}/edit`">Ubah</Link>
                                    </Button>
                                    <Button variant="destructive" size="sm" @click="remove(vehicle)">
                                        Hapus
                                    </Button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <nav v-if="vehicles.links.length > 3" class="flex flex-wrap gap-1">
                <template v-for="(link, index) in vehicles.links" :key="index">
                    <Link
                        v-if="link.url"
                        :href="link.url"
                        class="rounded-md border px-3 py-1 text-sm"
                        :class="link.active ? 'bg-primary text-primary-foreground' : 'hover:bg-muted'"
                        preserve-scroll
                        v-html="link.label"
                    />
                    <span
                        v-else
                        class="rounded-md border px-3 py-1 text-sm text-muted-foreground opacity-50"
                        v-html="link.label"
                    />
                </template>
            </nav>
        </div>
    </AppLayout>
</template>