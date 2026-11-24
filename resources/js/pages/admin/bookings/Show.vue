<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatDateTime, rupiah } from '@/lib/format';
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
        description: `Apakah Anda yakin ingin MEMBATALKAN booking ${props.booking.booking_code}? Booking yang dibatalkan tidak dapat diproses lagi dan slot kendaraan akan dibebaskan.`,
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

const statusLabel: Record<string, string> = {
    pending_dp: 'Menunggu DP',
    booked: 'Terkonfirmasi',
    assigned: 'Siap Kirim',
    delivering: 'Sedang Dikirim',
    on_rent: 'Sedang Disewa',
    returned: 'Kembali (Cek Denda)',
    completed: 'Selesai',
    cancelled: 'Dibatalkan',
};

// Leaflet map init setelah DOM tersedia
const mapEl = ref<HTMLElement | null>(null);
onMounted(() => {
    const log = props.booking.location_logs?.[0];
    if (!log || !mapEl.value) return;
    // Fix default icon path issue dengan Vite
    delete (L.Icon.Default.prototype as any)._getIconUrl;
    L.Icon.Default.mergeOptions({
        iconUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-icon.png',
        iconRetinaUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-icon-2x.png',
        shadowUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-shadow.png',
    });
    const map = L.map(mapEl.value).setView([log.lat, log.lng], 15);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap',
    }).addTo(map);
    L.marker([log.lat, log.lng]).addTo(map)
        .bindPopup(`📍 Lokasi terakhir: ${log.lat}, ${log.lng}`)
        .openPopup();
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
        <!-- Top bar -->
        <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-center">
            <div>
                <div class="flex items-center gap-3">
                    <h1 class="text-2xl font-bold font-mono">{{ booking.booking_code }}</h1>
                    <Badge :variant="statusVariant[booking.status] ?? 'outline'">
                        {{ statusLabel[booking.status] ?? booking.status }}
                    </Badge>
                </div>
                <p class="text-sm text-muted-foreground mt-1">
                    Dibuat pada {{ formatDateTime(booking.created_at) }}
                </p>
            </div>
            <div class="flex items-center gap-2">
                <Button
                    v-if="!['completed', 'cancelled', 'on_rent'].includes(booking.status)"
                    variant="destructive"
                    size="sm"
                    @click="cancelBooking"
                >
                    Batalkan Booking
                </Button>
                <Button as-child variant="outline">
                    <Link href="/admin/bookings">Kembali ke Daftar</Link>
                </Button>
            </div>
        </div>

        <div class="grid gap-6 lg:grid-cols-3">
            <!-- Kolom Kiri: Informasi Pelanggan & Kendaraan -->
            <div class="flex flex-col gap-6 lg:col-span-1">
                <!-- Info Pelanggan -->
                <div class="rounded-xl border bg-card p-5 shadow-xs">
                    <h2 class="text-base font-semibold mb-3">Informasi Pelanggan</h2>
                    <div class="flex flex-col gap-2 text-sm">
                        <div>
                            <span class="text-xs text-muted-foreground block">Nama Lengkap</span>
                            <span class="font-medium">{{ booking.user?.name }}</span>
                            <Badge v-if="booking.user?.is_blacklisted" variant="destructive" class="ml-2 text-[10px]">
                                Blacklisted
                            </Badge>
                        </div>
                        <div>
                            <span class="text-xs text-muted-foreground block">Nomor HP / WhatsApp</span>
                            <span class="font-medium">{{ booking.user?.phone ?? '-' }}</span>
                        </div>
                        <div>
                            <span class="text-xs text-muted-foreground block">Email</span>
                            <span class="font-medium">{{ booking.user?.email }}</span>
                        </div>
                        <div>
                            <span class="text-xs text-muted-foreground block">Alamat Pengiriman Unit</span>
                            <span class="font-medium">{{ booking.delivery_address }}</span>
                        </div>
                        <div>
                            <span class="text-xs text-muted-foreground block">Kota / Wilayah Tujuan</span>
                            <span class="font-medium">{{ booking.destination }}</span>
                        </div>
                    </div>
                </div>

                <!-- Info Kendaraan -->
                <div class="rounded-xl border bg-card p-5 shadow-xs">
                    <h2 class="text-base font-semibold mb-3">Unit Kendaraan</h2>
                    <img
                        v-if="booking.vehicle?.photo_url"
                        :src="booking.vehicle?.photo_url"
                        :alt="booking.vehicle?.model"
                        class="aspect-video w-full rounded-lg object-cover mb-3"
                    />
                    <div class="flex flex-col gap-2 text-sm">
                        <div class="font-semibold text-base">{{ booking.vehicle?.brand }} {{ booking.vehicle?.model }}</div>
                        <div class="text-muted-foreground">Plat: <span class="font-mono text-foreground font-semibold">{{ booking.vehicle?.plate }}</span></div>
                        <div class="text-muted-foreground">Tarif: {{ rupiah(booking.price_per_day) }} / hari</div>
                        <div class="text-muted-foreground">Denda Telat: {{ rupiah(booking.vehicle?.late_fee_per_hour) }} / jam</div>
                    </div>
                </div>

                <!-- Lokasi Terakhir (GPS) -->
                <div v-if="booking.location_logs && booking.location_logs.length > 0" class="rounded-xl border bg-card p-5 shadow-xs">
                    <h2 class="text-base font-semibold mb-2">Lokasi Terakhir Pelanggan (GPS)</h2>
                    <p class="text-xs text-muted-foreground mb-3">Dicatat: {{ formatDateTime(booking.location_logs[0].recorded_at) }}</p>
                    <!-- Peta Mini Leaflet/OpenStreetMap -->
                    <div ref="mapEl" class="rounded-lg border overflow-hidden" style="height:200px;width:100%;"></div>
                    <a
                        :href="`https://www.google.com/maps?q=${booking.location_logs[0].lat},${booking.location_logs[0].lng}`"
                        target="_blank"
                        class="mt-3 inline-block text-xs font-semibold text-primary underline"
                    >
                        Buka di Google Maps ↗
                    </a>
                </div>
            </div>

            <!-- Kolom Tengah & Kanan: Rincian Jadwal, Pembayaran, Pengiriman -->
            <div class="flex flex-col gap-6 lg:col-span-2">
                <!-- Rincian Biaya & Jadwal -->
                <div class="rounded-xl border bg-card p-5 shadow-xs">
                    <h2 class="text-base font-semibold mb-4">Rincian Sewa & Biaya</h2>
                    <div class="grid grid-cols-2 gap-4 text-sm sm:grid-cols-4">
                        <div class="rounded-lg bg-muted/40 p-3">
                            <span class="text-xs text-muted-foreground block">Ambil Unit</span>
                            <span class="font-medium text-xs">{{ formatDateTime(booking.pickup_at) }}</span>
                        </div>
                        <div class="rounded-lg bg-muted/40 p-3">
                            <span class="text-xs text-muted-foreground block">Rencana Kembali</span>
                            <span class="font-medium text-xs">{{ formatDateTime(booking.return_at) }}</span>
                        </div>
                        <div class="rounded-lg bg-muted/40 p-3">
                            <span class="text-xs text-muted-foreground block">Durasi</span>
                            <span class="font-bold text-sm">
                                {{ durationBreakdown.days }} Hari
                                <span v-if="durationBreakdown.extraHours > 0" class="text-xs text-amber-600 block">
                                    +{{ durationBreakdown.extraHours }} Jam
                                </span>
                            </span>
                        </div>
                        <div class="rounded-lg bg-muted/40 p-3">
                            <span class="text-xs text-muted-foreground block">Total Sewa</span>
                            <span class="font-bold text-base text-primary">{{ rupiah(booking.total_price) }}</span>
                        </div>
                    </div>

                    <div v-if="durationBreakdown.extraHours > 0" class="mt-3 rounded-lg bg-amber-500/10 p-2.5 text-xs text-amber-700 dark:text-amber-300 flex justify-between items-center">
                        <span>Rincian Sewa: Pokok ({{ rupiah(durationBreakdown.basePrice) }}) + Lebihan {{ durationBreakdown.extraHours }} jam ({{ rupiah(durationBreakdown.overtimeFee) }})</span>
                        <span class="font-semibold">{{ rupiah(booking.total_price) }}</span>
                    </div>

                    <div v-if="Number(booking.late_fee_total) > 0" class="mt-3 rounded-lg bg-destructive/10 p-3 text-destructive text-sm flex justify-between items-center">
                        <span>Denda Telat Pengembalian Unit:</span>
                        <span class="font-bold">+{{ rupiah(booking.late_fee_total) }}</span>
                    </div>
                </div>

                <!-- Verifikasi Pembayaran -->
                <div class="rounded-xl border bg-card p-5 shadow-xs">
                    <h2 class="text-base font-semibold mb-3">Verifikasi Pembayaran</h2>
                    <div v-if="!booking.payments || booking.payments.length === 0" class="text-sm text-muted-foreground py-4 text-center border rounded-lg">
                        Pelanggan belum mengunggah bukti pembayaran.
                    </div>

                    <div v-else class="flex flex-col gap-4">
                        <div
                            v-for="pay in booking.payments"
                            :key="pay.id"
                            class="flex flex-col gap-3 rounded-lg border p-4 sm:flex-row sm:items-center sm:justify-between"
                        >
                            <div class="flex items-start gap-4">
                                <a v-if="pay.proof_url" :href="pay.proof_url" target="_blank" class="shrink-0">
                                    <img :src="pay.proof_url" alt="Bukti transfer" class="h-16 w-20 rounded-md border object-cover" />
                                </a>
                                <div>
                                    <div class="flex items-center gap-2">
                                        <span class="font-bold uppercase text-xs tracking-wider">{{ pay.type }}</span>
                                        <Badge :variant="pay.status === 'verified' ? 'default' : pay.status === 'rejected' ? 'destructive' : 'secondary'">
                                            {{ pay.status }}
                                        </Badge>
                                    </div>
                                    <div class="text-lg font-bold text-primary">{{ rupiah(pay.amount) }}</div>
                                    <div class="text-xs text-muted-foreground">
                                        Metode: {{ pay.payment_method?.label ?? '-' }} · Diunggah: {{ formatDateTime(pay.created_at) }}
                                    </div>
                                    <div v-if="pay.verified_by" class="text-xs text-muted-foreground mt-1">
                                        Diverifikasi oleh {{ pay.verified_by?.name }} ({{ formatDateTime(pay.verified_at) }})
                                    </div>
                                </div>
                            </div>

                            <div v-if="pay.status === 'pending'" class="flex gap-2 shrink-0">
                                <Button size="sm" class="bg-emerald-600 hover:bg-emerald-700 text-white" @click="verifyPayment(pay.id, 'verify')">
                                    Setujui (Verify)
                                </Button>
                                <Button size="sm" variant="destructive" @click="verifyPayment(pay.id, 'reject')">
                                    Tolak
                                </Button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Penugasan Pengiriman -->
                <div class="rounded-xl border bg-card p-5 shadow-xs">
                    <h2 class="text-base font-semibold mb-3">Penugasan Pengiriman Unit</h2>
                    <form class="flex flex-col gap-4" @submit.prevent="submitAssign">
                        <div class="grid gap-2 sm:grid-cols-2">
                            <div>
                                <Label for="employee">Pilih Pegawai Pengantar</Label>
                                <select
                                    id="employee"
                                    v-model="assignForm.employee_id"
                                    class="h-9 w-full rounded-md border border-input bg-background px-3 text-sm shadow-xs outline-none focus-visible:ring-1 focus-visible:ring-ring mt-1"
                                >
                                    <option value="">-- Pilih Pegawai --</option>
                                    <option v-for="emp in employees" :key="emp.id" :value="emp.id">
                                        {{ emp.name }} — {{ emp.active_tasks > 0 ? emp.active_tasks + ' tugas aktif' : 'Tersedia' }} ({{ emp.phone ?? '-' }})
                                    </option>
                                </select>
                            </div>
                            <div>
                                <Label for="notes">Catatan untuk Petugas</Label>
                                <Input id="notes" v-model="assignForm.notes" placeholder="Contoh: Titip kunci di satpam atau serahkan langsung" class="mt-1" />
                            </div>
                        </div>

                        <div class="flex items-center justify-between border-t pt-3">
                            <span v-if="booking.delivery" class="text-xs text-muted-foreground">
                                Status Pengiriman: <strong>{{ booking.delivery.status }}</strong>
                            </span>
                            <span v-else class="text-xs text-amber-600">
                                Belum ada pegawai yang ditugaskan.
                            </span>
                            <Button type="submit" size="sm" :disabled="assignForm.processing">
                                Simpan Penugasan
                            </Button>
                        </div>
                    </form>

                    <!-- Foto Pengiriman dari Pegawai -->
                    <div v-if="booking.delivery?.photos && booking.delivery.photos.length > 0" class="mt-5 border-t pt-4">
                        <h3 class="text-sm font-semibold mb-3">Foto Kondisi Unit dari Pegawai</h3>
                        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                            <div v-for="p in booking.delivery.photos" :key="p.id" class="rounded-lg border p-2">
                                <a :href="p.photo_url" target="_blank">
                                    <img :src="p.photo_url" class="h-24 w-full rounded object-cover" />
                                </a>
                                <div class="mt-1 text-xs font-semibold uppercase">{{ p.stage }}</div>
                                <div class="text-[11px] text-muted-foreground">{{ p.note ?? '-' }}</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Kerusakan & Denda Tambahan -->
                <div class="rounded-xl border bg-card p-5 shadow-xs">
                    <h2 class="text-base font-semibold mb-3">Pencatatan Kerusakan & Denda</h2>
                    
                    <div v-if="booking.damages && booking.damages.length > 0" class="flex flex-col gap-2 mb-4">
                        <div v-for="d in booking.damages" :key="d.id" class="flex justify-between items-center rounded-lg border p-3 text-sm">
                            <div>
                                <div class="font-medium">{{ d.description }}</div>
                                <div class="text-xs text-muted-foreground">Dinilai oleh: {{ d.assessed_by?.name }}</div>
                            </div>
                            <div class="font-bold text-destructive">{{ rupiah(d.fine_amount) }}</div>
                        </div>
                    </div>

                    <form class="grid gap-3 sm:grid-cols-3 items-end border-t pt-3" @submit.prevent="submitDamage">
                        <div class="sm:col-span-2">
                            <Label for="damage_desc">Deskripsi Kerusakan</Label>
                            <Input id="damage_desc" v-model="damageForm.description" placeholder="Contoh: Lecet bumper depan kanan" class="mt-1" />
                        </div>
                        <div>
                            <Label for="fine">Nominal Denda (Rp)</Label>
                            <Input id="fine" v-model="damageForm.fine_amount" type="number" placeholder="250000" class="mt-1" />
                        </div>
                        <div class="sm:col-span-3 flex justify-end">
                            <Button type="submit" variant="destructive" size="sm" :disabled="damageForm.processing">
                                Tambah Denda Kerusakan
                            </Button>
                        </div>
                    </form>
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
    </div>
</template>
