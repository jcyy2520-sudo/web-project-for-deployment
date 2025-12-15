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
        <div className="min-h-screen bg-gray-900 flex items-center justify-center">
          <div className="text-center">
            <div className="w-16 h-16 bg-red-500 rounded-full flex items-center justify-center mx-auto mb-4">
              <span className="text-white text-2xl">!</span>
            </div>
            <h2 className="text-xl font-bold text-amber-50 mb-2">Something went wrong</h2>
            <p className="text-gray-400 mb-4">We're sorry, but something went wrong.</p>
            <button
              onClick={() => window.location.reload()}
              className="px-4 py-2 bg-amber-600 text-white rounded hover:bg-amber-700 transition-colors"
            >
              Reload Page
            </button>
          </div>
        </div>
      );
    }

    return this.props.children;
  }
}

export default ErrorBoundary;