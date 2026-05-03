/**
 * Core domain models matching backend Laravel models
 *
 * These types represent the structure of data returned from the backend API
 * and used throughout the frontend application.
 */

export interface Business {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    address: string | null;
    phone: string | null;
    email: string | null;
    logo_url: string | null;
    invitation_code: string;
    loyalty_stamps_required: number;
    loyalty_reward_description: string | null;
    status: 'active' | 'inactive' | 'suspended';
    timezone: string;
    settings: Record<string, unknown> | null;
    created_at: string;
    updated_at: string;
    // Aggregates (from withCount)
    users_count?: number;
    employees_count?: number;
    services_count?: number;
}

export interface ServiceCategory {
    id: number;
    business_id: number;
    parent_id: number | null;
    name: string;
    description: string | null;
    sort_order: number;
    is_active: boolean;
    created_at: string;
    updated_at: string;
    slug?: string | null;
    image_path?: string | null;
    image_url?: string | null;
    // Relationships
    parent?: ServiceCategory;
    children?: ServiceCategory[];
    services?: Service[];
    // Aggregates (from withCount)
    services_count?: number;
    children_count?: number;
}

export interface Service {
    id: number;
    business_id: number;
    service_category_id: number | null;
    name: string;
    description: string | null;
    duration: number; // minutes
    price: number; // decimal as number
    category: string | null;
    is_active: boolean;
    created_at: string;
    updated_at: string;
    // Relationships
    business?: Business;
    service_category?: ServiceCategory;
}

export interface Employee {
    id: number;
    business_id: number;
    user_id: number;
    bio: string | null;
    photo_url: string | null;
    is_active: boolean;
    created_at: string;
    updated_at: string;
    // Relationships
    user?: User;
    business?: Business;
    services?: Service[];
    schedules?: EmployeeSchedule[];
}

export interface EmployeeSchedule {
    id: number;
    employee_id: number;
    day_of_week: number; // 0 = Sunday, 6 = Saturday
    start_time: string; // HH:MM format
    end_time: string; // HH:MM format
    is_available: boolean;
    created_at: string;
    updated_at: string;
    // Relationships
    employee?: Employee;
}

export type AppointmentStatus = 'pending' | 'confirmed' | 'completed' | 'cancelled';

export interface AppointmentServicePivot {
    appointment_id: number;
    service_id: number;
    employee_id: number | null;
}

export interface AppointmentServiceEntry extends Service {
    pivot: AppointmentServicePivot;
}

export interface Appointment {
    id: number;
    business_id: number;
    client_id: number;
    employee_id: number | null;
    service_id: number | null;
    scheduled_at: string; // ISO 8601 datetime
    scheduled_until: string; // ISO 8601 datetime
    status: AppointmentStatus;
    notes: string | null;
    cancellation_reason: string | null;
    qr_code: string | null;
    created_at: string;
    updated_at: string;
    // Relationships
    client?: User;
    employee?: Employee;
    service?: Service;
    services?: AppointmentServiceEntry[];
    business?: Business;
    visit?: Visit;
}

export interface Visit {
    id: number;
    business_id: number;
    appointment_id: number;
    verified_at: string; // ISO 8601 datetime
    verified_by: number | null; // employee user_id
    created_at: string;
    updated_at: string;
    // Relationships
    appointment?: Appointment;
    business?: Business;
    verifier?: User;
}

export interface Stamp {
    id: number;
    business_id: number;
    client_id: number;
    visit_id: number | null;
    appointment_id: number | null;
    earned_at: string; // ISO 8601 datetime
    redeemed_at: string | null; // ISO 8601 datetime
    created_at: string;
    updated_at: string;
    // Relationships
    client?: User;
    business?: Business;
    visit?: Visit;
    appointment?: Appointment;
}

