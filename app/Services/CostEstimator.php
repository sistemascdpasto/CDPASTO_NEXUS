<?php

namespace App\Services;

use App\Models\Application;
use App\Models\ResourceMetric;
use App\Support\Sql;
use Illuminate\Support\Collection;

/**
 * Estimación del costo mensual en Railway a partir de las métricas de CPU, memoria y red.
 *
 * Railway cobra por uso: vCPU y GB de RAM por minuto, y egress por GB. Con el promedio
 * de CPU/RAM observado en el mes se proyecta el costo del mes completo.
 */
class CostEstimator
{
    /**
     * @param  Collection<int, Application>  $applications
     * @return array{services: list<array<string, mixed>>, total_projected: float, total_to_date: float, month_progress: float, daily: list<array<string, mixed>>, prices: array<string, float>}
     */
    public function monthly(Collection $applications): array
    {
        $from = now()->startOfMonth();
        $progress = round(now()->diffInSeconds($from, true) / now()->startOfMonth()->diffInSeconds(now()->endOfMonth(), true), 4);
        $prices = config('nexus.costs');

        $rows = ResourceMetric::query()
            ->whereIn('application_id', $applications->pluck('id'))
            ->where('measured_at', '>=', $from)
            ->groupBy('application_id', 'service_kind')
            ->selectRaw('application_id, service_kind, AVG(cpu) as cpu, AVG(memory_gb) as memory, SUM(network_tx_gb) as egress, COUNT(*) as samples, MIN(measured_at) as first_at')
            ->get();

        $services = $rows->map(function ($row) use ($applications, $prices, $progress) {
            $app = $applications->firstWhere('id', $row->application_id);
            $cpuCost = (float) $row->cpu * $prices['vcpu_month'];
            $memoryCost = (float) $row->memory * $prices['memory_gb_month'];
            // El egress observado se proyecta al mes completo según el avance.
            $egressProjected = $progress > 0 ? (float) $row->egress / max($progress, 0.01) : 0;
            $egressCost = $egressProjected * $prices['egress_gb'];
            $projected = $cpuCost + $memoryCost + $egressCost;

            return [
                'application_id' => $row->application_id,
                'name' => $app?->name,
                'kind' => $row->service_kind,
                'service' => $row->service_kind === ResourceMetric::KIND_DATABASE ? $app?->database_service_name : $app?->railway_service_name,
                'avg_cpu' => round((float) $row->cpu, 4),
                'avg_memory_gb' => round((float) $row->memory, 3),
                'egress_gb' => round((float) $row->egress, 3),
                'cpu_cost' => round($cpuCost, 2),
                'memory_cost' => round($memoryCost, 2),
                'egress_cost' => round($egressCost, 2),
                'projected' => round($projected, 2),
                'to_date' => round($projected * $progress, 2),
            ];
        })->sortByDesc('projected')->values();

        $day = Sql::dayBucket('measured_at');
        $daily = ResourceMetric::query()
            ->whereIn('application_id', $applications->pluck('id'))
            ->where('measured_at', '>=', $from)
            ->groupByRaw($day)
            ->orderByRaw($day)
            ->selectRaw("{$day} as date, application_id, service_kind, AVG(cpu) as cpu, AVG(memory_gb) as memory, SUM(network_tx_gb) as egress")
            ->groupBy('application_id', 'service_kind')
            ->get()
            ->groupBy('date')
            ->map(fn ($rows, $date) => [
                'date' => $date,
                // Costo de un día = 1/30 del costo mensual al ritmo de ese día.
                'cost' => round($rows->sum(fn ($r) => ((float) $r->cpu * $prices['vcpu_month'] + (float) $r->memory * $prices['memory_gb_month']) / 30
                    + (float) $r->egress * $prices['egress_gb']), 2),
            ])
            ->values()
            ->all();

        return [
            'services' => $services->all(),
            'total_projected' => round($services->sum('projected'), 2),
            'total_to_date' => round($services->sum('to_date'), 2),
            'month_progress' => $progress,
            'daily' => $daily,
            'prices' => $prices,
        ];
    }
}
