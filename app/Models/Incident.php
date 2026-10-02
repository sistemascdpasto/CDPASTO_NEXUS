<?php

namespace App\Models;

use App\Models\Concerns\BelongsToApplication;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Incident extends Model
{
    use BelongsToApplication;

    protected $fillable = [
        'application_id',
        'started_at',
        'resolved_at',
        'duration_seconds',
        'cause',
        'failed_checks',
        'notes',
        'notes_by',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public function notesAuthor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'notes_by');
    }

    /**
     * Duración en segundos: la registrada si ya se resolvió, o la transcurrida si sigue abierto.
     */
    public function currentDuration(): int
    {
        return $this->duration_seconds ?? (int) $this->started_at->diffInSeconds(now());
    }

    protected function scopeOpen(Builder $query): void
    {
        $query->whereNull('resolved_at');
    }
}
