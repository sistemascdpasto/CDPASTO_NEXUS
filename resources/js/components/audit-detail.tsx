import { ValueDiff } from '@/components/value-diff';
import { type AuditLog } from '@/types';
import { Download, FileUp } from 'lucide-react';

interface UploadedFile {
    campo?: string;
    nombre: string;
    tamano_kb?: number;
    tipo?: string | null;
}

function formatSize(kb?: number): string {
    if (kb === undefined) return '';

    return kb >= 1024 ? `${(kb / 1024).toFixed(1)} MB` : `${kb} KB`;
}

/**
 * Detalle de un registro de auditoría: archivos para subidas/descargas, antes/después para el resto.
 */
export function AuditDetail({ log }: { log: AuditLog }) {
    const values = log.new_values ?? {};

    if (log.action === 'uploaded' && Array.isArray(values.archivos)) {
        return (
            <div className="space-y-1.5">
                <p className="text-muted-foreground text-xs">{String(values.ruta ?? '')}</p>
                <ul className="space-y-1">
                    {(values.archivos as UploadedFile[]).map((file, i) => (
                        <li key={i} className="bg-background flex items-center gap-2 rounded-md border px-3 py-1.5 text-sm">
                            <FileUp className="size-4 shrink-0" style={{ color: 'var(--series-2)' }} />
                            <span className="truncate font-medium">{file.nombre}</span>
                            <span className="text-muted-foreground ml-auto shrink-0 text-xs">
                                {[formatSize(file.tamano_kb), file.tipo, file.campo && `campo ${file.campo}`].filter(Boolean).join(' · ')}
                            </span>
                        </li>
                    ))}
                </ul>
            </div>
        );
    }

    if (log.action === 'downloaded') {
        return (
            <div className="bg-background flex items-center gap-2 rounded-md border px-3 py-1.5 text-sm">
                <Download className="size-4 shrink-0" style={{ color: 'var(--status-warning)' }} />
                <span className="font-medium">{String(values.archivo ?? 'Archivo')}</span>
                <span className="text-muted-foreground ml-auto text-xs">{String(values.ruta ?? '')}</span>
            </div>
        );
    }

    return <ValueDiff oldValues={log.old_values} newValues={log.new_values} />;
}

/**
 * Resumen corto para listas: nombre del archivo o cantidad de campos cambiados.
 */
export function auditSummary(log: AuditLog): string | null {
    const values = log.new_values ?? {};

    if (log.action === 'uploaded' && Array.isArray(values.archivos)) {
        const files = values.archivos as UploadedFile[];

        return files.length === 1 ? files[0].nombre : `${files.length} archivos`;
    }

    if (log.action === 'downloaded') {
        return String(values.archivo ?? '');
    }

    const fields = Object.keys(log.new_values ?? log.old_values ?? {}).length;

    return fields > 0 ? `${fields} campos` : null;
}
