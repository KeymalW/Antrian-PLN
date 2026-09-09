<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\BelongsToTenant;

class Service extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'name',
        'code',
        'prefix',
        'counter_number',
        'icon',
        'service_group',
        'is_active',
        'show_in_kiosk',
        'tenant_id',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'show_in_kiosk' => 'boolean',
    ];

    public function toArray()
    {
        return [
            'id' => (string) $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'prefix' => $this->prefix,
            'counterNumber' => $this->counter_number !== null ? (int) $this->counter_number : null,
            'icon' => $this->icon,
            'serviceGroup' => $this->service_group ?? 'group_a',
            'isActive' => (bool) $this->is_active,
            'showInKiosk' => (bool) $this->show_in_kiosk,
            'tenantId' => $this->tenant_id !== null ? (int) $this->tenant_id : null,
        ];
    }
}
