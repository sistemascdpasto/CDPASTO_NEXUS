<?php

namespace App\Services;

use App\Models\Application;
use App\Models\Incident;
use App\Models\RequestMetric;
use Illuminate\Support\Collection;

/**
 * Puntaje de salud 0-100 por app, para ver de un vistazo cuál necesita atención.
 *
 * Laravel con agente: disponibilidad 7 d (40) + tasa de errores 5xx 24 h (20) + tiempo de respuesta (20)
 * + seguridad (10) + operación: trabajos fallidos y scheduler (10).
 * Sitios estáticos: disponibilidad (60) + tiempo de respuesta (40).
 */
class HealthScore
{
    public function __construct(private Metrics $metrics) {}

    /**
     * @param  Collection<int, Application>  $applications
     * @return array<int, array{score: int, grade: string, parts: array<string, array{points: float, max: int, detail: string}>}>
     */
    public function forApplications(Collection $applications): array
    {
        $ids = $applications->pluck('id')->all();
        $uptime = $this->metrics->uptimeByApp(now()->subWeek(), $ids);

        $requests = RequestMetric::query()
            ->whereIn('application_id', $ids)
            ->where('bucket', '>=', now()->subDay())
            ->groupBy('application_id')
            ->selectRaw('application_id, SUM(count) as total, SUM(errors_5xx) as errors, SUM(total_ms) as ms')
            ->get()
            ->keyBy('application_id');

        $openIncidents = Incident::whereIn('application_id', $ids)->open()->pluck('application_id')->flip();

        return $applications->mapWithKeys(function (Application $app) use ($uptime, $requests, $openIncidents) {
            $parts = [];
            $static = ! $app->isLaravel() || ! $app->last_ingest_at;
            $up = $uptime[$app->id] ?? null;

            // Disponibilidad: 100% = máximo, 95% o menos = 0.
            $uptimeMax = $static ? 60 : 40;
            $parts['uptime'] = [
                'points' => $up === null ? $uptimeMax / 2 : $this->scale($up, 95, 100) * $uptimeMax,
                'max' => $uptimeMax,
                'detail' => $up === null ? 'Sin datos' : number_format($up, 2).'% en 7 días',
            ];

            // Respuesta: ≤ 300 ms = máximo, ≥ 3 s = 0.
            $responseMax = $static ? 40 : 20;
            $req = $requests[$app->id] ?? null;
            $avgMs = $req && $req->total > 0 ? $req->ms / $req->total : $app->last_response_ms;
            $parts['response'] = [
                'points' => $avgMs === null ? $responseMax / 2 : (1 - $this->scale($avgMs, 300, 3000)) * $responseMax,
                'max' => $responseMax,
                'detail' => $avgMs === null ? 'Sin datos' : round($avgMs).' ms promedio',
            ];

            if (! $static) {
                $rate = $req && $req->total > 0 ? $req->errors / $req->total * 100 : 0;
                $parts['errors'] = [
                    'points' => (1 - $this->scale($rate, 0, 5)) * 20,
                    'max' => 20,
                    'detail' => number_format($rate, 2).'% de peticiones con error 5xx',
                ];

                $findings = count(AgentInfo::findings($app));
                $parts['security'] = [
                    'points' => max(0, 10 - $findings * 4),
                    'max' => 10,
                    'detail' => $findings === 0 ? 'Sin hallazgos' : "{$findings} hallazgos de seguridad",
                ];

                $failedJobs = (int) ($app->last_info['failed_jobs']['total'] ?? 0);
                $scheduler = $app->last_components['scheduler']['ok'] ?? true;
                $parts['operations'] = [
                    'points' => ($failedJobs === 0 ? 5 : ($failedJobs < 10 ? 2.5 : 0)) + ($scheduler ? 5 : 0),
                    'max' => 10,
                    'detail' => ($failedJobs === 0 ? 'Sin trabajos fallidos' : "{$failedJobs} trabajos fallidos").($scheduler ? '' : ' · scheduler detenido'),
                ];
            }

            $score = (int) round(array_sum(array_column($parts, 'points')));

            // Una caída en curso limita el puntaje: no puede verse "sana" una app que no responde.
            if (isset($openIncidents[$app->id])) {
                $score = min($score, 30);
            }

            return [$app->id => [
                'score' => $score,
                'grade' => $score >= 90 ? 'excelente' : ($score >= 75 ? 'buena' : ($score >= 50 ? 'regular' : 'crítica')),
                'parts' => array_map(fn ($p) => [...$p, 'points' => round($p['points'], 1)], $parts),
            ]];
        })->all();
    }

    /**
     * Normaliza $value entre $min y $max a 0..1.
     */
    private function scale(float $value, float $min, float $max): float
    {
        return max(0, min(1, ($value - $min) / ($max - $min)));
    }
}
