<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import EmptyState from '@/components/EmptyState.vue';
import InputError from '@/components/InputError.vue';
import PageHeader from '@/components/PageHeader.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { formatDateTime } from '@/lib/format';

interface User {
    id: number;
    name: string;
    email: string;
    phone: string | null;
    address: string | null;
    is_blacklisted: boolean;
    blacklist_reason: string | null;
    created_at: string;
    bookings_count?: number;
}

interface PageLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface Props {
    employees: User[];
    customers: {
        data: User[];
        links: PageLink[];
        total: number;
    };
    filters: { search?: string };
}

const props = defineProps<Props>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Pegawai & Pelanggan', href: '/admin/users' }],
    },
});

const activeTab = ref<'customers' | 'employees'>('customers');
const isCreatingEmployee = ref(false);

const employeeForm = useForm({
    name: '',
    email: '',
    password: '',
    phone: '',
});

function submitEmployee(): void {
    employeeForm.post('/admin/users/employees', {
        onSuccess: () => {
            isCreatingEmployee.value = false;
            employeeForm.reset();
        },
    });
}

// Dialog Blacklist & Restore
const unblacklistConfirmOpen = ref(false);
const blacklistModalOpen = ref(false);
const targetUser = ref<User | null>(null);
const blacklistReasonInput = ref('Pelanggaran ketentuan sewa mobil.');

function toggleBlacklist(user: User): void {
    targetUser.value = user;
    if (user.is_blacklisted) {
        unblacklistConfirmOpen.value = true;
    } else {
        blacklistReasonInput.value = 'Pelanggaran ketentuan sewa mobil.';
        blacklistModalOpen.value = true;
    }
}

function handleConfirmUnblacklist(): void {
    if (!targetUser.value) return;
    useForm({ is_blacklisted: false }).post(`/admin/users/${targetUser.value.id}/blacklist`, {
        onFinish: () => {
            unblacklistConfirmOpen.value = false;
            targetUser.value = null;
        },
    });
}

function handleConfirmBlacklist(): void {
    if (!targetUser.value) return;
    useForm({
        is_blacklisted: true,
        blacklist_reason: blacklistReasonInput.value || 'Pelanggaran ketentuan sewa mobil.',
    }).post(`/admin/users/${targetUser.value.id}/blacklist`, {
        onFinish: () => {
            blacklistModalOpen.value = false;
            targetUser.value = null;
        },
    });
}
</script>

