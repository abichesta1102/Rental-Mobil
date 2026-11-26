<?php

use App\Http\Controllers\Admin\BookingController as AdminBookingController;
use App\Http\Controllers\Admin\FinanceController as AdminFinanceController;
use App\Http\Controllers\Admin\PaymentMethodController as AdminPaymentMethodController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\Admin\VehicleController;
use App\Http\Controllers\Customer\BookingController as CustomerBookingController;
use App\Http\Controllers\Customer\VehicleController as CustomerVehicleController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\Pegawai\DeliveryController as PegawaiDeliveryController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (auth()->check()) {
        return redirect()->route('dashboard');
    }
    return redirect()->route('login');
})->name('home');

Route::middleware(['auth'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Katalog kendaraan dapat diakses oleh semua pengguna yang sudah login
    Route::get('vehicles', [CustomerVehicleController::class, 'index'])->name('vehicles.index');
    Route::get('vehicles/{vehicle}', [CustomerVehicleController::class, 'show'])->name('vehicles.show');

    // Notifikasi
    Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('notifications/{id}/read', [NotificationController::class, 'markAsRead'])->name('notifications.read');
    Route::post('notifications/read-all', [NotificationController::class, 'markAllAsRead'])->name('notifications.read-all');
});

// Sisi admin
Route::middleware(['auth', 'role:admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::resource('vehicles', VehicleController::class)->except('show');

        // Manajemen Booking & Verifikasi
        Route::get('bookings', [AdminBookingController::class, 'index'])->name('bookings.index');
        Route::get('bookings/{booking}', [AdminBookingController::class, 'show'])->name('bookings.show');
        Route::post('bookings/{booking}/assign-delivery', [AdminBookingController::class, 'assignDelivery'])->name('bookings.assign-delivery');
        Route::post('payments/{payment}/verify', [AdminBookingController::class, 'verifyPayment'])->name('payments.verify');
        Route::post('bookings/{booking}/damages', [AdminBookingController::class, 'recordDamage'])->name('bookings.damages');
        Route::post('bookings/{booking}/cancel', [AdminBookingController::class, 'cancel'])->name('bookings.cancel');

        // Manajemen Metode Pembayaran
        Route::get('payment-methods', [AdminPaymentMethodController::class, 'index'])->name('payment-methods.index');
        Route::post('payment-methods', [AdminPaymentMethodController::class, 'store'])->name('payment-methods.store');
        Route::put('payment-methods/{paymentMethod}', [AdminPaymentMethodController::class, 'update'])->name('payment-methods.update');
        Route::delete('payment-methods/{paymentMethod}', [AdminPaymentMethodController::class, 'destroy'])->name('payment-methods.destroy');

        // Manajemen Pengguna & Blacklist
        Route::get('users', [AdminUserController::class, 'index'])->name('users.index');
        Route::post('users/employees', [AdminUserController::class, 'storeEmployee'])->name('users.employees.store');
        Route::post('users/{user}/blacklist', [AdminUserController::class, 'toggleBlacklist'])->name('users.blacklist');

        // Laporan Keuangan & Pengeluaran
        Route::get('finance', [AdminFinanceController::class, 'index'])->name('finance.index');
        Route::post('finance/expenses', [AdminFinanceController::class, 'storeExpense'])->name('finance.expenses.store');
        Route::delete('finance/expenses/{expense}', [AdminFinanceController::class, 'destroyExpense'])->name('finance.expenses.destroy');
    });

// Sisi pegawai
Route::middleware(['auth', 'role:pegawai'])
    ->prefix('pegawai')
    ->name('pegawai.')
    ->group(function () {
        Route::get('deliveries', [PegawaiDeliveryController::class, 'index'])->name('deliveries.index');
        Route::get('deliveries/{delivery}', [PegawaiDeliveryController::class, 'show'])->name('deliveries.show');
        Route::post('deliveries/{delivery}/status', [PegawaiDeliveryController::class, 'updateStatus'])->name('deliveries.status');
        Route::post('deliveries/{delivery}/photos', [PegawaiDeliveryController::class, 'uploadPhoto'])->name('deliveries.photos');
        Route::post('deliveries/{delivery}/damages', [PegawaiDeliveryController::class, 'recordDamage'])->name('deliveries.damages');
    });

// Sisi pelanggan
Route::middleware(['auth', 'role:pelanggan'])->group(function () {
    Route::get('bookings', [CustomerBookingController::class, 'index'])->name('bookings.index');
    Route::post('bookings', [CustomerBookingController::class, 'store'])->name('bookings.store');
    Route::get('bookings/{booking}', [CustomerBookingController::class, 'show'])->name('bookings.show');
    Route::get('bookings/{booking}/invoice', [CustomerBookingController::class, 'invoice'])->name('bookings.invoice');
    Route::post('bookings/{booking}/pay', [CustomerBookingController::class, 'pay'])->name('bookings.pay');
    Route::post('bookings/{booking}/location', [CustomerBookingController::class, 'logLocation'])->name('bookings.location');
});

require __DIR__.'/settings.php';
