import { useEffect, useRef, useState } from 'react';
import { Head, Link } from '@inertiajs/react';
import { login, register } from '@/routes';
import { Facebook, Instagram, Menu, X, Check, Calendar } from 'lucide-react';

// ─── Scroll Fade-In Hook ─────────────────────────────────────────────────────

function useScrollReveal<T extends HTMLElement>(threshold = 0.15) {
    const ref = useRef<T>(null);
    const [visible, setVisible] = useState(false);

    useEffect(() => {
        const el = ref.current;
        if (!el) return;

        const observer = new IntersectionObserver(
            ([entry]) => {
                if (entry.isIntersecting) {
                    setVisible(true);
                    observer.unobserve(el);
                }
            },
            { threshold },
        );

        observer.observe(el);
        return () => observer.disconnect();
    }, [threshold]);

    return { ref, visible };
}

const revealClasses = (visible: boolean, delay = '') =>
    `transition-all duration-700 ease-out ${delay} ${
        visible ? 'translate-y-0 opacity-100' : 'translate-y-8 opacity-0'
    }`;

// ─── Navbar ──────────────────────────────────────────────────────────────────

function LandingNavbar() {
    const [open, setOpen] = useState(false);

    return (
        <nav className="sticky top-0 z-50 border-b border-white/10 bg-white/90 backdrop-blur-md">
            <div className="mx-auto flex max-w-7xl items-center justify-between px-5 py-3 lg:py-4">
                {/* Logo */}
                <Link href="/" className="flex items-center">
                    <img
                        src="/images/agendamax-logo-dark.png"
                        alt="AgendaMax"
                        className="h-9 w-auto"
                    />
                </Link>

                {/* Desktop nav links */}
                <div className="hidden items-center gap-7 lg:flex">
                    <a
                        href="#como-funciona"
                        className="text-sm font-medium text-slate-600 transition-colors hover:text-[#1B4FE8]"
                    >
                        Funciones
                    </a>
                    <a
                        href="#por-que"
                        className="text-sm font-medium text-slate-600 transition-colors hover:text-[#1B4FE8]"
                    >
                        ¿Por qué nosotros?
                    </a>
                    <a
                        href="#precios"
                        className="text-sm font-medium text-slate-600 transition-colors hover:text-[#1B4FE8]"
                    >
                        Planes
                    </a>
                    <a
                        href="#"
                        className="text-sm font-medium text-slate-600 transition-colors hover:text-[#1B4FE8]"
                    >
                        Soporte
                    </a>
                </div>

                {/* Desktop actions */}
                <div className="hidden items-center gap-3 lg:flex">
                    <Link
                        href={login()}
                        className="rounded-lg border border-slate-200 px-4 py-2 text-sm font-medium text-slate-700 transition-colors hover:border-[#1B4FE8] hover:text-[#1B4FE8]"
                    >
                        Ingresar
                    </Link>
                    <Link
                        href={register()}
                        className="rounded-lg bg-[#1B4FE8] px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-[#3D6FFF]"
                    >
                        Empezar gratis →
                    </Link>
                </div>

                {/* Mobile hamburger */}
                <button
                    className="rounded-md p-2 text-slate-600 lg:hidden"
                    onClick={() => setOpen(!open)}
                    aria-label="Toggle menu"
                >
                    {open ? <X size={22} /> : <Menu size={22} />}
                </button>
            </div>

            {/* Mobile dropdown */}
            {open && (
                <div className="border-t border-slate-100 bg-white px-5 py-4 lg:hidden">
                    <div className="flex flex-col gap-4">
                        <a
                            href="#como-funciona"
                            className="text-sm font-medium text-slate-700"
                            onClick={() => setOpen(false)}
                        >
                            Funciones
                        </a>
                        <a
                            href="#por-que"
                            className="text-sm font-medium text-slate-700"
                            onClick={() => setOpen(false)}
                        >
                            ¿Por qué nosotros?
                        </a>
                        <a
                            href="#precios"
                            className="text-sm font-medium text-slate-700"
                            onClick={() => setOpen(false)}
                        >
                            Planes
                        </a>
                        <a
                            href="#"
                            className="text-sm font-medium text-slate-700"
                            onClick={() => setOpen(false)}
                        >
                            Soporte
                        </a>
                        <hr className="border-slate-100" />
                        <Link
                            href={login()}
                            className="rounded-lg border border-slate-200 px-4 py-2.5 text-center text-sm font-medium text-slate-700"
                        >
                            Ingresar
                        </Link>
                        <Link
                            href={register()}
                            className="rounded-lg bg-[#1B4FE8] px-4 py-2.5 text-center text-sm font-semibold text-white"
                        >
                            Empezar gratis →
                        </Link>
                    </div>
                </div>
            )}
        </nav>
    );
}

