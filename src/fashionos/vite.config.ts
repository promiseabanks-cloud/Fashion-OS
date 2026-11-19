import { fileURLToPath } from 'node:url';
import react from '@vitejs/plugin-react';
import { defineConfig } from 'vitest/config';

const entry = fileURLToPath(new URL('./resources/js/app/main.tsx', import.meta.url));
const outDir = fileURLToPath(new URL('./public/build', import.meta.url));

export default defineConfig({
  // Assets are served from a WordPress plugin directory, so URLs stay relative.
  base: './',
  // No static public directory: build output lives in public/build.
  publicDir: false,
  plugins: [react()],
  build: {
    outDir,
    emptyOutDir: true,
    manifest: 'manifest.json',
    sourcemap: false,
    rollupOptions: {
      input: entry,
      output: {
        entryFileNames: 'assets/[name]-[hash].js',
        chunkFileNames: 'assets/[name]-[hash].js',
        assetFileNames: 'assets/[name]-[hash][extname]',
      },
    },
  },
  server: {
    port: 5173,
    strictPort: true,
    cors: true,
  },
  test: {
    environment: 'node',
    include: ['resources/js/**/*.test.ts'],
  },
});
