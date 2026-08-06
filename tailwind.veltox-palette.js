// Veltox theme palette. Merged into the active tailwind config by
// tailwind.config.js -- safe to edit, colours here win over the base config.
const colors = require('tailwindcss/colors');

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

const violet = {
    50: '#F3F0FF',
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
    colors: {
        black: '#0B0E14',
        canvas: '#0B0E14',
        surface: '#11151F',
        raised: '#1D2434',
        gray,
        neutral: gray,
        violet,
        primary: violet,
        brand: violet,
        success: colors.emerald,
        warning: colors.amber,
        danger: colors.red,
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
    fontFamily: {
        header: ['"IBM Plex Sans"', 'system-ui', 'sans-serif'],
        mono: ['Menlo', 'Consolas', '"Liberation Mono"', 'monospace'],
    },
    fontSize: { '2xs': '0.625rem' },
    borderRadius: { DEFAULT: '8px', lg: '12px', xl: '16px' },
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
    transitionDuration: { 250: '250ms' },
    // Tailwind 3.0.x only ships the opacity steps 0,5,10,20,25,...,100, so a
    // class like bg-danger-500/15 used by the stock panel CSS does not exist
    // there. Declaring the full 5% ramp makes every /NN modifier resolve
    // regardless of the tailwindcss version that happens to be installed.
    opacity: Object.fromEntries(
        Array.from({ length: 21 }, (_, i) => [i * 5, (i * 5 / 100).toString()]).concat([
            [15, '0.15'],
        ]),
    ),
};
