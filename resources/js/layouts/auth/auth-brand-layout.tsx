import { Link } from '@inertiajs/react';
import { ShieldAlert, Sparkles, Tags } from 'lucide-react';
import { home } from '@/routes';
import type { AuthLayoutProps } from '@/types';

const features = [
    {
        icon: Sparkles,
        title: 'Análisis con IA',
        description: 'Lee el PDF de tu banco y lo entiende por ti.',
    },
    {
        icon: Tags,
        title: 'Categorización automática',
        description: 'Cada movimiento clasificado sin mover un dedo.',
    },
    {
        icon: ShieldAlert,
        title: 'Detección de anomalías',
        description: 'Te avisamos de cobros raros o duplicados.',
    },
];

/**
 * Split-screen authentication layout: a dark brand panel with a subtle green
 * glow and the product value proposition on the left, and the actual form on
 * the right. Shared by login, register and password recovery screens.
 */
export default function AuthBrandLayout({
    children,
    title,
    description,
}: AuthLayoutProps) {
    return (
        <div className="relative grid min-h-svh lg:grid-cols-2">
            {/* Brand panel (desktop only) */}
            <div className="relative hidden flex-col justify-between overflow-hidden bg-zinc-950 p-10 text-white lg:flex xl:p-14">
                <div
                    aria-hidden
                    className="pointer-events-none absolute inset-0"
                    style={{
                        background:
                            'radial-gradient(120% 80% at 15% 10%, color-mix(in oklab, var(--brand) 22%, transparent), transparent 55%)',
                    }}
                />
                <div
                    aria-hidden
                    className="pointer-events-none absolute -right-24 -bottom-32 size-96 rounded-full opacity-20 blur-3xl"
                    style={{ background: 'var(--brand)' }}
                />

                <Link
                    href={home()}
                    className="relative z-10 flex items-center gap-2.5"
                >
                    <img
                        src="/logo.png"
                        alt="Al centavo"
                        className="size-8 rounded-md object-contain"
                    />
                    <span className="text-lg font-semibold tracking-tight">
                        Al centavo
                    </span>
                </Link>

                <div className="relative z-10 space-y-9">
                    <div className="space-y-3">
                        <h2 className="max-w-md text-3xl font-semibold tracking-tight text-balance xl:text-4xl">
                            Tus finanzas, al centavo.
                        </h2>
                        <p className="max-w-md text-white/60">
                            Sube el estado de cuenta de tu banco y obtén
                            claridad total sobre a dónde va tu dinero.
                        </p>
                    </div>

                    <ul className="space-y-5">
                        {features.map((feature) => (
                            <li
                                key={feature.title}
                                className="flex items-start gap-3.5"
                            >
                                <span className="mt-0.5 flex size-9 shrink-0 items-center justify-center rounded-lg bg-white/5 ring-1 ring-white/10">
                                    <feature.icon className="size-4 text-brand" />
                                </span>
                                <div className="space-y-0.5">
                                    <p className="font-medium">
                                        {feature.title}
                                    </p>
                                    <p className="text-sm text-white/50">
                                        {feature.description}
                                    </p>
                                </div>
                            </li>
                        ))}
                    </ul>
                </div>

                <p className="relative z-10 text-xs text-white/40">
                    Powered by SynapticSpark
                </p>
            </div>

            {/* Form panel */}
            <div className="flex flex-col items-center justify-center px-6 py-10 sm:px-8">
                <div className="w-full max-w-sm">
                    <Link
                        href={home()}
                        className="mb-8 flex items-center justify-center gap-2 lg:hidden"
                    >
                        <img
                            src="/logo.png"
                            alt="Al centavo"
                            className="size-9 rounded-md object-contain"
                        />
                        <span className="text-lg font-semibold tracking-tight">
                            Al centavo
                        </span>
                    </Link>

                    <div className="mb-8 flex flex-col gap-2 text-center lg:text-left">
                        <h1 className="text-2xl font-semibold tracking-tight">
                            {title}
                        </h1>
                        <p className="text-sm text-balance text-muted-foreground">
                            {description}
                        </p>
                    </div>

                    {children}
                </div>
            </div>
        </div>
    );
}
