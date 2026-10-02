export function BrandMark({ className = 'size-8' }: { className?: string }) {
    return (
        <div className={`relative flex shrink-0 items-center justify-center rounded-lg bg-white p-1 shadow-sm ring-1 ring-black/5 ${className}`}>
            <img src="/image/icono-square.png" alt="CD Pasto" className="size-full object-contain" />
        </div>
    );
}

export default function AppLogo() {
    return (
        <>
            <BrandMark />
            <div className="ml-1 grid flex-1 text-left text-sm leading-tight">
                <span className="truncate font-semibold tracking-wide text-white">
                    Nexus <span className="text-brand-gradient">·</span>
                </span>
                <span className="text-sidebar-foreground/70 truncate text-[11px]">Centro de control CD Pasto</span>
            </div>
        </>
    );
}
