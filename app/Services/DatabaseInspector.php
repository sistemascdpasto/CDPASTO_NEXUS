<?php

namespace App\Services;

use App\Models\Application;
use App\Models\AppSession;
use App\Models\LoginEvent;
use App\Models\UserActivity;
use Illuminate\Database\Connection;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Consultas de solo lectura sobre la base de datos de una app: estado del servidor, tablas,
 * explorador de registros y directorio de usuarios.
 */
class DatabaseInspector
{
    /** Columnas cuyo valor nunca se muestra. */
    private const SENSITIVE = '/(password|passwd|secret|token|remember|api_key|apikey|two_factor|recovery|private_key|cvv|card)/i';

    public function __construct(private AppDatabase $databases) {}

    /**
     * @return array<string, mixed>
     */
    public function overview(Application $app): array
    {
        $db = $this->databases->connection($app);

        $status = collect($db->select("SHOW GLOBAL STATUS WHERE Variable_name IN ('Uptime','Threads_connected','Threads_running','Questions','Slow_queries','Aborted_connects','Max_used_connections','Connections')"))
            ->mapWithKeys(fn ($row) => [$row->Variable_name => (int) $row->Value]);

        $tables = $this->tables($db);

        return [
            'version' => $db->selectOne('SELECT VERSION() as v')->v,
            'database' => $db->getDatabaseName(),
            'uptime_seconds' => $status['Uptime'] ?? null,
            'threads_connected' => $status['Threads_connected'] ?? null,
            'threads_running' => $status['Threads_running'] ?? null,
            'max_connections' => (int) ($db->selectOne("SHOW VARIABLES LIKE 'max_connections'")->Value ?? 0),
            'max_used_connections' => $status['Max_used_connections'] ?? null,
            'slow_queries' => $status['Slow_queries'] ?? null,
            'aborted_connects' => $status['Aborted_connects'] ?? null,
            'queries_per_second' => ($status['Uptime'] ?? 0) > 0 ? round(($status['Questions'] ?? 0) / $status['Uptime'], 2) : null,
            'size_mb' => round($tables->sum('size_mb'), 2),
            'table_count' => $tables->count(),
            'row_estimate' => $tables->sum('rows'),
            'tables' => $tables->values()->all(),
        ];
    }

    /**
     * @return Collection<int, array{name: string, rows: int, size_mb: float, engine: ?string, updated_at: ?string}>
     */
    public function tables(Connection $db): Collection
    {
        return collect($db->select(
            'SELECT TABLE_NAME as name, TABLE_ROWS as rows_estimate, DATA_LENGTH + INDEX_LENGTH as bytes, ENGINE as engine, UPDATE_TIME as updated_at
             FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = ? ORDER BY bytes DESC',
            ['BASE TABLE'],
        ))->map(fn ($t) => [
            'name' => $t->name,
            'rows' => (int) $t->rows_estimate,
            'size_mb' => round(((int) $t->bytes) / 1048576, 2),
            'engine' => $t->engine,
            'updated_at' => $t->updated_at,
        ]);
    }

    /**
     * Registros de una tabla, más recientes primero, con columnas sensibles ocultas.
     *
     * @return array<string, mixed>
     */
    public function browse(Application $app, string $table, int $page = 1, ?string $search = null, int $perPage = 25): array
    {
        $db = $this->databases->connection($app);

        if (! $this->tables($db)->contains('name', $table)) {
            throw new InvalidArgumentException("La tabla {$table} no existe.");
        }

        $columns = collect($db->select(
            'SELECT COLUMN_NAME as name, DATA_TYPE as type, COLUMN_KEY as `key` FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? ORDER BY ORDINAL_POSITION',
            [$table],
        ));

        $primary = $columns->firstWhere('key', 'PRI')?->name;
        $query = $db->table($table);

        if ($search) {
            $searchable = $columns->filter(fn ($c) => in_array($c->type, ['varchar', 'char', 'text', 'mediumtext', 'int', 'bigint'], true) && ! preg_match(self::SENSITIVE, $c->name));
            $query->where(function ($q) use ($searchable, $search) {
                foreach ($searchable as $column) {
                    $q->orWhere($column->name, 'like', "%{$search}%");
                }
            });
        }

        $total = (clone $query)->count();

        if ($primary) {
            $query->orderByDesc($primary);
        }

        $rows = $query->forPage($page, $perPage)->get()->map(fn ($row) => $this->mask((array) $row));

        return [
            'table' => $table,
            'columns' => $columns->map(fn ($c) => ['name' => $c->name, 'type' => $c->type, 'sensitive' => (bool) preg_match(self::SENSITIVE, $c->name)])->values()->all(),
            'rows' => $rows->values()->all(),
            'total' => $total,
            'page' => $page,
            'last_page' => max(1, (int) ceil($total / $perPage)),
        ];
    }

