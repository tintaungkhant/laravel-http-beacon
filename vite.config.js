import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import tailwindcss from '@tailwindcss/vite'
import { fileURLToPath, URL } from 'node:url'

export default defineConfig({
    plugins: [vue(), tailwindcss()],
    resolve: {
        alias: {
            '@': fileURLToPath(new URL('./resources/js', import.meta.url)),
        },
    },
    build: {
        outDir: 'public/build',
        emptyOutDir: true,
        manifest: false,
        rollupOptions: {
            input: 'resources/js/app.js',
            output: {
                entryFileNames: 'app.js',
                chunkFileNames: 'chunks/[name].js',
                assetFileNames: (info) =>
                    info.name && info.name.endsWith('.css')
                        ? 'app.css'
                        : 'assets/[name][extname]',
            },
        },
    },
})
