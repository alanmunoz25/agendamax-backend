# 📘 PRD - AgendaMax Cursos (Fase 1 - Headless + Embed)

## 🧠 1. Overview

AgendaMax es un SaaS de gestión de citas que evoluciona hacia una plataforma **headless** capaz de permitir a negocios vender cursos directamente desde sus páginas web mediante:

- API REST
- SDK embebible (JavaScript)

Este PRD define la implementación del **Módulo de Cursos (Fase 1)** enfocado en:
- Creación de cursos
- Publicación externa vía embed
- Captura de leads
- Procesamiento de pagos
- Gestión centralizada

---

## 🎯 2. Objetivos del Producto

### Objetivo Principal
Permitir a negocios crear cursos en AgendaMax y venderlos desde sus propias webs sin necesidad de desarrollo adicional.

### Objetivos Secundarios
- Incrementar el valor del SaaS (upsell)
- Expandir mercado hacia academias y formadores
- Centralizar leads y pagos
- Mantener simplicidad (no LMS completo en Fase 1)

---

## 👤 3. User Personas

### Negocio (Admin)
- Dueño de salón, academia o servicio
- Quiere vender cursos fácilmente
- No necesariamente técnico

### Cliente Final (Estudiante)
- Quiere inscribirse en un curso
- Realiza pago
- Espera confirmación rápida

### Integrador (Opcional)
- Desarrollador del negocio
- Usa API para integración custom

---

## 🧩 4. Alcance (Scope)

### ✅ Incluido (Fase 1)
- CRUD de cursos
- API pública de cursos
- SDK embebible
- Inscripción (enrollment)
- Pagos (one-time)
- Gestión de leads

### ❌ No incluido
- LMS (lecciones, videos)
- Certificados
- Evaluaciones
- Comunidad
- Progreso de usuario

---

## 🏗️ 5. Arquitectura

### Modelo: Headless SaaS
AgendaMax (Backend)
├── Cursos
├── Leads
├── Pagos
├── API REST
└── SDK Embed

Cliente Web
└── Consume SDK o API

---

## 🧱 6. Modelo de Datos

### Course

```ts
Course {
  id: string
  business_id: string
  title: string
  description: string
  cover_image: string
  duration_text: string
  price: number
  capacity: number
  modality: 'online' | 'presencial'
  is_active: boolean
  created_at: Date
}

Enrollment {
  id: string
  course_id: string
  business_id: string
  customer_name: string
  customer_email: string
  customer_phone: string
  status: 'lead' | 'confirmed'
  payment_status: 'pending' | 'paid'
  created_at: Date
}

Lead {
  id: string
  business_id: string
  name: string
  email: string
  phone: string
  source: 'course'
  source_id: string
  tag: string
}
```
🔌 7. API Specification
GET Courses
GET /api/courses?business_id={id}
Response
[
{
"id": "course_1",
"title": "Certificación en Cejas",
"price": 598
}
]
GET Course Detail
GET /api/courses/{id}
POST Enrollment
POST /api/enrollments
Body
{
"course_id": "course_1",
"name": "Juan Perez",
"email": "juan@email.com",
"phone": "8090000000"
}
🧠 8. SDK Embebible
Script
<script src="https://agendamax.app/embed.js"></script>
Inicialización
<div id="agendamax-course"></div>

<script>
  AgendaMax.init({
    businessId: "BUSINESS_ID",
    courseId: "COURSE_ID",
    container: "#agendamax-course"
  });
</script>
Responsabilidades del SDK

Fetch de curso desde API

Render UI (card del curso)

Mostrar:

título

descripción

precio

Formulario de inscripción

Integración con pagos

Confirmación de inscripción

💳 9. Flujo de Pago
Integración inicial

Stripe Checkout (recomendado)

Flujo

Usuario hace clic en "Inscribirme"

Completa formulario

Redirección a Stripe

Pago exitoso

Webhook confirma pago

Enrollment → paid

🔄 10. Flujos del Sistema
Flujo Negocio

Crear curso

Copiar script embed

Insertar en web

Recibir leads

Flujo Usuario

Ver curso en web

Click en inscribirse

Completar datos

Pagar

Confirmación

📊 11. Dashboard
Cursos

Lista

Cantidad inscritos

Leads

Nombre

Email

Curso

Estado pago

⚙️ 12. Requisitos Técnicos
Backend

Node.js (Express o NestJS)

Base de datos (PostgreSQL recomendado)

Frontend

Next.js (panel admin)

SDK

Vanilla JS o pequeño bundle (Vite recomendado)

Pagos

Stripe

🔐 13. Seguridad

Validar business_id en todos los endpoints

Sanitizar inputs

Rate limiting básico

Webhooks seguros (Stripe signature)

📈 14. Métricas Clave

Cursos creados

Inscripciones

Conversión (visita → pago)

Leads generados

🚀 15. Roadmap
Fase 1 (este PRD)

Cursos básicos + embed + pagos

Fase 2

Módulos y contenido

Panel estudiante

Fase 3

Certificados

Comunidad

🧠 16. Consideraciones Técnicas para Claude Code

Mantener código modular

Separar lógica de:

Courses

Enrollments

Payments

Crear SDK desacoplado

Usar REST limpio

Preparar sistema para multi-tenant

✅ 17. Definition of Done

Se puede crear un curso

Se puede embeder en web externa

Usuario puede inscribirse

Usuario puede pagar

Lead aparece en dashboard

Enrollment queda registrado