<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { login } from '@/routes';
import { email } from '@/routes/password';

defineOptions({
    layout: {
        title: 'Lupa Kata Sandi',
        description: 'Masukkan email Anda untuk menerima tautan pemulihan kata sandi',
    },
});

defineProps<{
    status?: string;
}>();
</script>

<template>
    <Head title="Lupa Kata Sandi" />

    <div
        v-if="status"
        class="rounded-[6px] border border-emerald-500/30 bg-emerald-50 dark:bg-emerald-950/40 p-3 text-sm font-medium text-emerald-800 dark:text-emerald-300"
    >
        {{ status }}
    </div>

    <div class="space-y-6">
        <Form v-bind="email.form()" v-slot="{ errors, processing }">
            <div class="grid gap-2">
                <Label for="email">Alamat Email</Label>
                <Input
                    id="email"
                    type="email"
                    name="email"
                    autocomplete="off"
                    v-focus
                    placeholder="nama@email.com"
                    class="h-10 min-h-[40px]"
                />
                <InputError :message="errors.email" />
            </div>

            <div class="my-6 flex items-center justify-start">
                <Button
                    class="w-full h-10 min-h-[40px]"
                    :disabled="processing"
                    data-test="email-password-reset-link-button"
                >
                    <Spinner v-if="processing" class="mr-2" />
                    Kirim Tautan Pemulihan
                </Button>
            </div>
        </Form>

        <div class="text-center text-sm text-muted-foreground">
            Kembali ke
            <Link :href="login()" class="font-medium text-primary hover:underline ml-1">
                halaman masuk
            </Link>
        </div>
    </div>
</template>
