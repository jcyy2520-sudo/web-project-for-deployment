// Debug utility to diagnose API configuration issues
import logger from './logger';

export const debugApiConfig = () => {
  logger.group('🔍 API Configuration Debug');
  
  // Check current URL
  logger.log('Current URL:', window.location.href);
  logger.log('Current Origin:', window.location.origin);
  
  // Check axios defaults
  import('axios').then(axiosModule => {
    const axios = axiosModule.default;
    logger.log('Axios Config:', {
      baseURL: axios.defaults.baseURL || 'NOT SET (using Vite proxy)',
      withCredentials: axios.defaults.withCredentials,
      timeout: axios.defaults.timeout,
      headers: {
        Authorization: axios.defaults.headers.common['Authorization'] ? 'SET' : 'NOT SET',
        'Content-Type': axios.defaults.headers.common['Content-Type']
      }
    });
  });
  
  // Check Vite config from import.meta
  if (typeof import.meta !== 'undefined' && import.meta.env) {
    logger.log('Vite Env:', {
      MODE: import.meta.env.MODE,
      DEV: import.meta.env.DEV,
      PROD: import.meta.env.PROD,
      SSR: import.meta.env.SSR,
      BASE: import.meta.env.BASE
    });
  }
  
  // Try a test request to check proxy
  logger.log('Testing proxy with /api/health...');
  // Use axios so this utility follows the same client configuration as the app
  import('axios').then(axiosModule => {
    const axios = axiosModule.default;
    axios.get('/api/health', { withCredentials: true })
      .then(resp => {
        logger.log('✅ Proxy working! Response:', resp.status, resp.statusText || resp.status);
        logger.log('Health payload:', resp.data);
      })
      .catch(err => {
        const msg = err?.response ? `Status ${err.response.status}` : err.message || String(err);
        logger.error('❌ Proxy FAILED:', msg);
        logger.error('This means /api requests are NOT being proxied to localhost:8000 or backend is offline');
      });
  });
  
  logger.groupEnd();
};

// Call on window load
if (typeof window !== 'undefined') {
  window.debugApiConfig = debugApiConfig;
  logger.info('💡 Debug utility available. Run: window.debugApiConfig()');
}
