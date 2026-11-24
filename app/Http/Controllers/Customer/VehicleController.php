<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Vehicle;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class VehicleController extends Controller
{
    /** Katalog kendaraan, dengan filter kendaraan yang ready pada tanggal dan jam tertentu. */
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'pickup_at'    => ['nullable', 'date'],
            'return_at'    => ['nullable', 'date', 'after:pickup_at'],
            'transmission' => ['nullable', 'in:manual,matic'],
            'seats'        => ['nullable', 'integer', 'min:1'],
        ]);

        $filtered = ! empty($filters['pickup_at']) && ! empty($filters['return_at']);

        $query = Vehicle::query()->orderBy('brand')->orderBy('model');

        if ($filtered) {
            $query->availableBetween(
                Carbon::parse($filters['pickup_at']),
                Carbon::parse($filters['return_at']),
            );
        } else {
            $query->where('status', 'available');
        }

        if (! empty($filters['transmission'])) {
            $query->where('transmission', $filters['transmission']);
        }
        if (! empty($filters['seats'])) {
            $query->where('seats', $filters['seats']);
        }

        return Inertia::render('vehicles/Index', [
            'vehicles' => $query->get(),
            'filtered' => $filtered,
            'filters'  => [
                'pickup_at'    => $filters['pickup_at'] ?? null,
                'return_at'    => $filters['return_at'] ?? null,
                'transmission' => $filters['transmission'] ?? null,
                'seats'        => $filters['seats'] ?? null,
            ],
        ]);
    }

    /** Detail kendaraan: kalender ketersediaan 30 hari ke depan dan form booking. */
    public function show(Request $request, Vehicle $vehicle): Response
    {
        $busy = $vehicle->bookings()
            ->blocking()
            ->where('return_at', '>', now())
            ->orderBy('pickup_at')
            ->get(['id', 'pickup_at', 'return_at']);

        $days = collect(range(0, 29))->map(function (int $i) use ($busy) {
            $start = now()->startOfDay()->addDays($i);
            $end = $start->copy()->addDay();

            return [
                'date' => $start->toDateString(),
                'busy' => $busy->contains(
                    fn ($b) => $b->pickup_at < $end && $b->return_at > $start,
                ),
            ];
        });

        return Inertia::render('vehicles/Show', [
            'vehicle' => $vehicle,
            'bookable' => $vehicle->status === 'available',
            'days' => $days,
            'busy' => $busy->map(fn ($b) => [
                'pickup_at' => $b->pickup_at->toIso8601String(),
                'return_at' => $b->return_at->toIso8601String(),
            ])->values(),
            'prefill' => [
                'pickup_at' => $request->query('pickup_at'),
                'return_at' => $request->query('return_at'),
            ],
        ]);
    }
}
