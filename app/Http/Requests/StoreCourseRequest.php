<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Course;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class StoreCourseRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Gate::allows('create', Course::class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'min:3', 'max:255'],
            'description' => ['required', 'string'],
            'syllabus' => ['nullable', 'string'],
            'cover_image' => ['nullable', 'image', 'mimes:jpeg,png,webp', 'max:5120'],
            'instructor_name' => ['nullable', 'string', 'max:255'],
            'instructor_bio' => ['nullable', 'string', 'max:1000'],
            'instructor_image' => ['nullable', 'image', 'mimes:jpeg,png,webp', 'max:5120'],
            'duration_text' => ['nullable', 'string', 'max:255'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'enrollment_deadline' => ['nullable', 'date'],
            'schedule_text' => ['nullable', 'string', 'max:255'],
            'price' => ['required', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'max:10'],
            'capacity' => ['nullable', 'integer', 'min:1'],
            'modality' => ['required', 'in:online,presencial,hybrid'],
            'is_active' => ['nullable', 'boolean'],
            'is_featured' => ['nullable', 'boolean'],
            'meta' => ['nullable', 'array'],
        ];
    }

    /**
     * Get custom error messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'title.required' => 'El titulo del curso es obligatorio.',
            'title.min' => 'El titulo debe tener al menos :min caracteres.',
            'title.max' => 'El titulo no puede exceder :max caracteres.',
            'description.required' => 'La descripcion del curso es obligatoria.',
            'price.required' => 'El precio es obligatorio.',
            'price.numeric' => 'El precio debe ser un numero valido.',
            'price.min' => 'El precio no puede ser negativo.',
            'capacity.min' => 'La capacidad debe ser al menos :min.',
            'modality.required' => 'La modalidad es obligatoria.',
            'modality.in' => 'La modalidad debe ser online, presencial o hybrid.',
            'cover_image.image' => 'La imagen de portada debe ser una imagen valida.',
            'cover_image.mimes' => 'La imagen debe ser JPEG, PNG o WebP.',
            'cover_image.max' => 'La imagen no puede exceder 5MB.',
            'end_date.after_or_equal' => 'La fecha de fin debe ser igual o posterior a la fecha de inicio.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'title' => 'titulo',
            'description' => 'descripcion',
            'syllabus' => 'temario',
            'cover_image' => 'imagen de portada',
            'instructor_name' => 'nombre del instructor',
            'instructor_bio' => 'biografia del instructor',
            'duration_text' => 'duracion',
            'start_date' => 'fecha de inicio',
            'end_date' => 'fecha de fin',
            'enrollment_deadline' => 'fecha limite de inscripcion',
            'schedule_text' => 'horario',
            'price' => 'precio',
            'currency' => 'moneda',
            'capacity' => 'capacidad',
            'modality' => 'modalidad',
        ];
    }
}
