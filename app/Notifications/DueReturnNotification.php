<?php

namespace App\Notifications;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class DueReturnNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Booking $booking,
        public string $recipientRole
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $vehicleName = "{$this->booking->vehicle?->brand} {$this->booking->vehicle?->model} ({$this->booking->vehicle?->plate})";

        if ($this->recipientRole === 'pelanggan') {
            return [
                'title' => 'Masa Sewa Habis',
                'message' => 'Masa sewa habis, unit akan diambil.',
                'booking_id' => $this->booking->id,
                'booking_code' => $this->booking->booking_code,
                'vehicle' => $vehicleName,
                'url' => "/bookings/{$this->booking->id}",
                'type' => 'due_return',
            ];
        }

        if ($this->recipientRole === 'pegawai') {
            $deliveryId = $this->booking->delivery?->id;
            return [
                'title' => 'Waktunya Penjemputan Unit',
                'message' => "Masa sewa booking {$this->booking->booking_code} ({$vehicleName}) telah habis. Segera jemput unit dari pelanggan.",
                'booking_id' => $this->booking->id,
                'booking_code' => $this->booking->booking_code,
                'vehicle' => $vehicleName,
                'url' => $deliveryId ? "/pegawai/deliveries/{$deliveryId}" : "/pegawai/deliveries",
                'type' => 'due_return',
            ];
        }

        return [
            'title' => 'Masa Sewa Habis',
            'message' => "Masa sewa booking {$this->booking->booking_code} ({$vehicleName}) telah jatuh tempo. Unit siap diambil.",
            'booking_id' => $this->booking->id,
            'booking_code' => $this->booking->booking_code,
            'vehicle' => $vehicleName,
            'url' => "/admin/bookings/{$this->booking->id}",
            'type' => 'due_return',
        ];
    }
}
