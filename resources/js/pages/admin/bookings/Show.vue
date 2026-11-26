<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import EmptyState from '@/components/EmptyState.vue';
import InputError from '@/components/InputError.vue';
import PageHeader from '@/components/PageHeader.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { formatDateTime, rupiah } from '@/lib/format';
import { getStatusLabel } from '@/lib/status';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

interface Props {
    booking: any;
    employees: Array<{ id: number; name: string; phone?: string; active_tasks: number }>;
}

const props = defineProps<Props>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Data Booking', href: '/admin/bookings' },
            { title: 'Detail Booking', href: '#' },
        ],
    },
});

const assignForm = useForm({
    employee_id: props.booking.delivery?.employee_id ?? '',
    notes: props.booking.delivery?.notes ?? '',
});

function submitAssign(): void {
    assignForm.post(`/admin/bookings/${props.booking.id}/assign-delivery`);
}

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

// Dialog Konfirmasi State
const confirmOpen = ref(false);
const confirmTitle = ref('');
const confirmDescription = ref('');
const confirmVariant = ref<'default' | 'destructive' | 'secondary' | 'outline'>('default');
const confirmText = ref('Lanjutkan');
let confirmActionCallback: (() => void) | null = null;

function requestConfirm(options: {
    title: string;
    description: string;
    variant?: 'default' | 'destructive';
    confirmText?: string;
    onConfirm: () => void;
}): void {
    confirmTitle.value = options.title;
    confirmDescription.value = options.description;
    confirmVariant.value = options.variant ?? 'default';
    confirmText.value = options.confirmText ?? 'Lanjutkan';
    confirmActionCallback = options.onConfirm;
    confirmOpen.value = true;
}

function handleConfirmedAction(): void {
    confirmOpen.value = false;
    if (confirmActionCallback) {
        confirmActionCallback();
        confirmActionCallback = null;
    }
}

function verifyPayment(paymentId: number, action: 'verify' | 'reject'): void {
    requestConfirm({
        title: action === 'verify' ? 'Setujui Pembayaran' : 'Tolak Pembayaran',
        description: `Apakah Anda yakin ingin ${action === 'verify' ? 'MENYETUJUI' : 'MENOLAK'} pembayaran ini? Tindakan ini akan memperbarui status transaksi pelanggan.`,
        variant: action === 'verify' ? 'default' : 'destructive',
        confirmText: action === 'verify' ? 'Ya, Setujui' : 'Ya, Tolak',
        onConfirm: () => {
            useForm({ action }).post(`/admin/payments/${paymentId}/verify`);
        },
    });
}

function cancelBooking(): void {
    requestConfirm({
        title: 'Batalkan Booking',
        description: `Apakah Anda yakin ingin MEMBATALKAN booking ${props.booking.booking_code}? Booking yang dibatalkan tidak dapat diproses lagi dan unit kendaraan akan dibebaskan.`,
        variant: 'destructive',
        confirmText: 'Ya, Batalkan Booking',
        onConfirm: () => {
            useForm({}).post(`/admin/bookings/${props.booking.id}/cancel`);
        },
    });
}

const damageForm = useForm({
    description: '',
    fine_amount: '',
});

function submitDamage(): void {
    damageForm.post(`/admin/bookings/${props.booking.id}/damages`, {
        onSuccess: () => damageForm.reset(),
    });
}

// Leaflet map init tanpa CDN eksternal (menggunakan divIcon lokal)
const mapEl = ref<HTMLElement | null>(null);
onMounted(() => {
    const log = props.booking.location_logs?.[0];
    if (!log || !mapEl.value) return;

    const map = L.map(mapEl.value).setView([log.lat, log.lng], 15);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap',
    }).addTo(map);

    const pinIcon = L.divIcon({
        className: 'custom-map-marker',
        html: `<div style="width:14px;height:14px;background:#c2410c;border:2px solid #ffffff;border-radius:50%;box-shadow:0 1px 3px rgba(0,0,0,0.3);"></div>`,
        iconSize: [14, 14],
        iconAnchor: [7, 7],
    });

    L.marker([log.lat, log.lng], { icon: pinIcon }).addTo(map)
        .bindPopup(`Titik Lokasi Terakhir: ${log.lat}, ${log.lng}`)
        .openPopup();
});
</script>

