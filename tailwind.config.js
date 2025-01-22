import defaultTheme from "tailwindcss/defaultTheme";

/** @type {import('tailwindcss').Config} */
export default {
    darkMode: "class",
    content: [
        "./vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php",
        "./storage/framework/views/*.php",
        "./resources/**/*.blade.php",
        "./resources/**/*.js",
        "./resources/**/*.vue",
        "node_modules/preline/dist/*.js",
    ],
    theme: {
        fontSize: {
            'xs': '0.875rem',
            'sm': '1rem',
            'base': '1.25rem',
            'lg': '1.5rem',
            'xl': '1.875rem',
            '2xl': '2.25rem',
            '3xl': '2.75rem',
            '4xl': '3.25rem',
            '5xl': '3.75rem',
        },
        extend: {
            fontFamily: {
                sans: ["Figtree", ...defaultTheme.fontFamily.sans],
            },
        },
    },
    plugins: [require("preline/plugin")],
};