// ─── Phone Mockup ─────────────────────────────────────────────────────────────

function PhoneMockup() {
    return (
        <div className="relative mx-auto w-[260px]">
            {/* Floating chips */}
            <div className="animate-[floatY_3.2s_ease-in-out_infinite] absolute -left-14 top-16 z-10 flex items-center gap-2 rounded-xl bg-white px-3 py-2 shadow-lg shadow-black/10">
                <span className="flex h-6 w-6 items-center justify-center rounded-full bg-[#00CC7E] text-xs">✓</span>
                <span className="text-xs font-semibold text-slate-800">Cita confirmada</span>
            </div>
            <div className="animate-[floatY_3.8s_ease-in-out_0.6s_infinite] absolute -right-12 bottom-28 z-10 flex items-center gap-2 rounded-xl bg-white px-3 py-2 shadow-lg shadow-black/10">
                <span className="text-sm">📅</span>
                <span className="text-xs font-semibold text-slate-800">Agenda llena hoy</span>
            </div>

            {/* Phone frame */}
            <div className="relative overflow-hidden rounded-[2.5rem] border-[6px] border-slate-700 bg-[#0F1A3E] shadow-2xl shadow-black/50">
                {/* Notch */}
                <div className="mx-auto mt-2 h-5 w-20 rounded-full bg-slate-800" />

                {/* Screen content */}
                <div className="px-3 pb-5 pt-2">
                    {/* App header */}
                    <div className="mb-3 flex items-center justify-between">
                        <div>
                            <p className="text-[10px] font-medium text-[#8A95B4]">Mi Negocio</p>
                            <p className="text-xs font-bold text-white">Salón Glamour</p>
                        </div>
                        <div className="flex h-7 w-7 items-center justify-center rounded-full bg-[#1B4FE8] text-xs font-bold text-white">
                            SG
                        </div>
                    </div>

                    {/* Stat mini-cards */}
                    <div className="mb-3 grid grid-cols-2 gap-2">
                        <div className="rounded-xl bg-white/10 p-2.5">
                            <p className="text-[10px] text-[#8A95B4]">Citas hoy</p>
                            <p className="text-lg font-bold text-white">8</p>
                        </div>
                        <div className="rounded-xl bg-white/10 p-2.5">
                            <p className="text-[10px] text-[#8A95B4]">Confirmadas</p>
                            <p className="text-lg font-bold text-[#00CC7E]">100%</p>
                        </div>
                    </div>

                    {/* Mini calendar */}
                    <div className="mb-3 rounded-xl bg-white/10 p-2.5">
                        <p className="mb-2 text-[10px] font-semibold text-white">Marzo 2025</p>
                        <div className="grid grid-cols-7 gap-0.5 text-center">
                            {['L', 'M', 'X', 'J', 'V', 'S', 'D'].map((d) => (
                                <span key={d} className="text-[8px] font-medium text-[#8A95B4]">
                                    {d}
                                </span>
                            ))}
                            {Array.from({ length: 31 }, (_, i) => i + 1).map((d) => (
                                <span
                                    key={d}
                                    className={`rounded-full text-[9px] leading-4 ${
                                        d === 15
                                            ? 'bg-[#1B4FE8] font-bold text-white'
                                            : [8, 12, 19, 22, 26].includes(d)
                                              ? 'text-[#00C8E8]'
                                              : 'text-slate-400'
                                    }`}
                                >
                                    {d}
                                </span>
                            ))}
                        </div>
                    </div>

                    {/* Appointment card */}
                    <div className="mb-2 rounded-xl bg-[#1B4FE8]/30 p-2.5">
                        <div className="flex items-center gap-2">
                            <div className="flex h-7 w-7 items-center justify-center rounded-full bg-[#1B4FE8] text-xs">
                                👩
                            </div>
                            <div>
                                <p className="text-[10px] font-semibold text-white">María González</p>
                                <p className="text-[9px] text-[#8A95B4]">3:00 PM · Coloración</p>
                            </div>
                        </div>
                    </div>

                    {/* Notification */}
                    <div className="flex items-center gap-2 rounded-xl bg-[#00CC7E]/20 p-2.5">
                        <span className="text-sm">🔔</span>
                        <p className="text-[9px] font-medium text-[#00CC7E]">Recordatorio enviado</p>
                    </div>
                </div>

                {/* Home bar */}
                <div className="mx-auto mb-2 h-1 w-16 rounded-full bg-slate-600" />
            </div>
        </div>
    );
}

