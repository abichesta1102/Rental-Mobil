<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\User;
use App\Models\Vehicle;
use App\Notifications\FineNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationFeatureTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Booking $booking;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'name' => 'User Notif',
            'email' => 'usernotif@test.test',
            'password' => 'password',
            'role' => 'pelanggan',
        ]);

        $vehicle = Vehicle::create([
            'plate' => 'N 4444 CD',
            'brand' => 'Honda',
            'model' => 'Brio',
            'transmission' => 'matic',
            'seats' => 4,
            'price_per_day' => 300000,
            'late_fee_per_hour' => 50000,
            'status' => 'available',
        ]);

        $this->booking = Booking::create([
            'user_id' => $this->user->id,
            'vehicle_id' => $vehicle->id,
            'booking_code' => 'RNT-NOTIF-01',
            'destination' => 'Batu',
            'delivery_address' => 'Jl. Mawar',
            'pickup_at' => now()->addHour(),
            'return_at' => now()->addDays(1),
            'duration_days' => 1,
            'price_per_day' => 300000,
            'total_price' => 300000,
            'dp_amount' => 100000,
            'status' => 'booked',
        ]);
    }

    public function test_guest_cannot_access_notifications(): void
    {
        $this->get('/notifications')->assertRedirect('/login');
    }

    public function test_user_can_view_notifications_list(): void
    {
        $this->user->notify(new FineNotification(
            booking: $this->booking,
            fineType: 'keterlambatan',
            amount: 50000,
            description: 'Terlambat 1 jam'
        ));

        $response = $this->actingAs($this->user)->get('/notifications');
        $response->assertStatus(200);
        $this->assertEquals(1, $this->user->unreadNotifications()->count());
    }

    public function test_user_can_mark_notification_as_read(): void
    {
        $this->user->notify(new FineNotification(
            booking: $this->booking,
            fineType: 'keterlambatan',
            amount: 50000,
            description: 'Terlambat 1 jam'
        ));

        $notificationId = $this->user->unreadNotifications->first()->id;

        $response = $this->actingAs($this->user)->post("/notifications/{$notificationId}/read");
        $response->assertSessionHasNoErrors();

        $this->assertEquals(0, $this->user->fresh()->unreadNotifications()->count());
    }

    public function test_user_can_mark_all_notifications_as_read(): void
    {
        $this->user->notify(new FineNotification(booking: $this->booking, fineType: 'keterlambatan', amount: 50000, description: '1'));
        $this->user->notify(new FineNotification(booking: $this->booking, fineType: 'kerusakan', amount: 100000, description: '2'));

        $this->assertEquals(2, $this->user->unreadNotifications()->count());

        $response = $this->actingAs($this->user)->post('/notifications/read-all');
        $response->assertSessionHasNoErrors();

        $this->assertEquals(0, $this->user->fresh()->unreadNotifications()->count());
    }
}
