<?php

namespace App\Models;

use App\Models\Concerns\BelongsToApplication;
use Illuminate\Database\Eloquent\Model;

class UserActivity extends Model
{
    use BelongsToApplication;

    public $timestamps = false;

    protected $table = 'user_activity';

    protected $fillable = [
        'application_id',
        'external_user_id',
        'user_name',
        'bucket',
        'requests',
        'routes',
    ];

    protected function casts(): array
    {
        return [
            'bucket' => 'datetime',
            'routes' => 'array',
        ];
    }
}
