<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

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
            { title: 'Form', href: '#' },
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
        // Upload file butuh POST, jadi PUT "dipalsukan" lewat _method
        form.transform((data) => ({ ...data, _method: 'put' })).post(
            `/admin/vehicles/${props.vehicle.id}`,
            { forceFormData: true },
        );
    } else {
        form.post('/admin/vehicles', { forceFormData: true });
    }
}

const selectClass =
    'border-input bg-background h-9 w-full rounded-md border px-3 text-sm shadow-xs outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50';
</script>

<template>
    <Head :title="isEdit ? 'Ubah Kendaraan' : 'Tambah Kendaraan'" />

    <div class="mx-auto flex w-full max-w-3xl flex-col gap-6 p-4">
        <div>
            <h1 class="text-xl font-semibold">
                {{ isEdit ? 'Ubah Kendaraan' : 'Tambah Kendaraan' }}
            </h1>
            <p class="text-sm text-muted-foreground">
                Harga sewa dan denda keterlambatan per jam akan dipakai untuk menghitung tagihan.
            </p>
        </div>

        <form class="grid gap-4 sm:grid-cols-2" @submit.prevent="submit">
            <div class="grid gap-2">
                <Label for="plate">Plat nomor</Label>
                <Input id="plate" v-model="form.plate" placeholder="L 1234 WU" />
                <InputError :message="form.errors.plate" />
            </div>

            <div class="grid gap-2">
                <Label for="status">Status</Label>
                <select id="status" v-model="form.status" :class="selectClass">
                    <option value="available">Tersedia</option>
                    <option value="maintenance">Perawatan</option>
                    <option value="inactive">Nonaktif</option>
                </select>
                <InputError :message="form.errors.status" />
            </div>

            <div class="grid gap-2">
                <Label for="brand">Merek</Label>
                <Input id="brand" v-model="form.brand" placeholder="Honda" />
                <InputError :message="form.errors.brand" />
            </div>

            <div class="grid gap-2">
                <Label for="model">Tipe</Label>
                <Input id="model" v-model="form.model" placeholder="Brio RS" />
                <InputError :message="form.errors.model" />
            </div>

            <div class="grid gap-2">
                <Label for="year">Tahun</Label>
                <Input id="year" v-model="form.year" type="number" placeholder="2022" />
                <InputError :message="form.errors.year" />
            </div>

            <div class="grid gap-2">
                <Label for="color">Warna</Label>
                <Input id="color" v-model="form.color" placeholder="Putih" />
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
                <Label for="seats">Jumlah kursi</Label>
                <Input id="seats" v-model="form.seats" type="number" />
                <InputError :message="form.errors.seats" />
            </div>

            <div class="grid gap-2">
                <Label for="price_per_day">Harga sewa per hari (Rp)</Label>
                <Input id="price_per_day" v-model="form.price_per_day" type="number" placeholder="350000" />
                <InputError :message="form.errors.price_per_day" />
            </div>

            <div class="grid gap-2">
                <Label for="late_fee_per_hour">Denda telat per jam (Rp)</Label>
                <Input id="late_fee_per_hour" v-model="form.late_fee_per_hour" type="number" placeholder="50000" />
                <InputError :message="form.errors.late_fee_per_hour" />
            </div>

            <div class="grid gap-2">
                <Label for="purchase_price">Harga beli kendaraan (Rp)</Label>
                <Input id="purchase_price" v-model="form.purchase_price" type="number" placeholder="200000000" />
                <InputError :message="form.errors.purchase_price" />
            </div>

            <div class="grid gap-2 sm:col-span-2">
                <Label for="photo">Foto kendaraan (maks. 2 MB)</Label>
                <img
                    v-if="vehicle?.photo_url"
                    :src="vehicle.photo_url"
                    alt="Foto saat ini"
                    class="h-32 w-52 rounded-md object-cover"
                />
                <Input id="photo" type="file" accept="image/*" @change="onPhotoChange" />
                <p v-if="isEdit" class="text-xs text-muted-foreground">
                    Kosongkan jika tidak ingin mengganti foto.
                </p>
                <InputError :message="form.errors.photo" />
            </div>

            <div class="flex gap-2 sm:col-span-2">
                <Button type="submit" :disabled="form.processing">
                    {{ form.processing ? 'Menyimpan...' : 'Simpan' }}
                </Button>
                <Button as-child variant="outline" type="button">
                    <Link href="/admin/vehicles">Batal</Link>
                </Button>
            </div>
        </form>
    </div>
</template>
