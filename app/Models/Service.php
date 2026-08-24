<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Service extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'prefix',
        'is_active',
        'show_in_kiosk',
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
            'prefix' => $this->prefix,
            'isActive' => (bool) $this->is_active,
            'showInKiosk' => (bool) $this->show_in_kiosk,
        ];
    }
}