// ─── Hero Section ─────────────────────────────────────────────────────────────

function HeroSection() {
    return (
        <section className="relative overflow-hidden bg-[#0B1437] py-20 md:py-28">
            {/* Decorative gradient orbs */}
            <div className="pointer-events-none absolute -top-40 -left-40 h-[500px] w-[500px] rounded-full bg-[#1B4FE8]/20 blur-[120px]" />
            <div className="pointer-events-none absolute -right-40 bottom-0 h-[400px] w-[400px] rounded-full bg-[#00C8E8]/15 blur-[100px]" />

            <div className="relative mx-auto max-w-7xl px-5">
                <div className="flex flex-col items-center gap-14 md:flex-row md:items-center md:gap-10">
                    {/* Phone mockup on top for mobile */}
                    <div className="order-first md:order-last md:flex-1">
                        <PhoneMockup />
                    </div>

                    {/* Left copy */}
                    <div className="animate-[fadeUp_0.65s_ease_both] flex-1 text-center md:text-left">
                        {/* Badge */}
                        <div className="mb-5 inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/5 px-4 py-1.5">
                            <span className="animate-[blink_2s_infinite] h-2 w-2 rounded-full bg-[#00C8E8]" />
                            <span className="text-xs font-medium text-[#8A95B4]">
                                Diseñado para negocios en RD
                            </span>
                        </div>

                        <h1 className="mb-5 text-4xl leading-tight font-extrabold tracking-tight text-white md:text-5xl lg:text-6xl">
                            Tu negocio no se detiene,{' '}
                            <span className="text-[#00C8E8]">tu agenda tampoco.</span>
                        </h1>

                        <p className="mb-8 text-base text-[#8A95B4] md:text-lg">
                            Gestiona citas, automatiza recordatorios y haz crecer tu negocio
                            desde una sola plataforma. Sin complicaciones.
                        </p>

                        <div className="flex flex-col items-center gap-3 sm:flex-row md:items-start">
                            <Link
                                href={register()}
                                className="w-full rounded-xl bg-[#1B4FE8] px-7 py-3.5 text-center text-sm font-semibold text-white transition-colors hover:bg-[#3D6FFF] sm:w-auto"
                            >
                                Empezar mi prueba gratuita
                            </Link>
                            <a
                                href="#como-funciona"
                                className="flex w-full items-center justify-center gap-2 rounded-xl border border-white/20 px-7 py-3.5 text-sm font-semibold text-white transition-colors hover:border-white/40 sm:w-auto"
                            >
                                Ver cómo funciona
                            </a>
                        </div>

                        <p className="mt-4 text-xs text-[#8A95B4]">
                            Sin tarjeta de crédito · Cancela cuando quieras
                        </p>
                    </div>
                </div>
            </div>
        </section>
    );
}

// ─── Steps Section ────────────────────────────────────────────────────────────

const STEPS = [
    {
        num: '01',
        emoji: '📱',
        title: 'Agendamiento Online',
        desc: 'Tus clientes reservan citas 24/7 desde su teléfono, sin llamadas ni mensajes de texto.',
        accent: true,
    },
    {
        num: '02',
        emoji: '✅',
        title: 'Confirmación Automática',
        desc: 'El sistema confirma cada cita al instante y actualiza tu calendario en tiempo real.',
        accent: false,
    },
    {
        num: '03',
        emoji: '🔔',
        title: 'Recordatorios Inteligentes',
        desc: 'Envía recordatorios automáticos por WhatsApp y reduce las ausencias hasta un 70%.',
        accent: false,
    },
    {
        num: '04',
        emoji: '📈',
        title: 'Reportes de Crecimiento',
        desc: 'Visualiza tus métricas, ingresos y tendencias para tomar mejores decisiones.',
        accent: false,
    },
];

