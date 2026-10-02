import { LucideIcon } from 'lucide-react';

export interface Auth {
    user: User;
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
    url: string;
    icon?: LucideIcon | null;
    isActive?: boolean;
    badge?: number;
}

export interface SharedData {
    name: string;
    auth: Auth;
    openAlerts: number;
    systemPulse: { total: number | string; down: number | string | null; degraded: number | string | null } | null;
    flash: { success?: string | null; error?: string | null };
    [key: string]: unknown;
}

export type Role = 'superadmin' | 'jefe' | 'lector';

export interface User {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    role: Role;
    role_label: string;
    is_superadmin: boolean;
    two_factor: boolean;
    [key: string]: unknown;
}

export type AppStatus = 'online' | 'degraded' | 'down' | 'unknown';

export interface AppRef {
    id: number;
    name: string;
}

export interface Paginated<T> {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
    links: { url: string | null; label: string; active: boolean }[];
}

export interface LoginEvent {
    id: number;
    application_id: number;
    application?: AppRef;
    external_user_id: string | null;
    identifier: string | null;
    user_name: string | null;
    event: 'login' | 'failed' | 'logout' | 'lockout' | 'blocked';
    ip: string | null;
    device: string | null;
    user_agent: string | null;
    occurred_at: string;
}

export interface AuditLog {
    id: number;
    application_id: number;
    application?: AppRef;
    external_user_id: string | null;
    user_name: string | null;
    action: string;
    module: string;
    record_id: string | null;
    old_values: Record<string, unknown> | null;
    new_values: Record<string, unknown> | null;
    ip: string | null;
    url: string | null;
    occurred_at: string;
}

export interface AppSession {
    id: number;
    application_id: number;
    application?: AppRef;
    external_user_id: string;
    user_name: string | null;
    user_email: string | null;
    user_role: string | null;
    ip: string | null;
    device: string | null;
    login_at: string | null;
    last_activity_at: string;
}

export interface Alert {
    id: number;
    application_id: number | null;
    application?: AppRef | null;
    type: string;
    severity: 'critical' | 'warning' | 'info';
    title: string;
    message: string;
    acknowledged_at: string | null;
    acknowledged_by?: AppRef | null;
    created_at: string;
}

export interface ErrorGroup {
    id: number;
    application_id: number;
    application?: AppRef;
    exception_class: string;
    message: string;
    file: string | null;
    line: number | null;
    occurrences: number;
    first_seen_at: string;
    last_seen_at: string;
    resolved_at: string | null;
    last_24h?: number;
}

export interface Deployment {
    id: number;
    railway_id: string;
    status: string;
    commit_hash: string | null;
    commit_message: string | null;
    commit_author: string | null;
    branch: string | null;
    deployed_at: string;
}
