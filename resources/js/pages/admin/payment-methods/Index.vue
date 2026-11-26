<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import EmptyState from '@/components/EmptyState.vue';
import InputError from '@/components/InputError.vue';
import PageHeader from '@/components/PageHeader.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

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

    <div class="flex flex-col gap-6 p-4 sm:p-6">
        <PageHeader
            title="Metode Pembayaran"
            description="Atur rekening transfer bank dan kode QRIS untuk pembayaran uang muka dan pelunasan sewa."
        >
            <template #actions>
                <Button size="sm" @click="isCreating = !isCreating">
                    {{ isCreating ? 'Batal' : '+ Tambah Metode' }}
                </Button>
            </template>
        </PageHeader>

        <!-- Form Tambah -->
        <div v-if="isCreating" class="rounded-[6px] border border-border bg-card p-5">
            <h2 class="text-sm font-semibold tracking-tight text-foreground mb-4 pb-2 border-b border-border">
                Tambah Metode Pembayaran Baru
            </h2>
            <form class="grid gap-4 sm:grid-cols-2" @submit.prevent="submit">
                <div>
                    <Label for="type">Tipe Pembayaran</Label>
                    <select
                        id="type"
                        v-model="form.type"
                        class="h-10 min-h-[40px] w-full rounded-[6px] border border-input bg-background px-3 text-sm shadow-none outline-none focus-visible:ring-[2px] focus-visible:ring-ring mt-1"
                    >
                        <option value="bank_transfer">Transfer Bank</option>
                        <option value="qris">QRIS (Scan Barcode)</option>
                    </select>
                    <InputError :message="form.errors.type" />
                </div>

                <div>
                    <Label for="label">Nama / Label Metode</Label>
                    <Input
                        id="label"
                        v-model="form.label"
                        placeholder="Contoh: BCA Transfer atau QRIS Toko"
                        class="h-10 min-h-[40px] mt-1"
                    />
                    <InputError :message="form.errors.label" />
                </div>

                <template v-if="form.type === 'bank_transfer'">
                    <div>
                        <Label for="acc_num">Nomor Rekening</Label>
                        <Input
                            id="acc_num"
                            v-model="form.account_number"
                            placeholder="Contoh: 1234567890"
                            class="h-10 min-h-[40px] font-mono mt-1"
                        />
                        <InputError :message="form.errors.account_number" />
                    </div>
                    <div>
                        <Label for="acc_name">Atas Nama (A.N.)</Label>
                        <Input
                            id="acc_name"
                            v-model="form.account_name"
                            placeholder="Contoh: PT Rental Mobil Utama"
                            class="h-10 min-h-[40px] mt-1"
                        />
                        <InputError :message="form.errors.account_name" />
                    </div>
                </template>

                <div v-if="form.type === 'qris'" class="sm:col-span-2">
                    <Label for="qris_img">Gambar Barcode QRIS</Label>
                    <Input
                        id="qris_img"
                        type="file"
                        accept="image/*"
                        class="h-10 min-h-[40px] mt-1 cursor-pointer"
                        @change="onQrisChange"
                    />
                    <InputError :message="form.errors.qris_image" />
                </div>

                <div class="sm:col-span-2 flex justify-end gap-2 border-t border-border pt-4">
                    <Button type="button" variant="outline" class="h-10 min-h-[40px]" @click="isCreating = false">
                        Batal
                    </Button>
                    <Button type="submit" class="h-10 min-h-[40px]" :disabled="form.processing">
                        <Spinner v-if="form.processing" class="mr-2" />
                        Simpan Metode
                    </Button>
                </div>
            </form>
        </div>

        <!-- Grid Metode Pembayaran -->
        <div v-if="methods.length === 0" class="rounded-[6px] border border-border bg-card p-6">
            <EmptyState
                title="Belum ada metode pembayaran"
                description="Tambahkan rekening transfer bank atau QRIS agar pelanggan dapat melakukan pembayaran."
            >
                <Button size="sm" class="mt-2" @click="isCreating = true">
                    Tambah Metode Pertama
                </Button>
            </EmptyState>
        </div>

        <div v-else class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <div
                v-for="m in methods"
                :key="m.id"
                class="flex flex-col justify-between rounded-[6px] border border-border bg-card p-4 transition-colors"
            >
                <div>
                    <div class="flex items-center justify-between mb-2">
                        <span class="rounded-[4px] border border-border bg-muted/60 px-2 py-0.5 text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">
                            {{ m.type === 'qris' ? 'QRIS' : 'Transfer Bank' }}
                        </span>
                        <StatusBadge :status="m.is_active ? 'active' : 'inactive'" class="scale-90 origin-right" />
                    </div>

                    <h3 class="text-sm font-semibold tracking-tight text-foreground">{{ m.label }}</h3>

                    <div v-if="m.type === 'bank_transfer'" class="mt-3 text-xs space-y-1">
                        <div class="text-muted-foreground text-[11px]">Nomor Rekening:</div>
                        <div class="font-mono font-bold text-sm text-foreground">{{ m.account_number }}</div>
                        <div class="text-muted-foreground text-[11px]">A.N. {{ m.account_name }}</div>
                    </div>

                    <div v-if="m.type === 'qris'" class="mt-3">
                        <img
                            v-if="m.qris_image_url"
                            :src="m.qris_image_url"
                            alt="QRIS"
                            class="h-32 w-32 object-contain rounded-[4px] border border-border mx-auto bg-white p-1"
                        />
                        <div v-else class="h-24 flex items-center justify-center border border-border rounded-[4px] bg-muted text-xs text-muted-foreground">
                            Tanpa gambar barcode
                        </div>
                    </div>
                </div>

                <div class="mt-4 flex justify-end border-t border-border pt-3">
                    <Button variant="destructive" size="sm" class="h-8 text-xs" @click="remove(m)">
                        Hapus
                    </Button>
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
