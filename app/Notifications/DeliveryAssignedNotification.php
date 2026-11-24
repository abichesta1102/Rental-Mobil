<?php

namespace App\Notifications;

use App\Models\Delivery;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class DeliveryAssignedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Delivery $delivery
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $booking = $this->delivery->booking;
        $vehicleName = "{$booking?->vehicle?->brand} {$booking?->vehicle?->model} ({$booking?->vehicle?->plate})";

        return [
            'title' => 'Tugas Pengiriman Baru',
            'message' => "Anda ditugaskan untuk mengantar {$vehicleName} ke pelanggan {$booking?->user?->name}. Alamat: {$booking?->delivery_address}.",
            'booking_id' => $booking?->id,
            'delivery_id' => $this->delivery->id,
            'url' => "/pegawai/deliveries/{$this->delivery->id}",
            'type' => 'delivery_assigned',
        ];
    }
}
