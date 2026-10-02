import { BrandMark } from '@/components/app-logo';
import BlurText from '@/components/reactbits/BlurText';
import LightRays from '@/components/reactbits/LightRays';
import Magnet from '@/components/reactbits/Magnet';
import RotatingText from '@/components/reactbits/RotatingText';
import ShinyText from '@/components/reactbits/ShinyText';
import SpotlightCard from '@/components/reactbits/SpotlightCard';
import StarBorder from '@/components/reactbits/StarBorder';
import { statusColor, statusLabel } from '@/components/status-badge';
import { AnimatedNumber } from '@/components/viz';
import { formatMs, formatRelative } from '@/lib/format';
import { cn } from '@/lib/utils';
import { type AppStatus } from '@/types';
import { Head, Link, router, usePoll } from '@inertiajs/react';
import { Activity, ArrowRight, BellRing, Database, Fingerprint, Gauge, Radio, ScrollText, ShieldCheck, Sparkles, UsersRound } from 'lucide-react';

interface System {
    name: string;
    status: AppStatus;
    uptime_30d: number | null;
    response_ms: number | null;
    daily: { date: string; uptime: number }[];
}

interface Props {
    systems: System[];
    overall: AppStatus;
    uptime_avg: number | null;
    incidents_30d: number;
    checked_at: string | null;
    isAuthenticated: boolean;
}

const overallCopy: Record<string, { title: string; tone: string }> = {
    online: { title: 'Todos los sistemas operan con normalidad', tone: 'var(--status-good)' },
    degraded: { title: 'Algunos sistemas presentan lentitud', tone: 'var(--status-warning)' },
    down: { title: 'Hay sistemas fuera de servicio', tone: 'var(--status-critical)' },
    unknown: { title: 'Recopilando el estado de los sistemas', tone: 'var(--status-unknown)' },
};

const features = [
    { icon: Gauge, title: 'Salud en tiempo real', text: 'Verificación cada minuto, puntaje de salud y disponibilidad diaria de cada sistema.' },
    { icon: ScrollText, title: 'Auditoría completa', text: 'Quién creó, editó, eliminó, subió o descargó qué, con valores antes y después.' },
    { icon: UsersRound, title: 'Usuarios bajo control', text: 'Conectados ahora, historial de accesos, cierre de sesión y bloqueo remotos.' },
    { icon: BellRing, title: 'Alertas inmediatas', text: 'Caídas, picos de errores y actividad sospechosa por correo y WhatsApp.' },
    { icon: Database, title: 'Infraestructura Railway', text: 'Despliegues, logs, CPU, memoria, costos y bases de datos en un solo lugar.' },
    { icon: Fingerprint, title: 'Acceso blindado', text: 'Doble factor obligatorio, roles por sistema y trazabilidad de cada acción del panel.' },
];

function uptimeTone(value: number): string {
    if (value >= 99.5) return 'var(--status-good)';
    if (value >= 95) return 'var(--status-warning)';

    return 'var(--status-critical)';
}

