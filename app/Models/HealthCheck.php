<?php

namespace App\Models;

use App\Enums\AppStatus;
use App\Models\Concerns\BelongsToApplication;
use Illuminate\Database\Eloquent\Model;

class HealthCheck extends Model
{
    use BelongsToApplication;

    public $timestamps = false;

    protected $fillable = [
        'status',
        'http_status',
        'response_ms',
        'components',
        'error',
        'checked_at',
        'application_id',
    ];

    protected function casts(): array
    {
        return [
            'status' => AppStatus::class,
            'components' => 'array',
            'checked_at' => 'datetime',
        ];
    }
}
