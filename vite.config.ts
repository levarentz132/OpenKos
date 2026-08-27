import inertia from '@inertiajs/vite';
import { wayfinder } from '@laravel/vite-plugin-wayfinder';
import tailwindcss from '@tailwindcss/vite';
import react from '@vitejs/plugin-react';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import { defineConfig } from 'vite';

// Ensure PHP 8.4 (WinGet/Links) takes precedence over older XAMPP PHP in PATH on Windows
if (process.platform === 'win32' && process.env.LOCALAPPDATA) {
    const wingetLinksPath = `${process.env.LOCALAPPDATA}\\Microsoft\\WinGet\\Links`;
    if (!process.env.PATH?.startsWith(wingetLinksPath)) {
        process.env.PATH = `${wingetLinksPath};${process.env.PATH}`;
    }
}

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.tsx'],
            refresh: true,
            fonts: [
                bunny('Instrument Sans', {
                    weights: [400, 500, 600],
                }),
            ],
        }),
        inertia(),
        react({
            babel: {
                plugins: ['babel-plugin-react-compiler'],
            },
        }),
        tailwindcss(),
        wayfinder({
            formVariants: true,
        }),
    ],
});
