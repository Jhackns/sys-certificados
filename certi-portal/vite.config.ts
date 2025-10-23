import { defineConfig } from 'vite';

export default defineConfig({
  server: {
    port: 4200,
    host: 'localhost',
    strictPort: true,
    hmr: {
      port: 4200,
      host: 'localhost'
    },
    // Configuración para evitar problemas de CORS
    cors: true,
    // Configuración para mejorar la conectividad del WebSocket
    watch: {
      usePolling: false,
      interval: 100
    }
  },
  // Configuración para desarrollo
  define: {
    'process.env': {}
  },
  // Optimización para desarrollo
  optimizeDeps: {
    include: ['@angular/core', '@angular/common', '@angular/router']
  }
});