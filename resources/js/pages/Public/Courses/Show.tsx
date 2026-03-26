import { Head, Link } from '@inertiajs/react';
import type { Business, Course } from '@/types/models';
import {
    GraduationCap,
    Calendar,
    Clock,
    MapPin,
    Users,
    DollarSign,
    Monitor,
    UserCircle,
    CheckCircle,
    ArrowLeft,
} from 'lucide-react';
import { useState } from 'react';

const modalityLabels: Record<string, string> = {
    online: 'Online',
    presencial: 'Presencial',
    hybrid: 'Híbrido',
};

interface Props {
    business: Business;
    course: Course;
}

function EnrollmentForm({ course }: { course: Course }) {
    const [formData, setFormData] = useState({ name: '', email: '', phone: '' });
    const [loading, setLoading] = useState(false);
    const [success, setSuccess] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();
        setLoading(true);
        setError(null);

        try {
            const response = await fetch(`/api/v1/courses/${course.id}/enroll`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                },
                body: JSON.stringify(formData),
            });

            const data = await response.json();

            if (!response.ok) {
                setError(data.message || 'Error al procesar la inscripcion.');
                return;
            }

            setSuccess(true);
        } catch {
            setError('Error de conexion. Intenta de nuevo.');
        } finally {
            setLoading(false);
        }
    };

    if (success) {
        const isFree = Number(course.price) <= 0;
        return (
            <div className="rounded-xl border bg-white p-6 text-center shadow-sm">
                <CheckCircle className="mx-auto h-12 w-12 text-green-500" />
                <h3 className="mt-3 text-lg font-semibold text-gray-900">
                    {isFree ? 'Inscripcion Confirmada' : 'Inscripcion Registrada'}
                </h3>
                <p className="mt-2 text-sm text-gray-500">
                    {isFree
                        ? 'Tu inscripcion ha sido confirmada. Revisa tu email para mas detalles.'
                        : 'Tu inscripcion ha sido registrada. Te contactaremos para completar el pago.'}
                </p>
            </div>
        );
    }

    return (
        <div className="rounded-xl border bg-white p-6 shadow-sm">
            <h3 className="mb-4 text-lg font-semibold text-gray-900">Inscribirme</h3>
            <form onSubmit={handleSubmit} className="space-y-4">
                <div>
                    <label htmlFor="enroll-name" className="mb-1 block text-sm font-medium text-gray-700">
                        Nombre completo *
                    </label>
                    <input
                        id="enroll-name"
                        type="text"
                        required
                        value={formData.name}
                        onChange={(e) => setFormData({ ...formData, name: e.target.value })}
                        placeholder="Tu nombre"
                        className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20"
                    />
                </div>
                <div>
                    <label htmlFor="enroll-email" className="mb-1 block text-sm font-medium text-gray-700">
                        Email *
                    </label>
                    <input
                        id="enroll-email"
                        type="email"
                        required
                        value={formData.email}
                        onChange={(e) => setFormData({ ...formData, email: e.target.value })}
                        placeholder="tu@email.com"
                        className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20"
                    />
                </div>
                <div>
                    <label htmlFor="enroll-phone" className="mb-1 block text-sm font-medium text-gray-700">
                        Telefono
                    </label>
                    <input
                        id="enroll-phone"
                        type="tel"
                        value={formData.phone}
                        onChange={(e) => setFormData({ ...formData, phone: e.target.value })}
                        placeholder="809-000-0000"
                        className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20"
                    />
                </div>

                {error && (
                    <div className="rounded-lg bg-red-50 p-3 text-sm text-red-700">
                        {error}
                    </div>
                )}

                <button
                    type="submit"
                    disabled={loading}
                    className="w-full rounded-lg bg-indigo-600 px-4 py-3 text-sm font-semibold text-white transition-colors hover:bg-indigo-700 disabled:opacity-50"
                >
                    {loading ? 'Procesando...' : 'Inscribirme Ahora'}
                </button>

                {Number(course.price) > 0 && (
                    <p className="text-center text-xs text-gray-400">
                        Al inscribirte, te contactaremos para coordinar el pago.
                    </p>
                )}
            </form>
        </div>
    );
}

