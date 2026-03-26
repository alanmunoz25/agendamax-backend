import { type FormEventHandler } from 'react';
import { useForm, Link } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Checkbox } from '@/components/ui/checkbox';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import InputError from '@/components/input-error';
import type { Course } from '@/types/models';
import { GraduationCap, Save, X } from 'lucide-react';
import { useState } from 'react';
import { useEditor, EditorContent } from '@tiptap/react';
import StarterKit from '@tiptap/starter-kit';
import TiptapLink from '@tiptap/extension-link';
import TiptapImage from '@tiptap/extension-image';

function SyllabusEditor({ value, onChange }: { value: string; onChange: (html: string) => void }) {
    const editor = useEditor({
        extensions: [
            StarterKit,
            TiptapLink.configure({ openOnClick: false }),
            TiptapImage,
        ],
        content: value,
        onUpdate: ({ editor }) => {
            onChange(editor.getHTML());
        },
    });

    if (!editor) return null;

    return (
        <div className="space-y-2">
            <div className="flex flex-wrap gap-1 rounded-t-md border border-b-0 bg-muted/50 p-1">
                <Button
                    type="button"
                    variant={editor.isActive('bold') ? 'default' : 'ghost'}
                    size="sm"
                    onClick={() => editor.chain().focus().toggleBold().run()}
                    className="h-8 px-2 text-xs"
                >
                    B
                </Button>
                <Button
                    type="button"
                    variant={editor.isActive('italic') ? 'default' : 'ghost'}
                    size="sm"
                    onClick={() => editor.chain().focus().toggleItalic().run()}
                    className="h-8 px-2 text-xs italic"
                >
                    I
                </Button>
                <Button
                    type="button"
                    variant={editor.isActive('heading', { level: 2 }) ? 'default' : 'ghost'}
                    size="sm"
                    onClick={() => editor.chain().focus().toggleHeading({ level: 2 }).run()}
                    className="h-8 px-2 text-xs"
                >
                    H2
                </Button>
                <Button
                    type="button"
                    variant={editor.isActive('heading', { level: 3 }) ? 'default' : 'ghost'}
                    size="sm"
                    onClick={() => editor.chain().focus().toggleHeading({ level: 3 }).run()}
                    className="h-8 px-2 text-xs"
                >
                    H3
                </Button>
                <Button
                    type="button"
                    variant={editor.isActive('bulletList') ? 'default' : 'ghost'}
                    size="sm"
                    onClick={() => editor.chain().focus().toggleBulletList().run()}
                    className="h-8 px-2 text-xs"
                >
                    Lista
                </Button>
                <Button
                    type="button"
                    variant={editor.isActive('orderedList') ? 'default' : 'ghost'}
                    size="sm"
                    onClick={() => editor.chain().focus().toggleOrderedList().run()}
                    className="h-8 px-2 text-xs"
                >
                    1. Lista
                </Button>
                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    onClick={() => {
                        const url = window.prompt('URL del enlace:');
                        if (url) {
                            editor.chain().focus().setLink({ href: url }).run();
                        }
                    }}
                    className="h-8 px-2 text-xs"
                >
                    Link
                </Button>
            </div>
            <EditorContent
                editor={editor}
                className="prose prose-sm dark:prose-invert min-h-[200px] max-w-none rounded-b-md border p-3 focus-within:ring-2 focus-within:ring-ring"
            />
        </div>
    );
}

interface Props {
    course: Course;
}

