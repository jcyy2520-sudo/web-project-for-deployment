import { useMemo } from 'react';
import {
  EyeIcon,
  ClockIcon,
  UserIcon,
  CurrencyDollarIcon,
  CheckCircleIcon,
  ExclamationCircleIcon,
  CalendarIcon,
} from '@heroicons/react/24/outline';
import { formatServiceName, formatPrice } from '../../utils/format';

/**
 * Calendar Detail Panel Component
 * Displays detailed table of appointments for selected date with full information
 * and ability to open detailed view or process payments
 */
const CalendarDetailPanel = ({
  selectedDate,
  appointments = [],
  currentMonth,
  monthNames = [],
  onAppointmentClick = () => {},
  isLoading = false,
  isDarkMode = true,
}) => {
  if (!selectedDate) {
    return (
      <div className={`${isDarkMode ? 'bg-gray-900 border-amber-500/20' : 'bg-white border-amber-300/30'} border rounded-lg p-6 text-center`}>
        <div className={`${isDarkMode ? 'text-gray-400' : 'text-gray-500'} text-sm`}>
          <CalendarIcon className="h-12 w-12 mx-auto mb-2 opacity-30" />
          <p>Select a date to view appointments</p>
        </div>
      </div>
    );
  }

  const year = currentMonth.getFullYear();
  const month = currentMonth.getMonth();
  const dateStr = `${year}-${String(month + 1).padStart(2, '0')}-${String(selectedDate).padStart(2, '0')}`;
  const monthName = monthNames[month] || '';

  // Get appointments for the selected date, sorted by time
  const dateAppointments = useMemo(() => {
    return appointments
      .filter(apt => apt.appointment_date === dateStr)
      .sort((a, b) => {
        const timeA = a.start_time || '';
        const timeB = b.start_time || '';
        return timeA.localeCompare(timeB);
      });
  }, [appointments, dateStr]);

  if (isLoading) {
    return (
      <div className={`${isDarkMode ? 'bg-gray-900 border-amber-500/20' : 'bg-white border-amber-300/30'} border rounded-lg p-6 text-center`}>
        <div className="animate-pulse">
          <div className={`h-8 ${isDarkMode ? 'bg-gray-800' : 'bg-gray-200'} rounded w-1/3 mx-auto mb-4`}></div>
          <div className="space-y-3">
            {[1, 2, 3].map(i => (
              <div key={i} className={`h-12 ${isDarkMode ? 'bg-gray-800' : 'bg-gray-200'} rounded`}></div>
            ))}
          </div>
        </div>
      </div>
    );
  }

  if (dateAppointments.length === 0) {
    return (
      <div className={`${isDarkMode ? 'bg-gray-900 border-amber-500/20' : 'bg-white border-amber-300/30'} border rounded-lg p-6 text-center`}>
        <p className={`${isDarkMode ? 'text-gray-400' : 'text-gray-500'} text-sm`}>
          No appointments for {monthName} {selectedDate}, {year}
        </p>
      </div>
    );
  }

  const getPaymentStatusBadge = (appointment) => {
    const status = appointment?.payment_status || 'unpaid';
    if (status === 'paid') {
      return { bg: 'bg-green-500/20', text: 'text-green-400', label: 'Paid' };
    } else if (status === 'partial' || status === 'partially_paid') {
      return { bg: 'bg-yellow-500/20', text: 'text-yellow-400', label: 'Partially Paid' };
    } else if (status === 'unpaid' || status === 0 || status === '0') {
      return { bg: 'bg-red-500/20', text: 'text-red-400', label: 'Unpaid' };
    }
    return { bg: 'bg-gray-500/20', text: 'text-gray-400', label: 'Unknown' };
  };

  const getAppointmentStatusBadge = (appointment) => {
    const status = appointment?.status || 'pending';
    if (status === 'approved') {
      return { bg: 'bg-blue-500/20', text: 'text-blue-400', label: 'Approved' };
    } else if (status === 'completed') {
      return { bg: 'bg-green-500/20', text: 'text-green-400', label: 'Completed' };
    } else if (status === 'pending') {
      return { bg: 'bg-amber-500/20', text: 'text-amber-400', label: 'Pending' };
    } else if (status === 'cancelled' || status === 'refunded') {
      return { bg: 'bg-red-500/20', text: 'text-red-400', label: status };
    }
    return { bg: 'bg-gray-500/20', text: 'text-gray-400', label: 'Unknown' };
  };

  return (
    <div className="space-y-4">
      {/* Header */}
      <div className={`${isDarkMode ? 'bg-gray-900 border-amber-500/20' : 'bg-white border-amber-300/30'} border rounded-lg p-4`}>
        <h4 className="text-sm font-semibold text-amber-400 flex items-center gap-2">
          <ClockIcon className="h-4 w-4" />
          Appointments for {monthName} {selectedDate}, {year}
          <span className={`ml-auto ${isDarkMode ? 'bg-amber-500/20 text-amber-400' : 'bg-amber-100 text-amber-700'} text-xs px-2 py-1 rounded`}>
            {dateAppointments.length} appointment{dateAppointments.length > 1 ? 's' : ''}
          </span>
        </h4>
      </div>

      {/* Appointments List */}
      <div className="space-y-3">
        {dateAppointments.map((apt) => {
          const paymentBadge = getPaymentStatusBadge(apt);
          const appointmentBadge = getAppointmentStatusBadge(apt);
          const clientName = `${apt.user?.first_name || ''} ${apt.user?.last_name || ''}`.trim();
          const serviceName = apt.service?.name || apt.service_type || 'Unknown Service';
          const time = apt.start_time || 'No time set';
          const price = apt.service?.price || 0;

          return (
            <div
              key={apt.id}
              onClick={() => onAppointmentClick(apt)}
              className={`${isDarkMode ? 'bg-gray-900 border-gray-700 hover:bg-gray-800/50' : 'bg-white border-gray-200 hover:bg-gray-50'} border rounded-lg p-4 hover:border-amber-500/40 transition-all cursor-pointer group`}
            >
              {/* Top row: Client name and appointment status */}
              <div className="flex items-start justify-between mb-3">
                <div className="flex-1">
                  <p className={`text-sm font-semibold ${isDarkMode ? 'text-amber-50' : 'text-amber-900'}`}>{clientName}</p>
                  <p className={`text-xs ${isDarkMode ? 'text-gray-500' : 'text-gray-400'} mt-1`}>{apt.user?.email || 'No email'}</p>
                </div>
                <div className="flex items-center gap-2 ml-3">
                  <span className={`text-xs font-medium px-2 py-1 rounded ${appointmentBadge.bg} ${appointmentBadge.text}`}>
                    {appointmentBadge.label}
                  </span>
                  <div className={`w-8 h-8 rounded border ${isDarkMode ? 'border-amber-500/30 bg-amber-500/10' : 'border-amber-300 bg-amber-50'} flex items-center justify-center text-amber-400 opacity-0 group-hover:opacity-100 transition-opacity`}>
                    <EyeIcon className="h-4 w-4" />
                  </div>
                </div>
              </div>

              {/* Middle row: Service info and time */}
              <div className={`grid grid-cols-2 md:grid-cols-4 gap-3 mb-3 pb-3 border-b ${isDarkMode ? 'border-gray-800' : 'border-gray-100'}`}>
                {/* Service */}
                <div>
                  <p className={`text-xs ${isDarkMode ? 'text-gray-500' : 'text-gray-400'} mb-1`}>Service</p>
                  <p className={`text-sm ${isDarkMode ? 'text-gray-300' : 'text-gray-700'}`}>{serviceName}</p>
                </div>

                {/* Time */}
                <div>
                  <p className={`text-xs ${isDarkMode ? 'text-gray-500' : 'text-gray-400'} mb-1`}>Time</p>
                  <p className={`text-sm ${isDarkMode ? 'text-gray-300' : 'text-gray-700'} font-mono`}>
                    {time}{apt.end_time ? ` - ${apt.end_time}` : ''}
                  </p>
                </div>

                {/* ID/Reference */}
                <div>
                  <p className={`text-xs ${isDarkMode ? 'text-gray-500' : 'text-gray-400'} mb-1`}>Reference</p>
                  <p className={`text-sm ${isDarkMode ? 'text-gray-300' : 'text-gray-700'} font-mono truncate`} title={typeof apt.id === 'string' ? apt.id : JSON.stringify(apt.id)}>
                    {typeof apt.id === 'string' ? apt.id?.substring(0, 8) : (apt.id?.id || JSON.stringify(apt.id)).toString().substring(0, 8)}...
                  </p>
                </div>

                {/* ID Type */}
                <div>
                  <p className={`text-xs ${isDarkMode ? 'text-gray-500' : 'text-gray-400'} mb-1`}>ID Type</p>
                  <p className={`text-sm ${isDarkMode ? 'text-gray-300' : 'text-gray-700'}`}>
                    {apt.identification_type || apt.form_of_id || 'Not specified'}
                  </p>
                </div>
              </div>

              {/* Bottom row: Payment info and action */}
              <div className="flex items-end justify-between">
                <div className="grid grid-cols-2 gap-4">
                  {/* Price */}
                  <div>
                    <p className={`text-xs ${isDarkMode ? 'text-gray-500' : 'text-gray-400'} mb-1`}>Price</p>
                    <p className="text-sm font-semibold text-amber-400">{formatPrice(price)}</p>
                  </div>

                  {/* Payment Status */}
                  <div>
                    <p className={`text-xs ${isDarkMode ? 'text-gray-500' : 'text-gray-400'} mb-1`}>Payment</p>
                    <span className={`text-xs font-medium px-2 py-1 rounded inline-block ${paymentBadge.bg} ${paymentBadge.text}`}>
                      {paymentBadge.label}
                    </span>
                  </div>
                </div>

                {/* Amount Paid (if partially or paid) */}
                {apt.payment_status !== 'unpaid' && apt.payment_status !== 0 && apt.amount_paid && (
                  <div className="text-right">
                    <p className="text-xs text-gray-500 mb-1">Amount Paid</p>
                    <p className="text-sm font-semibold text-green-400">{formatPrice(apt.amount_paid)}</p>
                  </div>
                )}
              </div>

              {/* Quick action indicators */}
              {(apt.payment_status === 'unpaid' || apt.payment_status === 0 || !apt.payment_status) && (
                <div className="mt-3 pt-3 border-t border-gray-800 flex items-center gap-2">
                  <ExclamationCircleIcon className="h-4 w-4 text-amber-500" />
                  <span className="text-xs text-amber-300">Click to process payment or view details</span>
                </div>
              )}
              {(apt.payment_status === 'partial' || apt.payment_status === 'partially_paid') && (
                <div className="mt-3 pt-3 border-t border-gray-800 flex items-center gap-2">
                  <ExclamationCircleIcon className="h-4 w-4 text-yellow-500" />
                  <span className="text-xs text-yellow-300">Partially paid - Click to collect remaining balance</span>
                </div>
              )}
              {apt.payment_status === 'paid' && (
                <div className="mt-3 pt-3 border-t border-gray-800 flex items-center gap-2">
                  <CheckCircleIcon className="h-4 w-4 text-green-500" />
                  <span className="text-xs text-green-300">Completed - Click to view details or reprint receipt</span>
                </div>
              )}
            </div>
          );
        })}
      </div>
    </div>
  );
};

export default CalendarDetailPanel;
