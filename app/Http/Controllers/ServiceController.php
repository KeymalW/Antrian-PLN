<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Models\Service;
use App\Services\WebSocketService;

class ServiceController extends Controller
{
    private function broadcast(string $type, $payload): void
    {
        try {
            app(WebSocketService::class)->broadcast($type, $payload);
        } catch (\Throwable) {
            // silent
        }
    }

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
            'code' => strtolower(trim((string) $request->input('code'))),
            'prefix' => strtoupper(trim((string) $request->input('prefix'))),
        ]);

        $request->validate([
            'name' => 'required|string|max:100',
            'code' => 'required|string|max:50|alpha_dash|unique:services,code',
            'prefix' => 'required|string|max:5|unique:services,prefix',
            'counterNumber' => 'nullable|integer|min:1|max:99',
            'icon' => 'nullable|string|max:50',
            'serviceGroup' => 'nullable|in:group_a,group_b',
            'isActive' => 'required|boolean',
            'showInKiosk' => 'required|boolean',
        ]);

        $createData = [
            'name' => trim($request->input('name')),
            'code' => $request->input('code'),
            'prefix' => $request->input('prefix'),
            'counter_number' => $request->input('counterNumber'),
            'icon' => $request->filled('icon') ? $request->input('icon') : null,
            'is_active' => $request->boolean('isActive'),
            'show_in_kiosk' => $request->boolean('showInKiosk'),
        ];

        // Kolom memakai default DB — hanya diisi bila dikirim eksplisit.
        if ($request->filled('serviceGroup')) {
            $createData['service_group'] = $request->input('serviceGroup');
        }

        $service = Service::create($createData);

        $this->broadcast('services_update', $service->toArray());

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

        // Normalisasi hanya bila field dikirim — jangan merusak aturan 'sometimes'.
        if ($request->has('code')) {
            $request->merge(['code' => strtolower(trim((string) $request->input('code')))]);
        }
        if ($request->has('prefix')) {
            $request->merge(['prefix' => strtoupper(trim((string) $request->input('prefix')))]);
        }

        $request->validate([
            'name' => 'sometimes|required|string|max:100',
            'code' => [
                'sometimes',
                'required',
                'string',
                'max:50',
                'alpha_dash',
                Rule::unique('services', 'code')->ignore($service->id),
            ],
            'prefix' => [
                'sometimes',
                'required',
                'string',
                'max:5',
                Rule::unique('services', 'prefix')->ignore($service->id),
            ],
            'counterNumber' => 'nullable|integer|min:1|max:99',
            'icon' => 'nullable|string|max:50',
            'serviceGroup' => 'nullable|in:group_a,group_b',
            'isActive' => 'sometimes|required|boolean',
            'showInKiosk' => 'sometimes|required|boolean',
        ]);

        $changes = [];

        if ($request->has('name')) {
            $changes['name'] = trim($request->input('name'));
        }

        if ($request->filled('code')) {
            $changes['code'] = $request->input('code');
        }

        if ($request->filled('prefix')) {
            $changes['prefix'] = $request->input('prefix');
        }

        if (array_key_exists('counterNumber', $request->all())) {
            $changes['counter_number'] = $request->input('counterNumber');
        }

        if (array_key_exists('icon', $request->all())) {
            $changes['icon'] = $request->filled('icon') ? $request->input('icon') : null;
        }

        if ($request->has('serviceGroup')) {
            $changes['service_group'] = $request->input('serviceGroup');
        }

        if ($request->has('isActive')) {
            $changes['is_active'] = $request->boolean('isActive');
        }

        if ($request->has('showInKiosk')) {
            $changes['show_in_kiosk'] = $request->boolean('showInKiosk');
        }

        $service->update($changes);

        $this->broadcast('services_update', $service->fresh()->toArray());

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

        $payload = $service->toArray();
        $service->delete();

        $this->broadcast('services_update', ['deleted' => true, 'id' => (string) $id] + $payload);

        return response()->json([
            'success' => true,
            'message' => 'Layanan berhasil dihapus',
        ]);
    }
}
