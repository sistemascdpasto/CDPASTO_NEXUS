<?php

namespace App\Models;

use App\Models\Concerns\BelongsToApplication;
use Illuminate\Database\Eloquent\Model;

class RequestMetric extends Model
{
    use BelongsToApplication;

    public $timestamps = false;

    protected $fillable = [
        'application_id',
        'bucket',
        'method',
        'route',
        'count',
        'total_ms',
        'max_ms',
        'errors_4xx',
        'errors_5xx',
    ];

    protected function casts(): array
    {
        return [
            'bucket' => 'datetime',
        ];
    }
}
