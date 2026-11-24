<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Vehicle;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class VehicleController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/vehicles/Index', [
            'vehicles' => Vehicle::with(['bookings' => fn ($q) => $q->whereIn('status', ['on_rent', 'delivering', 'assigned'])->with('user')->latest()])->latest()->paginate(10),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/vehicles/Form', ['vehicle' => null]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        if ($request->hasFile('photo')) {
            $data['photo'] = $request->file('photo')->store('vehicles', 'public');
        }

        Vehicle::create($data);

        return redirect()->route('admin.vehicles.index');
    }

    public function edit(Vehicle $vehicle): Response
    {
        return Inertia::render('admin/vehicles/Form', ['vehicle' => $vehicle]);
    }

    public function update(Request $request, Vehicle $vehicle): RedirectResponse
    {
        $data = $this->validated($request, $vehicle);

        if ($request->hasFile('photo')) {
            if ($vehicle->photo) {
                Storage::disk('public')->delete($vehicle->photo);
            }
            $data['photo'] = $request->file('photo')->store('vehicles', 'public');
        } else {
            unset($data['photo']);
        }

        $vehicle->update($data);

        return redirect()->route('admin.vehicles.index');
    }

    public function destroy(Vehicle $vehicle): RedirectResponse
    {
        $vehicle->delete(); // soft delete, riwayat booking tetap aman

        return redirect()->route('admin.vehicles.index');
    }

    private function validated(Request $request, ?Vehicle $vehicle = null): array
    {
        return $request->validate([
            'plate' => ['required', 'string', 'max:15', Rule::unique('vehicles', 'plate')->ignore($vehicle?->id)],
            'brand' => ['required', 'string', 'max:100'],
            'model' => ['required', 'string', 'max:100'],
            'year' => ['nullable', 'integer', 'between:1990,2100'],
            'color' => ['nullable', 'string', 'max:50'],
            'transmission' => ['required', Rule::in(['manual', 'matic'])],
            'seats' => ['required', 'integer', 'between:2,20'],
            'price_per_day' => ['required', 'numeric', 'min:0'],
            'late_fee_per_hour' => ['required', 'numeric', 'min:0'],
            'purchase_price' => ['nullable', 'numeric', 'min:0'],
            'status' => ['required', Rule::in(['available', 'maintenance', 'inactive'])],
            'photo' => ['nullable', 'image', 'max:2048'],
        ]);
    }
}