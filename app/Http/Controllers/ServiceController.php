<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Models\Service;

class ServiceController extends Controller
{
    public function index()
    {
        return response()->json([
            'success' => true,
            'data' => Service::orderBy('id')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $request->merge([
            'prefix' => strtoupper(trim((string) $request->input('prefix'))),
        ]);

        $request->validate([
            'name' => 'required|string|max:100',
            'prefix' => 'required|string|max:5|unique:services,prefix',
            'isActive' => 'required|boolean',
            'showInKiosk' => 'required|boolean',
        ]);

        $service = Service::create([
            'name' => trim($request->input('name')),
            'prefix' => $request->input('prefix'),
            'is_active' => $request->boolean('isActive'),
            'show_in_kiosk' => $request->boolean('showInKiosk'),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Layanan berhasil dibuat',
            'data' => $service,
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $service = Service::find($id);
        if (!$service) {
            return response()->json([
                'success' => false,
                'message' => 'Layanan tidak ditemukan',
            ], 404);
        }

        $request->merge([
            'prefix' => strtoupper(trim((string) $request->input('prefix'))),
        ]);

        $request->validate([
            'name' => 'sometimes|required|string|max:100',
            'prefix' => [
                'sometimes',
                'required',
                'string',
                'max:5',
                Rule::unique('services', 'prefix')->ignore($service->id),
            ],
            'isActive' => 'sometimes|required|boolean',
            'showInKiosk' => 'sometimes|required|boolean',
        ]);

        $changes = [];

        if ($request->has('name')) {
            $changes['name'] = trim($request->input('name'));
        }

        if ($request->filled('prefix')) {
            $changes['prefix'] = $request->input('prefix');
        }

        if ($request->has('isActive')) {
            $changes['is_active'] = $request->boolean('isActive');
        }

        if ($request->has('showInKiosk')) {
            $changes['show_in_kiosk'] = $request->boolean('showInKiosk');
        }

        $service->update($changes);

        return response()->json([
            'success' => true,
            'message' => 'Layanan berhasil diperbarui',
            'data' => $service->fresh(),
        ]);
    }

    public function destroy($id)
    {
        $service = Service::find($id);
        if (!$service) {
            return response()->json([
                'success' => false,
                'message' => 'Layanan tidak ditemukan',
            ], 404);
        }

        $service->delete();

        return response()->json([
            'success' => true,
            'message' => 'Layanan berhasil dihapus',
        ]);
    }
}
