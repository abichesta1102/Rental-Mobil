<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import {
    ArrowDownRight,
    ArrowUpRight,
    CheckCircle2,
    Filter,
    Plus,
    Trash2,
    TrendingUp,
} from '@lucide/vue';
import { ref } from 'vue';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import EmptyState from '@/components/EmptyState.vue';
import InputError from '@/components/InputError.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { rupiah } from '@/lib/format';

interface ExpenseItem {
    id: number;
    vehicle_id: number | null;
    category: 'oli' | 'perbaikan' | 'servis' | 'pajak' | 'lainnya';
    description: string;
    amount: string | number;
    spent_at: string;
    vehicle?: {
        id: number;
        plate: string;
        brand: string;
        model: string;
    } | null;
    creator?: {
        id: number;
        name: string;
    } | null;
}

interface MonthlyBreakdown {
    vehicle_id: number;
    plate: string;
    brand_model: string;
    revenue: number;
    expense: number;
    net_profit: number;
}

interface RoiItem {
    vehicle_id: number;
    plate: string;
    brand_model: string;
    purchase_price: number;
    all_time_revenue: number;
    all_time_expenses: number;
    accumulated_net_profit: number;
    roi_percent: number;
    completed_rentals_count: number;
    avg_profit_per_rental: number;
    rentals_needed: number | null;
    rentals_remaining: number | null;
    is_break_even: boolean;
}

interface VehicleOption {
    id: number;
    plate: string;
    brand: string;
    model: string;
}

const props = defineProps<{
    selected_month: string;
    summary: {
        revenue: number;
        expense: number;
        net_profit: number;
    };
    expenses: ExpenseItem[];
    monthly_vehicle_breakdown: MonthlyBreakdown[];
    roi_data: RoiItem[];
    vehicle_options: VehicleOption[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: '/dashboard' },
            { title: 'Laporan Keuangan', href: '/admin/finance' },
        ],
    },
});

const monthFilter = ref(props.selected_month);

function applyFilter(): void {
    router.get('/admin/finance', { month: monthFilter.value }, { preserveState: true });
}

const showAddModal = ref(false);

const form = useForm({
    vehicle_id: '' as string | number,
    category: 'servis',
    description: '',
    amount: '',
    spent_at: new Date().toISOString().split('T')[0],
});

function submitExpense(): void {
    form.post('/admin/finance/expenses', {
        onSuccess: () => {
            form.reset();
            form.spent_at = new Date().toISOString().split('T')[0];
            showAddModal.value = false;
        },
    });
}

// Dialog Konfirmasi Hapus Pengeluaran (menggantikan browser confirm)
const confirmDeleteOpen = ref(false);
const expenseToDelete = ref<number | null>(null);

function confirmDeleteExpense(id: number): void {
    expenseToDelete.value = id;
    confirmDeleteOpen.value = true;
}

function handleConfirmDeleteExpense(): void {
    if (expenseToDelete.value) {
        router.delete(`/admin/finance/expenses/${expenseToDelete.value}`, {
            onFinish: () => {
                confirmDeleteOpen.value = false;
                expenseToDelete.value = null;
            },
        });
    }
}
</script>

