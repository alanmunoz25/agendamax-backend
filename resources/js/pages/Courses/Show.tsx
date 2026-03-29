import { router } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
    AlertDialogTrigger,
} from '@/components/ui/alert-dialog';
import type { Course, Enrollment } from '@/types/models';
import {
    GraduationCap,
    Pencil,
    Trash2,
    Users,
    DollarSign,
    Monitor,
    Calendar,
    Clock,
    MapPin,
    UserCircle,
    ClipboardList,
    ExternalLink,
    Globe,
} from 'lucide-react';
import { format } from 'date-fns';
import { Link } from '@inertiajs/react';

const modalityLabels: Record<string, string> = {
    online: 'Online',
    presencial: 'Presencial',
    hybrid: 'Hibrido',
};

const statusColors: Record<string, string> = {
    lead: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
    confirmed: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
    cancelled: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
};

interface Props {
    course: Course;
    recentEnrollments: Enrollment[];
    businessSlug: string;
    can: {
        update: boolean;
        delete: boolean;
    };
}

export default function CourseShow({ course, recentEnrollments, businessSlug, can }: Props) {
    const handleDelete = () => {
        router.delete(`/courses/${course.id}`);
    };

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Dashboard', href: '/dashboard' },
                { title: 'Cursos', href: '/courses' },
                { title: course.title },
            ]}
        >
            <div className="space-y-6">
                {/* Hero / Header */}
                {course.cover_image && (
                    <div className="relative h-48 w-full overflow-hidden rounded-lg sm:h-64">
                        <img
                            src={`/storage/${course.cover_image}`}
                            alt={course.title}
                            className="h-full w-full object-cover"
                        />
                        <div className="absolute inset-0 bg-gradient-to-t from-black/60 to-transparent" />
                    </div>
                )}

                <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <div className="flex items-center gap-3">
                            <h1 className="text-3xl font-bold tracking-tight text-foreground">
                                {course.title}
                            </h1>
                            <Badge variant={course.is_active ? 'default' : 'secondary'}>
                                {course.is_active ? 'Activo' : 'Inactivo'}
                            </Badge>
                            <Badge variant="outline">
                                {modalityLabels[course.modality] || course.modality}
                            </Badge>
                            {course.is_featured && (
                                <Badge variant="secondary">Destacado</Badge>
                            )}
                        </div>
                        {course.instructor_name && (
                            <p className="mt-1 text-sm text-muted-foreground">
                                por {course.instructor_name}
                            </p>
                        )}
                    </div>

                    <div className="flex gap-2">
                        {can.update && (
                            <Button
                                variant="outline"
                                onClick={() => router.get(`/courses/${course.id}/edit`)}
                            >
                                <Pencil className="mr-2 h-4 w-4" />
                                Editar
                            </Button>
                        )}
                        <Button
                            variant="outline"
                            onClick={() => router.get(`/courses/${course.id}/enrollments`)}
                        >
                            <Users className="mr-2 h-4 w-4" />
                            Inscripciones
                        </Button>
                        <a
                            href={`/${businessSlug}/courses/${course.slug}`}
                            target="_blank"
                            rel="noopener noreferrer"
                        >
                            <Button variant="outline">
                                <Globe className="mr-2 h-4 w-4" />
                                Pagina Publica
                            </Button>
                        </a>
                        {can.delete && (
                            <AlertDialog>
                                <AlertDialogTrigger asChild>
                                    <Button variant="destructive">
                                        <Trash2 className="mr-2 h-4 w-4" />
                                        Eliminar
                                    </Button>
                                </AlertDialogTrigger>
                                <AlertDialogContent>
                                    <AlertDialogHeader>
                                        <AlertDialogTitle>Eliminar Curso</AlertDialogTitle>
                                        <AlertDialogDescription>
                                            Esta accion no se puede deshacer. Se eliminara permanentemente
                                            el curso &quot;{course.title}&quot;.
                                        </AlertDialogDescription>
                                    </AlertDialogHeader>
                                    <AlertDialogFooter>
                                        <AlertDialogCancel>Cancelar</AlertDialogCancel>
                                        <AlertDialogAction onClick={handleDelete}>
                                            Eliminar
                                        </AlertDialogAction>
                                    </AlertDialogFooter>
                                </AlertDialogContent>
                            </AlertDialog>
                        )}
                    </div>
                </div>

                {/* Stats Row */}
                <div className="grid gap-4 sm:grid-cols-4">
                    <Card>
                        <CardContent className="flex items-center gap-3 pt-6">
                            <Users className="h-8 w-8 text-muted-foreground" />
                            <div>
                                <p className="text-2xl font-bold">
                                    {course.enrollments_count || 0}
                                    {course.capacity ? ` / ${course.capacity}` : ''}
                                </p>
                                <p className="text-xs text-muted-foreground">Inscritos</p>
                            </div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent className="flex items-center gap-3 pt-6">
                            <DollarSign className="h-8 w-8 text-muted-foreground" />
                            <div>
                                <p className="text-2xl font-bold">
                                    {Number(course.price) > 0
                                        ? `${course.currency} ${Number(course.price).toLocaleString('es-DO', { minimumFractionDigits: 2 })}`
                                        : 'Gratis'}
                                </p>
                                <p className="text-xs text-muted-foreground">Precio</p>
                            </div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent className="flex items-center gap-3 pt-6">
                            <Monitor className="h-8 w-8 text-muted-foreground" />
                            <div>
                                <p className="text-2xl font-bold">
                                    {modalityLabels[course.modality] || course.modality}
                                </p>
                                <p className="text-xs text-muted-foreground">Modalidad</p>
                            </div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent className="flex items-center gap-3 pt-6">
                            <Calendar className="h-8 w-8 text-muted-foreground" />
                            <div>
                                <p className="text-2xl font-bold">
                                    {course.start_date
                                        ? format(new Date(course.start_date), 'MMM d, yyyy')
                                        : 'Por definir'}
                                </p>
                                <p className="text-xs text-muted-foreground">Fecha inicio</p>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                {/* Main Content Grid */}
                <div className="grid gap-6 lg:grid-cols-3">
                    {/* Left Column (2/3) */}
                    <div className="space-y-6 lg:col-span-2">
                        {/* Description */}
                        <Card>
                            <CardHeader>
                                <CardTitle>Descripcion</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <p className="whitespace-pre-wrap text-sm text-muted-foreground">
                                    {course.description}
                                </p>
                            </CardContent>
                        </Card>

                        {/* Syllabus */}
                        {course.syllabus && (
                            <Card>
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2">
                                        <ClipboardList className="h-5 w-5" />
                                        Temario
                                    </CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <div
                                        className="prose prose-sm dark:prose-invert max-w-none"
                                        dangerouslySetInnerHTML={{ __html: course.syllabus }}
                                    />
                                </CardContent>
                            </Card>
                        )}

                        {/* Instructor */}
                        {course.instructor_name && (
                            <Card>
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2">
                                        <UserCircle className="h-5 w-5" />
                                        Instructor
                                    </CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <div className="flex items-start gap-4">
                                        {course.instructor_image ? (
                                            <img
                                                src={`/storage/${course.instructor_image}`}
                                                alt={course.instructor_name || 'Instructor'}
                                                className="h-16 w-16 shrink-0 rounded-full object-cover"
                                            />
                                        ) : (
                                            <div className="flex h-16 w-16 shrink-0 items-center justify-center rounded-full bg-muted">
                                                <UserCircle className="h-8 w-8 text-muted-foreground" />
                                            </div>
                                        )}
                                        <div>
                                            <p className="font-medium">{course.instructor_name}</p>
                                            {course.instructor_bio && (
                                                <p className="mt-1 text-sm text-muted-foreground">
                                                    {course.instructor_bio}
                                                </p>
                                            )}
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>
                        )}
                    </div>

                    {/* Right Column (1/3) */}
                    <div className="space-y-6">
                        {/* Details Card */}
                        <Card>
                            <CardHeader>
                                <CardTitle>Detalles</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                {course.start_date && (
                                    <div className="flex items-center gap-3">
                                        <Calendar className="h-5 w-5 text-muted-foreground" />
                                        <div>
                                            <p className="text-sm font-medium">Fecha de Inicio</p>
                                            <p className="text-sm text-muted-foreground">
                                                {format(new Date(course.start_date), 'MMMM d, yyyy')}
                                            </p>
                                        </div>
                                    </div>
                                )}
                                {course.end_date && (
                                    <div className="flex items-center gap-3">
                                        <Calendar className="h-5 w-5 text-muted-foreground" />
                                        <div>
                                            <p className="text-sm font-medium">Fecha de Fin</p>
                                            <p className="text-sm text-muted-foreground">
                                                {format(new Date(course.end_date), 'MMMM d, yyyy')}
                                            </p>
                                        </div>
                                    </div>
                                )}
                                {course.duration_text && (
                                    <div className="flex items-center gap-3">
                                        <Clock className="h-5 w-5 text-muted-foreground" />
                                        <div>
                                            <p className="text-sm font-medium">Duracion</p>
                                            <p className="text-sm text-muted-foreground">
                                                {course.duration_text}
                                            </p>
                                        </div>
                                    </div>
                                )}
                                {course.schedule_text && (
                                    <div className="flex items-center gap-3">
                                        <MapPin className="h-5 w-5 text-muted-foreground" />
                                        <div>
                                            <p className="text-sm font-medium">Horario</p>
                                            <p className="text-sm text-muted-foreground">
                                                {course.schedule_text}
                                            </p>
                                        </div>
                                    </div>
                                )}
                                {course.enrollment_deadline && (
                                    <div className="flex items-center gap-3">
                                        <Calendar className="h-5 w-5 text-muted-foreground" />
                                        <div>
                                            <p className="text-sm font-medium">Limite Inscripcion</p>
                                            <p className="text-sm text-muted-foreground">
                                                {format(new Date(course.enrollment_deadline), 'MMMM d, yyyy')}
                                            </p>
                                        </div>
                                    </div>
                                )}
                            </CardContent>
                        </Card>

                        {/* Embed Snippet */}
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <ExternalLink className="h-5 w-5" />
                                    Embed
                                </CardTitle>
                                <CardDescription>Widget de inscripcion</CardDescription>
                            </CardHeader>
                            <CardContent>
                                <p className="text-sm text-muted-foreground">Proximamente</p>
                            </CardContent>
                        </Card>
                    </div>
                </div>

                {/* Recent Enrollments */}
                <Card>
                    <CardHeader className="flex flex-row items-center justify-between">
                        <div>
                            <CardTitle>Inscripciones Recientes</CardTitle>
                            <CardDescription>Ultimas 5 inscripciones</CardDescription>
                        </div>
                        <Link
                            href={`/courses/${course.id}/enrollments`}
                            className="text-sm font-medium text-primary hover:underline"
                        >
                            Ver todas
                        </Link>
                    </CardHeader>
                    <CardContent>
                        {recentEnrollments && recentEnrollments.length > 0 ? (
                            <div className="space-y-3">
                                {recentEnrollments.map((enrollment) => (
                                    <div
                                        key={enrollment.id}
                                        className="flex items-center justify-between rounded-lg border p-4"
                                    >
                                        <div>
                                            <p className="font-medium">{enrollment.customer_name}</p>
                                            <p className="text-sm text-muted-foreground">
                                                {enrollment.customer_email}
                                            </p>
                                        </div>
                                        <div className="flex items-center gap-2">
                                            <span
                                                className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold ${
                                                    statusColors[enrollment.status] ||
                                                    'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-200'
                                                }`}
                                            >
                                                {enrollment.status}
                                            </span>
                                            <Badge variant="outline" className="text-xs">
                                                {enrollment.payment_status}
                                            </Badge>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        ) : (
                            <p className="py-8 text-center text-sm text-muted-foreground">
                                No hay inscripciones aun
                            </p>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
