import React from 'react';
import errorLogger from '../utils/errorLogger';

class ErrorBoundary extends React.Component {
  constructor(props) {
    super(props);
    this.state = { hasError: false, error: null, errorInfo: null };
  }

  static getDerivedStateFromError(error) {
    return { hasError: true, error };
  }

  componentDidCatch(error, errorInfo) {
    this.setState({ errorInfo });
    console.error('Error caught by boundary:', error, errorInfo);
    
    // Log error to backend
    errorLogger.captureError({
      message: error.toString(),
      error_type: 'react_error_boundary',
      severity: 'critical',
      stack_trace: errorInfo?.componentStack || error.stack,
      url: window.location.href,
    });
  }

  render() {
    if (this.state.hasError) {
      return (
        <div className="min-h-screen bg-white dark:bg-gray-900 flex items-center justify-center px-4" role="alert" aria-live="assertive">
          <div className="text-center max-w-md">
            <div className="w-16 h-16 bg-red-500 rounded-full flex items-center justify-center mx-auto mb-4">
              <span className="text-white text-2xl" aria-hidden="true">!</span>
            </div>
            <h2 className="text-xl font-bold text-gray-900 dark:text-amber-50 mb-2">Something went wrong</h2>
            <p className="text-gray-600 dark:text-gray-400 mb-2">We're sorry, but an unexpected error occurred.</p>
            <p className="text-gray-500 dark:text-gray-500 text-sm mb-6">Try reloading the page. If the problem persists, please contact support.</p>
            <div className="flex flex-col sm:flex-row gap-3 justify-center">
              <button
                onClick={() => window.location.reload()}
                className="px-6 py-2.5 bg-amber-600 text-white rounded-lg hover:bg-amber-700 transition-colors focus:outline-none focus:ring-2 focus:ring-amber-500 focus:ring-offset-2 font-medium"
              >
                Reload Page
              </button>
              <button
                onClick={() => { window.history.back(); }}
                className="px-6 py-2.5 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors focus:outline-none focus:ring-2 focus:ring-amber-500 focus:ring-offset-2 font-medium"
              >
                Go Back
              </button>
            </div>
          </div>
        </div>
      );
    }

    return this.props.children;
  }
}

export default ErrorBoundary;