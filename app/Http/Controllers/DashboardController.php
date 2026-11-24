<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Delivery;
use App\Models\Payment;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        if ($user->role === 'admin') {
            return $this->adminDashboard();
        }

        if ($user->role === 'pegawai') {
            return $this->pegawaiDashboard($user);
        }

        return $this->pelangganDashboard($user);
    }

    private function adminDashboard(): Response
    {
        $totalVehicles = Vehicle::count();
        $rentedVehicles = Booking::whereIn('status', ['on_rent', 'delivering'])->count();
        $maintenanceVehicles = Vehicle::where('status', 'maintenance')->count();
        $availableVehicles = max(0, Vehicle::where('status', 'available')->count() - $rentedVehicles);

        $totalBookings = Booking::count();
        $pendingPayments = Payment::where('status', 'pending')->count();
        $activeRentals = Booking::where('status', 'on_rent')->count();

        $totalCustomers = User::where('role', 'pelanggan')->count();
        $blacklistedCustomers = User::where('role', 'pelanggan')->where('is_blacklisted', true)->count();
        $totalEmployees = User::where('role', 'pegawai')->count();

        $recentBookings = Booking::with(['user', 'vehicle'])
            ->latest()
            ->take(6)
            ->get();

        return Inertia::render('Dashboard', [
            'stats' => [
                'total_vehicles' => $totalVehicles,
                'available_vehicles' => $availableVehicles,
                'rented_vehicles' => $rentedVehicles,
                'maintenance_vehicles' => $maintenanceVehicles,
                'total_bookings' => $totalBookings,
                'pending_payments' => $pendingPayments,
                'active_rentals' => $activeRentals,
                'total_customers' => $totalCustomers,
                'blacklisted_customers' => $blacklistedCustomers,
                'total_employees' => $totalEmployees,
            ],
            'recent_bookings' => $recentBookings,
            'overdue_rentals' => Booking::with(['vehicle', 'user', 'delivery.employee'])->where('status', 'on_rent')->where('return_at', '<=', now())->get(),
        ]);
    }

    private function pegawaiDashboard(User $user): Response
    {
        $deliveries = Delivery::with(['booking.vehicle', 'booking.user'])
            ->where('employee_id', $user->id)
            ->whereIn('status', ['assigned', 'approved', 'delivering', 'picked_up'])
            ->latest()
            ->get();

        return Inertia::render('Dashboard', [
            'assigned_deliveries' => $deliveries,
            'overdue_rentals' => Booking::with(['vehicle', 'user', 'delivery'])->where('status', 'on_rent')->where('return_at', '<=', now())->whereHas('delivery', fn ($q) => $q->where('employee_id', $user->id))->get(),
            'stats' => [
                'active_tasks' => $deliveries->count(),
                'completed_tasks' => Delivery::where('employee_id', $user->id)->where('status', 'in_garage')->count(),
            ],
        ]);
    }

    private function pelangganDashboard(User $user): Response
    {
        $activeBookings = Booking::with('vehicle')
            ->where('user_id', $user->id)
            ->whereIn('status', ['pending_dp', 'booked', 'assigned', 'delivering', 'on_rent', 'returned'])
            ->latest()
            ->get();

        $totalCompleted = Booking::where('user_id', $user->id)->where('status', 'completed')->count();

        return Inertia::render('Dashboard', [
            'active_bookings' => $activeBookings,
            'stats' => [
                'active_count' => $activeBookings->count(),
                'completed_count' => $totalCompleted,
            ],
        ]);
    }
}
