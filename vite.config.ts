import inertia from '@inertiajs/vite';
import { wayfinder } from '@laravel/vite-plugin-wayfinder';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import { defineConfig } from 'vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.ts'],
            // Sem esta entrada, `vite build --ssr` compilaria o app do cliente
            // (config.ssr cai no config.input) e o SSR sairia quebrado.
            ssr: 'resources/js/ssr.ts',
            refresh: true,
            fonts: [
                bunny('Inter', {
                    weights: [400, 500, 600, 700],
                }),
                bunny('JetBrains Mono', {
                    weights: [400, 500],
                }),
            ],
        }),
        inertia(),
        tailwindcss(),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
        wayfinder({
            formVariants: true,
            // Em producao o `php artisan optimize` grava `bootstrap/cache/routes-v7.php`.
            // Sem limpar o cache de rotas antes, o `wayfinder:generate` le a tabela de
            // rotas do deploy anterior e deixa de gerar rotas novas durante o `vite build`.
            command:
                'php artisan route:clear && php artisan wayfinder:generate',
        }),
    ],
    // O worker do parser do JSON Viewer e um modulo ES (o `{ type: 'module' }`
    // do `new Worker`). Sem isto o build sairia com o formato padrao (`iife`),
    // que ignora o tipo pedido no codigo e quebraria o `import` dentro dele.
    worker: {
        format: 'es',
    },
    server: {
        watch: {
            ignored: [
                '**/.agents/**',
                '**/.claude/**',
                '**/.cursor/**',
                '**/.junie/**',
                '**/vendor/**',
            ],
        },
    },
});
