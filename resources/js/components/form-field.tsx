import InputError from '@/components/input-error';
import { Label } from '@/components/ui/label';
import { type ReactNode } from 'react';

export function Field({
    label,
    error,
    hint,
    className,
    children,
}: {
    label: string;
    error?: string;
    hint?: string;
    className?: string;
    children: ReactNode;
}) {
    return (
        <div className={`grid content-start gap-1.5 ${className ?? ''}`}>
            <Label>{label}</Label>
            {children}
            {hint && !error && <p className="text-muted-foreground text-xs">{hint}</p>}
            <InputError message={error} />
        </div>
    );
}