<template>
    <Head title="Laporan Keuangan" />

    <div class="flex flex-col gap-6 p-4 sm:p-6">
        <PageHeader
            title="Laporan Keuangan"
            description="Ringkasan pendapatan sewa, pengeluaran operasional armada, dan analisis balik modal (ROI)."
        >
            <template #actions>
                <div class="flex flex-wrap items-center gap-2">
                    <div class="flex items-center gap-1.5">
                        <Label for="month_filter" class="text-xs text-muted-foreground sr-only">Bulan:</Label>
                        <Input
                            id="month_filter"
                            v-model="monthFilter"
                            type="month"
                            class="w-36 h-9 text-xs"
                        />
                        <Button variant="outline" size="sm" class="h-9 px-3" @click="applyFilter">
                            <Filter class="size-3.5 mr-1" />
                            Filter
                        </Button>
                    </div>
                    <Button size="sm" class="h-9 px-3" @click="showAddModal = !showAddModal">
                        <Plus class="size-4 mr-1" />
                        {{ showAddModal ? 'Batal' : 'Catat Biaya' }}
                    </Button>
                </div>
            </template>
        </PageHeader>

        <!-- Summary Metric Cards -->
        <div class="grid gap-3 sm:grid-cols-3">
            <div class="rounded-[6px] border border-border bg-card p-4">
                <div class="flex items-center justify-between text-muted-foreground mb-1">
                    <span class="text-xs font-semibold uppercase tracking-wider">Pendapatan Bulan Ini</span>
                    <ArrowUpRight class="size-4 text-emerald-600" />
                </div>
                <div class="text-2xl font-bold tracking-tight text-emerald-700 dark:text-emerald-400">
                    {{ rupiah(summary.revenue) }}
                </div>
                <p class="mt-1 text-xs text-muted-foreground">Dari seluruh pembayaran terverifikasi</p>
            </div>

            <div class="rounded-[6px] border border-border bg-card p-4">
                <div class="flex items-center justify-between text-muted-foreground mb-1">
                    <span class="text-xs font-semibold uppercase tracking-wider">Pengeluaran Bulan Ini</span>
                    <ArrowDownRight class="size-4 text-rose-600" />
                </div>
                <div class="text-2xl font-bold tracking-tight text-rose-700 dark:text-rose-400">
                    {{ rupiah(summary.expense) }}
                </div>
                <p class="mt-1 text-xs text-muted-foreground">Biaya oli, servis, perbaikan, dan pajak</p>
            </div>

            <div class="rounded-[6px] border border-border bg-card p-4">
                <div class="flex items-center justify-between text-muted-foreground mb-1">
                    <span class="text-xs font-semibold uppercase tracking-wider">Laba Bersih Bulan Ini</span>
                    <TrendingUp class="size-4 text-primary" />
                </div>
                <div
                    class="text-2xl font-bold tracking-tight"
                    :class="summary.net_profit >= 0 ? 'text-primary' : 'text-rose-700 dark:text-rose-400'"
                >
                    {{ rupiah(summary.net_profit) }}
                </div>
                <p class="mt-1 text-xs text-muted-foreground">Pendapatan dikurangi pengeluaran operasional</p>
            </div>
        </div>

        <!-- Form Modal / Inline Tambah Pengeluaran -->
        <div v-if="showAddModal" class="rounded-[6px] border border-border bg-card p-5">
            <h3 class="text-sm font-semibold tracking-tight text-foreground mb-3 pb-2 border-b border-border">
                Catat Pengeluaran Operasional Baru
            </h3>
            <form @submit.prevent="submitExpense" class="grid gap-4 sm:grid-cols-2">
                <div class="grid gap-1.5">
                    <Label for="exp_category" class="text-xs">Kategori Biaya</Label>
                    <select
                        id="exp_category"
                        v-model="form.category"
                        class="h-10 min-h-[40px] rounded-[6px] border border-input bg-background px-3 text-xs outline-none focus-visible:ring-[2px] focus-visible:ring-ring"
                    >
                        <option value="oli">Oli</option>
                        <option value="perbaikan">Perbaikan</option>
                        <option value="servis">Servis Berkala</option>
                        <option value="pajak">Pajak Kendaraan</option>
                        <option value="lainnya">Lainnya</option>
                    </select>
                    <InputError :message="form.errors.category" />
                </div>

                <div class="grid gap-1.5">
                    <Label for="exp_vehicle" class="text-xs">Unit Kendaraan Terkait (Opsional)</Label>
                    <select
                        id="exp_vehicle"
                        v-model="form.vehicle_id"
                        class="h-10 min-h-[40px] rounded-[6px] border border-input bg-background px-3 text-xs outline-none focus-visible:ring-[2px] focus-visible:ring-ring"
                    >
                        <option value="">-- Umum / Tanpa Kendaraan Tertentu --</option>
                        <option v-for="v in vehicle_options" :key="v.id" :value="v.id">
                            {{ v.plate }} &middot; {{ v.brand }} {{ v.model }}
                        </option>
                    </select>
                    <InputError :message="form.errors.vehicle_id" />
                </div>

                <div class="grid gap-1.5">
                    <Label for="exp_amount" class="text-xs">Nominal Biaya (Rp)</Label>
                    <Input id="exp_amount" v-model="form.amount" type="number" placeholder="150000" class="h-10 min-h-[40px] text-xs" />
                    <InputError :message="form.errors.amount" />
                </div>

                <div class="grid gap-1.5">
                    <Label for="exp_date" class="text-xs">Tanggal Pengeluaran</Label>
                    <Input id="exp_date" v-model="form.spent_at" type="date" class="h-10 min-h-[40px] text-xs" />
                    <InputError :message="form.errors.spent_at" />
                </div>

                <div class="grid gap-1.5 sm:col-span-2">
                    <Label for="exp_desc" class="text-xs">Keterangan / Rincian</Label>
                    <Input id="exp_desc" v-model="form.description" placeholder="Contoh: Ganti oli mesin Shell Helix 10W-40" class="h-10 min-h-[40px] text-xs" />
                    <InputError :message="form.errors.description" />
                </div>

                <div class="flex gap-2 sm:col-span-2 border-t border-border pt-4">
                    <Button type="submit" size="sm" class="h-10 min-h-[40px]" :disabled="form.processing">
                        <Spinner v-if="form.processing" class="mr-2" />
                        Simpan Pengeluaran
                    </Button>
                    <Button type="button" variant="outline" size="sm" class="h-10 min-h-[40px]" @click="showAddModal = false">
                        Batal
                    </Button>
                </div>
            </form>
        </div>

        <!-- Section: Balik Modal Armada (All Time ROI) -->
        <div class="rounded-[6px] border border-border bg-card">
            <div class="p-4 border-b border-border">
                <h2 class="text-sm font-semibold tracking-tight text-foreground">Analisis Balik Modal Armada (ROI)</h2>
                <p class="text-xs text-muted-foreground">Kalkulasi ROI kumulatif berdasarkan modal beli, akumulasi laba bersih, dan sisa target sewa.</p>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="border-b border-border bg-muted/40 text-muted-foreground font-semibold">
                        <tr>
                            <th class="p-3">Kendaraan</th>
                            <th class="p-3">Harga Beli Unit</th>
                            <th class="p-3">Laba Bersih Terkumpul</th>
                            <th class="p-3">% ROI</th>
                            <th class="p-3">Total Sewa Selesai</th>
                            <th class="p-3">Rata-rata Laba / Sewa</th>
                            <th class="p-3">Status Balik Modal</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        <tr v-if="roi_data.length === 0">
                            <td colspan="7" class="p-6 text-center">
                                <EmptyState
                                    title="Belum ada data armada"
                                    description="Data analisis balik modal akan muncul setelah unit kendaraan terdaftar."
                                />
                            </td>
                        </tr>
                        <tr v-for="item in roi_data" :key="item.vehicle_id" class="hover:bg-muted/30 transition-colors">
                            <td class="p-3">
                                <div class="font-medium text-foreground">{{ item.brand_model }}</div>
                                <div class="text-[11px] text-muted-foreground font-mono">{{ item.plate }}</div>
                            </td>
                            <td class="p-3 whitespace-nowrap">
                                {{ item.purchase_price > 0 ? rupiah(item.purchase_price) : '-' }}
                            </td>
                            <td class="p-3 font-semibold whitespace-nowrap" :class="item.accumulated_net_profit >= 0 ? 'text-primary' : 'text-rose-600'">
                                {{ rupiah(item.accumulated_net_profit) }}
                            </td>
                            <td class="p-3 font-mono font-medium whitespace-nowrap">
                                <span v-if="item.purchase_price > 0">{{ item.roi_percent }}%</span>
                                <span v-else class="text-muted-foreground">-</span>
                            </td>
                            <td class="p-3 whitespace-nowrap">{{ item.completed_rentals_count }} kali sewa</td>
                            <td class="p-3 whitespace-nowrap">
                                {{ item.completed_rentals_count > 0 ? rupiah(item.avg_profit_per_rental) : '-' }}
                            </td>
                            <td class="p-3 whitespace-nowrap">
                                <Badge v-if="item.is_break_even" variant="default" class="bg-emerald-700 text-white rounded-[4px] text-[11px]">
                                    <CheckCircle2 class="mr-1 size-3" />
                                    Sudah Balik Modal
                                </Badge>
                                <div v-else-if="item.purchase_price > 0 && item.rentals_remaining !== null">
                                    <Badge variant="outline" class="text-[11px] rounded-[4px]">
                                        Butuh {{ item.rentals_needed }} sewa
                                    </Badge>
                                    <div class="text-[10px] text-muted-foreground mt-0.5">
                                        Sisa: {{ item.rentals_remaining }} sewa lagi
                                    </div>
                                </div>
                                <span v-else class="text-[11px] text-muted-foreground">
                                    {{ item.purchase_price > 0 ? 'Belum cukup transaksi' : 'Harga beli belum diset' }}
                                </span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Section: Kinerja Armada & Rincian Pengeluaran Bulan Ini -->
        <div class="grid gap-6 lg:grid-cols-2">
            <!-- Kinerja Per Kendaraan -->
            <div class="rounded-[6px] border border-border bg-card">
                <div class="p-4 border-b border-border">
                    <h2 class="text-sm font-semibold tracking-tight text-foreground">Kinerja Armada Bulan Ini</h2>
                    <p class="text-xs text-muted-foreground">Breakdown perbandingan pendapatan dan beban biaya unit.</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="border-b border-border bg-muted/40 text-muted-foreground font-semibold">
                            <tr>
                                <th class="p-3">Kendaraan</th>
                                <th class="p-3">Pendapatan</th>
                                <th class="p-3">Pengeluaran</th>
                                <th class="p-3">Laba Bersih</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            <tr v-if="monthly_vehicle_breakdown.length === 0">
                                <td colspan="4" class="p-6 text-center">
                                    <EmptyState
                                        title="Tidak ada aktivitas armada"
                                        description="Belum ada transaksi sewa untuk bulan ini."
                                    />
                                </td>
                            </tr>
                            <tr v-for="b in monthly_vehicle_breakdown" :key="b.vehicle_id" class="hover:bg-muted/30 transition-colors">
                                <td class="p-3">
                                    <div class="font-medium text-foreground">{{ b.brand_model }}</div>
                                    <div class="text-[11px] text-muted-foreground font-mono">{{ b.plate }}</div>
                                </td>
                                <td class="p-3 font-medium text-emerald-700 dark:text-emerald-400 whitespace-nowrap">{{ rupiah(b.revenue) }}</td>
                                <td class="p-3 font-medium text-rose-700 dark:text-rose-400 whitespace-nowrap">{{ rupiah(b.expense) }}</td>
                                <td class="p-3 font-semibold whitespace-nowrap" :class="b.net_profit >= 0 ? 'text-primary' : 'text-rose-600'">
                                    {{ rupiah(b.net_profit) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Daftar Pengeluaran Bulan Ini -->
            <div class="rounded-[6px] border border-border bg-card">
                <div class="p-4 border-b border-border">
                    <h2 class="text-sm font-semibold tracking-tight text-foreground">Rincian Pengeluaran Bulan Ini</h2>
                    <p class="text-xs text-muted-foreground">Catatan pemeliharaan dan pengeluaran operasional.</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="border-b border-border bg-muted/40 text-muted-foreground font-semibold">
                            <tr>
                                <th class="p-3">Tanggal / Kategori</th>
                                <th class="p-3">Keterangan</th>
                                <th class="p-3">Nominal</th>
                                <th class="p-3 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            <tr v-if="expenses.length === 0">
                                <td colspan="4" class="p-6 text-center">
                                    <EmptyState
                                        title="Belum ada pengeluaran"
                                        description="Tidak ada catatan biaya operasional pada bulan yang dipilih."
                                    />
                                </td>
                            </tr>
                            <tr v-for="exp in expenses" :key="exp.id" class="hover:bg-muted/30 transition-colors">
                                <td class="p-3 whitespace-nowrap">
                                    <div class="font-medium text-foreground">{{ exp.spent_at }}</div>
                                    <span class="rounded-[4px] border border-border bg-muted/60 px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wider text-muted-foreground">
                                        {{ exp.category }}
                                    </span>
                                </td>
                                <td class="p-3">
                                    <div class="text-foreground">{{ exp.description }}</div>
                                    <div v-if="exp.vehicle" class="text-[11px] text-muted-foreground font-mono">
                                        {{ exp.vehicle.plate }} ({{ exp.vehicle.brand }} {{ exp.vehicle.model }})
                                    </div>
                                </td>
                                <td class="p-3 font-semibold text-rose-700 dark:text-rose-400 whitespace-nowrap">
                                    {{ rupiah(exp.amount) }}
                                </td>
                                <td class="p-3 text-right whitespace-nowrap">
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        class="h-8 w-8 p-0 text-muted-foreground hover:text-destructive cursor-pointer"
                                        @click="confirmDeleteExpense(exp.id)"
                                        title="Hapus Pengeluaran"
                                    >
                                        <Trash2 class="size-4" />
                                    </Button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Confirm Dialog Hapus Pengeluaran (pengganti window.confirm) -->
    <ConfirmDialog
        :open="confirmDeleteOpen"
        title="Hapus Pengeluaran"
        description="Apakah Anda yakin ingin menghapus data pengeluaran operasional ini? Tindakan ini akan memengaruhi laporan laba rugi."
        variant="destructive"
        confirm-text="Ya, Hapus"
        @update:open="confirmDeleteOpen = $event"
        @confirm="handleConfirmDeleteExpense"
    />
</template>
