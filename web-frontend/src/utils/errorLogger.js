import axios from 'axios';

/**
 * Frontend Error Logger Service
 * Captures and logs JavaScript errors to the backend for monitoring
 */
class ErrorLogger {
  constructor() {
    // Use the configured API URL; in production VITE_API_URL must be set via environment
    this.apiUrl = (import.meta.env.VITE_API_URL || '') + '/api';
    this.errorQueue = [];
    this.isOnline = navigator.onLine;
    this.isInitialized = false;

    // Listen for online/offline events
    window.addEventListener('online', () => {
      this.isOnline = true;
      this.flushErrorQueue();
    });

    window.addEventListener('offline', () => {
      this.isOnline = false;
    });
  }

  /**
   * Initialize error tracking by setting up global error handlers
   */
  initialize() {
    // Prevent double initialization
    if (this.isInitialized) return;
    this.isInitialized = true;
    
    // Global error handler for uncaught exceptions
    window.addEventListener('error', (event) => {
      // Ignore ResizeObserver errors (common false positives)
      if (event.message?.includes('ResizeObserver')) return;
      
      this.captureError({
        message: event.message,
        error_type: 'uncaught_exception',
        severity: 'critical',
        stack_trace: event.error?.stack,
        url: window.location.href,
      });
    });

    // Global handler for unhandled promise rejections
    window.addEventListener('unhandledrejection', (event) => {
      // Ignore network errors from polling (expected failures)
      const message = event.reason?.message || String(event.reason);
      if (message.includes('Network Error') || message.includes('timeout')) return;
      
      this.captureError({
        message: message,
        error_type: 'unhandled_promise_rejection',
        severity: 'critical',
        stack_trace: event.reason?.stack,
        url: window.location.href,
      });
    });

    // React error boundary integration (optional)
    this.setupReactErrorBoundary();
  }

  /**
   * Capture and log an error
   */
  captureError(errorData) {
    const payload = {
      message: errorData.message || 'Unknown error',
      error_type: errorData.error_type || 'general',
      severity: errorData.severity || 'warning',
      stack_trace: errorData.stack_trace || null,
      user_agent: navigator.userAgent,
      browser: this.getBrowserInfo(),
      url: errorData.url || window.location.href,
    };

    if (this.isOnline) {
      this.sendError(payload);
    } else {
      this.errorQueue.push(payload);
      this.saveToLocalStorage();
    }
  }

  /**
   * Send error to backend
   */
  async sendError(errorData) {
    try {
      await axios.post(`${this.apiUrl}/frontend-errors/log`, errorData, {
        timeout: 5000,
      });
    } catch (error) {
      console.error('Failed to log error to backend:', error);
      this.errorQueue.push(errorData);
      this.saveToLocalStorage();
    }
  }

  /**
   * Flush queued errors when coming back online
   */
  async flushErrorQueue() {
    while (this.errorQueue.length > 0) {
      const error = this.errorQueue.shift();
      await this.sendError(error);
    }
    localStorage.removeItem('errorQueue');
  }

  /**
   * Save errors to localStorage for persistence
   */
  saveToLocalStorage() {
    try {
      localStorage.setItem('errorQueue', JSON.stringify(this.errorQueue));
    } catch (error) {
      console.warn('Could not save error queue to localStorage');
    }
  }

  /**
   * Load errors from localStorage on initialization
   */
  loadFromLocalStorage() {
    try {
      const stored = localStorage.getItem('errorQueue');
      if (stored) {
        this.errorQueue = JSON.parse(stored);
      }
    } catch (error) {
      console.warn('Could not load error queue from localStorage');
    }
  }

  /**
   * Get browser information
   */
  getBrowserInfo() {
    const ua = navigator.userAgent;

    if (ua.includes('Chrome')) return 'Chrome';
    if (ua.includes('Safari')) return 'Safari';
    if (ua.includes('Firefox')) return 'Firefox';
    if (ua.includes('Edge')) return 'Edge';
    if (ua.includes('Opera') || ua.includes('OPR')) return 'Opera';

    return 'Unknown';
  }

  /**
   * Setup React Error Boundary integration
   */
  setupReactErrorBoundary() {
    // This will be called from your App.jsx error boundary
    window.logReactError = (error, errorInfo) => {
      this.captureError({
        message: error.toString(),
        error_type: 'react_error_boundary',
        severity: 'critical',
        stack_trace: errorInfo?.componentStack || error.stack,
        url: window.location.href,
      });
    };
  }

  /**
   * Manually log an error
   */
  log(message, errorType = 'manual', severity = 'warning', stackTrace = null) {
    this.captureError({
      message,
      error_type: errorType,
      severity,
      stack_trace: stackTrace,
      url: window.location.href,
    });
  }
}

// Create singleton instance
const errorLogger = new ErrorLogger();

export default errorLogger;