export default function EditCourse({ course }: Props) {
    const { data, setData, post, processing, errors, isDirty } = useForm({
        _method: 'PUT',
        title: course.title,
        description: course.description,
        syllabus: course.syllabus || '',
        cover_image: null as File | null,
        instructor_name: course.instructor_name || '',
        instructor_bio: course.instructor_bio || '',
        duration_text: course.duration_text || '',
        start_date: course.start_date || '',
        end_date: course.end_date || '',
        enrollment_deadline: course.enrollment_deadline || '',
        schedule_text: course.schedule_text || '',
        price: Number(course.price),
        currency: course.currency,
        capacity: (course.capacity ?? '') as number | string,
        modality: course.modality,
        is_active: course.is_active,
        is_featured: course.is_featured,
    });

    const [imagePreview, setImagePreview] = useState<string | null>(
        course.cover_image ? `/storage/${course.cover_image}` : null,
    );

    const handleImageChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        const file = e.target.files?.[0] || null;
        setData('cover_image', file);
        if (file) {
            setImagePreview(URL.createObjectURL(file));
        }
    };

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(`/courses/${course.id}`, {
            forceFormData: true,
        });
    };

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Dashboard', href: '/dashboard' },
                { title: 'Cursos', href: '/courses' },
                { title: course.title, href: `/courses/${course.id}` },
                { title: 'Editar' },
            ]}
        >
            <div className="mx-auto max-w-3xl space-y-6">
                {/* Header */}
                <div>
                    <h1 className="text-3xl font-bold tracking-tight text-foreground flex items-center gap-2">
                        <GraduationCap className="h-8 w-8" />
                        Editar Curso
                    </h1>
                    <p className="mt-2 text-sm text-muted-foreground">
                        Modifica los datos del curso
                    </p>
                </div>

                <form onSubmit={submit} className="space-y-6">
                    {/* Card 1: General Information */}
                    <Card>
                        <CardHeader>
                            <CardTitle>Informacion General</CardTitle>
                            <CardDescription>Datos basicos del curso</CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-6">
                            <div>
                                <Label htmlFor="title">Titulo *</Label>
                                <Input
                                    id="title"
                                    value={data.title}
                                    onChange={(e) => setData('title', e.target.value)}
                                    className="mt-1"
                                />
                                <InputError message={errors.title} className="mt-2" />
                            </div>

                            <div>
                                <Label htmlFor="description">Descripcion *</Label>
                                <Textarea
                                    id="description"
                                    value={data.description}
                                    onChange={(e) => setData('description', e.target.value)}
                                    className="mt-1"
                                    rows={4}
                                />
                                <InputError message={errors.description} className="mt-2" />
                            </div>

                            <div>
                                <Label htmlFor="modality">Modalidad *</Label>
                                <Select
                                    value={data.modality}
                                    onValueChange={(value) =>
                                        setData('modality', value as 'online' | 'presencial' | 'hybrid')
                                    }
                                >
                                    <SelectTrigger className="mt-1">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="presencial">Presencial</SelectItem>
                                        <SelectItem value="online">Online</SelectItem>
                                        <SelectItem value="hybrid">Hibrido</SelectItem>
                                    </SelectContent>
                                </Select>
                                <InputError message={errors.modality} className="mt-2" />
                            </div>

                            <div>
                                <Label htmlFor="cover_image">Imagen de Portada</Label>
                                <Input
                                    id="cover_image"
                                    type="file"
                                    accept="image/jpeg,image/png,image/webp"
                                    onChange={handleImageChange}
                                    className="mt-1"
                                />
                                <InputError message={errors.cover_image} className="mt-2" />
                                {imagePreview && (
                                    <img
                                        src={imagePreview}
                                        alt="Preview"
                                        className="mt-3 h-40 w-full rounded-md object-cover"
                                    />
                                )}
                            </div>
                        </CardContent>
                    </Card>

                    {/* Card 2: Syllabus */}
                    <Card>
                        <CardHeader>
                            <CardTitle>Temario</CardTitle>
                            <CardDescription>Contenido del curso (opcional)</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <SyllabusEditor
                                value={data.syllabus}
                                onChange={(html) => setData('syllabus', html)}
                            />
                            <InputError message={errors.syllabus} className="mt-2" />
                        </CardContent>
                    </Card>

                    {/* Card 3: Instructor */}
                    <Card>
                        <CardHeader>
                            <CardTitle>Instructor</CardTitle>
                            <CardDescription>Informacion del instructor (opcional)</CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-6">
                            <div>
                                <Label htmlFor="instructor_name">Nombre del Instructor</Label>
                                <Input
                                    id="instructor_name"
                                    value={data.instructor_name}
                                    onChange={(e) => setData('instructor_name', e.target.value)}
                                    className="mt-1"
                                />
                                <InputError message={errors.instructor_name} className="mt-2" />
                            </div>

                            <div>
                                <Label htmlFor="instructor_bio">Biografia del Instructor</Label>
                                <Textarea
                                    id="instructor_bio"
                                    value={data.instructor_bio}
                                    onChange={(e) => setData('instructor_bio', e.target.value)}
                                    className="mt-1"
                                    rows={3}
                                />
                                <InputError message={errors.instructor_bio} className="mt-2" />
                            </div>
                        </CardContent>
                    </Card>

                    {/* Card 4: Dates & Schedule */}
                    <Card>
                        <CardHeader>
                            <CardTitle>Fechas y Horario</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-6">
                            <div className="grid gap-6 sm:grid-cols-2">
                                <div>
                                    <Label htmlFor="start_date">Fecha de Inicio</Label>
                                    <Input
                                        id="start_date"
                                        type="date"
                                        value={data.start_date}
                                        onChange={(e) => setData('start_date', e.target.value)}
                                        className="mt-1"
                                    />
                                    <InputError message={errors.start_date} className="mt-2" />
                                </div>

                                <div>
                                    <Label htmlFor="end_date">Fecha de Fin</Label>
                                    <Input
                                        id="end_date"
                                        type="date"
                                        value={data.end_date}
                                        onChange={(e) => setData('end_date', e.target.value)}
                                        className="mt-1"
                                    />
                                    <InputError message={errors.end_date} className="mt-2" />
                                </div>
                            </div>

                            <div>
                                <Label htmlFor="enrollment_deadline">Fecha Limite de Inscripcion</Label>
                                <Input
                                    id="enrollment_deadline"
                                    type="date"
                                    value={data.enrollment_deadline}
                                    onChange={(e) => setData('enrollment_deadline', e.target.value)}
                                    className="mt-1"
                                />
                                <InputError message={errors.enrollment_deadline} className="mt-2" />
                            </div>

                            <div>
                                <Label htmlFor="duration_text">Duracion</Label>
                                <Input
                                    id="duration_text"
                                    value={data.duration_text}
                                    onChange={(e) => setData('duration_text', e.target.value)}
                                    className="mt-1"
                                />
                                <InputError message={errors.duration_text} className="mt-2" />
                            </div>

                            <div>
                                <Label htmlFor="schedule_text">Horario</Label>
                                <Input
                                    id="schedule_text"
                                    value={data.schedule_text}
                                    onChange={(e) => setData('schedule_text', e.target.value)}
                                    className="mt-1"
                                />
                                <InputError message={errors.schedule_text} className="mt-2" />
                            </div>
                        </CardContent>
                    </Card>

                    {/* Card 5: Price & Capacity */}
                    <Card>
                        <CardHeader>
                            <CardTitle>Precio y Capacidad</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-6">
                            <div>
                                <Label htmlFor="price">Precio (DOP) *</Label>
                                <Input
                                    id="price"
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    value={data.price}
                                    onChange={(e) => setData('price', parseFloat(e.target.value) || 0)}
                                    className="mt-1"
                                />
                                <InputError message={errors.price} className="mt-2" />
                            </div>

                            <div>
                                <Label htmlFor="capacity">Capacidad</Label>
                                <Input
                                    id="capacity"
                                    type="number"
                                    min="1"
                                    value={data.capacity}
                                    onChange={(e) => setData('capacity', e.target.value ? parseInt(e.target.value) : '')}
                                    placeholder="Ilimitado si vacio"
                                    className="mt-1"
                                />
                                <InputError message={errors.capacity} className="mt-2" />
                            </div>
                        </CardContent>
                    </Card>

                    {/* Card 6: Configuration */}
                    <Card>
                        <CardHeader>
                            <CardTitle>Configuracion</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="flex items-center space-x-2">
                                <Checkbox
                                    id="is_active"
                                    checked={data.is_active}
                                    onCheckedChange={(checked) => setData('is_active', checked === true)}
                                />
                                <Label htmlFor="is_active">Curso activo</Label>
                            </div>
                            <div className="flex items-center space-x-2">
                                <Checkbox
                                    id="is_featured"
                                    checked={data.is_featured}
                                    onCheckedChange={(checked) => setData('is_featured', checked === true)}
                                />
                                <Label htmlFor="is_featured">Curso destacado</Label>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Actions */}
                    <div className="flex items-center justify-between gap-4">
                        <Link
                            href={`/courses/${course.id}`}
                            className="inline-flex items-center justify-center rounded-md text-sm font-medium ring-offset-background transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 border border-input bg-background hover:bg-accent hover:text-accent-foreground h-10 px-4 py-2"
                        >
                            <X className="mr-2 h-4 w-4" />
                            Cancelar
                        </Link>
                        <Button type="submit" disabled={processing || !isDirty}>
                            <Save className="mr-2 h-4 w-4" />
                            {processing ? 'Guardando...' : 'Guardar Cambios'}
                        </Button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
