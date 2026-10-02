import HeadingSmall from '@/components/heading-small';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { formatDateTime } from '@/lib/format';
import { Head, useForm } from '@inertiajs/react';
import { ShieldCheck } from 'lucide-react';
import { type FormEventHandler } from 'react';

interface Props {
    enabled: boolean;
    confirmedAt: string | null;
    recoveryCodesLeft: number;
    recoveryCodes: string[] | null;
}

export default function TwoFactorSettings({ enabled, confirmedAt, recoveryCodesLeft, recoveryCodes }: Props) {
    const codes = useForm({ password: '' });
    const resetForm = useForm({ password: '' });

    const regenerate: FormEventHandler = (e) => {
        e.preventDefault();
        codes.post('/settings/two-factor/recovery-codes', { preserveScroll: true, onFinish: () => codes.reset() });
    };

    const reset: FormEventHandler = (e) => {
        e.preventDefault();
        if (confirm('Deberás escanear un nuevo código QR. ¿Continuar?')) {
            resetForm.delete('/settings/two-factor');
        }
    };

    return (
        <AppLayout breadcrumbs={[{ title: 'Doble factor', href: '/settings/two-factor' }]}>
            <Head title="Doble factor" />
            <SettingsLayout>
                <div className="space-y-8">
                    <div className="space-y-3">
                        <HeadingSmall title="Verificación en dos pasos" description="Protege el acceso al panel con un código de tu teléfono." />
                        <p className="inline-flex items-center gap-2 text-sm">
                            <ShieldCheck className="size-4" style={{ color: 'var(--status-good)' }} />
                            {enabled ? `Activo desde ${formatDateTime(confirmedAt)}` : 'Inactivo'}
                        </p>
                    </div>

                    <div className="space-y-3">
                        <HeadingSmall title="Códigos de recuperación" description={`Te quedan ${recoveryCodesLeft} códigos sin usar.`} />
                        {recoveryCodes && (
                            <div className="bg-muted grid max-w-sm grid-cols-2 gap-2 rounded-lg p-4 font-mono text-sm">
                                {recoveryCodes.map((c) => (
                                    <span key={c}>{c}</span>
                                ))}
                            </div>
                        )}
                        <form onSubmit={regenerate} className="flex max-w-sm gap-2">
                            <Input
                                type="password"
                                placeholder="Tu contraseña"
                                value={codes.data.password}
                                onChange={(e) => codes.setData('password', e.target.value)}
                            />
                            <Button type="submit" variant="outline" disabled={codes.processing}>
                                Generar nuevos
                            </Button>
                        </form>
                        <InputError message={codes.errors.password} />
                    </div>

                    <div className="space-y-3">
                        <HeadingSmall title="Cambiar de teléfono" description="Borra la configuración actual y vuelve a escanear un código QR." />
                        <form onSubmit={reset} className="flex max-w-sm gap-2">
                            <Input
                                type="password"
                                placeholder="Tu contraseña"
                                value={resetForm.data.password}
                                onChange={(e) => resetForm.setData('password', e.target.value)}
                            />
                            <Button type="submit" variant="destructive" disabled={resetForm.processing}>
                                Reconfigurar
                            </Button>
                        </form>
                        <InputError message={resetForm.errors.password} />
                    </div>
                </div>
            </SettingsLayout>
        </AppLayout>
    );
}
