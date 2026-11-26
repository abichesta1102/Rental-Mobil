export type BadgeVariant = 'default' | 'secondary' | 'destructive' | 'outline';

export interface StatusMeta {
    label: string;
    variant: BadgeVariant;
    classes: string;
}

export const statusMap: Record<string, StatusMeta> = {
    // Booking statuses
    pending_dp: {
        label: 'Menunggu DP',
        variant: 'outline',
        classes: 'border-amber-600/40 text-amber-700 bg-amber-50 dark:bg-amber-950/40 dark:text-amber-400 dark:border-amber-800',
    },
    booked: {
        label: 'Dipesan',
        variant: 'secondary',
        classes: 'border-blue-600/40 text-blue-700 bg-blue-50 dark:bg-blue-950/40 dark:text-blue-400 dark:border-blue-800',
    },
    assigned: {
        label: 'Ditugaskan',
        variant: 'secondary',
        classes: 'border-stone-500/40 text-stone-700 bg-stone-100 dark:bg-stone-800 dark:text-stone-300 dark:border-stone-700',
    },
    approved: {
        label: 'Disetujui',
        variant: 'secondary',
        classes: 'border-stone-500/40 text-stone-700 bg-stone-100 dark:bg-stone-800 dark:text-stone-300 dark:border-stone-700',
    },
    delivering: {
        label: 'Sedang Dikirim',
        variant: 'default',
        classes: 'border-orange-600/40 text-orange-700 bg-orange-50 dark:bg-orange-950/40 dark:text-orange-400 dark:border-orange-800',
    },
    delivered: {
        label: 'Tiba di Pelanggan',
        variant: 'default',
        classes: 'border-emerald-600/40 text-emerald-700 bg-emerald-50 dark:bg-emerald-950/40 dark:text-emerald-400 dark:border-emerald-800',
    },
    on_rent: {
        label: 'Sedang Disewa',
        variant: 'default',
        classes: 'border-orange-700/40 text-orange-700 bg-orange-50 dark:bg-orange-950/40 dark:text-orange-400 dark:border-orange-800',
    },
    picked_up: {
        label: 'Diambil Kembali',
        variant: 'secondary',
        classes: 'border-stone-500/40 text-stone-700 bg-stone-100 dark:bg-stone-800 dark:text-stone-300 dark:border-stone-700',
    },
    in_garage: {
        label: 'Di Garasi',
        variant: 'outline',
        classes: 'border-stone-500/40 text-stone-700 bg-stone-100 dark:bg-stone-800 dark:text-stone-300 dark:border-stone-700',
    },
    returned: {
        label: 'Unit Kembali',
        variant: 'secondary',
        classes: 'border-stone-500/40 text-stone-700 bg-stone-100 dark:bg-stone-800 dark:text-stone-300 dark:border-stone-700',
    },
    completed: {
        label: 'Selesai',
        variant: 'outline',
        classes: 'border-emerald-600/40 text-emerald-700 bg-emerald-50 dark:bg-emerald-950/40 dark:text-emerald-400 dark:border-emerald-800',
    },
    cancelled: {
        label: 'Dibatalkan',
        variant: 'destructive',
        classes: 'border-red-600/40 text-red-700 bg-red-50 dark:bg-red-950/40 dark:text-red-400 dark:border-red-800',
    },

    // Payment statuses
    pending: {
        label: 'Menunggu Verifikasi',
        variant: 'outline',
        classes: 'border-amber-600/40 text-amber-700 bg-amber-50 dark:bg-amber-950/40 dark:text-amber-400 dark:border-amber-800',
    },
    verified: {
        label: 'Terverifikasi',
        variant: 'default',
        classes: 'border-emerald-600/40 text-emerald-700 bg-emerald-50 dark:bg-emerald-950/40 dark:text-emerald-400 dark:border-emerald-800',
    },
    rejected: {
        label: 'Ditolak',
        variant: 'destructive',
        classes: 'border-red-600/40 text-red-700 bg-red-50 dark:bg-red-950/40 dark:text-red-400 dark:border-red-800',
    },

    // Vehicle statuses
    available: {
        label: 'Tersedia',
        variant: 'default',
        classes: 'border-emerald-600/40 text-emerald-700 bg-emerald-50 dark:bg-emerald-950/40 dark:text-emerald-400 dark:border-emerald-800',
    },
    maintenance: {
        label: 'Perawatan',
        variant: 'secondary',
        classes: 'border-amber-600/40 text-amber-700 bg-amber-50 dark:bg-amber-950/40 dark:text-amber-400 dark:border-amber-800',
    },
    inactive: {
        label: 'Nonaktif',
        variant: 'destructive',
        classes: 'border-stone-500/40 text-stone-600 bg-stone-100 dark:bg-stone-800 dark:text-stone-400 dark:border-stone-700',
    },

    // User / Blacklist statuses
    active: {
        label: 'Aktif',
        variant: 'default',
        classes: 'border-emerald-600/40 text-emerald-700 bg-emerald-50 dark:bg-emerald-950/40 dark:text-emerald-400 dark:border-emerald-800',
    },
    blacklisted: {
        label: 'Blacklist',
        variant: 'destructive',
        classes: 'border-red-600/40 text-red-700 bg-red-50 dark:bg-red-950/40 dark:text-red-400 dark:border-red-800',
    },
};

export function getStatusLabel(status: string): string {
    return statusMap[status]?.label ?? status;
}

export function getStatusVariant(status: string): BadgeVariant {
    return statusMap[status]?.variant ?? 'outline';
}

export function getStatusClass(status: string): string {
    return statusMap[status]?.classes ?? '';
}
