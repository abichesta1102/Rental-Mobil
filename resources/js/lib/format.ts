export function rupiah(value: string | number | null | undefined): string {
    if (value === null || value === undefined || value === '') {
        return 'Rp0';
    }
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        maximumFractionDigits: 0,
    }).format(Number(value));
}

export function formatDateTime(value: string | null | undefined): string {
    if (!value) return '-';
    try {
        return new Intl.DateTimeFormat('id-ID', {
            dateStyle: 'medium',
            timeStyle: 'short',
        }).format(new Date(value));
    } catch {
        return String(value);
    }
}

export function formatDate(value: string | null | undefined): string {
    if (!value) return '-';
    try {
        return new Intl.DateTimeFormat('id-ID', {
            dateStyle: 'medium',
        }).format(new Date(value));
    } catch {
        return String(value);
    }
}

export function formatDayLabel(isoDate: string): string {
    if (!isoDate) return '-';
    try {
        return new Date(`${isoDate}T00:00:00`).toLocaleDateString('id-ID', {
            weekday: 'short',
            day: 'numeric',
            month: 'short',
        });
    } catch {
        return isoDate;
    }
}
