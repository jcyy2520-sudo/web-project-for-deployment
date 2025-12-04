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

/**
 * Convert military time (24-hour format) to 12-hour format with AM/PM
 * Input: "14:30" or "14:30:00"
 * Output: "2:30 PM"
 */
export const formatTime12Hour = (militaryTime) => {
  if (!militaryTime) return '';
  
  // Extract hours and minutes from "HH:mm" or "HH:mm:ss" format
  const [hours, minutes] = militaryTime.split(':');
  let hour = parseInt(hours);
  const min = minutes || '00';
  
  const period = hour >= 12 ? 'PM' : 'AM';
  if (hour > 12) {
    hour -= 12;
  } else if (hour === 0) {
    hour = 12;
  }
  
  return `${hour}:${min} ${period}`;
};

export default {
  formatServiceName,
  formatPrice,
  formatAppointmentPrice,
  formatTime12Hour
};
