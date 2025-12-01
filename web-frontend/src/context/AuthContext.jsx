import React, { createContext, useContext, useState, useEffect } from 'react';
import axios from 'axios';

const AuthContext = createContext();

// Configure axios defaults based on environment
// In development (Vite): Use proxy (/api routes via vite.config.js)
// In production (Vercel): Use full backend URL from env variable
const getApiBaseUrl = () => {
  // Check if we're in production (Vercel)
  if (import.meta.env.PROD) {
    // Get from environment variable
    const backendUrl = import.meta.env.VITE_API_URL || 'http://localhost:8000';
    console.log('📡 Using production API URL:', backendUrl);
    return backendUrl;
  }
  // Development: Use proxy (no baseURL needed)
  console.log('📡 Using development proxy configuration');
  return null;
};

const apiBaseUrl = getApiBaseUrl();
if (apiBaseUrl) {
  axios.defaults.baseURL = apiBaseUrl;
}

axios.defaults.withCredentials = true;
axios.defaults.timeout = 15000;

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
  
  // Get token from localStorage on initial load
  const [token, setToken] = useState(() => {
    return localStorage.getItem('token');
  });

  // Set up axios interceptor for auth
  useEffect(() => {
    if (token) {
      axios.defaults.headers.common['Authorization'] = `Bearer ${token}`;
    } else {
      delete axios.defaults.headers.common['Authorization'];
    }
  }, [token]);

  // Initialize auth state
  useEffect(() => {
    const initializeAuth = async () => {
      try {
        const storedToken = localStorage.getItem('token');
        const storedUser = localStorage.getItem('user');

        if (storedToken && storedUser) {
          try {
            // Set token FIRST before making any API calls
            setToken(storedToken);
            axios.defaults.headers.common['Authorization'] = `Bearer ${storedToken}`;
            
            const parsedUser = JSON.parse(storedUser);
            setUser(parsedUser);
            
            // Verify token is still valid by trying to fetch user
            try {
              const response = await axios.get('/api/user', {
                headers: {
                  'Authorization': `Bearer ${storedToken}`
                }
              });
              const freshUserData = response.data.data || response.data;
              
              // Update with fresh data
              setUser(freshUserData);
              localStorage.setItem('user', JSON.stringify(freshUserData));
            } catch (verifyError) {
              console.warn('Token validation failed, but keeping user logged in:', verifyError.message);
              // Keep the user logged in even if token verification fails
              // This can happen if the API endpoint doesn't exist
            }
            
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

  const login = async (email, password) => {
    try {
      console.log('🔐 Starting login process...');
      
      // Ensure CSRF cookie is set before login
      console.log('🛡️ Getting CSRF token...');
      await axios.get('/sanctum/csrf-cookie');
      
      console.log('📤 Sending login request...');
      const response = await axios.post('/api/login', { 
        email, 
        password 
      });
      
      console.log('✅ Login response received:', response.data);
      
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
        console.error('❌ Missing user data or token in response:', response.data);
        return { 
          success: false, 
          message: 'Invalid response format from server' 
        };
      }
      
      console.log('💾 Storing auth data...');
      // Store both token and user data in localStorage
      localStorage.setItem('token', authToken);
      localStorage.setItem('user', JSON.stringify(userData));
      
      // Update state
      setToken(authToken);
      setUser(userData);
      
      console.log('🎉 Login successful! User:', userData);
      return { success: true, user: userData };
      
    } catch (error) {
      console.error('❌ Login failed:', {
        status: error.response?.status,
        data: error.response?.data,
        message: error.message
      });
      
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
  };

  const logout = async () => {
    try {
      await axios.post('/api/logout');
    } catch (error) {
      console.error('Logout error:', error);
    } finally {
      handleLogout();
    }
  };

  const value = {
    user,
    login,
    logout,
    loading,
    isAuthenticated: !!user && !!token,
    isAdmin: user?.role === 'admin',
    isStaff: false,
    isClient: user?.role === 'client',
  };

  return (
    <AuthContext.Provider value={value}>
      {children}
    </AuthContext.Provider>
  );
};