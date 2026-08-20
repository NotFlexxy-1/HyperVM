import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    darkMode: 'class',
    content: [
        './resources/views/**/*.blade.php',
        './resources/js/**/*.{ts,tsx}',
    ],
    theme: {
        extend: {
            colors: {
                brand: {
                    DEFAULT: 'rgb(var(--hv-brand) / <alpha-value>)',
                    soft: 'rgb(var(--hv-brand-soft) / <alpha-value>)',
                    contrast: 'rgb(var(--hv-brand-contrast) / <alpha-value>)',
                },
                accent: 'rgb(var(--hv-accent) / <alpha-value>)',
                surface: {
                    DEFAULT: 'rgb(var(--hv-surface) / <alpha-value>)',
                    raised: 'rgb(var(--hv-surface-raised) / <alpha-value>)',
                    sunken: 'rgb(var(--hv-surface-sunken) / <alpha-value>)',
                },
                edge: 'rgb(var(--hv-border) / <alpha-value>)',
                ink: {
                    DEFAULT: 'rgb(var(--hv-text) / <alpha-value>)',
                    muted: 'rgb(var(--hv-text-muted) / <alpha-value>)',
                },
            },
            borderRadius: {
                panel: 'var(--hv-radius)',
            },
            fontFamily: {
                sans: ['var(--hv-font)', 'ui-sans-serif', 'system-ui', 'sans-serif'],
            },
            boxShadow: {
                glow: '0 18px 60px -24px rgb(var(--hv-brand) / 0.55)',
            },
        },
    },
    plugins: [forms],
};
