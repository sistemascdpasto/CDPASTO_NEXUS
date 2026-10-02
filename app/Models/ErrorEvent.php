<?php

namespace App\Models;

use App\Models\Concerns\BelongsToApplication;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ErrorEvent extends Model
{
    use BelongsToApplication;

    public $timestamps = false;

    protected $fillable = [
        'error_group_id',
        'application_id',
        'external_user_id',
        'user_name',
        'url',
        'method',
        'ip',
        'trace',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
        ];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(ErrorGroup::class, 'error_group_id');
    }
}
