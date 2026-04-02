import React from 'react'
import ReactDOM from 'react-dom/client'
import axios from 'axios'
import App from './App.jsx'
import './index.css'

// ============================================================================
// CRITICAL: Initialize theme BEFORE React renders to prevent flashing
// ============================================================================
(() => {
  try {
    // Get stored theme preference or system preference
    let isDarkMode = true; // Default to dark mode
    const saved = localStorage.getItem('isDarkMode');
    
    if (saved !== null) {
      isDarkMode = saved === 'true';
    } else if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
      isDarkMode = window.matchMedia('(prefers-color-scheme: dark)').matches;
    }
    
    // Apply theme class to HTML element IMMEDIATELY
    const html = document.documentElement;
    if (isDarkMode) {
      html.classList.add('dark');
      html.setAttribute('data-theme', 'dark');
    } else {
      html.classList.remove('dark');
      html.setAttribute('data-theme', 'light');
    }
    
    // Set data attribute for CSS variable selection
    document.documentElement.style.colorScheme = isDarkMode ? 'dark' : 'light';
  } catch (e) {
    console.error('Failed to initialize theme:', e);
    // Fallback: ensure dark mode
    document.documentElement.classList.add('dark');
  }
})();

// Set API URL from environment
// On Vercel (production): use cPanel backend URL
// In development: use local backend
const isProduction = import.meta.env.PROD;
const envApiUrl = import.meta.env.VITE_API_URL;

// Determine API URL with proper fallback
// NOTE: Do NOT include /api here - the routes already have /api prefix
// IMPORTANT: Set VITE_API_URL in .env for production builds
let API_URL;
if (envApiUrl) {
  API_URL = envApiUrl;
} else if (isProduction) {
  // Production builds MUST set VITE_API_URL; this fallback ensures graceful degradation
  API_URL = import.meta.env.VITE_PRODUCTION_API_URL || 'https://legaleaase.site';
  console.warn('[config] VITE_API_URL not set for production build. Using fallback.');
} else {
  // Development: Don't set baseURL - let Vite proxy handle /api routes
  API_URL = null;
}

// SECURITY: Enforce HTTPS for production API communication
if (isProduction && API_URL && !API_URL.startsWith('https://')) {
  console.error('[security] Production API URL MUST use HTTPS. Blocking request.');
  API_URL = null; 
}

// Configure axios to use the API URL
if (API_URL) {
  axios.defaults.baseURL = API_URL;
}

// Re-enable StrictMode — helps catch bugs in development (no effect in production)
ReactDOM.createRoot(document.getElementById('root')).render(
  <React.StrictMode>
    <App />
  </React.StrictMode>
)

