<?php

namespace App\Models;

use App\Models\Concerns\BelongsToApplication;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RemoteCommand extends Model
{
    use BelongsToApplication;

    protected $fillable = [
        'application_id',
        'user_id',
        'type',
        'external_user_id',
        'target_name',
        'payload',
        'status',
        'response',
        'executed_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'executed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
