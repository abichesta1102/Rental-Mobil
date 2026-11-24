<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Expense;
use App\Models\Payment;
use App\Models\Vehicle;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class FinanceController extends Controller
{
    public function index(Request $request): Response
    {
        $selectedMonth = $request->input('month', now()->format('Y-m'));
        $monthStart = Carbon::parse($selectedMonth)->startOfMonth();
        $monthEnd = Carbon::parse($selectedMonth)->endOfMonth();

        // 1. Pengeluaran
        $expenses = Expense::with(['vehicle', 'creator'])
            ->whereBetween('spent_at', [$monthStart->toDateString(), $monthEnd->toDateString()])
            ->latest('spent_at')
            ->get();

        $monthlyExpensesTotal = (float) $expenses->sum('amount');

        // 2. Pendapatan bulanan (verified payments)
        $monthlyRevenueTotal = (float) Payment::where('status', 'verified')
            ->whereBetween('verified_at', [$monthStart, $monthEnd])
            ->sum('amount');

        $monthlyNetProfit = $monthlyRevenueTotal - $monthlyExpensesTotal;

        // 3. Pendapatan per kendaraan untuk bulan yang dipilih
        $vehicles = Vehicle::withTrashed()->get();
        
        // Dapatkan semua payment verified bulan ini beserta vehicle_id via booking
        $monthlyVehicleRevenue = Payment::where('payments.status', 'verified')
            ->whereBetween('payments.verified_at', [$monthStart, $monthEnd])
            ->join('bookings', 'payments.booking_id', '=', 'bookings.id')
            ->selectRaw('bookings.vehicle_id, SUM(payments.amount) as total_revenue')
            ->groupBy('bookings.vehicle_id')
            ->pluck('total_revenue', 'vehicle_id');

        $monthlyVehicleExpenses = Expense::whereBetween('spent_at', [$monthStart->toDateString(), $monthEnd->toDateString()])
            ->whereNotNull('vehicle_id')
            ->selectRaw('vehicle_id, SUM(amount) as total_expense')
            ->groupBy('vehicle_id')
            ->pluck('total_expense', 'vehicle_id');

        $monthlyVehicleBreakdown = $vehicles->map(function ($vehicle) use ($monthlyVehicleRevenue, $monthlyVehicleExpenses) {
            $rev = (float) ($monthlyVehicleRevenue->get($vehicle->id) ?? 0);
            $exp = (float) ($monthlyVehicleExpenses->get($vehicle->id) ?? 0);
            return [
                'vehicle_id' => $vehicle->id,
                'plate' => $vehicle->plate,
                'brand_model' => "{$vehicle->brand} {$vehicle->model}",
                'revenue' => $rev,
                'expense' => $exp,
                'net_profit' => $rev - $exp,
            ];
        });

        // 4. Balik Modal per kendaraan (akumulasi sepanjang waktu)
        // Total pendapatan verified per kendaraan (all time)
        $allTimeRevenue = Payment::where('payments.status', 'verified')
            ->join('bookings', 'payments.booking_id', '=', 'bookings.id')
            ->selectRaw('bookings.vehicle_id, SUM(payments.amount) as total_revenue')
            ->groupBy('bookings.vehicle_id')
            ->pluck('total_revenue', 'vehicle_id');

        // Total pengeluaran per kendaraan (all time)
        $allTimeExpenses = Expense::whereNotNull('vehicle_id')
            ->selectRaw('vehicle_id, SUM(amount) as total_expense')
            ->groupBy('vehicle_id')
            ->pluck('total_expense', 'vehicle_id');

        // Jumlah booking completed per kendaraan
        $completedBookingsCount = Booking::where('status', 'completed')
            ->selectRaw('vehicle_id, COUNT(*) as count')
            ->groupBy('vehicle_id')
            ->pluck('count', 'vehicle_id');

        $roiData = $vehicles->map(function ($vehicle) use ($allTimeRevenue, $allTimeExpenses, $completedBookingsCount) {
            $purchasePrice = (float) ($vehicle->purchase_price ?? 0);
            $rev = (float) ($allTimeRevenue->get($vehicle->id) ?? 0);
            $exp = (float) ($allTimeExpenses->get($vehicle->id) ?? 0);
            $netProfit = $rev - $exp;
            $completedCount = (int) ($completedBookingsCount->get($vehicle->id) ?? 0);

            $roiPercent = $purchasePrice > 0 ? round(($netProfit / $purchasePrice) * 100, 2) : 0;
            $avgProfitPerRental = $completedCount > 0 ? ($netProfit / $completedCount) : 0;

            $isBreakEven = $purchasePrice > 0 && $netProfit >= $purchasePrice;

            $rentalsNeeded = null;
            $rentalsRemaining = null;

            if ($purchasePrice > 0 && $avgProfitPerRental > 0 && !$isBreakEven) {
                $rentalsNeeded = (int) ceil($purchasePrice / $avgProfitPerRental);
                $rentalsRemaining = max(0, $rentalsNeeded - $completedCount);
            } elseif ($isBreakEven) {
                $rentalsRemaining = 0;
            }

            return [
                'vehicle_id' => $vehicle->id,
                'plate' => $vehicle->plate,
                'brand_model' => "{$vehicle->brand} {$vehicle->model}",
                'purchase_price' => $purchasePrice,
                'all_time_revenue' => $rev,
                'all_time_expenses' => $exp,
                'accumulated_net_profit' => $netProfit,
                'roi_percent' => $roiPercent,
                'completed_rentals_count' => $completedCount,
                'avg_profit_per_rental' => round($avgProfitPerRental, 2),
                'rentals_needed' => $rentalsNeeded,
                'rentals_remaining' => $rentalsRemaining,
                'is_break_even' => $isBreakEven,
            ];
        });

        return Inertia::render('admin/finance/Index', [
            'selected_month' => $selectedMonth,
            'summary' => [
                'revenue' => $monthlyRevenueTotal,
                'expense' => $monthlyExpensesTotal,
                'net_profit' => $monthlyNetProfit,
            ],
            'expenses' => $expenses,
            'monthly_vehicle_breakdown' => $monthlyVehicleBreakdown,
            'roi_data' => $roiData,
            'vehicle_options' => Vehicle::select('id', 'plate', 'brand', 'model')->get(),
        ]);
    }

    public function storeExpense(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'vehicle_id' => ['nullable', 'exists:vehicles,id'],
            'category' => ['required', Rule::in(['oli', 'perbaikan', 'servis', 'pajak', 'lainnya'])],
            'description' => ['required', 'string', 'max:500'],
            'amount' => ['required', 'numeric', 'min:0'],
            'spent_at' => ['required', 'date'],
        ]);

        $validated['created_by'] = $request->user()->id;

        Expense::create($validated);

        return back()->with('success', 'Pengeluaran berhasil dicatat.');
    }

    public function destroyExpense(Expense $expense): RedirectResponse
    {
        $expense->delete();

        return back()->with('success', 'Pengeluaran berhasil dihapus.');
    }
}
