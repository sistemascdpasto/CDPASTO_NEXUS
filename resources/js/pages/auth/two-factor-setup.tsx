import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthLayout from '@/layouts/auth-layout';
import { Head, Link, useForm } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import { type FormEventHandler } from 'react';

interface Props {
    qrSvg: string | null;
    secret: string | null;
    recoveryCodes: string[] | null;
}

export default function TwoFactorSetup({ qrSvg, secret, recoveryCodes }: Props) {
    const { data, setData, post, processing, errors } = useForm({ code: '' });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post('/two-factor/confirm');
    };

    if (recoveryCodes) {
        return (
            <AuthLayout
                title="Doble factor activado"
                description="Guarda estos códigos de recuperación en un lugar seguro. Cada uno sirve una sola vez si pierdes el teléfono."
            >
                <Head title="Códigos de recuperación" />
                <div className="bg-muted grid grid-cols-2 gap-2 rounded-lg p-4 font-mono text-sm">
                    {recoveryCodes.map((code) => (
                        <span key={code}>{code}</span>
                    ))}
                </div>
                <div className="flex gap-2">
                    <Button variant="outline" className="flex-1" onClick={() => navigator.clipboard.writeText(recoveryCodes.join('\n'))}>
                        Copiar
                    </Button>
                    <Button asChild className="flex-1">
                        <Link href={route('dashboard')}>Ya los guardé, continuar</Link>
                    </Button>
                </div>
            </AuthLayout>
        );
    }

    return (
        <AuthLayout
            title="Configura la verificación en dos pasos"
            description="Nexus concentra el control de todos los sistemas, por eso el doble factor es obligatorio."
        >
            <Head title="Configurar doble factor" />
            <ol className="text-muted-foreground list-decimal space-y-1 pl-5 text-sm">
                <li>Instala Google Authenticator, Microsoft Authenticator o similar.</li>
                <li>Escanea el código QR (o ingresa la clave manualmente).</li>
                <li>Escribe el código de 6 dígitos que muestra la app.</li>
            </ol>
            {qrSvg && <div className="mx-auto w-fit rounded-lg bg-white p-3" dangerouslySetInnerHTML={{ __html: qrSvg }} />}
            {secret && (
                <p className="text-muted-foreground text-center text-xs">
                    Clave manual: <span className="text-foreground font-mono break-all select-all">{secret}</span>
                </p>
            )}
            <form onSubmit={submit} className="grid gap-4">
                <div className="grid gap-2">
                    <Label htmlFor="code">Código de verificación</Label>
                    <Input
                        id="code"
                        value={data.code}
                        onChange={(e) => setData('code', e.target.value.replace(/\D/g, '').slice(0, 6))}
                        inputMode="numeric"
                        autoComplete="one-time-code"
                        autoFocus
                        placeholder="000000"
                        className="text-center font-mono text-xl tracking-[0.4em]"
                    />
                    <InputError message={errors.code} />
                </div>
                <Button type="submit" disabled={processing || data.code.length !== 6}>
                    {processing && <LoaderCircle className="animate-spin" />}
                    Activar doble factor
                </Button>
                <Link href={route('logout')} method="post" as="button" className="text-muted-foreground hover:text-foreground text-sm">
                    Cerrar sesión
                </Link>
            </form>
        </AuthLayout>
    );
}
