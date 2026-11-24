<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import {
    ArrowDownRight,
    ArrowUpRight,
    CheckCircle2,
    DollarSign,
    Filter,
    Plus,
    Trash2,
    TrendingUp,
} from '@lucide/vue';
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatDateTime, rupiah } from '@/lib/format';

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

function applyFilter() {
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

function submitExpense() {
    form.post('/admin/finance/expenses', {
        onSuccess: () => {
            form.reset();
            form.spent_at = new Date().toISOString().split('T')[0];
            showAddModal.value = false;
        },
    });
}

function deleteExpense(id: number) {
    if (confirm('Yakin ingin menghapus pengeluaran ini?')) {
        router.delete(`/admin/finance/expenses/${id}`);
    }
}
</script>

<template>
    <Head title="Keuangan Admin" />

    <div class="space-y-6 p-6">
        <!-- Header & Filter -->
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-bold tracking-tight">Laporan Keuangan</h1>
                <p class="text-sm text-muted-foreground">
                    Ringkasan pendapatan sewa, pengeluaran operasional, dan analisis balik modal armada.
                </p>
            </div>
            <div class="flex items-center gap-3">
                <div class="flex items-center gap-2">
                    <Label for="month_filter" class="text-xs text-muted-foreground">Bulan:</Label>
                    <Input
                        id="month_filter"
                        v-model="monthFilter"
                        type="month"
                        class="w-40 h-9 text-xs"
                    />
                    <Button variant="outline" size="sm" @click="applyFilter">
                        <Filter class="h-3.5 w-3.5 mr-1" />
                        Terapkan
                    </Button>
                </div>
                <Button size="sm" @click="showAddModal = !showAddModal">
                    <Plus class="h-4 w-4 mr-1" />
                    Catat Biaya
                </Button>
            </div>
        </div>

        <!-- Summary Cards -->
        <div class="grid gap-4 sm:grid-cols-3">
            <Card>
                <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                    <CardTitle class="text-sm font-medium">Pendapatan Bulan Ini</CardTitle>
                    <ArrowUpRight class="h-4 w-4 text-emerald-600" />
                </CardHeader>
                <CardContent>
                    <div class="text-2xl font-bold text-emerald-600">{{ rupiah(summary.revenue) }}</div>
                    <p class="text-xs text-muted-foreground">Dari seluruh pembayaran terverifikasi</p>
                </CardContent>
            </Card>

            <Card>
                <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                    <CardTitle class="text-sm font-medium">Pengeluaran Bulan Ini</CardTitle>
                    <ArrowDownRight class="h-4 w-4 text-rose-600" />
                </CardHeader>
                <CardContent>
                    <div class="text-2xl font-bold text-rose-600">{{ rupiah(summary.expense) }}</div>
                    <p class="text-xs text-muted-foreground">Biaya servis, oli, perbaikan, dsb.</p>
                </CardContent>
            </Card>

            <Card>
                <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                    <CardTitle class="text-sm font-medium">Laba Bersih Bulan Ini</CardTitle>
                    <TrendingUp class="h-4 w-4 text-primary" />
                </CardHeader>
                <CardContent>
                    <div
                        class="text-2xl font-bold"
                        :class="summary.net_profit >= 0 ? 'text-primary' : 'text-rose-600'"
                    >
                        {{ rupiah(summary.net_profit) }}
                    </div>
                    <p class="text-xs text-muted-foreground">Pendapatan dikurangi pengeluaran</p>
                </CardContent>
            </Card>
        </div>

        <!-- Modal Tambah Pengeluaran -->
        <div v-if="showAddModal" class="rounded-lg border bg-card p-5 shadow-sm space-y-4">
            <h3 class="text-base font-semibold">Form Pencatatan Pengeluaran</h3>
            <form @submit.prevent="submitExpense" class="grid gap-4 sm:grid-cols-2">
                <div class="grid gap-1">
                    <Label for="exp_category">Kategori</Label>
                    <select
                        id="exp_category"
                        v-model="form.category"
                        class="h-9 rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                    >
                        <option value="oli">Oli</option>
                        <option value="perbaikan">Perbaikan</option>
                        <option value="servis">Servis</option>
                        <option value="pajak">Pajak</option>
                        <option value="lainnya">Lainnya</option>
                    </select>
                    <InputError :message="form.errors.category" />
                </div>

                <div class="grid gap-1">
                    <Label for="exp_vehicle">Kendaraan Terkait (Opsional)</Label>
                    <select
                        id="exp_vehicle"
                        v-model="form.vehicle_id"
                        class="h-9 rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                    >
                        <option value="">-- Umum / Tanpa Kendaraan --</option>
                        <option v-for="v in vehicle_options" :key="v.id" :value="v.id">
                            {{ v.plate }} - {{ v.brand }} {{ v.model }}
                        </option>
                    </select>
                    <InputError :message="form.errors.vehicle_id" />
                </div>

                <div class="grid gap-1">
                    <Label for="exp_amount">Nominal Biaya (Rp)</Label>
                    <Input id="exp_amount" v-model="form.amount" type="number" placeholder="150000" />
                    <InputError :message="form.errors.amount" />
                </div>

                <div class="grid gap-1">
                    <Label for="exp_date">Tanggal Pengeluaran</Label>
                    <Input id="exp_date" v-model="form.spent_at" type="date" />
                    <InputError :message="form.errors.spent_at" />
                </div>

                <div class="grid gap-1 sm:col-span-2">
                    <Label for="exp_desc">Keterangan</Label>
                    <Input id="exp_desc" v-model="form.description" placeholder="Ganti oli Shell Helix + filter" />
                    <InputError :message="form.errors.description" />
                </div>

                <div class="flex gap-2 sm:col-span-2">
                    <Button type="submit" size="sm" :disabled="form.processing">
                        {{ form.processing ? 'Menyimpan...' : 'Simpan Pengeluaran' }}
                    </Button>
                    <Button type="button" variant="outline" size="sm" @click="showAddModal = false">
                        Batal
                    </Button>
                </div>
            </form>
        </div>

        <!-- Section: Balik Modal Armada (All Time ROI) -->
        <div class="space-y-4">
            <div>
                <h2 class="text-lg font-bold tracking-tight">Analisis Balik Modal Armada</h2>
                <p class="text-sm text-muted-foreground">
                    Perhitungan ROI kumulatif berdasarkan harga beli, laba bersih seumur unit, dan proyeksi sewa.
                </p>
            </div>

            <div class="overflow-x-auto rounded-lg border bg-card">
                <table class="w-full text-left text-sm">
                    <thead class="border-b bg-muted/50 text-xs font-semibold uppercase text-muted-foreground">
                        <tr>
                            <th class="px-4 py-3">Kendaraan</th>
                            <th class="px-4 py-3">Harga Beli</th>
                            <th class="px-4 py-3">Laba Terkumpul</th>
                            <th class="px-4 py-3">% ROI</th>
                            <th class="px-4 py-3">Sewa Selesai</th>
                            <th class="px-4 py-3">Rata2 Laba/Sewa</th>
                            <th class="px-4 py-3">Status Balik Modal</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr v-for="item in roi_data" :key="item.vehicle_id" class="hover:bg-muted/30">
                            <td class="px-4 py-3 font-medium">
                                <div>{{ item.brand_model }}</div>
                                <div class="text-xs text-muted-foreground">{{ item.plate }}</div>
                            </td>
                            <td class="px-4 py-3">
                                {{ item.purchase_price > 0 ? rupiah(item.purchase_price) : '-' }}
                            </td>
                            <td class="px-4 py-3 font-semibold" :class="item.accumulated_net_profit >= 0 ? 'text-primary' : 'text-rose-600'">
                                {{ rupiah(item.accumulated_net_profit) }}
                            </td>
                            <td class="px-4 py-3">
                                <span v-if="item.purchase_price > 0" class="font-medium">
                                    {{ item.roi_percent }}%
                                </span>
                                <span v-else class="text-muted-foreground">-</span>
                            </td>
                            <td class="px-4 py-3">{{ item.completed_rentals_count }} kali</td>
                            <td class="px-4 py-3">
                                {{ item.completed_rentals_count > 0 ? rupiah(item.avg_profit_per_rental) : '-' }}
                            </td>
                            <td class="px-4 py-3">
                                <Badge v-if="item.is_break_even" variant="default" class="bg-emerald-600 hover:bg-emerald-700">
                                    <CheckCircle2 class="mr-1 h-3.5 w-3.5" />
                                    Sudah balik modal
                                </Badge>
                                <div v-else-if="item.purchase_price > 0 && item.rentals_remaining !== null">
                                    <Badge variant="outline" class="text-xs">
                                        Butuh {{ item.rentals_needed }} sewa
                                    </Badge>
                                    <div class="text-xs text-muted-foreground mt-0.5">
                                        Sisa: {{ item.rentals_remaining }} sewa lagi
                                    </div>
                                </div>
                                <span v-else class="text-xs text-muted-foreground">
                                    {{ item.purchase_price > 0 ? 'Belum cukup data' : 'Harga beli belum diset' }}
                                </span>
                            </td>
                        </tr>
                        <tr v-if="roi_data.length === 0">
                            <td colspan="7" class="py-6 text-center text-sm text-muted-foreground">
                                Belum ada data kendaraan.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Section: Pengeluaran Bulan Terpilih & Breakdown Kendaraan -->
        <div class="grid gap-6 lg:grid-cols-2">
            <!-- Breakdown Pendapatan & Pengeluaran Per Kendaraan (Bulan Terpilih) -->
            <div class="space-y-4">
                <h2 class="text-base font-bold tracking-tight">Kinerja Armada Bulan Ini</h2>
                <div class="overflow-x-auto rounded-lg border bg-card">
                    <table class="w-full text-left text-sm">
                        <thead class="border-b bg-muted/50 text-xs font-semibold uppercase text-muted-foreground">
                            <tr>
                                <th class="px-4 py-3">Kendaraan</th>
                                <th class="px-4 py-3">Pendapatan</th>
                                <th class="px-4 py-3">Pengeluaran</th>
                                <th class="px-4 py-3">Laba Bersih</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr v-for="b in monthly_vehicle_breakdown" :key="b.vehicle_id">
                                <td class="px-4 py-2.5 font-medium">
                                    <div>{{ b.brand_model }}</div>
                                    <div class="text-xs text-muted-foreground">{{ b.plate }}</div>
                                </td>
                                <td class="px-4 py-2.5 text-emerald-600">{{ rupiah(b.revenue) }}</td>
                                <td class="px-4 py-2.5 text-rose-600">{{ rupiah(b.expense) }}</td>
                                <td class="px-4 py-2.5 font-semibold" :class="b.net_profit >= 0 ? 'text-primary' : 'text-rose-600'">
                                    {{ rupiah(b.net_profit) }}
                                </td>
                            </tr>
                            <tr v-if="monthly_vehicle_breakdown.length === 0">
                                <td colspan="4" class="py-6 text-center text-sm text-muted-foreground">
                                    Tidak ada kendaraan.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Daftar Pengeluaran Bulan Terpilih -->
            <div class="space-y-4">
                <h2 class="text-base font-bold tracking-tight">Rincian Pengeluaran Bulan Ini</h2>
                <div class="overflow-x-auto rounded-lg border bg-card">
                    <table class="w-full text-left text-sm">
                        <thead class="border-b bg-muted/50 text-xs font-semibold uppercase text-muted-foreground">
                            <tr>
                                <th class="px-4 py-3">Tanggal / Kategori</th>
                                <th class="px-4 py-3">Keterangan</th>
                                <th class="px-4 py-3">Nominal</th>
                                <th class="px-4 py-3">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr v-for="exp in expenses" :key="exp.id">
                                <td class="px-4 py-2.5">
                                    <div class="font-medium text-xs">{{ exp.spent_at }}</div>
                                    <Badge variant="secondary" class="text-[10px] uppercase mt-0.5">
                                        {{ exp.category }}
                                    </Badge>
                                </td>
                                <td class="px-4 py-2.5 text-xs">
                                    <div class="text-foreground">{{ exp.description }}</div>
                                    <div v-if="exp.vehicle" class="text-muted-foreground text-[11px]">
                                        Unit: {{ exp.vehicle.plate }} ({{ exp.vehicle.brand }} {{ exp.vehicle.model }})
                                    </div>
                                </td>
                                <td class="px-4 py-2.5 text-rose-600 font-semibold text-xs">
                                    {{ rupiah(exp.amount) }}
                                </td>
                                <td class="px-4 py-2.5">
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        class="h-7 w-7 p-0 text-muted-foreground hover:text-destructive"
                                        @click="deleteExpense(exp.id)"
                                        title="Hapus Pengeluaran"
                                    >
                                        <Trash2 class="h-3.5 w-3.5" />
                                    </Button>
                                </td>
                            </tr>
                            <tr v-if="expenses.length === 0">
                                <td colspan="4" class="py-6 text-center text-sm text-muted-foreground">
                                    Belum ada pengeluaran dicatat untuk bulan ini.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</template>
