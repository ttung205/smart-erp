import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'

export default defineConfig({
  plugins: [vue()],
  server: {
    port: 5173,
    proxy: {
      '/api/v1/auth': {
        target: 'http://127.0.0.1:8001',
        changeOrigin: true,
        rewrite: (path) => path.replace(/^\/api\/v1\/auth/, '/api')
      },
      '/api/v1/catalog': {
        target: 'http://127.0.0.1:8002',
        changeOrigin: true,
        rewrite: (path) => path.replace(/^\/api\/v1\/catalog/, '/api')
      },
      '/api/v1/inventory': {
        target: 'http://127.0.0.1:8004',
        changeOrigin: true
      }
    }
  }
})