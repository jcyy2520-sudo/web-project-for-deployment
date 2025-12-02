/**
 * Example: Login Modal - Before and After Hook Migration
 * 
 * This file demonstrates how to migrate from direct AuthContext usage
 * to the new custom hooks. Both implementations work identically,
 * but the hooks approach is cleaner and more maintainable.
 */

import { useState } from 'react'
import axios from 'axios'

// ============================================================================
// BEFORE: Using AuthContext Directly (Legacy Pattern)
// ============================================================================

import { useContext } from 'react'
import { AuthContext } from '@/context/AuthContext'

/**
 * OLD IMPLEMENTATION - Using useContext directly
 * This still works but couples components to the Context implementation
 */
export function LoginModalOLD({ isOpen, onClose, onSuccess }) {
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [isLoading, setIsLoading] = useState(false)

  // Direct context usage - tight coupling
  const authContext = useContext(AuthContext)
  const { user, error, login } = authContext

  const handleSubmit = async (e) => {
    e.preventDefault()
    setIsLoading(true)

    try {
      await login(email, password)
      setEmail('')
      setPassword('')
      onSuccess?.()
    } catch (err) {
      console.error('Login failed:', err)
    } finally {
      setIsLoading(false)
    }
  }

  if (!isOpen) return null

  return (
    <div className="modal">
      <div className="modal-content">
        <h2>Login</h2>

        {error && (
          <div className="alert alert-error">{error}</div>
        )}

        <form onSubmit={handleSubmit}>
          <input
            type="email"
            placeholder="Email"
            value={email}
            onChange={(e) => setEmail(e.target.value)}
            disabled={isLoading}
          />

          <input
            type="password"
            placeholder="Password"
            value={password}
            onChange={(e) => setPassword(e.target.value)}
            disabled={isLoading}
          />

          <button type="submit" disabled={isLoading}>
            {isLoading ? 'Logging in...' : 'Login'}
          </button>

          <button type="button" onClick={onClose}>
            Cancel
          </button>
        </form>
      </div>
    </div>
  )
}

// ============================================================================
// AFTER: Using Custom Hooks (New Pattern - RECOMMENDED)
// ============================================================================

import { useAuth } from '@/hooks'
import { useNotification } from '@/hooks'

/**
 * NEW IMPLEMENTATION - Using custom hooks
 * Cleaner, decoupled from Context implementation details
 * 
 * Benefits:
 * - No direct Context coupling
 * - Easier to test (can mock hooks instead of Context)
 * - Better IDE support and autocomplete
 * - Clearer intent (useAuth vs useContext(AuthContext))
 */
export function LoginModal({ isOpen, onClose, onSuccess }) {
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')

  // New hooks approach - clean and clear intent
  const { login, isLoading, error } = useAuth()
  const { warning } = useNotification()

  const handleSubmit = async (e) => {
    e.preventDefault()

    // Validate input
    if (!email || !password) {
      warning('Please fill in all fields')
      return
    }

    try {
      await login(email, password)
      setEmail('')
      setPassword('')
      onSuccess?.()
    } catch (err) {
      // Error handling via AuthContext/hook
      // No need to manually manage error state
      console.error('Login failed:', err)
    }
  }

  if (!isOpen) return null

  return (
    <div className="modal">
      <div className="modal-content">
        <h2>Login</h2>

        {error && (
          <div className="alert alert-error">{error}</div>
        )}

        <form onSubmit={handleSubmit}>
          <input
            type="email"
            placeholder="Email"
            value={email}
            onChange={(e) => setEmail(e.target.value)}
            disabled={isLoading}
            required
          />

          <input
            type="password"
            placeholder="Password"
            value={password}
            onChange={(e) => setPassword(e.target.value)}
            disabled={isLoading}
            required
          />

          <button type="submit" disabled={isLoading}>
            {isLoading ? 'Logging in...' : 'Login'}
          </button>

          <button type="button" onClick={onClose}>
            Cancel
          </button>
        </form>
      </div>
    </div>
  )
}

// ============================================================================
// VARIATION: Granular Hook Usage - Even More Optimized
// ============================================================================

import { useAuthActions } from '@/hooks'
import { useAuthStatus } from '@/hooks'