export default function Welcome({ systems, overall, uptime_avg, incidents_30d, checked_at, isAuthenticated }: Props) {
    usePoll(60_000, {}, { keepAlive: true });
    const status = overallCopy[overall] ?? overallCopy.unknown;
    const cta = isAuthenticated ? { href: route('dashboard'), label: 'Ir al panel' } : { href: route('login'), label: 'Ingresar al panel' };

    return (
        <>
            <Head title="Centro de control">
                <meta name="description" content="Nexus: monitoreo, auditoría y control de los sistemas de CD Pasto." />
            </Head>
            <div className="dark bg-background text-foreground min-h-screen overflow-x-hidden">
                {/* ---------- HERO ---------- */}
                <section className="relative isolate overflow-hidden">
                    <div className="absolute inset-0 -z-20 bg-[radial-gradient(ellipse_80%_60%_at_50%_-10%,hsl(226_80%_25%/.55),transparent_70%)]" />
                    <div className="bg-grid absolute inset-0 -z-20 [mask-image:radial-gradient(ellipse_70%_60%_at_50%_30%,black,transparent)]" />
                    <div className="absolute inset-0 -z-10 opacity-80">
                        <LightRays
                            raysOrigin="top-center"
                            raysColor="#ffd27a"
                            raysSpeed={0.9}
                            lightSpread={0.9}
                            rayLength={1.6}
                            followMouse
                            mouseInfluence={0.08}
                            noiseAmount={0.06}
                            distortion={0.04}
                        />
                    </div>

                    <header className="mx-auto flex w-full max-w-7xl items-center justify-between px-5 py-5 sm:px-8">
                        <div className="flex items-center gap-3">
                            <BrandMark className="size-9" />
                            <div className="leading-tight">
                                <p className="font-semibold tracking-wide">Nexus</p>
                                <p className="text-muted-foreground text-xs">Centro de control · CD Pasto</p>
                            </div>
                        </div>
                        <nav className="flex items-center gap-2 sm:gap-4">
                            <a href="#estado" className="text-muted-foreground hover:text-foreground hidden text-sm sm:inline">
                                Estado
                            </a>
                            <a href="#capacidades" className="text-muted-foreground hover:text-foreground hidden text-sm sm:inline">
                                Capacidades
                            </a>
                            <Link
                                href={cta.href}
                                className="bg-primary text-primary-foreground hover:bg-primary/90 inline-flex h-9 items-center gap-1.5 rounded-full px-4 text-sm font-medium transition-colors"
                            >
                                {cta.label} <ArrowRight className="size-4" />
                            </Link>
                        </nav>
                    </header>

                    <div className="mx-auto flex max-w-5xl flex-col items-center px-5 pt-14 pb-20 text-center sm:px-8 sm:pt-20 sm:pb-28">
                        <a
                            href="#estado"
                            className="glass mb-8 inline-flex items-center gap-2 rounded-full border px-4 py-1.5 text-xs font-medium sm:text-sm"
                        >
                            <span className="relative flex size-2">
                                <span
                                    className="absolute inline-flex size-full animate-ping rounded-full opacity-70"
                                    style={{ background: status.tone }}
                                />
                                <span className="relative inline-flex size-2 rounded-full" style={{ background: status.tone }} />
                            </span>
                            <ShinyText text={status.title} speed={3} color="#c9d3ea" shineColor="#ffffff" />
                        </a>

                        <h1 className="text-4xl leading-[1.05] font-semibold tracking-tight sm:text-6xl lg:text-7xl">
                            <BlurText text="El pulso de todos" delay={90} animateBy="words" direction="top" className="justify-center" />
                            <span className="mt-1 flex flex-wrap items-center justify-center gap-x-3">
                                <span className="text-brand-gradient">nuestros sistemas</span>
                            </span>
                        </h1>

                        <div className="text-muted-foreground mt-7 flex flex-wrap items-center justify-center gap-2 text-lg sm:text-xl">
                            <span>Monitoreados, auditados y</span>
                            <RotatingText
                                texts={['bajo control', 'en tiempo real', 'seguros', 'siempre en línea']}
                                mainClassName="overflow-hidden rounded-lg bg-primary/15 px-2.5 py-0.5 text-foreground ring-1 ring-primary/30"
                                staggerFrom="last"
                                initial={{ y: '100%' }}
                                animate={{ y: 0 }}
                                exit={{ y: '-120%' }}
                                staggerDuration={0.02}
                                splitLevelClassName="overflow-hidden pb-0.5"
                                transition={{ type: 'spring', damping: 30, stiffness: 400 }}
                                rotationInterval={2600}
                            />
                        </div>

                        <p className="text-muted-foreground mt-6 max-w-2xl text-base sm:text-lg">
                            Nexus reúne en un solo panel la salud, los usuarios, la auditoría y la infraestructura de los sistemas que mueven la
                            operación de Bavaria, Adenar y Easy Logística.
                        </p>

                        <div className="mt-10 flex flex-col items-center gap-4 sm:flex-row">
                            <Magnet padding={60} magnetStrength={4}>
                                <StarBorder
                                    as="button"
                                    type="button"
                                    onClick={() => router.visit(cta.href)}
                                    color="#f5b301"
                                    speed="5s"
                                    backgroundColor="hsl(226 70% 46%)"
                                    borderColor="transparent"
                                    className="[&>div:last-child]:px-7 [&>div:last-child]:py-3.5 [&>div:last-child]:text-base [&>div:last-child]:font-semibold"
                                >
                                    <span className="inline-flex items-center gap-2">
                                        {cta.label} <ArrowRight className="size-4" />
                                    </span>
                                </StarBorder>
                            </Magnet>
                            <a
                                href="#estado"
                                className="text-muted-foreground hover:text-foreground inline-flex items-center gap-2 text-sm font-medium"
                            >
                                <Radio className="size-4" /> Ver estado en vivo
                            </a>
                        </div>
                    </div>

                    {/* KPIs */}
                    <div className="mx-auto grid max-w-5xl grid-cols-2 gap-px overflow-hidden rounded-2xl border bg-[var(--border)] px-0 sm:grid-cols-4">
                        <Kpi label="Sistemas monitoreados" value={<AnimatedNumber value={systems.length} />} />
                        <Kpi label="Disponibilidad (30 días)" value={<AnimatedNumber value={uptime_avg} decimals={2} suffix="%" />} />
                        <Kpi label="Incidentes (30 días)" value={<AnimatedNumber value={incidents_30d} />} />
                        <Kpi label="Verificación" value={<span className="text-2xl">cada 60 s</span>} />
                    </div>
                    <div className="h-20" />
                </section>

                {/* ---------- ESTADO ---------- */}
                <section id="estado" className="mx-auto max-w-7xl scroll-mt-10 px-5 py-16 sm:px-8">
                    <div className="mb-8 flex flex-col justify-between gap-3 sm:flex-row sm:items-end">
                        <div>
                            <p className="text-primary text-sm font-semibold tracking-wider uppercase">Estado en vivo</p>
                            <h2 className="mt-1 text-3xl font-semibold tracking-tight">Sistemas en producción</h2>
                        </div>
                        <p className="text-muted-foreground text-sm">Última verificación {formatRelative(checked_at)} · se actualiza solo</p>
                    </div>

                    <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                        {systems.map((system) => (
                            <SpotlightCard key={system.name} className="p-5" spotlightColor="rgba(91, 127, 255, 0.18)">
                                <div className="flex items-start justify-between gap-3">
                                    <div>
                                        <h3 className="font-semibold">{system.name}</h3>
                                        <p className="mt-1 inline-flex items-center gap-1.5 text-sm" style={{ color: statusColor(system.status) }}>
                                            <span className="size-2 rounded-full" style={{ background: statusColor(system.status) }} />
                                            <span className="text-foreground">{statusLabel(system.status)}</span>
                                        </p>
                                    </div>
                                    <div className="text-right">
                                        <p className="text-2xl font-semibold tabular-nums">
                                            {system.uptime_30d !== null ? `${system.uptime_30d.toFixed(2)}%` : '—'}
                                        </p>
                                        <p className="text-muted-foreground text-xs">disponibilidad 30 d</p>
                                    </div>
                                </div>
                                <UptimeBars daily={system.daily} />
                                <p className="text-muted-foreground mt-3 flex justify-between text-xs">
                                    <span>Hace 30 días</span>
                                    <span>Respuesta {formatMs(system.response_ms)}</span>
                                    <span>Hoy</span>
                                </p>
                            </SpotlightCard>
                        ))}
                    </div>
                </section>

                {/* ---------- CAPACIDADES ---------- */}
                <section id="capacidades" className="relative scroll-mt-10 border-y bg-[hsl(224_48%_4%)]">
                    <div className="mx-auto max-w-7xl px-5 py-20 sm:px-8">
                        <div className="mx-auto max-w-2xl text-center">
                            <p className="text-primary inline-flex items-center gap-2 text-sm font-semibold tracking-wider uppercase">
                                <Sparkles className="size-4" /> Capacidades
                            </p>
                            <h2 className="mt-2 text-3xl font-semibold tracking-tight sm:text-4xl">
                                Todo lo que pasa en producción, en un solo lugar
                            </h2>
                            <p className="text-muted-foreground mt-4">
                                Un agente liviano dentro de cada sistema reporta a Nexus; Nexus vigila, alerta y permite actuar sin entrar a cada
                                aplicación.
                            </p>
                        </div>

                        <div className="mt-14 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                            {features.map((feature) => (
                                <SpotlightCard key={feature.title} className="p-6" spotlightColor="rgba(245, 179, 1, 0.12)">
                                    <div className="bg-primary/15 text-primary ring-primary/25 mb-4 inline-flex size-11 items-center justify-center rounded-xl ring-1">
                                        <feature.icon className="size-5" />
                                    </div>
                                    <h3 className="font-semibold">{feature.title}</h3>
                                    <p className="text-muted-foreground mt-2 text-sm leading-relaxed">{feature.text}</p>
                                </SpotlightCard>
                            ))}
                        </div>
                    </div>
                </section>

                {/* ---------- MARCAS ---------- */}
                <section className="mx-auto max-w-7xl px-5 py-20 sm:px-8">
                    <p className="text-muted-foreground mb-6 text-center text-sm font-medium tracking-wider uppercase">
                        Al servicio de la operación de
                    </p>
                    <div className="mx-auto max-w-5xl overflow-hidden rounded-2xl bg-white p-4 shadow-2xl ring-1 shadow-black/40 ring-white/10 sm:p-6">
                        <img
                            src="/image/banner-cd-narino.webp"
                            alt="Bavaria, Adenar S.A.S. y Easy Logística"
                            className="h-auto w-full"
                            width={1920}
                            height={500}
                        />
                    </div>
                </section>

                {/* ---------- CTA FINAL ---------- */}
                <section className="mx-auto max-w-7xl px-5 pb-20 sm:px-8">
                    <div className="relative overflow-hidden rounded-3xl border bg-[linear-gradient(120deg,hsl(226_70%_22%),hsl(224_48%_8%)_55%)] px-6 py-14 text-center sm:px-12">
                        <div className="absolute -top-24 -right-24 size-72 rounded-full bg-[radial-gradient(circle,rgba(245,179,1,.35),transparent_70%)]" />
                        <div className="absolute -bottom-24 -left-24 size-72 rounded-full bg-[radial-gradient(circle,rgba(227,0,27,.3),transparent_70%)]" />
                        <ShieldCheck className="text-primary mx-auto mb-4 size-10" />
                        <h2 className="relative text-2xl font-semibold sm:text-3xl">Acceso exclusivo para jefes y encargados</h2>
                        <p className="text-muted-foreground relative mx-auto mt-3 max-w-xl">
                            Cada responsable ve solo sus sistemas. El acceso requiere doble factor y todo queda registrado.
                        </p>
                        <Link
                            href={cta.href}
                            className="relative mt-8 inline-flex h-11 items-center gap-2 rounded-full bg-white px-6 text-sm font-semibold text-[hsl(222_52%_9%)] transition-transform hover:scale-[1.03]"
                        >
                            {cta.label} <ArrowRight className="size-4" />
                        </Link>
                    </div>
                </section>

                <footer className="border-t">
                    <div className="text-muted-foreground mx-auto flex max-w-7xl flex-col items-center justify-between gap-3 px-5 py-8 text-sm sm:flex-row sm:px-8">
                        <div className="flex items-center gap-2">
                            <BrandMark className="size-6" />
                            <span>Nexus · CD Pasto © {new Date().getFullYear()}</span>
                        </div>
                        <span className="inline-flex items-center gap-1.5">
                            <Activity className="size-4" /> Monitoreo continuo de los sistemas en producción
                        </span>
                    </div>
                </footer>
            </div>
        </>
    );
}

