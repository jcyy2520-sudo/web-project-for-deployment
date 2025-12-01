// config/apiConfig.js
/**
 * API Configuration for Smart Caching and Performance Optimization
 * 
 * This configuration defines:
 * 1. Cache durations for different types of requests
 * 2. Request deduplication rules
 * 3. Retry strategies
 * 4. Performance thresholds
 */

export const apiConfig = {
  // Cache durations (in seconds)
  cacheDuration: {
    // Short cache for volatile data
    stats: 120,          // Admin stats - cached for 2 minutes
    summary: 120,        // Quick stats - cached for 2 minutes
    appointments: 30,    // Appointments - cached for 30 seconds
    users: 60,           // Users list - cached for 1 minute
    
    // Medium cache for stable data
    services: 600,       // Services - cached for 10 minutes
    unavailableDates: 300, // Dates - cached for 5 minutes
    
    // Long cache for rarely changing data
    config: 3600,        // Configuration - cached for 1 hour
    permissions: 3600,   // Permissions - cached for 1 hour
  },

  // Request timeout (milliseconds)
  timeout: 10000,

  // Retry configuration
  retry: {
    maxAttempts: 3,
    delayMs: 1000,
    backoffMultiplier: 2, // Exponential backoff
  },

  // Performance thresholds (milliseconds)
  performanceThresholds: {
    slow: 1000,      // Log as warning if request takes > 1s
    verySlow: 3000,  // Log as error if request takes > 3s
  },

  // Endpoints that should never be cached
  noCacheEndpoints: [
    '/api/user',           // Current user - always fresh
    '/api/login',          // Auth endpoints
    '/api/register-step1',
    '/api/verify-code',
    '/api/logout',
    '/api/messages',       // Messages - need to be fresh
    '/api/unavailable-dates', // Unavailable dates - needs to be fresh for booking
  ],

  // Endpoints that should use aggressive caching
  aggressiveCacheEndpoints: [
    '/api/services',       // Rarely changes
    '/api/admin/services', // Rarely changes
  ],

  // Request deduplication rules
  deduplication: {
    enabled: true,
    timeout: 500, // Deduplicate requests within 500ms
  },

  // Parallel request limits
  parallelRequests: {
    max: 6, // Browser typically allows 6 concurrent connections
    debounceMs: 50, // Group requests made within 50ms
  },

  // Tab-specific loading strategy for admin dashboard
  adminTabLoadingStrategy: {
    dashboard: 'immediate',  // Load immediately with skeleton
    users: 'lazy',          // Load when tab becomes active
    appointments: 'lazy',
    calendar: 'lazy',
    services: 'lazy',
    archive: 'lazy',
    deactivated: 'lazy',
    messages: 'lazy',
    reports: 'lazy',
  },

  // Critical stats to load first (for dashboard)
  criticalStats: [
    'totalUsers',
    'totalAppointments',
    'pendingAppointments',
    'completedAppointments'
  ],

  // Non-critical stats that can load after
  secondaryStats: [
    'appointmentsByStatus',
    'appointmentsByMonth',
    'userGrowth',
    'revenue'
  ]
};

/**
 * Cache key generator
 * Creates consistent cache keys based on endpoint and parameters
 */
export const generateCacheKey = (endpoint, params = {}) => {
  const sortedParams = Object.keys(params)
    .sort()
    .reduce((acc, key) => {
      acc[key] = params[key];
      return acc;
    }, {});

  return `api:${endpoint}:${JSON.stringify(sortedParams)}`;
};

/**
 * Determines if a request should be cached
 */
export const shouldCache = (endpoint) => {
  if (apiConfig.noCacheEndpoints.some(e => endpoint.includes(e))) {
    return false;
  }
  return true;
};

/**
 * Gets cache duration for an endpoint
 */
export const getCacheDuration = (endpoint) => {
  // Check if it's an aggressive cache endpoint
  if (apiConfig.aggressiveCacheEndpoints.some(e => endpoint.includes(e))) {
    return apiConfig.cacheDuration.services;
  }

  // Check for specific cache durations
  for (const [key, duration] of Object.entries(apiConfig.cacheDuration)) {
    if (endpoint.includes(key)) {
      return duration;
    }
  }

  // Default cache duration
  return 60; // 1 minute
};

/**
 * Determines loading strategy for admin tabs
 */
export const getTabLoadingStrategy = (tabKey) => {
  return apiConfig.adminTabLoadingStrategy[tabKey] || 'lazy';
};

