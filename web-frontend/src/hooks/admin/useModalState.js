import { useState, useCallback } from 'react';

/**
 * Hook to manage modal open/close states
 */
export const useModalState = (initialValue = false) => {
  const [isOpen, setIsOpen] = useState(initialValue);
  
  const open = useCallback(() => setIsOpen(true), []);
  const close = useCallback(() => setIsOpen(false), []);
  const toggle = useCallback(() => setIsOpen(prev => !prev), []);
  
  return { isOpen, open, close, toggle, setIsOpen };
};

/**
 * Hook to manage all admin dashboard modals
 */
export const useAdminModals = () => {
  const userModal = useModalState();
  const adminModal = useModalState();
  const unavailableModal = useModalState();
  const deleteModal = useModalState();
  const userDetailModal = useModalState();
  const reportModal = useModalState();
  const messageModal = useModalState();
  const logoutModal = useModalState();
  const declineModal = useModalState();

  return {
    userModal,
    adminModal,
    unavailableModal,
    deleteModal,
    userDetailModal,
    reportModal,
    messageModal,
    logoutModal,
    declineModal
  };
};

export default useModalState;
