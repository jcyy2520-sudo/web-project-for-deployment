import React, { createContext, useContext, useState, useEffect, useRef } from 'react';
import axios from 'axios';

const AuthContext = createContext();

// Track whether a logout is already in progress globally to prevent cascading logouts
let isLoggingOutGlobal = false;

// Configure axios defaults based on environment
// In development (Vite): Use proxy (/api routes via vite.config.js)
// In production (Vercel): Use full backend URL from env variable
const getApiBaseUrl = () => {
  // Check if we're in production (Vercel)
  if (import.meta.env.PROD) {
    // Get from environment variable
    const backendUrl = import.meta.env.VITE_API_URL || 'http://localhost:8000';
    return backendUrl;
  }
  // Development: Use proxy (no baseURL needed)
  return null;
};

const apiBaseUrl = getApiBaseUrl();
if (apiBaseUrl) {
  axios.defaults.baseURL = apiBaseUrl;
}

// Initialize Authorization header from localStorage if token exists
// This ensures that the first requests (like /api/user mapping) have the token
const storedToken = localStorage.getItem('token');
if (storedToken) {
  axios.defaults.headers.common['Authorization'] = `Bearer ${storedToken}`;
}

axios.defaults.withCredentials = true;
axios.defaults.timeout = 60000; // Increased to 60 seconds for slower API/LLM requests

export const useAuth = () => {
  const context = useContext(AuthContext);
  if (!context) {
    throw new Error('useAuth must be used within an AuthProvider');
  }
  return context;
};

