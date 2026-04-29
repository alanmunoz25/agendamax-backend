import { router } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { DataTable, type Column } from '@/components/data-table';
import { EmptyState } from '@/components/empty-state';
import type { Course, PaginatedResponse } from '@/types/models';
import { GraduationCap, Plus, Eye, Search } from 'lucide-react';
import { format } from 'date-fns';
import { useState } from 'react';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

interface Filters {
    search?: string;
    is_active?: string;
    modality?: string;
    sort?: string;
    direction?: 'asc' | 'desc';
}

interface Props {
    courses: PaginatedResponse<Course>;
    filters: Filters;
    can: {
        create: boolean;
    };
}

const modalityLabels: Record<string, string> = {
    online: 'Online',
    presencial: 'Presencial',
    hybrid: 'Híbrido',
};

export default function CoursesIndex({ courses, filters, can }: Props) {
    const [searchQuery, setSearchQuery] = useState(filters.search || '');

    const handleSearch = (value: string) => {
        setSearchQuery(value);
        router.get(
            '/courses',
            { ...filters, search: value || undefined, page: undefined },
            { preserveState: true, replace: true },
        );
    };

    const handleFilter = (key: string, value: string | undefined) => {
        router.get(
            '/courses',
            { ...filters, [key]: value, page: undefined },
            { preserveState: true, replace: true },
        );
    };

    const columns: Column<Course>[] = [
        {
            key: 'title',
            label: 'Curso',
            sortable: true,
            render: (course) => (
                <div>
                    <span className="font-medium text-foreground">{course.title}</span>
                    {course.is_featured && (
                        <Badge variant="secondary" className="ml-2">Destacado</Badge>
                    )}
                </div>
            ),
        },
        {
            key: 'modality',
            label: 'Modalidad',
            render: (course) => (
                <Badge variant="outline">
                    {modalityLabels[course.modality] || course.modality}
                </Badge>
            ),
        },
        {
            key: 'price',
            label: 'Precio',
            render: (course) => (
                <span className="text-sm font-medium">
                    {Number(course.price) > 0
                        ? `${course.currency} ${Number(course.price).toLocaleString('es-DO', { minimumFractionDigits: 2 })}`
                        : 'Gratis'}
                </span>
            ),
        },
        {
            key: 'capacity',
            label: 'Inscritos',
            render: (course) => (
                <span className="text-sm text-muted-foreground">
                    {course.enrollments_count || 0}
                    {course.capacity ? ` / ${course.capacity}` : ''}
                </span>
            ),
        },
        {
            key: 'is_active',
            label: 'Estado',
            render: (course) => (
                <Badge variant={course.is_active ? 'default' : 'secondary'}>
                    {course.is_active ? 'Activo' : 'Inactivo'}
                </Badge>
            ),
        },
        {
            key: 'start_date',
            label: 'Inicio',
            render: (course) => (
                <span className="text-sm text-muted-foreground">
                    {course.start_date
                        ? format(new Date(course.start_date), 'MMM d, yyyy')
                        : '—'}
                </span>
            ),
        },
        {
            key: 'actions',
            label: '',
            render: (course) => (
                <div className="flex items-center justify-end gap-2">
                    <Button
                        variant="ghost"
                        size="sm"
                        onClick={(e) => {
                            e.stopPropagation();
                            router.get(`/courses/${course.id}`);
                        }}
                    >
                        <Eye className="h-4 w-4" />
                        Ver
                    </Button>
                </div>
            ),
        },
    ];

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Dashboard', href: '/dashboard' },
                { title: 'Cursos', href: '/courses' },
            ]}
        >
            <div className="space-y-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-3xl font-bold tracking-tight text-foreground flex items-center gap-2">
                            <GraduationCap className="h-8 w-8" />
                            Cursos
                        </h1>
                        <p className="mt-2 text-sm text-muted-foreground">
                            Gestiona los cursos y talleres de tu negocio
                        </p>
                    </div>
                    {can.create && (
                        <Button onClick={() => router.get('/courses/create')}>
                            <Plus className="mr-2 h-4 w-4" />
                            Crear Curso
                        </Button>
                    )}
                </div>

                {/* Search & Filters */}
                <div className="flex flex-col gap-4 sm:flex-row">
                    <div className="relative flex-1">
                        <Search className="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            placeholder="Buscar por titulo o instructor..."
                            value={searchQuery}
                            onChange={(e) => handleSearch(e.target.value)}
                            className="pl-9"
                        />
                    </div>
                    <Select
                        value={filters.is_active ?? 'all'}
                        onValueChange={(value) =>
                            handleFilter('is_active', value === 'all' ? undefined : value)
                        }
                    >
                        <SelectTrigger className="w-[160px]">
                            <SelectValue placeholder="Estado" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">Todos</SelectItem>
                            <SelectItem value="1">Activos</SelectItem>
                            <SelectItem value="0">Inactivos</SelectItem>
                        </SelectContent>
                    </Select>
                    <Select
                        value={filters.modality ?? 'all'}
                        onValueChange={(value) =>
                            handleFilter('modality', value === 'all' ? undefined : value)
                        }
                    >
                        <SelectTrigger className="w-[160px]">
                            <SelectValue placeholder="Modalidad" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">Todas</SelectItem>
                            <SelectItem value="online">Online</SelectItem>
                            <SelectItem value="presencial">Presencial</SelectItem>
                            <SelectItem value="hybrid">Híbrido</SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                {/* Data Table */}
                {courses.data.length > 0 ? (
                    <DataTable
                        data={courses.data}
                        columns={columns}
                        onRowClick={(course) => router.get(`/courses/${course.id}`)}
                        pagination={{
                            currentPage: courses.current_page,
                            lastPage: courses.last_page,
                            onPageChange: (page) =>
                                router.get('/courses', { ...filters, page }, { preserveState: true, replace: true }),
                        }}
                    />
                ) : (
                    <EmptyState
                        icon={GraduationCap}
                        title="No hay cursos"
                        description={
                            can.create
                                ? 'Crea tu primer curso para empezar a recibir inscripciones.'
                                : 'No hay cursos disponibles.'
                        }
                        action={
                            can.create
                                ? {
                                      label: 'Crear Primer Curso',
                                      onClick: () => router.get('/courses/create'),
                                  }
                                : undefined
                        }
                    />
                )}
            </div>
        </AppLayout>
    );
}
