// hooks/usePerformanceMonitor.js
import { useEffect, useRef, useCallback } from 'react';

/**
 * Hook to monitor and log API performance
 * Helps identify bottlenecks and slow endpoints
 */
export const usePerformanceMonitor = () => {
  const metricsRef = useRef({
    apiCalls: [],
    pageLoadTime: performance.now(),
    renderTime: 0
  });

  const recordApiCall = useCallback((endpoint, duration, status = 200) => {
    metricsRef.current.apiCalls.push({
      endpoint,
      duration,
      status,
      timestamp: new Date().toISOString(),
      cached: duration < 10 // Likely cached if under 10ms
    });

    // Performance warnings
    if (duration > 1000) {
      console.warn(`⚠️ Slow API: ${endpoint} took ${duration.toFixed(2)}ms`);
    }
    if (duration > 3000) {
      console.error(`❌ Very Slow API: ${endpoint} took ${duration.toFixed(2)}ms`);
    }

    // Keep only last 100 requests for performance
    if (metricsRef.current.apiCalls.length > 100) {
      metricsRef.current.apiCalls.shift();
    }
  }, []);

  const getReport = useCallback(() => {
    const calls = metricsRef.current.apiCalls;
    if (calls.length === 0) return null;

    const avgDuration = calls.reduce((sum, c) => sum + c.duration, 0) / calls.length;
    const slowestCall = calls.reduce((max, c) => c.duration > max.duration ? c : max, calls[0]);
    const cachedCount = calls.filter(c => c.cached).length;

    return {
      totalCalls: calls.length,
      averageDuration: avgDuration.toFixed(2),
      slowestCall: `${slowestCall.endpoint} (${slowestCall.duration.toFixed(2)}ms)`,
      cacheHitRate: `${((cachedCount / calls.length) * 100).toFixed(1)}%`,
      pageLoadTime: (performance.now() - metricsRef.current.pageLoadTime).toFixed(2),
      recentCalls: calls.slice(-5) // Last 5 calls
    };
  }, []);

  const logReport = useCallback(() => {
    const report = getReport();
    if (report) {
      console.table(report);
      console.table(report.recentCalls);
    }
  }, [getReport]);

  // Auto-log on page unload
  useEffect(() => {
    const handleBeforeUnload = () => {
      logReport();
    };

    window.addEventListener('beforeunload', handleBeforeUnload);
    return () => window.removeEventListener('beforeunload', handleBeforeUnload);
  }, [logReport]);

  return {
    recordApiCall,
    getReport,
    logReport,
    metrics: metricsRef.current
  };
};

// Global performance monitor instance
let globalMonitor = null;

export const getGlobalPerformanceMonitor = () => {
  if (!globalMonitor) {
    globalMonitor = {
      apiCalls: [],
      recordApiCall(endpoint, duration, status) {
        this.apiCalls.push({
          endpoint,
          duration,
          status,
          timestamp: new Date().toISOString()
        });

        // Log slow requests
        if (duration > 1000) {
          console.warn(`⚠️ [API] ${endpoint} took ${duration.toFixed(2)}ms`);
        }

        // Keep only last 200 calls
        if (this.apiCalls.length > 200) {
          this.apiCalls.shift();
        }
      },
      getSlowEndpoints() {
        return this.apiCalls
          .filter(c => c.duration > 500)
          .sort((a, b) => b.duration - a.duration)
          .slice(0, 10);
      },
      getStats() {
        if (this.apiCalls.length === 0) {
          return { message: 'No API calls recorded' };
        }

        const durations = this.apiCalls.map(c => c.duration);
        const avgDuration = durations.reduce((a, b) => a + b) / durations.length;
        const maxDuration = Math.max(...durations);
        const minDuration = Math.min(...durations);

        return {
          totalCalls: this.apiCalls.length,
          averageDuration: avgDuration.toFixed(2),
          maxDuration: maxDuration.toFixed(2),
          minDuration: minDuration.toFixed(2),
          slowEndpoints: this.getSlowEndpoints()
        };
      }
    };
  }

  return globalMonitor;
};
