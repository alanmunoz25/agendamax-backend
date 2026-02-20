import { InertiaLinkProps } from '@inertiajs/react';
import { LucideIcon } from 'lucide-react';

export interface Auth {
    user: User;
    business_name: string | null;
}

export interface BreadcrumbItem {
    title: string;
    href: string;
}

export interface NavGroup {
    title: string;
    items: NavItem[];
}

export interface NavItem {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
    icon?: LucideIcon | null;
    isActive?: boolean;
}

export interface Permissions {
    is_super_admin: boolean;
    is_business_admin: boolean;
    is_employee: boolean;
    is_client: boolean;
    can_manage_businesses: boolean;
    can_manage_users: boolean;
}

export interface SharedData {
    name: string;
    quote: { message: string; author: string };
    auth: Auth;
    permissions: Permissions;
    sidebarOpen: boolean;
    [key: string]: unknown;
}

export interface User {
    id: number;
    business_id: number | null;
    name: string;
    email: string;
    phone: string | null;
    role: 'super_admin' | 'business_admin' | 'employee' | 'client';
    avatar?: string;
    email_verified_at: string | null;
    two_factor_enabled?: boolean;
    fcm_token: string | null;
    created_at: string;
    updated_at: string;
}

// Re-export all model types
export * from './models';
