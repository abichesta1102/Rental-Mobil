<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Damage;
use App\Models\Delivery;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RentalFlowTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $pegawai;
    private User $pelanggan;
    private Vehicle $vehicle;
    private PaymentMethod $paymentMethod;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->admin = User::create([
            'name' => 'Admin Rental',
            'email' => 'admin@test.test',
            'password' => 'password',
            'role' => 'admin',
        ]);

        $this->pegawai = User::create([
            'name' => 'Budi Pegawai',
            'email' => 'pegawai@test.test',
            'password' => 'password',
            'role' => 'pegawai',
            'phone' => '081234567890',
        ]);

        $this->pelanggan = User::create([
            'name' => 'Wahyu Pelanggan',
            'email' => 'wahyu@test.test',
            'password' => 'password',
            'role' => 'pelanggan',
            'phone' => '081298765432',
            'address' => 'Jl. Mungsidi No. 53 Surabaya',
        ]);

        $this->vehicle = Vehicle::create([
            'plate' => 'L 1234 TEST',
            'brand' => 'Honda',
            'model' => 'Brio RS',
            'year' => 2022,
            'color' => 'Putih',
            'transmission' => 'matic',
            'seats' => 4,
            'price_per_day' => 300000,
            'late_fee_per_hour' => 10000,
            'status' => 'available',
        ]);

        $this->paymentMethod = PaymentMethod::create([
            'type' => 'bank_transfer',
            'label' => 'BCA Transfer',
            'account_number' => '1234567890',
            'account_name' => 'PT Rental Mobil',
            'is_active' => true,
        ]);
    }

    /** 1. Katalog kendaraan dengan filter tanggal */
    public function test_katalog_dengan_filter_tanggal(): void
    {
        $response = $this->actingAs($this->pelanggan)->get('/vehicles');
        $response->assertStatus(200);

        // Filter tanggal
        $pickup = now()->addDays(2)->format('Y-m-d\TH:00');
        $return = now()->addDays(3)->format('Y-m-d\TH:00');

        $filterResponse = $this->actingAs($this->pelanggan)->get("/vehicles?pickup_at={$pickup}&return_at={$return}");
        $filterResponse->assertStatus(200);
    }

    /** 2. Booking kendaraan oleh pelanggan */
    public function test_booking_kendaraan_oleh_pelanggan(): void
    {
        $pickup = now()->addDays(2)->setHour(10)->setMinute(0);
        $return = now()->addDays(3)->setHour(10)->setMinute(0);

        $response = $this->actingAs($this->pelanggan)->post('/bookings', [
            'vehicle_id' => $this->vehicle->id,
            'destination' => 'Malang',
            'delivery_address' => 'Hotel Tugu Malang',
            'pickup_at' => $pickup->toIso8601String(),
            'return_at' => $return->toIso8601String(),
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('bookings', [
            'vehicle_id' => $this->vehicle->id,
            'user_id' => $this->pelanggan->id,
            'status' => 'pending_dp',
            'duration_days' => 1,
            'total_price' => 300000,
        ]);
    }

    /** 3. Booking ganda pada jadwal tumpang tindih ditolak */
    public function test_booking_ganda_pada_jadwal_sama_ditolak(): void
    {
        $pickup = now()->addDays(2)->setHour(10)->setMinute(0);
        $return = now()->addDays(3)->setHour(10)->setMinute(0);

        // Booking pertama (status booked menahan jadwal)
        Booking::create([
            'user_id' => $this->pelanggan->id,
            'vehicle_id' => $this->vehicle->id,
            'booking_code' => 'RNT-TEST-001',
            'destination' => 'Malang',
            'delivery_address' => 'Jl. Mawar',
            'pickup_at' => $pickup,
            'return_at' => $return,
            'duration_days' => 1,
            'price_per_day' => 300000,
            'total_price' => 300000,
            'dp_amount' => 100000,
            'status' => 'booked',
        ]);

        $otherCustomer = User::create([
            'name' => 'Doni',
            'email' => 'doni@test.test',
            'password' => 'password',
            'role' => 'pelanggan',
        ]);

        $response = $this->actingAs($otherCustomer)->post('/bookings', [
            'vehicle_id' => $this->vehicle->id,
            'destination' => 'Batu',
            'delivery_address' => 'Hotel Batu',
            'pickup_at' => $pickup->toIso8601String(),
            'return_at' => $return->toIso8601String(),
        ]);

        $response->assertSessionHasErrors('pickup_at');
    }

    /** 4. Bayar DP (type & amount ditentukan di server) */
    public function test_bayar_dp_dengan_nominal_dan_tipe_dari_server(): void
    {
        $booking = Booking::create([
            'user_id' => $this->pelanggan->id,
            'vehicle_id' => $this->vehicle->id,
            'booking_code' => 'RNT-TEST-002',
            'destination' => 'Malang',
            'delivery_address' => 'Jl. Mawar',
            'pickup_at' => now()->addDays(2),
            'return_at' => now()->addDays(3),
            'duration_days' => 1,
            'price_per_day' => 300000,
            'total_price' => 300000,
            'dp_amount' => 100000,
            'status' => 'pending_dp',
        ]);

        $proof = UploadedFile::fake()->image('bukti_dp.jpg');

        $response = $this->actingAs($this->pelanggan)->post("/bookings/{$booking->id}/pay", [
            'payment_method_id' => $this->paymentMethod->id,
            'proof' => $proof,
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('payments', [
            'booking_id' => $booking->id,
            'type' => 'dp',
            'amount' => 100000,
            'status' => 'pending',
        ]);
    }

    /** 5. Bayar DP ditolak jika metode pembayaran tidak aktif */
    public function test_bayar_dp_dengan_metode_nonaktif_ditolak(): void
    {
        $inactiveMethod = PaymentMethod::create([
            'type' => 'bank_transfer',
            'label' => 'Bank Tutup',
            'is_active' => false,
        ]);

        $booking = Booking::create([
            'user_id' => $this->pelanggan->id,
            'vehicle_id' => $this->vehicle->id,
            'booking_code' => 'RNT-TEST-003',
            'destination' => 'Malang',
            'delivery_address' => 'Jl. Mawar',
            'pickup_at' => now()->addDays(2),
            'return_at' => now()->addDays(3),
            'duration_days' => 1,
            'price_per_day' => 300000,
            'total_price' => 300000,
            'dp_amount' => 100000,
            'status' => 'pending_dp',
        ]);

        $proof = UploadedFile::fake()->image('bukti_dp.jpg');

        $response = $this->actingAs($this->pelanggan)->post("/bookings/{$booking->id}/pay", [
            'payment_method_id' => $inactiveMethod->id,
            'proof' => $proof,
        ]);

        $response->assertSessionHasErrors('payment_method_id');
    }

    /** 6. Verifikasi DP oleh Admin */
    public function test_verifikasi_dp_oleh_admin(): void
    {
        $booking = Booking::create([
            'user_id' => $this->pelanggan->id,
            'vehicle_id' => $this->vehicle->id,
            'booking_code' => 'RNT-TEST-004',
            'destination' => 'Malang',
            'delivery_address' => 'Jl. Mawar',
            'pickup_at' => now()->addDays(2),
            'return_at' => now()->addDays(3),
            'duration_days' => 1,
            'price_per_day' => 300000,
            'total_price' => 300000,
            'dp_amount' => 100000,
            'status' => 'pending_dp',
        ]);

        $payment = Payment::create([
            'booking_id' => $booking->id,
            'payment_method_id' => $this->paymentMethod->id,
            'type' => 'dp',
            'amount' => 100000,
            'proof' => 'payments/dummy.jpg',
            'status' => 'pending',
            'paid_at' => now(),
        ]);

        $response = $this->actingAs($this->admin)->post("/admin/payments/{$payment->id}/verify", [
            'action' => 'verify',
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'status' => 'verified',
            'verified_by' => $this->admin->id,
        ]);

        $this->assertEquals('booked', $booking->fresh()->status);
    }

    /** 7. Verifikasi DP ditolak jika jadwal bentrok */
    public function test_verifikasi_dp_ditolak_jika_jadwal_bentrok(): void
    {
        $pickup = now()->addDays(5);
        $return = now()->addDays(6);

        // Booking A (sudah booked)
        Booking::create([
            'user_id' => $this->pelanggan->id,
            'vehicle_id' => $this->vehicle->id,
            'booking_code' => 'RNT-TEST-005A',
            'destination' => 'Malang',
            'delivery_address' => 'Jl. Mawar',
            'pickup_at' => $pickup,
            'return_at' => $return,
            'duration_days' => 1,
            'price_per_day' => 300000,
            'total_price' => 300000,
            'dp_amount' => 100000,
            'status' => 'booked',
        ]);

        // Booking B (masih pending_dp pada rentang waktu yang sama)
        $bookingB = Booking::create([
            'user_id' => $this->pelanggan->id,
            'vehicle_id' => $this->vehicle->id,
            'booking_code' => 'RNT-TEST-005B',
            'destination' => 'Batu',
            'delivery_address' => 'Jl. Melati',
            'pickup_at' => $pickup,
            'return_at' => $return,
            'duration_days' => 1,
            'price_per_day' => 300000,
            'total_price' => 300000,
            'dp_amount' => 100000,
            'status' => 'pending_dp',
        ]);

        $paymentB = Payment::create([
            'booking_id' => $bookingB->id,
            'payment_method_id' => $this->paymentMethod->id,
            'type' => 'dp',
            'amount' => 100000,
            'proof' => 'payments/dummyB.jpg',
            'status' => 'pending',
            'paid_at' => now(),
        ]);

        // Admin verifikasi paymentB harus ditolak karena jadwal bentrok dengan Booking A
        $response = $this->actingAs($this->admin)->post("/admin/payments/{$paymentB->id}/verify", [
            'action' => 'verify',
        ]);

        $response->assertSessionHasErrors('booking');
        $this->assertEquals('pending_dp', $bookingB->fresh()->status);
    }

    /** 8. Penugasan pegawai oleh Admin */
    public function test_tugaskan_pegawai_oleh_admin(): void
    {
        $booking = Booking::create([
            'user_id' => $this->pelanggan->id,
            'vehicle_id' => $this->vehicle->id,
            'booking_code' => 'RNT-TEST-006',
            'destination' => 'Malang',
            'delivery_address' => 'Jl. Mawar',
            'pickup_at' => now()->addDays(2),
            'return_at' => now()->addDays(3),
            'duration_days' => 1,
            'price_per_day' => 300000,
            'total_price' => 300000,
            'dp_amount' => 100000,
            'status' => 'booked',
        ]);

        $response = $this->actingAs($this->admin)->post("/admin/bookings/{$booking->id}/assign-delivery", [
            'employee_id' => $this->pegawai->id,
            'notes' => 'Tolong antar tepat waktu',
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertEquals('assigned', $booking->fresh()->status);
        $this->assertDatabaseHas('deliveries', [
            'booking_id' => $booking->id,
            'employee_id' => $this->pegawai->id,
            'status' => 'assigned',
        ]);
    }

    /** 9. Penugasan pegawai ditolak jika user bukan role pegawai atau status booking belum booked/assigned */
    public function test_tugaskan_pegawai_ditolak_jika_bukan_pegawai_atau_status_salah(): void
    {
        $booking = Booking::create([
            'user_id' => $this->pelanggan->id,
            'vehicle_id' => $this->vehicle->id,
            'booking_code' => 'RNT-TEST-007',
            'destination' => 'Malang',
            'delivery_address' => 'Jl. Mawar',
            'pickup_at' => now()->addDays(2),
            'return_at' => now()->addDays(3),
            'duration_days' => 1,
            'price_per_day' => 300000,
            'total_price' => 300000,
            'dp_amount' => 100000,
            'status' => 'pending_dp', // masih pending_dp
        ]);

        // Ditolak karena status masih pending_dp
        $response1 = $this->actingAs($this->admin)->post("/admin/bookings/{$booking->id}/assign-delivery", [
            'employee_id' => $this->pegawai->id,
        ]);
        $response1->assertSessionHasErrors('booking');

        // Ubah status ke booked
        $booking->update(['status' => 'booked']);

        // Ditolak jika assign ke user yang bukan pegawai (misal pelanggan)
        $response2 = $this->actingAs($this->admin)->post("/admin/bookings/{$booking->id}/assign-delivery", [
            'employee_id' => $this->pelanggan->id,
        ]);
        $response2->assertSessionHasErrors('employee_id');
    }

    /** 10. Alur status pengiriman berurutan secara ketat */
    public function test_alur_status_pengiriman_secara_berurutan(): void
    {
        $booking = Booking::create([
            'user_id' => $this->pelanggan->id,
            'vehicle_id' => $this->vehicle->id,
            'booking_code' => 'RNT-TEST-008',
            'destination' => 'Malang',
            'delivery_address' => 'Jl. Mawar',
            'pickup_at' => now()->addHour(),
            'return_at' => now()->addDays(1),
            'duration_days' => 1,
            'price_per_day' => 300000,
            'total_price' => 300000,
            'dp_amount' => 100000,
            'status' => 'assigned',
        ]);

        $delivery = Delivery::create([
            'booking_id' => $booking->id,
            'employee_id' => $this->pegawai->id,
            'assigned_by' => $this->admin->id,
            'status' => 'assigned',
        ]);

        Payment::create([
            'booking_id' => $booking->id,
            'payment_method_id' => $this->paymentMethod->id,
            'type' => 'dp',
            'amount' => 100000,
            'status' => 'verified',
            'paid_at' => now(),
            'verified_at' => now(),
            'verified_by' => $this->admin->id,
        ]);

        // Step 1: Pegawai approve
        $this->actingAs($this->pegawai)->post("/pegawai/deliveries/{$delivery->id}/status", [
            'status' => 'approved',
        ])->assertSessionHasNoErrors();
        $this->assertEquals('approved', $delivery->fresh()->status);

        // Step 2: Pegawai delivering
        $this->actingAs($this->pegawai)->post("/pegawai/deliveries/{$delivery->id}/status", [
            'status' => 'delivering',
        ])->assertSessionHasNoErrors();
        $this->assertEquals('delivering', $delivery->fresh()->status);
        $this->assertEquals('delivering', $booking->fresh()->status);

        // Step 3: Pegawai delivered (serah terima ke pelanggan -> booking on_rent)
        $this->actingAs($this->pegawai)->post("/pegawai/deliveries/{$delivery->id}/status", [
            'status' => 'delivered',
        ])->assertSessionHasNoErrors();
        $this->assertEquals('delivered', $delivery->fresh()->status);
        $this->assertEquals('on_rent', $booking->fresh()->status);

        // Step 4: Pegawai picked_up
        $this->actingAs($this->pegawai)->post("/pegawai/deliveries/{$delivery->id}/status", [
            'status' => 'picked_up',
        ])->assertSessionHasNoErrors();
        $this->assertEquals('picked_up', $delivery->fresh()->status);
        $this->assertEquals('returned', $booking->fresh()->status);

        // Simulasi booking completed oleh admin/sistem setelah pelunasan
        $booking->update(['status' => 'completed']);

        // Step 5: Pegawai in_garage (kembali ke garasi)
        $this->actingAs($this->pegawai)->post("/pegawai/deliveries/{$delivery->id}/status", [
            'status' => 'in_garage',
        ])->assertSessionHasNoErrors();
        $this->assertEquals('in_garage', $delivery->fresh()->status);
    }

    /** 11. Perubahan status pengiriman loncat / tidak berurutan ditolak */
    public function test_status_pengiriman_di_luar_urutan_ditolak(): void
    {
        $booking = Booking::create([
            'user_id' => $this->pelanggan->id,
            'vehicle_id' => $this->vehicle->id,
            'booking_code' => 'RNT-TEST-009',
            'destination' => 'Malang',
            'delivery_address' => 'Jl. Mawar',
            'pickup_at' => now()->addHour(),
            'return_at' => now()->addDays(1),
            'duration_days' => 1,
            'price_per_day' => 300000,
            'total_price' => 300000,
            'dp_amount' => 100000,
            'status' => 'assigned',
        ]);

        $delivery = Delivery::create([
            'booking_id' => $booking->id,
            'employee_id' => $this->pegawai->id,
            'assigned_by' => $this->admin->id,
            'status' => 'assigned',
        ]);

        Payment::create([
            'booking_id' => $booking->id,
            'payment_method_id' => $this->paymentMethod->id,
            'type' => 'dp',
            'amount' => 100000,
            'status' => 'verified',
            'paid_at' => now(),
            'verified_at' => now(),
            'verified_by' => $this->admin->id,
        ]);

        // Coba loncat dari assigned langsung delivered (tanpa approved & delivering)
        $response = $this->actingAs($this->pegawai)->post("/pegawai/deliveries/{$delivery->id}/status", [
            'status' => 'delivered',
        ]);

        $response->assertSessionHasErrors('status');
        $this->assertEquals('assigned', $delivery->fresh()->status);
    }

    /** 12. Upload foto dokumentasi pengiriman */
    public function test_upload_foto_pengiriman(): void
    {
        $booking = Booking::create([
            'user_id' => $this->pelanggan->id,
            'vehicle_id' => $this->vehicle->id,
            'booking_code' => 'RNT-TEST-010',
            'destination' => 'Malang',
            'delivery_address' => 'Jl. Mawar',
            'pickup_at' => now()->addHour(),
            'return_at' => now()->addDays(1),
            'duration_days' => 1,
            'price_per_day' => 300000,
            'total_price' => 300000,
            'dp_amount' => 100000,
            'status' => 'delivering',
        ]);

        $delivery = Delivery::create([
            'booking_id' => $booking->id,
            'employee_id' => $this->pegawai->id,
            'assigned_by' => $this->admin->id,
            'status' => 'delivering',
        ]);

        $photo = UploadedFile::fake()->image('kondisi_kirim.jpg');

        $response = $this->actingAs($this->pegawai)->post("/pegawai/deliveries/{$delivery->id}/photos", [
            'stage' => 'kirim',
            'photo' => $photo,
            'note' => 'Bodi depan mulus',
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('delivery_photos', [
            'delivery_id' => $delivery->id,
            'stage' => 'kirim',
            'note' => 'Bodi depan mulus',
        ]);
    }

    /** 13. Pencatatan kerusakan kendaraan */
    public function test_pencatatan_kerusakan_kendaraan(): void
    {
        $booking = Booking::create([
            'user_id' => $this->pelanggan->id,
            'vehicle_id' => $this->vehicle->id,
            'booking_code' => 'RNT-TEST-011',
            'destination' => 'Malang',
            'delivery_address' => 'Jl. Mawar',
            'pickup_at' => now()->subDays(2),
            'return_at' => now()->subDay(),
            'duration_days' => 1,
            'price_per_day' => 300000,
            'total_price' => 300000,
            'dp_amount' => 100000,
            'status' => 'returned',
        ]);

        $response = $this->actingAs($this->admin)->post("/admin/bookings/{$booking->id}/damages", [
            'description' => 'Baret pada bumper kanan depan',
            'fine_amount' => 150000,
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('damages', [
            'booking_id' => $booking->id,
            'fine_amount' => 150000,
        ]);
    }

    /** 14. Log lokasi GPS hanya saat booking on_rent */
    public function test_log_lokasi_sukses_saat_on_rent_dan_diabaikan_jika_bukan_on_rent(): void
    {
        $booking = Booking::create([
            'user_id' => $this->pelanggan->id,
            'vehicle_id' => $this->vehicle->id,
            'booking_code' => 'RNT-TEST-012',
            'destination' => 'Malang',
            'delivery_address' => 'Jl. Mawar',
            'pickup_at' => now()->subHour(),
            'return_at' => now()->addDays(1),
            'duration_days' => 1,
            'price_per_day' => 300000,
            'total_price' => 300000,
            'dp_amount' => 100000,
            'status' => 'pending_dp', // belum on_rent
        ]);

        // Saat pending_dp: diabaikan
        $response1 = $this->actingAs($this->pelanggan)->postJson("/bookings/{$booking->id}/location", [
            'lat' => -7.2575,
            'lng' => 112.7521,
        ]);
        $response1->assertJson(['status' => 'ignored']);
        $this->assertDatabaseMissing('location_logs', ['booking_id' => $booking->id]);

        // Saat on_rent: berhasil disimpan
        $booking->update(['status' => 'on_rent']);
        $response2 = $this->actingAs($this->pelanggan)->postJson("/bookings/{$booking->id}/location", [
            'lat' => -7.2575,
            'lng' => 112.7521,
        ]);
        $response2->assertJson(['status' => 'ok']);
        $this->assertDatabaseHas('location_logs', ['booking_id' => $booking->id]);
    }

    /** 15. Perhitungan denda keterlambatan saat picked_up */
    public function test_perhitungan_denda_keterlambatan_saat_in_garage(): void
    {
        // Sewa harusnya kembali 2 jam yang lalu
        $booking = Booking::create([
            'user_id' => $this->pelanggan->id,
            'vehicle_id' => $this->vehicle->id,
            'booking_code' => 'RNT-TEST-013',
            'destination' => 'Malang',
            'delivery_address' => 'Jl. Mawar',
            'pickup_at' => now()->subDays(2),
            'return_at' => now()->subHours(2),
            'duration_days' => 1,
            'price_per_day' => 300000,
            'total_price' => 300000,
            'dp_amount' => 100000,
            'status' => 'on_rent',
        ]);

        $delivery = Delivery::create([
            'booking_id' => $booking->id,
            'employee_id' => $this->pegawai->id,
            'assigned_by' => $this->admin->id,
            'status' => 'delivered',
        ]);

        // Pegawai konfirmasi picked_up
        $this->actingAs($this->pegawai)->post("/pegawai/deliveries/{$delivery->id}/status", [
            'status' => 'picked_up',
        ])->assertSessionHasNoErrors();

        $bookingRefreshed = $booking->fresh();
        $this->assertEquals('returned', $bookingRefreshed->status);
        // Terlambat 2 jam * late_fee_per_hour (10000) = 20000
        $this->assertGreaterThan(0, (float) $bookingRefreshed->late_fee_total);
    }

    /** 16. Pelunasan dan penyelesaian booking (completed) */
    public function test_pelunasan_mengubah_status_booking_ke_completed(): void
    {
        $booking = Booking::create([
            'user_id' => $this->pelanggan->id,
            'vehicle_id' => $this->vehicle->id,
            'booking_code' => 'RNT-TEST-014',
            'destination' => 'Malang',
            'delivery_address' => 'Jl. Mawar',
            'pickup_at' => now()->subDays(2),
            'return_at' => now()->subDay(),
            'duration_days' => 1,
            'price_per_day' => 300000,
            'total_price' => 300000,
            'dp_amount' => 100000,
            'late_fee_total' => 20000,
            'status' => 'returned',
        ]);

        // DP sudah verified (100.000)
        Payment::create([
            'booking_id' => $booking->id,
            'payment_method_id' => $this->paymentMethod->id,
            'type' => 'dp',
            'amount' => 100000,
            'proof' => 'payments/dp.jpg',
            'status' => 'verified',
            'paid_at' => now()->subDays(2),
            'verified_at' => now()->subDays(2),
        ]);

        // Pelanggan bayar pelunasan (sisa tagihan = 300.000 + 20.000 - 100.000 = 220.000)
        $proof = UploadedFile::fake()->image('bukti_pelunasan.jpg');
        $this->actingAs($this->pelanggan)->post("/bookings/{$booking->id}/pay", [
            'payment_method_id' => $this->paymentMethod->id,
            'proof' => $proof,
        ])->assertSessionHasNoErrors();

        $pelunasanPayment = Payment::where('booking_id', $booking->id)->where('type', 'pelunasan')->first();
        $this->assertNotNull($pelunasanPayment);
        $this->assertEquals(220000, (float) $pelunasanPayment->amount);

        // Admin memverifikasi pelunasan
        $this->actingAs($this->admin)->post("/admin/payments/{$pelunasanPayment->id}/verify", [
            'action' => 'verify',
        ])->assertSessionHasNoErrors();

        $this->assertEquals('completed', $booking->fresh()->status);
    }

    /** 17. User blacklist ditolak 403 di seluruh area terproteksi */
    public function test_user_blacklist_ditolak_403(): void
    {
        $blacklistedUser = User::create([
            'name' => 'Bad User',
            'email' => 'bad@test.test',
            'password' => 'password',
            'role' => 'pelanggan',
            'is_blacklisted' => true,
            'blacklist_reason' => 'Membawa kabur mobil',
        ]);

        $response = $this->actingAs($blacklistedUser)->get('/bookings');
        $response->assertStatus(403);
    }

    /** 18. Akses antar peran ditolak 403 */
    public function test_akses_antar_peran_harus_403(): void
    {
        // Pelanggan akses admin
        $this->actingAs($this->pelanggan)->get('/admin/vehicles')->assertStatus(403);
        $this->actingAs($this->pelanggan)->get('/admin/bookings')->assertStatus(403);
        $this->actingAs($this->pelanggan)->get('/admin/users')->assertStatus(403);

        // Pegawai akses admin
        $this->actingAs($this->pegawai)->get('/admin/vehicles')->assertStatus(403);
        $this->actingAs($this->pegawai)->get('/admin/users')->assertStatus(403);

        // Pelanggan akses pegawai
        $this->actingAs($this->pelanggan)->get('/pegawai/deliveries')->assertStatus(403);
    }

    /** 19. Hapus metode pembayaran yang memiliki transaksi harus ditolak */
    public function test_hapus_metode_pembayaran_yang_memiliki_transaksi_ditolak(): void
    {
        $booking = Booking::create([
            'user_id' => $this->pelanggan->id,
            'vehicle_id' => $this->vehicle->id,
            'booking_code' => 'RNT-TEST-019',
            'destination' => 'Malang',
            'delivery_address' => 'Jl. Mawar',
            'pickup_at' => now()->addDays(2),
            'return_at' => now()->addDays(3),
            'duration_days' => 1,
            'price_per_day' => 300000,
            'total_price' => 300000,
            'dp_amount' => 100000,
            'status' => 'pending_dp',
        ]);

        Payment::create([
            'booking_id' => $booking->id,
            'payment_method_id' => $this->paymentMethod->id,
            'type' => 'dp',
            'amount' => 100000,
            'proof' => 'payments/test.jpg',
            'status' => 'pending',
            'paid_at' => now(),
        ]);

        $response = $this->actingAs($this->admin)->delete("/admin/payment-methods/{$this->paymentMethod->id}");

        $response->assertSessionHasErrors('payment_method');
        $this->assertDatabaseHas('payment_methods', ['id' => $this->paymentMethod->id]);
    }

    /** 20. Bayar ditolak jika masih ada pembayaran pending untuk booking yang sama */
    public function test_bayar_ditolak_jika_masih_ada_pembayaran_pending(): void
    {
        $booking = Booking::create([
            'user_id' => $this->pelanggan->id,
            'vehicle_id' => $this->vehicle->id,
            'booking_code' => 'RNT-TEST-020',
            'destination' => 'Malang',
            'delivery_address' => 'Jl. Mawar',
            'pickup_at' => now()->addDays(2),
            'return_at' => now()->addDays(3),
            'duration_days' => 1,
            'price_per_day' => 300000,
            'total_price' => 300000,
            'dp_amount' => 100000,
            'status' => 'pending_dp',
        ]);

        Payment::create([
            'booking_id' => $booking->id,
            'payment_method_id' => $this->paymentMethod->id,
            'type' => 'dp',
            'amount' => 100000,
            'proof' => 'payments/test1.jpg',
            'status' => 'pending',
            'paid_at' => now(),
        ]);

        $proof2 = UploadedFile::fake()->image('bukti2.jpg');
        $response = $this->actingAs($this->pelanggan)->post("/bookings/{$booking->id}/pay", [
            'payment_method_id' => $this->paymentMethod->id,
            'proof' => $proof2,
        ]);

        $response->assertSessionHasErrors('payment');
    }

    /** 21. Blacklist ditolak untuk akun selain pelanggan */
    public function test_blacklist_ditolak_untuk_akun_selain_pelanggan(): void
    {
        $otherAdmin = User::create([
            'name' => 'Admin Dua',
            'email' => 'admin2@test.test',
            'password' => 'password',
            'role' => 'admin',
        ]);

        // Coba blacklist admin lain
        $response1 = $this->actingAs($this->admin)->post("/admin/users/{$otherAdmin->id}/blacklist", [
            'is_blacklisted' => true,
            'blacklist_reason' => 'Uji coba',
        ]);
        $response1->assertSessionHasErrors('user');
        $this->assertFalse($otherAdmin->fresh()->is_blacklisted);

        // Coba blacklist pegawai
        $response2 = $this->actingAs($this->admin)->post("/admin/users/{$this->pegawai->id}/blacklist", [
            'is_blacklisted' => true,
            'blacklist_reason' => 'Uji coba pegawai',
        ]);
        $response2->assertSessionHasErrors('user');
        $this->assertFalse($this->pegawai->fresh()->is_blacklisted);
    }

    /** 22. Catat kerusakan ditolak pada booking yang belum disewa atau sudah selesai */
    public function test_catat_kerusakan_ditolak_pada_booking_status_tidak_sesuai(): void
    {
        // Booking masih pending_dp
        $bookingPending = Booking::create([
            'user_id' => $this->pelanggan->id,
            'vehicle_id' => $this->vehicle->id,
            'booking_code' => 'RNT-TEST-022A',
            'destination' => 'Malang',
            'delivery_address' => 'Jl. Mawar',
            'pickup_at' => now()->addDays(2),
            'return_at' => now()->addDays(3),
            'duration_days' => 1,
            'price_per_day' => 300000,
            'total_price' => 300000,
            'dp_amount' => 100000,
            'status' => 'pending_dp',
        ]);

        $response1 = $this->actingAs($this->admin)->post("/admin/bookings/{$bookingPending->id}/damages", [
            'description' => 'Lecet pintu',
            'fine_amount' => 50000,
        ]);
        $response1->assertSessionHasErrors('booking');

        // Booking sudah completed
        $bookingCompleted = Booking::create([
            'user_id' => $this->pelanggan->id,
            'vehicle_id' => $this->vehicle->id,
            'booking_code' => 'RNT-TEST-022B',
            'destination' => 'Malang',
            'delivery_address' => 'Jl. Mawar',
            'pickup_at' => now()->subDays(5),
            'return_at' => now()->subDays(4),
            'duration_days' => 1,
            'price_per_day' => 300000,
            'total_price' => 300000,
            'dp_amount' => 100000,
            'status' => 'completed',
        ]);

        $response2 = $this->actingAs($this->admin)->post("/admin/bookings/{$bookingCompleted->id}/damages", [
            'description' => 'Baret spion',
            'fine_amount' => 50000,
        ]);
        $response2->assertSessionHasErrors('booking');
    }

    /** 23. Kode booking generate berurutan dan tidak bentrok */
    public function test_generate_kode_booking_tidak_duplikat(): void
    {
        $today = now()->format('Ymd');
        Booking::create([
            'user_id' => $this->pelanggan->id,
            'vehicle_id' => $this->vehicle->id,
            'booking_code' => "RNT-{$today}-005",
            'destination' => 'Malang',
            'delivery_address' => 'Jl. Mawar',
            'pickup_at' => now()->addDays(2),
            'return_at' => now()->addDays(3),
            'duration_days' => 1,
            'price_per_day' => 300000,
            'total_price' => 300000,
            'dp_amount' => 100000,
            'status' => 'pending_dp',
        ]);

        // Karena sudah ada kode 005 hari ini, kode berikutnya harus 006 (bukan 002)
        $nextCode = Booking::generateCode();
        $this->assertEquals("RNT-{$today}-006", $nextCode);
    }

    /** 24. Verifikasi hanya dapat dilakukan pada pembayaran status pending */
    public function test_verifikasi_pembayaran_hanya_untuk_status_pending(): void
    {
        $booking = Booking::create([
            'user_id' => $this->pelanggan->id,
            'vehicle_id' => $this->vehicle->id,
            'booking_code' => 'RNT-TEST-024',
            'destination' => 'Malang',
            'delivery_address' => 'Jl. Mawar',
            'pickup_at' => now()->addDays(2),
            'return_at' => now()->addDays(3),
            'duration_days' => 1,
            'price_per_day' => 300000,
            'total_price' => 300000,
            'dp_amount' => 100000,
            'status' => 'booked',
        ]);

        $verifiedPayment = Payment::create([
            'booking_id' => $booking->id,
            'payment_method_id' => $this->paymentMethod->id,
            'type' => 'dp',
            'amount' => 100000,
            'proof' => 'payments/test.jpg',
            'status' => 'verified',
            'paid_at' => now(),
            'verified_by' => $this->admin->id,
            'verified_at' => now(),
        ]);

        $response = $this->actingAs($this->admin)->post("/admin/payments/{$verifiedPayment->id}/verify", [
            'action' => 'verify',
        ]);

        $response->assertSessionHasErrors('payment');
    }

    /** 25. Otorisasi akses booking dan lokasi antar pelanggan */
    public function test_otorisasi_antar_pelanggan_dan_pegawai(): void
    {
        $otherCustomer = User::create([
            'name' => 'Pelanggan Lain',
            'email' => 'other@test.test',
            'password' => 'password',
            'role' => 'pelanggan',
        ]);

        $booking = Booking::create([
            'user_id' => $this->pelanggan->id,
            'vehicle_id' => $this->vehicle->id,
            'booking_code' => 'RNT-TEST-025',
            'destination' => 'Malang',
            'delivery_address' => 'Jl. Mawar',
            'pickup_at' => now()->addDays(2),
            'return_at' => now()->addDays(3),
            'duration_days' => 1,
            'price_per_day' => 300000,
            'total_price' => 300000,
            'dp_amount' => 100000,
            'status' => 'on_rent',
        ]);

        // Pelanggan lain tidak boleh melihat detail booking milik pelanggan pertama
        $this->actingAs($otherCustomer)->get("/bookings/{$booking->id}")->assertStatus(403);

        // Pelanggan lain tidak boleh mengirim log lokasi ke booking pelanggan pertama
        $responseLocation = $this->actingAs($otherCustomer)->postJson("/bookings/{$booking->id}/location", [
            'lat' => -7.25,
            'lng' => 112.75,
        ]);
        $responseLocation->assertStatus(403);

        // Pegawai lain tidak boleh melihat tugas pengiriman yang bukan miliknya
        $delivery = Delivery::create([
            'booking_id' => $booking->id,
            'employee_id' => $this->pegawai->id,
            'assigned_by' => $this->admin->id,
            'status' => 'assigned',
        ]);

        Payment::create([
            'booking_id' => $booking->id,
            'payment_method_id' => $this->paymentMethod->id,
            'type' => 'dp',
            'amount' => 100000,
            'status' => 'verified',
            'paid_at' => now(),
            'verified_at' => now(),
            'verified_by' => $this->admin->id,
        ]);

        $otherPegawai = User::create([
            'name' => 'Pegawai Lain',
            'email' => 'pegawai2@test.test',
            'password' => 'password',
            'role' => 'pegawai',
        ]);

        $this->actingAs($otherPegawai)->get("/pegawai/deliveries/{$delivery->id}")->assertStatus(403);
    }
}