function Kpi({ label, value }: { label: string; value: React.ReactNode }) {
    return (
        <div className="bg-background/80 px-5 py-6 text-center backdrop-blur">
            <div className="text-3xl font-semibold tracking-tight">{value}</div>
            <div className="text-muted-foreground mt-1 text-xs sm:text-sm">{label}</div>
        </div>
    );
}

function UptimeBars({ daily }: { daily: { date: string; uptime: number }[] }) {
    const byDate = new Map(daily.map((d) => [d.date, d.uptime]));
    const today = new Date();
    const cells = Array.from({ length: 30 }, (_, i) => {
        const d = new Date(today.getFullYear(), today.getMonth(), today.getDate() - (29 - i));
        const key = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;

        return { key, uptime: byDate.get(key) };
    });

    return (
        <div className="mt-5 flex h-8 items-stretch gap-[3px]">
            {cells.map((cell) => (
                <div
                    key={cell.key}
                    title={cell.uptime !== undefined ? `${cell.key}: ${cell.uptime.toFixed(2)}%` : `${cell.key}: sin datos`}
                    className={cn('flex-1 rounded-[3px]', cell.uptime === undefined && 'bg-muted')}
                    style={cell.uptime !== undefined ? { background: uptimeTone(cell.uptime) } : undefined}
                />
            ))}
        </div>
    );
}