/**
 * ADVANCED - Using granular hooks
 * Use this when:
 * - Component only needs to display status (use useAuthStatus)
 * - Component only calls actions (use useAuthActions)
 * - Need to optimize re-renders in performance-critical components
 */
export function LoginModalOptimized({ isOpen, onClose, onSuccess }) {
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [localError, setLocalError] = useState(null)

  // Split hooks for granular control
  const { login } = useAuthActions() // Only methods, no state
  const { isLoading } = useAuthStatus() // Only status, no methods

  const handleSubmit = async (e) => {
    e.preventDefault()
    setLocalError(null)

    if (!email || !password) {
      setLocalError('Please fill in all fields')
      return
    }

    try {
      await login(email, password)
      setEmail('')
      setPassword('')
      onSuccess?.()
    } catch (err) {
      setLocalError(err.message || 'Login failed')
    }
  }

  if (!isOpen) return null

  return (
    <div className="modal">
      <div className="modal-content">
        <h2>Login</h2>

        {localError && (
          <div className="alert alert-error">{localError}</div>
        )}

        <form onSubmit={handleSubmit}>
          <input
            type="email"
            placeholder="Email"
            value={email}
            onChange={(e) => setEmail(e.target.value)}
            disabled={isLoading}
            required
          />

          <input
            type="password"
            placeholder="Password"
            value={password}
            onChange={(e) => setPassword(e.target.value)}
            disabled={isLoading}
            required
          />

          <button type="submit" disabled={isLoading}>
            {isLoading ? 'Logging in...' : 'Login'}
          </button>

          <button type="button" onClick={onClose}>
            Cancel
          </button>
        </form>
      </div>
    </div>
  )
}

// ============================================================================
// EXAMPLE: Admin Form - Using Multiple Hooks Together
// ============================================================================

import { useModal, useNotification, useFormState } from '@/hooks'
import { userService } from '@/services/userService'

/**
 * Example: UserFormModal - Demonstrates combining multiple hooks
 * 
 * Shows:
 * - useFormState() for form management
 * - useNotification() for user feedback
 * - useModal() for modal state
 * - useAuthStatus() for permission checking
 */
export function UserFormModal({ initialData, onClose, onSuccess }) {
  const { success, error: showError } = useNotification()
  const { isAdmin } = useAuthStatus()
  const [isSubmitting, setIsSubmitting] = useState(false)

  const { values, errors, handleChange, reset } = useFormState({
    name: initialData?.name || '',
    email: initialData?.email || '',
    role: initialData?.role || 'client'
  })

  if (!isAdmin) {
    return <p>You don't have permission to access this form.</p>
  }

  const handleSubmit = async (e) => {
    e.preventDefault()

    if (!values.name || !values.email) {
      showError('Please fill in all required fields')
      return
    }

    setIsSubmitting(true)

    try {
      let response

      if (initialData?.id) {
        // Update existing user
        response = await userService.updateUser(initialData.id, values)
      } else {
        // Create new user
        response = await userService.createUser(values)
      }

      if (response.success) {
        success(`User ${initialData?.id ? 'updated' : 'created'} successfully`)
        reset()
        onSuccess?.(response.data)
        onClose()
      }
    } catch (err) {
      showError(err.response?.data?.message || 'An error occurred')
    } finally {
      setIsSubmitting(false)
    }
  }

  return (
    <div className="modal">
      <div className="modal-content">
        <h2>{initialData?.id ? 'Edit User' : 'New User'}</h2>

        <form onSubmit={handleSubmit}>
          <div className="form-group">
            <label>Name *</label>
            <input
              type="text"
              name="name"
              value={values.name}
              onChange={handleChange}
              disabled={isSubmitting}
              required
            />
            {errors.name && <span className="error">{errors.name}</span>}
          </div>

          <div className="form-group">
            <label>Email *</label>
            <input
              type="email"
              name="email"
              value={values.email}
              onChange={handleChange}
              disabled={isSubmitting}
              required
            />
            {errors.email && <span className="error">{errors.email}</span>}
          </div>

          <div className="form-group">
            <label>Role</label>
            <select
              name="role"
              value={values.role}
              onChange={handleChange}
              disabled={isSubmitting}
            >
              <option value="client">Client</option>
              <option value="staff">Staff</option>
              <option value="admin">Admin</option>
            </select>
          </div>

          <div className="form-actions">
            <button type="submit" disabled={isSubmitting}>
              {isSubmitting ? 'Saving...' : 'Save'}
            </button>
            <button type="button" onClick={onClose}>
              Cancel
            </button>
          </div>
        </form>
      </div>
    </div>
  )
}

