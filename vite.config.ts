import inertia from '@inertiajs/vite';
import { wayfinder } from '@laravel/vite-plugin-wayfinder';
import tailwindcss from '@tailwindcss/vite';
import react from '@vitejs/plugin-react';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import { defineConfig } from 'vite';

// Ensure PHP 8.4 takes precedence in PATH
if (process.platform === 'win32' && process.env.LOCALAPPDATA) {
    const wingetLinksPath = `${process.env.LOCALAPPDATA}\\Microsoft\\WinGet\\Links`;
    if (!process.env.PATH?.startsWith(wingetLinksPath)) {
        process.env.PATH = `${wingetLinksPath};${process.env.PATH}`;
    }
} else if (process.platform === 'linux') {
    // aaPanel PHP 8.4 directory
    const aapanelPhp84 = '/www/server/php/84/bin';
    if (process.env.PATH && !process.env.PATH.includes(aapanelPhp84)) {
        process.env.PATH = `${aapanelPhp84}:${process.env.PATH}`;
    }
}

const phpBinary = process.env.PHP_BINARY || 'php';

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
            command: `${phpBinary} artisan wayfinder:generate`,
        }),
    ],
});
