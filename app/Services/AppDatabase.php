<?php

namespace App\Services;

use App\Models\Application;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use PDO;
use RuntimeException;

/**
 * Conexión de SOLO LECTURA a la base MySQL de una app en Railway.
 *
 * Las credenciales no se guardan en Nexus: se leen de las variables del servicio MySQL vía la API
 * de Railway y se cachean cifradas 10 minutos. Dentro de Railway se usa la red privada (MYSQL_URL);
 * fuera (desarrollo local) el proxy TCP público (MYSQL_PUBLIC_URL).
 */
class AppDatabase
{
    /** @var array<string, true> */
    private array $readOnlyApplied = [];

    public function __construct(private RailwayClient $railway) {}

    public function connection(Application $app): Connection
    {
        if (! $app->hasDatabase()) {
            throw new RuntimeException('La aplicación no tiene una base de datos de Railway vinculada.');
        }

        $name = "app_db_{$app->id}";

        if (! config("database.connections.{$name}")) {
            config(["database.connections.{$name}" => $this->config($this->url($app))]);
        }

        $connection = DB::connection($name);

        // Toda transacción (incluidas las sentencias sueltas) queda en modo solo lectura.
        if (! isset($this->readOnlyApplied[$name])) {
            $connection->statement('SET SESSION TRANSACTION READ ONLY');
            $this->readOnlyApplied[$name] = true;
        }

        return $connection;
    }

    public function forget(Application $app): void
    {
        Cache::forget("app-db-url:{$app->id}");
        DB::purge("app_db_{$app->id}");
        unset($this->readOnlyApplied["app_db_{$app->id}"]);
    }

    private function url(Application $app): string
    {
        $encrypted = Cache::remember("app-db-url:{$app->id}", now()->addMinutes(10), function () use ($app) {
            $variables = $this->railway->serviceVariables($app->database_service_id);
            $url = self::runningOnRailway()
                ? ($variables['MYSQL_URL'] ?? null)
                : ($variables['MYSQL_PUBLIC_URL'] ?? null);

            if (! $url) {
                throw new RuntimeException(self::runningOnRailway()
                    ? 'El servicio MySQL no expone MYSQL_URL.'
                    : 'El servicio MySQL no tiene proxy TCP público (MYSQL_PUBLIC_URL). Actívalo en Railway o ejecuta Nexus dentro de Railway.');
            }

            return Crypt::encryptString($url);
        });

        return Crypt::decryptString($encrypted);
    }

    /**
     * @return array<string, mixed>
     */
    private function config(string $url): array
    {
        $parts = parse_url($url);

        if (! $parts || empty($parts['host'])) {
            throw new RuntimeException('URL de MySQL inválida en Railway.');
        }

        return [
            'driver' => 'mysql',
            'host' => $parts['host'],
            'port' => $parts['port'] ?? 3306,
            'database' => ltrim($parts['path'] ?? '/railway', '/'),
            'username' => rawurldecode($parts['user'] ?? 'root'),
            'password' => rawurldecode($parts['pass'] ?? ''),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'strict' => true,
            'options' => [
                PDO::ATTR_TIMEOUT => 8,
            ],
        ];
    }

    public static function runningOnRailway(): bool
    {
        return (bool) config('nexus.railway.inside');
    }
}
