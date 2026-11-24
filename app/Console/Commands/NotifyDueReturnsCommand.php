<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Models\User;
use App\Notifications\DueReturnNotification;
use Illuminate\Console\Command;

class NotifyDueReturnsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'rental:notify-due-returns';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Kirim notifikasi jatuh tempo masa sewa untuk unit yang harus diambil';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $dueBookings = Booking::with(['user', 'vehicle', 'delivery.employee'])
            ->where('status', 'on_rent')
            ->where('return_at', '<=', now())
            ->whereNull('pickup_notified_at')
            ->get();

        if ($dueBookings->isEmpty()) {
            $this->info('Tidak ada booking yang jatuh tempo untuk dinotifikasi.');
            return self::SUCCESS;
        }

        $admins = User::where('role', 'admin')->get();
        $count = 0;

        foreach ($dueBookings as $booking) {
            // Notifikasi ke pelanggan
            if ($booking->user) {
                $booking->user->notify(new DueReturnNotification($booking, 'pelanggan'));
            }

            // Notifikasi ke pegawai yang ditugaskan
            if ($booking->delivery?->employee) {
                $booking->delivery->employee->notify(new DueReturnNotification($booking, 'pegawai'));
            }

            // Notifikasi ke seluruh admin
            foreach ($admins as $admin) {
                $admin->notify(new DueReturnNotification($booking, 'admin'));
            }

            $booking->update(['pickup_notified_at' => now()]);
            $count++;
        }

        $this->info("Berhasil mengirim notifikasi untuk {$count} booking yang jatuh tempo.");

        return self::SUCCESS;
    }
}
