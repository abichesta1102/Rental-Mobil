<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Expense;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinanceFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $pelanggan;
    protected Vehicle $vehicle;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'name' => 'Admin Fin',
            'email' => 'adminfin@test.test',
            'password' => 'password',
            'role' => 'admin',
        ]);
        $this->pelanggan = User::create([
            'name' => 'Pelanggan Fin',
            'email' => 'pelangganfin@test.test',
            'password' => 'password',
            'role' => 'pelanggan',
        ]);

        $this->vehicle = Vehicle::create([
            'plate' => 'N 5555 EF',
            'brand' => 'Toyota',
            'model' => 'Yaris',
            'transmission' => 'matic',
            'seats' => 5,
            'purchase_price' => 200000000,
            'price_per_day' => 350000,
            'late_fee_per_hour' => 50000,
            'status' => 'available',
        ]);
    }

    public function test_non_admin_cannot_access_finance(): void
    {
        $this->actingAs($this->pelanggan)->get('/admin/finance')->assertForbidden();
    }

    public function test_admin_can_access_finance_page(): void
    {
        $this->actingAs($this->admin)->get('/admin/finance')->assertStatus(200);
    }

    public function test_admin_can_store_and_destroy_expense(): void
    {
        $response = $this->actingAs($this->admin)->post('/admin/finance/expenses', [
            'vehicle_id' => $this->vehicle->id,
            'category' => 'oli',
            'description' => 'Ganti Oli Mesin',
            'amount' => 450000,
            'spent_at' => now()->toDateString(),
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('expenses', [
            'category' => 'oli',
            'amount' => 450000,
            'created_by' => $this->admin->id,
        ]);

        $expense = Expense::first();
        $deleteResponse = $this->actingAs($this->admin)->delete("/admin/finance/expenses/{$expense->id}");
        $deleteResponse->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('expenses', ['id' => $expense->id]);
    }

    public function test_roi_and_break_even_calculations(): void
    {
        $pm = PaymentMethod::create([
            'type' => 'bank_transfer',
            'label' => 'BCA',
            'account_number' => '1234567890',
            'account_name' => 'PT Rental Mobil',
            'is_active' => true,
        ]);

        $booking = Booking::create([
            'user_id' => $this->pelanggan->id,
            'vehicle_id' => $this->vehicle->id,
            'booking_code' => 'RNT-FIN-01',
            'destination' => 'Batu',
            'delivery_address' => 'Jl. Mawar',
            'pickup_at' => now()->subDays(5),
            'return_at' => now()->subDays(4),
            'duration_days' => 1,
            'price_per_day' => 350000,
            'total_price' => 350000,
            'dp_amount' => 100000,
            'status' => 'completed',
        ]);

        Payment::create([
            'booking_id' => $booking->id,
            'payment_method_id' => $pm->id,
            'type' => 'dp',
            'amount' => 100000,
            'status' => 'verified',
            'paid_at' => now()->subDays(5),
            'verified_at' => now()->subDays(5),
            'verified_by' => $this->admin->id,
        ]);

        Payment::create([
            'booking_id' => $booking->id,
            'payment_method_id' => $pm->id,
            'type' => 'pelunasan',
            'amount' => 250000,
            'status' => 'verified',
            'paid_at' => now()->subDays(4),
            'verified_at' => now()->subDays(4),
            'verified_by' => $this->admin->id,
        ]);

        Expense::create([
            'vehicle_id' => $this->vehicle->id,
            'category' => 'servis',
            'description' => 'Servis Ringan',
            'amount' => 50000,
            'spent_at' => now()->subDays(4)->toDateString(),
            'created_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)->get('/admin/finance');
        $response->assertStatus(200);

        // Check props passed to Inertia
        $response->assertInertia(fn ($page) => $page
            ->component('admin/finance/Index')
            ->has('roi_data')
            ->where('roi_data.0.accumulated_net_profit', 300000)
            ->where('roi_data.0.completed_rentals_count', 1)
        );
    }
}
