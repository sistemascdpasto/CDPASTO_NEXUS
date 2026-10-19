<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Anuncio (banner) que se muestra a los usuarios de las apps al iniciar sesión y a los
 * visitantes de la web principal.
 */
class Announcement extends Model
{
    protected $fillable = [
        'title',
        'link_url',
        'link_label',
        'starts_at',
        'ends_at',
        'is_active',
        'priority',
    ];

    protected $hidden = ['image_data'];

    protected $attributes = [
        'is_active' => true,
        'priority' => 0,
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(fn (Announcement $a) => $a->uuid ??= (string) Str::uuid());
    }

    public function applications(): BelongsToMany
    {
        return $this->belongsToMany(Application::class);
    }

    public function stats(): HasMany
    {
        return $this->hasMany(AnnouncementStat::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Activos y dentro de su vigencia.
     */
    protected function scopeLive(Builder $query): void
    {
        $now = now();

        $query->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now))
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', $now));
    }

    /**
     * @return 'live'|'scheduled'|'expired'|'paused'
     */
    public function state(): string
    {
        return match (true) {
            ! $this->is_active => 'paused',
            $this->ends_at !== null && $this->ends_at->isPast() => 'expired',
            $this->starts_at !== null && $this->starts_at->isFuture() => 'scheduled',
            default => 'live',
        };
    }

    public function setImage(string $binary, string $mime): void
    {
        $size = @getimagesizefromstring($binary) ?: [null, null];

        $this->forceFill([
            'image_data' => base64_encode($binary),
            'image_mime' => $mime,
            'image_size' => strlen($binary),
            'image_width' => $size[0],
            'image_height' => $size[1],
        ]);
    }

    /**
     * URL pública de la imagen; cambia con cada edición para invalidar la caché del navegador.
     */
    public function imageUrl(): string
    {
        return self::publicUrl(route('announcements.image', ['announcement' => $this->uuid, 'v' => $this->updated_at?->timestamp], false));
    }

    /**
     * URL absoluta con el dominio público de Nexus (APP_URL). El agente llama a Nexus por la red
     * privada de Railway, pero estas URLs las abre el navegador del usuario.
     */
    public static function publicUrl(string $path): string
    {
        return rtrim((string) config('app.url'), '/').'/'.ltrim($path, '/');
    }

    /**
     * Lo que reciben el agente de cada app y la web principal.
     *
     * @return array<string, mixed>
     */
    public function toFeed(): array
    {
        return [
            'id' => $this->uuid,
            'title' => $this->title,
            'image_url' => $this->imageUrl(),
            'width' => $this->image_width,
            'height' => $this->image_height,
            'link_url' => $this->link_url,
            'link_label' => $this->link_label,
            'event_url' => self::publicUrl(route('announcements.event', ['announcement' => $this->uuid], false)),
        ];
    }

    /**
     * Anuncios vigentes de una app, en orden de prioridad.
     *
     * @return list<array<string, mixed>>
     */
    public static function feedFor(Application $application): array
    {
        return static::live()
            ->whereHas('applications', fn ($q) => $q->whereKey($application->id))
            ->orderByDesc('priority')
            ->latest('id')
            ->limit(5)
            ->get()
            ->map(fn (Announcement $a) => $a->toFeed())
            ->all();
    }
}
