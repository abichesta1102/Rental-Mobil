<?php

namespace App\Notifications;

use App\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class NewPaymentNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Payment $payment
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $booking = $this->payment->booking;
        $customerName = $booking?->user?->name ?? 'Pelanggan';
        $formattedAmount = 'Rp ' . number_format((float) $this->payment->amount, 0, ',', '.');
        $typeLabel = strtoupper($this->payment->type);

        return [
            'title' => "Bukti Pembayaran Baru ({$typeLabel})",
            'message' => "{$customerName} telah mengunggah bukti pembayaran {$typeLabel} sebesar {$formattedAmount} untuk booking {$booking?->booking_code}.",
            'booking_id' => $booking?->id,
            'payment_id' => $this->payment->id,
            'amount' => (float) $this->payment->amount,
            'url' => "/admin/bookings/{$booking?->id}",
            'type' => 'new_payment',
        ];
    }
}
