import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'
// import { VitePWA } from 'vite-plugin-pwa' // Disabled PWA plugin to fix proxy issues

export default defineConfig({
  plugins: [
    react(),
    // Disabled PWA in development to prevent service worker proxy issues
    // VitePWA({
    //   registerType: 'autoUpdate',
    //   workbox: {
    //     globPatterns: ['**/*.{js,css,html,ico,png,svg}']
    //   },
    //   manifest: {
    //     name: 'Law Notary System',
    //     short_name: 'NotarySystem',
    //     description: 'Law Notary Appointment System',
    //     theme_color: '#000000',
    //     icons: [
    //       {
    //         src: '/icon-192.png',
    //         sizes: '192x192',
    //         type: 'image/png'
    //       },
    //       {
    //         src: '/icon-512.png',
    //         sizes: '512x512',
    //         type: 'image/png'
    //       }
    //     ]
    //   }
    // })
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