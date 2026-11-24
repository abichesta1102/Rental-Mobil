# Rental Mobil (Tugas Besar)

> File ini adalah **sumber konteks utama proyek**. Dibaca oleh pemilik proyek saat lupa, dan oleh asisten AI (Claude Code, Antigravity, dll.) supaya paham proyek ini sebelum mengubah apa pun.
> Terakhir diperbarui: 16 November 2026. Perbarui bagian "Status Pengerjaan" setiap selesai satu tahap.

## 0. Instruksi untuk Asisten AI (baca dulu)

- Pemilik proyek adalah **mahasiswa D4 Teknik Informatika** yang masih belajar Laravel. Jelaskan dengan **bahasa Indonesia yang sederhana**, sebutkan alasan di balik tiap langkah, dan jelaskan arti istilah baru.
- Database adalah **PostgreSQL 15**, **bukan MySQL**. Jangan menulis sintaks MySQL (UNSIGNED, AUTO_INCREMENT, ENUM native, KEY di dalam CREATE TABLE).
- Sistem operasi **Windows**, lingkungan **Laragon**. Perintah dijalankan di CMD atau PowerShell, bukan bash.
- Proyek memakai **Laravel 13** dengan starter kit **Vue + Inertia**. Gaya kode starter kit ini berbeda dari tutorial lama: model memakai atribut `#[Fillable([...])]` dan `#[Hidden([...])]` (bukan properti `$fillable`), route memakai `Route::inertia(...)`. **Ikuti gaya yang sudah ada di file proyek**, jangan campur dengan gaya tutorial lama.
- Perubahan struktur database dilakukan **lewat migration**, bukan SQL manual. Selama pengembangan, `php artisan migrate:fresh` boleh dipakai (menghapus semua data). Jangan menjalankannya tanpa memberi tahu pemilik proyek kalau sudah ada data yang berarti.
- **Keamanan:** kolom `role` boleh diisi massal di model User. Jangan pernah membuat atau mengubah user dari data request mentah (`$request->all()`). Selalu pakai data hasil validasi.
- Sebelum membuat file baru, cek pola file yang sudah ada (lihat bagian 9) dan ikuti penamaan serta strukturnya.
- Jangan mengarang nama komponen Vue atau lokasi file frontend. Struktur `resources/js` **belum diverifikasi** (lihat bagian 11). Periksa dulu isi foldernya.
- Perubahan status booking dan delivery harus lewat satu tempat (service atau action) di dalam satu transaksi database, agar kedua tabel tidak tidak sinkron.

## 1. Ringkasan Proyek

Website rental mobil dengan tiga sisi: **pelanggan**, **admin**, dan **pegawai**. Pelanggan memesan kendaraan untuk rentang tanggal dan jam tertentu, membayar DP, kendaraan dikirim pegawai ke alamat pelanggan, lokasi pelanggan dipantau selama sewa, lalu pelunasan dan denda (keterlambatan atau kerusakan) ditagihkan setelah kendaraan kembali.

**Syarat tugas (Tugas Besar, Opsi 2: aplikasi baru, +5 poin):**

- Minimal 5 tabel (proyek ini punya 9 tabel sendiri).
- Harus ada transaksi, dan transaksi tidak boleh hanya master yang berelasi dengan tabel dependen.
- Backend wajib Laravel, boleh dikombinasikan dengan React atau Vue (dipilih: **Vue**).
- Demo dimulai minggu ke-12 sampai ke-15.

## 2. Teknologi dan Lingkungan

| Komponen | Detail |
|---|---|
| Backend | Laravel 13 (PHP 8.3 ke atas) |
| Frontend | Vue 3 via Inertia.js, dibundel Vite, starter kit resmi Laravel (Vue) |
| Autentikasi | Laravel built-in (bukan WorkOS), hanya fitur registration. Tanpa team support, tanpa email verification, 2FA, atau passkeys |
| Database | PostgreSQL 15, database bernama `Rental`, dikelola lewat pgAdmin 4 |
| Lingkungan lokal | Laragon di Windows, folder `C:/laragon/www/rental-mobil` |
| Peta lokasi (rencana) | Leaflet + OpenStreetMap |
| Penjadwalan (rencana) | Laravel Scheduler untuk denda otomatis |
| Notifikasi (rencana) | Notifikasi database bawaan Laravel |

Ekstensi PHP `pdo_pgsql` dan `pgsql` harus aktif.

## 3. Menjalankan Proyek

