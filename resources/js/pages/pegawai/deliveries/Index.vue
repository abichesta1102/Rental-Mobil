<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import EmptyState from '@/components/EmptyState.vue';
import PageHeader from '@/components/PageHeader.vue';
import StatusBadge from '@/components/StatusBadge.vue';
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

defineProps<{
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
</script>

<template>
    <Head title="Tugas Pengiriman" />

    <div class="flex flex-col gap-6 p-4 sm:p-6">
        <PageHeader
            title="Tugas Pengiriman & Penjemputan"
            :description="`${deliveries.total} penugasan operasional unit kendaraan.`"
        />

        <div class="overflow-x-auto rounded-[6px] border border-border bg-card">
            <table class="w-full text-left text-xs">
                <thead class="border-b border-border bg-muted/40 text-muted-foreground font-semibold">
                    <tr>
                        <th class="p-3">Kode Booking</th>
                        <th class="p-3">Kendaraan</th>
                        <th class="p-3">Pelanggan</th>
                        <th class="p-3">Alamat Pengantaran</th>
                        <th class="p-3">Status Pengiriman</th>
                        <th class="p-3 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    <tr v-if="deliveries.data.length === 0">
                        <td colspan="6" class="p-6 text-center">
                            <EmptyState
                                title="Belum ada tugas pengiriman"
                                description="Tugas pengantaran atau penjemputan unit akan muncul saat ditugaskan oleh admin."
                            />
                        </td>
                    </tr>
                    <tr v-for="d in deliveries.data" :key="d.id" class="hover:bg-muted/30 transition-colors">
                        <td class="p-3 font-mono font-medium whitespace-nowrap">
                            <Link :href="`/pegawai/deliveries/${d.id}`" class="text-primary hover:underline font-semibold">
                                {{ d.booking?.booking_code }}
                            </Link>
                        </td>
                        <td class="p-3">
                            <div class="font-medium text-foreground">{{ d.booking?.vehicle?.brand }} {{ d.booking?.vehicle?.model }}</div>
                            <div class="text-[11px] font-mono text-muted-foreground">{{ d.booking?.vehicle?.plate }}</div>
                        </td>
                        <td class="p-3">
                            <div class="font-medium text-foreground">{{ d.booking?.user?.name }}</div>
                            <div class="text-[11px] text-muted-foreground">{{ d.booking?.user?.phone ?? '-' }}</div>
                        </td>
                        <td class="p-3 text-muted-foreground max-w-xs truncate">
                            {{ d.booking?.delivery_address }}
                        </td>
                        <td class="p-3 whitespace-nowrap">
                            <StatusBadge :status="d.status" />
                        </td>
                        <td class="p-3 text-right whitespace-nowrap">
                            <Button as-child size="sm" class="h-8 min-h-[32px] text-xs">
                                <Link :href="`/pegawai/deliveries/${d.id}`">Proses</Link>
                            </Button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <nav v-if="deliveries.links.length > 3" class="flex flex-wrap gap-1">
            <template v-for="(link, index) in deliveries.links" :key="index">
                <Link
                    v-if="link.url"
                    :href="link.url"
                    class="rounded-[6px] border border-border px-3 py-1.5 text-xs font-medium min-h-[36px] flex items-center justify-center"
                    :class="link.active ? 'bg-primary text-primary-foreground border-primary' : 'bg-background hover:bg-muted text-foreground'"
                    preserve-scroll
                    v-html="link.label"
                />
                <span
                    v-else
                    class="rounded-[6px] border border-border px-3 py-1.5 text-xs text-muted-foreground opacity-40 min-h-[36px] flex items-center justify-center"
                    v-html="link.label"
                />
            </template>
        </nav>
    </div>
</template>
