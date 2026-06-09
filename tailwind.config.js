import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.jsx',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                brand: {
                    primary: '#4F46E5',
                    light: '#EEF2FF',
                    mid: '#818CF8',
                    dark: '#3730A3',
                },
                chart: {
                    fixed: '#6366F1',
                    variable: '#10B981',
                },
            },
        },
    },

    plugins: [forms],
};