```
cd C:\laragon\www\rental-mobil
composer install
npm install
copy .env.example .env        (lewati jika .env sudah ada)
php artisan key:generate
php artisan storage:link
php artisan migrate:fresh --seed
composer run dev
```

- Buka aplikasi di **http://127.0.0.1:8000** (bukan port 5173, itu hanya server Vite).
- `composer run dev` menjalankan server Laravel (8000), Vite (5173), dan queue worker sekaligus. Biarkan terminalnya terbuka.
- Setelah mengubah `.env`, jalankan `php artisan config:clear` lalu restart `composer run dev`.

### Pengaturan .env penting

```
APP_NAME="Rental Mobil"
APP_URL=http://localhost:8000
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=Rental
DB_USERNAME=postgres
DB_PASSWORD=(password PostgreSQL lokal)
```

Zona waktu di `config/app.php` harus `Asia/Jakarta` (jam ambil, jam kembali, dan denda dihitung sampai menit). **Status: belum dikonfirmasi sudah diubah. Periksa.**

### Akun contoh (hasil seeder, hanya untuk pengembangan)

| Peran | Email | Password |
|---|---|---|
| admin | admin@rental.test | password |
| pegawai | pegawai@rental.test | password |
| pelanggan | wahyu@rental.test | password |

Ganti password sebelum demo.

## 4. Peran dan Fitur

### Pelanggan (user site)
- Melihat kendaraan yang ready pada tanggal tertentu.
- Melihat tanggal-tanggal ready dari kendaraan yang dipilih.
- Booking kendaraan: input durasi sewa (tanggal, jam pengambilan, jam pengembalian), tujuan, alamat pengiriman.
- Bayar DP sebagai bukti booking (tanggal kendaraan terkunci setelah DP terverifikasi).
- Berbagi lokasi real time **setiap 15 detik** selama sewa berjalan (status `on_rent`).
- Menerima notifikasi bahwa kendaraan akan dikirim.
- Menerima tagihan pelunasan, dan tagihan kekurangan bila ada denda kerusakan setelah pengembalian.
- Melihat metode pembayaran (QRIS dan rekening) yang hanya bisa diakses pelanggan saat akan membayar DP, pelunasan, atau denda.

### Admin
- Melihat semua booking, dengan tampilan rapi per booking. Contoh: tanggal 12, Brio RS plat L 1234 WU, tujuan Pemalang, penyewa Wahyu, alamat Jl. Mungsidi No. 53 Surabaya, pembayaran QRIS Rp300.000 sebagai DP, sewa 2 hari, pengiriman pukul 00:01 12 Mei, kembali pukul 23:59 14 Mei.
- Dashboard kendaraan paling laku (traffic).
- Dashboard booking dan data pelanggan.
- Melihat kendaraan yang keluar hari ini, dan kendaraan yang ada di garasi hari ini (tidak disewa).
- CRUD kendaraan, termasuk harga sewa dan denda keterlambatan per jam.
- CRUD pegawai.
- Melihat member aktif, posisi pelanggan (peta), dan pelanggan yang di-blacklist.
- Melihat pegawai mana mengirim mobil apa.
- Memerintahkan pegawai mengirim unit kendaraan.
- Mencatat kerusakan kendaraan. Contoh: Brio disewa Wahyu, lecet bumper depan, denda cat Rp500.000, denda inap Rp300.000 per hari.
- Mengunggah metode pembayaran (QRIS dan nomor rekening).
- Memverifikasi bukti pembayaran.
- Melihat durasi kendaraan yang sedang disewa.
- Denda otomatis saat pelanggan melebihi jam sewa (jam telat x denda per jam kendaraan).

### Pegawai
- Menerima notifikasi dan melakukan approval perintah pengiriman dari admin.
- Mengirim kendaraan, dan mengunggah foto kendaraan setiap kali mengirim ke pelanggan.
- Mengunggah foto kendaraan setiap kali mengambil kendaraan yang baru kembali dari pelanggan.
- Mengunggah foto lecet atau anomali (minor) pada kendaraan.
- Menentukan denda bila ada kerusakan.
- Approve kendaraan sudah di garasi, dan approve kendaraan sudah di penyewa.

## 5. Skema Database (PostgreSQL)

Sembilan tabel buatan sendiri, ditambah tabel bawaan Laravel (`migrations`, `sessions`, `password_reset_tokens`, `cache`, `jobs`).

