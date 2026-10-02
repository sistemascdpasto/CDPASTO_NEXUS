<?php

namespace App\Models;

use App\Models\Concerns\BelongsToApplication;
use Illuminate\Database\Eloquent\Model;

class Deployment extends Model
{
    use BelongsToApplication;

    protected $fillable = [
        'application_id',
        'railway_id',
        'status',
        'commit_hash',
        'commit_message',
        'commit_author',
        'branch',
        'deployed_at',
    ];

    protected function casts(): array
    {
        return [
            'deployed_at' => 'datetime',
        ];
    }
}
