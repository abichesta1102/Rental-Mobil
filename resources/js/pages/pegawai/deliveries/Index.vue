<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { formatDateTime } from '@/lib/format';

interface Delivery {
    id: number;
    status: 'assigned' | 'approved' | 'delivering' | 'delivered' | 'picked_up' | 'in_garage';
    assigned_at: string | null;
    delivered_at: string | null;
    returned_at: string | null;
    notes: string | null;
    booking: {
        id: number;
        booking_code: string;
        destination: string;
        delivery_address: string;
        pickup_at: string;
        return_at: string;
        user: { name: string; phone: string | null };
        vehicle: { brand: string; model: string; plate: string; photo_url: string | null };
    };
    photos?: any[];
}

interface PageLink {
    url: string | null;
    label: string;
    active: boolean;
}

const props = defineProps<{
    deliveries: {
        data: Delivery[];
        links: PageLink[];
        total: number;
    };
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Tugas Pengiriman', href: '/pegawai/deliveries' }],
    },
});

const statusLabel: Record<string, string> = {
    assigned: 'Ditugaskan',
    approved: 'Disetujui',
    delivering: 'Sedang Mengantar',
    delivered: 'Tiba di Pelanggan',
    picked_up: 'Diambil Kembali',
    in_garage: 'Kembali di Garasi',
};

const statusVariant: Record<string, 'default' | 'secondary' | 'outline'> = {
    assigned: 'secondary',
    approved: 'default',
    delivering: 'default',
    delivered: 'default',
    picked_up: 'outline',
    in_garage: 'outline',
};
</script>

<template>
    <Head title="Tugas Pengiriman" />

    <div class="flex flex-col gap-6 p-4">
        <div>
            <h1 class="text-xl font-semibold">Daftar Tugas Pengantaran & Penjemputan</h1>
            <p class="text-sm text-muted-foreground">
                Kelola status serah terima unit kendaraan kepada pelanggan dan unggah bukti foto kondisi unit.
            </p>
        </div>

        <div class="overflow-x-auto rounded-xl border bg-card">
            <table class="w-full text-sm">
                <thead class="bg-muted/50 text-left">
                    <tr>
                        <th class="p-3">Kode Booking</th>
                        <th class="p-3">Kendaraan</th>
                        <th class="p-3">Pelanggan & Kontak</th>
                        <th class="p-3">Alamat Kirim</th>
                        <th class="p-3">Status Pengiriman</th>
                        <th class="p-3 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="deliveries.data.length === 0">
                        <td colspan="6" class="p-8 text-center text-muted-foreground">
                            Belum ada tugas pengiriman yang ditugaskan kepada Anda.
                        </td>
                    </tr>
                    <tr v-for="d in deliveries.data" :key="d.id" class="border-t align-top">
                        <td class="p-3 font-mono font-medium">{{ d.booking?.booking_code }}</td>
                        <td class="p-3">
                            <div class="font-semibold">{{ d.booking?.vehicle?.brand }} {{ d.booking?.vehicle?.model }}</div>
                            <div class="text-xs font-mono text-muted-foreground">{{ d.booking?.vehicle?.plate }}</div>
                        </td>
                        <td class="p-3">
                            <div class="font-medium">{{ d.booking?.user?.name }}</div>
                            <div class="text-xs text-muted-foreground">{{ d.booking?.user?.phone ?? '-' }}</div>
                        </td>
                        <td class="p-3 text-xs max-w-xs">{{ d.booking?.delivery_address }}</td>
                        <td class="p-3">
                            <Badge :variant="statusVariant[d.status] ?? 'outline'">
                                {{ statusLabel[d.status] ?? d.status }}
                            </Badge>
                        </td>
                        <td class="p-3 text-right">
                            <Button as-child size="sm">
                                <Link :href="`/pegawai/deliveries/${d.id}`">Proses</Link>
                            </Button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
