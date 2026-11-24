<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

interface PaymentMethod {
    id: number;
    type: 'qris' | 'bank_transfer';
    label: string;
    account_number: string | null;
    account_name: string | null;
    qris_image_url: string | null;
    is_active: boolean;
}

const props = defineProps<{ methods: PaymentMethod[] }>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Metode Pembayaran', href: '/admin/payment-methods' }],
    },
});

const isCreating = ref(false);

const confirmOpen = ref(false);
const selectedMethod = ref<PaymentMethod | null>(null);

const form = useForm({
    type: 'bank_transfer',
    label: '',
    account_number: '',
    account_name: '',
    qris_image: null as File | null,
    is_active: true,
});

function onQrisChange(e: Event): void {
    const input = e.target as HTMLInputElement;
    form.qris_image = input.files?.[0] ?? null;
}

function submit(): void {
    form.post('/admin/payment-methods', {
        forceFormData: true,
        onSuccess: () => {
            isCreating.value = false;
            form.reset();
        },
    });
}

function remove(method: PaymentMethod): void {
    selectedMethod.value = method;
    confirmOpen.value = true;
}

function handleConfirmDelete(): void {
    if (selectedMethod.value) {
        useForm({}).delete(`/admin/payment-methods/${selectedMethod.value.id}`, {
            onFinish: () => {
                confirmOpen.value = false;
                selectedMethod.value = null;
            },
        });
    }
}
</script>

<template>
    <Head title="Metode Pembayaran" />

    <div class="flex flex-col gap-6 p-4">
        <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-center">
            <div>
                <h1 class="text-xl font-semibold">Metode Pembayaran</h1>
                <p class="text-sm text-muted-foreground">
                    Atur rekening transfer dan QRIS yang dapat dipilih pelanggan untuk pembayaran DP dan pelunasan.
                </p>
            </div>
            <Button @click="isCreating = !isCreating">
                {{ isCreating ? 'Batal' : '+ Tambah Metode' }}
            </Button>
        </div>

        <!-- Form Tambah -->
        <div v-if="isCreating" class="rounded-xl border bg-card p-5 shadow-xs">
            <h2 class="text-base font-semibold mb-4">Tambah Metode Pembayaran Baru</h2>
            <form class="grid gap-4 sm:grid-cols-2" @submit.prevent="submit">
                <div>
                    <Label for="type">Tipe Pembayaran</Label>
                    <select
                        id="type"
                        v-model="form.type"
                        class="h-9 w-full rounded-md border border-input bg-background px-3 text-sm shadow-xs outline-none focus-visible:ring-1 focus-visible:ring-ring mt-1"
                    >
                        <option value="bank_transfer">Transfer Bank</option>
                        <option value="qris">QRIS (Scan Barcode)</option>
                    </select>
                </div>

                <div>
                    <Label for="label">Nama / Label Metode</Label>
                    <Input id="label" v-model="form.label" placeholder="Contoh: BCA Transfer atau QRIS Utama" class="mt-1" />
                </div>

                <template v-if="form.type === 'bank_transfer'">
                    <div>
                        <Label for="acc_num">Nomor Rekening</Label>
                        <Input id="acc_num" v-model="form.account_number" placeholder="Contoh: 1234567890" class="mt-1" />
                    </div>
                    <div>
                        <Label for="acc_name">Nama Pemilik Rekening (A.N.)</Label>
                        <Input id="acc_name" v-model="form.account_name" placeholder="Contoh: PT Rental Mobil" class="mt-1" />
                    </div>
                </template>

                <div v-if="form.type === 'qris'" class="sm:col-span-2">
                    <Label for="qris_img">Gambar Barcode QRIS</Label>
                    <Input id="qris_img" type="file" accept="image/*" class="mt-1" @change="onQrisChange" />
                </div>

                <div class="sm:col-span-2 flex justify-end gap-2 border-t pt-3">
                    <Button type="button" variant="outline" @click="isCreating = false">Batal</Button>
                    <Button type="submit" :disabled="form.processing">Simpan Metode</Button>
                </div>
            </form>
        </div>

        <!-- Grid Metode Pembayaran -->
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <div v-for="m in methods" :key="m.id" class="flex flex-col justify-between rounded-xl border bg-card p-5 shadow-xs">
                <div>
                    <div class="flex items-center justify-between mb-2">
                        <Badge :variant="m.type === 'qris' ? 'default' : 'secondary'">
                            {{ m.type === 'qris' ? 'QRIS' : 'Transfer Bank' }}
                        </Badge>
                        <span class="text-xs text-emerald-600 font-semibold" v-if="m.is_active">Aktif</span>
                    </div>

                    <h3 class="text-lg font-semibold">{{ m.label }}</h3>

                    <div v-if="m.type === 'bank_transfer'" class="mt-2 text-sm">
                        <div class="text-xs text-muted-foreground">Nomor Rekening:</div>
                        <div class="font-mono font-bold text-base">{{ m.account_number }}</div>
                        <div class="text-xs text-muted-foreground mt-1">A.N. {{ m.account_name }}</div>
                    </div>

                    <div v-if="m.type === 'qris'" class="mt-3">
                        <img
                            v-if="m.qris_image_url"
                            :src="m.qris_image_url"
                            alt="QRIS"
                            class="h-36 w-36 object-contain rounded-lg border mx-auto"
                        />
                        <div v-else class="h-28 flex items-center justify-center border rounded-lg bg-muted text-xs text-muted-foreground">
                            Belum ada gambar barcode QRIS
                        </div>
                    </div>
                </div>

                <div class="mt-5 flex justify-end border-t pt-3">
                    <Button variant="destructive" size="sm" @click="remove(m)">Hapus</Button>
                </div>
            </div>
        </div>

        <ConfirmDialog
            :open="confirmOpen"
            title="Hapus Metode Pembayaran"
            :description="`Apakah Anda yakin ingin menghapus metode pembayaran '${selectedMethod?.label}'? Metode ini tidak akan lagi muncul untuk pelanggan baru.`"
            variant="destructive"
            confirm-text="Ya, Hapus"
            @update:open="confirmOpen = $event"
            @confirm="handleConfirmDelete"
        />
    </div>
</template>
