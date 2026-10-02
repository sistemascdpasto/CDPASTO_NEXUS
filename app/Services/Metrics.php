<?php

namespace App\Services;

use App\Enums\AppStatus;
use App\Models\HealthCheck;
use App\Models\RequestMetric;
use App\Models\ResourceMetric;
use App\Support\Sql;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Consultas agregadas para dashboard y detalle de apps (HU-05, HU-08, HU-09, HU-11).
 */
class Metrics
{
    /**
     * Porcentaje de checks no caídos por app desde una fecha.
     *
     * @param  list<int>|null  $applicationIds
     * @return array<int, float|null> application_id => porcentaje
     */
    public function uptimeByApp(Carbon $from, ?array $applicationIds = null): array
    {
        return HealthCheck::query()
            ->where('checked_at', '>=', $from)
            ->when($applicationIds !== null, fn ($q) => $q->whereIn('application_id', $applicationIds))
            ->groupBy('application_id')
            ->selectRaw('application_id, COUNT(*) as total, SUM(CASE WHEN status = ? THEN 0 ELSE 1 END) as up', [AppStatus::Down->value])
            ->get()
            ->mapWithKeys(fn ($row) => [$row->application_id => $row->total ? round($row->up / $row->total * 100, 2) : null])
            ->all();
    }

    public function uptime(int $applicationId, Carbon $from): ?float
    {
        return $this->uptimeByApp($from, [$applicationId])[$applicationId] ?? null;
    }

    /**
     * @return list<array{date: string, uptime: float, checks: int}>
     */
    public function dailyUptime(int $applicationId, int $days = 30): array
    {
        $day = Sql::dayBucket('checked_at');

        return HealthCheck::query()
            ->where('application_id', $applicationId)
            ->where('checked_at', '>=', now()->subDays($days - 1)->startOfDay())
            ->groupByRaw($day)
            ->orderByRaw($day)
            ->selectRaw("{$day} as date, COUNT(*) as total, SUM(CASE WHEN status = ? THEN 0 ELSE 1 END) as up", [AppStatus::Down->value])
            ->get()
            ->map(fn ($row) => ['date' => $row->date, 'uptime' => round($row->up / $row->total * 100, 2), 'checks' => (int) $row->total])
            ->all();
    }

    /**
     * Tiempo de respuesta del health check por hora.
     *
     * @return list<array{time: string, avg_ms: int, max_ms: int}>
     */
    public function healthResponseSeries(int $applicationId, Carbon $from): array
    {
        $hour = Sql::hourBucket('checked_at');

        return HealthCheck::query()
            ->where('application_id', $applicationId)
            ->where('checked_at', '>=', $from)
            ->whereNotNull('response_ms')
            ->groupByRaw($hour)
            ->orderByRaw($hour)
            ->selectRaw("{$hour} as time, AVG(response_ms) as avg_ms, MAX(response_ms) as max_ms")
            ->get()
            ->map(fn ($row) => ['time' => $row->time, 'avg_ms' => (int) $row->avg_ms, 'max_ms' => (int) $row->max_ms])
            ->all();
    }

    /**
     * Volumen de peticiones y tiempo promedio por hora o día.
     *
     * @param  list<int>|null  $applicationIds
     * @return list<array{time: string, requests: int, avg_ms: int, errors: int}>
     */
    public function requestSeries(Carbon $from, ?array $applicationIds, string $granularity = 'hour'): array
    {
        $bucket = $granularity === 'day' ? Sql::dayBucket('bucket') : Sql::hourBucket('bucket');

        return RequestMetric::query()
            ->where('bucket', '>=', $from)
            ->when($applicationIds !== null, fn ($q) => $q->whereIn('application_id', $applicationIds))
            ->groupByRaw($bucket)
            ->orderByRaw($bucket)
            ->selectRaw("{$bucket} as time, SUM(count) as requests, SUM(total_ms) as total_ms, SUM(errors_5xx) as errors")
            ->get()
            ->map(fn ($row) => [
                'time' => $row->time,
                'requests' => (int) $row->requests,
                'avg_ms' => $row->requests ? (int) round($row->total_ms / $row->requests) : 0,
                'errors' => (int) $row->errors,
            ])
            ->all();
    }

    /**
     * @return array{requests: int, avg_ms: int, errors_4xx: int, errors_5xx: int}
     */
    public function requestTotals(int $applicationId, Carbon $from): array
    {
        $row = RequestMetric::query()
            ->where('application_id', $applicationId)
            ->where('bucket', '>=', $from)
            ->selectRaw('SUM(count) as requests, SUM(total_ms) as total_ms, SUM(errors_4xx) as e4, SUM(errors_5xx) as e5')
            ->first();

        return [
            'requests' => (int) $row?->requests,
            'avg_ms' => $row?->requests ? (int) round($row->total_ms / $row->requests) : 0,
            'errors_4xx' => (int) $row?->e4,
            'errors_5xx' => (int) $row?->e5,
        ];
    }

    /**
     * Rutas más lentas por tiempo promedio.
     *
     * @return list<array{method: string, route: string, requests: int, avg_ms: int, max_ms: int, errors: int}>
     */
    public function slowestRoutes(int $applicationId, Carbon $from, int $limit = 10): array
    {
        return RequestMetric::query()
            ->where('application_id', $applicationId)
            ->where('bucket', '>=', $from)
            ->groupBy('method', 'route')
            ->selectRaw('method, route, SUM(count) as requests, SUM(total_ms) as total_ms, MAX(max_ms) as max_ms, SUM(errors_5xx) as errors')
            ->orderByRaw('SUM(total_ms) / SUM(count) DESC')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => [
                'method' => $row->method,
                'route' => $row->route,
                'requests' => (int) $row->requests,
                'avg_ms' => (int) round($row->total_ms / max(1, $row->requests)),
                'max_ms' => (int) $row->max_ms,
                'errors' => (int) $row->errors,
            ])
            ->all();
    }

    /**
     * @return list<array{time: string, cpu: ?float, memory_gb: ?float, network_rx_gb: ?float, network_tx_gb: ?float}>
     */
    public function resourceSeries(int $applicationId, Carbon $from): array
    {
        $hours = $from->diffInHours(now());
        $bucket = $hours > 48 ? Sql::hourBucket('measured_at') : 'measured_at';

        return ResourceMetric::query()
            ->where('application_id', $applicationId)
            ->where('measured_at', '>=', $from)
            ->groupByRaw($bucket)
            ->orderByRaw($bucket)
            ->select([
                DB::raw("{$bucket} as time"),
                DB::raw('AVG(cpu) as cpu'),
                DB::raw('AVG(memory_gb) as memory_gb'),
                DB::raw('SUM(network_rx_gb) as network_rx_gb'),
                DB::raw('SUM(network_tx_gb) as network_tx_gb'),
            ])
            ->get()
            ->map(fn ($row) => [
                'time' => (string) $row->time,
                'cpu' => $row->cpu !== null ? round((float) $row->cpu, 4) : null,
                'memory_gb' => $row->memory_gb !== null ? round((float) $row->memory_gb, 3) : null,
                'network_rx_gb' => $row->network_rx_gb !== null ? round((float) $row->network_rx_gb, 5) : null,
                'network_tx_gb' => $row->network_tx_gb !== null ? round((float) $row->network_tx_gb, 5) : null,
            ])
            ->all();
    }
}
