const dateTime = new Intl.DateTimeFormat('es-CO', { dateStyle: 'short', timeStyle: 'short' });
const dateOnly = new Intl.DateTimeFormat('es-CO', { day: '2-digit', month: 'short' });
const timeOnly = new Intl.DateTimeFormat('es-CO', { hour: '2-digit', minute: '2-digit' });
const relative = new Intl.RelativeTimeFormat('es', { numeric: 'auto' });
const number = new Intl.NumberFormat('es-CO');

export function formatDateTime(value?: string | null): string {
    return value ? dateTime.format(new Date(value)) : '—';
}

export function formatRelative(value?: string | null): string {
    if (!value) return 'nunca';

    const seconds = Math.round((new Date(value).getTime() - Date.now()) / 1000);
    const abs = Math.abs(seconds);

    if (abs < 60) return relative.format(seconds, 'second');
    if (abs < 3600) return relative.format(Math.round(seconds / 60), 'minute');
    if (abs < 86400) return relative.format(Math.round(seconds / 3600), 'hour');

    return relative.format(Math.round(seconds / 86400), 'day');
}

/** Etiqueta de eje para buckets "YYYY-MM-DD HH:00" o "YYYY-MM-DD" (hora de Colombia, ya agrupada en el servidor). */
export function formatBucket(bucket: string): string {
    const [date, time] = bucket.split(' ');
    const [y, m, d] = date.split('-').map(Number);

    if (time) {
        return time.slice(0, 5);
    }

    return dateOnly.format(new Date(y, m - 1, d));
}

export function formatBucketLong(bucket: string): string {
    const [date, time] = bucket.split(' ');
    const [y, m, d] = date.split('-').map(Number);
    const label = dateOnly.format(new Date(y, m - 1, d));

    return time ? `${label}, ${time.slice(0, 5)}` : label;
}

export function formatTime(value: string): string {
    return timeOnly.format(new Date(value.replace(' ', 'T')));
}

export function formatNumber(value?: number | null): string {
    return value === null || value === undefined ? '—' : number.format(value);
}

export function formatMs(value?: number | null): string {
    if (value === null || value === undefined) return '—';

    return value >= 1000 ? `${(value / 1000).toFixed(1)} s` : `${value} ms`;
}

export function formatPercent(value?: number | null, digits = 2): string {
    return value === null || value === undefined ? '—' : `${value.toFixed(digits)}%`;
}

export function shortHash(hash?: string | null): string {
    return hash ? hash.slice(0, 7) : '—';
}
