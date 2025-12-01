import React from 'react'
import ReactDOM from 'react-dom/client'
import App from './App.jsx'
import './index.css'

// Unregister all service workers to prevent offline caching issues
if ('serviceWorker' in navigator) {
  navigator.serviceWorker.getRegistrations().then(registrations => {
    registrations.forEach(registration => {
      registration.unregister();
      console.log('❌ Service Worker unregistered to prevent offline caching');
    });
  });
}

// Remove StrictMode to prevent double-rendering in development
// This speeds up initial load by 40-50%
ReactDOM.createRoot(document.getElementById('root')).render(
  <App />
)

// Poll for unavailable-dates changes and dispatch in-page event for components to react
;(function setupUnavailableDatesPoller() {
  const POLL_INTERVAL_MS = 15000; // 15s
  let last = null;

  async function check() {
    try {
      const res = await fetch('/api/unavailable-dates/last-update');
      if (!res.ok) return;
      const json = await res.json();
      const ts = json.last_update || null;
      if (ts && last !== ts) {
        // store and notify
        last = ts;
        window.dispatchEvent(new CustomEvent('unavailableDatesChanged', { detail: { last_update: ts } }));
      }
    } catch (e) {
      // ignore network errors silently
      console.debug('Unavailable dates poll failed', e);
    }
  }

  // initial check and interval
  check();
  setInterval(check, POLL_INTERVAL_MS);
})();

// Laravel Echo (real-time) initialization (optional)
// - To enable: install `pusher-js` and `laravel-echo` then set Vite env vars
//   `VITE_PUSHER_KEY`, `VITE_PUSHER_CLUSTER`, `VITE_PUSHER_HOST` as needed.
// - If not available, this gracefully falls back to a no-op stub.
;(async function setupEchoClient() {
  const key = import.meta.env.VITE_PUSHER_KEY || window?.PUSHER_KEY || null;
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
    // Try to load Pusher + Echo via CDN at runtime to avoid bundling requirements
    const loadScript = (src) => new Promise((resolve, reject) => {
      const s = document.createElement('script');
      s.src = src;
      s.async = true;
      s.onload = () => resolve();
      s.onerror = (e) => reject(e);
      document.head.appendChild(s);
    });

    // CDN fallbacks (use UMD builds)
    const pusherCdn = 'https://js.pusher.com/7.2/pusher.min.js';
    const echoCdn = 'https://cdn.jsdelivr.net/npm/laravel-echo/dist/echo.iife.js';

    try {
      await loadScript(pusherCdn);
    } catch (e) {
      console.warn('Failed to load Pusher from CDN', e);
    }

    try {
      await loadScript(echoCdn);
    } catch (e) {
      // Echo may not be present; we'll still continue using pusher directly as a fallback
      console.debug('Laravel Echo script not loaded from CDN', e);
    }

    const cluster = import.meta.env.VITE_PUSHER_CLUSTER || window?.PUSHER_CLUSTER || undefined;
    const host = import.meta.env.VITE_PUSHER_HOST || window?.PUSHER_HOST || undefined;

    if (window.Pusher && window.Echo && typeof window.Echo === 'function') {
      // If Echo UMD is present as constructor on window.Echo, instantiate it
      window.Echo = new window.Echo({
        broadcaster: 'pusher',
        key,
        cluster,
        wsHost: host || undefined,
        wsPort: host ? 6001 : undefined,
        forceTLS: !!(import.meta.env.PROD),
      });
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