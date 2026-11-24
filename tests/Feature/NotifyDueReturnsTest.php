<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Delivery;
use App\Models\User;
use App\Models\Vehicle;
use App\Notifications\DueReturnNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class NotifyDueReturnsTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_rental_notify_due_returns_sends_notifications_and_sets_timestamp(): void
    {
        Notification::fake();

        $admin = User::create([
            'name' => 'Admin',
            'email' => 'admin@test.test',
            'password' => 'password',
            'role' => 'admin',
        ]);
        $pegawai = User::create([
            'name' => 'Pegawai',
            'email' => 'pegawai@test.test',
            'password' => 'password',
            'role' => 'pegawai',
        ]);
        $pelanggan = User::create([
            'name' => 'Pelanggan',
            'email' => 'pelanggan@test.test',
            'password' => 'password',
            'role' => 'pelanggan',
        ]);

        $vehicle = Vehicle::create([
            'plate' => 'N 1111 AB',
            'brand' => 'Toyota',
            'model' => 'Avanza',
            'transmission' => 'matic',
            'seats' => 7,
            'price_per_day' => 300000,
            'late_fee_per_hour' => 20000,
            'status' => 'available',
        ]);

        $booking = Booking::create([
            'user_id' => $pelanggan->id,
            'vehicle_id' => $vehicle->id,
            'booking_code' => 'RNT-DUE-001',
            'destination' => 'Kota Batu',
            'delivery_address' => 'Jl. Panderman',
            'pickup_at' => now()->subDays(2),
            'return_at' => now()->subHour(),
            'duration_days' => 1,
            'price_per_day' => 300000,
            'total_price' => 300000,
            'dp_amount' => 100000,
            'status' => 'on_rent',
            'pickup_notified_at' => null,
        ]);

        Delivery::create([
            'booking_id' => $booking->id,
            'employee_id' => $pegawai->id,
            'assigned_by' => $admin->id,
            'status' => 'delivered',
        ]);

        $this->artisan('rental:notify-due-returns')
            ->expectsOutputToContain('Berhasil mengirim notifikasi untuk 1 booking')
            ->assertExitCode(0);

        $this->assertNotNull($booking->fresh()->pickup_notified_at);

        Notification::assertSentTo($pelanggan, DueReturnNotification::class);
        Notification::assertSentTo($pegawai, DueReturnNotification::class);
        Notification::assertSentTo($admin, DueReturnNotification::class);
    }

    public function test_command_does_not_re_notify_if_already_notified(): void
    {
        Notification::fake();

        $pelanggan = User::create([
            'name' => 'Pelanggan 2',
            'email' => 'pelanggan2@test.test',
            'password' => 'password',
            'role' => 'pelanggan',
        ]);

        $vehicle = Vehicle::create([
            'plate' => 'N 2222 AB',
            'brand' => 'Toyota',
            'model' => 'Innova',
            'transmission' => 'matic',
            'seats' => 7,
            'price_per_day' => 450000,
            'late_fee_per_hour' => 30000,
            'status' => 'available',
        ]);

        $booking = Booking::create([
            'user_id' => $pelanggan->id,
            'vehicle_id' => $vehicle->id,
            'booking_code' => 'RNT-DUE-002',
            'destination' => 'Kota Batu',
            'delivery_address' => 'Jl. Panderman',
            'pickup_at' => now()->subDays(2),
            'return_at' => now()->subHour(),
            'duration_days' => 1,
            'price_per_day' => 300000,
            'total_price' => 300000,
            'dp_amount' => 100000,
            'status' => 'on_rent',
            'pickup_notified_at' => now()->subMinutes(10),
        ]);

        $this->artisan('rental:notify-due-returns')
            ->expectsOutputToContain('Tidak ada booking yang jatuh tempo')
            ->assertExitCode(0);

        Notification::assertNothingSent();
    }
}