function StepsSection() {
    const header = useScrollReveal<HTMLDivElement>();
    const grid = useScrollReveal<HTMLDivElement>(0.1);

    return (
        <section id="como-funciona" className="bg-[#F5F7FC] py-20 md:py-28">
            <div className="mx-auto max-w-7xl px-5">
                {/* Header */}
                <div ref={header.ref} className={`mb-14 text-center ${revealClasses(header.visible)}`}>
                    <span className="mb-3 inline-block rounded-full bg-[#E6EAF6] px-4 py-1 text-xs font-semibold uppercase tracking-widest text-[#1B4FE8]">
                        ¿Cómo funciona?
                    </span>
                    <h2 className="text-3xl font-extrabold tracking-tight text-[#0B1437] md:text-4xl">
                        Organiza, Automatiza y Crece en 4 pasos
                    </h2>
                </div>

                {/* Steps grid */}
                <div ref={grid.ref} className="relative">
                    {/* Connecting gradient line (desktop) */}
                    <div className="absolute top-10 right-0 left-0 hidden h-0.5 bg-gradient-to-r from-[#1B4FE8] to-[#00C8E8] lg:block" />

                    <div className="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                        {STEPS.map((step, i) => (
                            <div
                                key={step.num}
                                className={`relative rounded-2xl p-6 transition-all duration-700 ease-out ${
                                    grid.visible ? 'translate-y-0 opacity-100' : 'translate-y-8 opacity-0'
                                } ${
                                    step.accent
                                        ? 'bg-[#1B4FE8] text-white shadow-lg shadow-[#1B4FE8]/30'
                                        : 'bg-white text-[#0B1437] shadow-sm'
                                }`}
                                style={{ transitionDelay: grid.visible ? `${i * 120}ms` : '0ms' }}
                            >
                                {/* Number badge */}
                                <div
                                    className={`mb-4 flex h-10 w-10 items-center justify-center rounded-full text-sm font-bold ${
                                        step.accent
                                            ? 'bg-white/20 text-white'
                                            : 'bg-[#E6EAF6] text-[#1B4FE8]'
                                    }`}
                                >
                                    {step.num}
                                </div>
                                <div className="mb-3 text-2xl">{step.emoji}</div>
                                <h3
                                    className={`mb-2 text-base font-bold ${step.accent ? 'text-white' : 'text-[#0B1437]'}`}
                                >
                                    {step.title}
                                </h3>
                                <p
                                    className={`text-sm leading-relaxed ${step.accent ? 'text-white/80' : 'text-[#8A95B4]'}`}
                                >
                                    {step.desc}
                                </p>
                            </div>
                        ))}
                    </div>
                </div>
            </div>
        </section>
    );
}

// ─── Why Us Section ───────────────────────────────────────────────────────────

const FEATURES = [
    {
        emoji: '📅',
        title: 'Reservas Online 24/7',
        desc: 'Tus clientes pueden agendar en cualquier momento, incluso cuando estás durmiendo.',
        bg: 'bg-[#1B4FE8]/10',
        color: 'text-[#1B4FE8]',
    },
    {
        emoji: '💬',
        title: 'WhatsApp Business Integrado',
        desc: 'Confirmaciones y recordatorios automáticos directo al WhatsApp de tus clientes.',
        bg: 'bg-[#00C8E8]/10',
        color: 'text-[#00C8E8]',
    },
    {
        emoji: '📊',
        title: 'Control Total del Negocio',
        desc: 'Panel unificado con ingresos, citas, empleados y métricas en tiempo real.',
        bg: 'bg-[#00CC7E]/10',
        color: 'text-[#00CC7E]',
    },
    {
        emoji: '🇩🇴',
        title: 'Soporte 100% Dominicano',
        desc: 'Equipo local que entiende tu negocio y habla tu idioma. Soporte real, no bots.',
        bg: 'bg-[#FFB800]/10',
        color: 'text-[#FFB800]',
    },
];

