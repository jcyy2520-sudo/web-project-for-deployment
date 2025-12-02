/**
 * UI State Management Hooks
 * Centralized hooks for UI-related operations (notifications, modals, UI state)
 */

import { useState, useCallback } from 'react';

/**
 * useNotification - Manage toast notifications
 * 
 * @returns {Object} Notification state and methods
 */
export function useNotification() {
  const [notifications, setNotifications] = useState([]);

  const addNotification = useCallback((message, type = 'info', duration = 5000) => {
    const id = Date.now();
    const notification = { id, message, type, duration };

    setNotifications(prev => [...prev, notification]);

    if (duration > 0) {
      const timer = setTimeout(() => {
        removeNotification(id);
      }, duration);

      return () => clearTimeout(timer);
    }

    return () => removeNotification(id);
  }, []);

  const removeNotification = useCallback((id) => {
    setNotifications(prev => prev.filter(notif => notif.id !== id));
  }, []);

  const success = useCallback((message, duration = 5000) => {
    return addNotification(message, 'success', duration);
  }, [addNotification]);

  const error = useCallback((message, duration = 7000) => {
    return addNotification(message, 'error', duration);
  }, [addNotification]);

  const warning = useCallback((message, duration = 5000) => {
    return addNotification(message, 'warning', duration);
  }, [addNotification]);

  const info = useCallback((message, duration = 5000) => {
    return addNotification(message, 'info', duration);
  }, [addNotification]);

  return {
    notifications,
    addNotification,
    removeNotification,
    success,
    error,
    warning,
    info,
  };
}

/**
 * useModal - Manage modal dialogs
 * 
 * @returns {Object} Modal state and methods
 */
export function useModal() {
  const [modals, setModals] = useState({});

  const openModal = useCallback((modalId, data = null) => {
    setModals(prev => ({
      ...prev,
      [modalId]: {
        isOpen: true,
        data,
      }
    }));
  }, []);

  const closeModal = useCallback((modalId) => {
    setModals(prev => ({
      ...prev,
      [modalId]: {
        ...prev[modalId],
        isOpen: false,
      }
    }));
  }, []);

  const isOpen = useCallback((modalId) => {
    return modals[modalId]?.isOpen || false;
  }, [modals]);

  const getData = useCallback((modalId) => {
    return modals[modalId]?.data || null;
  }, [modals]);

  const toggle = useCallback((modalId, data = null) => {
    if (isOpen(modalId)) {
      closeModal(modalId);
    } else {
      openModal(modalId, data);
    }
  }, [isOpen, closeModal, openModal]);

  return {
    modals,
    openModal,
    closeModal,
    isOpen,
    getData,
    toggle,
  };
}

/**
 * useUIState - Manage general UI state (loading, error, sidebar visibility, etc.)
 * 
 * @returns {Object} UI state and methods
 */
export function useUIState() {
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);
  const [sidebarOpen, setSidebarOpen] = useState(true);
  const [menuOpen, setMenuOpen] = useState(false);

  const toggleSidebar = useCallback(() => {
    setSidebarOpen(prev => !prev);
  }, []);

  const toggleMenu = useCallback(() => {
    setMenuOpen(prev => !prev);
  }, []);

  const clearError = useCallback(() => {
    setError(null);
  }, []);

  return {
    loading,
    setLoading,
    error,
    setError,
    clearError,
    sidebarOpen,
    setSidebarOpen,
    toggleSidebar,
    menuOpen,
    setMenuOpen,
    toggleMenu,
  };
}

/**
 * usePagination - Manage pagination state
 * 
 * @param {number} initialPage Starting page
 * @param {number} initialPerPage Items per page
 * @returns {Object} Pagination state and methods
 */
export function usePagination(initialPage = 1, initialPerPage = 15) {
  const [page, setPage] = useState(initialPage);
  const [perPage, setPerPage] = useState(initialPerPage);
  const [total, setTotal] = useState(0);

  const totalPages = Math.ceil(total / perPage);

  const goToPage = useCallback((newPage) => {
    if (newPage >= 1 && newPage <= totalPages) {
      setPage(newPage);
    }
  }, [totalPages]);

  const nextPage = useCallback(() => {
    goToPage(page + 1);
  }, [page, goToPage]);

  const prevPage = useCallback(() => {
    goToPage(page - 1);
  }, [page, goToPage]);

  const updatePerPage = useCallback((newPerPage) => {
    setPerPage(newPerPage);
    setPage(1);
  }, []);

  return {
    page,
    perPage,
    total,
    setTotal,
    totalPages,
    goToPage,
    nextPage,
    prevPage,
    updatePerPage,
  };
}

/**
 * useFormState - Manage generic form state
 * 
 * @param {Object} initialState Initial form values
 * @returns {Object} Form state and methods
 */
export function useFormState(initialState = {}) {
  const [values, setValues] = useState(initialState);
  const [touched, setTouched] = useState({});
  const [errors, setErrors] = useState({});

  const handleChange = useCallback((e) => {
    const { name, value, type, checked } = e.target;
    setValues(prev => ({
      ...prev,
      [name]: type === 'checkbox' ? checked : value,
    }));
  }, []);

  const handleBlur = useCallback((e) => {
    const { name } = e.target;
    setTouched(prev => ({
      ...prev,
      [name]: true,
    }));
  }, []);

  const setFieldValue = useCallback((name, value) => {
    setValues(prev => ({
      ...prev,
      [name]: value,
    }));
  }, []);

  const setFieldError = useCallback((name, error) => {
    setErrors(prev => ({
      ...prev,
      [name]: error,
    }));
  }, []);

  const reset = useCallback(() => {
    setValues(initialState);
    setTouched({});
    setErrors({});
  }, [initialState]);

  const hasError = useCallback((fieldName) => {
    return touched[fieldName] && errors[fieldName];
  }, [touched, errors]);

  return {
    values,
    touched,
    errors,
    handleChange,
    handleBlur,
    setFieldValue,
    setFieldError,
    reset,
    hasError,
    setValues,
    setTouched,
    setErrors,
  };
}