<template>
    <Head title="Kelola Pengguna" />

    <div class="flex flex-col gap-6 p-4 sm:p-6">
        <PageHeader
            title="Pegawai & Pelanggan"
            description="Manajemen akun pegawai lapangan dan pemantauan status pelanggan aktif serta daftar hitam."
        >
            <template #actions>
                <div class="inline-flex rounded-[6px] border border-border bg-muted p-1">
                    <button
                        type="button"
                        class="rounded-[4px] px-3.5 py-1.5 text-xs font-medium transition-colors cursor-pointer min-h-[36px]"
                        :class="activeTab === 'customers' ? 'bg-background text-foreground shadow-xs' : 'text-muted-foreground hover:text-foreground'"
                        @click="activeTab = 'customers'"
                    >
                        Pelanggan ({{ customers.total }})
                    </button>
                    <button
                        type="button"
                        class="rounded-[4px] px-3.5 py-1.5 text-xs font-medium transition-colors cursor-pointer min-h-[36px]"
                        :class="activeTab === 'employees' ? 'bg-background text-foreground shadow-xs' : 'text-muted-foreground hover:text-foreground'"
                        @click="activeTab = 'employees'"
                    >
                        Pegawai ({{ employees.length }})
                    </button>
                </div>
            </template>
        </PageHeader>

        <!-- Tab Pegawai -->
        <div v-if="activeTab === 'employees'" class="flex flex-col gap-4">
            <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-center">
                <div>
                    <h2 class="text-sm font-semibold tracking-tight text-foreground">Daftar Pegawai Lapangan</h2>
                    <p class="text-xs text-muted-foreground">Petugas yang berwenang mengantar, menjemput unit, dan mendokumentasikan foto serah terima.</p>
                </div>
                <Button size="sm" @click="isCreatingEmployee = !isCreatingEmployee">
                    {{ isCreatingEmployee ? 'Tutup Form' : '+ Tambah Pegawai' }}
                </Button>
            </div>

            <!-- Form Tambah Pegawai -->
            <div v-if="isCreatingEmployee" class="rounded-[6px] border border-border bg-card p-5">
                <h3 class="text-sm font-semibold tracking-tight text-foreground mb-3 pb-2 border-b border-border">
                    Pendaftaran Pegawai Baru
                </h3>
                <form class="grid gap-4 sm:grid-cols-2" @submit.prevent="submitEmployee">
                    <div>
                        <Label for="emp_name">Nama Lengkap</Label>
                        <Input id="emp_name" v-model="employeeForm.name" placeholder="Contoh: Budi Santoso" class="h-10 min-h-[40px] mt-1" required />
                        <InputError :message="employeeForm.errors.name" />
                    </div>
                    <div>
                        <Label for="emp_email">Alamat Email</Label>
                        <Input id="emp_email" v-model="employeeForm.email" type="email" placeholder="pegawai@rental.test" class="h-10 min-h-[40px] mt-1" required />
                        <InputError :message="employeeForm.errors.email" />
                    </div>
                    <div>
                        <Label for="emp_pass">Kata Sandi</Label>
                        <Input id="emp_pass" v-model="employeeForm.password" type="password" placeholder="Minimal 8 karakter" class="h-10 min-h-[40px] mt-1" required />
                        <InputError :message="employeeForm.errors.password" />
                    </div>
                    <div>
                        <Label for="emp_phone">Nomor Telepon / WhatsApp</Label>
                        <Input id="emp_phone" v-model="employeeForm.phone" placeholder="08123456789" class="h-10 min-h-[40px] mt-1" />
                        <InputError :message="employeeForm.errors.phone" />
                    </div>

                    <div class="sm:col-span-2 flex justify-end gap-2 border-t border-border pt-4">
                        <Button type="button" variant="outline" class="h-10 min-h-[40px]" @click="isCreatingEmployee = false">
                            Batal
                        </Button>
                        <Button type="submit" class="h-10 min-h-[40px]" :disabled="employeeForm.processing">
                            <Spinner v-if="employeeForm.processing" class="mr-2" />
                            Simpan Akun Pegawai
                        </Button>
                    </div>
                </form>
            </div>

            <!-- Tabel Pegawai -->
            <div class="overflow-x-auto rounded-[6px] border border-border bg-card">
                <table class="w-full text-left text-xs">
                    <thead class="border-b border-border bg-muted/40 text-muted-foreground font-semibold">
                        <tr>
                            <th class="p-3">Nama Pegawai</th>
                            <th class="p-3">Email</th>
                            <th class="p-3">No. Telepon</th>
                            <th class="p-3">Terdaftar Sejak</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        <tr v-if="employees.length === 0">
                            <td colspan="4" class="p-6 text-center">
                                <EmptyState
                                    title="Belum ada pegawai"
                                    description="Tambahkan akun pegawai baru untuk ditugaskan pada pesanan rental."
                                />
                            </td>
                        </tr>
                        <tr v-for="emp in employees" :key="emp.id" class="hover:bg-muted/30 transition-colors">
                            <td class="p-3 font-medium text-foreground">{{ emp.name }}</td>
                            <td class="p-3 text-muted-foreground">{{ emp.email }}</td>
                            <td class="p-3 text-muted-foreground">{{ emp.phone ?? '-' }}</td>
                            <td class="p-3 text-muted-foreground whitespace-nowrap">{{ formatDateTime(emp.created_at) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Tab Pelanggan -->
        <div v-else class="flex flex-col gap-4">
            <div>
                <h2 class="text-sm font-semibold tracking-tight text-foreground">Data Pelanggan (Member)</h2>
                <p class="text-xs text-muted-foreground">Total {{ customers.total }} pelanggan terdaftar dalam sistem.</p>
            </div>

            <!-- Tabel Pelanggan -->
            <div class="overflow-x-auto rounded-[6px] border border-border bg-card">
                <table class="w-full text-left text-xs">
                    <thead class="border-b border-border bg-muted/40 text-muted-foreground font-semibold">
                        <tr>
                            <th class="p-3">Nama Pelanggan</th>
                            <th class="p-3">Kontak</th>
                            <th class="p-3">Alamat</th>
                            <th class="p-3">Total Pesanan</th>
                            <th class="p-3">Status</th>
                            <th class="p-3 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        <tr v-if="customers.data.length === 0">
                            <td colspan="6" class="p-6 text-center">
                                <EmptyState
                                    title="Belum ada pelanggan"
                                    description="Pelanggan yang mendaftar akan tampil di daftar ini."
                                />
                            </td>
                        </tr>
                        <tr v-for="c in customers.data" :key="c.id" class="hover:bg-muted/30 transition-colors">
                            <td class="p-3">
                                <div class="font-medium text-foreground">{{ c.name }}</div>
                                <div class="text-[11px] text-muted-foreground">Bergabung: {{ formatDateTime(c.created_at) }}</div>
                            </td>
                            <td class="p-3">
                                <div class="text-foreground">{{ c.email }}</div>
                                <div class="text-[11px] text-muted-foreground">{{ c.phone ?? '-' }}</div>
                            </td>
                            <td class="p-3 text-muted-foreground max-w-xs truncate">{{ c.address ?? '-' }}</td>
                            <td class="p-3 font-medium whitespace-nowrap">{{ c.bookings_count ?? 0 }} booking</td>
                            <td class="p-3 whitespace-nowrap">
                                <StatusBadge :status="c.is_blacklisted ? 'blacklisted' : 'active'" />
                                <div v-if="c.is_blacklisted" class="text-[11px] text-destructive mt-0.5">
                                    {{ c.blacklist_reason }}
                                </div>
                            </td>
                            <td class="p-3 text-right whitespace-nowrap">
                                <Button
                                    size="sm"
                                    class="h-8 text-xs"
                                    :variant="c.is_blacklisted ? 'outline' : 'destructive'"
                                    @click="toggleBlacklist(c)"
                                >
                                    {{ c.is_blacklisted ? 'Pulihkan' : 'Blacklist' }}
                                </Button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <nav v-if="customers.links.length > 3" class="flex flex-wrap gap-1">
                <template v-for="(link, index) in customers.links" :key="index">
                    <Link
                        v-if="link.url"
                        :href="link.url"
                        class="rounded-[6px] border border-border px-3 py-1.5 text-xs font-medium min-h-[36px] flex items-center justify-center"
                        :class="link.active ? 'bg-primary text-primary-foreground border-primary' : 'bg-background hover:bg-muted text-foreground'"
                        preserve-scroll
                        v-html="link.label"
                    />
                    <span
                        v-else
                        class="rounded-[6px] border border-border px-3 py-1.5 text-xs text-muted-foreground opacity-40 min-h-[36px] flex items-center justify-center"
                        v-html="link.label"
                    />
                </template>
            </nav>
        </div>

        <!-- Confirm Unblacklist Dialog -->
        <ConfirmDialog
            :open="unblacklistConfirmOpen"
            title="Pulihkan Akun Pelanggan"
            :description="`Apakah Anda yakin ingin memulihkan akun ${targetUser?.name} dari daftar hitam? Pelanggan ini akan dapat kembali memesan kendaraan.`"
            variant="default"
            confirm-text="Ya, Pulihkan Akun"
            @update:open="unblacklistConfirmOpen = $event"
            @confirm="handleConfirmUnblacklist"
        />

        <!-- Modal Input Alasan Blacklist -->
        <Dialog :open="blacklistModalOpen" @update:open="blacklistModalOpen = $event">
            <DialogContent class="sm:max-w-[425px]">
                <DialogHeader>
                    <DialogTitle class="text-destructive">Masukkan ke Daftar Hitam (Blacklist)</DialogTitle>
                    <DialogDescription>
                        Akun {{ targetUser?.name }} tidak dapat login atau melakukan pemesanan sewa selama status blacklist aktif.
                    </DialogDescription>
                </DialogHeader>

                <div class="py-2">
                    <Label for="reason">Alasan Blacklist</Label>
                    <Input id="reason" v-model="blacklistReasonInput" placeholder="Masukkan alasan pelanggaran..." class="mt-1" />
                </div>

                <DialogFooter>
                    <Button variant="outline" @click="blacklistModalOpen = false">Batal</Button>
                    <Button variant="destructive" @click="handleConfirmBlacklist">Blacklist Pelanggan</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