### users
`id`, `name`, `email` (unik), `email_verified_at`, `password`, **`role`** (`admin` / `pegawai` / `pelanggan`, default `pelanggan`, indexed), `phone`, `address`, **`is_blacklisted`** (bool), `blacklist_reason`, `member_since`, `remember_token`, timestamps.

### vehicles
`id`, `plate` (unik), `brand`, `model`, `year`, `color`, `transmission` (`manual`/`matic`), `seats`, **`price_per_day`**, **`late_fee_per_hour`**, `photo`, **`status`** (`available` / `maintenance` / `inactive`), timestamps, `deleted_at` (soft delete).

### payment_methods
`id`, `type` (`qris`/`bank_transfer`), `label`, `account_number`, `account_name`, `qris_image`, `is_active`, timestamps.

### bookings (pusat transaksi)
`id`, `user_id` -> users, `vehicle_id` -> vehicles, **`booking_code`** (unik, misalnya RNT-20261112-001), `destination`, `delivery_address`, **`pickup_at`**, **`return_at`**, `actual_returned_at`, `duration_days`, `price_per_day` (salinan harga saat booking), `total_price`, `dp_amount`, `late_fee_total`, **`status`**, timestamps.
Index: `(vehicle_id, pickup_at, return_at)` dan `status`. Constraint CHECK: `return_at > pickup_at`.

### payments
`id`, `booking_id` -> bookings (cascade), `payment_method_id` -> payment_methods, **`type`** (`dp` / `pelunasan` / `denda`), `amount`, `proof` (path bukti bayar), **`status`** (`pending` / `verified` / `rejected`), `paid_at`, `verified_by` -> users (nullable), `verified_at`, timestamps.
Index: `(booking_id, type)` dan `status`.

### deliveries
`id`, `booking_id` -> bookings (**unik**, satu booking satu delivery), `employee_id` -> users (pegawai), `assigned_by` -> users (admin), **`status`** (`assigned` / `approved` / `delivering` / `delivered` / `picked_up` / `in_garage`), `assigned_at`, `delivered_at`, `returned_at`, `notes`, timestamps.

### delivery_photos
`id`, `delivery_id` -> deliveries (cascade), `stage` (`kirim` / `ambil` / `anomali`), `path`, `note`, timestamps.

### damages
`id`, `booking_id` -> bookings (cascade), `assessed_by` -> users (pegawai), `description`, `fine_amount`, timestamps.

### location_logs
`id`, `booking_id` -> bookings (cascade), `lat`, `lng` (decimal 10,7), `recorded_at`. Index `(booking_id, recorded_at)`.

### Relasi ringkas
- users (pelanggan) 1 -> N bookings; vehicles 1 -> N bookings.
- bookings 1 -> N payments; bookings 1 -> 1 deliveries; deliveries 1 -> N delivery_photos.
- bookings 1 -> N damages; bookings 1 -> N location_logs.
- `deliveries.employee_id`, `deliveries.assigned_by`, `damages.assessed_by`, `payments.verified_by` semuanya menunjuk ke `users`.

## 6. Aturan Bisnis Penting

**Status booking (urutan normal):**
`pending_dp` -> `booked` (DP terverifikasi, tanggal terkunci) -> `assigned` (admin menugaskan pegawai) -> `delivering` -> `on_rent` (diterima pelanggan) -> `returned` (kendaraan kembali) -> `completed` (pelunasan dan denda lunas). Cabang: `cancelled`.

**Ketersediaan kendaraan tidak disimpan sebagai status.** Dihitung dari tabel `bookings`. Kendaraan bentrok bila ada booking aktif (selain `cancelled`) dengan rentang waktu yang tumpang tindih:
`pickup_at < jam_kembali_baru AND return_at > jam_ambil_baru`. Perhitungan memakai tanggal **dan jam**. Kendaraan dengan `vehicles.status` bukan `available` tidak boleh dipesan.

**Dashboard:** "keluar hari ini" dan "di garasi hari ini" diturunkan dari `bookings` (status dan rentang waktu), bukan dari kolom di `vehicles`.

**Perhitungan:**
- `duration_days` dihitung dari `pickup_at` ke `return_at`. `total_price = duration_days x price_per_day` (harga disalin ke booking agar perubahan harga kendaraan tidak memengaruhi booking lama).
- Denda keterlambatan: `jam telat x late_fee_per_hour`, dihitung otomatis lewat Laravel Scheduler bila `now > return_at` dan kendaraan belum kembali. Hasilnya masuk `late_fee_total` dan ditagihkan lewat `payments` bertipe `denda`.
- Denda kerusakan: dicatat pegawai di `damages`, ditagihkan sebagai `payments` bertipe `denda`.

