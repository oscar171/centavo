import { Head, Link, usePage } from '@inertiajs/react';
import {
    AlertTriangle,
    ArrowRight,
    FileText,
    Landmark,
    LineChart,
    Scale,
    ShieldAlert,
    Sparkles,
    Tags,
    Upload,
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import { dashboard, login } from '@/routes';

const FEATURES = [
    {
        icon: Sparkles,
        title: 'Extracción con IA',
        description:
            'Sube el PDF y la IA extrae cada movimiento, saldo y comercio con precisión, sin capturar nada a mano.',
    },
    {
        icon: Scale,
        title: 'Conciliación automática',
        description:
            'Verificamos que los saldos cuadren al centavo y te avisamos al instante si algo no encaja.',
    },
    {
        icon: ShieldAlert,
        title: 'Detección de anomalías',
        description:
            'Ráfagas de cargos, cobros duplicados y reversos detectados automáticamente.',
    },
    {
        icon: Tags,
        title: 'Categorización',
        description:
            'Cada movimiento se clasifica solo; ajusta la categoría con un clic cuando quieras.',
    },
    {
        icon: LineChart,
        title: 'Dashboard e insights',
        description:
            'Ingresos vs. gastos, gasto por comercio y tendencias por periodo, siempre a la mano.',
    },
    {
        icon: Landmark,
        title: 'Multi-banco',
        description:
            'Varias cuentas y bancos en un solo lugar, organizados y comparables.',
    },
];

const STEPS = [
    {
        icon: Upload,
        title: 'Sube tu estado de cuenta',
        description:
            'Arrastra el PDF de cualquier banco. Nosotros hacemos el resto.',
    },
    {
        icon: Sparkles,
        title: 'La IA lo procesa',
        description:
            'Extrae los movimientos, concilia los saldos y detecta anomalías en segundos.',
    },
    {
        icon: LineChart,
        title: 'Explora tus finanzas',
        description:
            'Movimientos, categorías, tendencias y alertas. Todo cuadrado, al centavo.',
    },
];

const BANKS = [
    'Chase',
    'Wells Fargo',
    'BBVA',
    'Bank of America',
    'Citi',
    'Santander',
];

function Wordmark() {
    return (
        <span className="flex items-center gap-2">
            <img
                src="/logo.png"
                alt="Al centavo"
                className="size-8 shrink-0 rounded-md object-contain"
            />
            <span className="text-base font-semibold tracking-tight">
                Al centavo
            </span>
        </span>
    );
}

function MockKpi({
    label,
    value,
    accent,
}: {
    label: string;
    value: string;
    accent?: 'brand';
}) {
    return (
        <div className="rounded-lg border border-border bg-background/60 p-3">
            <p className="text-[10px] tracking-wide text-muted-foreground uppercase">
                {label}
            </p>
            <p
                className={`text-lg font-semibold tabular-nums ${accent === 'brand' ? 'text-brand' : ''}`}
            >
                {value}
            </p>
        </div>
    );
}

function StatementPreview() {
    return (
        <div className="rounded-2xl border border-border bg-card p-5 shadow-sm">
            <div className="flex items-center justify-between gap-3">
                <div className="flex min-w-0 items-center gap-2">
                    <FileText className="size-4 shrink-0 text-muted-foreground" />
                    <span className="truncate text-sm font-medium">
                        chase-febrero-2025.pdf
                    </span>
                </div>
                <span className="shrink-0 rounded-md bg-brand/10 px-2 py-0.5 text-xs font-medium text-brand">
                    Conciliado
                </span>
            </div>

            <div className="mt-4 grid grid-cols-3 gap-2.5">
                <MockKpi label="Ingresos" value="$3,000" accent="brand" />
                <MockKpi label="Gastos" value="$2,500" />
                <MockKpi label="Neto" value="+$500" accent="brand" />
            </div>

            <div className="mt-3 rounded-xl border border-border p-3">
                <svg
                    viewBox="0 0 300 80"
                    className="h-20 w-full"
                    preserveAspectRatio="none"
                    aria-hidden
                >
                    <polyline
                        points="0,58 50,52 100,55 150,40 200,44 250,28 300,24"
                        fill="none"
                        stroke="var(--brand)"
                        strokeWidth="2.5"
                        strokeLinecap="round"
                        strokeLinejoin="round"
                    />
                    <polyline
                        points="0,66 50,60 100,63 150,58 200,62 250,54 300,58"
                        fill="none"
                        stroke="var(--negative)"
                        strokeWidth="2.5"
                        strokeLinecap="round"
                        strokeLinejoin="round"
                    />
                </svg>
            </div>

            <div className="mt-3 space-y-2">
                {[
                    {
                        name: 'Employer',
                        cat: 'Ingresos',
                        amount: '+$3,000',
                        up: true,
                    },
                    { name: 'Landlord', cat: 'Vivienda', amount: '−$1,500' },
                    { name: 'Whole Foods', cat: 'Comida', amount: '−$400' },
                ].map((row) => (
                    <div
                        key={row.name}
                        className="flex items-center justify-between gap-3 text-sm"
                    >
                        <span className="flex min-w-0 items-center gap-2">
                            <span className="truncate font-medium">
                                {row.name}
                            </span>
                            <span className="shrink-0 rounded-md bg-muted px-1.5 py-0.5 text-[10px] text-muted-foreground">
                                {row.cat}
                            </span>
                        </span>
                        <span
                            className={`shrink-0 tabular-nums ${row.up ? 'text-brand' : 'text-negative'}`}
                        >
                            {row.amount}
                        </span>
                    </div>
                ))}
            </div>

            <div className="mt-3 flex items-center gap-2 rounded-lg bg-amber-500/10 px-3 py-2 text-xs font-medium text-amber-600 dark:text-amber-400">
                <AlertTriangle className="size-4 shrink-0" />
                Ráfaga de 44 cargos a FanDuel detectada
            </div>
        </div>
    );
}

export default function Welcome() {
    const { auth } = usePage().props;

    const primaryHref = auth.user ? dashboard() : login();
    const primaryLabel = auth.user ? 'Ir al dashboard' : 'Comenzar';

    return (
        <>
            <Head title="Al centavo — Analiza tus estados de cuenta con IA" />

            <div className="min-h-screen bg-background text-foreground">
                {/* Nav */}
                <header className="sticky top-0 z-40 border-b border-border bg-background/80 backdrop-blur">
                    <div className="mx-auto flex h-16 w-full max-w-6xl items-center justify-between px-4 md:px-6">
                        <Wordmark />
                        <nav className="flex items-center gap-2">
                            {auth.user ? (
                                <Button asChild size="sm">
                                    <Link href={dashboard()}>Dashboard</Link>
                                </Button>
                            ) : (
                                <>
                                    <Button asChild variant="ghost" size="sm">
                                        <Link href={login()}>
                                            Iniciar sesión
                                        </Link>
                                    </Button>
                                    <Button asChild size="sm">
                                        <Link href={login()}>Comenzar</Link>
                                    </Button>
                                </>
                            )}
                        </nav>
                    </div>
                </header>

                {/* Hero */}
                <section className="relative overflow-hidden">
                    <div
                        aria-hidden
                        className="pointer-events-none absolute top-[-15%] left-1/2 -z-10 h-110 w-220 max-w-full -translate-x-1/2 rounded-full bg-brand/10 blur-3xl"
                    />
                    <div className="mx-auto grid w-full max-w-6xl items-center gap-12 px-4 py-16 md:px-6 md:py-24 lg:grid-cols-2">
                        <div className="flex flex-col items-start gap-6">
                            <span className="inline-flex items-center gap-2 rounded-full border border-border bg-card px-3 py-1 text-xs font-medium text-muted-foreground">
                                <Sparkles className="size-3.5 text-brand" />
                                Análisis de estados de cuenta con IA
                            </span>
                            <h1 className="text-4xl font-semibold tracking-tight text-balance sm:text-5xl">
                                Entiende cada centavo de tus estados de cuenta
                            </h1>
                            <p className="max-w-xl text-lg text-pretty text-muted-foreground">
                                Sube el PDF de tu banco y obtén tus movimientos
                                extraídos, saldos conciliados, categorías y
                                alertas de anomalías. Sin hojas de cálculo, sin
                                capturar nada a mano.
                            </p>
                            <div className="flex flex-col gap-3 sm:flex-row">
                                <Button asChild size="lg">
                                    <Link href={primaryHref}>
                                        {primaryLabel}
                                        <ArrowRight className="size-4" />
                                    </Link>
                                </Button>
                                <Button asChild variant="outline" size="lg">
                                    <a href="#como-funciona">Cómo funciona</a>
                                </Button>
                            </div>
                            <p className="text-xs text-muted-foreground">
                                Tus PDFs se procesan de forma privada. Concilia
                                al centavo o te avisamos por qué no cuadra.
                            </p>
                        </div>

                        <div className="w-full">
                            <StatementPreview />
                        </div>
                    </div>
                </section>

                {/* Bank strip */}
                <section className="border-y border-border bg-muted/30">
                    <div className="mx-auto flex w-full max-w-6xl flex-col items-center gap-4 px-4 py-8 md:px-6">
                        <p className="text-xs tracking-wide text-muted-foreground uppercase">
                            Compatible con los PDFs de cualquier banco
                        </p>
                        <div className="flex flex-wrap items-center justify-center gap-x-8 gap-y-3">
                            {BANKS.map((bank) => (
                                <span
                                    key={bank}
                                    className="flex items-center gap-1.5 text-sm font-medium text-muted-foreground"
                                >
                                    <Landmark className="size-4 opacity-60" />
                                    {bank}
                                </span>
                            ))}
                        </div>
                    </div>
                </section>

                {/* Features */}
                <section className="mx-auto w-full max-w-6xl px-4 py-16 md:px-6 md:py-24">
                    <div className="mx-auto max-w-2xl text-center">
                        <h2 className="text-3xl font-semibold tracking-tight text-balance">
                            Todo lo que necesitas para entender tu dinero
                        </h2>
                        <p className="mt-3 text-pretty text-muted-foreground">
                            Del PDF crudo a decisiones claras. Al centavo lo
                            hace en segundos.
                        </p>
                    </div>

                    <div className="mt-12 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        {FEATURES.map((feature) => (
                            <div
                                key={feature.title}
                                className="rounded-xl border border-border bg-card p-6 transition-colors hover:border-foreground/20"
                            >
                                <div className="flex size-10 items-center justify-center rounded-lg bg-brand/10 text-brand">
                                    <feature.icon className="size-5" />
                                </div>
                                <h3 className="mt-4 font-medium">
                                    {feature.title}
                                </h3>
                                <p className="mt-1.5 text-sm text-muted-foreground">
                                    {feature.description}
                                </p>
                            </div>
                        ))}
                    </div>
                </section>

                {/* How it works */}
                <section
                    id="como-funciona"
                    className="border-t border-border bg-muted/30"
                >
                    <div className="mx-auto w-full max-w-6xl px-4 py-16 md:px-6 md:py-24">
                        <div className="mx-auto max-w-2xl text-center">
                            <h2 className="text-3xl font-semibold tracking-tight text-balance">
                                De PDF a claridad en tres pasos
                            </h2>
                        </div>

                        <div className="mt-12 grid gap-6 md:grid-cols-3">
                            {STEPS.map((step, index) => (
                                <div
                                    key={step.title}
                                    className="relative rounded-xl border border-border bg-card p-6"
                                >
                                    <span className="text-sm font-semibold text-muted-foreground tabular-nums">
                                        0{index + 1}
                                    </span>
                                    <div className="mt-3 flex size-10 items-center justify-center rounded-lg bg-brand/10 text-brand">
                                        <step.icon className="size-5" />
                                    </div>
                                    <h3 className="mt-4 font-medium">
                                        {step.title}
                                    </h3>
                                    <p className="mt-1.5 text-sm text-muted-foreground">
                                        {step.description}
                                    </p>
                                </div>
                            ))}
                        </div>
                    </div>
                </section>

                {/* Final CTA */}
                <section className="mx-auto w-full max-w-6xl px-4 py-16 md:px-6 md:py-24">
                    <div className="relative overflow-hidden rounded-2xl border border-border bg-card px-6 py-14 text-center">
                        <div
                            aria-hidden
                            className="pointer-events-none absolute top-[-40%] left-1/2 z-0 h-75 w-150 max-w-full -translate-x-1/2 rounded-full bg-brand/10 blur-3xl"
                        />
                        <div className="relative">
                            <h2 className="mx-auto max-w-xl text-3xl font-semibold tracking-tight text-balance">
                                Empieza a cuadrar tus cuentas al centavo
                            </h2>
                            <p className="mx-auto mt-3 max-w-md text-pretty text-muted-foreground">
                                Sube tu primer estado de cuenta y ve tus
                                movimientos, categorías y alertas en minutos.
                            </p>
                            <div className="mt-6 flex justify-center">
                                <Button asChild size="lg">
                                    <Link href={primaryHref}>
                                        {primaryLabel}
                                        <ArrowRight className="size-4" />
                                    </Link>
                                </Button>
                            </div>
                        </div>
                    </div>
                </section>

                {/* Footer */}
                <footer className="border-t border-border">
                    <div className="mx-auto flex w-full max-w-6xl flex-col items-center justify-between gap-4 px-4 py-8 sm:flex-row md:px-6">
                        <Wordmark />
                        <p className="text-xs text-muted-foreground">
                            © 2026 Al centavo · Powered by SynapticSpark
                        </p>
                    </div>
                </footer>
            </div>
        </>
    );
}