export const AuthProvider = ({ children }) => {
  const [user, setUser] = useState(() => {
    const storedUser = localStorage.getItem('user');
    return storedUser ? JSON.parse(storedUser) : null;
  });
  const [loading, setLoading] = useState(true);
  const [connectionError, setConnectionError] = useState(false);
  const logoutInProgressRef = useRef(false);

  // Global 401 interceptor: auto-logout on authentication failures
  // This prevents cascading 401 errors from flooding the server
  useEffect(() => {
    const interceptorId = axios.interceptors.response.use(
      (response) => response,
      (error) => {
        if (
          error.response?.status === 401 &&
          !isLoggingOutGlobal &&
          // Don't intercept login/csrf requests
          !error.config?.url?.includes('/login') &&
          !error.config?.url?.includes('/sanctum/csrf-cookie') &&
          // Don't intercept the logout call itself
          !error.config?.url?.includes('/logout')
        ) {
          console.warn('Global 401 interceptor: session expired, clearing auth state');
          // Silently clear auth
          isLoggingOutGlobal = true;
          
          // Dispatch a global event so other components can react (e.g. show a toast)
          window.dispatchEvent(new CustomEvent('auth:expired'));
          
          localStorage.removeItem('user');
          setUser(null);
          // Allow future logouts after a brief delay
          setTimeout(() => { isLoggingOutGlobal = false; }, 2000);
        }
        return Promise.reject(error);
      }
    );
    return () => axios.interceptors.response.eject(interceptorId);
  }, []);

  // Initialize auth state - prevent optimistic rendering of protected routes based on localStorage
  useEffect(() => {
    const initializeAuth = async () => {
      try {
        const storedUser = localStorage.getItem('user');
        const token = localStorage.getItem('token');

        // If a token exists but isn't in headers yet, apply it
        if (token && !axios.defaults.headers.common['Authorization']) {
          axios.defaults.headers.common['Authorization'] = `Bearer ${token}`;
        }

        if (storedUser) {
          try {
            const parsedUser = JSON.parse(storedUser);
            // Hydrate quickly from localStorage so role dashboards can render immediately.
            setUser({ ...parsedUser });
            setLoading(false);

            // Verify session in the background and reconcile user data when available.
            axios.get('/api/user', {
              timeout: 8000
            }).then(response => {
              const freshUserData = response.data.data || response.data;
              setUser(freshUserData);
              localStorage.setItem('user', JSON.stringify(freshUserData));
              setConnectionError(false);
            }).catch(verifyError => {
              if (verifyError.response?.status === 401) {
                console.warn('Session expired or token invalid, logging out...');
                handleLogout();
              } else if (!verifyError.response) {
                // Network failure
                setConnectionError(true);
              }
            });

            return;
            
          } catch (error) {
            console.error('Auth parsing failed:', error);
            handleLogout();
            setLoading(false);
          }
        } else {
          setUser(null);
          setLoading(false);
        }
      } catch (error) {
        console.error('Auth initialization failed:', error);
        handleLogout();
        setLoading(false);
      }
    };

    initializeAuth();
  }, []);

  // Auto-recover connection banner after transient startup/network failures
  useEffect(() => {
    if (!connectionError) return;

    const checkConnection = async () => {
      try {
        await axios.get('/api/health', { timeout: 5000 });
        setConnectionError(false);
      } catch {
        // Keep banner visible until backend becomes reachable
      }
    };

    checkConnection();
    const intervalId = setInterval(checkConnection, 10000);

    return () => clearInterval(intervalId);
  }, [connectionError]);

  const login = async (email, password) => {
    try {
      // Ensure CSRF cookie is set before login
      await axios.get('/sanctum/csrf-cookie');

      const response = await axios.post('/api/login', {
        email,
        password
      });

      // FIXED: Handle response structure from AuthController
      const userData = response.data.user || response.data.data;
      
      if (!userData) {
        return {
          success: false,
          message: 'Invalid response format from server'
        };
      }

      // Store user data in localStorage
      localStorage.setItem('user', JSON.stringify(userData));

      // Update state
      setUser(userData);

      return { success: true, user: userData };
      
    } catch (error) {
      let errorMessage = 'Login failed';
      
      if (error.response?.status === 422) {
        errorMessage = error.response.data?.message || 'Validation failed';
      } else if (error.response?.status === 429) {
        const retryAfter = error.response.headers?.['retry-after'] || error.response.headers?.['Retry-After'] || 60;
        errorMessage = error.response.data?.message || `Too many attempts! Please try again in ${retryAfter} seconds.`;
      } else if (error.response?.status === 401) {
        errorMessage = 'Invalid email or password';
      } else if (error.response?.data?.message) {
        errorMessage = error.response.data.message;
      } else if (error.message === 'Network Error') {
        errorMessage = 'Cannot connect to server. Please check if the backend is running.';
      } else if (error.code === 'ECONNABORTED') {
        errorMessage = 'Request timeout. Server might be down.';
      }
      
      return { 
        success: false, 
        message: errorMessage 
      };
    }
  };

  const handleLogout = () => {
    localStorage.removeItem('user');
    localStorage.removeItem('token');
    delete axios.defaults.headers.common['Authorization'];
    setUser(null);
    
    // Clean up theme customizations applied by Dashboard
    // This ensures the landing page shows its default theme after logout
    const root = document.documentElement;
    root.classList.remove('user-light');
    // Reset any inline CSS variable overrides to let :root defaults apply
    root.style.removeProperty('--primary');
    root.style.removeProperty('--secondary');
    root.style.removeProperty('--accent');
    root.style.removeProperty('--background');
    root.style.removeProperty('--surface');
    root.style.removeProperty('--text-primary');
    root.style.removeProperty('--text-secondary');
    root.style.removeProperty('--borders');
    root.style.removeProperty('--success');
    root.style.removeProperty('--error');
    root.style.removeProperty('background-color');
    root.style.removeProperty('color');
    
    // Restore the theme based on stored preference or default to dark
    const savedTheme = localStorage.getItem('isDarkMode');
    const shouldBeDark = savedTheme !== 'false'; // Default to dark if not explicitly set to false
    
    if (shouldBeDark) {
      root.classList.add('dark');
      root.setAttribute('data-theme', 'dark');
    } else {
      root.classList.remove('dark');
      root.setAttribute('data-theme', 'light');
    }
  };

  const logout = async () => {
    // Prevent concurrent logout calls that cause cascading failures
    if (logoutInProgressRef.current || isLoggingOutGlobal) return;
    logoutInProgressRef.current = true;
    isLoggingOutGlobal = true;

    // Signal all polling/intervals to stop BEFORE clearing auth
    window.dispatchEvent(new CustomEvent('auth:logout'));

    try {
      await axios.post('/api/logout', null, { timeout: 5000 });
    } catch (error) {
      console.error('Logout error:', error);
    } finally {
      handleLogout();
      logoutInProgressRef.current = false;
      // Allow future logins/logouts after a brief delay
      setTimeout(() => { isLoggingOutGlobal = false; }, 1000);
    }
  };

  /**
   * Update the current user data in state and localStorage.
   * Use this after profile updates (e.g., profile picture change) to keep
   * the user object in sync without a full page reload.
   */
  const updateUser = (updatedFields) => {
    setUser((prev) => {
      const merged = { ...prev, ...updatedFields };
      localStorage.setItem('user', JSON.stringify(merged));
      return merged;
    });
  };

  const setAuthData = (userData, token) => {
    if (token) {
      localStorage.setItem('token', token);
      axios.defaults.headers.common['Authorization'] = `Bearer ${token}`;
    }
    if (userData) {
      localStorage.setItem('user', JSON.stringify(userData));
      setUser(userData);
    }
    setLoading(false);
  };

  const value = {
    user,
    login,
    logout,
    updateUser,
    setAuthData,
    loading,
    connectionError,
    setConnectionError,
    isAuthenticated: !!user,
    isAdmin: user?.role === 'admin',
    isStaff: user?.role === 'staff',
    isClient: user?.role === 'client',
  };

  return (
    <AuthContext.Provider value={value}>
      {children}
    </AuthContext.Provider>
  );
};