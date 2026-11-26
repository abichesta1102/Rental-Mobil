<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { register } from '@/routes';
import { request } from '@/routes/password';
import { Loader2 } from '@lucide/vue';

defineOptions({
    layout: {
        title: 'Masuk ke Akun',
        description: 'Masukkan email dan kata sandi untuk mengakses sistem',
    },
});

defineProps<{
    status?: string;
    canResetPassword?: boolean;
}>();

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const isDev = import.meta.env.DEV;

function fillDemoAccount(role: 'admin' | 'pegawai' | 'pelanggan'): void {
    const accounts = {
        admin: { email: 'admin@rental.test', password: 'password' },
        pegawai: { email: 'pegawai@rental.test', password: 'password' },
        pelanggan: { email: 'wahyu@rental.test', password: 'password' },
    };
    form.email = accounts[role].email;
    form.password = accounts[role].password;
}

function submit(): void {
    form.post('/login', {
        onFinish: () => form.reset('password'),
    });
}
</script>

<template>
    <Head title="Masuk" />

    <div v-if="status" class="mb-4 rounded-[6px] border border-emerald-500/30 bg-emerald-50 dark:bg-emerald-950/40 p-3 text-sm font-medium text-emerald-800 dark:text-emerald-300">
        {{ status }}
    </div>

    <form @submit.prevent="submit" class="flex flex-col gap-5">
        <div class="grid gap-2">
            <Label for="email">Alamat Email</Label>
            <Input id="email" v-model="form.email" type="email" name="email" required v-focus tabindex="1" autocomplete="email" placeholder="nama@email.com" class="h-10" />
            <InputError :message="form.errors.email" />
        </div>

        <div class="grid gap-2">
            <div class="flex items-center justify-between">
                <Label for="password">Kata Sandi</Label>
                <Link v-if="canResetPassword" :href="request()" class="text-xs font-medium text-primary hover:underline" tabindex="5">Lupa kata sandi?</Link>
            </div>
            <PasswordInput id="password" v-model="form.password" name="password" required tabindex="2" autocomplete="current-password" placeholder="Masukkan kata sandi" />
            <InputError :message="form.errors.password" />
        </div>

        <Label for="remember" class="flex items-center gap-2.5 cursor-pointer">
            <Checkbox id="remember" name="remember" :checked="form.remember" @update:checked="form.remember = $event" tabindex="3" />
            <span class="text-sm text-foreground">Ingat saya</span>
        </Label>

        <Button type="submit" class="w-full h-11 text-sm font-medium" :disabled="form.processing" data-test="login-button">
            <Loader2 v-if="form.processing" class="mr-2 h-4 w-4 animate-spin" />
            Masuk
        </Button>

        <div class="text-center text-sm text-muted-foreground">
            Belum memiliki akun?
            <Link :href="register()" class="font-medium text-primary underline underline-offset-4 hover:text-primary/90 ml-1" tabindex="6">Daftar sekarang</Link>
        </div>
    </form>
</template>
