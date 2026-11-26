<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import {
    Camera,
    Check,
    Clock,
    FileText,
    MapPin,
    Navigation,
    Phone,
    ShieldAlert,
    Truck,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import InputError from '@/components/InputError.vue';
import PageHeader from '@/components/PageHeader.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { formatDateTime, rupiah } from '@/lib/format';
import { getStatusLabel } from '@/lib/status';

interface Props {
    delivery: any;
}

const props = defineProps<Props>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Tugas Pengiriman', href: '/pegawai/deliveries' },
            { title: 'Detail Tugas', href: '#' },
        ],
    },
});

const confirmOpen = ref(false);
const pendingStatus = ref<string>('');

const stages = [
    { key: 'assigned', title: 'Ditugaskan' },
    { key: 'approved', title: 'Disetujui' },
    { key: 'delivering', title: 'Mengantar' },
    { key: 'delivered', title: 'Diterima' },
    { key: 'picked_up', title: 'Dijemput' },
    { key: 'in_garage', title: 'Di Garasi' },
];

const isDpVerified = computed(() => {
    return (props.delivery.booking?.payments ?? []).some(
        (p: any) => p.type === 'dp' && p.status === 'verified'
    );
});

const isBookingCompleted = computed(() => {
    return props.delivery.booking?.status === 'completed';
});

function isStageCompleted(stageKey: string): boolean {
    const order = ['assigned', 'approved', 'delivering', 'delivered', 'picked_up', 'in_garage'];
    const currentIndex = order.indexOf(props.delivery.status);
    const stageIndex = order.indexOf(stageKey);
    return stageIndex <= currentIndex;
}

function updateStatus(newStatus: string): void {
    pendingStatus.value = newStatus;
    confirmOpen.value = true;
}

function handleConfirmStatus(): void {
    if (pendingStatus.value) {
        useForm({ status: pendingStatus.value }).post(`/pegawai/deliveries/${props.delivery.id}/status`, {
            onFinish: () => {
                confirmOpen.value = false;
                pendingStatus.value = '';
            },
        });
    }
}

const photoPreview = ref<string | null>(null);
const photoForm = useForm({
    stage: 'kirim',
    photo: null as File | null,
    note: '',
});

function onPhotoChange(e: Event): void {
    const input = e.target as HTMLInputElement;
    const file = input.files?.[0] ?? null;
    photoForm.photo = file;
    if (file) {
        photoPreview.value = URL.createObjectURL(file);
    } else {
        photoPreview.value = null;
    }
}

function submitPhoto(): void {
    photoForm.post(`/pegawai/deliveries/${props.delivery.id}/photos`, {
        forceFormData: true,
        onSuccess: () => {
            photoForm.reset();
            photoPreview.value = null;
        },
    });
}

const damageForm = useForm({
    description: '',
    fine_amount: '',
});

function submitDamage(): void {
    damageForm.post(`/pegawai/deliveries/${props.delivery.id}/damages`, {
        onSuccess: () => damageForm.reset(),
    });
}
</script>

