import { BrandMark } from '@/components/app-logo';
import ShinyText from '@/components/reactbits/ShinyText';
import { Link } from '@inertiajs/react';
import { Fingerprint, Gauge, ScrollText } from 'lucide-react';

interface AuthLayoutProps {
    children: React.ReactNode;
    name?: string;
    title?: string;
    description?: string;
}

/**
 * Acceso con identidad de marca: panel oscuro de "centro de control" a la izquierda y formulario a la derecha.
 */
export default function AuthSimpleLayout({ children, title, description }: AuthLayoutProps) {
    return (
        <div className="bg-background grid min-h-svh lg:grid-cols-[1.05fr_1fr]">
            <aside className="dark bg-background text-foreground relative hidden overflow-hidden lg:flex lg:flex-col lg:justify-between lg:p-12">
                <div className="absolute inset-0 bg-[radial-gradient(ellipse_90%_70%_at_20%_0%,hsl(226_80%_28%/.6),transparent_65%)]" />
                <div className="bg-grid absolute inset-0 [mask-image:radial-gradient(ellipse_80%_70%_at_30%_30%,black,transparent)]" />
                <div className="absolute -right-32 -bottom-32 size-96 rounded-full bg-[radial-gradient(circle,rgba(245,179,1,.22),transparent_70%)]" />

                <Link href={route('home')} className="relative flex items-center gap-3">
                    <BrandMark className="size-10" />
                    <div className="leading-tight">
                        <p className="font-semibold tracking-wide">Nexus</p>
                        <p className="text-muted-foreground text-xs">Centro de control · CD Pasto</p>
                    </div>
                </Link>

                <div className="relative max-w-md">
                    <ShinyText
                        text="Monitoreo · Auditoría · Control"
                        className="text-sm font-medium tracking-wider uppercase"
                        color="#8fa3d9"
                        shineColor="#ffffff"
                    />
                    <h2 className="mt-4 text-4xl leading-tight font-semibold tracking-tight">
                        Cada sistema de producción, <span className="text-brand-gradient">bajo tu mirada.</span>
                    </h2>
                    <ul className="text-muted-foreground mt-8 space-y-4 text-sm">
                        <li className="flex gap-3">
                            <Gauge className="text-primary size-5 shrink-0" /> Salud, despliegues y recursos de Railway en tiempo real.
                        </li>
                        <li className="flex gap-3">
                            <ScrollText className="text-primary size-5 shrink-0" /> Auditoría de lo que cada usuario crea, edita, sube o elimina.
                        </li>
                        <li className="flex gap-3">
                            <Fingerprint className="text-primary size-5 shrink-0" /> Acceso con doble factor y trazabilidad de cada acción.
                        </li>
                    </ul>
                </div>

                <div className="relative overflow-hidden rounded-xl bg-white p-3 shadow-2xl shadow-black/50">
                    <img
                        src="/image/banner-cd-narino.webp"
                        alt="Bavaria, Adenar y Easy Logística"
                        className="h-auto w-full"
                        width={1920}
                        height={500}
                    />
                </div>
            </aside>

            <main className="flex flex-col items-center justify-center p-6 md:p-10">
                <div className="w-full max-w-sm">
                    <div className="flex flex-col gap-8">
                        <div className="flex flex-col items-center gap-4 lg:items-start">
                            <Link href={route('home')} className="lg:hidden">
                                <BrandMark className="size-11" />
                            </Link>
                            <div className="space-y-1.5 text-center lg:text-left">
                                <h1 className="text-2xl font-semibold tracking-tight">{title}</h1>
                                <p className="text-muted-foreground text-sm">{description}</p>
                            </div>
                        </div>
                        {children}
                    </div>
                </div>
            </main>
        </div>
    );
}
