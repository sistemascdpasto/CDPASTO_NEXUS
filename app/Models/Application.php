<?php

namespace App\Models;

use App\Enums\AppStatus;
use Database\Factories\ApplicationFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Application extends Model
{
    /** @use HasFactory<ApplicationFactory> */
    use HasFactory;

    public const TYPE_LARAVEL = 'laravel';

    public const TYPE_STATIC = 'static';

    protected $fillable = [
        'name',
        'slug',
        'description',
        'type',
        'url',
        'health_path',
        'environment',
        'railway_service_id',
        'railway_service_name',
        'database_service_id',
        'database_service_name',
        'repository',
        'check_interval_minutes',
        'slow_threshold_ms',
        'error_threshold',
        'failed_login_threshold',
        'mass_delete_threshold',
        'is_active',
    ];

    /**
     * Mismos valores por defecto que la migración, para que estén disponibles antes de recargar el modelo.
     */
    protected $attributes = [
        'type' => self::TYPE_LARAVEL,
        'environment' => 'production',
        'check_interval_minutes' => 1,
        'slow_threshold_ms' => 3000,
        'error_threshold' => 20,
        'failed_login_threshold' => 10,
        'mass_delete_threshold' => 30,
        'is_active' => true,
        'status' => 'unknown',
        'consecutive_failures' => 0,
    ];

    protected $hidden = [
        'api_key',
        'api_key_hash',
    ];

    protected function casts(): array
    {
        return [
            'api_key' => 'encrypted',
            'status' => AppStatus::class,
            'is_active' => 'boolean',
            'last_components' => 'array',
            'last_info' => 'array',
            'last_info_at' => 'datetime',
            'last_checked_at' => 'datetime',
            'status_changed_at' => 'datetime',
            'last_ingest_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Application $app) {
            $app->slug ??= Str::slug($app->name);
        });
    }

    /**
     * Genera una nueva API key (cifrada + hash para búsqueda) y devuelve el valor en claro.
     */
    public function regenerateApiKey(): string
    {
        $key = 'nx_'.Str::random(45);

        $this->forceFill([
            'api_key' => $key,
            'api_key_hash' => hash('sha256', $key),
            'api_key_prefix' => substr($key, 0, 10),
        ]);

        return $key;
    }

    public static function findByApiKey(string $key): ?self
    {
        return static::where('api_key_hash', hash('sha256', $key))->first();
    }

    public function isLaravel(): bool
    {
        return $this->type === self::TYPE_LARAVEL;
    }

    public function healthUrl(): string
    {
        $path = $this->health_path ?: ($this->isLaravel() ? '/nexus/health' : '/');

        return rtrim($this->url, '/').'/'.ltrim($path, '/');
    }

    public function agentUrl(string $path): string
    {
        return rtrim($this->url, '/').'/nexus/'.ltrim($path, '/');
    }

    public function isDueForCheck(): bool
    {
        return $this->last_checked_at === null
            || $this->last_checked_at->lte(now()->subMinutes($this->check_interval_minutes)->addSeconds(10));
    }

    public function hasDatabase(): bool
    {
        return filled($this->database_service_id);
    }

    public function userActivity(): HasMany
    {
        return $this->hasMany(UserActivity::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }

    public function healthChecks(): HasMany
    {
        return $this->hasMany(HealthCheck::class);
    }

    public function deployments(): HasMany
    {
        return $this->hasMany(Deployment::class);
    }

    public function resourceMetrics(): HasMany
    {
        return $this->hasMany(ResourceMetric::class);
    }

    public function requestMetrics(): HasMany
    {
        return $this->hasMany(RequestMetric::class);
    }

    public function errorGroups(): HasMany
    {
        return $this->hasMany(ErrorGroup::class);
    }

    public function loginEvents(): HasMany
    {
        return $this->hasMany(LoginEvent::class);
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(AppSession::class);
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    public function alerts(): HasMany
    {
        return $this->hasMany(Alert::class);
    }

    /**
     * Limita la consulta a las apps que el usuario puede ver.
     */
    protected function scopeVisibleTo(Builder $query, User $user): void
    {
        $ids = $user->accessibleApplicationIds();

        if ($ids !== null) {
            $query->whereIn('applications.id', $ids);
        }
    }

    protected function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }
}
