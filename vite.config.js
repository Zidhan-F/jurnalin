import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
            fonts: [
                // Disimpan sendiri (self-hosted) saat build. Hanya subset latin (cukup untuk Bahasa Indonesia),
                // font-display swap dengan fallback bermetrik agar tidak ada pergeseran tata letak.
                // Preload hanya dua bobot teks utama; JetBrains Mono (angka) dimuat tanpa preload.
                bunny('Plus Jakarta Sans', {
                    weights: [400, 500, 600, 700],
                    subsets: ['latin'],
                    display: 'swap',
                    preload: [{ weight: 400 }, { weight: 700 }],
                }),
                bunny('JetBrains Mono', {
                    weights: [400, 600],
                    subsets: ['latin'],
                    display: 'swap',
                    preload: false,
                }),
            ],
        }),
        tailwindcss(),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
