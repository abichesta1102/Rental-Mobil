<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Car, CreditCard, MapPin, Navigation } from '@lucide/vue';
import { home } from '@/routes';

defineProps<{
    title?: string;
    description?: string;
}>();
</script>

<template>
    <div class="min-h-screen grid lg:grid-cols-2 bg-background">
        <!-- Kolom Kiri: Panel Gambar Latar (disembunyikan di layar kecil) -->
        <div
            class="hidden lg:flex flex-col justify-between p-12 border-r border-stone-800 select-none relative bg-cover bg-center text-stone-100"
            style="background-image: linear-gradient(rgba(24, 21, 19, 0.88), rgba(24, 21, 19, 0.88)), url('/storage/images/landing-bg.jpg');"
        >
            <!-- Brand -->
            <div>
                <Link :href="home()" class="inline-flex items-center gap-3">
                    <img src="/storage/images/logo.png" alt="Logo" class="h-12 object-contain" />
                </Link>
            </div>

            <!-- Content & Features -->
            <div class="space-y-8 my-auto max-w-md">
                <div class="space-y-3">
                    <h2 class="text-2xl font-semibold tracking-tight text-white">
                        Operasional Rental Mobil
                    </h2>
                    <p class="text-sm leading-relaxed text-stone-400">
                        Layanan rental kendaraan operasional dengan armada terawat, pengantaran tepat waktu, dan penagihan yang transparan.
                    </p>
                </div>

                <div class="space-y-4 pt-4 border-t border-stone-800">
                    <div class="flex items-start gap-3">
                        <div class="flex size-8 shrink-0 items-center justify-center rounded-[6px] bg-stone-800 text-stone-300">
                            <MapPin class="size-4" />
                        </div>
                        <div>
                            <div class="text-sm font-medium text-stone-200">Antar ke Alamat</div>
                            <div class="text-xs text-stone-400 leading-normal">Unit kendaraan diantar langsung ke lokasi pemesan sesuai jadwal.</div>
                        </div>
                    </div>

                    <div class="flex items-start gap-3">
                        <div class="flex size-8 shrink-0 items-center justify-center rounded-[6px] bg-stone-800 text-stone-300">
                            <Navigation class="size-4" />
                        </div>
                        <div>
                            <div class="text-sm font-medium text-stone-200">Pelacakan Lokasi</div>
                            <div class="text-xs text-stone-400 leading-normal">Pemantauan lokasi kendaraan secara langsung selama masa sewa aktif.</div>
                        </div>
                    </div>

                    <div class="flex items-start gap-3">
                        <div class="flex size-8 shrink-0 items-center justify-center rounded-[6px] bg-stone-800 text-stone-300">
                            <CreditCard class="size-4" />
                        </div>
                        <div>
                            <div class="text-sm font-medium text-stone-200">Bayar Lewat QRIS atau Transfer</div>
                            <div class="text-xs text-stone-400 leading-normal">Kemudahan pembayaran DP, pelunasan, dan denda dengan konfirmasi terstruktur.</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer note -->
            <div class="text-xs text-stone-500">
                Sistem Operasional Rental Kendaraan
            </div>
        </div>

        <!-- Kolom Kanan: Form Area -->
        <div class="flex flex-col justify-center items-center px-6 py-12 sm:px-12 w-full">
            <div class="w-full max-w-sm space-y-6">
                <!-- Mobile brand (hanya muncul di layar kecil) -->
                <div class="lg:hidden flex items-center gap-2.5 mb-2">
                    <div class="flex size-8 items-center justify-center rounded-[6px] bg-primary text-primary-foreground">
                        <img src="/storage/images/logo.png" alt="Logo" class="size-5 object-contain" />
                    </div>
                    <span class="text-base font-semibold tracking-tight text-foreground">Rental Mobil</span>
                </div>

                <div class="space-y-1">
                    <h1 v-if="title" class="text-xl sm:text-2xl font-semibold tracking-tight text-foreground">
                        {{ title }}
                    </h1>
                    <p v-if="description" class="text-sm text-muted-foreground leading-relaxed">
                        {{ description }}
                    </p>
                </div>

                <slot />
            </div>
        </div>
    </div>
</template>
