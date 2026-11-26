<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import InputError from '@/components/InputError.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

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
    purchase_price?: string | number | null;
    photo_url: string | null;
    status: 'available' | 'maintenance' | 'inactive';
}

const props = defineProps<{ vehicle: Vehicle | null }>();

const isEdit = computed(() => props.vehicle !== null);

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Kendaraan', href: '/admin/vehicles' },
            { title: 'Form Kendaraan', href: '#' },
        ],
    },
});

const form = useForm({
    plate: props.vehicle?.plate ?? '',
    brand: props.vehicle?.brand ?? '',
    model: props.vehicle?.model ?? '',
    year: props.vehicle?.year ?? '',
    color: props.vehicle?.color ?? '',
    transmission: props.vehicle?.transmission ?? 'manual',
    seats: props.vehicle?.seats ?? 4,
    price_per_day: props.vehicle ? Number(props.vehicle.price_per_day) : '',
    late_fee_per_hour: props.vehicle ? Number(props.vehicle.late_fee_per_hour) : '',
    purchase_price: props.vehicle?.purchase_price ? Number(props.vehicle.purchase_price) : '',
    status: props.vehicle?.status ?? 'available',
    photo: null as File | null,
});

function onPhotoChange(event: Event): void {
    const input = event.target as HTMLInputElement;
    form.photo = input.files?.[0] ?? null;
}

function submit(): void {
    if (props.vehicle) {
        form.transform((data) => ({ ...data, _method: 'put' })).post(
            `/admin/vehicles/${props.vehicle.id}`,
            { forceFormData: true },
        );
    } else {
        form.post('/admin/vehicles', { forceFormData: true });
    }
}

const selectClass =
    'border border-input bg-background h-10 min-h-[40px] w-full rounded-[6px] px-3 text-sm shadow-none outline-none focus-visible:ring-[2px] focus-visible:ring-ring';
</script>

<template>
    <Head :title="isEdit ? 'Ubah Data Kendaraan' : 'Tambah Kendaraan Baru'" />

    <div class="mx-auto flex w-full max-w-3xl flex-col gap-6 p-4 sm:p-6">
        <PageHeader
            :title="isEdit ? 'Ubah Data Kendaraan' : 'Tambah Kendaraan Baru'"
            description="Lengkapi detail spesifikasi, tarif sewa harian, dan ketentuan denda keterlambatan."
        >
            <template #actions>
                <Button as-child variant="outline" size="sm">
                    <Link href="/admin/vehicles">Kembali ke Daftar</Link>
                </Button>
            </template>
        </PageHeader>

        <form class="grid gap-4 sm:grid-cols-2 rounded-[6px] border border-border bg-card p-5" @submit.prevent="submit">
            <div class="grid gap-2">
                <Label for="plate">Plat Nomor</Label>
                <Input id="plate" v-model="form.plate" placeholder="Contoh: L 1234 WU" class="font-mono" />
                <InputError :message="form.errors.plate" />
            </div>

            <div class="grid gap-2">
                <Label for="status">Status Unit</Label>
                <select id="status" v-model="form.status" :class="selectClass">
                    <option value="available">Tersedia (Ready)</option>
                    <option value="maintenance">Perawatan (Bengkel)</option>
                    <option value="inactive">Nonaktif</option>
                </select>
                <InputError :message="form.errors.status" />
            </div>

            <div class="grid gap-2">
                <Label for="brand">Merek Kendaraan</Label>
                <Input id="brand" v-model="form.brand" placeholder="Contoh: Toyota, Honda" />
                <InputError :message="form.errors.brand" />
            </div>

            <div class="grid gap-2">
                <Label for="model">Model / Tipe</Label>
                <Input id="model" v-model="form.model" placeholder="Contoh: Avanza G, Brio RS" />
                <InputError :message="form.errors.model" />
            </div>

            <div class="grid gap-2">
                <Label for="year">Tahun Pembuatan</Label>
                <Input id="year" v-model="form.year" type="number" placeholder="2023" />
                <InputError :message="form.errors.year" />
            </div>

            <div class="grid gap-2">
                <Label for="color">Warna Kendaraan</Label>
                <Input id="color" v-model="form.color" placeholder="Contoh: Putih, Hitam Metalik" />
                <InputError :message="form.errors.color" />
            </div>

            <div class="grid gap-2">
                <Label for="transmission">Transmisi</Label>
                <select id="transmission" v-model="form.transmission" :class="selectClass">
                    <option value="manual">Manual</option>
                    <option value="matic">Matic</option>
                </select>
                <InputError :message="form.errors.transmission" />
            </div>

            <div class="grid gap-2">
                <Label for="seats">Kapasitas Kursi</Label>
                <Input id="seats" v-model="form.seats" type="number" placeholder="5" />
                <InputError :message="form.errors.seats" />
            </div>

            <div class="grid gap-2">
                <Label for="price_per_day">Tarif Sewa Per Hari (Rp)</Label>
                <Input id="price_per_day" v-model="form.price_per_day" type="number" placeholder="350000" />
                <InputError :message="form.errors.price_per_day" />
            </div>

            <div class="grid gap-2">
                <Label for="late_fee_per_hour">Denda Keterlambatan Per Jam (Rp)</Label>
                <Input id="late_fee_per_hour" v-model="form.late_fee_per_hour" type="number" placeholder="50000" />
                <InputError :message="form.errors.late_fee_per_hour" />
            </div>

            <div class="grid gap-2 sm:col-span-2">
                <Label for="purchase_price">Harga Beli Kendaraan (Rp) - Opsional</Label>
                <Input id="purchase_price" v-model="form.purchase_price" type="number" placeholder="200000000" />
                <InputError :message="form.errors.purchase_price" />
            </div>

            <div class="grid gap-2 sm:col-span-2">
                <Label for="photo">Foto Unit Kendaraan</Label>
                <div v-if="vehicle?.photo_url" class="mb-2">
                    <img
                        :src="vehicle.photo_url"
                        alt="Foto unit"
                        class="h-28 w-44 rounded-[6px] object-cover border border-border"
                    />
                </div>
                <Input id="photo" type="file" accept="image/*" @change="onPhotoChange" class="cursor-pointer" />
                <p v-if="isEdit" class="text-xs text-muted-foreground">
                    Biarkan kosong bila tidak ingin mengganti foto.
                </p>
                <InputError :message="form.errors.photo" />
            </div>

            <div class="flex flex-wrap items-center gap-3 pt-4 sm:col-span-2 border-t border-border">
                <Button type="submit" :disabled="form.processing" class="h-10 min-h-[40px] px-5">
                    <Spinner v-if="form.processing" class="mr-2" />
                    {{ form.processing ? 'Menyimpan...' : 'Simpan Kendaraan' }}
                </Button>
                <Button as-child variant="outline" type="button" class="h-10 min-h-[40px]">
                    <Link href="/admin/vehicles">Batal</Link>
                </Button>
            </div>
        </form>
    </div>
</template>