function WhyUsSection() {
    const header = useScrollReveal<HTMLDivElement>();
    const grid = useScrollReveal<HTMLDivElement>(0.1);

    return (
        <section id="por-que" className="bg-white py-20 md:py-28">
            <div className="mx-auto max-w-7xl px-5">
                {/* Header */}
                <div ref={header.ref} className={`mb-14 text-center ${revealClasses(header.visible)}`}>
                    <span className="mb-3 inline-block rounded-full bg-[#E6EAF6] px-4 py-1 text-xs font-semibold uppercase tracking-widest text-[#1B4FE8]">
                        ¿Por qué elegirnos?
                    </span>
                    <h2 className="text-3xl font-extrabold tracking-tight text-[#0B1437] md:text-4xl">
                        Todo lo que tu negocio necesita,{' '}
                        <span className="block">en un solo lugar.</span>
                    </h2>
                </div>

                <div ref={grid.ref} className="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                    {FEATURES.map((f, i) => (
                        <div
                            key={f.title}
                            className={`rounded-2xl border border-[#E6EAF6] bg-white p-6 transition-all duration-700 ease-out hover:shadow-md ${
                                grid.visible ? 'translate-y-0 opacity-100' : 'translate-y-8 opacity-0'
                            }`}
                            style={{ transitionDelay: grid.visible ? `${i * 120}ms` : '0ms' }}
                        >
                            <div
                                className={`mb-4 flex h-12 w-12 items-center justify-center rounded-xl text-2xl ${f.bg}`}
                            >
                                {f.emoji}
                            </div>
                            <h3 className="mb-2 text-base font-bold text-[#0B1437]">{f.title}</h3>
                            <p className="text-sm leading-relaxed text-[#8A95B4]">{f.desc}</p>
                        </div>
                    ))}
                </div>
            </div>
        </section>
    );
}

// ─── Pricing Section ──────────────────────────────────────────────────────────

interface Plan {
    name: string;
    price: string;
    period: string;
    badge?: string;
    featured: boolean;
    features: string[];
    cta: string;
}

const PLANS: Plan[] = [
    {
        name: 'Emprendedor',
        price: '$19',
        period: '/mes',
        featured: false,
        features: [
            'Hasta 100 citas/mes',
            '1 empleado',
            'Recordatorios básicos',
            'Soporte por email',
            'Panel de control',
        ],
        cta: 'Empezar gratis',
    },
    {
        name: 'Profesional',
        price: '$39',
        period: '/mes',
        badge: '⭐ Más Popular',
        featured: true,
        features: [
            'Citas ilimitadas',
            'Hasta 5 empleados',
            'WhatsApp automatizado',
            'Reportes avanzados',
            'Soporte prioritario',
            'Integración Google Calendar',
        ],
        cta: 'Empezar gratis',
    },
    {
        name: 'ProMax',
        price: '$69',
        period: '/mes',
        featured: false,
        features: [
            'Todo en Profesional',
            'Empleados ilimitados',
            'Multi-sucursal',
            'API personalizada',
            'Manager dedicado',
            'Onboarding personalizado',
        ],
        cta: 'Contactar ventas',
    },
];

