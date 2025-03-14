/** @type {import('tailwindcss').Config} */
export default {
    content: [
        "./resources/**/*.blade.php",
        "./resources/**/*.js",
        "./resources/**/*.vue",
        "./vendor/livewire/flux/dist/**/*.js",
        "./vendor/livewire/flux/src/View/Components/**/*.php",
        "./vendor/livewire/livewire/resources/views/**/*.blade.php",
        "./vendor/laravel/framework/src/Illuminate/Pagination/resources/views/**/*.blade.php",
    ],
    darkMode: "class",
    theme: {
        extend: {
            colors: {
                // Essential text/background colors
                background: "rgb(var(--background) / <alpha-value>)",
                foreground: "rgb(var(--foreground) / <alpha-value>)",

                primary: {
                    DEFAULT: "rgb(var(--color-primary) / <alpha-value>)",
                    foreground:
                        "rgb(var(--color-primary-foreground) / <alpha-value>)",
                },
                accent: {
                    DEFAULT: "rgb(var(--color-accent) / <alpha-value>)",
                    foreground:
                        "rgb(var(--color-accent-foreground) / <alpha-value>)",
                    content: "rgb(var(--color-accent-content) / <alpha-value>)",
                },
                neutral: {
                    50: "#fafafa",
                    100: "#f5f5f5",
                    200: "#e5e5e5",
                    300: "#d4d4d4",
                    400: "#a3a3a3",
                    500: "#737373",
                    600: "#525252",
                    700: "#404040",
                    800: "#262626",
                    900: "#171717",
                    950: "#0a0a0a",
                },
            },
        },
    },
    plugins: [],
};
