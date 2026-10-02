<?php

namespace App\Models;

use App\Models\Concerns\BelongsToApplication;
use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    use BelongsToApplication;

    public $timestamps = false;

    protected $fillable = [
        'application_id',
        'external_user_id',
        'user_name',
        'action',
        'module',
        'record_id',
        'old_values',
        'new_values',
        'ip',
        'url',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
            'occurred_at' => 'datetime',
        ];
    }
}
