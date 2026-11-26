<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Car, ArrowRight } from '@lucide/vue';
import { dashboard, login, register } from '@/routes';
</script>

<template>
    <Head title="RentalinAja" />

    <div
        class="min-h-screen flex flex-col justify-between bg-cover bg-center bg-no-repeat"
        style="background-image: url('/storage/images/landing-bg.jpg');"
    >
        <!-- Top bar -->
        <header class="border-b border-white/10 bg-[#181513]/90 backdrop-blur-md px-6 py-3.5 z-10">
            <div class="mx-auto flex max-w-7xl items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="flex size-9 items-center justify-center rounded-[6px] bg-[#c2410c] text-white shadow-sm">
                        <Car class="size-5 text-white" />
                    </div>
                    <span class="text-base font-semibold tracking-tight text-white">
                        RentalinAja
                    </span>
                </div>

                <nav class="flex items-center gap-3">
                    <template v-if="$page.props.auth?.user">
                        <Link
                            :href="dashboard()"
                            class="inline-flex h-9 items-center justify-center rounded-[6px] bg-[#c2410c] px-4 text-sm font-medium text-white transition-colors hover:bg-[#ea580c]"
                        >
                            Buka Dashboard
                            <ArrowRight class="ml-1.5 size-4" />
                        </Link>
                    </template>
                    <template v-else>
                        <Link
                            :href="login()"
                            class="inline-flex h-9 items-center justify-center rounded-[6px] px-4 text-sm font-medium text-white transition-colors hover:text-white/80"
                        >
                            Masuk
                        </Link>
                        <Link
                            :href="register()"
                            class="inline-flex h-9 items-center justify-center rounded-[6px] bg-[#c2410c] px-4 text-sm font-medium text-white transition-colors hover:bg-[#ea580c]"
                        >
                            Daftar
                        </Link>
                    </template>
                </nav>
            </div>
        </header>

        <!-- Main center section displaying background showroom -->
        <main class="flex-1"></main>

        <!-- Footer -->
        <footer class="border-t border-white/10 bg-[#181513]/90 backdrop-blur-md py-4 px-6 text-xs text-stone-400 z-10">
            <div class="mx-auto max-w-7xl flex flex-col sm:flex-row items-center justify-between gap-2">
                <span>RentalinAja &copy; 2026. Hak Cipta Dilindungi.</span>
                <span>Aplikasi Operasional Rental</span>
            </div>
        </footer>
    </div>
</template>
