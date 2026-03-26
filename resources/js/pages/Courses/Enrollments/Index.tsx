import { router } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { DataTable, type Column } from '@/components/data-table';
import { EmptyState } from '@/components/empty-state';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
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
import type { Course, Enrollment, PaginatedResponse } from '@/types/models';
import {
    ArrowLeft,
    Users,
    CheckCircle,
    UserPlus,
    DollarSign,
    Download,
    XCircle,
    Trash2,
} from 'lucide-react';
import { format } from 'date-fns';
import { Link } from '@inertiajs/react';

const statusColors: Record<string, string> = {
    lead: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
    confirmed: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
    cancelled: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
    waitlisted: 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
};

const paymentStatusColors: Record<string, string> = {
    pending: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
    paid: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
    refunded: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
    free: 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-200',
};

interface Filters {
    status?: string;
    payment_status?: string;
}

interface Stats {
    total: number;
    confirmed: number;
    leads: number;
    revenue: number;
}

interface Props {
    course: Course;
    enrollments: PaginatedResponse<Enrollment>;
    stats: Stats;
    filters: Filters;
}

export default function EnrollmentsIndex({ course, enrollments, stats, filters }: Props) {
    const handleFilter = (key: string, value: string | undefined) => {
        router.get(
            `/courses/${course.id}/enrollments`,
            { ...filters, [key]: value, page: undefined },
            { preserveState: true, replace: true },
        );
    };

    const handleStatusUpdate = (enrollment: Enrollment, status: string) => {
        router.patch(`/enrollments/${enrollment.id}/status`, { status }, {
            preserveState: true,
        });
    };

    const handleDelete = (enrollment: Enrollment) => {
        router.delete(`/enrollments/${enrollment.id}`, {
            preserveState: true,
        });
    };

    const columns: Column<Enrollment>[] = [
        {
            key: 'customer_name',
            label: 'Nombre',
            render: (enrollment) => (
                <div>
                    <span className="font-medium text-foreground">{enrollment.customer_name}</span>
                    <div className="text-sm text-muted-foreground">{enrollment.customer_email}</div>
                </div>
            ),
        },
        {
            key: 'customer_phone',
            label: 'Telefono',
            render: (enrollment) => (
                <span className="text-sm text-muted-foreground">
                    {enrollment.customer_phone || '—'}
                </span>
            ),
        },
        {
            key: 'status',
            label: 'Estado',
            render: (enrollment) => (
                <span
                    className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold ${
                        statusColors[enrollment.status] || 'bg-gray-100 text-gray-800'
                    }`}
                >
                    {enrollment.status}
                </span>
            ),
        },
        {
            key: 'payment_status',
            label: 'Pago',
            render: (enrollment) => (
                <span
                    className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold ${
                        paymentStatusColors[enrollment.payment_status] || 'bg-gray-100 text-gray-800'
                    }`}
                >
                    {enrollment.payment_status}
                </span>
            ),
        },
        {
            key: 'enrolled_at',
            label: 'Fecha',
            render: (enrollment) => (
                <span className="text-sm text-muted-foreground">
                    {enrollment.enrolled_at
                        ? format(new Date(enrollment.enrolled_at), 'MMM d, yyyy')
                        : format(new Date(enrollment.created_at), 'MMM d, yyyy')}
                </span>
            ),
        },
        {
            key: 'actions',
            label: '',
            render: (enrollment) => (
                <div className="flex items-center justify-end gap-1" onClick={(e) => e.stopPropagation()}>
                    {enrollment.status === 'lead' && (
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={() => handleStatusUpdate(enrollment, 'confirmed')}
                            title="Confirmar"
                        >
                            <CheckCircle className="h-4 w-4 text-green-600" />
                        </Button>
                    )}
                    {enrollment.status !== 'cancelled' && (
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={() => handleStatusUpdate(enrollment, 'cancelled')}
                            title="Cancelar"
                        >
                            <XCircle className="h-4 w-4 text-red-600" />
                        </Button>
                    )}
                    <AlertDialog>
                        <AlertDialogTrigger asChild>
                            <Button variant="ghost" size="sm" title="Eliminar">
                                <Trash2 className="h-4 w-4 text-red-600" />
                            </Button>
                        </AlertDialogTrigger>
                        <AlertDialogContent>
                            <AlertDialogHeader>
                                <AlertDialogTitle>Eliminar Inscripcion</AlertDialogTitle>
                                <AlertDialogDescription>
                                    Se eliminara la inscripcion de {enrollment.customer_name}. Esta accion
                                    no se puede deshacer.
                                </AlertDialogDescription>
                            </AlertDialogHeader>
                            <AlertDialogFooter>
                                <AlertDialogCancel>Cancelar</AlertDialogCancel>
                                <AlertDialogAction onClick={() => handleDelete(enrollment)}>
                                    Eliminar
                                </AlertDialogAction>
                            </AlertDialogFooter>
                        </AlertDialogContent>
                    </AlertDialog>
                </div>
            ),
        },
    ];

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Dashboard', href: '/dashboard' },
                { title: 'Cursos', href: '/courses' },
                { title: course.title, href: `/courses/${course.id}` },
                { title: 'Inscripciones' },
            ]}
        >
            <div className="space-y-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-3xl font-bold tracking-tight text-foreground">
                            Inscripciones
                        </h1>
                        <p className="mt-1 text-sm text-muted-foreground">{course.title}</p>
                    </div>
                    <div className="flex gap-2">
                        <Button variant="outline" asChild>
                            <Link href={`/courses/${course.id}`}>
                                <ArrowLeft className="mr-2 h-4 w-4" />
                                Volver al Curso
                            </Link>
                        </Button>
                        <Button variant="outline" asChild>
                            <a href={`/courses/${course.id}/enrollments/export`} download>
                                <Download className="mr-2 h-4 w-4" />
                                Exportar CSV
                            </a>
                        </Button>
                    </div>
                </div>

                {/* Stats */}
                <div className="grid gap-4 sm:grid-cols-4">
                    <Card>
                        <CardContent className="flex items-center gap-3 pt-6">
                            <Users className="h-8 w-8 text-muted-foreground" />
                            <div>
                                <p className="text-2xl font-bold">{stats.total}</p>
                                <p className="text-xs text-muted-foreground">Total Inscritos</p>
                            </div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent className="flex items-center gap-3 pt-6">
                            <CheckCircle className="h-8 w-8 text-green-600" />
                            <div>
                                <p className="text-2xl font-bold">{stats.confirmed}</p>
                                <p className="text-xs text-muted-foreground">Confirmados</p>
                            </div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent className="flex items-center gap-3 pt-6">
                            <UserPlus className="h-8 w-8 text-yellow-600" />
                            <div>
                                <p className="text-2xl font-bold">{stats.leads}</p>
                                <p className="text-xs text-muted-foreground">Leads</p>
                            </div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent className="flex items-center gap-3 pt-6">
                            <DollarSign className="h-8 w-8 text-muted-foreground" />
                            <div>
                                <p className="text-2xl font-bold">
                                    {course.currency}{' '}
                                    {stats.revenue.toLocaleString('es-DO', { minimumFractionDigits: 2 })}
                                </p>
                                <p className="text-xs text-muted-foreground">Revenue</p>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                {/* Filters */}
                <div className="flex gap-4">
                    <Select
                        value={filters.status ?? 'all'}
                        onValueChange={(value) =>
                            handleFilter('status', value === 'all' ? undefined : value)
                        }
                    >
                        <SelectTrigger className="w-[160px]">
                            <SelectValue placeholder="Estado" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">Todos</SelectItem>
                            <SelectItem value="lead">Lead</SelectItem>
                            <SelectItem value="confirmed">Confirmado</SelectItem>
                            <SelectItem value="cancelled">Cancelado</SelectItem>
                        </SelectContent>
                    </Select>
                    <Select
                        value={filters.payment_status ?? 'all'}
                        onValueChange={(value) =>
                            handleFilter('payment_status', value === 'all' ? undefined : value)
                        }
                    >
                        <SelectTrigger className="w-[160px]">
                            <SelectValue placeholder="Pago" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">Todos</SelectItem>
                            <SelectItem value="pending">Pendiente</SelectItem>
                            <SelectItem value="paid">Pagado</SelectItem>
                            <SelectItem value="free">Gratis</SelectItem>
                            <SelectItem value="refunded">Reembolsado</SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                {/* Data Table */}
                {enrollments.data.length > 0 ? (
                    <DataTable
                        data={enrollments.data}
                        columns={columns}
                        pagination={{
                            currentPage: enrollments.current_page,
                            lastPage: enrollments.last_page,
                            onPageChange: (page) =>
                                router.get(
                                    `/courses/${course.id}/enrollments`,
                                    { ...filters, page },
                                    { preserveState: true, replace: true },
                                ),
                        }}
                    />
                ) : (
                    <EmptyState
                        icon={Users}
                        title="No hay inscripciones"
                        description="Aun no hay inscripciones para este curso."
                    />
                )}
            </div>
        </AppLayout>
    );
}
