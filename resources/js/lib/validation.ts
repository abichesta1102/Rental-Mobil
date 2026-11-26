const dictionary: Record<string, string> = {
    'The email field is required.': 'Email wajib diisi.',
    'The password field is required.': 'Kata sandi wajib diisi.',
    'The name field is required.': 'Nama lengkap wajib diisi.',
    'These credentials do not match our records.': 'Email atau kata sandi tidak cocok.',
    'The provided credentials do not match our records.': 'Email atau kata sandi tidak cocok.',
    'The password field confirmation does not match.': 'Konfirmasi kata sandi tidak cocok.',
    'The password confirmation does not match.': 'Konfirmasi kata sandi tidak cocok.',
    'The password field must be at least 8 characters.': 'Kata sandi minimal 8 karakter.',
    'The phone field is required.': 'Nomor telepon wajib diisi.',
    'The address field is required.': 'Alamat wajib diisi.',
    'The vehicle id field is required.': 'Kendaraan harus dipilih.',
    'The pickup at field is required.': 'Waktu pengambilan wajib diisi.',
    'The return at field is required.': 'Waktu pengembalian wajib diisi.',
    'The destination field is required.': 'Tujuan perjalanan wajib diisi.',
    'The delivery address field is required.': 'Alamat pengantaran wajib diisi.',
    'The amount field is required.': 'Jumlah pembayaran wajib diisi.',
    'The proof field is required.': 'Bukti transfer/pembayaran wajib diunggah.',
    'The photo field is required.': 'Foto wajib diunggah.',
    'The plate field is required.': 'Plat nomor kendaraan wajib diisi.',
    'The brand field is required.': 'Merek kendaraan wajib diisi.',
    'The model field is required.': 'Model kendaraan wajib diisi.',
    'The year field is required.': 'Tahun kendaraan wajib diisi.',
    'The color field is required.': 'Warna kendaraan wajib diisi.',
    'The price per day field is required.': 'Harga sewa per hari wajib diisi.',
    'The late fee per hour field is required.': 'Denda per jam wajib diisi.',
    'The email has already been taken.': 'Email sudah terdaftar.',
    'The plate has already been taken.': 'Plat nomor sudah terdaftar.',
};

export function translateError(message?: string): string | undefined {
    if (!message) return undefined;
    if (dictionary[message]) return dictionary[message];

    // Generic pattern: The <field> field is required.
    if (message.endsWith(' field is required.')) {
        const fieldName = message
            .replace(/^The\s+/, '')
            .replace(/\s+field is required\.$/, '')
            .replace(/_/g, ' ');
        return `Kolom ${fieldName} wajib diisi.`;
    }

    return message;
}