function PricingSection() {
    const header = useScrollReveal<HTMLDivElement>();
    const grid = useScrollReveal<HTMLDivElement>(0.1);

    return (
        <section id="precios" className="bg-[#F5F7FC] py-20 md:py-28">
            <div className="mx-auto max-w-7xl px-5">
                {/* Header */}
                <div ref={header.ref} className={`mb-14 text-center ${revealClasses(header.visible)}`}>
                    <span className="mb-3 inline-block rounded-full bg-[#E6EAF6] px-4 py-1 text-xs font-semibold uppercase tracking-widest text-[#1B4FE8]">
                        Planes
                    </span>
                    <h2 className="text-3xl font-extrabold tracking-tight text-[#0B1437] md:text-4xl">
                        Elige el plan perfecto para ti
                    </h2>
                    <p className="mt-3 text-[#8A95B4]">
                        Sin contratos anuales · Cancela cuando quieras · 14 días gratis
                    </p>
                </div>

                <div ref={grid.ref} className="grid gap-6 md:grid-cols-3">
                    {PLANS.map((plan, i) => (
                        <div
                            key={plan.name}
                            className={`relative flex flex-col rounded-2xl p-7 transition-all duration-700 ease-out ${
                                grid.visible ? 'translate-y-0 opacity-100' : 'translate-y-8 opacity-0'
                            } ${
                                plan.featured
                                    ? 'border-2 border-[#1B4FE8] bg-[#0B1437] shadow-2xl shadow-[#1B4FE8]/20'
                                    : 'border border-[#E6EAF6] bg-white shadow-sm'
                            }`}
                            style={{ transitionDelay: grid.visible ? `${i * 150}ms` : '0ms' }}
                        >
                            {/* Badge */}
                            {plan.badge && (
                                <div className="mb-4 inline-block w-fit rounded-full bg-[#1B4FE8] px-3 py-1 text-xs font-semibold text-white">
                                    {plan.badge}
                                </div>
                            )}

                            <h3
                                className={`mb-1 text-lg font-bold ${plan.featured ? 'text-white' : 'text-[#0B1437]'}`}
                            >
                                {plan.name}
                            </h3>

                            <div className="mb-6 flex items-baseline gap-1">
                                <span
                                    className={`text-4xl font-extrabold ${plan.featured ? 'text-white' : 'text-[#0B1437]'}`}
                                >
                                    {plan.price}
                                </span>
                                <span
                                    className={`text-sm ${plan.featured ? 'text-[#8A95B4]' : 'text-[#8A95B4]'}`}
                                >
                                    {plan.period}
                                </span>
                            </div>

                            <ul className="mb-8 flex flex-col gap-3">
                                {plan.features.map((f) => (
                                    <li key={f} className="flex items-center gap-2.5">
                                        <Check
                                            size={15}
                                            className={
                                                plan.featured ? 'text-[#00CC7E]' : 'text-[#1B4FE8]'
                                            }
                                            strokeWidth={3}
                                        />
                                        <span
                                            className={`text-sm ${plan.featured ? 'text-slate-300' : 'text-[#8A95B4]'}`}
                                        >
                                            {f}
                                        </span>
                                    </li>
                                ))}
                            </ul>

                            <div className="mt-auto">
                                <Link
                                    href={register()}
                                    className={`block w-full rounded-xl py-3 text-center text-sm font-semibold transition-colors ${
                                        plan.featured
                                            ? 'bg-[#1B4FE8] text-white hover:bg-[#3D6FFF]'
                                            : 'border border-[#1B4FE8] text-[#1B4FE8] hover:bg-[#1B4FE8] hover:text-white'
                                    }`}
                                >
                                    {plan.cta}
                                </Link>
                            </div>
                        </div>
                    ))}
                </div>
            </div>
        </section>
    );
}

// ─── CTA Final Section ────────────────────────────────────────────────────────

function CtaSection() {
    const reveal = useScrollReveal<HTMLDivElement>();

    return (
        <section className="relative overflow-hidden bg-[#0B1437] py-24">
            {/* Orb */}
            <div className="pointer-events-none absolute top-1/2 left-1/2 h-[600px] w-[600px] -translate-x-1/2 -translate-y-1/2 rounded-full bg-[#1B4FE8]/20 blur-[120px]" />

            <div ref={reveal.ref} className={`relative mx-auto max-w-3xl px-5 text-center ${revealClasses(reveal.visible)}`}>
                <h2 className="mb-5 text-3xl font-extrabold tracking-tight text-white md:text-5xl">
                    Únete a la nueva era de los negocios en RD.
                </h2>
                <p className="mb-8 text-[#8A95B4] md:text-lg">
                    Miles de negocios ya automatizan sus citas y hacen crecer sus ingresos con
                    AgendaMax.
                </p>
                <div className="flex flex-col items-center justify-center gap-4 sm:flex-row">
                    <Link
                        href={register()}
                        className="w-full rounded-xl bg-[#1B4FE8] px-8 py-4 text-center text-sm font-semibold text-white transition-colors hover:bg-[#3D6FFF] sm:w-auto"
                    >
                        Crear mi cuenta gratis →
                    </Link>
                    <a
                        href="#"
                        className="flex w-full items-center justify-center gap-2 rounded-xl border border-white/20 px-8 py-4 text-sm font-semibold text-white transition-colors hover:border-white/40 sm:w-auto"
                    >
                        Hablar con soporte
                    </a>
                </div>
                <p className="mt-6 text-xs text-[#8A95B4]">
                    Sin contratos · Sin compromisos · 100% dominicano
                </p>
            </div>
        </section>
    );
}

// ─── Footer ───────────────────────────────────────────────────────────────────