<template>
    <Head :title="`Pengiriman ${delivery.booking?.booking_code}`" />

    <div class="flex flex-col gap-6 p-4 sm:p-6 max-w-5xl mx-auto w-full">
        <PageHeader
            :title="`Tugas: ${delivery.booking?.booking_code}`"
            :description="`Status pengiriman: ${getStatusLabel(delivery.status)}`"
        >
            <template #actions>
                <StatusBadge :status="delivery.status" class="mr-2" />
                <Button as-child variant="outline" size="sm" class="h-9 min-h-[36px]">
                    <Link href="/pegawai/deliveries">Kembali ke Daftar</Link>
                </Button>
            </template>
        </PageHeader>

        <!-- Progres Tahapan Pengiriman (responsif di layar HP 375px) -->
        <div class="rounded-[6px] border border-border bg-card p-4">
            <h2 class="text-xs font-semibold uppercase tracking-wider text-muted-foreground mb-3">
                Tahapan Pengantaran & Penjemputan
            </h2>
            <div class="grid grid-cols-2 gap-2 sm:grid-cols-6">
                <div
                    v-for="(stage, idx) in stages"
                    :key="stage.key"
                    class="flex flex-col items-center justify-center rounded-[6px] border p-2 text-center text-xs transition-colors min-h-[52px]"
                    :class="[
                        delivery.status === stage.key
                            ? 'border-primary bg-primary/10 font-bold text-primary ring-1 ring-primary'
                            : isStageCompleted(stage.key)
                            ? 'border-emerald-600/30 bg-emerald-50 text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300'
                            : 'border-border bg-muted/40 text-muted-foreground opacity-60'
                    ]"
                >
                    <span class="text-[10px] text-muted-foreground">Tahap {{ idx + 1 }}</span>
                    <span class="font-medium mt-0.5">{{ stage.title }}</span>
                    <span v-if="delivery.status === stage.key" class="text-[10px] font-bold text-primary mt-0.5">Sedang Aktif</span>
                    <span v-else-if="isStageCompleted(stage.key)" class="text-[10px] font-medium text-emerald-700 dark:text-emerald-400 mt-0.5">Selesai</span>
                </div>
            </div>
        </div>

        <!-- Tombol Aksi Status Tahapan (Target Sentuh Minimal 40px) -->
        <div class="rounded-[6px] border border-border bg-card p-4 sm:p-5">
            <h2 class="text-sm font-semibold tracking-tight text-foreground mb-1">
                Tindakan Status Lapangan
            </h2>
            <p class="text-xs text-muted-foreground mb-4">
                Klik tombol di bawah ini sesuai tindakan fisik yang dilakukan di lapangan.
            </p>
            <div class="flex flex-col sm:flex-row flex-wrap gap-2.5">
                <Button
                    v-if="delivery.status === 'assigned'"
                    class="w-full sm:w-auto h-11 min-h-[44px] px-5 text-sm"
                    @click="updateStatus('approved')"
                >
                    <Check class="mr-2 size-4" />
                    Setujui Tugas Pengiriman
                </Button>

                <Button
                    v-if="delivery.status === 'approved'"
                    class="w-full sm:w-auto h-11 min-h-[44px] px-5 text-sm"
                    @click="updateStatus('delivering')"
                >
                    <Truck class="mr-2 size-4" />
                    Berangkat Mengantar Unit ke Pelanggan
                </Button>

                <template v-if="delivery.status === 'delivering'">
                    <Button
                        v-if="isDpVerified"
                        class="w-full sm:w-auto h-11 min-h-[44px] px-5 text-sm bg-emerald-700 hover:bg-emerald-800 text-white"
                        @click="updateStatus('delivered')"
                    >
                        <Check class="mr-2 size-4" />
                        Konfirmasi Unit Diterima Pelanggan (Sewa Dimulai)
                    </Button>
                    <div
                        v-else
                        class="flex items-center gap-2 rounded-[6px] border border-amber-600/30 bg-amber-50 dark:bg-amber-950/40 px-4 py-2.5 text-xs font-semibold text-amber-800 dark:text-amber-300 w-full sm:w-auto min-h-[44px]"
                    >
                        <Clock class="size-4 shrink-0 text-amber-600" />
                        <span>Menunggu verifikasi pembayaran DP oleh admin sebelum serah terima.</span>
                    </div>
                </template>

                <Button
                    v-if="delivery.status === 'delivered'"
                    class="w-full sm:w-auto h-11 min-h-[44px] px-5 text-sm"
                    @click="updateStatus('picked_up')"
                >
                    <Navigation class="mr-2 size-4" />
                    Mulai Penjemputan / Ambil Mobil Kembali
                </Button>

                <template v-if="delivery.status === 'picked_up'">
                    <Button
                        v-if="isBookingCompleted"
                        class="w-full sm:w-auto h-11 min-h-[44px] px-5 text-sm"
                        @click="updateStatus('in_garage')"
                    >
                        <Check class="mr-2 size-4" />
                        Konfirmasi Unit Tiba di Garasi
                    </Button>
                    <div
                        v-else
                        class="flex items-center gap-2 rounded-[6px] border border-blue-600/30 bg-blue-50 dark:bg-blue-950/40 px-4 py-2.5 text-xs font-semibold text-blue-800 dark:text-blue-300 w-full sm:w-auto min-h-[44px]"
                    >
                        <Clock class="size-4 shrink-0 text-blue-600" />
                        <span>Tunggu pelunasan sisa sewa diverifikasi oleh admin sebelum selesai garasi.</span>
                    </div>
                </template>

                <div
                    v-if="delivery.status === 'in_garage'"
                    class="flex items-center gap-2 text-xs font-semibold text-emerald-700 dark:text-emerald-400 py-1"
                >
                    <Check class="size-4" />
                    <span>Tugas pengantaran dan penjemputan unit telah selesai seluruhnya.</span>
                </div>
            </div>
        </div>

        <div class="grid gap-6 lg:grid-cols-2">
            <!-- Informasi Pelanggan & Unit -->
            <div class="flex flex-col gap-6">
                <div class="rounded-[6px] border border-border bg-card p-4">
                    <h2 class="text-sm font-semibold tracking-tight text-foreground mb-3 pb-2 border-b border-border">
                        Detail Pelanggan & Alamat Pengiriman
                    </h2>
                    <div class="space-y-3 text-xs">
                        <div>
                            <span class="text-muted-foreground block text-[11px]">Nama Penyewa</span>
                            <span class="font-semibold text-sm text-foreground">{{ delivery.booking?.user?.name }}</span>
                        </div>
                        <div>
                            <span class="text-muted-foreground block text-[11px]">Nomor Telepon</span>
                            <a
                                :href="`https://wa.me/${delivery.booking?.user?.phone}`"
                                target="_blank"
                                class="inline-flex items-center gap-1.5 font-medium text-primary hover:underline py-1 min-h-[36px]"
                            >
                                <Phone class="size-3.5" />
                                <span>{{ delivery.booking?.user?.phone ?? '-' }}</span>
                            </a>
                        </div>
                        <div>
                            <span class="text-muted-foreground block text-[11px]">Alamat Pengantaran</span>
                            <p class="font-medium text-foreground leading-normal mt-0.5">
                                {{ delivery.booking?.delivery_address }}
                            </p>
                            <a
                                :href="`https://www.google.com/maps/dir/?api=1&destination=${encodeURIComponent(delivery.booking?.delivery_address ?? '')}`"
                                target="_blank"
                                class="mt-2 inline-flex items-center gap-1.5 rounded-[6px] border border-border bg-muted/60 px-3 py-2 text-xs font-medium text-foreground hover:bg-muted min-h-[40px]"
                            >
                                <MapPin class="size-4 text-primary" />
                                <span>Buka Navigasi Rute Maps</span>
                            </a>
                        </div>
                        <div v-if="delivery.notes" class="pt-2 border-t border-border">
                            <span class="text-muted-foreground block text-[11px]">Catatan Admin</span>
                            <p class="font-medium text-foreground mt-0.5">{{ delivery.notes }}</p>
                        </div>
                    </div>
                </div>

                <div class="rounded-[6px] border border-border bg-card p-4">
                    <h2 class="text-sm font-semibold tracking-tight text-foreground mb-3 pb-2 border-b border-border">
                        Unit Kendaraan
                    </h2>
                    <div class="flex flex-col sm:flex-row gap-3">
                        <img
                            v-if="delivery.booking?.vehicle?.photo_url"
                            :src="delivery.booking?.vehicle?.photo_url"
                            :alt="delivery.booking?.vehicle?.model"
                            class="h-28 w-full sm:w-40 rounded-[4px] object-cover border border-border"
                        />
                        <div class="space-y-1.5 text-xs flex-1">
                            <div class="font-semibold text-sm text-foreground">
                                {{ delivery.booking?.vehicle?.brand }} {{ delivery.booking?.vehicle?.model }}
                            </div>
                            <div class="font-mono text-xs text-muted-foreground">
                                Plat: {{ delivery.booking?.vehicle?.plate }}
                            </div>
                            <div class="text-muted-foreground text-[11px] pt-1">
                                Jadwal Ambil: {{ formatDateTime(delivery.booking?.pickup_at) }}
                            </div>
                            <div class="text-muted-foreground text-[11px]">
                                Jadwal Kembali: {{ formatDateTime(delivery.booking?.return_at) }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Upload Foto Dokumentasi & Catat Kerusakan (Sangat nyaman di 375px HP) -->
            <div class="flex flex-col gap-6">
                <!-- Form Upload Foto -->
                <div class="rounded-[6px] border border-border bg-card p-4">
                    <h2 class="text-sm font-semibold tracking-tight text-foreground mb-1 pb-2 border-b border-border flex items-center gap-2">
                        <Camera class="size-4 text-primary" />
                        <span>Unggah Foto Kondisi Unit</span>
                    </h2>
                    <form class="flex flex-col gap-3.5 mt-3" @submit.prevent="submitPhoto">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <Label for="stage" class="text-xs">Tahapan Foto</Label>
                                <select
                                    id="stage"
                                    v-model="photoForm.stage"
                                    class="h-10 min-h-[40px] w-full rounded-[6px] border border-input bg-background px-3 text-xs outline-none focus-visible:ring-[2px] focus-visible:ring-ring mt-1"
                                >
                                    <option value="kirim">Saat Kirim ke Pelanggan</option>
                                    <option value="ambil">Saat Ambil Kembali</option>
                                    <option value="anomali">Anomali / Lecet / Baret</option>
                                </select>
                                <InputError :message="photoForm.errors.stage" />
                            </div>
                            <div>
                                <Label for="photo_file" class="text-xs">Pilih Foto Kamera / Galeri</Label>
                                <Input
                                    id="photo_file"
                                    type="file"
                                    accept="image/*"
                                    capture="environment"
                                    class="h-10 min-h-[40px] mt-1 cursor-pointer text-xs"
                                    required
                                    @change="onPhotoChange"
                                />
                                <InputError :message="photoForm.errors.photo" />
                            </div>
                        </div>

                        <div v-if="photoPreview" class="relative overflow-hidden rounded-[6px] border border-border">
                            <img :src="photoPreview" alt="Pratinjau foto" class="h-40 w-full object-cover" />
                            <span class="absolute bottom-1 right-1 rounded-[4px] bg-black/80 px-2 py-0.5 text-[10px] text-white">
                                Pratinjau Foto
                            </span>
                        </div>

                        <div>
                            <Label for="p_note" class="text-xs">Keterangan Foto</Label>
                            <Input
                                id="p_note"
                                v-model="photoForm.note"
                                placeholder="Contoh: Kondisi fisik bagian depan saat serah terima"
                                class="h-10 min-h-[40px] text-xs mt-1"
                            />
                            <InputError :message="photoForm.errors.note" />
                        </div>

                        <Button
                            type="submit"
                            size="sm"
                            class="w-full sm:w-auto h-10 min-h-[40px] self-end px-5"
                            :disabled="photoForm.processing"
                        >
                            <Spinner v-if="photoForm.processing" class="mr-2" />
                            Unggah Foto
                        </Button>
                    </form>

                    <!-- Galeri Foto Dokumentasi -->
                    <div v-if="delivery.photos && delivery.photos.length > 0" class="mt-5 border-t border-border pt-3">
                        <h3 class="text-xs font-semibold mb-2.5 text-muted-foreground uppercase tracking-wider">
                            Foto Dokumentasi Unit ({{ delivery.photos.length }})
                        </h3>
                        <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
                            <div
                                v-for="p in delivery.photos"
                                :key="p.id"
                                class="rounded-[4px] border border-border p-1.5 text-[11px] bg-background"
                            >
                                <a :href="p.photo_url" target="_blank" class="block">
                                    <img :src="p.photo_url" class="h-24 w-full rounded-[2px] object-cover" />
                                </a>
                                <div class="font-bold uppercase text-[10px] text-primary mt-1">{{ p.stage }}</div>
                                <div class="truncate text-muted-foreground text-[10px]">{{ p.note ?? '-' }}</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Form Catat Kerusakan & Denda -->
                <div class="rounded-[6px] border border-border bg-card p-4">
                    <h2 class="text-sm font-semibold tracking-tight text-foreground mb-1 pb-2 border-b border-border flex items-center gap-2">
                        <ShieldAlert class="size-4 text-destructive" />
                        <span>Catat Kerusakan / Lecet (Jika Ada)</span>
                    </h2>
                    <form class="flex flex-col gap-3 mt-3" @submit.prevent="submitDamage">
                        <div>
                            <Label for="dmg_desc" class="text-xs">Uraian Kerusakan Fisik</Label>
                            <Input
                                id="dmg_desc"
                                v-model="damageForm.description"
                                placeholder="Contoh: Baret sepanjang 10cm di pintu kanan"
                                class="h-10 min-h-[40px] text-xs mt-1"
                                required
                            />
                            <InputError :message="damageForm.errors.description" />
                        </div>
                        <div>
                            <Label for="dmg_fine" class="text-xs">Estimasi Biaya Perbaikan (Rp)</Label>
                            <Input
                                id="dmg_fine"
                                v-model="damageForm.fine_amount"
                                type="number"
                                placeholder="300000"
                                class="h-10 min-h-[40px] text-xs mt-1"
                                required
                            />
                            <InputError :message="damageForm.errors.fine_amount" />
                        </div>
                        <Button
                            type="submit"
                            variant="destructive"
                            size="sm"
                            class="w-full sm:w-auto h-10 min-h-[40px] self-end px-5"
                            :disabled="damageForm.processing"
                        >
                            <Spinner v-if="damageForm.processing" class="mr-2" />
                            Simpan Laporan Kerusakan
                        </Button>
                    </form>
                </div>
            </div>
        </div>

        <ConfirmDialog
            :open="confirmOpen"
            title="Perbarui Status Tugas"
            :description="`Apakah Anda yakin ingin memperbarui status tugas pengiriman menjadi '${getStatusLabel(pendingStatus)}'?`"
            confirm-text="Ya, Perbarui Status"
            @update:open="confirmOpen = $event"
            @confirm="handleConfirmStatus"
        />
    </div>
</template>
