import { applyFilters, EmptyState, NativeSelect, PageHeader, Panel, StatCard } from '@/components/nexus-ui';
import AppLayout from '@/layouts/app-layout';
import { formatNumber, formatRelative } from '@/lib/format';
import { cn } from '@/lib/utils';
import { Head } from '@inertiajs/react';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import { Building2, Crosshair, LocateFixed, MapPinned, Wifi } from 'lucide-react';
import { useEffect, useMemo, useRef } from 'react';

interface Point {
    id: number;
    application: { id: number; name: string; slug: string } | null;
    user_id: string | null;
    user_name: string | null;
    source: 'gps' | 'ip';
    latitude: number | null;
    longitude: number | null;
    accuracy_m: number | null;
    place: string;
    city: string | null;
    device: string | null;
    ip: string | null;
    recorded_at: string;
}

interface Props {
    points: Point[];
    byCity: { city: string; users: number }[];
    stats: { users: number; gps: number; ip: number; cities: number };
    applications: { id: number; name: string; slug: string }[];
    filters: { period: 'now' | '24h' | '7d'; app: number | null };
}

// Paleta categórica en orden fijo: el color sigue a la app, no al orden.
const APP_COLORS = ['#2a78d6', '#eb6834', '#0ca30c', '#a24bd6', '#d6a12a', '#2ab3b8'];

const periods: { key: Props['filters']['period']; label: string }[] = [
    { key: 'now', label: 'Conectados ahora' },
    { key: '24h', label: 'Últimas 24 h' },
    { key: '7d', label: 'Últimos 7 días' },
];

function escapeHtml(value: string): string {
    return value.replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c] as string);
}

