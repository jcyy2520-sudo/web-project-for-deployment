import { BrowserRouter as Router, Routes, Route, Navigate, useSearchParams } from 'react-router-dom';
import { lazy, Suspense, useState, useEffect } from 'react';
import { AuthProvider, useAuth } from './context/AuthContext';
import { ThemeProvider, useTheme } from './context/ThemeContext';
import LoadingSpinner from './components/ui/LoadingSpinner';
import ErrorBoundary from './components/ErrorBoundary';
import ToastContainer from './components/notifications/ToastContainer';
import ChatbotButton from './components/chatbot/ChatbotButton';
import InstallPrompt from './components/InstallPrompt';
import ConnectionTest from './components/ConnectionTest';
import './css/animations.css';
import errorLogger from './utils/errorLogger';

// Initialize error logging
errorLogger.initialize();
errorLogger.loadFromLocalStorage();

// Debug API configuration available via console: window.debugApiConfig()
// (loaded on-demand via debugApi.js; no import needed here)

// Lazy load components
const LandingPage = lazy(() => import('./pages/LandingPage'));
const Dashboard = lazy(() => import('./pages/Dashboard'));
const ClientAppointments = lazy(() => import('./pages/ClientAppointments'));
const StaffAppointments = lazy(() => import('./pages/StaffAppointments'));
const AdminDashboard = lazy(() => import('./pages/AdminDashboard'));
const CashierDashboard = lazy(() => import('./pages/CashierDashboard'));
const UserManagement = lazy(() => import('./pages/UserManagement'));
const CalendarManagement = lazy(() => import('./pages/CalendarManagement'));
const MessageCenter = lazy(() => import('./pages/MessageCenter'));
const AppealPage = lazy(() => import('./pages/AppealPage'));
const LandingPageCMS = lazy(() => import('./pages/LandingPageCMS'));

const AuthCallback = lazy(() => import('./pages/AuthCallback'));

// Loading component for Suspense fallback - theme-aware
const PageLoading = () => {
  // Read theme preference from localStorage to avoid flash of wrong theme
  const isDark = localStorage.getItem('isDarkMode') !== 'false';
  
  return (
    <div className={`min-h-screen flex items-center justify-center transition-colors ${
      isDark ? 'bg-gray-900' : 'bg-gray-50'
    }`}>
      <div className="text-center">
        <LoadingSpinner size="lg" />
        <p className={`mt-4 text-sm ${isDark ? 'text-amber-100' : 'text-gray-600'}`}>Loading...</p>
      </div>
    </div>
  );
};

// ProtectedRoute component
const ProtectedRoute = ({ children, allowedRoles = [] }) => {
  const { isAuthenticated, user, loading } = useAuth();

  if (loading) {
    return <PageLoading />;
  }

  if (!isAuthenticated) {
    return <Navigate to="/" replace />;
  }

  if (allowedRoles.length > 0 && !allowedRoles.includes(user?.role)) {
    return <Navigate to="/dashboard" replace />;
  }

  return (
    <ErrorBoundary>
      {children}
    </ErrorBoundary>
  );
};

// PublicRoute component
const PublicRoute = ({ children }) => {
  const { isAuthenticated, logout, loading } = useAuth();
  const [searchParams, setSearchParams] = useSearchParams();
  const [forceLogoutDone, setForceLogoutDone] = useState(false);

  useEffect(() => {
    // If force_logout param is present (from reactivation/unblock emails), clear auth
    if (searchParams.get('force_logout') === 'true') {
      localStorage.removeItem('token');
      localStorage.removeItem('user');
      // Remove the query param from URL so it doesn't persist
      searchParams.delete('force_logout');
      setSearchParams(searchParams, { replace: true });
      // Force a full reload to reset AuthContext state
      window.location.replace(window.location.pathname);
      return;
    }
    setForceLogoutDone(true);
  }, []);

  if (loading || !forceLogoutDone) {
    return <PageLoading />;
  }

  return !isAuthenticated ? (
    <ErrorBoundary>
      {children}
    </ErrorBoundary>
  ) : (
    <Navigate to="/dashboard" replace />
  );
};

// Connection error banner component
const ConnectionBanner = () => {
  const [dismissed, setDismissed] = useState(false);
  const [retrying, setRetrying] = useState(false);
  
  const handleRetry = async () => {
    setRetrying(true);
    try {
      await fetch('/api/health', { method: 'GET', headers: { 'Accept': 'application/json' } });
      // If successful, reload the page to re-initialize
      window.location.reload();
    } catch {
      setRetrying(false);
    }
  };
  
  if (dismissed) return null;
  
  return (
    <div role="alert" className="fixed top-0 left-0 right-0 z-50 bg-red-600 text-white px-4 py-3 shadow-lg flex items-center justify-between">
      <div className="flex items-center gap-2">
        <svg className="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z" />
        </svg>
        <span className="text-sm font-medium">
          Cannot connect to the server. Some features may not work. Please ensure the backend is running.
        </span>
      </div>
      <div className="flex items-center gap-2 flex-shrink-0">
        <button 
          onClick={handleRetry}
          disabled={retrying}
          className="px-3 py-1 text-xs bg-white/20 hover:bg-white/30 rounded transition-colors disabled:opacity-50"
        >
          {retrying ? 'Retrying...' : 'Retry'}
        </button>
        <button 
          onClick={() => setDismissed(true)}
          className="px-2 py-1 text-xs hover:bg-white/20 rounded transition-colors"
        >
          ✕
        </button>
      </div>
    </div>
  );
};

