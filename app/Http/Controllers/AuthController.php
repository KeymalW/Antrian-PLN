<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use App\Models\Tenant;
use App\Models\User;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'username' => 'required',
            'password' => 'required',
        ]);

        if (!Auth::attempt($request->only('username', 'password'))) {
            return response()->json([
                'success' => false,
                'message' => 'Username atau password salah'
            ], 401);
        }

        $user = User::where('username', $request->username)->firstOrFail();

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Login berhasil',
            'data' => [
                'user' => $user,
                'token' => $token,
            ]
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logout berhasil'
        ]);
    }

    public function profile(Request $request)
    {
        return response()->json([
            'success' => true,
            'data' => $request->user()
        ]);
    }

    public function adminExists()
    {
        // For multi-tenant: FE shows register button only when no tenant exists at all (first bootstrap)
        // Once at least one tenant exists, global register is still allowed for new companies, but FE hides button after first company for simplicity of PKL (5 max)
        // Keep simple: exists = Tenant::exists() ? true : User::where('role','admin')->exists() for backward compat
        $exists = Tenant::exists();

        return response()->json([
            'success' => true,
            'data' => ['exists' => $exists],
        ]);
    }

    public function register(Request $request)
    {
        // Multi-tenant: each company registers its own tenant+admin. Allow up to 5 tenants for PKL.
        // No global single-admin block — check company slug uniqueness and tenant limit.
        $request->validate([
            'companyName' => 'required|string|max:100',
            'name' => 'required|string|max:100',
            'username' => 'required|string|min:3|max:50|alpha_dash|unique:users,username',
            'password' => 'required|string|min:6|confirmed',
        ]);

        if (Tenant::count() >= 5) {
            return response()->json([
                'success' => false,
                'message' => 'Batas perusahaan tercapai (maks 5 untuk demo PKL).',
            ], 403);
        }

        $companyName = trim($request->input('companyName'));
        $slug = Str::slug($companyName);
        if (empty($slug)) $slug = 'company-' . Str::random(4);
        $baseSlug = $slug;
        $i = 2;
        while (Tenant::where('slug', $slug)->exists()) {
            $slug = $baseSlug . '-' . $i++;
        }

        return DB::transaction(function () use ($request, $companyName, $slug) {
            $tenant = Tenant::create([
                'name' => $companyName,
                'slug' => $slug,
            ]);

            $user = User::create([
                'name' => trim($request->input('name')),
                'username' => strtolower(trim($request->input('username'))),
                'password' => Hash::make($request->input('password')),
                'role' => 'admin',
                'tenant_id' => $tenant->id,
            ]);

            // Seed default 3 services for this tenant — generik Layanan 1/2/3 (Opsi A untuk PKL)
            $defaults = [
                ['name' => 'Layanan 1', 'code' => 'layanan1', 'prefix' => 'A', 'counter_number' => 1, 'icon' => 'layers'],
                ['name' => 'Layanan 2', 'code' => 'layanan2', 'prefix' => 'B', 'counter_number' => 2, 'icon' => 'layers'],
                ['name' => 'Layanan 3', 'code' => 'layanan3', 'prefix' => 'C', 'counter_number' => 3, 'icon' => 'layers'],
            ];
            foreach ($defaults as $svc) {
                \App\Models\Service::create([
                    'name' => $svc['name'],
                    'code' => $svc['code'],
                    'prefix' => $svc['prefix'],
                    'counter_number' => $svc['counter_number'],
                    'icon' => $svc['icon'],
                    'service_group' => 'group_a',
                    'is_active' => true,
                    'show_in_kiosk' => true,
                    'tenant_id' => $tenant->id,
                ]);
            }

            // Seed per-tenant file settings from default tenant's files if exists, otherwise defaults
            $this->seedTenantSettings($tenant->id);

            $token = $user->createToken('auth_token')->plainTextToken;
            $user->load('tenant');

            return response()->json([
                'success' => true,
                'message' => 'Registrasi perusahaan & admin berhasil',
                'data' => [
                    'user' => $user,
                    'token' => $token,
                    'tenant' => $tenant,
                ],
            ], 201);
        });
    }

    private function seedTenantSettings(int $tenantId): void
    {
        $dstDir = storage_path('app/settings/tenants/' . $tenantId);
        if (!is_dir($dstDir)) mkdir($dstDir, 0755, true);

        // Ambil nama perusahaan dari tenant untuk auto-isi identitas (Opsi A)
        $tenantName = \App\Models\Tenant::where('id', $tenantId)->value('name') ?? '';

        // Identitas: auto-isi dengan nama perusahaan (admin tetap bisa ubah di Pengaturan → Identitas)
        file_put_contents($dstDir . '/general.json', json_encode([
            'institution_name' => $tenantName,
            'logo_url' => '',
        ]));
        // Kiosk: kosong (biar admin isi sendiri, sesuai permintaan)
        file_put_contents($dstDir . '/kiosk-text.json', json_encode([
            'welcome_text' => '',
            'subtitle_text' => '',
            'hint_text' => '',
            'footer_text' => '',
        ]));
        // Video: kosong (tidak ada video/link/volume custom)
        file_put_contents($dstDir . '/video-links.json', json_encode([]));
        file_put_contents($dstDir . '/video-volume.json', json_encode(['volume' => 0.2]));
        // Ticket text biarkan fallback ke global default (tidak perlu file tenant khusus)
    }
}
