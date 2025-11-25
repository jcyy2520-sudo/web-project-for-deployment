// hooks/useApi.js
import { useState, useCallback, useRef } from 'react';
import axios from 'axios';
import logger from '../utils/logger';

// Global CSRF token flag
let csrfTokenLoaded = false;

// Global cache for API responses with TTL
const apiCache = new Map();
const pendingRequests = new Map();

// Simple performance monitoring - records API metrics
if (typeof window !== 'undefined' && !window.__API_METRICS) {
  window.__API_METRICS = [];
  window.__PERF_REPORT = () => {
    if (window.__API_METRICS.length === 0) return 'No API calls';
    const calls = window.__API_METRICS;
    const avg = calls.reduce((sum, c) => sum + c.duration, 0) / calls.length;
    const slowest = calls.reduce((max, c) => c.duration > max.duration ? c : max);
    return {
      totalCalls: calls.length,
      avgDuration: avg.toFixed(2) + 'ms',
      slowest: `${slowest.endpoint} (${slowest.duration.toFixed(2)}ms)`,
      recentCalls: calls.slice(-5)
    };
  };
}

const recordApiMetric = (endpoint, duration, status) => {
  if (typeof window !== 'undefined' && window.__API_METRICS) {
    window.__API_METRICS.push({ endpoint, duration, status, timestamp: Date.now() });
    // Warn about slow requests
    if (duration > 1000) {
      logger.warn(`⚠️ Slow API: ${endpoint} took ${duration.toFixed(2)}ms`);
    }
  }
};

// Generate cache key from endpoint and params
const generateCacheKey = (endpoint, params = {}) => {
  return `${endpoint}:${JSON.stringify(params)}`;
};

// Check if cached response is still valid
const isCacheValid = (timestamp, ttlSeconds) => {
  return Date.now() - timestamp < ttlSeconds * 1000;
};

// Get cache duration based on endpoint
const getCacheTTL = (endpoint) => {
  if (endpoint.includes('/stats')) return 120; // 2 minutes for stats
  if (endpoint.includes('/appointments')) return 30; // 30 seconds for appointments
  if (endpoint.includes('/users')) return 60; // 1 minute for users
  if (endpoint.includes('/services')) return 600; // 10 minutes for services
  if (endpoint.includes('/unavailable')) return 300; // 5 minutes for unavailable dates
  return 60; // Default: 1 minute
};

