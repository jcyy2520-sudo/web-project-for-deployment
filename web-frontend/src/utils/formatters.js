/**
 * @deprecated This file is NOT imported anywhere in the codebase.
 * Use utils/format.js instead for formatting utilities.
 * Safe to remove after verifying no new imports reference it.
 */

/**
 * Format date to readable format
 */
export const formatDate = (date) => {
  if (!date) return 'N/A';
  return new Date(date).toLocaleDateString();
};

/**
 * Format currency
 */
export const formatCurrency = (amount) => {
  const num = amount === null || amount === undefined ? 0 : Number(amount) || 0;
  return `₱${num.toLocaleString()}`;
};

/**
 * Format user full name
 */
export const formatUserName = (firstName, lastName) => {
  return `${firstName || ''} ${lastName || ''}`.trim();
};

/**
 * Get initials from name
 */
export const getInitials = (firstName, lastName) => {
  return `${firstName?.charAt(0) || ''}${lastName?.charAt(0) || ''}`.toUpperCase();
};

/**
 * Validate email format
 */
export const validateEmail = (email) => {
  return /\S+@\S+\.\S+/.test(email);
};

/**
 * Capitalize first letter
 */
export const capitalize = (str) => {
  if (!str) return '';
  return str.charAt(0).toUpperCase() + str.slice(1);
};

/**
 * Debounce function
 */
export const debounce = (func, wait) => {
  let timeout;
  return function executedFunction(...args) {
    const later = () => {
      clearTimeout(timeout);
      func(...args);
    };
    clearTimeout(timeout);
    timeout = setTimeout(later, wait);
  };
};

export default {
  formatDate,
  formatCurrency,
  formatUserName,
  getInitials,
  validateEmail,
  capitalize,
  debounce
};
