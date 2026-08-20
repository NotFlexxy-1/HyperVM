import { createContext, useCallback, useContext, useEffect, useMemo, useState, type ReactNode } from 'react';

type Mode = 'light' | 'dark' | 'system';

type ThemeSettings = {
    default_mode?: Mode;
    brand: string;
    brand_soft: string;
    brand_contrast: string;
    accent: string;
    radius: string;
    font: string;
};

const ThemeContext = createContext<{
    mode: Mode;
    setMode: (m: Mode) => void;
    resolved: 'light' | 'dark';
}>({
    mode: 'dark',
    setMode: () => undefined,
    resolved: 'dark',
});

export function ThemeProvider({
    children,
    theme,
}: {
    children: ReactNode;
    theme?: ThemeSettings;
}) {
    const stored =
        typeof window !== 'undefined'
            ? (localStorage.getItem('hv-mode') as Mode | null)
            : null;

    const [mode, setModeState] = useState<Mode>(
        stored ?? theme?.default_mode ?? 'dark',
    );

    const resolved: 'light' | 'dark' = useMemo(() => {
        if (mode !== 'system') return mode;

        return window.matchMedia('(prefers-color-scheme: dark)').matches
            ? 'dark'
            : 'light';
    }, [mode]);

    useEffect(() => {
        document.documentElement.classList.toggle('dark', resolved === 'dark');
    }, [resolved]);

    useEffect(() => {
        if (!theme) return;

        const root = document.documentElement;

        const rgb = (hex: string) => {
            const clean = hex.replace('#', '');
            const full =
                clean.length === 3
                    ? clean
                          .split('')
                          .map((c) => c + c)
                          .join('')
                    : clean;

            const n = parseInt(full, 16);

            return `${(n >> 16) & 255} ${(n >> 8) & 255} ${n & 255}`;
        };

        root.style.setProperty('--hv-brand', rgb(theme.brand));
        root.style.setProperty('--hv-brand-soft', rgb(theme.brand_soft));
        root.style.setProperty('--hv-brand-contrast', rgb(theme.brand_contrast));
        root.style.setProperty('--hv-accent', rgb(theme.accent));
        root.style.setProperty('--hv-radius', theme.radius);
        root.style.setProperty('--hv-font', theme.font);
    }, [theme]);

    const setMode = useCallback((next: Mode) => {
        localStorage.setItem('hv-mode', next);
        setModeState(next);
    }, []);

    return (
        <ThemeContext.Provider value={{ mode, setMode, resolved }}>
            {children}
        </ThemeContext.Provider>
    );
}

export const useTheme = () => useContext(ThemeContext);
