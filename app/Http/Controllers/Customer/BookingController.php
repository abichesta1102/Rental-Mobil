<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\LocationLog;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Vehicle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class BookingController extends Controller
{
    /** Daftar booking milik pelanggan yang sedang login. */
    public function index(Request $request): Response
    {
        return Inertia::render('bookings/Index', [
            'bookings' => Booking::where('user_id', $request->user()->id)
                ->with(['vehicle', 'payments'])
                ->latest()
                ->paginate(10),
        ]);
    }

    public function show(Request $request, Booking $booking): Response
    {
        if ($booking->user_id !== $request->user()->id) {
            abort(403, 'Akses ditolak.');
        }

        $booking->load([
            'vehicle',
            'payments.paymentMethod',
            'delivery.photos',
            'damages',
        ]);

        $activePaymentMethods = PaymentMethod::where('is_active', true)->get();

        return Inertia::render('bookings/Show', [
            'booking' => $booking,
            'paymentMethods' => $activePaymentMethods,
        ]);
    }

    public function invoice(Request $request, Booking $booking): Response
    {
        if ($booking->user_id !== $request->user()->id) {
            abort(403, 'Akses ditolak.');
        }

        $booking->load([
            'user',
            'vehicle',
            'payments' => fn ($q) => $q->where('status', 'verified')->with('paymentMethod'),
            'damages',
        ]);

        return Inertia::render('bookings/Invoice', [
            'booking' => $booking,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'vehicle_id' => ['required', 'integer', Rule::exists('vehicles', 'id')->whereNull('deleted_at')],
            'destination' => ['required', 'string', 'max:255'],
            'delivery_address' => ['required', 'string', 'max:1000'],
            'pickup_at' => ['required', 'date', 'after_or_equal:now'],
            'return_at' => ['required', 'date', 'after:pickup_at'],
        ]);

        $pickup = Carbon::parse($data['pickup_at']);
        $return = Carbon::parse($data['return_at']);

        $booking = DB::transaction(function () use ($request, $data, $pickup, $return) {
            // Kunci baris kendaraan supaya dua pelanggan tidak bisa memesan slot yang sama bersamaan.
            $vehicle = Vehicle::whereKey($data['vehicle_id'])->lockForUpdate()->firstOrFail();

            if ($vehicle->status !== 'available') {
                throw ValidationException::withMessages([
                    'vehicle_id' => 'Kendaraan ini sedang tidak tersedia untuk disewa.',
                ]);
            }

            $taken = $vehicle->bookings()->blocking()->overlapping($pickup, $return)->exists();

            if ($taken) {
                throw ValidationException::withMessages([
                    'pickup_at' => 'Kendaraan sudah dipesan pada rentang waktu tersebut. Pilih tanggal atau jam lain.',
                ]);
            }

            $pricePerDay = (float) $vehicle->price_per_day;
            $lateFeePerHour = (float) $vehicle->late_fee_per_hour;
            $pricing = Booking::calculatePricing($pickup, $return, $pricePerDay, $lateFeePerHour);

            return Booking::create([
                'user_id' => $request->user()->id,
                'vehicle_id' => $vehicle->id,
                'booking_code' => Booking::generateCode(),
                'destination' => $data['destination'],
                'delivery_address' => $data['delivery_address'],
                'pickup_at' => $pickup,
                'return_at' => $return,
                'duration_days' => $pricing['days'],
                'price_per_day' => $pricePerDay,
                'total_price' => $pricing['total'],
                'dp_amount' => $pricing['dp'],
                'late_fee_total' => $pricing['overtime_fee'],
                'status' => 'pending_dp',
            ]);
        });

        return redirect()->route('bookings.show', $booking)->with('success', 'Booking berhasil dibuat. Silakan lakukan pembayaran DP.');
    }

    public function pay(Request $request, Booking $booking): RedirectResponse
    {
        if ($booking->user_id !== $request->user()->id) {
            abort(403, 'Akses ditolak.');
        }

        if ($booking->payments()->where('status', 'pending')->exists()) {
            throw ValidationException::withMessages([
                'payment' => 'Masih ada bukti pembayaran yang sedang menunggu verifikasi oleh Admin.',
            ]);
        }

        $data = $request->validate([
            'payment_method_id' => [
                'required',
                Rule::exists('payment_methods', 'id')->where('is_active', true),
            ],
            'proof' => ['required', 'image', 'max:5120'],
        ]);

        // Tentukan type dan amount di server dari status booking
        if ($booking->status === 'pending_dp') {
            $type = 'dp';
            $amount = (float) $booking->dp_amount;
        } elseif ($booking->status === 'returned') {
            $type = 'pelunasan';
            $damagesTotal = (float) $booking->damages()->sum('fine_amount');
            $grandTotal = (float) $booking->total_price + (float) $booking->late_fee_total + $damagesTotal;
            $verifiedPaid = (float) $booking->payments()->where('status', 'verified')->sum('amount');
            $remaining = max(0, $grandTotal - $verifiedPaid);

            if ($remaining <= 0) {
                throw ValidationException::withMessages([
                    'payment' => 'Tagihan sewa ini sudah lunas.',
                ]);
            }
            $amount = $remaining;
        } else {
            throw ValidationException::withMessages([
                'payment' => 'Status booking saat ini tidak dalam tahap pembayaran (hanya untuk status pending_dp dan returned).',
            ]);
        }

        $proofPath = $request->file('proof')->store('payments', 'public');

        $payment = Payment::create([
            'booking_id' => $booking->id,
            'payment_method_id' => $data['payment_method_id'],
            'type' => $type,
            'amount' => $amount,
            'proof' => $proofPath,
            'status' => 'pending',
            'paid_at' => now(),
        ]);

        $admins = \App\Models\User::where('role', 'admin')->get();
        foreach ($admins as $admin) {
            $admin->notify(new \App\Notifications\NewPaymentNotification($payment));
        }

        return back()->with('success', 'Bukti pembayaran berhasil diunggah dan sedang menunggu verifikasi.');
    }

    public function logLocation(Request $request, Booking $booking): JsonResponse
    {
        if ($booking->user_id !== $request->user()->id) {
            return response()->json(['error' => 'Akses ditolak.'], 403);
        }

        if ($booking->status !== 'on_rent') {
            return response()->json(['status' => 'ignored', 'message' => 'Status sewa tidak aktif.']);
        }

        $data = $request->validate([
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
        ]);

        LocationLog::create([
            'booking_id' => $booking->id,
            'lat' => $data['lat'],
            'lng' => $data['lng'],
            'recorded_at' => now(),
        ]);

        return response()->json(['status' => 'ok']);
    }
}
