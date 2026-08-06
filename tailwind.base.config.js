// Drop-in replacement for tailwind.config.js
// Neon Dark design system for the Pterodactyl panel rewrite.
//
// IMPORTANT: twin.macro v2 resolves classes against the *Tailwind v2* default
// palette, which only ships: gray, red, yellow, green, blue, indigo, purple,
// pink. Anything else (cyan, orange, amber, teal, sky, rose, emerald, ...)
// must be declared explicitly here or `tw` will throw "was not found" at build
// time. That is why the whole modern palette is spelled out below.

const colors = require('tailwindcss/colors');

/** Cool blue-grey ramp used for every surface and border in the panel. */
const gray = {
    50: '#F5F7FB',
    100: '#E8ECF4',
    200: '#C7CEDD',
    300: '#9AA3B8',
    400: '#6E7896',
    500: '#4C5570',
    600: '#343C54',
    700: '#232B3D',
    800: '#161B28',
    900: '#11151F',
    950: '#0B0E14',
};

/** Violet accent — 400 is text-safe on dark, 600 is the button fill. */
const violet = {
    50:  '#F3F0FF',
    100: '#E6DEFF',
    200: '#CFC0FF',
    300: '#B9A2FF',
    400: '#A78BFA',
    500: '#7C5CFF',
    600: '#5B3FD9',
    700: '#4830AD',
    800: '#352382',
    900: '#241757',
    950: '#180E3B',
};

module.exports = {
    darkMode: 'class',
    content: ['./resources/scripts/**/*.{js,ts,tsx}'],
    theme: {
        extend: {
            fontFamily: {
                header: ['"IBM Plex Sans"', 'system-ui', 'sans-serif'],
                mono: ['Menlo', 'Consolas', '"Liberation Mono"', 'monospace'],
            },
            colors: {
                black: '#0B0E14',
                canvas: '#0B0E14',
                surface: '#11151F',
                raised: '#1D2434',

                gray,
                neutral: gray, // deprecated alias, kept for legacy components
                violet,
                primary: violet, // deprecated alias, kept for legacy components
                brand: violet,   // used by DashboardContainer and custom addons

                // --- semantic ---
                success: colors.emerald,
                warning: colors.amber,
                danger: colors.red,

                // --- full modern palette ---
                // twin.macro v2 does not know these by default; declare them all
                // so addon code can use any Tailwind colour without breaking.
                slate: colors.slate,
                zinc: colors.zinc,
                stone: colors.stone,
                orange: colors.orange,
                amber: colors.amber,
                lime: colors.lime,
                emerald: colors.emerald,
                teal: colors.teal,
                cyan: colors.cyan,
                sky: colors.sky,
                fuchsia: colors.fuchsia,
                rose: colors.rose,
            },
            fontSize: {
                '2xs': '0.625rem',
            },
            borderRadius: {
                DEFAULT: '8px',
                lg: '12px',
                xl: '16px',
            },
            borderColor: {
                DEFAULT: 'rgba(255, 255, 255, 0.08)',
                strong: 'rgba(255, 255, 255, 0.14)',
            },
            boxShadow: {
                card: '0 1px 2px rgba(0,0,0,.30), 0 4px 12px rgba(0,0,0,.24)',
                float: '0 8px 32px rgba(0,0,0,.45)',
                'glow-violet': '0 0 0 1px rgba(124,92,255,.34), 0 0 24px rgba(124,92,255,.22)',
                'glow-cyan': '0 0 18px rgba(34,211,238,.35)',
            },
            backgroundImage: {
                'accent-gradient': 'linear-gradient(140deg, #7C5CFF, #22D3EE)',
                'meter-gradient': 'linear-gradient(90deg, #22D3EE, #7C5CFF)',
                'card-gradient': 'linear-gradient(180deg, #161B28, #11151F)',
            },
            transitionDuration: {
                250: '250ms',
            },
        },
    },
    plugins: [
        // Tailwind >= 3.3 prints "line-clamp is now included by default" and that
        // is true for the PostCSS build -- but twin.macro v2 resolves `tw` at
        // build time with its own v2-era engine, which has NO built-in
        // line-clamp. Removing this plugin breaks `line-clamp-2` in
        // ServerRow.tsx. Keep it and ignore the warning.
        require('@tailwindcss/line-clamp'),
        require('@tailwindcss/forms')({ strategy: 'class' }),
    ],
};
