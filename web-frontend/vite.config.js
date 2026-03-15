import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'
import { VitePWA } from 'vite-plugin-pwa'

export default defineConfig({
  plugins: [
    react(),
    VitePWA({
      registerType: 'autoUpdate',
      workbox: {
        // Precache critical assets plus icons used by the PWA install prompt
        globPatterns: ['**/*.{js,css,html,ico,png,jpg,jpeg,webp,svg}'],
        runtimeCaching: [
          {
            // API requests: Network first, with short timeout and conservative caching
            urlPattern: /^https?:\/\/.*\/api\/.*/i,
            handler: 'NetworkFirst',
            options: {
              cacheName: 'api-cache',
              networkTimeoutSeconds: 3,
              expiration: {
                maxEntries: 100, // Reduced from 200 to prevent memory issues
                maxAgeSeconds: 300, // 5 minutes - keeps data fresh
                purgeOnQuotaError: true // Auto-clear if quota exceeded
              }
            }
          },
          {
            // Images: Cache first with size limits
            urlPattern: /^https?:.*\.(?:png|jpg|jpeg|svg|gif|webp)$/i,
            handler: 'CacheFirst',
            options: {
              cacheName: 'image-cache',
              expiration: {
                maxEntries: 200, // Reduced from 500 to prevent memory issues
                maxAgeSeconds: 604800, // 7 days (reduced from 30)
                purgeOnQuotaError: true
              }
            }
          },
          {
            // CSS and JS: Cache first with network fallback
            urlPattern: /^https?:.*\.(?:css|js)$/i,
            handler: 'CacheFirst',
            options: {
              cacheName: 'static-cache',
              expiration: {
                maxEntries: 50,
                maxAgeSeconds: 604800,
                purgeOnQuotaError: true
              }
            }
          }
        ],
        navigateFallback: '/index.html',
        navigateFallbackDenylist: [/^\/api\//, /^\/sanctum\//, /^\/\./, /^\/node_modules/],
        skipWaiting: true,
        clientsClaim: true,
        // Clean up old caches on activation
        cleanupOutdatedCaches: true
      },
      manifest: false, // Use public/manifest.json
      devOptions: {
        enabled: false,
        suppressWarnings: true,
        navigateFallbackToIndex: true
      }
    })
  ],
  server: {
    port: 3000,
    host: '0.0.0.0',
    strictPort: false,
    middlewareMode: false,
    // Reduce filesystem watchers to avoid high CPU / editor lag on large workspaces
    watch: {
      ignored: ['**/node_modules/**', '**/vendor/**', '**/dist/**', '**/.git/**', '**/web-backend/vendor/**', '**/web-backend/storage/**']
    },
    proxy: {
      '/api': {
        target: 'http://127.0.0.1:8000',
        changeOrigin: true,
        secure: false,
        rewrite: (path) => path,
        ws: true,
        configure: (proxy, options) => {
          proxy.on('error', (err, req, res) => {
            console.log('proxy error', err);
          });
          
        }
      },
      '/sanctum': {
        target: 'http://127.0.0.1:8000',
        changeOrigin: true,
        secure: false,
      }
    }
  },
  build: {
    // Code splitting for better caching and parallel loading
    rollupOptions: {
      output: {
        manualChunks: {
          'vendor': ['react', 'react-dom', 'react-router-dom', 'axios'],
          'heroicons': ['@heroicons/react'],
          'headlessui': ['@headlessui/react']
        }
      }
    },
    chunkSizeWarningLimit: 1000,
    // Disable sourcemaps in production for smaller bundle
    sourcemap: false,
    // Optimize minification
    minify: 'terser',
    terserOptions: {
      compress: {
        drop_console: true,
        drop_debugger: true
      }
    },
    reportCompressedSize: false
  },
  // Optimize dependencies
  optimizeDeps: {
    include: ['react', 'react-dom', 'react-router-dom', 'axios', '@heroicons/react', '@headlessui/react']
  }
})