**Pembayaran:** pelanggan mengunggah bukti bayar (`status = pending`), admin memverifikasi (`verified` atau `rejected`, mengisi `verified_by` dan `verified_at`). DP terverifikasi mengubah booking menjadi `booked`.

**Pelacakan lokasi:** hanya aktif saat booking `on_rent`, dengan persetujuan pelanggan (izin Geolocation browser). Butuh HTTPS atau localhost. Satu titik per 15 detik (sekitar 5.760 baris per booking per hari). Log lama dapat diarsipkan setelah booking `returned`. Admin cukup melihat titik terakhir.

**Konsistensi status:** `bookings.status` dan `deliveries.status` berbagi beberapa makna. Ubah keduanya hanya lewat satu service atau action dalam satu transaksi.

**Blacklist:** pengguna dengan `is_blacklisted = true` ditolak oleh middleware `role` di seluruh area terproteksi.

## 7. Autentikasi dan Otorisasi

- Registrasi publik membuat akun dengan role `pelanggan` (default kolom).
- Middleware **`EnsureRole`** (`app/Http/Middleware/EnsureRole.php`, alias **`role`** di `bootstrap/app.php`) mengembalikan **403** bila pengguna belum login, di-blacklist, atau perannya tidak ada dalam daftar. Contoh pemakaian: `->middleware(['auth', 'role:admin'])`. Bisa lebih dari satu peran: `role:admin,pegawai`.

## 8. Rute yang Sudah Ada

`routes/web.php`:

- `/` (Welcome), `dashboard` (auth + verified), rute pengaturan dari `routes/settings.php` (bawaan starter kit).
- Grup admin: `Route::middleware(['auth', 'role:admin'])->prefix('admin')->name('admin.')`, berisi `Route::resource('vehicles', VehicleController::class)->except('show')`.
- Hasilnya 6 rute `admin.vehicles.index/create/store/edit/update/destroy` (cek dengan `php artisan route:list --path=admin`).

## 9. Struktur dan Pola Kode yang Sudah Dibuat

```
app/Models/User.php                                   (#[Fillable] + #[Hidden], casts: is_blacklisted bool, member_since datetime)
app/Models/Vehicle.php                                (#[Fillable], SoftDeletes, accessor photo_url)
app/Http/Middleware/EnsureRole.php
app/Http/Controllers/Admin/VehicleController.php      (index, create, store, edit, update, destroy, validated)
bootstrap/app.php                                     (alias middleware 'role')
database/migrations/0001_01_01_000000_create_users_table.php        (sudah diubah: kolom role, phone, dll.)
database/migrations/2026_11_12_101517_create_rental_tables.php      (8 tabel: vehicles sampai location_logs)
database/seeders/DatabaseSeeder.php                   (3 akun contoh)
routes/web.php
```

**Pola yang diikuti:**
- Controller admin berada di namespace `App/Http/Controllers/Admin`, mengembalikan `Inertia::render('admin/<modul>/<Halaman>', [...])`.
- Validasi lewat private method `validated()` di controller, dengan `Rule::unique(...)->ignore(...)` untuk update.
- Upload file memakai disk `public` (`->store('vehicles', 'public')`), diakses lewat `/storage/...` (butuh `php artisan storage:link`). Saat mengganti file, file lama dihapus.
- Penghapusan kendaraan memakai **soft delete** agar riwayat booking aman.
- Urutan migration penting karena foreign key (vehicles dan payment_methods dulu, lalu bookings, dst.). Nama file migration rental berawalan 2026, jadi berjalan setelah migration users.

Dokumen pendukung (di luar repo, opsional dilampirkan): ERD (Mermaid), `rental_mobil_postgres.sql` (rujukan SQL PostgreSQL, bukan sumber utama), dan dokumentasi pengerjaan tahap awal.

## 10. Status Pengerjaan

