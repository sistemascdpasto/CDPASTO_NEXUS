<?php

namespace App\Models;

use App\Models\Concerns\BelongsToApplication;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ErrorGroup extends Model
{
    use BelongsToApplication;

    protected $fillable = [
        'application_id',
        'fingerprint',
        'exception_class',
        'message',
        'file',
        'line',
        'occurrences',
        'first_seen_at',
        'last_seen_at',
        'resolved_at',
        'resolved_by',
    ];

    protected function casts(): array
    {
        return [
            'first_seen_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public function events(): HasMany
    {
        return $this->hasMany(ErrorEvent::class);
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    protected function scopeUnresolved(Builder $query): void
    {
        $query->whereNull('resolved_at');
    }
}
