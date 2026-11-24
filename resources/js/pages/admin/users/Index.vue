<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import { Badge } from '@/components/ui/badge';
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

    <div class="flex flex-col gap-6 p-4">
        <!-- Header & Tab Navigasi -->
        <div class="flex flex-col justify-between gap-4 border-b pb-4 sm:flex-row sm:items-center">
            <div>
                <h1 class="text-2xl font-bold">Manajemen Pengguna</h1>
                <p class="text-sm text-muted-foreground">Kelola akun pelanggan, daftar hitam (blacklist), dan akun petugas pegawai.</p>
            </div>
            <div class="flex rounded-lg border bg-muted p-1">
                <button
                    type="button"
                    class="rounded-md px-4 py-1.5 text-sm font-medium transition-all"
                    :class="activeTab === 'customers' ? 'bg-background text-foreground shadow-xs' : 'text-muted-foreground hover:text-foreground'"
                    @click="activeTab = 'customers'"
                >
                    Data Pelanggan ({{ customers.total }})
                </button>
                <button
                    type="button"
                    class="rounded-md px-4 py-1.5 text-sm font-medium transition-all"
                    :class="activeTab === 'employees' ? 'bg-background text-foreground shadow-xs' : 'text-muted-foreground hover:text-foreground'"
                    @click="activeTab = 'employees'"
                >
                    Data Pegawai ({{ employees.length }})
                </button>
            </div>
        </div>

        <!-- Tab Pegawai -->
        <div v-if="activeTab === 'employees'" class="flex flex-col gap-4">
            <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-center">
                <div>
                    <h2 class="text-lg font-semibold">Petugas Pegawai Lapangan</h2>
                    <p class="text-sm text-muted-foreground">Pegawai yang bertugas mengantar dan mengambil unit kendaraan.</p>
                </div>
                <Button @click="isCreatingEmployee = !isCreatingEmployee">
                    {{ isCreatingEmployee ? 'Tutup Form' : '+ Tambah Pegawai Baru' }}
                </Button>
            </div>

            <!-- Form Tambah Pegawai -->
            <div v-if="isCreatingEmployee" class="rounded-xl border bg-card p-5 shadow-xs">
                <h3 class="text-base font-semibold mb-3">Pendaftaran Pegawai Baru</h3>
                <form class="grid gap-4 sm:grid-cols-2" @submit.prevent="submitEmployee">
                    <div>
                        <Label for="emp_name">Nama Lengkap</Label>
                        <Input id="emp_name" v-model="employeeForm.name" placeholder="Budi Santoso" class="mt-1" required />
                    </div>
                    <div>
                        <Label for="emp_email">Alamat Email</Label>
                        <Input id="emp_email" v-model="employeeForm.email" type="email" placeholder="pegawai@rental.test" class="mt-1" required />
                    </div>
                    <div>
                        <Label for="emp_pass">Password</Label>
                        <Input id="emp_pass" v-model="employeeForm.password" type="password" placeholder="Minimal 8 karakter" class="mt-1" required />
                    </div>
                    <div>
                        <Label for="emp_phone">Nomor Telepon / WA</Label>
                        <Input id="emp_phone" v-model="employeeForm.phone" placeholder="08123456789" class="mt-1" />
                    </div>

                    <div class="sm:col-span-2 flex justify-end gap-2 border-t pt-3">
                        <Button type="button" variant="outline" @click="isCreatingEmployee = false">Batal</Button>
                        <Button type="submit" :disabled="employeeForm.processing">Simpan Akun Pegawai</Button>
                    </div>
                </form>
            </div>

            <!-- Tabel Pegawai -->
            <div class="overflow-x-auto rounded-xl border bg-card">
                <table class="w-full text-sm">
                    <thead class="bg-muted/50 text-left">
                        <tr>
                            <th class="p-3">Nama Pegawai</th>
                            <th class="p-3">Email</th>
                            <th class="p-3">No. Telepon</th>
                            <th class="p-3">Terdaftar Sejak</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="employees.length === 0">
                            <td colspan="4" class="p-6 text-center text-muted-foreground">Belum ada akun pegawai.</td>
                        </tr>
                        <tr v-for="emp in employees" :key="emp.id" class="border-t">
                            <td class="p-3 font-semibold">{{ emp.name }}</td>
                            <td class="p-3">{{ emp.email }}</td>
                            <td class="p-3">{{ emp.phone ?? '-' }}</td>
                            <td class="p-3 text-muted-foreground">{{ formatDateTime(emp.created_at) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Tab Pelanggan -->
        <div v-else class="flex flex-col gap-4">
            <div>
                <h2 class="text-lg font-semibold">Data Pelanggan (Member)</h2>
                <p class="text-sm text-muted-foreground">Total {{ customers.total }} pelanggan terdaftar dalam sistem.</p>
            </div>

            <!-- Tabel Pelanggan -->
            <div class="overflow-x-auto rounded-xl border bg-card">
                <table class="w-full text-sm">
                    <thead class="bg-muted/50 text-left">
                        <tr>
                            <th class="p-3">Nama Pelanggan</th>
                            <th class="p-3">Kontak</th>
                            <th class="p-3">Alamat</th>
                            <th class="p-3">Total Pesanan</th>
                            <th class="p-3">Status</th>
                            <th class="p-3 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="customers.data.length === 0">
                            <td colspan="6" class="p-6 text-center text-muted-foreground">Belum ada data pelanggan.</td>
                        </tr>
                        <tr v-for="c in customers.data" :key="c.id" class="border-t">
                            <td class="p-3">
                                <div class="font-semibold">{{ c.name }}</div>
                                <div class="text-xs text-muted-foreground">Bergabung: {{ formatDateTime(c.created_at) }}</div>
                            </td>
                            <td class="p-3">
                                <div>{{ c.email }}</div>
                                <div class="text-xs text-muted-foreground">{{ c.phone ?? '-' }}</div>
                            </td>
                            <td class="p-3 text-xs max-w-xs truncate">{{ c.address ?? '-' }}</td>
                            <td class="p-3 font-medium">{{ c.bookings_count ?? 0 }} booking</td>
                            <td class="p-3">
                                <Badge v-if="c.is_blacklisted" variant="destructive">
                                    Blacklisted
                                </Badge>
                                <Badge v-else variant="default">
                                    Aktif
                                </Badge>
                                <div v-if="c.is_blacklisted" class="text-[11px] text-destructive mt-1 font-medium">
                                    Alasan: {{ c.blacklist_reason }}
                                </div>
                            </td>
                            <td class="p-3 text-right">
                                <Button
                                    size="sm"
                                    :variant="c.is_blacklisted ? 'outline' : 'destructive'"
                                    @click="toggleBlacklist(c)"
                                >
                                    {{ c.is_blacklisted ? 'Pulihkan (Unblacklist)' : 'Blacklist' }}
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
                        class="rounded-md border px-3 py-1 text-sm"
                        :class="link.active ? 'bg-primary text-primary-foreground' : 'hover:bg-muted'"
                        preserve-scroll
                        v-html="link.label"
                    />
                    <span
                        v-else
                        class="rounded-md border px-3 py-1 text-sm text-muted-foreground opacity-50"
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
                        Akun {{ targetUser?.name }} tidak akan dapat login atau melakukan pemesanan kendaraan selama diblacklist.
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
