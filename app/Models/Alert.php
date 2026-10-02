<?php

namespace App\Models;

use App\Enums\AlertType;
use App\Models\Concerns\BelongsToApplication;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Alert extends Model
{
    use BelongsToApplication;

    protected $fillable = [
        'application_id',
        'type',
        'severity',
        'title',
        'message',
        'data',
        'acknowledged_at',
        'acknowledged_by',
    ];

    protected function casts(): array
    {
        return [
            'type' => AlertType::class,
            'data' => 'array',
            'acknowledged_at' => 'datetime',
        ];
    }

    public function acknowledgedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acknowledged_by');
    }
}
