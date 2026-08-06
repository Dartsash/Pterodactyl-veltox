import tw from 'twin.macro';
import { createGlobalStyle } from 'styled-components/macro';
// @ts-expect-error untyped font file
import font from '@fontsource-variable/ibm-plex-sans/files/ibm-plex-sans-latin-wght-normal.woff2';

export default createGlobalStyle`
    @font-face {
        font-family: 'IBM Plex Sans';
        font-style: normal;
        font-display: swap;
        font-weight: 100 700;
        src: url(${font}) format('woff2-variations');
        unicode-range: U+0000-00FF,U+0131,U+0152-0153,U+02BB-02BC,U+02C6,U+02DA,U+02DC,U+0304,U+0308,U+0329,U+2000-206F,U+20AC,U+2122,U+2191,U+2193,U+2212,U+2215,U+FEFF,U+FFFD;
    }

    body {
        ${tw`font-sans text-neutral-200`};
        /* No background here on purpose: the Veltox gradient lives on <html> in
           tailwind.css. Painting the body would cut the gradient off at the end
           of the content and leave a black strip underneath. */
        background-color: transparent;
        min-height: 100vh;
        letter-spacing: 0.015em;
    }

    h1, h2, h3, h4, h5, h6 {
        ${tw`font-medium tracking-normal font-header`};
    }

    p {
        ${tw`text-neutral-200 leading-snug font-sans`};
    }

    form {
        ${tw`m-0`};
    }

    textarea, select, input, button, button:focus, button:focus-visible {
        ${tw`outline-none`};
    }

    input[type=number]::-webkit-outer-spin-button,
    input[type=number]::-webkit-inner-spin-button {
        -webkit-appearance: none !important;
        margin: 0;
    }

    input[type=number] {
        -moz-appearance: textfield !important;
    }

    /* Scroll Bar Style - slim dark bars that pick up the brand colour. */
    ::-webkit-scrollbar {
        background: none;
        width: 10px;
        height: 10px;
    }

    ::-webkit-scrollbar-thumb {
        background-color: hsl(255, 19%, 23%);
        border: 2px solid transparent;
        background-clip: content-box;
        border-radius: 999px;
        box-shadow: none;
    }

    ::-webkit-scrollbar-thumb:hover {
        background-color: #7c4dff;
        background-clip: content-box;
    }

    ::-webkit-scrollbar-track,
    ::-webkit-scrollbar-corner {
        background: transparent;
    }
`;
