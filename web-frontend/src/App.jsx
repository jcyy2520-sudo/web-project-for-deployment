import { BrowserRouter as Router, Routes, Route, Navigate } from 'react-router-dom';
import { lazy, Suspense, useState, useEffect } from 'react';
import { AuthProvider, useAuth } from './context/AuthContext';
import LoadingSpinner from './components/ui/LoadingSpinner';
import ErrorBoundary from './components/ErrorBoundary';
import ToastContainer from './components/notifications/ToastContainer';
import ChatbotButton from './components/chatbot/ChatbotButton';
import InstallPrompt from './components/InstallPrompt';
import ConnectionTest from './components/ConnectionTest';
import './css/animations.css';
import { debugApiConfig } from './utils/debugApi';
import errorLogger from './utils/errorLogger';

// Initialize error logging
errorLogger.initialize();
errorLogger.loadFromLocalStorage();

// Debug API configuration on app load (development only)
if (import.meta.env.DEV) {
  debugApiConfig();
}

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

// Loading component for Suspense fallback
const PageLoading = () => (
  <div className="min-h-screen bg-gray-900 flex items-center justify-center">
    <div className="text-center">
      <LoadingSpinner size="lg" />
      <p className="mt-4 text-amber-100 text-sm">Loading...</p>
    </div>
  </div>
);

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
  const { isAuthenticated, loading } = useAuth();

  if (loading) {
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

// AppContent component to prevent layout shifts
const AppContent = () => {
  const { loading } = useAuth();
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
      <Routes>
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
          <ProtectedRoute allowedRoles={['staff', 'admin']}>
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
        path="/messages"
        element={
          <ProtectedRoute>
            <Suspense fallback={<PageLoading />}>
              <MessageCenter />
            </Suspense>
          </ProtectedRoute>
        } 
      />
      <Route path="*" element={<Navigate to="/" replace />} />
    </Routes>
      <button 
        onClick={() => setShowTest(true)}
        style={{
          position: 'fixed',
          bottom: '20px',
          right: '20px',
          padding: '8px 16px',
          background: '#2196F3',
          color: 'white',
          border: 'none',
          borderRadius: '4px',
          cursor: 'pointer',
          fontSize: '12px'
        }}
      >
        Show Connection Test
      </button>
    </>
  );
};

function App() {
  return (
    <AuthProvider>
      <Router>
        <div className="min-h-screen bg-gray-900">
          <ToastContainer isDarkMode={true} />
          <AppContent />
          {/* Chatbot enabled after fixes to nullable userId and endpoint routing */}
          <ChatbotButton />
          <InstallPrompt />
        </div>
      </Router>
    </AuthProvider>
  );
}

export default App;