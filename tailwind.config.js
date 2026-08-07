import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.vue',
        './Modules/**/Vue/**/*.vue',
        './Modules/**/Resources/views/**/*.blade.php',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                brand: {
                    DEFAULT: '#031226',
                    50: '#eef1f5',
                    100: '#d7dde6',
                    600: '#0a2140',
                    700: '#061a33',
                    800: '#041729',
                    900: '#031226',
                },
            },
        },
    },

    plugins: [forms],
};
