<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, CheckCircle2, Printer } from '@lucide/vue';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import { formatDateTime, rupiah } from '@/lib/format';

interface Props {
    booking: any;
}

const props = defineProps<Props>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Booking Saya', href: '/bookings' },
            { title: 'Invoice', href: '#' },
        ],
    },
});

const verifiedPayments = computed(() => props.booking.payments ?? []);
const totalPaid = computed(() => {
    return verifiedPayments.value.reduce((sum: number, p: any) => sum + Number(p.amount), 0);
});

const damagesTotal = computed(() => {
    return (props.booking.damages ?? []).reduce((sum: number, d: any) => sum + Number(d.fine_amount), 0);
});

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

const lateFeeTotal = computed(() => Number(props.booking.late_fee_total ?? 0));
const rentalTotal = computed(() => Number(props.booking.total_price ?? 0));
const grandTotal = computed(() => rentalTotal.value + lateFeeTotal.value + damagesTotal.value);
const remainingBalance = computed(() => Math.max(0, grandTotal.value - totalPaid.value));

function printInvoice() {
    window.print();
}
</script>

<template>
    <Head :title="`Invoice - ${booking.booking_code}`" />

    <div class="mx-auto max-w-4xl p-4 sm:p-6 space-y-6">
        <!-- Action Buttons (Hidden on Print) -->
        <div class="flex items-center justify-between print:hidden">
            <Button as-child variant="outline" size="sm">
                <Link :href="`/bookings/${booking.id}`">
                    <ArrowLeft class="mr-1.5 h-4 w-4" />
                    Kembali
                </Link>
            </Button>
            <Button size="sm" @click="printInvoice">
                <Printer class="mr-1.5 h-4 w-4" />
                Cetak Invoice / PDF
            </Button>
        </div>

        <!-- Invoice Printable Paper -->
        <div class="rounded-xl border bg-card p-8 shadow-sm text-foreground print:border-none print:shadow-none print:p-0">
            <!-- Header -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between border-b pb-6 gap-4">
                <div>
                    <h1 class="text-2xl font-black tracking-tight uppercase">INVOICE RENTAL MOBIL</h1>
                    <p class="text-sm text-muted-foreground mt-0.5">Bukti Resmi Transaksi Sewa Kendaraan</p>
                </div>
                <div class="sm:text-right">
                    <div class="text-xs uppercase font-semibold text-muted-foreground">Nomor Invoice / Booking</div>
                    <div class="font-mono text-xl font-bold text-primary">{{ booking.booking_code }}</div>
                    <div class="text-xs text-muted-foreground mt-1">Tanggal: {{ formatDateTime(booking.created_at) }}</div>
                </div>
            </div>

            <!-- Customer & Rental Info -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 py-6 border-b text-sm">
                <div>
                    <h3 class="text-xs font-bold uppercase tracking-wider text-muted-foreground mb-2">Informasi Penyewa</h3>
                    <div class="font-semibold text-base">{{ booking.user?.name }}</div>
                    <div class="text-muted-foreground">{{ booking.user?.email }}</div>
                    <div class="text-muted-foreground">{{ booking.user?.phone || '-' }}</div>
                    <div class="text-muted-foreground mt-1 text-xs">Alamat: {{ booking.user?.address || '-' }}</div>
                </div>
                <div>
                    <h3 class="text-xs font-bold uppercase tracking-wider text-muted-foreground mb-2">Detail Pengantaran & Jadwal</h3>
                    <div class="space-y-1">
                        <div><span class="text-muted-foreground">Tujuan:</span> {{ booking.destination }}</div>
                        <div><span class="text-muted-foreground">Alamat Serah Terima:</span> {{ booking.delivery_address }}</div>
                        <div><span class="text-muted-foreground">Mulai Sewa:</span> {{ formatDateTime(booking.pickup_at) }}</div>
                        <div><span class="text-muted-foreground">Selesai Sewa:</span> {{ formatDateTime(booking.return_at) }}</div>
                        <div><span class="text-muted-foreground">Durasi:</span> {{ booking.duration_days }} Hari</div>
                    </div>
                </div>
            </div>

            <!-- Unit Details Table -->
            <div class="py-6 border-b">
                <h3 class="text-xs font-bold uppercase tracking-wider text-muted-foreground mb-3">Rincian Kendaraan & Biaya</h3>
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="border-b text-xs text-muted-foreground uppercase font-semibold">
                            <th class="py-2">Deskripsi</th>
                            <th class="py-2 text-center">Unit / Plat</th>
                            <th class="py-2 text-right">Tarif / Hari</th>
                            <th class="py-2 text-right">Jumlah</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y text-sm">
                        <tr>
                            <td class="py-3 font-medium">
                                Sewa Pokok {{ booking.vehicle?.brand }} {{ booking.vehicle?.model }} ({{ durationBreakdown.days }} Hari / {{ durationBreakdown.days * 24 }} Jam)
                            </td>
                            <td class="py-3 text-center font-mono">{{ booking.vehicle?.plate }}</td>
                            <td class="py-3 text-right">{{ rupiah(booking.price_per_day) }}</td>
                            <td class="py-3 text-right font-semibold">{{ rupiah(durationBreakdown.basePrice) }}</td>
                        </tr>
                        <tr v-if="durationBreakdown.extraHours > 0">
                            <td class="py-3 font-medium text-amber-700 dark:text-amber-400">
                                Kelebihan Jam Sewa (+{{ durationBreakdown.extraHours }} Jam)
                            </td>
                            <td class="py-3 text-center">-</td>
                            <td class="py-3 text-right">{{ rupiah(booking.vehicle?.late_fee_per_hour) }} / jam</td>
                            <td class="py-3 text-right font-semibold text-amber-700 dark:text-amber-400">{{ rupiah(durationBreakdown.overtimeFee) }}</td>
                        </tr>
                        <tr v-if="lateFeeTotal > 0">
                            <td class="py-3 text-rose-600 font-medium">Denda Keterlambatan Pengembalian Unit</td>
                            <td class="py-3 text-center">-</td>
                            <td class="py-3 text-right">{{ rupiah(booking.vehicle?.late_fee_per_hour) }} / jam</td>
                            <td class="py-3 text-right text-rose-600 font-semibold">{{ rupiah(lateFeeTotal) }}</td>
                        </tr>
                        <tr v-for="dmg in booking.damages" :key="dmg.id">
                            <td class="py-3 text-rose-600 font-medium">
                                Denda Kerusakan: {{ dmg.description }}
                            </td>
                            <td class="py-3 text-center">-</td>
                            <td class="py-3 text-right">-</td>
                            <td class="py-3 text-right text-rose-600 font-semibold">{{ rupiah(dmg.fine_amount) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Payment Summary & Balance -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 pt-6 text-sm">
                <!-- Payment History -->
                <div>
                    <h3 class="text-xs font-bold uppercase tracking-wider text-muted-foreground mb-2">Riwayat Pembayaran Terverifikasi</h3>
                    <div v-if="verifiedPayments.length === 0" class="text-xs text-muted-foreground italic">
                        Belum ada pembayaran yang terverifikasi.
                    </div>
                    <ul v-else class="space-y-2 text-xs">
                        <li v-for="p in verifiedPayments" :key="p.id" class="flex justify-between border-b pb-1">
                            <div>
                                <span class="font-semibold uppercase">{{ p.type }}</span>
                                <span class="text-muted-foreground"> ({{ p.payment_method?.label || 'Transfer' }})</span>
                                <div class="text-[10px] text-muted-foreground">{{ formatDateTime(p.verified_at || p.created_at) }}</div>
                            </div>
                            <span class="font-semibold text-emerald-600">{{ rupiah(p.amount) }}</span>
                        </li>
                    </ul>
                </div>

                <!-- Grand Total Calculation -->
                <div class="space-y-2">
                    <div class="flex justify-between text-muted-foreground text-xs">
                        <span>Sewa Pokok ({{ durationBreakdown.days }} hari)</span>
                        <span>{{ rupiah(durationBreakdown.basePrice) }}</span>
                    </div>
                    <div v-if="durationBreakdown.extraHours > 0" class="flex justify-between text-amber-600 dark:text-amber-400 text-xs">
                        <span>Lebihan Jam (+{{ durationBreakdown.extraHours }} jam)</span>
                        <span>+{{ rupiah(durationBreakdown.overtimeFee) }}</span>
                    </div>
                    <div class="flex justify-between text-xs font-medium border-t pt-1">
                        <span>Total Biaya Sewa Pesanan</span>
                        <span>{{ rupiah(rentalTotal) }}</span>
                    </div>
                    <div v-if="lateFeeTotal > 0" class="flex justify-between text-rose-600 text-xs">
                        <span>Total Denda Telat Pengembalian</span>
                        <span>+{{ rupiah(lateFeeTotal) }}</span>
                    </div>
                    <div v-if="damagesTotal > 0" class="flex justify-between text-rose-600 text-xs">
                        <span>Total Denda Kerusakan</span>
                        <span>+{{ rupiah(damagesTotal) }}</span>
                    </div>
                    <div class="flex justify-between font-bold text-base border-t pt-2">
                        <span>Total Tagihan</span>
                        <span>{{ rupiah(grandTotal) }}</span>
                    </div>
                    <div class="flex justify-between text-emerald-600 text-sm font-semibold">
                        <span>Total Telah Dibayar</span>
                        <span>-{{ rupiah(totalPaid) }}</span>
                    </div>
                    <div class="flex justify-between border-t border-dashed pt-2 font-bold text-base">
                        <span>Sisa Pembayaran</span>
                        <span :class="remainingBalance > 0 ? 'text-rose-600' : 'text-emerald-600'">
                            {{ remainingBalance > 0 ? rupiah(remainingBalance) : 'LUNAS' }}
                        </span>
                    </div>

                    <div v-if="remainingBalance === 0" class="mt-4 flex items-center gap-2 rounded-lg bg-emerald-50 p-2 text-xs font-semibold text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-400">
                        <CheckCircle2 class="h-4 w-4 shrink-0" />
                        <span>Transaksi sewa ini telah lunas seluruhnya. Terima kasih.</span>
                    </div>
                </div>
            </div>

            <!-- Footer / Notes -->
            <div class="mt-10 border-t pt-4 text-center text-xs text-muted-foreground">
                <p>Dokumen ini adalah bukti transaksi digital resmi dari sistem Rental Mobil.</p>
                <p class="mt-0.5">Harap simpan invoice ini sebagai referensi perjalanan Anda.</p>
            </div>
        </div>
    </div>
</template>

<style scoped>
@media print {
    body {
        background-color: white !important;
        color: black !important;
    }
}
</style>