function LandingFooter() {
    const reveal = useScrollReveal<HTMLElement>(0.2);

    return (
        <footer ref={reveal.ref} className={`border-t border-white/10 bg-[#0B1437] py-10 ${revealClasses(reveal.visible)}`}>
            <div className="mx-auto max-w-7xl px-5">
                <div className="flex flex-col items-center justify-between gap-6 md:flex-row">
                    {/* Logo */}
                    <Link href="/" className="flex items-center">
                        <img
                            src="/images/agendamax-logo-light.png"
                            alt="AgendaMax"
                            className="h-8 w-auto"
                        />
                    </Link>

                    {/* Links */}
                    <div className="flex items-center gap-6">
                        <a
                            href="#"
                            className="text-sm text-[#8A95B4] transition-colors hover:text-white"
                        >
                            Legal
                        </a>
                        <a
                            href="#"
                            className="text-sm text-[#8A95B4] transition-colors hover:text-white"
                        >
                            Contacto
                        </a>
                        <a
                            href="#"
                            className="text-sm text-[#8A95B4] transition-colors hover:text-white"
                        >
                            Privacidad
                        </a>
                    </div>

                    {/* Social */}
                    <div className="flex items-center gap-4">
                        <a
                            href="#"
                            className="flex h-9 w-9 items-center justify-center rounded-full border border-white/10 text-[#8A95B4] transition-colors hover:border-white/30 hover:text-white"
                            aria-label="Facebook"
                        >
                            <Facebook size={16} />
                        </a>
                        <a
                            href="#"
                            className="flex h-9 w-9 items-center justify-center rounded-full border border-white/10 text-[#8A95B4] transition-colors hover:border-white/30 hover:text-white"
                            aria-label="Instagram"
                        >
                            <Instagram size={16} />
                        </a>
                    </div>
                </div>

                <p className="mt-8 text-center text-xs text-[#8A95B4]">
                    © {new Date().getFullYear()} AgendaMax. Todos los derechos reservados.
                </p>
            </div>
        </footer>
    );
}

// ─── Page ─────────────────────────────────────────────────────────────────────

export default function Homepage() {
    return (
        <>
            <Head title="AgendaMax — Tu negocio no se detiene, tu agenda tampoco">
                {/* SEO Meta */}
                <meta name="description" content="Automatiza tus citas, reduce inasistencias y gestiona tus clientes desde un solo lugar. La plataforma de agendamiento hecha para negocios en República Dominicana." />
                <meta name="keywords" content="agendamiento online, citas, agenda, negocios, República Dominicana, salón, spa, barbería, WhatsApp, automatización" />
                <meta name="author" content="AgendaMax" />
                <meta name="robots" content="index, follow" />
                <link rel="canonical" href="https://agendamax.do" />

                {/* OpenGraph */}
                <meta property="og:type" content="website" />
                <meta property="og:url" content="https://agendamax.do" />
                <meta property="og:title" content="AgendaMax — Tu negocio no se detiene, tu agenda tampoco" />
                <meta property="og:description" content="Automatiza tus citas, reduce inasistencias y gestiona tus clientes desde un solo lugar. La plataforma de agendamiento hecha para negocios en RD." />
                <meta property="og:image" content="https://agendamax.do/images/agendamax-og.png" />
                <meta property="og:image:width" content="1200" />
                <meta property="og:image:height" content="630" />
                <meta property="og:locale" content="es_DO" />
                <meta property="og:site_name" content="AgendaMax" />

                {/* Twitter Card */}
                <meta name="twitter:card" content="summary_large_image" />
                <meta name="twitter:title" content="AgendaMax — Tu negocio no se detiene, tu agenda tampoco" />
                <meta name="twitter:description" content="Automatiza tus citas, reduce inasistencias y gestiona tus clientes desde un solo lugar." />
                <meta name="twitter:image" content="https://agendamax.do/images/agendamax-og.png" />

                {/* Fonts */}
                <link rel="preconnect" href="https://fonts.googleapis.com" />
                <link rel="preconnect" href="https://fonts.gstatic.com" crossOrigin="anonymous" />
                <link
                    href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500;9..40,600&display=swap"
                    rel="stylesheet"
                />
            </Head>
            <div className="scroll-smooth font-['DM_Sans',ui-sans-serif,system-ui,sans-serif]">
                <LandingNavbar />
                <HeroSection />
                <StepsSection />
                <WhyUsSection />
                <PricingSection />
                <CtaSection />
                <LandingFooter />
            </div>
        </>
    );
}
