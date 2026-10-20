<?php

namespace App\Models;

use App\Models\Concerns\BelongsToApplication;
use Illuminate\Database\Eloquent\Model;

/**
 * Ubicación reportada por el agente de una app para uno de sus usuarios.
 */
class UserLocation extends Model
{
    use BelongsToApplication;

    public $timestamps = false;

    protected $fillable = [
        'application_id',
        'external_user_id',
        'user_name',
        'source',
        'latitude',
        'longitude',
        'accuracy_m',
        'city',
        'region',
        'country',
        'ip',
        'device',
        'recorded_at',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
            'accuracy_m' => 'integer',
            'recorded_at' => 'datetime',
        ];
    }

    public function place(): string
    {
        return collect([$this->city, $this->region, $this->country])->filter()->unique()->implode(', ') ?: 'Ubicación desconocida';
    }
}
