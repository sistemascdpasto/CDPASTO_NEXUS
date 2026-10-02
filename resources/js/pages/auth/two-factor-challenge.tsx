import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthLayout from '@/layouts/auth-layout';
import { Head, Link, useForm } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import { type FormEventHandler, useState } from 'react';

export default function TwoFactorChallenge() {
    const [useRecovery, setUseRecovery] = useState(false);
    const { data, setData, post, processing, errors, reset } = useForm({ code: '', recovery_code: '' });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post('/two-factor-challenge', { onFinish: () => reset() });
    };

    return (
        <AuthLayout
            title="Verificación en dos pasos"
            description={useRecovery ? 'Ingresa uno de tus códigos de recuperación.' : 'Ingresa el código de 6 dígitos de tu app de autenticación.'}
        >
            <Head title="Verificación en dos pasos" />
            <form onSubmit={submit} className="grid gap-6">
                {useRecovery ? (
                    <div className="grid gap-2">
                        <Label htmlFor="recovery_code">Código de recuperación</Label>
                        <Input
                            id="recovery_code"
                            value={data.recovery_code}
                            onChange={(e) => setData('recovery_code', e.target.value)}
                            autoComplete="one-time-code"
                            autoFocus
                            placeholder="abcde-fghij"
                        />
                        <InputError message={errors.recovery_code} />
                    </div>
                ) : (
                    <div className="grid gap-2">
                        <Label htmlFor="code">Código</Label>
                        <Input
                            id="code"
                            value={data.code}
                            onChange={(e) => setData('code', e.target.value.replace(/\D/g, '').slice(0, 6))}
                            inputMode="numeric"
                            autoComplete="one-time-code"
                            autoFocus
                            placeholder="000000"
                            className="text-center font-mono text-2xl tracking-[0.5em]"
                        />
                        <InputError message={errors.code} />
                    </div>
                )}

                <Button type="submit" className="w-full" disabled={processing}>
                    {processing && <LoaderCircle className="animate-spin" />}
                    Verificar
                </Button>

                <div className="text-muted-foreground flex justify-between text-sm">
                    <button type="button" className="hover:text-foreground underline underline-offset-4" onClick={() => setUseRecovery(!useRecovery)}>
                        {useRecovery ? 'Usar código de la app' : 'Usar código de recuperación'}
                    </button>
                    <Link href={route('login')} className="hover:text-foreground">
                        Volver
                    </Link>
                </div>
            </form>
        </AuthLayout>
    );
}
