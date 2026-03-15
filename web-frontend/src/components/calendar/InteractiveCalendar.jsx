import { useState, useMemo, useCallback, useEffect } from 'react';
import axios from 'axios';
import {
  ChevronLeftIcon,
  ChevronRightIcon,
  CalendarIcon,
  FunnelIcon,
  XMarkIcon,
} from '@heroicons/react/24/outline';
import { formatServiceName, formatPrice } from '../../utils/format';

/**
 * Enhanced Interactive Calendar Component
 * Displays monthly calendar with appointment visualization, filtering, and detailed views
 */
const InteractiveCalendar = ({
  appointments = [],
  selectedDate = null,
  onDateSelect = () => {},
  currentMonth = new Date(),
  onMonthChange = () => {},
  onAppointmentClick = () => {},
  filters = {},
  onFiltersChange = () => {},
  isLoading = false,
  monthSummary = null,
  isDarkMode = true,
}) => {
  const [showFilters, setShowFilters] = useState(false);
  const [hoveredDate, setHoveredDate] = useState(null);
  const [tooltipPosition, setTooltipPosition] = useState({ x: 0, y: 0 });
  const [unavailableDates, setUnavailableDates] = useState([]);
  const [loadingUnavailable, setLoadingUnavailable] = useState(false);

  const year = currentMonth.getFullYear();
  const month = currentMonth.getMonth();
  const firstDay = new Date(year, month, 1);
  const lastDay = new Date(year, month + 1, 0);
  const daysInMonth = lastDay.getDate();
  const startingDayOfWeek = firstDay.getDay();

  const monthNames = [
    'January', 'February', 'March', 'April', 'May', 'June',
    'July', 'August', 'September', 'October', 'November', 'December'
  ];

  // Load unavailable dates from admin
  useEffect(() => {
    const loadUnavailableDates = async () => {
      try {
        setLoadingUnavailable(true);
        const response = await axios.get('/api/unavailable-dates');
        if (response.data) {
          const dates = response.data.data || response.data || [];
          setUnavailableDates(dates);
        }
      } catch (error) {
        console.error('Failed to load unavailable dates:', error);
      } finally {
        setLoadingUnavailable(false);
      }
    };
    loadUnavailableDates();
    
    // Poll for updates every 120 seconds (reduced from 30s to minimize duplicate API calls
    // since the parent CashierDashboard already polls calendar data every 30s)
    const interval = setInterval(loadUnavailableDates, 120000);
    return () => clearInterval(interval);
  }, []);

  // Check if a date is a weekend
  const isWeekend = useCallback((day) => {
    if (!day) return false;
    const date = new Date(year, month, day);
    const dayOfWeek = date.getDay();
    return dayOfWeek === 0 || dayOfWeek === 6; // 0 = Sunday, 6 = Saturday
  }, [year, month]);

  // Check if a date is unavailable (set by admin)
  const isDateUnavailable = useCallback((day) => {
    if (!day) return false;
    const dateStr = `${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
    
    return unavailableDates.some(u => {
      if (!u.date) return false;
      const uDate = (u.date || '').toString().split('T')[0];
      if (uDate && uDate === dateStr) return true;
      
      // Check recurring blackout entries
      if (u.is_recurring && u.recurring_days) {
        const dayName = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'][new Date(year, month, day).getDay()];
        if (u.recurring_days.includes(dayName)) return true;
      }
      
      return false;
    });
  }, [year, month, unavailableDates]);

  // Build calendar days array (with null padding for previous month)
  const days = [];
  for (let i = 0; i < startingDayOfWeek; i++) {
    days.push(null);
  }
  for (let i = 1; i <= daysInMonth; i++) {
    days.push(i);
  }

  // Group appointments by date for the current month
  const appointmentsByDate = useMemo(() => {
    const grouped = {};
    appointments.forEach(apt => {
      if (!apt.appointment_date) return;
      const dateKey = apt.appointment_date;
      if (!grouped[dateKey]) grouped[dateKey] = [];
      grouped[dateKey].push(apt);
    });
    return grouped;
  }, [appointments]);

  // Get appointments for a specific date
  const getAppointmentsForDate = useCallback((day) => {
    if (!day) return [];
    const dateStr = `${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
    return appointmentsByDate[dateStr] || [];
  }, [year, month, appointmentsByDate]);

  // Count appointments by type for a date (memoized)
  const getDateStats = useCallback((day) => {
    const dateAppts = getAppointmentsForDate(day);
    return {
      total: dateAppts.length,
      approved: dateAppts.filter(a => a.status === 'approved').length,
      completed: dateAppts.filter(a => a.payment_status === 'paid' || a.status === 'completed').length,
      unpaid: dateAppts.filter(a => a.payment_status === 'unpaid' || a.payment_status === 0 || !a.payment_status).length,
      partiallyPaid: dateAppts.filter(a => a.payment_status === 'partially_paid' || a.payment_status === 'partial').length,
      pending: dateAppts.filter(a => a.status === 'pending').length,
      cancelled: dateAppts.filter(a => ['cancelled', 'refunded'].includes(a.status)).length,
    };
  }, [getAppointmentsForDate]);

  // Memoize stats for all days to prevent recalculation
  const allDayStats = useMemo(() => {
    const stats = {};
    days.forEach(day => {
      if (day) {
        stats[day] = getDateStats(day);
      }
    });
    return stats;
  }, [days, getDateStats]);

  // Determine if date has appointments matching filters
  const hasMatchingAppointments = useCallback((day) => {
    const dateAppts = getAppointmentsForDate(day);
    
    // Disable weekends and unavailable dates
    if (isWeekend(day) || isDateUnavailable(day)) {
      return false;
    }
    
    if (dateAppts.length === 0) return false;

    // If no filters applied, show all dates with approved or completed appointments
    if (!filters || Object.keys(filters).length === 0) {
      return dateAppts.some(a => a.status === 'approved' || a.status === 'completed' || a.payment_status === 'paid');
    }

    // Apply active filters
    return dateAppts.some(apt => {
      if (filters.approved && apt.status === 'approved') return true;
      if (filters.completed && (apt.payment_status === 'paid' || apt.status === 'completed')) return true;
      if (filters.unpaid && (apt.payment_status === 'unpaid' || apt.payment_status === 0 || !apt.payment_status)) return true;
      if (filters.partiallyPaid && (apt.payment_status === 'partially_paid' || apt.payment_status === 'partial')) return true;
      if (filters.pending && apt.status === 'pending') return true;
      return false;
    });
  }, [getAppointmentsForDate, filters, isWeekend, isDateUnavailable]);

  // Determine badge color based on appointment status
  const getBadgeColor = useCallback((day) => {
    const stats = getDateStats(day);
    if (stats.cancelled > 0 && stats.cancelled >= stats.approved) return 'bg-red-500 text-white';
    if (stats.partiallyPaid > 0 && stats.partiallyPaid >= stats.approved) return 'bg-yellow-500 text-white';
    if (stats.completed > 0 && stats.completed >= stats.approved) return 'bg-green-500 text-white';
    return 'bg-amber-500 text-gray-900';
  }, [getDateStats]);

  // Generate tooltip content for date
  const getTooltipContent = useCallback((day) => {
    const dateAppts = getAppointmentsForDate(day);
    if (dateAppts.length === 0) return null;

    const stats = getDateStats(day);
    const services = [...new Set(dateAppts.map(a => a.service?.name || a.service_type || 'Unknown'))].slice(0, 3);
    const times = dateAppts
      .filter(a => a.start_time)
      .map(a => a.start_time.substring(0, 5))
      .sort();

    const earliest = times[0];
    const latest = times[times.length - 1];

    return {
      total: stats.total,
      approved: stats.approved,
      completed: stats.completed,
      services: services,
      earliest,
      latest,
      clients: dateAppts.slice(0, 2).map(a => `${a.user?.first_name || ''} ${a.user?.last_name || ''}`.trim()),
    };
  }, [getAppointmentsForDate, getDateStats]);

  // Handle navigation
  const goToPreviousMonth = () => {
    const prev = new Date(year, month - 1, 1);
    onMonthChange(prev);
  };

  const goToNextMonth = () => {
    const next = new Date(year, month + 1, 1);
    onMonthChange(next);
  };

  return (
    <div className="space-y-3">
      {/* Calendar Header with Controls */}
      <div className={`${isDarkMode ? 'bg-gray-900 border-amber-500/20' : 'bg-white border-amber-300/30'} border rounded-lg p-3`}>
        {/* Top Controls */}
        <div className="flex items-center justify-between mb-3">
          <div className="flex items-center gap-2">
            <button
              onClick={goToPreviousMonth}
              className={`p-1 text-amber-400 ${isDarkMode ? 'hover:bg-amber-500/10' : 'hover:bg-amber-50'} rounded transition-colors`}
              title="Previous month"
            >
              <ChevronLeftIcon className="h-4 w-4" />
            </button>

            <div className="flex items-center gap-1">
              <CalendarIcon className="h-4 w-4 text-amber-400" />
              <h3 className={`text-sm font-semibold ${isDarkMode ? 'text-amber-50' : 'text-amber-900'}`}>
                {monthNames[month]} {year}
              </h3>
            </div>

            <button
              onClick={goToNextMonth}
              className={`p-1 text-amber-400 ${isDarkMode ? 'hover:bg-amber-500/10' : 'hover:bg-amber-50'} rounded transition-colors`}
              title="Next month"
            >
              <ChevronRightIcon className="h-4 w-4" />
            </button>
          </div>

          {/* Filter Toggle */}
          <button
            onClick={() => setShowFilters(!showFilters)}
            className={`flex items-center gap-1 px-2 py-1 rounded border ${isDarkMode ? 'border-amber-500/30 hover:bg-amber-500/10' : 'border-amber-300 hover:bg-amber-50'} text-amber-400 transition-colors`}
          >
            <FunnelIcon className="h-3 w-3" />
            <span className="text-xs font-medium">Filters</span>
          </button>
        </div>

        {/* Filters Panel */}
        {showFilters && (
          <CalendarFilters
            filters={filters}
            onFiltersChange={onFiltersChange}
            onClose={() => setShowFilters(false)}
          />
        )}

        {/* Calendar Grid */}
        <div className="grid grid-cols-7 gap-0.5">
          {/* Day headers */}
          {['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'].map(day => (
            <div key={day} className={`text-center text-xs font-semibold ${isDarkMode ? 'text-gray-400' : 'text-gray-500'} py-0.5`}>
              {day}
            </div>
          ))}

          {/* Calendar days */}
          {days.map((day, index) => {
            const isSelected = day === selectedDate;
            const hasAppts = hasMatchingAppointments(day);
            const stats = day ? allDayStats[day] : null;
            const tooltip = day ? getTooltipContent(day) : null;
            const isDisabled = day && (isWeekend(day) || isDateUnavailable(day));

            return (
              <div
                key={index}
                onClick={() => day && !isDisabled && onDateSelect(day)}
                onMouseEnter={() => day && setHoveredDate(day)}
                onMouseLeave={() => setHoveredDate(null)}
                className={`group aspect-square relative flex items-center justify-center text-xs rounded cursor-pointer transition-all duration-200 ${
                  day
                    ? isDisabled
                      ? `${isDarkMode ? 'bg-gray-800/50 text-gray-500' : 'bg-gray-100 text-gray-400'} cursor-not-allowed opacity-50`
                      : hasAppts
                      ? `${getBadgeColor(day)} font-bold hover:shadow-lg hover:shadow-amber-500/30`
                      : `${isDarkMode ? 'bg-gray-800 text-gray-300 hover:bg-gray-700' : 'bg-gray-50 text-gray-700 hover:bg-gray-100'}`
                    : 'bg-transparent'
                } ${isSelected && !isDisabled ? `ring-2 ring-amber-400 ring-offset-1 ${isDarkMode ? 'ring-offset-gray-900' : 'ring-offset-white'}` : ''}`}
              >
                {/* Date number */}
                <span className="relative z-10 text-xs">{day}</span>

                {/* Appointment count badge */}
                {day && stats && stats.total > 0 && !isDisabled && (
                  <div className="absolute -top-0.5 -right-0.5 w-4 h-4 rounded-full bg-white text-gray-900 font-bold text-xs flex items-center justify-center shadow-lg">
                    {stats.total}
                  </div>
                )}

                {/* Hover Tooltip */}
                {hoveredDate === day && tooltip && !isDisabled && (
                  <div className="absolute -top-2 left-full ml-1 z-50 pointer-events-none">
                    <div className={`${isDarkMode ? 'bg-gray-800 border-gray-700 text-gray-100' : 'bg-white border-gray-200 text-gray-900 shadow-lg'} border text-xs px-2 py-1 rounded whitespace-nowrap`}>
                      <div className="font-semibold text-amber-400 mb-1">
                        {tooltip.total} Apt{tooltip.total > 1 ? 's' : ''}
                      </div>
                      {tooltip.services.length > 0 && (
                        <div className="mb-1">
                          <span className={`${isDarkMode ? 'text-gray-400' : 'text-gray-500'}`}>Svc: </span>
                          <span>{tooltip.services.slice(0, 1).join(', ')}</span>
                        </div>
                      )}
                      {tooltip.earliest && (
                        <div>
                          <span className={`${isDarkMode ? 'text-gray-400' : 'text-gray-500'}`}>Time: </span>
                          <span>{tooltip.earliest}</span>
                        </div>
                      )}
                    </div>
                  </div>
                )}
              </div>
            );
          })}
        </div>
      </div>

      {/* Monthly Summary */}
      {monthSummary && (
        <CalendarSummary summary={monthSummary} />
      )}
    </div>
  );
};

/**
 * Filter Controls Component
 */
const CalendarFilters = ({ filters = {}, onFiltersChange = () => {}, onClose = () => {}, isDarkMode = true }) => {
  const filterOptions = [
    { key: 'approved', label: 'Approved', icon: '✓' },
    { key: 'completed', label: 'Completed', icon: '✓' },
    { key: 'unpaid', label: 'Unpaid', icon: '∅' },
    { key: 'partiallyPaid', label: 'Partial', icon: '◐' },
    { key: 'pending', label: 'Pending', icon: '◉' },
  ];

  const toggleFilter = (filterKey) => {
    onFiltersChange({
      ...filters,
      [filterKey]: !filters[filterKey],
    });
  };

  return (
    <div className={`${isDarkMode ? 'bg-gray-800 border-gray-700' : 'bg-gray-50 border-gray-200'} border rounded p-2 mb-3 space-y-1`}>
      <div className="flex items-center justify-between mb-1">
        <h4 className={`text-xs font-semibold ${isDarkMode ? 'text-amber-50' : 'text-amber-900'} uppercase tracking-wide`}>Filter</h4>
        <button
          onClick={onClose}
          className={`${isDarkMode ? 'text-gray-400 hover:text-gray-300' : 'text-gray-500 hover:text-gray-700'} transition-colors`}
        >
          <XMarkIcon className="h-3 w-3" />
        </button>
      </div>
      <div className="grid grid-cols-3 md:grid-cols-5 gap-1">
        {filterOptions.map(option => (
          <label
            key={option.key}
            className={`flex items-center gap-1 px-1 py-0.5 rounded cursor-pointer ${isDarkMode ? 'hover:bg-gray-700/50' : 'hover:bg-gray-200/50'} transition-colors`}
          >
            <input
              type="checkbox"
              checked={filters[option.key] || false}
              onChange={() => toggleFilter(option.key)}
              className={`w-2 h-2 rounded ${isDarkMode ? 'border-gray-600' : 'border-gray-300'} text-amber-500 cursor-pointer`}
            />
            <span className={`text-xs ${isDarkMode ? 'text-gray-300' : 'text-gray-600'}`}>{option.label}</span>
          </label>
        ))}
      </div>
    </div>
  );
};

/**
 * Calendar Summary Panel Component
 */
const CalendarSummary = ({ summary = {}, isDarkMode = true }) => {
  return (
    <div className="grid grid-cols-2 md:grid-cols-4 gap-2">
      {/* Total Approved */}
      <div className={`${isDarkMode ? 'bg-gray-900 border-emerald-500/30' : 'bg-white border-emerald-300'} border rounded-lg p-2`}>
        <p className={`text-xs ${isDarkMode ? 'text-gray-400' : 'text-gray-500'} mb-0.5`}>Total Approved</p>
        <p className="text-lg font-bold text-emerald-400">{summary.totalApproved || 0}</p>
        <p className={`text-xs ${isDarkMode ? 'text-gray-500' : 'text-gray-400'} mt-0.5`}>appts</p>
      </div>

      {/* Completed */}
      <div className={`${isDarkMode ? 'bg-gray-900 border-green-500/30' : 'bg-white border-green-300'} border rounded-lg p-2`}>
        <p className={`text-xs ${isDarkMode ? 'text-gray-400' : 'text-gray-500'} mb-0.5`}>Completed</p>
        <p className="text-lg font-bold text-green-400">{summary.totalCompleted || 0}</p>
        <p className={`text-xs ${isDarkMode ? 'text-gray-500' : 'text-gray-400'} mt-0.5`}>paid</p>
      </div>

      {/* Expected Revenue */}
      <div className={`${isDarkMode ? 'bg-gray-900 border-blue-500/30' : 'bg-white border-blue-300'} border rounded-lg p-2`}>
        <p className={`text-xs ${isDarkMode ? 'text-gray-400' : 'text-gray-500'} mb-0.5`}>Expected</p>
        <p className="text-lg font-bold text-blue-400">{formatPrice(summary.expectedRevenue || 0)}</p>
        <p className={`text-xs ${isDarkMode ? 'text-gray-500' : 'text-gray-400'} mt-0.5`}>revenue</p>
      </div>

      {/* Actual Revenue */}
      <div className={`${isDarkMode ? 'bg-gray-900 border-amber-500/30' : 'bg-white border-amber-300'} border rounded-lg p-2`}>
        <p className={`text-xs ${isDarkMode ? 'text-gray-400' : 'text-gray-500'} mb-0.5`}>Actual</p>
        <p className="text-lg font-bold text-amber-400">{formatPrice(summary.actualRevenue || 0)}</p>
        <p className={`text-xs ${isDarkMode ? 'text-gray-500' : 'text-gray-400'} mt-0.5`}>collected</p>
      </div>
    </div>
  );
};

export default InteractiveCalendar;
export { CalendarFilters, CalendarSummary };
