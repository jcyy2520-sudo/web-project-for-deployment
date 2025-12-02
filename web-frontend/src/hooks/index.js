/**
 * Centralized Hooks Export Index
 * All custom hooks available for import via:
 * import { useAuth, useNotification, useDocuments } from '@/hooks'
 */

// Authentication & User State
export { useAuth, useAuthError, useAuthStatus, useAuthActions } from './useAppState';

// Appointment Management
export { useAppointments, useAppointmentForm, useAppointmentStatus } from './useAppointmentState';

// Document Management
export { useDocuments, useDocumentUpload, useDocumentTypes } from './useDocumentState';

// UI State & Components
export {
  useNotification,
  useModal,
  useUIState,
  usePagination,
  useFormState,
} from './useUIState';

/**
 * HOOKS OVERVIEW
 * 
 * Authentication & Authorization:
 * - useAuth() - Full auth context (user, isAuthenticated, methods)
 * - useAuthError() - Error state only
 * - useAuthStatus() - Status checking (lighter weight, no methods)
 * - useAuthActions() - Methods only (no state reading)
 * 
 * Appointment Management:
 * - useAppointments() - Fetch and manage appointment list
 * - useAppointmentForm() - Appointment form state and submission
 * - useAppointmentStatus() - Status configuration and helpers
 * 
 * Document Management:
 * - useDocuments() - Fetch and manage documents
 * - useDocumentUpload() - Handle file uploads with progress
 * - useDocumentTypes() - Document type configuration
 * 
 * UI Management:
 * - useNotification() - Toast notifications (success, error, warning, info)
 * - useModal() - Modal state management (open, close, data passing)
 * - useUIState() - General UI state (loading, sidebar, menu)
 * - usePagination() - Pagination state (page, perPage, navigation)
 * - useFormState() - Generic form state management (values, touched, errors)
 * 
 * MIGRATION GUIDE:
 * 1. Old way (direct context):
 *    import { AuthContext } from '@/context/AuthContext';
 *    const authContext = useContext(AuthContext);
 *    const user = authContext.user;
 * 
 * 2. New way (hooks):
 *    import { useAuth } from '@/hooks';
 *    const { user } = useAuth();
 * 
 * 3. Granular consumption (lighter components):
 *    import { useAuthStatus } from '@/hooks';
 *    const { isAuthenticated } = useAuthStatus(); // No methods, only status
 * 
 * BACKWARD COMPATIBILITY:
 * - All existing code using AuthContext directly still works
 * - Hooks are opt-in, no breaking changes
 * - Can gradually migrate components to hooks
 */
