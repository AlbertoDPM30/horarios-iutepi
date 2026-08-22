import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'

// El build sale como archivos estaticos: se puede servir desde Apache,
// un hosting compartido o cualquier CDN sin Node del lado del servidor.
export default defineConfig({
  plugins: [react()],
  base: './',
  server: {
    port: 5173,
    open: true,
  },
  build: {
    outDir: 'dist',
    sourcemap: false,
    chunkSizeWarningLimit: 900,
    rollupOptions: {
      output: {
        // Separar lo que casi nunca cambia mejora el cacheo del navegador
        manualChunks: {
          react: ['react', 'react-dom', 'react-router-dom'],
          iconos: ['lucide-react'],
        },
      },
    },
  },
})
