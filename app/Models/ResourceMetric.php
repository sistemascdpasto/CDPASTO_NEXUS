<?php

namespace App\Models;

use App\Models\Concerns\BelongsToApplication;
use Illuminate\Database\Eloquent\Model;

class ResourceMetric extends Model
{
    use BelongsToApplication;

    public $timestamps = false;

    protected $fillable = [
        'application_id',
        'measured_at',
        'cpu',
        'memory_gb',
        'network_rx_gb',
        'network_tx_gb',
    ];

    protected function casts(): array
    {
        return [
            'measured_at' => 'datetime',
            'cpu' => 'float',
            'memory_gb' => 'float',
            'network_rx_gb' => 'float',
            'network_tx_gb' => 'float',
        ];
    }
}
