/**
 * useAppState - Centralized state management hook
 * Replaces direct context dependency with a cleaner API
 * Acts as an anti-corruption layer between components and context
 */

import { useContext } from 'react';
import { AuthContext } from '../context/AuthContext';

/**
 * useAuth - Get authentication state and methods
 * 
 * @returns {Object} Authentication state and methods
 * @returns {Object.user} Current user data
 * @returns {Object.isAuthenticated} Whether user is logged in
 * @returns {Object.isLoading} Whether auth is being checked
 * @returns {Object.login} Login function
 * @returns {Object.logout} Logout function
 * @returns {Object.updateUser} Update user profile
 * @returns {Object.error} Current auth error
 * 
 * @example
 * const { user, isAuthenticated, logout } = useAuth();
 * if (isAuthenticated) {
 *   return <div>Welcome {user.name}</div>;
 * }
 */
export function useAuth() {
  const context = useContext(AuthContext);
  
  if (!context) {
    throw new Error('useAuth must be used within AuthProvider');
  }

  const {
    user,
    isAuthenticated,
    isLoading,
    login,
    logout,
    register,
    error,
    clearError,
    updateUser,
  } = context;

  return {
    user,
    isAuthenticated,
    isLoading,
    login,
    logout,
    register,
    error,
    clearError,
    updateUser,
  };
}

/**
 * useAuthError - Get and clear authentication errors
 * 
 * @returns {Object} Error state and methods
 */
export function useAuthError() {
  const { error, clearError } = useAuth();
  
  return {
    error,
    clearError,
    hasError: !!error,
  };
}

/**
 * useAuthStatus - Get authentication status only
 * Lighter weight than useAuth if only checking status
 * 
 * @returns {Object} Authentication status
 */
export function useAuthStatus() {
  const { user, isAuthenticated, isLoading } = useAuth();
  
  return {
    user,
    isAuthenticated,
    isLoading,
    isAdmin: user?.role === 'admin',
    isStaff: user?.role === 'staff',
    isClient: user?.role === 'client',
  };
}

/**
 * useAuthActions - Get only authentication actions
 * Use when you only need to perform actions, not read state
 * 
 * @returns {Object} Authentication methods
 */
export function useAuthActions() {
  const { login, logout, register, updateUser } = useAuth();
  
  return {
    login,
    logout,
    register,
    updateUser,
  };
}
