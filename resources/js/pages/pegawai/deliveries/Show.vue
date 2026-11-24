<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatDateTime, rupiah } from '@/lib/format';

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

const statusLabel: Record<string, string> = {
    assigned: 'Ditugaskan (Menunggu Approval Pegawai)',
    approved: 'Tugas Disetujui (Siap Kirim)',
    delivering: 'Sedang Mengantar ke Alamat Pelanggan',
    delivered: 'Unit Tiba & Diterima Pelanggan (Sewa Berjalan)',
    picked_up: 'Mobil Telah Dijemput dari Pelanggan',
    in_garage: 'Unit Kembali Aman di Garasi',
};

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

    <div class="flex flex-col gap-6 p-4">
        <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-center">
            <div>
                <h1 class="text-xl font-bold font-mono">Tugas: {{ delivery.booking?.booking_code }}</h1>
                <p class="text-sm text-muted-foreground">
                    Status saat ini: <strong class="text-primary">{{ statusLabel[delivery.status] }}</strong>
                </p>
            </div>
            <Button as-child variant="outline">
                <Link href="/pegawai/deliveries">Kembali</Link>
            </Button>
        </div>

        <!-- Timeline Tahapan Pengiriman -->
        <div class="rounded-xl border bg-card p-5 shadow-xs">
            <h2 class="text-xs font-semibold uppercase tracking-wider text-muted-foreground mb-4">Progres Pengiriman & Penjemputan</h2>
            <div class="grid grid-cols-2 gap-2 sm:grid-cols-6">
                <div
                    v-for="(stage, idx) in stages"
                    :key="stage.key"
                    class="flex flex-col items-center rounded-lg border p-2 text-center text-xs transition-colors"
                    :class="[
                        delivery.status === stage.key
                            ? 'border-primary bg-primary/10 font-bold text-primary ring-1 ring-primary'
                            : isStageCompleted(stage.key)
                            ? 'border-emerald-300 bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-400'
                            : 'bg-muted/30 text-muted-foreground opacity-60'
                    ]"
                >
                    <span class="text-[10px]">{{ idx + 1 }}</span>
                    <span>{{ stage.title }}</span>
                    <span v-if="isStageCompleted(stage.key) && delivery.status !== stage.key" class="text-[10px] text-emerald-600 font-semibold">✓ Selesai</span>
                    <span v-else-if="delivery.status === stage.key" class="text-[10px] text-primary font-bold">● Aktif</span>
                </div>
            </div>
        </div>

        <!-- Tombol Aksi Status Tahapan -->
        <div class="rounded-xl border bg-card p-5 shadow-xs">
            <h2 class="text-base font-semibold mb-2">Tindakan Cepat Tahap Berikutnya</h2>
            <p class="text-xs text-muted-foreground mb-4">Perbarui status tugas ini sesuai tindakan fisik yang Anda lakukan di lapangan.</p>
            <div class="flex flex-wrap gap-3">
                <Button
                    v-if="delivery.status === 'assigned'"
                    class="bg-blue-600 hover:bg-blue-700 text-white"
                    @click="updateStatus('approved')"
                >
                    ✓ Setujui Perintah Pengiriman (Approve)
                </Button>

                <Button
                    v-if="delivery.status === 'approved'"
                    class="bg-amber-600 hover:bg-amber-700 text-white"
                    @click="updateStatus('delivering')"
                >
                    🚀 Berangkat Mengantar Unit ke Pelanggan
                </Button>

                <template v-if="delivery.status === 'delivering'">
                    <Button
                        v-if="isDpVerified"
                        class="bg-emerald-600 hover:bg-emerald-700 text-white"
                        @click="updateStatus('delivered')"
                    >
                        ✓ Konfirmasi Serah Terima ke Pelanggan (Mulai Sewa)
                    </Button>
                    <div
                        v-else
                        class="flex items-center gap-2 rounded-lg border border-amber-500/30 bg-amber-500/10 px-4 py-2 text-xs font-semibold text-amber-800 dark:text-amber-300"
                    >
                        <span>⏳</span> Menunggu verifikasi DP oleh admin
                    </div>
                </template>

                <Button
                    v-if="delivery.status === 'delivered'"
                    variant="secondary"
                    class="bg-indigo-600 hover:bg-indigo-700 text-white"
                    @click="updateStatus('picked_up')"
                >
                    🚙 Mulai Penjemputan / Ambil Mobil Kembali
                </Button>

                <template v-if="delivery.status === 'picked_up'">
                    <Button
                        v-if="isBookingCompleted"
                        class="bg-purple-600 hover:bg-purple-700 text-white"
                        @click="updateStatus('in_garage')"
                    >
                        🏠 Konfirmasi Mobil Sudah Tiba di Garasi
                    </Button>
                    <div
                        v-else
                        class="flex items-center gap-2 rounded-lg border border-blue-500/30 bg-blue-500/10 px-4 py-2 text-xs font-semibold text-blue-800 dark:text-blue-300"
                    >
                        <span>ℹ️</span> Tunggu konfirmasi admin, pelanggan harus melunasi dulu
                    </div>
                </template>

                <div v-if="delivery.status === 'in_garage'" class="text-sm text-emerald-600 font-semibold flex items-center gap-1.5">
                    <span>✓</span> Seluruh tugas pengantaran dan penjemputan unit ini telah selesai.
                </div>
            </div>
        </div>

        <div class="grid gap-6 lg:grid-cols-2">
            <!-- Informasi Pelanggan & Unit -->
            <div class="flex flex-col gap-6">
                <div class="rounded-xl border bg-card p-5 shadow-xs">
                    <h2 class="text-base font-semibold mb-3">Rincian Pelanggan & Alamat</h2>
                    <div class="flex flex-col gap-2 text-sm">
                        <div>
                            <span class="text-xs text-muted-foreground block">Penyewa</span>
                            <span class="font-bold text-base">{{ delivery.booking?.user?.name }}</span>
                        </div>
                        <div>
                            <span class="text-xs text-muted-foreground block">Nomor Telepon / WhatsApp</span>
                            <a :href="`https://wa.me/${delivery.booking?.user?.phone}`" target="_blank" class="font-semibold text-emerald-600 underline">
                                {{ delivery.booking?.user?.phone ?? '-' }}
                            </a>
                        </div>
                        <div>
                            <span class="text-xs text-muted-foreground block">Alamat Pengantaran</span>
                            <span class="font-medium text-primary">{{ delivery.booking?.delivery_address }}</span>
                            <a
                                :href="`https://www.google.com/maps/dir/?api=1&destination=${encodeURIComponent(delivery.booking?.delivery_address ?? '')}`"
                                target="_blank"
                                class="mt-1 inline-flex items-center gap-1 text-xs font-semibold text-blue-600 underline"
                            >
                                🗺️ Navigasi ke Alamat Pelanggan
                            </a>
                        </div>
                        <div>
                            <span class="text-xs text-muted-foreground block">Catatan dari Admin</span>
                            <span class="font-medium">{{ delivery.notes ?? '-' }}</span>
                        </div>
                    </div>
                </div>

                <div class="rounded-xl border bg-card p-5 shadow-xs">
                    <h2 class="text-base font-semibold mb-3">Unit Kendaraan</h2>
                    <div class="flex gap-4">
                        <img
                            v-if="delivery.booking?.vehicle?.photo_url"
                            :src="delivery.booking?.vehicle?.photo_url"
                            class="h-24 w-32 rounded-lg object-cover border"
                        />
                        <div class="flex flex-col gap-1 text-sm">
                            <span class="font-bold text-base">{{ delivery.booking?.vehicle?.brand }} {{ delivery.booking?.vehicle?.model }}</span>
                            <span class="font-mono text-muted-foreground">Plat: {{ delivery.booking?.vehicle?.plate }}</span>
                            <span class="text-xs text-muted-foreground">Jadwal: {{ formatDateTime(delivery.booking?.pickup_at) }} s/d {{ formatDateTime(delivery.booking?.return_at) }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Upload Foto & Catat Kerusakan -->
            <div class="flex flex-col gap-6">
                <!-- Form Upload Foto -->
                <div class="rounded-xl border bg-card p-5 shadow-xs">
                    <h2 class="text-base font-semibold mb-3">Unggah Foto Dokumentasi Unit</h2>
                    <form class="flex flex-col gap-3" @submit.prevent="submitPhoto">
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <Label for="stage">Tahapan Dokumentasi</Label>
                                <select
                                    id="stage"
                                    v-model="photoForm.stage"
                                    class="h-9 w-full rounded-md border border-input bg-background px-3 text-sm shadow-xs outline-none focus-visible:ring-1 focus-visible:ring-ring mt-1"
                                >
                                    <option value="kirim">Saat Kirim ke Pelanggan</option>
                                    <option value="ambil">Saat Ambil Kembali</option>
                                    <option value="anomali">Anomali / Lecet / Baret</option>
                                </select>
                            </div>
                            <div>
                                <Label for="photo_file">Pilih Foto</Label>
                                <Input id="photo_file" type="file" accept="image/*" class="mt-1" required @change="onPhotoChange" />
                            </div>
                        </div>

                        <div v-if="photoPreview" class="relative mt-2 overflow-hidden rounded-lg border">
                            <img :src="photoPreview" alt="Pratinjau foto" class="h-32 w-full object-cover" />
                            <span class="absolute bottom-1 right-1 rounded bg-black/70 px-2 py-0.5 text-[10px] text-white">Pratinjau Foto</span>
                        </div>

                        <div>
                            <Label for="p_note">Keterangan Foto</Label>
                            <Input id="p_note" v-model="photoForm.note" placeholder="Contoh: Kondisi bodi depan mulus saat serah terima" class="mt-1" />
                        </div>

                        <div class="flex justify-end pt-2">
                            <Button type="submit" size="sm" :disabled="photoForm.processing">
                                <span v-if="photoForm.processing" class="mr-2 inline-block h-3.5 w-3.5 animate-spin rounded-full border-2 border-current border-t-transparent"></span>
                                Simpan Foto
                            </Button>
                        </div>
                    </form>

                    <!-- Galeri Foto -->
                    <div v-if="delivery.photos && delivery.photos.length > 0" class="mt-4 border-t pt-3">
                        <h3 class="text-xs font-semibold mb-2 text-muted-foreground">Foto yang Sudah Diunggah</h3>
                        <div class="grid grid-cols-3 gap-2">
                            <div v-for="p in delivery.photos" :key="p.id" class="rounded border p-1 text-[11px]">
                                <img :src="p.photo_url" class="h-20 w-full rounded object-cover" />
                                <div class="font-bold uppercase mt-1">{{ p.stage }}</div>
                                <div class="truncate text-muted-foreground">{{ p.note ?? '-' }}</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Catat Kerusakan & Denda -->
                <div class="rounded-xl border bg-card p-5 shadow-xs">
                    <h2 class="text-base font-semibold mb-3">Catat Kerusakan / Denda (Jika Ada)</h2>
                    <form class="flex flex-col gap-3" @submit.prevent="submitDamage">
                        <div>
                            <Label for="dmg_desc">Uraian Kerusakan</Label>
                            <Input id="dmg_desc" v-model="damageForm.description" placeholder="Contoh: Baret dalam di pintu kiri belakang" class="mt-1" required />
                        </div>
                        <div>
                            <Label for="dmg_fine">Perkiraan Biaya Perbaikan (Rp)</Label>
                            <Input id="dmg_fine" v-model="damageForm.fine_amount" type="number" placeholder="300000" class="mt-1" required />
                        </div>
                        <div class="flex justify-end pt-2">
                            <Button type="submit" variant="destructive" size="sm" :disabled="damageForm.processing">
                                <span v-if="damageForm.processing" class="mr-2 inline-block h-3.5 w-3.5 animate-spin rounded-full border-2 border-current border-t-transparent"></span>
                                Simpan Laporan Kerusakan
                            </Button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <ConfirmDialog
            :open="confirmOpen"
            title="Perbarui Status Tugas"
            :description="`Apakah Anda yakin ingin mengubah status pengiriman menjadi: '${statusLabel[pendingStatus] ?? pendingStatus}'?`"
            confirm-text="Ya, Perbarui Status"
            @update:open="confirmOpen = $event"
            @confirm="handleConfirmStatus"
        />
    </div>
</template>