export default function LocationMap({ points, byCity, stats, applications, filters }: Props) {
    const container = useRef<HTMLDivElement>(null);
    const map = useRef<L.Map | null>(null);
    const layer = useRef<L.LayerGroup | null>(null);

    const colorFor = useMemo(() => {
        const ids = applications.map((a) => a.id);

        return (appId?: number) => APP_COLORS[Math.max(0, ids.indexOf(appId ?? -1)) % APP_COLORS.length];
    }, [applications]);

    const located = useMemo(() => points.filter((p) => p.latitude !== null && p.longitude !== null), [points]);

    // Crear el mapa una sola vez.
    useEffect(() => {
        if (!container.current || map.current) return;

        const dark = document.documentElement.classList.contains('dark');
        map.current = L.map(container.current, { zoomControl: true, attributionControl: true }).setView([1.2136, -77.2811], 7);
        // Teselas estándar de OpenStreetMap (gratuitas con atribución). En modo oscuro se
        // invierten con CSS para no deslumbrar.
        L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            className: dark ? 'nexus-tiles-dark' : '',
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
        }).addTo(map.current);
        layer.current = L.layerGroup().addTo(map.current);

        return () => {
            map.current?.remove();
            map.current = null;
        };
    }, []);

    // Pintar los puntos cada vez que cambian los filtros.
    useEffect(() => {
        if (!map.current || !layer.current) return;
        layer.current.clearLayers();

        located.forEach((p) => {
            const color = colorFor(p.application?.id);
            const latlng: L.LatLngExpression = [p.latitude as number, p.longitude as number];
            const precise = p.source === 'gps';

            if (precise && p.accuracy_m && p.accuracy_m > 30) {
                L.circle(latlng, { radius: p.accuracy_m, color, weight: 1, opacity: 0.4, fillOpacity: 0.06 }).addTo(layer.current!);
            }

            const marker = L.circleMarker(latlng, {
                radius: precise ? 8 : 7,
                color: '#ffffff',
                weight: 2,
                fillColor: color,
                fillOpacity: precise ? 0.95 : 0.55,
                dashArray: precise ? undefined : '3 3',
            });

            marker.bindTooltip(escapeHtml(p.user_name ?? `Usuario ${p.user_id ?? ''}`), { direction: 'top', offset: [0, -6] });
            marker.bindPopup(
                `<div style="min-width:200px;font-size:12px;line-height:1.45">
                    <strong style="font-size:13px">${escapeHtml(p.user_name ?? 'Usuario ' + (p.user_id ?? ''))}</strong><br>
                    <span style="color:${color};font-weight:600">${escapeHtml(p.application?.name ?? '')}</span><br>
                    ${escapeHtml(p.place)}<br>
                    <span style="opacity:.7">${precise ? `Ubicación del dispositivo${p.accuracy_m ? ` · ±${p.accuracy_m} m` : ''}` : 'Aproximada por IP'}</span><br>
                    <span style="opacity:.7">${escapeHtml(formatRelative(p.recorded_at))}${p.device ? ' · ' + escapeHtml(p.device) : ''}</span>
                </div>`,
            );
            marker.addTo(layer.current!);
        });

        if (located.length > 0) {
            const bounds = L.latLngBounds(located.map((p) => [p.latitude as number, p.longitude as number] as L.LatLngTuple));
            map.current.fitBounds(bounds.pad(0.25), { maxZoom: 13 });
        }
    }, [located, colorFor]);

    return (
        <AppLayout breadcrumbs={[{ title: 'Mapa de conexiones', href: '/map' }]}>
            <Head title="Mapa de conexiones" />
            <div className="space-y-6 p-4 md:p-6">
                <PageHeader
                    title="Mapa de conexiones"
                    description="Desde dónde trabajan los usuarios de cada sistema. La ubicación se toma al iniciar sesión y cada 10 minutos, con permiso del usuario; si no lo da, se usa la ciudad aproximada por su IP."
                />

                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <StatCard label="Usuarios en el mapa" value={formatNumber(stats.users)} icon={MapPinned} />
                    <StatCard label="Ubicación del dispositivo" value={formatNumber(stats.gps)} hint="Con permiso (GPS / Wi-Fi)" icon={LocateFixed} />
                    <StatCard label="Aproximada por IP" value={formatNumber(stats.ip)} hint="Sin permiso o sin GPS" icon={Wifi} />
                    <StatCard label="Ciudades" value={formatNumber(stats.cities)} icon={Building2} />
                </div>

                <div className="flex flex-wrap items-center gap-3">
                    <div className="bg-muted inline-flex rounded-lg p-1" role="group" aria-label="Periodo">
                        {periods.map((p) => (
                            <button
                                key={p.key}
                                type="button"
                                onClick={() => applyFilters('/map', { period: p.key, app: filters.app })}
                                className={cn(
                                    'rounded-md px-3 py-1.5 text-sm transition',
                                    filters.period === p.key ? 'bg-background text-foreground font-medium shadow-sm' : 'text-muted-foreground hover:text-foreground',
                                )}
                                aria-pressed={filters.period === p.key}
                            >
                                {p.label}
                            </button>
                        ))}
                    </div>
                    <NativeSelect value={filters.app ?? ''} onChange={(e) => applyFilters('/map', { period: filters.period, app: e.target.value })} className="w-56">
                        <option value="">Todas las apps</option>
                        {applications.map((a) => (
                            <option key={a.id} value={a.id}>
                                {a.name}
                            </option>
                        ))}
                    </NativeSelect>
                    <div className="text-muted-foreground ml-auto flex flex-wrap items-center gap-3 text-xs">
                        {applications.map((a) => (
                            <span key={a.id} className="flex items-center gap-1.5">
                                <span className="size-2.5 rounded-full" style={{ backgroundColor: colorFor(a.id) }} />
                                {a.name}
                            </span>
                        ))}
                        <span className="flex items-center gap-1.5">
                            <Crosshair className="size-3.5" /> Borde punteado = aproximada por IP
                        </span>
                    </div>
                </div>

                <div className="grid gap-6 xl:grid-cols-[minmax(0,1fr)_300px]">
                    <div className="bg-card relative overflow-hidden rounded-xl border">
                        <div ref={container} className="h-[62vh] min-h-[420px] w-full" aria-label="Mapa de ubicación de los usuarios" />
                        {points.length === 0 && (
                            <div className="bg-background/70 absolute inset-0 z-[500] flex items-center justify-center">
                                <EmptyState icon={MapPinned} title="Sin ubicaciones en este periodo">
                                    Aparecerán cuando los usuarios inicien sesión en las apps.
                                </EmptyState>
                            </div>
                        )}
                    </div>

                    <div className="space-y-6">
                        <Panel title="Por ciudad">
                            {byCity.length === 0 ? (
                                <p className="text-muted-foreground text-sm">Sin datos.</p>
                            ) : (
                                <ul className="space-y-2 text-sm">
                                    {byCity.map((c) => (
                                        <li key={c.city} className="flex items-center gap-2">
                                            <span className="flex-1 truncate">{c.city}</span>
                                            <span className="bg-muted h-1.5 w-20 overflow-hidden rounded-full">
                                                <span className="bg-primary block h-full rounded-full" style={{ width: `${(c.users / byCity[0].users) * 100}%` }} />
                                            </span>
                                            <span className="w-6 text-right tabular-nums">{c.users}</span>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </Panel>

                        <Panel title="Últimas ubicaciones">
                            <ul className="max-h-80 space-y-3 overflow-y-auto text-sm">
                                {points.slice(0, 30).map((p) => (
                                    <li key={p.id} className="flex items-start gap-2">
                                        <span
                                            className="mt-1.5 size-2.5 shrink-0 rounded-full"
                                            style={{ backgroundColor: colorFor(p.application?.id), opacity: p.source === 'gps' ? 1 : 0.5 }}
                                        />
                                        <span className="min-w-0">
                                            <span className="block truncate font-medium">{p.user_name ?? `Usuario ${p.user_id}`}</span>
                                            <span className="text-muted-foreground block truncate text-xs">
                                                {p.place} · {p.source === 'gps' ? 'dispositivo' : 'IP'} · {formatRelative(p.recorded_at)}
                                            </span>
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        </Panel>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
