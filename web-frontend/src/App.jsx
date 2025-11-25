import { BrowserRouter as Router, Routes, Route, Navigate } from 'react-router-dom';
import { lazy, Suspense, useState, useEffect } from 'react';
import { AuthProvider, useAuth } from './context/AuthContext';
import LoadingSpinner from './components/ui/LoadingSpinner';
import ErrorBoundary from './components/ErrorBoundary';
import ToastContainer from './components/notifications/ToastContainer';
import './css/animations.css';
import { debugApiConfig } from './utils/debugApi';

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

  // Removed unnecessary 100ms delay - it was causing perceived slowness
  // Direct render when auth is ready provides better UX
  if (loading) {
    return <PageLoading />;
  }

  return (
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
  );
};

function App() {
  return (
    <AuthProvider>
      <Router>
        <div className="min-h-screen bg-gray-900">
          <ToastContainer isDarkMode={true} />
          <AppContent />
        </div>
      </Router>
    </AuthProvider>
  );
}

export default App;