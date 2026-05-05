import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'
import { VitePWA } from 'vite-plugin-pwa'

export default defineConfig({
  plugins: [
    react(),
    VitePWA({
      registerType: 'autoUpdate',
      strategies: 'injectManifest',
      srcDir: 'src',
      filename: 'sw.js',
      injectManifest: {
        globPatterns: ['**/*.{js,css,html,ico,png,jpg,jpeg,webp,svg}'],
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