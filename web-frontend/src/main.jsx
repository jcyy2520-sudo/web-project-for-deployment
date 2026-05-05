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

const normalizeApiBaseUrl = (value) => {
  if (!value) {
    return value;
  }

  return value.replace(/\/$/, '').replace(/\/api$/, '');
};

// Determine API URL with proper fallback
// NOTE: Do NOT include /api here - the routes already have /api prefix
// IMPORTANT: Set VITE_API_URL in .env for production builds
let API_URL;
if (envApiUrl) {
  API_URL = normalizeApiBaseUrl(envApiUrl);
} else if (isProduction) {
  // Production builds MUST set VITE_API_URL; this fallback ensures graceful degradation
  API_URL = normalizeApiBaseUrl(import.meta.env.VITE_PRODUCTION_API_URL || 'https://legaleaase.site');
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

const RootWrapper = import.meta.env.DEV ? React.Fragment : React.StrictMode;

ReactDOM.createRoot(document.getElementById('root')).render(
  <RootWrapper>
    <App />
  </RootWrapper>
)

// Laravel Echo (real-time) initialization (optional)
// - To enable: install `pusher-js` and `laravel-echo` then set Vite env vars
//   `VITE_PUSHER_KEY`, `VITE_PUSHER_CLUSTER`, `VITE_PUSHER_HOST` as needed.
// - If not available, this gracefully falls back to a no-op stub.
;(async function setupEchoClient() {
  const createEchoStub = () => {
    window.Echo = window.Echo || {
      connected: false,
      channel: () => ({ listen: () => {}, stopListening: () => {} }),
      private: () => ({ listen: () => {}, stopListening: () => {} }),
    };
  };

  const resolveRealtimeConfig = async () => {
    const envConfig = {
      enabled: Boolean(import.meta.env.VITE_REVERB_APP_KEY || import.meta.env.VITE_PUSHER_KEY || window?.PUSHER_KEY),
      key: import.meta.env.VITE_REVERB_APP_KEY || import.meta.env.VITE_PUSHER_KEY || window?.PUSHER_KEY || null,
      host: import.meta.env.VITE_REVERB_HOST || null,
      port: import.meta.env.VITE_REVERB_PORT || null,
      scheme: import.meta.env.VITE_REVERB_SCHEME || null,
    };

    try {
      const response = await axios.get('/api/realtime/broadcast-config', { timeout: 5000 });
      const serverConfig = response.data?.data;

      if (serverConfig?.key) {
        return {
          enabled: Boolean(serverConfig.enabled),
          key: serverConfig.key,
          host: serverConfig.host,
          port: serverConfig.port,
          scheme: serverConfig.scheme,
        };
      }
    } catch (error) {
      console.debug('Realtime config endpoint unavailable, falling back to Vite env.', error);
    }

    return envConfig;
  };

  const realtimeConfig = await resolveRealtimeConfig();
  const key = realtimeConfig.key;

  if (!realtimeConfig.enabled || !key) {
    createEchoStub();
    console.debug('Echo not initialized: no realtime config found; running in stub mode');
    return;
  }

  try {
    const Pusher = (await import('pusher-js')).default || window.Pusher;
    const Echo = (await import('laravel-echo')).default || window.Echo;

    window.Pusher = Pusher;

    const normalizedHost = realtimeConfig.host && !['0.0.0.0', '127.0.0.1', 'localhost'].includes(realtimeConfig.host)
      ? realtimeConfig.host
      : window.location.hostname;
    const scheme = realtimeConfig.scheme || (window.location.protocol === 'https:' ? 'https' : 'http');
    const port = Number(realtimeConfig.port || (scheme === 'https' ? 443 : 8080));

    window.Echo = new Echo({
        broadcaster: 'reverb',
        key,
        wsHost: normalizedHost,
        wsPort: port,
        wssPort: port,
        forceTLS: scheme === 'https',
        enabledTransports: ['ws', 'wss'],
    });

    window.__echoBridgeCleanup?.();

    const registerRealtimeBridge = (channelName, eventName, browserEventName) => {
      const channel = window.Echo.channel(channelName);

      if (!channel || typeof channel.listen !== 'function') {
        return null;
      }

      channel.listen(eventName, (event) => {
        window.dispatchEvent(new CustomEvent(browserEventName, { detail: event }));
      });

      return () => {
        try {
          if (typeof channel.stopListening === 'function') {
            channel.stopListening(eventName);
          }
        } catch (error) {
          console.debug(`Echo cleanup failed for ${channelName}:${eventName}`, error);
        }
      };
    };

    const bridgeCleanups = [
      registerRealtimeBridge('unavailable-dates', 'UnavailableDatesUpdated', 'unavailableDatesChanged'),
      registerRealtimeBridge('slot-capacities', '.SlotCapacityChanged', 'slotCapacitiesChanged'),
      registerRealtimeBridge('appointment-settings', '.AppointmentSettingsChanged', 'appointmentSettingsChanged'),
    ].filter(Boolean);

    window.__echoBridgeCleanup = () => {
      bridgeCleanups.forEach((cleanup) => cleanup());
      delete window.__echoBridgeCleanup;
    };

    console.debug('Laravel Echo initialization attempted');
  } catch (err) {
    console.warn('Failed to initialize Echo at runtime. Using stub.', err);
    createEchoStub();
  }
})();