    /**
     * Directorio de usuarios de la app, enriquecido con lo que Nexus sabe de cada uno.
     *
     * @return array{users: list<array<string, mixed>>, total: int, page: int, last_page: int, columns: list<string>}
     */
    public function users(Application $app, ?string $search = null, int $page = 1, int $perPage = 30, ?string $status = null): array
    {
        $db = $this->databases->connection($app);
        $columns = $this->userColumns($db);

        $query = $db->table('users')->select(array_values(array_intersect(['id', 'name', 'first_name', 'last_name', 'nombres', 'apellidos', 'email', 'identification_number', 'identification', 'numero_identificacion', 'role', 'rol', 'is_active', 'activo', 'created_at', 'deleted_at'], $columns)));

        if ($search) {
            $query->where(function ($q) use ($search, $columns) {
                foreach (array_intersect(['name', 'email', 'identification_number', 'identification', 'numero_identificacion', 'first_name', 'last_name', 'nombres', 'apellidos'], $columns) as $column) {
                    $q->orWhere($column, 'like', "%{$search}%");
                }

                if (ctype_digit($search)) {
                    $q->orWhere('id', (int) $search);
                }
            });
        }

        // Cada app nombra distinto la columna de cuenta activa (is_active en Adenar/Tickets, activo en 5S).
        $activeColumn = collect(['is_active', 'activo'])->first(fn ($c) => in_array($c, $columns, true));

        if ($activeColumn && in_array($status, ['active', 'inactive'], true)) {
            $query->where($activeColumn, $status === 'active');
        }

        $total = (clone $query)->count();
        $users = $query->orderBy(collect(['name', 'nombres'])->first(fn ($c) => in_array($c, $columns, true)) ?? 'id')->forPage($page, $perPage)->get();

        return [
            'users' => $this->enrich($app, $db, $users)->values()->all(),
            'total' => $total,
            'page' => $page,
            'last_page' => max(1, (int) ceil($total / $perPage)),
            'columns' => $columns,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function user(Application $app, string $id): ?array
    {
        $db = $this->databases->connection($app);
        $row = $db->table('users')->where('id', $id)->first();

        if (! $row) {
            return null;
        }

        return $this->enrich($app, $db, collect([$row]))->first() + ['raw' => $this->mask((array) $row)];
    }

    /**
     * @param  Collection<int, object>  $users
     * @return Collection<int, array<string, mixed>>
     */
    private function enrich(Application $app, Connection $db, Collection $users): Collection
    {
        $ids = $users->pluck('id')->map(fn ($id) => (string) $id)->all();
        $roles = $this->roles($db, $ids);

        $lastLogins = LoginEvent::where('application_id', $app->id)->where('event', 'login')->whereIn('external_user_id', $ids)
            ->groupBy('external_user_id')->selectRaw('external_user_id, MAX(occurred_at) as at')->pluck('at', 'external_user_id');
        $lastActivity = UserActivity::where('application_id', $app->id)->whereIn('external_user_id', $ids)
            ->groupBy('external_user_id')->selectRaw('external_user_id, MAX(bucket) as at')->pluck('at', 'external_user_id');
        $online = AppSession::where('application_id', $app->id)->whereIn('external_user_id', $ids)
            ->where('last_activity_at', '>=', now()->subMinutes(config('nexus.active_session_minutes')))->pluck('external_user_id')->flip();

        return $users->map(function ($user) use ($roles, $lastLogins, $lastActivity, $online) {
            $id = (string) $user->id;
            $name = $user->name ?? trim(($user->first_name ?? $user->nombres ?? '').' '.($user->last_name ?? $user->apellidos ?? ''));

            return [
                'id' => $id,
                'name' => $name ?: "#{$id}",
                'email' => $user->email ?? null,
                'document' => $user->identification_number ?? $user->identification ?? $user->numero_identificacion ?? null,
                'roles' => $roles[$id] ?? (isset($user->role) || isset($user->rol) ? [(string) ($user->role ?? $user->rol)] : []),
                'is_active' => isset($user->is_active) ? (bool) $user->is_active : (isset($user->activo) ? (bool) $user->activo : null),
                'deleted' => ! empty($user->deleted_at),
                'created_at' => $user->created_at ?? null,
                'last_login_at' => $lastLogins[$id] ?? null,
                'last_activity_at' => $lastActivity[$id] ?? null,
                'online' => isset($online[$id]),
            ];
        });
    }

    /**
     * Roles de spatie/laravel-permission si la app los usa.
     *
     * @param  list<string>  $ids
     * @return array<string, list<string>>
     */
    private function roles(Connection $db, array $ids): array
    {
        if (! $ids || ! $db->getSchemaBuilder()->hasTable('model_has_roles')) {
            return [];
        }

        return $db->table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('model_type', 'like', '%User')
            ->whereIn('model_id', $ids)
            ->get(['model_id', 'roles.name'])
            ->groupBy(fn ($row) => (string) $row->model_id)
            ->map(fn ($rows) => $rows->pluck('name')->all())
            ->all();
    }

    /**
     * @return list<string>
     */
    private function userColumns(Connection $db): array
    {
        if (! $db->getSchemaBuilder()->hasTable('users')) {
            throw new InvalidArgumentException('La base de datos no tiene tabla users.');
        }

        return $db->getSchemaBuilder()->getColumnListing('users');
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function mask(array $row): array
    {
        foreach ($row as $column => $value) {
            if (preg_match(self::SENSITIVE, $column)) {
                $row[$column] = $value === null ? null : '••••••';
            } elseif (is_string($value) && mb_strlen($value) > 300) {
                $row[$column] = Str::limit($value, 300);
            } elseif (is_string($value) && ! mb_check_encoding($value, 'UTF-8')) {
                $row[$column] = '[binario]';
            }
        }

        return $row;
    }
}
