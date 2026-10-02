import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { Head, Link } from '@inertiajs/react';
import { ShieldOff } from 'lucide-react';

export default function Forbidden() {
    return (
        <AppLayout>
            <Head title="Sin acceso" />
            <div className="flex flex-1 flex-col items-center justify-center gap-3 p-10 text-center">
                <ShieldOff className="text-muted-foreground size-10" />
                <h1 className="text-lg font-semibold">No tienes acceso a esta sección</h1>
                <p className="text-muted-foreground max-w-sm text-sm">
                    Si necesitas ver esta información, pide al superadministrador que te asigne la aplicación o el rol correspondiente.
                </p>
                <Button asChild variant="outline">
                    <Link href="/dashboard">Ir al resumen</Link>
                </Button>
            </div>
        </AppLayout>
    );
}
