<?php

namespace App\Models\Concerns;

use App\Models\Application;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToApplication
{
    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    /**
     * Limita los registros a las apps que el usuario tiene asignadas.
     */
    protected function scopeVisibleTo(Builder $query, User $user): void
    {
        $ids = $user->accessibleApplicationIds();

        if ($ids !== null) {
            $query->whereIn($this->qualifyColumn('application_id'), $ids);
        }
    }
}
