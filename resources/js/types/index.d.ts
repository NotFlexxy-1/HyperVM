export interface Branding {
    panel_name: string;
    tagline: string | null;
    social_description: string | null;
    logo_url: string | null;
    favicon_url: string | null;
}

export interface ThemeSettings {
    brand: string;
    brand_soft: string;
    brand_contrast: string;
    accent: string;
    radius: string;
    font: string;
    default_mode: 'light' | 'dark' | 'system';
    allow_user_mode_switch: boolean;
}

export interface WidgetConfig {
    key: string;
    span: number;
    enabled: boolean;
}

export interface LayoutSettings {
    navigation: 'sidebar' | 'topbar' | 'rail';
    density: 'compact' | 'comfortable' | 'spacious';
    container: 'boxed' | 'wide' | 'fluid';
    dashboard_widgets: WidgetConfig[];
}

export interface AuthUser {
    id: number;
    uuid: string;
    name: string;
    username: string;
    email: string;
    avatar_url: string | null;
    is_admin: boolean;
    roles: string[];
    permissions: string[];
    preferences: Record<string, unknown>;
    force_password_change: boolean;
}

export interface PageProps {
    auth: { user: AuthUser | null };
    settings: {
        branding: Branding;
        theme: ThemeSettings;
        layout: LayoutSettings;
        registration: { enabled: boolean; discord_enabled: boolean };
    };
    flash: { success: string | null; error: string | null };
    [key: string]: unknown;
}