export const useApi = () => {
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);
  const abortControllerRef = useRef(null);

  const ensureCsrfToken = useCallback(async () => {
    if (csrfTokenLoaded) return;
    
    try {
      await axios.get('/sanctum/csrf-cookie');
      csrfTokenLoaded = true;
    } catch (error) {
      console.error('CSRF token failed:', error);
    }
  }, []);

  const callApi = useCallback(async (apiCall, options = {}) => {
    const {
      showLoading = true,
      clearError = true,
      abortPrevious = true,
      requireAuth = true,
      cache = true,
      skipCache = false,
      maxRetries = 2,
      retryDelay = 500
    } = options;

    // Extract endpoint for caching purposes
    let endpoint = null;
    if (typeof apiCall === 'function') {
      // Can't easily extract endpoint from function
    } else if (typeof apiCall === 'string') {
      endpoint = apiCall;
    }

    // Check cache first if enabled
    if (cache && endpoint && !skipCache) {
      const cacheKey = generateCacheKey(endpoint);
      const cached = apiCache.get(cacheKey);
      
      if (cached && isCacheValid(cached.timestamp, getCacheTTL(endpoint))) {
        logger.log(`✅ Cache HIT: ${endpoint}`);
        return cached.data;
      }
    }

    // Check for pending request (request deduplication)
    if (endpoint) {
      const pendingKey = endpoint;
      if (pendingRequests.has(pendingKey)) {
        logger.log(`🔄 Deduplicating request: ${endpoint}`);
        return pendingRequests.get(pendingKey);
      }
    }

    // Cancel previous request if needed
    if (abortPrevious && abortControllerRef.current) {
      abortControllerRef.current.abort();
    }

    // Create new AbortController only if we're going to use it
    let abortController = null;
    if (abortPrevious) {
      abortController = new AbortController();
      abortControllerRef.current = abortController;
    }
    
    if (showLoading) setLoading(true);
    if (clearError) setError(null);
    
    const startTime = performance.now();
    
    // Create promise for this request
    const requestPromise = (async () => {
      let lastError;
      let retryCount = 0;
      
      while (retryCount <= maxRetries) {
        try {
          // Only get CSRF token for authenticated requests
          if (requireAuth) {
            await ensureCsrfToken();
          }

          let response;
          if (typeof apiCall === 'function') {
            // If it's a function, call it with the signal if abortController exists
            if (abortController) {
              response = await apiCall(abortController.signal);
            } else {
              response = await apiCall();
            }
          } else {
            // If it's already an axios call or promise, use it directly
            if (apiCall && typeof apiCall === 'object' && apiCall.request) {
              // This is an axios call object - add signal if abortController exists
              const config = {
                ...apiCall,
                ...(abortController && { signal: abortController.signal })
              };
              response = await axios(config);
            } else {
              // This is a direct promise, use as is
              response = await apiCall;
            }
          }

          // Enhanced response handling for different structures
          let responseData = response?.data;
          let status = response?.status;

          // Record performance metric
          const duration = performance.now() - startTime;
          const endpointForMetric = response?.config?.url || endpoint || 'unknown';
          recordApiMetric(endpointForMetric, duration, status);

          // Handle case where response might be the data directly
          if (response && typeof response === 'object' && !response.status && !response.data) {
            responseData = response;
            status = 200; // Assume success
          }

          // Handle successful responses
          if ((status >= 200 && status < 300) || !status) {
            const result = { 
              success: true, 
              data: responseData,
              status: status || 200
            };

            // Cache successful responses
            if (cache && endpoint) {
              const cacheKey = generateCacheKey(endpoint);
                apiCache.set(cacheKey, { data: result, timestamp: Date.now() });
                logger.log(`💾 Cache SET: ${endpoint}`);
            }

            return result;
          }

          // Handle error responses
          const message = responseData?.message || 
                         responseData?.error || 
                         `Request failed with status ${status}`;
          
          // Don't set error for 401/403 - let AuthContext handle them
          if (status !== 401 && status !== 403) {
            setError(message);
          }
          
          return { 
            success: false, 
            error: message, 
            data: responseData,
            status: status 
          };

        } catch (err) {
          lastError = err;
          const duration = performance.now() - startTime;
          
          // Ignore abort errors
          if (axios.isCancel(err)) {
            return { success: false, aborted: true };
          }
          
          // Check if this is a network error that could be retried
          const isNetworkError = err.code === 'ERR_NETWORK' || 
                               err.message === 'Network Error' ||
                               !err.response;
          
          if (isNetworkError && retryCount < maxRetries) {
            retryCount++;
            logger.warn(`⚠️ Network error on attempt ${retryCount}, retrying in ${retryDelay}ms...`, err.message);
            // Wait before retrying
            await new Promise(resolve => setTimeout(resolve, retryDelay));
            continue; // Retry the request
          }
          
          logger.error('API Call Error:', err);
          
          let errorMessage = 'Something went wrong';
          
          // Check for network errors - could be proxy issue or offline
          if (err.code === 'ERR_NETWORK' || err.message === 'Network Error') {
            errorMessage = 'Network connection failed. Check if backend is running (port 8000) and try again.';
            logger.error('❌ Network Error Details:', {
              code: err.code,
              message: err.message,
              config: err.config,
              url: err.config?.url,
              proxyUrl: window.location.origin,
              attempts: retryCount + 1
            });
          } else if (err.response) {
            // Server responded with error status
            errorMessage = err.response.data?.message || 
                          err.response.data?.error || 
                          `Request failed with status ${err.response.status}`;

            recordApiMetric(err.config?.url || endpoint || 'unknown', duration, err.response.status);

            // Don't set error for auth issues - let AuthContext handle them
            if (err.response.status === 401 || err.response.status === 403) {
              return { 
                success: false, 
                error: errorMessage,
                status: err.response.status,
                isAuthError: true
              };
            }
          } else if (err.request) {
            // Request was made but no response received
            errorMessage = 'No response from server. The backend may be offline.';
          } else {
            // Something else happened
            errorMessage = err.message || 'Request configuration error';
          }
          
          setError(errorMessage);
          
          return { 
            success: false, 
            error: errorMessage,
            status: err.response?.status 
          };
        }
      }
      
      // If we got here, all retries failed
          logger.error('All retry attempts failed:', lastError);
      const errorMsg = 'Request failed after multiple retries. Backend may be offline.';
      setError(errorMsg);
      return {
        success: false,
        error: errorMsg
      };
    })();

    // Handle finally logic after the promise resolves
    requestPromise.finally(() => {
      if (showLoading) setLoading(false);
      // Only clear the reference if it's the current controller
      if (abortControllerRef.current === abortController) {
        abortControllerRef.current = null;
      }
      // Remove from pending requests
      if (endpoint) {
      pendingRequests.delete(endpoint);
    }
    });

    // Store pending request for deduplication
    if (endpoint) {
      pendingRequests.set(endpoint, requestPromise);
    }

    return requestPromise;
  }, [ensureCsrfToken]);

  const clearError = useCallback(() => setError(null), []);

  const cancelRequest = useCallback(() => {
    if (abortControllerRef.current) {
      abortControllerRef.current.abort();
      abortControllerRef.current = null;
    }
  }, []);

  return {
    loading,
    error,
    callApi,
    clearError,
    cancelRequest,
  };
};

// Utility function to clear specific cache entries
export const clearApiCache = (endpoint = null) => {
  if (endpoint) {
    const cacheKey = generateCacheKey(endpoint);
    apiCache.delete(cacheKey);
    logger.log(`🗑️ Cache cleared for: ${endpoint}`);
  } else {
    apiCache.clear();
    logger.log('🗑️ All cache cleared');
  }
};

// Export cache statistics utility
export const getCacheStats = () => {
  return {
    cacheSize: apiCache.size,
    cacheKeys: Array.from(apiCache.keys())
  };
};