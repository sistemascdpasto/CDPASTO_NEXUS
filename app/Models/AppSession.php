<?php

namespace App\Models;

use App\Models\Concerns\BelongsToApplication;
use Illuminate\Database\Eloquent\Model;

class AppSession extends Model
{
    use BelongsToApplication;

    public $timestamps = false;

    protected $fillable = [
        'application_id',
        'external_user_id',
        'user_name',
        'user_email',
        'user_role',
        'ip',
        'user_agent',
        'device',
        'login_at',
        'last_activity_at',
    ];

    protected function casts(): array
    {
        return [
            'login_at' => 'datetime',
            'last_activity_at' => 'datetime',
        ];
    }
}
