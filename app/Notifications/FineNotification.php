<?php

namespace App\Notifications;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class FineNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Booking $booking,
        public string $fineType, // 'keterlambatan' atau 'kerusakan'
        public float $amount,
        public ?string $description = null
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $formattedAmount = 'Rp ' . number_format($this->amount, 0, ',', '.');
        $vehicleName = "{$this->booking->vehicle?->brand} {$this->booking->vehicle?->model}";

        $message = $this->fineType === 'keterlambatan'
            ? "Terdapat denda keterlambatan pengembalian unit {$vehicleName} sebesar {$formattedAmount}."
            : "Terdapat denda kerusakan unit {$vehicleName} sebesar {$formattedAmount}" . ($this->description ? " ({$this->description})" : "") . ".";

        return [
            'title' => 'Tagihan Denda ' . ucfirst($this->fineType),
            'message' => $message,
            'booking_id' => $this->booking->id,
            'booking_code' => $this->booking->booking_code,
            'fine_type' => $this->fineType,
            'amount' => $this->amount,
            'url' => "/bookings/{$this->booking->id}",
            'type' => 'fine',
        ];
    }
}
