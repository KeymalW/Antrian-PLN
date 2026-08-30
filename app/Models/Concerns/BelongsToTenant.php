<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

trait BelongsToTenant
{
    protected static function bootBelongsToTenant()
    {
        static::addGlobalScope('tenant', function (Builder $builder) {
            // Auto-scope to current user's tenant when authenticated via standard auth middleware.
            // Do NOT call Auth::guard('sanctum')->user() here to avoid recursion during authentication.
            $user = Auth::user();
            if ($user && isset($user->tenant_id) && $user->tenant_id) {
                $builder->where($builder->getModel()->getTable() . '.tenant_id', $user->tenant_id);
            }
        });

        static::creating(function ($model) {
            if (empty($model->tenant_id)) {
                $user = Auth::user();
                if ($user && isset($user->tenant_id) && $user->tenant_id) {
                    $model->tenant_id = $user->tenant_id;
                }
            }
        });
    }

    public function tenant()
    {
        return $this->belongsTo(\App\Models\Tenant::class);
    }
}
