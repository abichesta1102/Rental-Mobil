<?php

namespace App\Http\Controllers\Pegawai;

use App\Http\Controllers\Controller;
use App\Models\Damage;
use App\Models\Delivery;
use App\Models\DeliveryPhoto;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class DeliveryController extends Controller
{
    public function index(Request $request): Response
    {
        $deliveries = Delivery::with(['booking.vehicle', 'booking.user', 'photos'])
            ->where('employee_id', $request->user()->id)
            ->latest()
            ->paginate(10);

        return Inertia::render('pegawai/deliveries/Index', [
            'deliveries' => $deliveries,
        ]);
    }

    public function show(Request $request, Delivery $delivery): Response
    {
        if ($delivery->employee_id !== $request->user()->id) {
            abort(403, 'Anda bukan petugas yang ditugaskan untuk pengiriman ini.');
        }

        $delivery->load([
            'booking.user',
            'booking.vehicle',
            'booking.damages',
            'booking.payments',
            'photos',
        ]);

        return Inertia::render('pegawai/deliveries/Show', [
            'delivery' => $delivery,
        ]);
    }

    public function updateStatus(Request $request, Delivery $delivery): RedirectResponse
    {
        if ($delivery->employee_id !== $request->user()->id) {
            abort(403, 'Akses ditolak.');
        }

        $data = $request->validate([
            'status' => ['required', 'in:approved,delivering,delivered,picked_up,in_garage'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $status = $data['status'];
        $booking = $delivery->booking;

        // Validasi urutan status pengiriman yang ketat
        $allowedTransitions = [
            'assigned' => ['approved'],
            'approved' => ['delivering'],
            'delivering' => ['delivered'],
            'delivered' => ['picked_up'],
            'picked_up' => ['in_garage'],
            'in_garage' => [],
        ];

        if (! in_array($status, $allowedTransitions[$delivery->status] ?? [], true)) {
            throw ValidationException::withMessages([
                'status' => "Perubahan status pengiriman tidak valid dari '{$delivery->status}' ke '{$status}'.",
            ]);
        }

        // Validasi kesesuaian status booking
        if ($status === 'approved' && $booking->status !== 'assigned') {
            throw ValidationException::withMessages([
                'status' => 'Pengiriman hanya dapat disetujui jika booking berstatus assigned.',
            ]);
        }

        if ($status === 'delivering' && ! in_array($booking->status, ['assigned', 'booked'], true)) {
            throw ValidationException::withMessages([
                'status' => 'Pengantaran hanya dapat dimulai jika booking berstatus assigned.',
            ]);
        }

        if ($status === 'delivered') {
            $dpVerified = $booking->payments()->where('type', 'dp')->where('status', 'verified')->exists();
            if (! $dpVerified || $booking->status === 'pending_dp') {
                throw ValidationException::withMessages([
                    'status' => 'Menunggu verifikasi DP oleh admin. Unit tidak dapat diserahkan sebelum DP diverifikasi.',
                ]);
            }
            if ($booking->status !== 'delivering') {
                throw ValidationException::withMessages([
                    'status' => 'Unit hanya dapat diserahkan ke pelanggan jika booking sedang dalam status delivering.',
                ]);
            }
        }

        if ($status === 'picked_up' && $booking->status !== 'on_rent') {
            throw ValidationException::withMessages([
                'status' => 'Penjemputan unit hanya dapat dilakukan jika booking sedang aktif disewa (on_rent).',
            ]);
        }

        if ($status === 'in_garage' && $booking->status !== 'completed') {
            throw ValidationException::withMessages([
                'status' => 'Tunggu konfirmasi admin, pelanggan harus melunasi dulu sebelum unit dapat ditandai masuk ke garasi.',
            ]);
        }

        DB::transaction(function () use ($delivery, $booking, $status, $data) {
            $updateData = ['status' => $status];
            if ($data['notes'] ?? null) {
                $updateData['notes'] = $data['notes'];
            }

            if ($status === 'delivering') {
                $booking->update(['status' => 'delivering']);
            } elseif ($status === 'delivered') {
                $updateData['delivered_at'] = now();
                $booking->update(['status' => 'on_rent']);
            } elseif ($status === 'picked_up') {
                $now = now();
                $updateData['returned_at'] = $now;
                $additionalLateFee = 0;

                if ($now->gt($booking->return_at)) {
                    $hoursLate = (int) ceil($booking->return_at->diffInMinutes($now) / 60);
                    $feePerHour = (float) $booking->vehicle->late_fee_per_hour;
                    $additionalLateFee = $hoursLate * $feePerHour;
                }

                $booking->update([
                    'status' => 'returned',
                    'actual_returned_at' => $now,
                    'late_fee_total' => (float) $booking->late_fee_total + $additionalLateFee,
                ]);

                if ($additionalLateFee > 0) {
                    $booking->user?->notify(new \App\Notifications\FineNotification(
                        $booking,
                        'keterlambatan',
                        $additionalLateFee
                    ));
                }
            }

            $delivery->update($updateData);
        });

        return back()->with('success', 'Status pengiriman berhasil diperbarui.');
    }

    public function uploadPhoto(Request $request, Delivery $delivery): RedirectResponse
    {
        if ($delivery->employee_id !== $request->user()->id) {
            abort(403, 'Akses ditolak.');
        }

        $data = $request->validate([
            'stage' => ['required', 'in:kirim,ambil,anomali'],
            'photo' => ['required', 'image', 'max:5120'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $path = $request->file('photo')->store('delivery-photos', 'public');

        DeliveryPhoto::create([
            'delivery_id' => $delivery->id,
            'stage' => $data['stage'],
            'path' => $path,
            'note' => $data['note'] ?? null,
        ]);

        return back()->with('success', 'Foto kondisi unit berhasil diunggah.');
    }

    public function recordDamage(Request $request, Delivery $delivery): RedirectResponse
    {
        if ($delivery->employee_id !== $request->user()->id) {
            abort(403, 'Akses ditolak.');
        }

        $booking = $delivery->booking;
        if (! in_array($booking->status, ['on_rent', 'returned'], true)) {
            throw ValidationException::withMessages([
                'booking' => 'Catatan kerusakan hanya dapat ditambahkan saat unit sedang disewa (on_rent) atau setelah unit dikembalikan (returned).',
            ]);
        }

        $data = $request->validate([
            'description' => ['required', 'string', 'max:1000'],
            'fine_amount' => ['required', 'numeric', 'min:0'],
        ]);

        Damage::create([
            'booking_id' => $delivery->booking_id,
            'assessed_by' => $request->user()->id,
            'description' => $data['description'],
            'fine_amount' => $data['fine_amount'],
        ]);

        $booking->user?->notify(new \App\Notifications\FineNotification(
            $booking,
            'kerusakan',
            (float) $data['fine_amount'],
            $data['description']
        ));

        return back()->with('success', 'Kerusakan berhasil dicatat.');
    }
}