<template>
    <Head :title="`Booking ${booking.booking_code}`" />

    <div class="flex flex-col gap-6 p-4 sm:p-6">
        <PageHeader
            :title="`Booking ${booking.booking_code}`"
            :description="`Dibuat pada ${formatDateTime(booking.created_at)} &middot; Status: ${getStatusLabel(booking.status)}`"
        >
            <template #actions>
                <StatusBadge :status="booking.status" class="mr-2" />
                <Button
                    v-if="!['completed', 'cancelled', 'on_rent'].includes(booking.status)"
                    variant="destructive"
                    size="sm"
                    class="h-9 min-h-[36px]"
                    @click="cancelBooking"
                >
                    Batalkan Booking
                </Button>
                <Button as-child variant="outline" size="sm" class="h-9 min-h-[36px]">
                    <Link href="/admin/bookings">Kembali ke Daftar</Link>
                </Button>
            </template>
        </PageHeader>

        <div class="grid gap-6 lg:grid-cols-3">
            <!-- Kolom Kiri: Informasi Pelanggan & Kendaraan -->
            <div class="flex flex-col gap-6 lg:col-span-1">
                <!-- Info Pelanggan -->
                <div class="rounded-[6px] border border-border bg-card p-4">
                    <h2 class="text-sm font-semibold tracking-tight text-foreground mb-3 pb-2 border-b border-border">
                        Informasi Pelanggan
                    </h2>
                    <div class="space-y-2.5 text-xs">
                        <div>
                            <span class="text-muted-foreground block text-[11px]">Nama Lengkap</span>
                            <div class="flex items-center gap-2 mt-0.5">
                                <span class="font-medium text-foreground">{{ booking.user?.name }}</span>
                                <StatusBadge v-if="booking.user?.is_blacklisted" status="blacklisted" class="scale-90 origin-left" />
                            </div>
                        </div>
                        <div>
                            <span class="text-muted-foreground block text-[11px]">Nomor Telepon</span>
                            <span class="font-medium text-foreground">{{ booking.user?.phone ?? '-' }}</span>
                        </div>
                        <div>
                            <span class="text-muted-foreground block text-[11px]">Email</span>
                            <span class="font-medium text-foreground">{{ booking.user?.email }}</span>
                        </div>
                        <div>
                            <span class="text-muted-foreground block text-[11px]">Alamat Pengiriman Unit</span>
                            <span class="font-medium text-foreground leading-normal">{{ booking.delivery_address }}</span>
                        </div>
                        <div>
                            <span class="text-muted-foreground block text-[11px]">Kota / Wilayah Tujuan</span>
                            <span class="font-medium text-foreground">{{ booking.destination }}</span>
                        </div>
                    </div>
                </div>

                <!-- Info Kendaraan -->
                <div class="rounded-[6px] border border-border bg-card p-4">
                    <h2 class="text-sm font-semibold tracking-tight text-foreground mb-3 pb-2 border-b border-border">
                        Unit Kendaraan
                    </h2>
                    <img
                        v-if="booking.vehicle?.photo_url"
                        :src="booking.vehicle?.photo_url"
                        :alt="booking.vehicle?.model"
                        class="aspect-video w-full rounded-[4px] object-cover border border-border mb-3"
                    />
                    <div class="space-y-2 text-xs">
                        <div class="flex justify-between">
                            <span class="text-muted-foreground">Unit:</span>
                            <span class="font-medium text-foreground">{{ booking.vehicle?.brand }} {{ booking.vehicle?.model }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-muted-foreground">Plat Nomor:</span>
                            <span class="font-mono font-medium text-foreground">{{ booking.vehicle?.plate }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-muted-foreground">Tarif Sewa:</span>
                            <span class="font-medium text-foreground">{{ rupiah(pricePerDay) }} / hari</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-muted-foreground">Denda Telat:</span>
                            <span class="font-medium text-foreground">{{ rupiah(lateFeePerHour) }} / jam</span>
                        </div>
                    </div>
                </div>

                <!-- Penugasan Pegawai Pengirim -->
                <div class="rounded-[6px] border border-border bg-card p-4">
                    <h2 class="text-sm font-semibold tracking-tight text-foreground mb-3 pb-2 border-b border-border">
                        Petugas Pengiriman
                    </h2>

                    <form class="space-y-3" @submit.prevent="submitAssign">
                        <div>
                            <Label for="employee" class="text-xs">Pilih Pegawai</Label>
                            <select
                                id="employee"
                                v-model="assignForm.employee_id"
                                class="h-10 min-h-[40px] w-full rounded-[6px] border border-input bg-background px-3 text-xs outline-none focus-visible:ring-[2px] focus-visible:ring-ring mt-1"
                            >
                                <option value="" disabled>-- Pilih Pegawai --</option>
                                <option
                                    v-for="emp in employees"
                                    :key="emp.id"
                                    :value="emp.id"
                                >
                                    {{ emp.name }} ({{ emp.active_tasks }} tugas aktif)
                                </option>
                            </select>
                            <InputError :message="assignForm.errors.employee_id" />
                        </div>

                        <div>
                            <Label for="notes" class="text-xs">Catatan Instruksi</Label>
                            <Input
                                id="notes"
                                v-model="assignForm.notes"
                                placeholder="Contoh: Unit siap kirim pukul 08.00 WIB"
                                class="h-10 min-h-[40px] text-xs mt-1"
                            />
                            <InputError :message="assignForm.errors.notes" />
                        </div>

                        <Button
                            type="submit"
                            size="sm"
                            class="w-full h-10 min-h-[40px]"
                            :disabled="assignForm.processing"
                        >
                            <Spinner v-if="assignForm.processing" class="mr-2" />
                            {{ booking.delivery ? 'Perbarui Petugas' : 'Tugaskan Pegawai' }}
                        </Button>
                    </form>
                </div>
            </div>

            <!-- Kolom Kanan: Rincian Keuangan, Pembayaran, Pelacakan -->
            <div class="flex flex-col gap-6 lg:col-span-2">
                <!-- Rincian Biaya -->
                <div class="rounded-[6px] border border-border bg-card p-4">
                    <h2 class="text-sm font-semibold tracking-tight text-foreground mb-3 pb-2 border-b border-border">
                        Rincian Durasi & Biaya Sewa
                    </h2>
                    <div class="grid gap-3 sm:grid-cols-2 text-xs">
                        <div>
                            <span class="text-muted-foreground block text-[11px]">Waktu Pengambilan</span>
                            <span class="font-medium text-foreground">{{ formatDateTime(booking.pickup_at) }}</span>
                        </div>
                        <div>
                            <span class="text-muted-foreground block text-[11px]">Waktu Pengembalian Dijadwalkan</span>
                            <span class="font-medium text-foreground">{{ formatDateTime(booking.return_at) }}</span>
                        </div>
                    </div>

                    <div class="mt-4 pt-3 border-t border-border space-y-2 text-xs">
                        <div class="flex justify-between">
                            <span class="text-muted-foreground">Durasi Dasar:</span>
                            <span>{{ durationBreakdown.days }} Hari &times; {{ rupiah(pricePerDay) }}</span>
                        </div>
                        <div v-if="durationBreakdown.extraHours > 0" class="flex justify-between text-amber-700 dark:text-amber-400">
                            <span>Kelebihan Jam:</span>
                            <span>{{ durationBreakdown.extraHours }} Jam &times; {{ rupiah(lateFeePerHour) }}</span>
                        </div>
                        <div v-if="Number(booking.late_fee_total) > 0" class="flex justify-between text-destructive">
                            <span>Denda Keterlambatan Real:</span>
                            <span>{{ rupiah(booking.late_fee_total) }}</span>
                        </div>
                        <div class="flex justify-between font-semibold text-sm pt-2 border-t border-border text-foreground">
                            <span>Total Tagihan:</span>
                            <span class="text-primary">{{ rupiah(booking.total_price) }}</span>
                        </div>
                    </div>
                </div>

                <!-- Pembayaran -->
                <div class="rounded-[6px] border border-border bg-card p-4">
                    <h2 class="text-sm font-semibold tracking-tight text-foreground mb-3 pb-2 border-b border-border">
                        Riwayat Pembayaran
                    </h2>

                    <div v-if="!booking.payments || booking.payments.length === 0" class="p-4 text-center">
                        <EmptyState
                            title="Belum ada pembayaran"
                            description="Pelanggan belum mengunggah bukti pembayaran untuk booking ini."
                        />
                    </div>

                    <div v-else class="overflow-x-auto">
                        <table class="w-full text-left text-xs">
                            <thead class="border-b border-border bg-muted/40 text-muted-foreground font-semibold">
                                <tr>
                                    <th class="p-2.5">Tipe</th>
                                    <th class="p-2.5">Jumlah</th>
                                    <th class="p-2.5">Metode</th>
                                    <th class="p-2.5">Bukti</th>
                                    <th class="p-2.5">Status</th>
                                    <th class="p-2.5 text-right">Aksi Verifikasi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border">
                                <tr v-for="pay in booking.payments" :key="pay.id" class="hover:bg-muted/30">
                                    <td class="p-2.5 uppercase font-medium">{{ pay.type }}</td>
                                    <td class="p-2.5 font-medium whitespace-nowrap">{{ rupiah(pay.amount) }}</td>
                                    <td class="p-2.5 text-muted-foreground">{{ pay.payment_method?.label ?? '-' }}</td>
                                    <td class="p-2.5">
                                        <a
                                            v-if="pay.proof_url"
                                            :href="pay.proof_url"
                                            target="_blank"
                                            class="text-primary hover:underline font-medium text-xs"
                                        >
                                            Lihat Bukti
                                        </a>
                                        <span v-else class="text-muted-foreground">-</span>
                                    </td>
                                    <td class="p-2.5 whitespace-nowrap">
                                        <StatusBadge :status="pay.status" />
                                    </td>
                                    <td class="p-2.5 text-right whitespace-nowrap">
                                        <div v-if="pay.status === 'pending'" class="flex justify-end gap-1.5">
                                            <Button
                                                size="sm"
                                                class="h-8 text-xs bg-emerald-700 hover:bg-emerald-800 text-white"
                                                @click="verifyPayment(pay.id, 'verify')"
                                            >
                                                Setujui
                                            </Button>
                                            <Button
                                                variant="destructive"
                                                size="sm"
                                                class="h-8 text-xs"
                                                @click="verifyPayment(pay.id, 'reject')"
                                            >
                                                Tolak
                                            </Button>
                                        </div>
                                        <span v-else class="text-[11px] text-muted-foreground">
                                            {{ pay.status === 'verified' ? 'Telah diverifikasi' : 'Ditolak' }}
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Kerusakan & Denda Tambahan -->
                <div class="rounded-[6px] border border-border bg-card p-4">
                    <h2 class="text-sm font-semibold tracking-tight text-foreground mb-3 pb-2 border-b border-border">
                        Catatan Kerusakan & Denda
                    </h2>

                    <div v-if="booking.damages && booking.damages.length > 0" class="mb-4 divide-y divide-border rounded-[6px] border border-border">
                        <div v-for="dmg in booking.damages" :key="dmg.id" class="p-3 text-xs flex justify-between items-center">
                            <div>
                                <div class="font-medium text-foreground">{{ dmg.description }}</div>
                                <div class="text-[11px] text-muted-foreground">Dicatat oleh: {{ dmg.assessed_by_user?.name ?? 'Pegawai' }}</div>
                            </div>
                            <span class="font-medium text-destructive">{{ rupiah(dmg.fine_amount) }}</span>
                        </div>
                    </div>

                    <form class="grid gap-3 sm:grid-cols-3" @submit.prevent="submitDamage">
                        <div class="sm:col-span-2">
                            <Label for="damage_desc" class="text-xs">Deskripsi Kerusakan</Label>
                            <Input
                                id="damage_desc"
                                v-model="damageForm.description"
                                placeholder="Contoh: Lecet bemper depan sebelah kanan"
                                class="h-10 min-h-[40px] text-xs mt-1"
                            />
                            <InputError :message="damageForm.errors.description" />
                        </div>
                        <div>
                            <Label for="fine_amount" class="text-xs">Nominal Denda (Rp)</Label>
                            <Input
                                id="fine_amount"
                                v-model="damageForm.fine_amount"
                                type="number"
                                placeholder="500000"
                                class="h-10 min-h-[40px] text-xs mt-1"
                            />
                            <InputError :message="damageForm.errors.fine_amount" />
                        </div>
                        <div class="sm:col-span-3">
                            <Button
                                type="submit"
                                size="sm"
                                class="h-10 min-h-[40px]"
                                :disabled="damageForm.processing"
                            >
                                <Spinner v-if="damageForm.processing" class="mr-2" />
                                Catat Denda Kerusakan
                            </Button>
                        </div>
                    </form>
                </div>

                <!-- Peta Pelacakan Lokasi -->
                <div class="rounded-[6px] border border-border bg-card p-4">
                    <h2 class="text-sm font-semibold tracking-tight text-foreground mb-3 pb-2 border-b border-border">
                        Pelacakan Lokasi Terakhir
                    </h2>
                    <div
                        v-if="booking.location_logs && booking.location_logs.length > 0"
                        ref="mapEl"
                        class="h-64 w-full rounded-[6px] border border-border"
                    ></div>
                    <div v-else class="p-6 text-center">
                        <EmptyState
                            title="Belum ada data GPS"
                            description="Titik lokasi akan terekam secara berkala saat kendaraan berstatus sedang disewa (on_rent)."
                        />
                    </div>
                </div>
            </div>
        </div>
    </div>

    <ConfirmDialog
        :open="confirmOpen"
        :title="confirmTitle"
        :description="confirmDescription"
        :variant="confirmVariant"
        :confirm-text="confirmText"
        @update:open="confirmOpen = $event"
        @confirm="handleConfirmedAction"
    />
</template>