// ============================================================================
// EXAMPLE: Admin Panel - Managing Multiple Modals and Lists
// ============================================================================

/**
 * Example: UserManagementPanel - Complex component using multiple hooks
 * 
 * Demonstrates:
 * - useAppointments() for list data
 * - useModal() for modal coordination
 * - useUIState() for loading/error states
 * - Multiple modals working together
 */
export function UserManagementPanel() {
  const { openModal, closeModal, isOpen, getData } = useModal()
  const { success, error: showError } = useNotification()
  const { loading, setLoading } = useUIState()
  const [users, setUsers] = useState([])

  // Fetch users on mount
  useEffect(() => {
    fetchUsers()
  }, [])

  const fetchUsers = async () => {
    setLoading(true)
    try {
      const response = await userService.getUsers()
      if (response.success) {
        setUsers(response.data)
      }
    } catch (err) {
      showError('Failed to load users')
    } finally {
      setLoading(false)
    }
  }

  const handleDeleteUser = async (userId) => {
    try {
      await userService.deleteUser(userId)
      success('User deleted')
      closeModal('confirmDelete')
      fetchUsers()
    } catch (err) {
      showError('Failed to delete user')
    }
  }

  return (
    <div className="panel">
      <h2>User Management</h2>

      <button onClick={() => openModal('userForm')}>
        Add User
      </button>

      {loading ? (
        <LoadingSpinner />
      ) : (
        <table>
          <tbody>
            {users.map(user => (
              <tr key={user.id}>
                <td>{user.name}</td>
                <td>{user.email}</td>
                <td>
                  <button
                    onClick={() => openModal('userForm', user)}
                  >
                    Edit
                  </button>
                  <button
                    onClick={() => openModal('confirmDelete', user)}
                  >
                    Delete
                  </button>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      )}

      {/* User Form Modal */}
      {isOpen('userForm') && (
        <UserFormModal
          initialData={getData('userForm')}
          onClose={() => closeModal('userForm')}
          onSuccess={() => {
            closeModal('userForm')
            fetchUsers()
          }}
        />
      )}

      {/* Delete Confirmation Modal */}
      {isOpen('confirmDelete') && (
        <ConfirmationModal
          title="Delete User?"
          message={`Are you sure you want to delete ${getData('confirmDelete')?.name}?`}
          onConfirm={() => handleDeleteUser(getData('confirmDelete')?.id)}
          onCancel={() => closeModal('confirmDelete')}
        />
      )}
    </div>
  )
}

// ============================================================================
// KEY TAKEAWAYS
// ============================================================================

/**
 * MIGRATION SUMMARY
 * 
 * 1. Replace: import { AuthContext } from '@/context/AuthContext'
 *    With:    import { useAuth } from '@/hooks'
 * 
 * 2. Replace: const authContext = useContext(AuthContext)
 *    With:    const { user, login, logout } = useAuth()
 * 
 * 3. For status-only components, use useAuthStatus() instead of useAuth()
 * 
 * 4. For action-only components, use useAuthActions() instead of useAuth()
 * 
 * 5. Combine hooks as needed:
 *    - useAuth() - Full state and methods
 *    - useAuthStatus() - Status only (lighter)
 *    - useAuthActions() - Methods only
 *    - useAuthError() - Error handling
 *    - useNotification() - User feedback
 *    - useModal() - Modal coordination
 *    - useFormState() - Form management
 *    - useUIState() - General UI state
 * 
 * BENEFITS
 * ✓ Cleaner, more readable code
 * ✓ Decoupled from Context implementation
 * ✓ Easier to test (mock hooks instead of Context)
 * ✓ Better performance (granular hook selection)
 * ✓ Better IDE support and autocomplete
 * ✓ Consistent with React best practices
 * 
 * BACKWARD COMPATIBILITY
 * ✓ Old code using useContext(AuthContext) still works
 * ✓ No breaking changes
 * ✓ Migrate at your own pace
 */

// EXPORT FOR REFERENCE
export const MigrationExamples = {
  LoginModalOLD,
  LoginModal,
  LoginModalOptimized,
  UserFormModal,
  UserManagementPanel
}
