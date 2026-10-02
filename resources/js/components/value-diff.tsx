function show(value: unknown): string {
    if (value === null || value === undefined) return '∅';
    if (typeof value === 'object') return JSON.stringify(value);

    return String(value);
}

/**
 * Valores anteriores vs. nuevos de una edición (HU-17).
 */
export function ValueDiff({ oldValues, newValues }: { oldValues: Record<string, unknown> | null; newValues: Record<string, unknown> | null }) {
    const fields = Array.from(new Set([...Object.keys(oldValues ?? {}), ...Object.keys(newValues ?? {})]));

    if (fields.length === 0) {
        return <p className="text-muted-foreground text-xs">Sin detalle de valores.</p>;
    }

    return (
        <div className="overflow-x-auto rounded-md border">
            <table className="w-full text-xs">
                <thead className="bg-muted/50">
                    <tr className="text-muted-foreground text-left">
                        <th className="px-3 py-1.5 font-medium">Campo</th>
                        <th className="px-3 py-1.5 font-medium">Antes</th>
                        <th className="px-3 py-1.5 font-medium">Después</th>
                    </tr>
                </thead>
                <tbody className="font-mono">
                    {fields.map((field) => {
                        const hasOld = oldValues !== null && field in oldValues;
                        const hasNew = newValues !== null && field in newValues;

                        return (
                            <tr key={field} className="border-t align-top">
                                <td className="px-3 py-1.5 font-sans font-medium">{field}</td>
                                <td className="px-3 py-1.5 break-all text-red-700 dark:text-red-400">
                                    {hasOld ? <del>{show(oldValues[field])}</del> : '—'}
                                </td>
                                <td className="px-3 py-1.5 break-all text-green-800 dark:text-green-400">{hasNew ? show(newValues[field]) : '—'}</td>
                            </tr>
                        );
                    })}
                </tbody>
            </table>
        </div>
    );
}
