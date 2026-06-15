<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Antrian extends Model
{
    use HasFactory;

    protected $fillable = [
        'nomor_antrian',
        'service_type',
        'tanggal',
        'status',
        'counter_number',
        'called_at',
        'completed_at',
    ];

    protected $casts = [
        'called_at' => 'datetime',
        'completed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function toArray()
    {
        return [
            'id' => (string) $this->id,
            'queueNumber' => $this->nomor_antrian,
            'serviceType' => $this->service_type,
            'status' => $this->status,
            'counterNumber' => $this->counter_number !== null ? (int) $this->counter_number : null,
            'createdAt' => $this->created_at ? $this->created_at->toIso8601String() : null,
            'calledAt' => $this->called_at ? $this->called_at->toIso8601String() : null,
            'completedAt' => $this->completed_at ? $this->completed_at->toIso8601String() : null,
        ];
    }
}
