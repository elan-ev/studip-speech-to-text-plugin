import vue from '@vitejs/plugin-vue';
import { dirname, resolve } from 'node:path';
import { fileURLToPath, URL } from 'node:url';
import { defineConfig } from 'vite';
import { visualizer } from 'rollup-plugin-visualizer';

const __filename = fileURLToPath(import.meta.url);
const __dirname = dirname(__filename);

export default defineConfig(({ mode }) => {
    return {
        build: {
            lib: {
                entry: resolve(__dirname, 'resources/js/app.js'),
                formats: ['umd'],
                name: 'speech-to-text',
                fileName: (format) => `speech-to-text.${format}.[hash].js`,
            },
            manifest: true,
            sourcemap: true,
        },
        define: { 'process.env.NODE_ENV': `"${mode}"` },
        plugins: [
            visualizer({
                gzipSize: true,
            }),
            vue(),
        ],
        resolve: {
            alias: {
                '@': fileURLToPath(new URL('./resources/js', import.meta.url)),
            },
        },
    };
});
