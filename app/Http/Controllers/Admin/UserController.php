<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(Request $request): Response
    {
        $employees = User::where('role', 'pegawai')
            ->latest()
            ->get();

        $customersQuery = User::where('role', 'pelanggan')
            ->withCount('bookings')
            ->latest();

        if ($request->filled('search')) {
            $search = strtolower($request->query('search'));
            $customersQuery->where(function ($q) use ($search) {
                $q->whereRaw('LOWER(name) LIKE ?', ["%{$search}%"])
                    ->orWhereRaw('LOWER(email) LIKE ?', ["%{$search}%"])
                    ->orWhereRaw('LOWER(phone) LIKE ?', ["%{$search}%"]);
            });
        }

        return Inertia::render('admin/users/Index', [
            'employees' => $employees,
            'customers' => $customersQuery->paginate(15)->withQueryString(),
            'filters' => [
                'search' => $request->query('search', ''),
            ],
        ]);
    }

    public function storeEmployee(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'phone' => ['nullable', 'string', 'max:20'],
        ]);

        User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'role' => 'pegawai',
            'phone' => $data['phone'] ?? null,
        ]);

        return back()->with('success', 'Akun pegawai berhasil ditambahkan.');
    }

    public function toggleBlacklist(Request $request, User $user): RedirectResponse
    {
        if ($user->role !== 'pelanggan') {
            throw ValidationException::withMessages([
                'user' => 'Hanya akun pelanggan yang dapat dimasukkan atau dikeluarkan dari daftar hitam.',
            ]);
        }

        $data = $request->validate([
            'is_blacklisted' => ['required', 'boolean'],
            'blacklist_reason' => ['nullable', 'string', 'max:500'],
        ]);

        $user->update([
            'is_blacklisted' => $data['is_blacklisted'],
            'blacklist_reason' => $data['is_blacklisted'] ? ($data['blacklist_reason'] ?? 'Pelanggaran ketentuan rental.') : null,
        ]);

        return back()->with('success', $data['is_blacklisted'] ? 'Pelanggan berhasil dimasukkan ke daftar hitam.' : 'Pelanggan berhasil dipulihkan dari daftar hitam.');
    }
}
