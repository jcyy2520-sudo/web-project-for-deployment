import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'
import { VitePWA } from 'vite-plugin-pwa'

export default defineConfig({
  plugins: [
    react(),
    // PWA configuration with proper service worker cache strategy
    VitePWA({
      registerType: 'autoUpdate',
      includeAssets: ['favicon.ico', 'robots.txt'],
      manifest: {
        name: 'Law Notary System',
        short_name: 'NotarySystem',
        description: 'Law Notary Appointment System',
        theme_color: '#000000',
        background_color: '#ffffff',
        display: 'standalone',
        scope: '/',
        start_url: '/',
        icons: [
          {
            src: '/icon-192.png',
            sizes: '192x192',
            type: 'image/png',
            purpose: 'any'
          },
          {
            src: '/icon-512.png',
            sizes: '512x512',
            type: 'image/png',
            purpose: 'any'
          },
          {
            src: '/icon-maskable-192.png',
            sizes: '192x192',
            type: 'image/png',
            purpose: 'maskable'
          }
        ],
        categories: ['productivity', 'business'],
        screenshots: []
      },
      workbox: {
        // Cache strategy: Network first for API calls, Cache first for static assets
        runtimeCaching: [
          {
            urlPattern: /^https:\/\/api\./i,
            handler: 'NetworkFirst',
            options: {
              cacheName: 'api-cache',
              expiration: {
                maxEntries: 200,
                maxAgeSeconds: 300 // 5 minutes
              }
            }
          },
          {
            urlPattern: /^https:.*\.(?:png|jpg|jpeg|svg|gif|webp)$/i,
            handler: 'CacheFirst',
            options: {
              cacheName: 'image-cache',
              expiration: {
                maxEntries: 500,
                maxAgeSeconds: 86400 * 30 // 30 days
              }
            }
          },
          {
            urlPattern: /^https:.*\.(?:js|css)$/i,
            handler: 'CacheFirst',
            options: {
              cacheName: 'static-cache',
              expiration: {
                maxEntries: 100,
                maxAgeSeconds: 86400 * 30 // 30 days
              }
            }
          }
        ],
        // Don't cache development/build endpoints
        navigateFallback: '/index.html',
        navigateFallbackDenylist: [/^\/api\//, /^\/sanctum\//, /^\/__/],
        skipWaiting: true,
        clientsClaim: true
      },
      // Disable PWA in development to prevent dev server proxy conflicts
      devOptions: {
        enabled: false, // Set to true to test PWA in dev mode (with caution)
        suppressWarnings: true,
        navigateFallbackToIndex: true,
        type: 'module'
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
        target: process.env.VITE_API_URL || 'http://127.0.0.1:8000',
        changeOrigin: true,
        secure: false,
        rewrite: (path) => path,
        ws: true,
        logLevel: 'info',
        onProxyReq: (proxyReq, req, res) => {
          // Ensure proper headers for all requests
          proxyReq.setHeader('X-Forwarded-For', req.socket.remoteAddress);
          proxyReq.setHeader('X-Forwarded-Proto', 'http');
          proxyReq.setHeader('X-Forwarded-Host', '127.0.0.1:3000');
        }
      },
      '/sanctum': {
        target: process.env.VITE_API_URL || 'http://127.0.0.1:8000',
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