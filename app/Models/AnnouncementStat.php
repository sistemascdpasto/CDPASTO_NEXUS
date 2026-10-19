<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class AnnouncementStat extends Model
{
    public $timestamps = false;

    protected $fillable = ['announcement_id', 'application_id', 'date', 'views', 'clicks'];

    protected function casts(): array
    {
        return ['date' => 'date'];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    /**
     * Suma una vista o un clic del día (una sola consulta, segura ante concurrencia).
     */
    public static function bump(Announcement $announcement, Application $application, string $type): void
    {
        $column = $type === 'click' ? 'clicks' : 'views';
        $key = ['announcement_id' => $announcement->id, 'application_id' => $application->id, 'date' => now()->toDateString()];

        static::query()->upsert(
            [[...$key, 'views' => $column === 'views' ? 1 : 0, 'clicks' => $column === 'clicks' ? 1 : 0]],
            array_keys($key),
            [$column => DB::raw(static::query()->getGrammar()->wrap($column).' + 1')],
        );
    }
}
