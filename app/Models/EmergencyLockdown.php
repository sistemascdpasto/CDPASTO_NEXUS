<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Una activación del botón de emergencia: qué servicios de Railway se detuvieron, quién y por qué.
 */
class EmergencyLockdown extends Model
{
    public const ACTIVE = 'active';

    public const RELEASED = 'released';

    protected $fillable = ['status', 'reason', 'started_by', 'started_at', 'released_by', 'released_at', 'services'];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'released_at' => 'datetime',
            'services' => 'array',
        ];
    }

    public static function current(): ?self
    {
        return static::where('status', self::ACTIVE)->latest('id')->first();
    }

    /**
     * IDs de servicios de Railway que siguen detenidos por la emergencia activa.
     *
     * @return list<string>
     */
    public static function stoppedServiceIds(): array
    {
        $current = static::current();

        return $current
            ? collect($current->services)->where('state', 'stopped')->pluck('service_id')->values()->all()
            : [];
    }

    public function starter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'started_by');
    }

    public function releaser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'released_by');
    }
}
