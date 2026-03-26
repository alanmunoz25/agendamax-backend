import { Head, Link } from '@inertiajs/react';
import type { Business, Course, PaginatedResponse } from '@/types/models';
import { GraduationCap, MapPin, Clock } from 'lucide-react';
import { router } from '@inertiajs/react';

const modalityLabels: Record<string, string> = {
    online: 'Online',
    presencial: 'Presencial',
    hybrid: 'Híbrido',
};

interface Props {
    business: Business;
    courses: PaginatedResponse<Course>;
}

function CourseCard({ course, businessSlug }: { course: Course; businessSlug: string }) {
    return (
        <Link
            href={`/${businessSlug}/courses/${course.slug}`}
            className="group flex flex-col overflow-hidden rounded-xl border bg-white shadow-sm transition-shadow hover:shadow-md"
        >
            {/* Image */}
            <div className="relative h-48 w-full overflow-hidden">
                {course.cover_image ? (
                    <img
                        src={`/storage/${course.cover_image}`}
                        alt={course.title}
                        className="h-full w-full object-cover transition-transform group-hover:scale-105"
                    />
                ) : (
                    <div className="flex h-full w-full items-center justify-center bg-gradient-to-br from-indigo-500 to-purple-600">
                        <GraduationCap className="h-16 w-16 text-white/60" />
                    </div>
                )}
                {/* Price badge */}
                <div className="absolute right-3 top-3 rounded-full bg-white px-3 py-1 text-sm font-bold shadow">
                    {Number(course.price) > 0
                        ? `${course.currency} ${Number(course.price).toLocaleString('es-DO', { minimumFractionDigits: 0 })}`
                        : 'Gratis'}
                </div>
            </div>

            {/* Content */}
            <div className="flex flex-1 flex-col p-5">
                <div className="mb-2 flex items-center gap-2">
                    <span className="inline-flex items-center rounded-full bg-indigo-50 px-2.5 py-0.5 text-xs font-medium text-indigo-700">
                        {modalityLabels[course.modality] || course.modality}
                    </span>
                    {course.is_featured && (
                        <span className="inline-flex items-center rounded-full bg-amber-50 px-2.5 py-0.5 text-xs font-medium text-amber-700">
                            Destacado
                        </span>
                    )}
                </div>

                <h3 className="mb-2 text-lg font-semibold text-gray-900 group-hover:text-indigo-600">
                    {course.title}
                </h3>

                {course.instructor_name && (
                    <p className="mb-3 text-sm text-gray-500">por {course.instructor_name}</p>
                )}

                <div className="mt-auto space-y-1">
                    {course.start_date && (
                        <div className="flex items-center gap-1.5 text-xs text-gray-500">
                            <Clock className="h-3.5 w-3.5" />
                            <span>Inicia: {new Date(course.start_date).toLocaleDateString('es-DO', { month: 'short', day: 'numeric', year: 'numeric' })}</span>
                        </div>
                    )}
                    {course.schedule_text && (
                        <div className="flex items-center gap-1.5 text-xs text-gray-500">
                            <MapPin className="h-3.5 w-3.5" />
                            <span>{course.schedule_text}</span>
                        </div>
                    )}
                </div>

                <div className="mt-4">
                    <span className="inline-flex w-full items-center justify-center rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-medium text-white transition-colors group-hover:bg-indigo-700">
                        Ver Detalles
                    </span>
                </div>
            </div>
        </Link>
    );
}

export default function PublicCoursesIndex({ business, courses }: Props) {
    return (
        <>
            <Head>
                <title>{`Cursos - ${business.name}`}</title>
                <meta name="description" content={`Descubre los cursos disponibles en ${business.name}`} />
                <meta property="og:title" content={`Cursos - ${business.name}`} />
                <meta property="og:type" content="website" />
            </Head>

            <div className="min-h-screen bg-gray-50">
                {/* Header */}
                <header className="bg-white shadow-sm">
                    <div className="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
                        <div className="flex items-center gap-3">
                            {business.logo_url ? (
                                <img src={business.logo_url} alt={business.name} className="h-10 w-10 rounded-full object-cover" />
                            ) : (
                                <div className="flex h-10 w-10 items-center justify-center rounded-full bg-indigo-100">
                                    <GraduationCap className="h-5 w-5 text-indigo-600" />
                                </div>
                            )}
                            <div>
                                <h1 className="text-2xl font-bold text-gray-900">{business.name}</h1>
                                <p className="text-sm text-gray-500">Nuestros Cursos</p>
                            </div>
                        </div>
                    </div>
                </header>

                {/* Content */}
                <main className="mx-auto max-w-7xl px-4 py-10 sm:px-6 lg:px-8">
                    {courses.data.length > 0 ? (
                        <>
                            <div className="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                                {courses.data.map((course) => (
                                    <CourseCard key={course.id} course={course} businessSlug={business.slug} />
                                ))}
                            </div>

                            {/* Pagination */}
                            {courses.last_page > 1 && (
                                <div className="mt-10 flex items-center justify-center gap-2">
                                    <button
                                        onClick={() => courses.current_page > 1 && router.get(`/${business.slug}/courses`, { page: courses.current_page - 1 }, { preserveState: true })}
                                        disabled={courses.current_page === 1}
                                        className="rounded-lg border bg-white px-4 py-2 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-50 disabled:opacity-50"
                                    >
                                        Anterior
                                    </button>
                                    <span className="px-4 text-sm text-gray-500">
                                        Pagina {courses.current_page} de {courses.last_page}
                                    </span>
                                    <button
                                        onClick={() => courses.current_page < courses.last_page && router.get(`/${business.slug}/courses`, { page: courses.current_page + 1 }, { preserveState: true })}
                                        disabled={courses.current_page === courses.last_page}
                                        className="rounded-lg border bg-white px-4 py-2 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-50 disabled:opacity-50"
                                    >
                                        Siguiente
                                    </button>
                                </div>
                            )}
                        </>
                    ) : (
                        <div className="py-20 text-center">
                            <GraduationCap className="mx-auto h-16 w-16 text-gray-300" />
                            <h2 className="mt-4 text-xl font-semibold text-gray-700">No hay cursos disponibles</h2>
                            <p className="mt-2 text-gray-500">Vuelve pronto para ver nuevos cursos.</p>
                        </div>
                    )}
                </main>

                {/* Footer */}
                <footer className="border-t bg-white py-8">
                    <div className="mx-auto max-w-7xl px-4 text-center sm:px-6 lg:px-8">
                        <p className="text-sm text-gray-500">{business.name}</p>
                        <p className="mt-1 text-xs text-gray-400">Powered by AgendaMax</p>
                    </div>
                </footer>
            </div>
        </>
    );
}
