<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Delivery;
use App\Models\PaymentMethod;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeliveryPickupSettlementTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $pegawai;
    protected User $pelanggan;
    protected Vehicle $vehicle;
    protected PaymentMethod $paymentMethod;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'name' => 'Admin Test',
            'email' => 'admin@test.test',
            'password' => 'password',
            'role' => 'admin',
        ]);
        $this->pegawai = User::create([
            'name' => 'Pegawai Test',
            'email' => 'pegawai@test.test',
            'password' => 'password',
            'role' => 'pegawai',
        ]);
        $this->pelanggan = User::create([
            'name' => 'Pelanggan Test',
            'email' => 'pelanggan@test.test',
            'password' => 'password',
            'role' => 'pelanggan',
        ]);

        $this->vehicle = Vehicle::create([
            'plate' => 'N 3333 CD',
            'brand' => 'Honda',
            'model' => 'Brio',
            'transmission' => 'matic',
            'seats' => 4,
            'price_per_day' => 300000,
            'late_fee_per_hour' => 50000,
            'status' => 'available',
        ]);

        $this->paymentMethod = PaymentMethod::create([
            'type' => 'bank_transfer',
            'label' => 'BCA Transfer',
            'account_number' => '1234567890',
            'account_name' => 'Rental Mobil',
            'is_active' => true,
        ]);
    }

    public function test_pegawai_cannot_mark_delivered_if_dp_not_verified(): void
    {
        $booking = Booking::create([
            'user_id' => $this->pelanggan->id,
            'vehicle_id' => $this->vehicle->id,
            'booking_code' => 'RNT-PICKUP-01',
            'destination' => 'Batu',
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

        // Belum ada DP yang verified
        $response = $this->actingAs($this->pegawai)->post("/pegawai/deliveries/{$delivery->id}/status", [
            'status' => 'delivered',
        ]);

        $response->assertSessionHasErrors();
        $this->assertEquals('delivering', $delivery->fresh()->status);
    }

    public function test_picked_up_sets_actual_returned_at_and_late_fee_and_status_returned(): void
    {
        $now = now()->startOfMinute();
        $this->travelTo($now);

        $booking = Booking::create([
            'user_id' => $this->pelanggan->id,
            'vehicle_id' => $this->vehicle->id,
            'booking_code' => 'RNT-PICKUP-02',
            'destination' => 'Batu',
            'delivery_address' => 'Jl. Mawar',
            'pickup_at' => $now->copy()->subDays(2),
            'return_at' => $now->copy()->subHours(3),
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

        $response = $this->actingAs($this->pegawai)->post("/pegawai/deliveries/{$delivery->id}/status", [
            'status' => 'picked_up',
        ]);

        $response->assertSessionHasNoErrors();
        $bookingFresh = $booking->fresh();

        $this->assertEquals('returned', $bookingFresh->status);
        $this->assertNotNull($bookingFresh->actual_returned_at);
        $this->assertEquals(150000, (float) $bookingFresh->late_fee_total); // 3 jam * 50.000
    }

    public function test_pegawai_cannot_mark_in_garage_if_booking_not_completed(): void
    {
        $booking = Booking::create([
            'user_id' => $this->pelanggan->id,
            'vehicle_id' => $this->vehicle->id,
            'booking_code' => 'RNT-PICKUP-03',
            'destination' => 'Batu',
            'delivery_address' => 'Jl. Mawar',
            'pickup_at' => now()->subDays(2),
            'return_at' => now()->subHour(),
            'duration_days' => 1,
            'price_per_day' => 300000,
            'total_price' => 300000,
            'dp_amount' => 100000,
            'status' => 'returned',
        ]);

        $delivery = Delivery::create([
            'booking_id' => $booking->id,
            'employee_id' => $this->pegawai->id,
            'assigned_by' => $this->admin->id,
            'status' => 'picked_up',
        ]);

        // Coba set in_garage saat booking masih 'returned' (belum completed/lunas)
        $response = $this->actingAs($this->pegawai)->post("/pegawai/deliveries/{$delivery->id}/status", [
            'status' => 'in_garage',
        ]);

        $response->assertSessionHasErrors();
        $this->assertEquals('picked_up', $delivery->fresh()->status);
    }
}