**Selesai**
- [x] Proyek Laravel 13 + starter kit Vue dibuat, dan berjalan di 127.0.0.1:8000
- [x] Koneksi ke PostgreSQL (database `Rental`)
- [x] ERD dan migration seluruh tabel (9 tabel + bawaan)
- [x] Model User disesuaikan, seeder 3 akun, login admin berhasil
- [x] Middleware `role` + alias + custom page error 403 informatif
- [x] Backend & Frontend CRUD kendaraan (model, controller, route resource, validasi, upload foto, soft delete)
- [x] Halaman Vue: `admin/vehicles/Index` dan `admin/vehicles/Form`
- [x] Menu sidebar terintegrasi sesuai peran (Admin, Pegawai, Pelanggan)
- [x] Pengarahan & Dashboard dinamis sesuai peran (Admin, Pegawai, Pelanggan)
- [x] Konfirmasi zona waktu `Asia/Jakarta`
- [x] Manajemen Pegawai (CRUD akun) & Blacklist Pelanggan (`admin/users`)
- [x] Manajemen Metode Pembayaran QRIS & Transfer Bank (`admin/payment-methods`)
- [x] Sisi Pelanggan: Katalog, Cek ketersediaan, Booking kendaraan, Detail booking & upload bukti bayar DP / Pelunasan
- [x] Verifikasi bukti bayar oleh Admin (DP, Pelunasan, Denda) & otomatisasi kode booking (format RNT-tanggal-urutan)
- [x] Alur pengiriman pegawai: Penugasan pegawai oleh admin, approval pegawai, status jalan/tiba di pelanggan, upload foto serah terima, foto ambil kembali, catat anomali/kerusakan
- [x] Perhitungan denda keterlambatan otomatis & denda kerusakan
- [x] Pelacakan lokasi GPS pelanggan berkala (setiap 15 detik) saat status `on_rent` dan peta link lokasi untuk admin
- [x] Dashboard Admin dengan statistik (unit keluar, unit garasi, booking aktif, verifikasi bayar, blacklist)

## 11. Hal yang Belum Diketahui atau Perlu Diverifikasi

- **Struktur `resources/js`** (nama folder `pages`, `layouts`, `components/ui`, komponen sidebar) belum diperiksa. Lihat dulu isi foldernya dan contoh `pages/Dashboard.vue` sebelum membuat halaman Vue baru, agar memakai layout dan komponen bawaan starter kit.
- Mekanisme penambahan menu sidebar di starter kit ini belum diperiksa.
- Zona waktu aplikasi (lihat bagian 3).

## 12. Kendala yang Pernah Terjadi (hindari terulang)

| Masalah | Penyebab | Solusi |
|---|---|---|
| Error `UNSIGNED` di pgAdmin | SQL dialek MySQL dijalankan di PostgreSQL | Pakai sintaks PostgreSQL |
| Folder proyek bersarang | `laravel new` dijalankan di dalam folder bernama sama | Jalankan dari `C:/laragon/www` |
| `password authentication failed for user "Rental"` | `DB_DATABASE` dan `DB_USERNAME` tertukar di `.env` | Database = `Rental`, user = `postgres` |
| Tabel rental tidak terbentuk saat `migrate:fresh` | File migration belum ada di `database/migrations` | Buat dengan `php artisan make:migration`, isi kodenya |
| `Class ...VehicleController does not exist` | File berada di `Controllers/`, bukan `Controllers/Admin/` | Pindahkan file, lalu `composer dump-autoload` |
| Buka `localhost:5173` hanya menampilkan halaman Vite | Itu server pendukung, bukan aplikasi | Buka `127.0.0.1:8000` |
| Nama kolom salah ketik (`verived_by`) | Salah ketik di migration | Konsisten pakai `verified_by` |

## 13. Perintah yang Sering Dipakai

| Perintah | Fungsi |
|---|---|
| `composer run dev` | Menjalankan server Laravel + Vite |
| `php artisan migrate` | Menjalankan migration yang tertunda |
| `php artisan migrate:fresh --seed` | Hapus semua tabel, buat ulang, isi data contoh |
| `php artisan db:seed` | Mengisi data contoh saja |
| `php artisan route:list --path=admin` | Melihat daftar rute admin |
| `php artisan route:clear` / `config:clear` | Membuang cache rute atau pengaturan |
| `composer dump-autoload` | Memperbarui daftar class PHP |
| `php artisan make:model/controller/migration/middleware` | Membuat kerangka file |

## 14. Referensi Resmi

- Laravel: https://laravel.com/docs (Starter Kits, Migrations, Eloquent, Middleware, Routing, Controllers, Validation, Filesystem, Seeding, Task Scheduling, Notifications)
- Inertia.js: https://inertiajs.com
- Vue.js: https://vuejs.org/guide/introduction.html
- PostgreSQL 15: https://www.postgresql.org/docs/15/
- Leaflet: https://leafletjs.com