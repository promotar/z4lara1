import { resolve } from 'node:path';
import { defineConfig } from 'vite';

const moduleRoot = import.meta.dirname;

export default defineConfig({
    base: './',
    publicDir: false,
    build: {
        outDir: resolve(moduleRoot, 'resources/private-assets/js'),
        emptyOutDir: false,
        lib: {
            entry: resolve(moduleRoot, 'resources/js/ve03/public-viewer.js'),
            formats: ['iife'],
            name: 'Z4Ve03PublicViewer',
            fileName: () => 've03-public-viewer.bundle.js',
        },
        target: 'es2022',
        minify: 'esbuild',
        sourcemap: false,
    },
});
