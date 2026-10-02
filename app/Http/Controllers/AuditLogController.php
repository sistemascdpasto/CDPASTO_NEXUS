<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\AuditLog;
use App\Services\PanelAudit;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AuditLogController extends Controller
{
    private const PDF_LIMIT = 2000;

    /**
     * Registro de acciones con filtros por app, usuario, acción y fechas (HU-16, HU-17, HU-18).
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        $filters = $this->filters($request);

        return Inertia::render('audit/index', [
            'logs' => $this->query($request)
                ->with('application:id,name')
                ->latest('occurred_at')
                ->latest('id')
                ->paginate(30)
                ->withQueryString(),
            'filters' => $filters,
            'applications' => Application::visibleTo($user)->orderBy('name')->get(['id', 'name']),
            'actions' => AuditLog::visibleTo($user)->distinct()->orderBy('action')->pluck('action'),
            'modules' => AuditLog::visibleTo($user)
                ->when($filters['application_id'] ?? null, fn ($q, $id) => $q->where('application_id', $id))
                ->distinct()->orderBy('module')->limit(200)->pluck('module'),
        ]);
    }

    /**
     * Exportación a Excel o PDF con los mismos filtros (HU-19).
     */
    public function export(Request $request, string $format): StreamedResponse|\Illuminate\Http\Response
    {
        abort_unless(in_array($format, ['xlsx', 'pdf'], true), 404);

        $query = $this->query($request)->with('application:id,name');
        $filename = 'auditoria-'.now()->format('Ymd-His').'.'.$format;

        PanelAudit::log('audit.exported', 'Exportó la auditoría a '.strtoupper($format), null, ['filters' => $this->filters($request)]);

        if ($format === 'pdf') {
            $logs = $query->latest('occurred_at')->latest('id')->limit(self::PDF_LIMIT)->get();

            return Pdf::loadView('exports.audit', [
                'logs' => $logs,
                'filters' => $this->describeFilters($request),
                'truncated' => $logs->count() >= self::PDF_LIMIT,
            ])->setPaper('letter', 'landscape')->download($filename);
        }

        return response()->streamDownload(function () use ($query) {
            $writer = new Writer;
            $writer->openToFile('php://output');
            $writer->addRow(Row::fromValues(
                ['Fecha', 'Aplicación', 'Usuario', 'ID usuario', 'Acción', 'Módulo', 'ID registro', 'Valores anteriores', 'Valores nuevos', 'IP', 'URL'],
                (new Style)->withFontBold(true),
            ));

            // Por id descendente (≈ orden cronológico) para recorrer sin cargar todo en memoria.
            $query->lazyByIdDesc(1000)->each(function (AuditLog $log) use ($writer) {
                $writer->addRow(Row::fromValues([
                    $log->occurred_at->format('Y-m-d H:i:s'),
                    $log->application?->name,
                    $log->user_name,
                    $log->external_user_id,
                    $log->action,
                    $log->module,
                    $log->record_id,
                    $log->old_values ? json_encode($log->old_values, JSON_UNESCAPED_UNICODE) : '',
                    $log->new_values ? json_encode($log->new_values, JSON_UNESCAPED_UNICODE) : '',
                    $log->ip,
                    $log->url,
                ]));
            });

            $writer->close();
        }, $filename, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    /**
     * @return array<string, string|null>
     */
    private function filters(Request $request): array
    {
        return $request->only(['application_id', 'user', 'action', 'module', 'record_id', 'from', 'to']);
    }

    private function query(Request $request): Builder
    {
        $filters = $this->filters($request);

        return AuditLog::query()
            ->visibleTo($request->user())
            ->when($filters['application_id'] ?? null, fn ($q, $id) => $q->where('application_id', $id))
            ->when($filters['action'] ?? null, fn ($q, $action) => $q->where('action', $action))
            ->when($filters['module'] ?? null, fn ($q, $module) => $q->where('module', $module))
            ->when($filters['record_id'] ?? null, fn ($q, $id) => $q->where('record_id', $id))
            ->when($filters['user'] ?? null, fn ($q, $user) => $q->where(fn ($q) => $q
                ->where('user_name', 'like', "%{$user}%")
                ->orWhere('external_user_id', $user)))
            ->when($filters['from'] ?? null, fn ($q, $from) => $q->where('occurred_at', '>=', $from))
            ->when($filters['to'] ?? null, fn ($q, $to) => $q->where('occurred_at', '<=', $to.' 23:59:59'));
    }

    private function describeFilters(Request $request): string
    {
        $filters = array_filter($this->filters($request));

        if (isset($filters['application_id'])) {
            $filters['application_id'] = Application::find($filters['application_id'])?->name;
        }

        $labels = ['application_id' => 'App', 'user' => 'Usuario', 'action' => 'Acción', 'module' => 'Módulo', 'record_id' => 'Registro', 'from' => 'Desde', 'to' => 'Hasta'];

        return collect($filters)->map(fn ($value, $key) => "{$labels[$key]}: {$value}")->implode(' · ') ?: 'Sin filtros';
    }
}
