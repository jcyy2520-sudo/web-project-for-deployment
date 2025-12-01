/**
 * Format service name to proper case
 * Handles database values like 'legal_consultation' and converts to 'Legal Consultation'
 */
export const formatServiceName = (appointment) => {
  if (!appointment) return 'N/A';
  
  // Get the service name from various sources
  let serviceName = appointment.service?.name || appointment.service_type || appointment.type;
  
  if (!serviceName) return 'N/A';
  
  // If it's already a properly formatted service name (from database), return as-is
  if (appointment.service?.name) {
    return appointment.service.name;
  }
  
  // Convert snake_case or lowercase to proper case
  return serviceName
    .split('_')
    .map(word => word.charAt(0).toUpperCase() + word.slice(1).toLowerCase())
    .join(' ');
};

/**
 * Format price to currency format
 */
export const formatPrice = (price) => {
  if (!price) return '$0.00';
  return `$${parseFloat(price).toFixed(2)}`;
};

/**
 * Format appointment price - handles both service price and displays it nicely
 */
export const formatAppointmentPrice = (appointment) => {
  if (appointment?.service?.price) {
    return formatPrice(appointment.service.price);
  }
  return '—';
};

export default {
  formatServiceName,
  formatPrice,
  formatAppointmentPrice
};
