<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { useTemplateRef } from 'vue';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';

const passwordInput = useTemplateRef('passwordInput');
</script>

<template>
    <div class="space-y-6">
        <Heading
            variant="small"
            title="Hapus Akun"
            description="Hapus permanen akun Anda dan seluruh data terkait"
        />
        <div
            class="space-y-4 rounded-[6px] border border-red-200 bg-red-50 p-4 dark:border-red-950 dark:bg-red-950/20"
        >
            <div class="relative space-y-0.5 text-red-600 dark:text-red-400">
                <p class="font-medium">Peringatan</p>
                <p class="text-sm">
                    Harap berhati-hati, tindakan ini bersifat permanen dan tidak dapat dibatalkan.
                </p>
            </div>
            <Dialog>
                <DialogTrigger as-child>
                    <Button variant="destructive" data-test="delete-user-button" class="rounded-[6px] min-h-[40px]"
                        >Hapus Akun</Button
                    >
                </DialogTrigger>
                <DialogContent class="rounded-[6px]">
                    <Form
                        v-bind="ProfileController.destroy.form()"
                        reset-on-success
                        @error="() => passwordInput?.focus()"
                        :options="{
                            preserveScroll: true,
                        }"
                        class="space-y-6"
                        v-slot="{ errors, processing, reset, clearErrors }"
                    >
                        <DialogHeader class="space-y-3">
                            <DialogTitle
                                >Apakah Anda yakin ingin menghapus akun?</DialogTitle
                            >
                            <DialogDescription>
                                Setelah akun dihapus, seluruh data dan riwayat akan dihapus secara permanen. Masukkan kata sandi Anda untuk mengonfirmasi penghapusan akun.
                            </DialogDescription>
                        </DialogHeader>

                        <div class="grid gap-2">
                            <Label for="password" class="sr-only"
                                >Kata Sandi</Label
                            >
                            <PasswordInput
                                id="password"
                                name="password"
                                ref="passwordInput"
                                placeholder="Kata sandi akun Anda"
                                class="rounded-[6px]"
                            />
                            <InputError :message="errors.password" />
                        </div>

                        <DialogFooter class="gap-2">
                            <DialogClose as-child>
                                <Button
                                    variant="secondary"
                                    class="rounded-[6px] min-h-[40px]"
                                    @click="
                                        () => {
                                            clearErrors();
                                            reset();
                                        }
                                    "
                                >
                                    Batal
                                </Button>
                            </DialogClose>

                            <Button
                                type="submit"
                                variant="destructive"
                                :disabled="processing"
                                data-test="confirm-delete-user-button"
                                class="rounded-[6px] min-h-[40px]"
                            >
                                Ya, Hapus Akun
                            </Button>
                        </DialogFooter>
                    </Form>
                </DialogContent>
            </Dialog>
        </div>
    </div>
</template>
