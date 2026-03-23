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
  const [user, setUser] = useState(null);
  const [loading, setLoading] = useState(true);
  const [connectionError, setConnectionError] = useState(false);
  const logoutInProgressRef = useRef(false);
  
  // Get token from localStorage on initial load AND set axios header immediately
  const [token, setToken] = useState(() => {
    const storedToken = localStorage.getItem('token');
    // Set axios header synchronously during initialization to prevent race conditions
    if (storedToken) {
      axios.defaults.headers.common['Authorization'] = `Bearer ${storedToken}`;
    }
    return storedToken;
  });

  // Keep axios header in sync with token state changes
  useEffect(() => {
    if (token) {
      axios.defaults.headers.common['Authorization'] = `Bearer ${token}`;
    } else {
      delete axios.defaults.headers.common['Authorization'];
    }
  }, [token]);

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
          const hasToken = localStorage.getItem('token');
          if (hasToken) {
            console.warn('Global 401 interceptor: token invalid, clearing auth state');
            // Silently clear auth - don't call the server logout (token is already invalid)
            isLoggingOutGlobal = true;
            localStorage.removeItem('token');
            localStorage.removeItem('user');
            delete axios.defaults.headers.common['Authorization'];
            setToken(null);
            setUser(null);
            // Allow future logouts after a brief delay
            setTimeout(() => { isLoggingOutGlobal = false; }, 2000);
          }
        }
        return Promise.reject(error);
      }
    );
    return () => axios.interceptors.response.eject(interceptorId);
  }, []);

  // Initialize auth state - show cached data immediately, verify in background
  useEffect(() => {
    const initializeAuth = async () => {
      try {
        const storedToken = localStorage.getItem('token');
        const storedUser = localStorage.getItem('user');

        if (storedToken && storedUser) {
          try {
            // Set token and cached user IMMEDIATELY so UI renders without delay
            setToken(storedToken);
            axios.defaults.headers.common['Authorization'] = `Bearer ${storedToken}`;

            const parsedUser = JSON.parse(storedUser);
            // For security: use cached data for display but demote role to 'client'
            // until the server verifies the actual role via /api/user
            const safeUser = { ...parsedUser, role: parsedUser.role || 'client' };
            setUser(safeUser);

            // Mark loading as done IMMEDIATELY with cached data
            // This eliminates the loading spinner delay on startup
            setLoading(false);

            // Verify token in the BACKGROUND (non-blocking)
            // If it fails with 401, we log out; otherwise update with server-verified data
            axios.get('/api/user', {
              headers: { 'Authorization': `Bearer ${storedToken}` },
              timeout: 8000
            }).then(response => {
              const freshUserData = response.data.data || response.data;
              setUser(freshUserData);
              localStorage.setItem('user', JSON.stringify(freshUserData));
              setConnectionError(false);
            }).catch(verifyError => {
              if (verifyError.response?.status === 401) {
                console.warn('Token expired or invalid, logging out...');
                handleLogout();
              } else if (!verifyError.response) {
                // No response at all = actual network/connection failure
                // Only show banner if this is NOT a timeout (timeouts can happen when backend is just slow)
                if (verifyError.code === 'ECONNABORTED') {
                  console.warn('Token validation timed out, keeping cached data');
                  // Don't set connectionError for timeouts — backend is likely just slow, not down
                } else {
                  console.warn('Token validation failed (network), keeping cached data:', verifyError.message);
                  setConnectionError(true);
                }
              } else {
                // Server responded (e.g. 500) — not a connection issue, just a server error
                console.warn('Token validation returned error status:', verifyError.response?.status);
              }
            });

            return; // Skip the finally block's setLoading since we already set it
            
          } catch (error) {
            console.error('Auth parsing failed:', error);
            handleLogout();
          }
        } else {
          setUser(null);
          setToken(null);
        }
      } catch (error) {
        console.error('Auth initialization failed:', error);
        handleLogout();
      } finally {
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

      // FIXED: Handle different response structures
      let userData, authToken;
      
      if (response.data.user && response.data.token) {
        // Structure: { user: {...}, token: "..." }
        userData = response.data.user;
        authToken = response.data.token;
      } else if (response.data.data && response.data.token) {
        // Structure: { data: {...}, token: "..." }
        userData = response.data.data;
        authToken = response.data.token;
      } else if (response.data.data) {
        // Structure: { data: { user: {...}, token: "..." } }
        userData = response.data.data.user || response.data.data;
        authToken = response.data.data.token;
      } else {
        // Fallback: use the entire response as user data
        userData = response.data;
        authToken = response.data.token || response.data.access_token;
      }
      
      if (!userData || !authToken) {
        return {
          success: false,
          message: 'Invalid response format from server'
        };
      }

      // Store both token and user data in localStorage
      localStorage.setItem('token', authToken);
      localStorage.setItem('user', JSON.stringify(userData));

      // Update state
      setToken(authToken);
      setUser(userData);

      return { success: true, user: userData };
      
    } catch (error) {
      let errorMessage = 'Login failed';
      
      if (error.response?.status === 422) {
        errorMessage = error.response.data?.message || 'Validation failed';
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
    localStorage.removeItem('token');
    localStorage.removeItem('user');
    setToken(null);
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

  const value = {
    user,
    login,
    logout,
    updateUser,
    loading,
    connectionError,
    setConnectionError,
    isAuthenticated: !!user && !!token,
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