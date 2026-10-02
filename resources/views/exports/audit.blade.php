<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Auditoría</title>
    <style>
        * { font-family: DejaVu Sans, sans-serif; }
        body { font-size: 8px; color: #111827; }
        h1 { font-size: 14px; margin: 0 0 2px; }
        .meta { color: #6b7280; margin-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #111827; color: #fff; text-align: left; padding: 4px; }
        td { border-bottom: 1px solid #e5e7eb; padding: 3px 4px; vertical-align: top; }
        tr:nth-child(even) td { background: #f9fafb; }
        .changes { font-family: DejaVu Sans Mono, monospace; font-size: 7px; word-break: break-all; }
        .old { color: #b91c1c; }
        .new { color: #047857; }
    </style>
</head>
<body>
    <h1>Nexus · Reporte de auditoría</h1>
    <div class="meta">
        Generado el {{ now()->format('d/m/Y h:i a') }} por {{ auth()->user()->name }} · {{ $filters }} · {{ $logs->count() }} registros
        @if ($truncated)
            (limitado a los {{ $logs->count() }} más recientes; use Excel para el detalle completo)
        @endif
    </div>
    <table>
        <thead>
            <tr>
                <th style="width: 9%">Fecha</th>
                <th style="width: 10%">Aplicación</th>
                <th style="width: 11%">Usuario</th>
                <th style="width: 7%">Acción</th>
                <th style="width: 11%">Módulo</th>
                <th style="width: 6%">Registro</th>
                <th>Cambios</th>
                <th style="width: 8%">IP</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($logs as $log)
                <tr>
                    <td>{{ $log->occurred_at->format('d/m/Y H:i:s') }}</td>
                    <td>{{ $log->application?->name }}</td>
                    <td>{{ $log->user_name ?? 'Sistema' }}</td>
                    <td>{{ $log->action }}</td>
                    <td>{{ $log->module }}</td>
                    <td>{{ $log->record_id }}</td>
                    <td class="changes">
                        @foreach (array_unique(array_merge(array_keys($log->old_values ?? []), array_keys($log->new_values ?? []))) as $field)
                            <div>
                                <strong>{{ $field }}:</strong>
                                @if (array_key_exists($field, $log->old_values ?? []))
                                    <span class="old">{{ \Illuminate\Support\Str::limit(json_encode($log->old_values[$field], JSON_UNESCAPED_UNICODE), 80) }}</span> →
                                @endif
                                <span class="new">{{ \Illuminate\Support\Str::limit(json_encode($log->new_values[$field] ?? null, JSON_UNESCAPED_UNICODE), 80) }}</span>
                            </div>
                        @endforeach
                    </td>
                    <td>{{ $log->ip }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
