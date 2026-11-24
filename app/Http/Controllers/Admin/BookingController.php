<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Damage;
use App\Models\Delivery;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class BookingController extends Controller
{
    public function index(Request $request): Response
    {
        $query = Booking::with(['user', 'vehicle', 'delivery.employee', 'payments'])
            ->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        if ($request->filled('search')) {
            $search = strtolower($request->query('search'));
            $query->where(function ($q) use ($search) {
                $q->whereRaw('LOWER(booking_code) LIKE ?', ["%{$search}%"])
                    ->orWhereRaw('LOWER(destination) LIKE ?', ["%{$search}%"])
                    ->orWhereHas('user', fn ($u) => $u->whereRaw('LOWER(name) LIKE ?', ["%{$search}%"])->orWhereRaw('LOWER(email) LIKE ?', ["%{$search}%"]))
                    ->orWhereHas('vehicle', fn ($v) => $v->whereRaw('LOWER(brand) LIKE ?', ["%{$search}%"])->orWhereRaw('LOWER(model) LIKE ?', ["%{$search}%"])->orWhereRaw('LOWER(plate) LIKE ?', ["%{$search}%"]));
            });
        }

        return Inertia::render('admin/bookings/Index', [
            'bookings' => $query->paginate(10)->withQueryString(),
            'filters' => [
                'status' => $request->query('status', ''),
                'search' => $request->query('search', ''),
            ],
        ]);
    }

    public function show(Booking $booking): Response
    {
        $booking->load([
            'user',
            'vehicle',
            'payments.paymentMethod',
            'payments.verifiedBy',
            'delivery.employee',
            'delivery.photos',
            'damages.assessedBy',
            'locationLogs' => fn ($q) => $q->latest('recorded_at')->take(1),
        ]);

        $employees = User::where('role', 'pegawai')
            ->where('is_blacklisted', false)
            ->withCount(['deliveries as active_tasks' => fn ($q) => $q->whereIn('status', ['assigned', 'approved', 'delivering', 'picked_up'])])
            ->orderBy('name')
            ->get(['id', 'name', 'phone']);

        return Inertia::render('admin/bookings/Show', [
            'booking' => $booking,
            'employees' => $employees,
        ]);
    }

    public function assignDelivery(Request $request, Booking $booking): RedirectResponse
    {
        if (! in_array($booking->status, ['booked', 'assigned'], true)) {
            throw ValidationException::withMessages([
                'booking' => 'Penugasan pengiriman hanya dapat dilakukan jika booking berstatus booked atau assigned.',
            ]);
        }

        $data = $request->validate([
            'employee_id' => [
                'required',
                Rule::exists('users', 'id')
                    ->where('role', 'pegawai')
                    ->where('is_blacklisted', false),
            ],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        DB::transaction(function () use ($request, $booking, $data) {
            $delivery = Delivery::updateOrCreate(
                ['booking_id' => $booking->id],
                [
                    'employee_id' => $data['employee_id'],
                    'assigned_by' => $request->user()->id,
                    'status' => 'assigned',
                    'assigned_at' => now(),
                    'notes' => $data['notes'] ?? null,
                ]
            );

            if ($booking->status === 'booked') {
                $booking->update(['status' => 'assigned']);
            }

            $employee = User::find($data['employee_id']);
            $employee?->notify(new \App\Notifications\DeliveryAssignedNotification($delivery));
        });

        return back()->with('success', 'Petugas pengiriman berhasil ditugaskan.');
    }

    public function verifyPayment(Request $request, Payment $payment): RedirectResponse
    {
        if ($payment->status !== 'pending') {
            throw ValidationException::withMessages([
                'payment' => 'Hanya pembayaran dengan status pending yang dapat diverifikasi.',
            ]);
        }

        $data = $request->validate([
            'action' => ['required', 'in:verify,reject'],
        ]);

        DB::transaction(function () use ($request, $payment, $data) {
            $isVerified = $data['action'] === 'verify';
            $booking = $payment->booking;

            if ($isVerified && $payment->type === 'dp' && $booking->status === 'pending_dp') {
                // Cek ulang apakah ada jadwal bentrok sebelum DP mengubah status booking ke booked
                $conflict = $booking->vehicle->bookings()
                    ->blocking()
                    ->where('id', '!=', $booking->id)
                    ->overlapping($booking->pickup_at, $booking->return_at)
                    ->exists();

                if ($conflict) {
                    throw ValidationException::withMessages([
                        'booking' => 'Jadwal kendaraan ini sudah terisi oleh booking lain yang aktif. Pembayaran DP tidak dapat diverifikasi.',
                    ]);
                }
            }

            $payment->update([
                'status' => $isVerified ? 'verified' : 'rejected',
                'verified_by' => $request->user()->id,
                'verified_at' => now(),
            ]);

            if ($isVerified) {
                if ($payment->type === 'dp' && $booking->status === 'pending_dp') {
                    $booking->update(['status' => 'booked']);
                } elseif ($booking->status === 'returned') {
                    // Pelunasan menjadi completed hanya jika total pembayaran terverifikasi >= total sewa + denda + kerusakan
                    $damagesTotal = (float) $booking->damages()->sum('fine_amount');
                    $grandTotal = (float) $booking->total_price + (float) $booking->late_fee_total + $damagesTotal;
                    $totalVerified = (float) $booking->payments()->where('status', 'verified')->sum('amount');

                    if ($totalVerified >= $grandTotal) {
                        $booking->update(['status' => 'completed']);
                    }
                }
            }
        });

        return back()->with('success', 'Status pembayaran berhasil diperbarui.');
    }

    public function recordDamage(Request $request, Booking $booking): RedirectResponse
    {
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
            'booking_id' => $booking->id,
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

        return back()->with('success', 'Catatan kerusakan dan denda berhasil ditambahkan.');
    }

    public function cancel(Request $request, Booking $booking): RedirectResponse
    {
        if (in_array($booking->status, ['completed', 'cancelled', 'on_rent'], true)) {
            throw ValidationException::withMessages([
                'booking' => 'Booking dengan status saat ini tidak dapat dibatalkan.',
            ]);
        }

        $booking->update([
            'status' => 'cancelled',
        ]);

        return back()->with('success', "Booking {$booking->booking_code} berhasil dibatalkan.");
    }
}
