import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
    ],

    theme: {
        extend: {
            colors: {
                canvas: 'rgb(var(--ds-bg) / <alpha-value>)',
                surface: 'rgb(var(--ds-surface) / <alpha-value>)',
                elevated: 'rgb(var(--ds-elevated) / <alpha-value>)',
                ink: {
                    DEFAULT: 'rgb(var(--ds-text) / <alpha-value>)',
                    muted: 'rgb(var(--ds-muted) / <alpha-value>)',
                    inverse: 'rgb(var(--ds-text-inverse) / <alpha-value>)',
                    deep: 'rgb(var(--ds-ink-deep) / <alpha-value>)',
                },
                brand: {
                    DEFAULT: 'rgb(var(--ds-primary) / <alpha-value>)',
                    hover: 'rgb(var(--ds-primary-hover) / <alpha-value>)',
                    soft: 'rgb(var(--ds-primary-soft) / <alpha-value>)',
                },
                accent: {
                    DEFAULT: 'rgb(var(--ds-accent) / <alpha-value>)',
                    soft: 'rgb(var(--ds-accent-soft) / <alpha-value>)',
                },
                success: {
                    DEFAULT: 'rgb(var(--ds-success) / <alpha-value>)',
                    soft: 'rgb(var(--ds-success-soft) / <alpha-value>)',
                },
                warning: {
                    DEFAULT: 'rgb(var(--ds-warning) / <alpha-value>)',
                    soft: 'rgb(var(--ds-warning-soft) / <alpha-value>)',
                },
                danger: {
                    DEFAULT: 'rgb(var(--ds-danger) / <alpha-value>)',
                    soft: 'rgb(var(--ds-danger-soft) / <alpha-value>)',
                },
                line: 'rgb(var(--ds-border) / <alpha-value>)',
                focus: 'rgb(var(--ds-focus) / <alpha-value>)',
            },
            fontFamily: {
                sans: ['"IBM Plex Sans Arabic"', 'IBM Plex Sans', 'Tahoma', ...defaultTheme.fontFamily.sans],
                display: ['"IBM Plex Sans Arabic"', 'IBM Plex Sans', ...defaultTheme.fontFamily.sans],
                numeric: ['"IBM Plex Sans Arabic"', 'ui-monospace', 'tabular-nums', 'sans-serif'],
            },
            fontSize: {
                display: ['clamp(2.4rem, 5.4vw, 4.35rem)', { lineHeight: '1.12', letterSpacing: '-0.03em', fontWeight: '600' }],
                'heading-1': ['clamp(1.65rem, 2.4vw, 2.15rem)', { lineHeight: '1.25', fontWeight: '600' }],
                'heading-2': ['1.45rem', { lineHeight: '1.3', fontWeight: '600' }],
                'heading-3': ['1.15rem', { lineHeight: '1.35', fontWeight: '600' }],
                body: ['1rem', { lineHeight: '1.75' }],
                caption: ['0.8125rem', { lineHeight: '1.5' }],
                price: ['1.2rem', { lineHeight: '1.3', fontWeight: '600', letterSpacing: '0.01em' }],
                label: ['0.875rem', { lineHeight: '1.4', fontWeight: '500' }],
            },
            spacing: {
                4.5: '1.125rem',
                13: '3.25rem',
                15: '3.75rem',
                18: '4.5rem',
            },
            borderRadius: {
                xs: '0.25rem',
                sm: '0.375rem',
                md: '0.5rem',
                lg: '0.75rem',
                xl: '1rem',
                pill: '999px',
            },
            boxShadow: {
                xs: 'var(--ds-shadow-xs)',
                sm: 'var(--ds-shadow-sm)',
                md: 'var(--ds-shadow-md)',
                lg: 'var(--ds-shadow-lg)',
            },
            maxWidth: {
                shell: '80rem',
            },
            transitionDuration: {
                DEFAULT: '220ms',
            },
            transitionTimingFunction: {
                brand: 'cubic-bezier(0.22, 1, 0.36, 1)',
            },
        },
    },

    plugins: [forms],
};
