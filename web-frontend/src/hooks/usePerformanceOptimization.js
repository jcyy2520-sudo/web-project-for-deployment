// hooks/usePerformanceOptimization.js
import { useCallback, useRef } from 'react';

/**
 * Hook to optimize performance by:
 * 1. Debouncing API calls
 * 2. Deduplicating concurrent requests
 * 3. Tracking request progress
 */
export const usePerformanceOptimization = () => {
  const requestQueueRef = useRef({});
  const timerRef = useRef({});

  /**
   * Deduplicate and debounce API calls
   * If the same request is made multiple times, it waits for the first one to complete
   * @param {string} key - Unique identifier for the request
   * @param {function} apiCall - The API call to make
   * @param {number} debounceMs - Debounce time in milliseconds
   */
  const debouncedApiCall = useCallback(async (key, apiCall, debounceMs = 300) => {
    // If a request is already in progress, return the existing promise
    if (requestQueueRef.current[key]) {
      return requestQueueRef.current[key];
    }

    // Clear any pending timer for this key
    if (timerRef.current[key]) {
      clearTimeout(timerRef.current[key]);
    }

    // Create a new promise for this request
    const promise = new Promise((resolve) => {
      timerRef.current[key] = setTimeout(async () => {
        try {
          const result = await apiCall();
          resolve(result);
        } catch (error) {
          resolve({ error });
        } finally {
          // Clean up the request queue
          delete requestQueueRef.current[key];
          delete timerRef.current[key];
        }
      }, debounceMs);
    });

    requestQueueRef.current[key] = promise;
    return promise;
  }, []);

  /**
   * Parallelize multiple API calls with proper error handling
   * @param {object} calls - Object with keys and async functions as values
   * @example
   * parallelApiCalls({
   *   stats: () => axios.get('/stats'),
   *   users: () => axios.get('/users'),
   *   appointments: () => axios.get('/appointments')
   * })
   */
  const parallelApiCalls = useCallback(async (calls) => {
    const keys = Object.keys(calls);
    const promises = keys.map(key => 
      Promise.resolve(calls[key]()).catch(err => ({ error: err, key }))
    );

    const results = await Promise.all(promises);
    
    const output = {};
    keys.forEach((key, index) => {
      output[key] = results[index];
    });

    return output;
  }, []);

  /**
   * Batch multiple state updates into a single render
   * Useful for updating multiple pieces of state from different API responses
   */
  const batchStateUpdates = useCallback((updateFunctions) => {
    // Use a promise-based approach to batch updates
    return Promise.all(updateFunctions.map(fn => Promise.resolve(fn())));
  }, []);

  const cancelPendingRequests = useCallback((pattern) => {
    Object.keys(requestQueueRef.current).forEach(key => {
      if (!pattern || key.includes(pattern)) {
        if (timerRef.current[key]) {
          clearTimeout(timerRef.current[key]);
        }
        delete requestQueueRef.current[key];
        delete timerRef.current[key];
      }
    });
  }, []);

  return {
    debouncedApiCall,
    parallelApiCalls,
    batchStateUpdates,
    cancelPendingRequests
  };
};

/**
 * Hook to measure and optimize component performance
 */
export const usePerformanceMetrics = (componentName) => {
  const startTimeRef = useRef(Date.now());
  const metricsRef = useRef({
    renders: 0,
    apiCalls: [],
    renderTimes: []
  });

  const recordMetric = useCallback((metricName, value, metadata = {}) => {
    const timestamp = Date.now() - startTimeRef.current;
    
    metricsRef.current.apiCalls.push({
      name: metricName,
      value,
      timestamp,
      metadata
    });

    // Log if in development and slow
    if (process.env.NODE_ENV === 'development' && value > 1000) {
      console.warn(`⚠️ [${componentName}] ${metricName} took ${value}ms`, metadata);
    }
  }, [componentName]);

  const logReport = useCallback(() => {
    console.table({
      component: componentName,
      uptime: Date.now() - startTimeRef.current,
      renders: metricsRef.current.renders,
      avgRenderTime: metricsRef.current.renderTimes.length ? 
        (metricsRef.current.renderTimes.reduce((a, b) => a + b) / metricsRef.current.renderTimes.length).toFixed(2) : 0,
      slowestAPI: metricsRef.current.apiCalls.length ?
        metricsRef.current.apiCalls.reduce((max, current) => 
          current.value > max.value ? current : max
        ).name : 'none'
    });
  }, [componentName]);

  return {
    recordMetric,
    logReport,
    getMetrics: () => metricsRef.current
  };
};