// AppContent component to prevent layout shifts
const AppContent = () => {
  const { loading, connectionError } = useAuth();
  const [showTest, setShowTest] = useState(false); // Hidden by default

  // Removed unnecessary 100ms delay - it was causing perceived slowness
  // Direct render when auth is ready provides better UX
  if (loading) {
    return <PageLoading />;
  }

  if (showTest) {
    return (
      <div>
        <ConnectionTest />
        <button 
          onClick={() => setShowTest(false)}
          style={{
            marginTop: '20px',
            padding: '10px 20px',
            background: '#2196F3',
            color: 'white',
            border: 'none',
            borderRadius: '4px',
            cursor: 'pointer',
            display: 'block',
            margin: '20px auto'
          }}
        >
          Hide Test & Continue to App
        </button>
      </div>
    );
  }

  return (
    <>
      {/* Skip to content link for keyboard/screen reader users */}
      <a href="#main-content" className="sr-only focus:not-sr-only focus:absolute focus:top-2 focus:left-2 focus:z-[100] focus:bg-white focus:text-black focus:px-4 focus:py-2 focus:rounded focus:shadow-lg">
        Skip to main content
      </a>
      {connectionError && <ConnectionBanner />}
      <main id="main-content">
      <Routes>
      <Route
        path="/auth/callback"
        element={
          <Suspense fallback={<PageLoading />}>
            <AuthCallback />
          </Suspense>
        }
      />
      <Route
        path="/"
        element={
          <PublicRoute>
            <Suspense fallback={<PageLoading />}>
              <LandingPage />
            </Suspense>
          </PublicRoute>
        }
      />
      <Route
        path="/dashboard"
        element={
          <ProtectedRoute>
            <Suspense fallback={<PageLoading />}>
              <Dashboard />
            </Suspense>
          </ProtectedRoute>
        }
      />
      <Route
        path="/appointments"
        element={
          <ProtectedRoute allowedRoles={['client']}>
            <Suspense fallback={<PageLoading />}>
              <ClientAppointments />
            </Suspense>
          </ProtectedRoute>
        }
      />
      <Route
        path="/staff/appointments"
        element={
          <ProtectedRoute allowedRoles={['staff', 'admin']}>
            <Suspense fallback={<PageLoading />}>
              <StaffAppointments />
            </Suspense>
          </ProtectedRoute>
        }
      />
      <Route
        path="/admin/dashboard"
        element={
          <ProtectedRoute allowedRoles={['admin']}>
            <Suspense fallback={<PageLoading />}>
              <AdminDashboard />
            </Suspense>
          </ProtectedRoute>
        }
      />
      <Route
        path="/admin"
        element={
          <ProtectedRoute allowedRoles={['admin']}>
            <Suspense fallback={<PageLoading />}>
              <AdminDashboard />
            </Suspense>
          </ProtectedRoute>
        }
      />
      <Route
        path="/cashier"
        element={
          <ProtectedRoute allowedRoles={['cashier', 'staff', 'admin']}>
            <Suspense fallback={<PageLoading />}>
              <CashierDashboard />
            </Suspense>
          </ProtectedRoute>
        }
      />
      <Route
        path="/admin/users"
        element={
          <ProtectedRoute allowedRoles={['admin']}>
            <Suspense fallback={<PageLoading />}>
              <UserManagement />
            </Suspense>
          </ProtectedRoute>
        }
      />
      <Route
        path="/admin/calendar"
        element={
          <ProtectedRoute allowedRoles={['admin', 'staff']}>
            <Suspense fallback={<PageLoading />}>
              <CalendarManagement />
            </Suspense>
          </ProtectedRoute>
        }
      />
      <Route
        path="/admin/cms"
        element={
          <ProtectedRoute allowedRoles={['admin']}>
            <Suspense fallback={<PageLoading />}>
              <LandingPageCMS />
            </Suspense>
          </ProtectedRoute>
        }
      />
      <Route
        path="/messages"
        element={
          <ProtectedRoute>
            <Suspense fallback={<PageLoading />}>
              <MessageCenter />
            </Suspense>
          </ProtectedRoute>
        } 
      />
      <Route
        path="/appeal/:token"
        element={
          <Suspense fallback={<PageLoading />}>
            <AppealPage />
          </Suspense>
        }
      />
      <Route path="*" element={<Navigate to="/" replace />} />
    </Routes>
    </main>
    </>
  );
};

function App() {
  return (
    <ThemeProvider>
      <AuthProvider>
        <Router>
          <AppWrapper />
        </Router>
      </AuthProvider>
    </ThemeProvider>
  );
}

function AppWrapper() {
  const { isDarkMode } = useTheme();
  
  return (
    <div 
      className={`min-h-screen transition-colors duration-300 ${
        isDarkMode 
          ? 'bg-gray-900' 
          : 'bg-[var(--background)]'
      }`}
      style={!isDarkMode ? { backgroundColor: 'var(--background, #F8FAFC)' } : {}}
    >
      <ToastContainer isDarkMode={isDarkMode} />
      <AppContent />
      {/* Chatbot enabled after fixes to nullable userId and endpoint routing */}
      <ChatbotButton isDarkMode={isDarkMode} />
      <InstallPrompt />
    </div>
  );
}

export default App;