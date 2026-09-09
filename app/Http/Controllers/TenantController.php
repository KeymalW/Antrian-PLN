<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use Illuminate\Http\Request;

class TenantController extends Controller
{
    public function index()
    {
        // Public debug for PKL — only id/slug/name, no sensitive data
        $tenants = Tenant::orderBy('id')->get(['id', 'slug', 'name', 'created_at']);
        // Add counts per tenant for demo
        $tenants = $tenants->map(function ($t) {
            return [
                'id' => $t->id,
                'slug' => $t->slug,
                'name' => $t->name,
                'created_at' => $t->created_at,
                'users_count' => \App\Models\User::withoutGlobalScope('tenant')->where('tenant_id', $t->id)->count(),
                'services_count' => \App\Models\Service::withoutGlobalScope('tenant')->where('tenant_id', $t->id)->count(),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $tenants,
        ]);
    }
}