export interface QrCode {
    id: number;
    business_id: number;
    code: string;
    type: string;
    reward_description: string;
    stamps_required: number;
    is_active: boolean;
    image_path: string | null;
    image_url?: string | null;
    created_at: string;
    updated_at: string;
}

export interface Offer {
    id: number;
    business_id: number;
    title: string;
    description: string | null;
    discount_type: 'percentage' | 'fixed' | 'free_service';
    discount_value: number;
    valid_from: string; // ISO 8601 datetime
    valid_until: string; // ISO 8601 datetime
    is_active: boolean;
    created_at: string;
    updated_at: string;
    // Relationships
    business?: Business;
}

export interface Promotion {
    id: number;
    business_id: number;
    title: string;
    image_path: string;
    image_url?: string;
    url: string | null;
    expires_at: string | null;
    is_active: boolean;
    created_at: string;
    updated_at: string;
}

export interface User {
    id: number;
    business_id: number | null;
    name: string;
    email: string;
    phone: string | null;
    birthday_day: number | null;
    birthday_month: number | null;
    role: 'super_admin' | 'business_admin' | 'employee' | 'client' | 'lead';
    avatar?: string;
    email_verified_at: string | null;
    two_factor_enabled?: boolean;
    fcm_token: string | null;
    created_at: string;
    updated_at: string;
    // Relationships
    business?: Business;
    employee?: Employee;
}

/**
 * DTOs and response types
 */

export interface LoyaltyProgress {
    current_stamps: number;
    stamps_required: number;
    remaining_stamps: number;
    reward_description: string | null;
    is_eligible: boolean;
    eligible_redemptions_count: number;
}

export interface AvailabilitySlot {
    start_time: string; // ISO 8601 datetime
    end_time: string; // ISO 8601 datetime
    is_available: boolean;
}

export interface AppointmentAvailability {
    date: string; // YYYY-MM-DD
    employee_id: number;
    service_id: number;
    slots: AvailabilitySlot[];
}

export interface DashboardStats {
    today_appointments_count: number;
    upcoming_appointments_count: number;
    total_clients: number;
    total_active_employees: number;
    revenue_this_month?: number;
}

export interface RecentActivity {
    type: 'appointment_created' | 'appointment_completed' | 'client_added' | 'visit_verified';
    title: string;
    description: string;
    timestamp: string;
    user?: User;
    appointment?: Appointment;
}

/**
 * Paginated response wrapper
 */
export interface PaginatedResponse<T> {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number;
    to: number;
}

/**
 * Form data types
 */

export interface BusinessFormData {
    name: string;
    description: string | null;
    address: string | null;
    phone: string | null;
    email: string | null;
    loyalty_stamps_required: number;
    loyalty_reward_description: string | null;
}

export interface ServiceFormData {
    name: string;
    description: string | null;
    duration: number;
    price: number;
    category: string | null;
    service_category_id: number | null;
    is_active: boolean;
}

export interface EmployeeFormData {
    user_id: number;
    bio: string | null;
    is_active: boolean;
    service_ids: number[];
}

export interface EmployeeScheduleFormData {
    day_of_week: number;
    start_time: string;
    end_time: string;
    is_available: boolean;
}

export interface AppointmentFormData {
    client_id: number;
    employee_id: number;
    service_id: number;
    scheduled_at: string;
    notes: string | null;
}

export interface ClientFormData {
    name: string;
    email: string;
    phone: string | null;
    birthday_day: number | null;
    birthday_month: number | null;
}

export interface Course {
    id: number;
    business_id: number;
    title: string;
    slug: string;
    description: string;
    syllabus: string | null;
    cover_image: string | null;
    instructor_name: string | null;
    instructor_bio: string | null;
    instructor_image: string | null;
    duration_text: string | null;
    start_date: string | null;
    end_date: string | null;
    enrollment_deadline: string | null;
    schedule_text: string | null;
    price: number;
    currency: string;
    capacity: number | null;
    modality: 'online' | 'presencial' | 'hybrid';
    is_active: boolean;
    is_featured: boolean;
    meta: Record<string, unknown> | null;
    created_at: string;
    updated_at: string;
    remaining_capacity: number | null;
    enrollments_count?: number;
    business?: Business;
}

