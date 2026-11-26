<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { login } from '@/routes';

defineProps<{
    passwordRules?: string;
}>();

defineOptions({
    layout: {
        title: 'Buat Akun Baru',
        description: 'Lengkapi data berikut untuk mendaftar sebagai pelanggan',
    },
});

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
});

function submit(): void {
    form.post('/register', {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
}
</script>

<template>
    <Head title="Daftar Akun" />

    <form @submit.prevent="submit" class="flex flex-col gap-4">
        <div class="grid gap-2">
            <Label for="name">Nama Lengkap</Label>
            <Input
                id="name"
                v-model="form.name"
                type="text"
                required
                v-focus
                :tabindex="1"
                autocomplete="name"
                name="name"
                placeholder="Masukkan nama lengkap"
                class="h-10 min-h-[40px]"
            />
            <InputError :message="form.errors.name" />
        </div>

        <div class="grid gap-2">
            <Label for="email">Alamat Email</Label>
            <Input
                id="email"
                v-model="form.email"
                type="email"
                required
                :tabindex="2"
                autocomplete="email"
                name="email"
                placeholder="nama@email.com"
                class="h-10 min-h-[40px]"
            />
            <InputError :message="form.errors.email" />
        </div>

        <div class="grid gap-2">
            <Label for="password">Kata Sandi</Label>
            <PasswordInput
                id="password"
                v-model="form.password"
                required
                :tabindex="3"
                autocomplete="new-password"
                name="password"
                placeholder="Minimal 8 karakter"
            />
            <InputError :message="form.errors.password" />
        </div>

        <div class="grid gap-2">
            <Label for="password_confirmation">Konfirmasi Kata Sandi</Label>
            <PasswordInput
                id="password_confirmation"
                v-model="form.password_confirmation"
                required
                :tabindex="4"
                autocomplete="new-password"
                name="password_confirmation"
                placeholder="Ulangi kata sandi"
            />
            <InputError :message="form.errors.password_confirmation" />
        </div>

        <Button
            type="submit"
            class="mt-2 w-full h-10 min-h-[40px] text-sm font-medium"
            :tabindex="5"
            :disabled="form.processing"
            data-test="register-user-button"
        >
            <Spinner v-if="form.processing" class="mr-2" />
            Daftar
        </Button>

        <div class="text-center text-sm text-muted-foreground pt-2">
            Sudah memiliki akun?
            <Link
                :href="login()"
                class="font-medium text-primary underline underline-offset-4 hover:text-primary/90 ml-1"
                :tabindex="6"
            >
                Masuk
            </Link>
        </div>
    </form>
</template>
