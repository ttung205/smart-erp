import { defineConfig, loadEnv } from 'vite'
import vue from '@vitejs/plugin-vue'
import tailwindcss from '@tailwindcss/vite'

export default defineConfig(({ mode }) => {
  const env = loadEnv(mode, process.cwd(), '')

  const identityTarget = env.VITE_IDENTITY_SERVICE_URL || 'http://127.0.0.1:8001'
  const catalogTarget = env.VITE_CATALOG_SERVICE_URL || 'http://127.0.0.1:8002'
  const purchaseTarget = env.VITE_PURCHASE_SERVICE_URL || 'http://127.0.0.1:8003'
  const inventoryTarget = env.VITE_INVENTORY_SERVICE_URL || 'http://127.0.0.1:8004'
  const salesTarget = env.VITE_SALES_SERVICE_URL || 'http://127.0.0.1:8005'
  const notificationTarget = env.VITE_NOTIFICATION_SERVICE_URL || 'http://127.0.0.1:8006'

  return {
    plugins: [
      vue(),
      tailwindcss()
    ],
    server: {
      port: Number(env.VITE_PORT) || 5174,
      strictPort: true,
      proxy: {
        '/api/v1/auth': {
          target: identityTarget,
          changeOrigin: true,
          rewrite: (path) => path.replace(/^\/api\/v1\/auth/, '/api')
        },
        '/api/v1/catalog': {
          target: catalogTarget,
          changeOrigin: true,
          rewrite: (path) => path.replace(/^\/api\/v1\/catalog/, '/api')
        },
        '/api/v1/purchase': {
          target: purchaseTarget,
          changeOrigin: true
        },
        '/api/v1/inventory': {
          target: inventoryTarget,
          changeOrigin: true
        },
        '/api/v1/sales': {
          target: salesTarget,
          changeOrigin: true,
          rewrite: (path) => path.replace(/^\/api\/v1\/sales/, '/api')
        },
        '/api/v1/notifications': {
          target: notificationTarget,
          changeOrigin: true
        }
      }
    }
  }
})