export default function PublicCourseShow({ business, course }: Props) {
    const formatDate = (dateStr: string | null) => {
        if (!dateStr) return null;
        return new Date(dateStr).toLocaleDateString('es-DO', {
            month: 'long',
            day: 'numeric',
            year: 'numeric',
        });
    };

    return (
        <>
            <Head>
                <title>{`${course.title} - ${business.name}`}</title>
                <meta name="description" content={course.description?.substring(0, 160)} />
                <meta property="og:title" content={course.title} />
                <meta property="og:description" content={course.description?.substring(0, 160)} />
                {course.cover_image && <meta property="og:image" content={`/storage/${course.cover_image}`} />}
                <meta property="og:type" content="website" />
            </Head>

            <div className="min-h-screen bg-gray-50">
                {/* Navigation */}
                <nav className="bg-white shadow-sm">
                    <div className="mx-auto flex max-w-7xl items-center gap-3 px-4 py-4 sm:px-6 lg:px-8">
                        <Link
                            href={`/${business.slug}/courses`}
                            className="flex items-center gap-1.5 text-sm text-gray-500 hover:text-gray-700"
                        >
                            <ArrowLeft className="h-4 w-4" />
                            Volver a cursos
                        </Link>
                        <span className="text-gray-300">|</span>
                        <span className="text-sm font-medium text-gray-700">{business.name}</span>
                    </div>
                </nav>

                {/* SECTION 1: Hero */}
                <div className="relative h-64 w-full overflow-hidden md:h-96">
                    {course.cover_image ? (
                        <img
                            src={`/storage/${course.cover_image}`}
                            alt={course.title}
                            className="h-full w-full object-cover"
                        />
                    ) : (
                        <div className="h-full w-full bg-gradient-to-br from-indigo-600 via-purple-600 to-indigo-800" />
                    )}
                    <div className="absolute inset-0 bg-gradient-to-t from-black/70 via-black/30 to-transparent" />
                    <div className="absolute inset-0 flex items-end">
                        <div className="mx-auto w-full max-w-7xl px-4 pb-8 sm:px-6 lg:px-8">
                            <div className="flex flex-wrap gap-2 mb-3">
                                <span className="inline-flex items-center rounded-full bg-white/20 px-3 py-1 text-sm font-medium text-white backdrop-blur-sm">
                                    {modalityLabels[course.modality] || course.modality}
                                </span>
                                <span className="inline-flex items-center rounded-full bg-white/20 px-3 py-1 text-sm font-bold text-white backdrop-blur-sm">
                                    {Number(course.price) > 0
                                        ? `${course.currency} ${Number(course.price).toLocaleString('es-DO', { minimumFractionDigits: 0 })}`
                                        : 'Gratis'}
                                </span>
                            </div>
                            <h1 className="text-3xl font-bold text-white md:text-5xl">
                                {course.title}
                            </h1>
                            {course.instructor_name && (
                                <p className="mt-2 text-lg text-white/80">por {course.instructor_name}</p>
                            )}
                        </div>
                    </div>
                </div>

                {/* SECTION 2: Quick Info Bar */}
                <div className="border-b bg-white">
                    <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                        <div className="flex flex-wrap items-center gap-6 py-4 text-sm">
                            <div className="flex items-center gap-1.5 text-gray-600">
                                <DollarSign className="h-4 w-4 text-indigo-500" />
                                <span className="font-semibold">
                                    {Number(course.price) > 0
                                        ? `${course.currency} ${Number(course.price).toLocaleString('es-DO', { minimumFractionDigits: 2 })}`
                                        : 'Gratis'}
                                </span>
                            </div>
                            {course.duration_text && (
                                <div className="flex items-center gap-1.5 text-gray-600">
                                    <Clock className="h-4 w-4 text-indigo-500" />
                                    <span>{course.duration_text}</span>
                                </div>
                            )}
                            {course.start_date && (
                                <div className="flex items-center gap-1.5 text-gray-600">
                                    <Calendar className="h-4 w-4 text-indigo-500" />
                                    <span>{formatDate(course.start_date)}</span>
                                </div>
                            )}
                            {course.schedule_text && (
                                <div className="flex items-center gap-1.5 text-gray-600">
                                    <MapPin className="h-4 w-4 text-indigo-500" />
                                    <span>{course.schedule_text}</span>
                                </div>
                            )}
                            <div className="flex items-center gap-1.5 text-gray-600">
                                <Monitor className="h-4 w-4 text-indigo-500" />
                                <span>{modalityLabels[course.modality]}</span>
                            </div>
                            {course.capacity && (
                                <div className="flex items-center gap-1.5 text-gray-600">
                                    <Users className="h-4 w-4 text-indigo-500" />
                                    <span>
                                        {course.remaining_capacity !== null
                                            ? `${course.remaining_capacity} plazas disponibles`
                                            : `${course.capacity} plazas`}
                                    </span>
                                </div>
                            )}
                        </div>
                    </div>
                </div>

                {/* SECTION 3: Main Content */}
                <main className="mx-auto max-w-7xl px-4 py-10 sm:px-6 lg:px-8">
                    <div className="grid gap-8 lg:grid-cols-3">
                        {/* Main column (2/3) */}
                        <div className="space-y-8 lg:col-span-2">
                            {/* Description */}
                            <section>
                                <h2 className="mb-4 text-xl font-bold text-gray-900">Descripcion</h2>
                                <p className="whitespace-pre-wrap leading-relaxed text-gray-600">
                                    {course.description}
                                </p>
                            </section>

                            {/* Syllabus */}
                            {course.syllabus && (
                                <section>
                                    <h2 className="mb-4 text-xl font-bold text-gray-900">Temario</h2>
                                    <div
                                        className="prose prose-lg max-w-none prose-headings:text-gray-900 prose-p:text-gray-600 prose-li:text-gray-600"
                                        dangerouslySetInnerHTML={{ __html: course.syllabus }}
                                    />
                                </section>
                            )}

                            {/* Instructor */}
                            {course.instructor_name && (
                                <section>
                                    <h2 className="mb-4 text-xl font-bold text-gray-900">Instructor</h2>
                                    <div className="flex items-start gap-4 rounded-xl border bg-white p-6">
                                        <div className="flex h-14 w-14 shrink-0 items-center justify-center rounded-full bg-indigo-100">
                                            <UserCircle className="h-8 w-8 text-indigo-600" />
                                        </div>
                                        <div>
                                            <h3 className="text-lg font-semibold text-gray-900">
                                                {course.instructor_name}
                                            </h3>
                                            {course.instructor_bio && (
                                                <p className="mt-1 text-gray-600">{course.instructor_bio}</p>
                                            )}
                                        </div>
                                    </div>
                                </section>
                            )}
                        </div>

                        {/* Sidebar (1/3) */}
                        <div className="space-y-6 lg:sticky lg:top-6 lg:self-start">
                            {/* Details Card */}
                            <div className="rounded-xl border bg-white p-6 shadow-sm">
                                <h3 className="mb-4 text-lg font-semibold text-gray-900">Detalles</h3>
                                <div className="space-y-3">
                                    {course.start_date && (
                                        <div className="flex items-center gap-3">
                                            <Calendar className="h-5 w-5 shrink-0 text-indigo-500" />
                                            <div>
                                                <p className="text-xs font-medium text-gray-500">Inicio</p>
                                                <p className="text-sm text-gray-900">{formatDate(course.start_date)}</p>
                                            </div>
                                        </div>
                                    )}
                                    {course.end_date && (
                                        <div className="flex items-center gap-3">
                                            <Calendar className="h-5 w-5 shrink-0 text-indigo-500" />
                                            <div>
                                                <p className="text-xs font-medium text-gray-500">Fin</p>
                                                <p className="text-sm text-gray-900">{formatDate(course.end_date)}</p>
                                            </div>
                                        </div>
                                    )}
                                    {course.duration_text && (
                                        <div className="flex items-center gap-3">
                                            <Clock className="h-5 w-5 shrink-0 text-indigo-500" />
                                            <div>
                                                <p className="text-xs font-medium text-gray-500">Duracion</p>
                                                <p className="text-sm text-gray-900">{course.duration_text}</p>
                                            </div>
                                        </div>
                                    )}
                                    {course.schedule_text && (
                                        <div className="flex items-center gap-3">
                                            <MapPin className="h-5 w-5 shrink-0 text-indigo-500" />
                                            <div>
                                                <p className="text-xs font-medium text-gray-500">Horario</p>
                                                <p className="text-sm text-gray-900">{course.schedule_text}</p>
                                            </div>
                                        </div>
                                    )}
                                    {course.enrollment_deadline && (
                                        <div className="flex items-center gap-3">
                                            <Calendar className="h-5 w-5 shrink-0 text-red-500" />
                                            <div>
                                                <p className="text-xs font-medium text-gray-500">Limite inscripcion</p>
                                                <p className="text-sm text-gray-900">{formatDate(course.enrollment_deadline)}</p>
                                            </div>
                                        </div>
                                    )}
                                    {course.capacity && (
                                        <div className="flex items-center gap-3">
                                            <Users className="h-5 w-5 shrink-0 text-indigo-500" />
                                            <div>
                                                <p className="text-xs font-medium text-gray-500">Capacidad</p>
                                                <p className="text-sm text-gray-900">
                                                    {course.remaining_capacity !== null
                                                        ? `${course.remaining_capacity} de ${course.capacity} disponibles`
                                                        : `${course.capacity} plazas`}
                                                </p>
                                            </div>
                                        </div>
                                    )}
                                </div>
                            </div>

                            {/* Enrollment Form */}
                            <EnrollmentForm course={course} />
                        </div>
                    </div>
                </main>

                {/* SECTION 4: Footer */}
                <footer className="border-t bg-white py-8">
                    <div className="mx-auto max-w-7xl px-4 text-center sm:px-6 lg:px-8">
                        <p className="text-sm text-gray-500">{business.name}</p>
                        {business.address && <p className="mt-1 text-xs text-gray-400">{business.address}</p>}
                        <p className="mt-2 text-xs text-gray-300">Powered by AgendaMax</p>
                    </div>
                </footer>
            </div>
        </>
    );
}