export interface Enrollment {
    id: number;
    business_id: number;
    course_id: number;
    user_id: number | null;
    customer_name: string;
    customer_email: string;
    customer_phone: string | null;
    status: 'lead' | 'confirmed' | 'cancelled' | 'waitlisted';
    payment_status: 'pending' | 'paid' | 'refunded' | 'free';
    payment_provider: string | null;
    payment_reference: string | null;
    amount_paid: number | null;
    enrolled_at: string | null;
    notes: string | null;
    created_at: string;
    updated_at: string;
    course?: Course;
    user?: User;
}

// ─── Payroll ───────────────────────────────────────────────────────────────

export type PayrollPeriodStatus = 'open' | 'closed';
export type PayrollRecordStatus = 'draft' | 'approved' | 'paid' | 'voided';
export type PayrollAdjustmentType = 'credit' | 'debit';

export interface PayrollPeriod {
    id: number;
    business_id: number;
    starts_on: string; // YYYY-MM-DD
    ends_on: string; // YYYY-MM-DD
    status: PayrollPeriodStatus;
    closed_at: string | null;
    created_at: string;
    updated_at: string;
    // Aggregates
    records_count?: number;
    paid_count?: number;
    has_records?: boolean;
}

export interface CommissionRecord {
    id: number;
    business_id: number;
    payroll_period_id: number | null;
    employee_id: number;
    service_id: number;
    service_price_snapshot: string;
    rule_type_snapshot: string;
    rule_value_snapshot: string;
    commission_amount: string;
    status: string;
    generated_at: string;
    locked_at: string | null;
    paid_at: string | null;
    created_at: string;
    // Relationships
    service?: Service;
    appointment?: Appointment;
    is_retroactive?: boolean;
}

export interface Tip {
    id: number;
    business_id: number;
    appointment_id: number | null;
    employee_id: number;
    payroll_period_id: number | null;
    amount: string;
    payment_method: string | null;
    notes: string | null;
    received_at: string;
    created_at: string;
}

export interface PayrollAdjustment {
    id: number;
    business_id: number;
    payroll_period_id: number;
    employee_id: number;
    type: PayrollAdjustmentType;
    amount: string;
    reason: string;
    description: string | null;
    created_by: number;
    created_at: string;
    // Relationships
    employee?: Employee;
    creator?: User;
}

export interface PayrollRecord {
    id: number;
    business_id: number;
    payroll_period_id: number;
    employee_id: number;
    base_salary_snapshot: string;
    commissions_total: string;
    tips_total: string;
    adjustments_total: string;
    gross_total: string;
    status: PayrollRecordStatus;
    payment_method: string | null;
    payment_reference: string | null;
    approved_at: string | null;
    approved_by: number | null;
    paid_at: string | null;
    paid_by: number | null;
    voided_at: string | null;
    voided_by: number | null;
    void_reason: string | null;
    snapshot_payload: unknown;
    created_at: string;
    updated_at: string;
    // Relationships
    employee?: Employee;
    commissions?: CommissionRecord[];
    tips?: Tip[];
    adjustments?: PayrollAdjustment[];
    period?: PayrollPeriod;
}

export interface CourseFormData {
    title: string;
    description: string;
    syllabus: string;
    cover_image: File | null;
    instructor_name: string;
    instructor_bio: string;
    instructor_image: File | null;
    duration_text: string;
    start_date: string;
    end_date: string;
    enrollment_deadline: string;
    schedule_text: string;
    price: number;
    currency: string;
    capacity: number | string;
    modality: 'online' | 'presencial' | 'hybrid';
    is_active: boolean;
    is_featured: boolean;
}