// Laravel Echo (real-time) initialization (optional)
// - To enable: install `pusher-js` and `laravel-echo` then set Vite env vars
//   `VITE_PUSHER_KEY`, `VITE_PUSHER_CLUSTER`, `VITE_PUSHER_HOST` as needed.
// - If not available, this gracefully falls back to a no-op stub.
;(async function setupEchoClient() {
  const key = import.meta.env.VITE_PUSHER_KEY || window?.PUSHER_KEY || null;
  // Disable Echo entirely in dev when no key is provided to avoid noisy CORS/WS errors
  if (!key) {
    // No credentials provided — create a lightweight stub that exposes `window.Echo` to avoid breakage
    window.Echo = window.Echo || {
      connected: false,
      channel: () => ({ listen: () => {}, stopListening: () => {} }),
      private: () => ({ listen: () => {} }),
    };
    console.debug('Echo not initialized: no PUSHER key found; running in stub mode');
    return;
  }

  try {
    // Prefer local packages when available (installed via npm). Fall back to CDN otherwise.
    let LocalPusher = null;
    let LocalEcho = null;
    try {
      // Dynamic import - will succeed if packages installed (e.g., npm install pusher-js laravel-echo)
      LocalPusher = (await import('pusher-js')).default || (await import('pusher-js'));
      LocalEcho = (await import('laravel-echo')).default || (await import('laravel-echo'));
    } catch (localErr) {
      // Not installed or import failed; fall back to CDN
      const loadScript = (src) => new Promise((resolve, reject) => {
        const s = document.createElement('script');
        s.src = src;
        s.async = true;
        s.onload = () => resolve();
        s.onerror = (e) => reject(e);
        document.head.appendChild(s);
      });

      const pusherCdn = 'https://js.pusher.com/7.2/pusher.min.js';
      const echoCdn = 'https://cdn.jsdelivr.net/npm/laravel-echo/dist/echo.iife.js';

      try { await loadScript(pusherCdn); } catch (e) { console.warn('Failed to load Pusher from CDN', e); }
      try { await loadScript(echoCdn); } catch (e) { console.debug('Laravel Echo script not loaded from CDN', e); }

      LocalPusher = window.Pusher;
      LocalEcho = window.Echo;
    }

    const cluster = import.meta.env.VITE_PUSHER_CLUSTER || window?.PUSHER_CLUSTER || undefined;
    const host = import.meta.env.VITE_PUSHER_HOST || window?.PUSHER_HOST || undefined;

    // If local imports are available, use them; else use UMD globals from CDN
    if (LocalEcho && (typeof LocalEcho === 'function' || typeof LocalEcho === 'object')) {
      // If LocalEcho is the module constructor
      if (typeof LocalEcho === 'function') {
        // If we imported the Pusher constructor, instantiate it so Echo receives a client instance
        const pusherClient = LocalPusher ? new LocalPusher(key, {
          cluster,
          wsHost: host || undefined,
          wsPort: host ? 6001 : undefined,
          forceTLS: !!(import.meta.env.PROD),
        }) : (window.Pusher || undefined);

        window.Echo = new LocalEcho({
          broadcaster: 'pusher',
          key,
          cluster,
          wsHost: host || undefined,
          wsPort: host ? 6001 : undefined,
          forceTLS: !!(import.meta.env.PROD),
          client: pusherClient,
        });
      } else {
        // LocalEcho may be the UMD-like exposed object
        window.Echo = new (LocalEcho || window.Echo)({
          broadcaster: 'pusher',
          key,
          cluster,
          wsHost: host || undefined,
          wsPort: host ? 6001 : undefined,
          forceTLS: !!(import.meta.env.PROD),
        });
      }
    } else if (window.Pusher) {
      // Build a minimal wrapper around Pusher to mimic the channel/listen API used in app
      const pusher = new window.Pusher(key, {
        cluster,
        wsHost: host || undefined,
        wsPort: host ? 6001 : undefined,
        forceTLS: !!(import.meta.env.PROD),
      });

      window.Echo = {
        _pusher: pusher,
        connected: true,
        channel: (name) => ({
          listen: (event, cb) => {
            try { pusher.subscribe(name).bind(event, cb); } catch (e) { console.debug(e); }
          },
          stopListening: () => { try { pusher.unsubscribe(name); } catch (e) {} }
        }),
        private: (name) => ({ listen: (event, cb) => { try { pusher.subscribe(name).bind(event, cb); } catch (e) {} } })
      };
    } else {
      window.Echo = window.Echo || {
        connected: false,
        channel: () => ({ listen: () => {}, stopListening: () => {} }),
        private: () => ({ listen: () => {} }),
      };
    }

    // Listen for admin broadcast notifications for unavailable dates
    try {
      window.Echo.channel('unavailable-dates').listen('UnavailableDatesUpdated', (e) => {
        window.dispatchEvent(new CustomEvent('unavailableDatesChanged', { detail: e }));
      });
    } catch (e) {
      console.debug('Echo channel setup failed:', e);
    }

    console.debug('Laravel Echo initialization attempted (CDN/runtime)');
  } catch (err) {
    console.warn('Failed to initialize Echo at runtime. Using stub.', err);
    window.Echo = window.Echo || {
      connected: false,
      channel: () => ({ listen: () => {}, stopListening: () => {} }),
      private: () => ({ listen: () => {} }),
    };
  }
})();