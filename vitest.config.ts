import vue from '@vitejs/plugin-vue';
import { fileURLToPath } from 'node:url';
import { defineConfig } from 'vitest/config';

export default defineConfig({
    plugins: [vue()],
    resolve: {
        alias: {
            '@': fileURLToPath(new URL('./resources/js', import.meta.url)),
        },
    },
    test: {
        environment: 'jsdom',
        include: ['resources/js/**/*.test.ts'],
        restoreMocks: true,
        // O suite cheio abre 163 ambientes jsdom e alguns testes montam
        // componentes ou chamam o PHP; no default de 5 s casos triviais
        // estouravam enquanto o caso isolado roda em menos de 1 s. O limite
        // maior nao mascara falha de assercao, so cobre a carga da maquina.
        testTimeout: 20000,
    },
});
