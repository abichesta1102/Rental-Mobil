<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PaymentMethod;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PaymentMethodController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/payment-methods/Index', [
            'methods' => PaymentMethod::latest()->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'type' => ['required', 'in:qris,bank_transfer'],
            'label' => ['required', 'string', 'max:100'],
            'account_number' => ['nullable', 'string', 'max:50'],
            'account_name' => ['nullable', 'string', 'max:100'],
            'qris_image' => ['nullable', 'image', 'max:2048'],
            'is_active' => ['boolean'],
        ]);

        if ($request->hasFile('qris_image')) {
            $data['qris_image'] = $request->file('qris_image')->store('payment-methods', 'public');
        }

        PaymentMethod::create($data);

        return back()->with('success', 'Metode pembayaran berhasil ditambahkan.');
    }

    public function update(Request $request, PaymentMethod $paymentMethod): RedirectResponse
    {
        $data = $request->validate([
            'type' => ['required', 'in:qris,bank_transfer'],
            'label' => ['required', 'string', 'max:100'],
            'account_number' => ['nullable', 'string', 'max:50'],
            'account_name' => ['nullable', 'string', 'max:100'],
            'qris_image' => ['nullable', 'image', 'max:2048'],
            'is_active' => ['boolean'],
        ]);

        if ($request->hasFile('qris_image')) {
            if ($paymentMethod->qris_image) {
                Storage::disk('public')->delete($paymentMethod->qris_image);
            }
            $data['qris_image'] = $request->file('qris_image')->store('payment-methods', 'public');
        }

        $paymentMethod->update($data);

        return back()->with('success', 'Metode pembayaran berhasil diperbarui.');
    }

    public function destroy(PaymentMethod $paymentMethod): RedirectResponse
    {
        if ($paymentMethod->payments()->exists()) {
            throw ValidationException::withMessages([
                'payment_method' => 'Metode pembayaran tidak dapat dihapus karena memiliki riwayat transaksi pembayaran. Silakan nonaktifkan metode pembayaran ini.',
            ]);
        }

        if ($paymentMethod->qris_image) {
            Storage::disk('public')->delete($paymentMethod->qris_image);
        }

        $paymentMethod->delete();

        return back()->with('success', 'Metode pembayaran berhasil dihapus.');
    }
}
