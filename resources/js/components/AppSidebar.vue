<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    Car,
    ClipboardList,
    CreditCard,
    DollarSign,
    LayoutGrid,
    Truck,
    Users,
} from '@lucide/vue';
import { computed } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import NavFooter from '@/components/NavFooter.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import type { NavItem } from '@/types';

const page = usePage();

const role = computed(() => {
    const auth = page.props.auth as unknown as { user?: { role?: string } | null };
    return auth?.user?.role;
});

const mainNavItems = computed<NavItem[]>(() => {
    const items: NavItem[] = [
        {
            title: 'Dashboard',
            href: dashboard(),
            icon: LayoutGrid,
        },
    ];

    if (role.value === 'admin') {
        items.push(
            {
                title: 'Data Kendaraan',
                href: '/admin/vehicles',
                icon: Car,
            },
            {
                title: 'Data Booking',
                href: '/admin/bookings',
                icon: ClipboardList,
            },
            {
                title: 'Metode Pembayaran',
                href: '/admin/payment-methods',
                icon: CreditCard,
            },
            {
                title: 'Pegawai & Pelanggan',
                href: '/admin/users',
                icon: Users,
            },
            {
                title: 'Keuangan',
                href: '/admin/finance',
                icon: DollarSign,
            },
            {
                title: 'Katalog Mobil',
                href: '/vehicles',
                icon: Car,
            },
        );
    }

    if (role.value === 'pegawai') {
        items.push(
            {
                title: 'Tugas Pengiriman',
                href: '/pegawai/deliveries',
                icon: Truck,
            },
        );
    }

    if (role.value === 'pelanggan') {
        items.push(
            {
                title: 'Katalog Kendaraan',
                href: '/vehicles',
                icon: Car,
            },
            {
                title: 'Booking Saya',
                href: '/bookings',
                icon: ClipboardList,
            },
        );
    }

    return items;
});

const footerNavItems: NavItem[] = [];
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="dashboard()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <NavFooter :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
