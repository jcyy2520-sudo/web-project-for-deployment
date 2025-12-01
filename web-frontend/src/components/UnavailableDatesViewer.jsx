import { useState, useEffect } from 'react';
import axios from 'axios';
import { useApi } from '../hooks/useApi';
import {
  ExclamationTriangleIcon,
  XMarkIcon,
  CalendarIcon,
  ClockIcon
} from '@heroicons/react/24/outline';
import LoadingSpinner from './LoadingSpinner';

/**
 * Unavailable Dates Viewer Component
 * Shows clients which dates are unavailable for booking
 */
const UnavailableDatesViewer = ({ isDarkMode = true, onDateSelect = null }) => {
  const { callApi, loading } = useApi();
  const [unavailableDates, setUnavailableDates] = useState([]);
  const [currentMonth, setCurrentMonth] = useState(new Date());

  useEffect(() => {
    loadUnavailableDates();
  }, []);

  const loadUnavailableDates = async () => {
    const result = await callApi(() =>
      axios.get('/api/unavailable-dates')
    );

    if (result.success) {
      setUnavailableDates(result.data.data || []);
    }
  };

  // Check if a date is unavailable
  const isDateUnavailable = (date) => {
    const dateStr = date.toISOString().split('T')[0];
    return unavailableDates.some(u => u.date === dateStr || isBlackedOut(date));
  };

  // Check for blackout dates
  const isBlackedOut = (date) => {
    const dayOfWeek = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'][date.getDay()];

    return unavailableDates.some(u => {
      // Weekend check
      if (u.type === 'weekend') {
        return dayOfWeek === 'saturday' || dayOfWeek === 'sunday';
      }

      // Specific blackout date
      if (u.date && u.date === date.toISOString().split('T')[0]) {
        return true;
      }

      // Recurring blackout
      if (u.is_recurring && u.recurring_days?.includes(dayOfWeek)) {
        return true;
      }

      return false;
    });
  };

  // Get unavailable reason
  const getUnavailableReason = (date) => {
    const dateStr = date.toISOString().split('T')[0];
    const dayOfWeek = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'][date.getDay()];

    const matching = unavailableDates.find(u => {
      if (u.type === 'weekend' && (dayOfWeek === 'saturday' || dayOfWeek === 'sunday')) {
        return true;
      }
      if (u.date === dateStr) {
        return true;
      }
      if (u.is_recurring && u.recurring_days?.includes(dayOfWeek)) {
        return true;
      }
      return false;
    });

    return matching?.reason || 'Not available';
  };

  // Calendar generation logic
  const getDaysInMonth = (date) => {
    return new Date(date.getFullYear(), date.getMonth() + 1, 0).getDate();
  };

  const getFirstDayOfMonth = (date) => {
    return new Date(date.getFullYear(), date.getMonth(), 1).getDay();
  };

  const handlePrevMonth = () => {
    setCurrentMonth(new Date(currentMonth.getFullYear(), currentMonth.getMonth() - 1));
  };

  const handleNextMonth = () => {
    setCurrentMonth(new Date(currentMonth.getFullYear(), currentMonth.getMonth() + 1));
  };

  const daysInMonth = getDaysInMonth(currentMonth);
  const firstDay = getFirstDayOfMonth(currentMonth);
  const days = [];

  // Add empty cells for days before month starts
  for (let i = 0; i < firstDay; i++) {
    days.push(null);
  }

  // Add days of month
  for (let i = 1; i <= daysInMonth; i++) {
    days.push(new Date(currentMonth.getFullYear(), currentMonth.getMonth(), i));
  }

  const monthName = currentMonth.toLocaleDateString('en-US', { month: 'long', year: 'numeric' });
  const isCurrentMonth = 
    currentMonth.getMonth() === new Date().getMonth() &&
    currentMonth.getFullYear() === new Date().getFullYear();

  return (
    <div className={`rounded-lg border shadow-lg ${isDarkMode ? 'bg-gray-800 border-gray-700' : 'bg-white border-gray-200'}`}>
      {/* Header */}
      <div className={`p-4 border-b ${isDarkMode ? 'border-gray-700 bg-gray-900' : 'border-gray-200 bg-gray-50'}`}>
        <div className="flex items-center gap-2 mb-3">
          <CalendarIcon className={`h-5 w-5 ${isDarkMode ? 'text-amber-400' : 'text-amber-600'}`} />
          <h3 className={`font-semibold ${isDarkMode ? 'text-amber-50' : 'text-amber-900'}`}>
            Availability
          </h3>
        </div>

        {/* Month Navigation */}
        <div className="flex items-center justify-between mb-4">
          <button
            onClick={handlePrevMonth}
            className={`px-3 py-1 rounded text-sm transition-colors ${
              isDarkMode
                ? 'text-gray-300 hover:bg-gray-700'
                : 'text-gray-700 hover:bg-gray-100'
            }`}
          >
            ← Previous
          </button>
          <span className={`font-semibold ${isDarkMode ? 'text-amber-50' : 'text-amber-900'}`}>
            {monthName}
          </span>
          <button
            onClick={handleNextMonth}
            className={`px-3 py-1 rounded text-sm transition-colors ${
              isDarkMode
                ? 'text-gray-300 hover:bg-gray-700'
                : 'text-gray-700 hover:bg-gray-100'
            }`}
          >
            Next →
          </button>
        </div>

        {/* Legend */}
        <div className="grid grid-cols-2 gap-3 text-xs">
          <div className="flex items-center gap-2">
            <div className={`w-3 h-3 rounded ${isDarkMode ? 'bg-green-500' : 'bg-green-400'}`}></div>
            <span className={isDarkMode ? 'text-gray-300' : 'text-gray-600'}>Available</span>
          </div>
          <div className="flex items-center gap-2">
            <div className={`w-3 h-3 rounded ${isDarkMode ? 'bg-red-500' : 'bg-red-400'}`}></div>
            <span className={isDarkMode ? 'text-gray-300' : 'text-gray-600'}>Unavailable</span>
          </div>
          <div className="flex items-center gap-2">
            <div className={`w-3 h-3 rounded ${isDarkMode ? 'bg-gray-500' : 'bg-gray-300'}`}></div>
            <span className={isDarkMode ? 'text-gray-300' : 'text-gray-600'}>Weekend</span>
          </div>
          <div className="flex items-center gap-2">
            <div className={`w-3 h-3 rounded opacity-30 ${isDarkMode ? 'bg-gray-500' : 'bg-gray-300'}`}></div>
            <span className={isDarkMode ? 'text-gray-400' : 'text-gray-500'}>Past Date</span>
          </div>
        </div>
      </div>

      {/* Calendar */}
      <div className="p-4">
        {loading ? (
          <div className="flex justify-center py-8">
            <LoadingSpinner size="sm" />
          </div>
        ) : (
          <div className="space-y-4">
            {/* Day headers */}
            <div className="grid grid-cols-7 gap-2">
              {['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'].map(day => (
                <div
                  key={day}
                  className={`text-center text-xs font-semibold py-2 ${
                    isDarkMode ? 'text-gray-400' : 'text-gray-600'
                  }`}
                >
                  {day}
                </div>
              ))}
            </div>

            {/* Calendar days */}
            <div className="grid grid-cols-7 gap-2">
              {days.map((date, idx) => {
                if (!date) {
                  return (
                    <div key={`empty-${idx}`} className="aspect-square"></div>
                  );
                }

                const isPast = date < new Date() && !isCurrentMonth;
                const isUnavailable = isDateUnavailable(date);
                const isDayWeekend = date.getDay() === 0 || date.getDay() === 6;

                let bgColor = isDarkMode ? 'bg-gray-700' : 'bg-gray-100';
                let textColor = isDarkMode ? 'text-gray-100' : 'text-gray-900';
                let borderColor = isDarkMode ? 'border-gray-600' : 'border-gray-300';

                // Apply colors based on state
                if (isPast) {
                  bgColor = isDarkMode ? 'bg-gray-800/50' : 'bg-gray-200/50';
                  textColor = isDarkMode ? 'text-gray-500' : 'text-gray-500';
                } else if (isUnavailable) {
                  bgColor = isDarkMode ? 'bg-red-500/20' : 'bg-red-100';
                  textColor = isDarkMode ? 'text-red-400' : 'text-red-700';
                  borderColor = isDarkMode ? 'border-red-500/30' : 'border-red-300';
                } else if (isDayWeekend) {
                  bgColor = isDarkMode ? 'bg-gray-600/50' : 'bg-gray-200';
                  textColor = isDarkMode ? 'text-gray-300' : 'text-gray-700';
                } else {
                  bgColor = isDarkMode ? 'bg-green-500/20' : 'bg-green-100';
                  textColor = isDarkMode ? 'text-green-400' : 'text-green-700';
                  borderColor = isDarkMode ? 'border-green-500/30' : 'border-green-300';
                }

                return (
                  <div
                    key={date.toISOString()}
                    className={`aspect-square flex flex-col items-center justify-center rounded border-2 p-1 text-xs font-semibold cursor-pointer transition-all hover:shadow-md ${bgColor} ${textColor} ${borderColor}`}
                    title={
                      isUnavailable && !isPast
                        ? `${getUnavailableReason(date)}`
                        : isPast
                        ? 'Past date'
                        : 'Available'
                    }
                    onClick={() => {
                      if (!isUnavailable && !isPast && onDateSelect) {
                        onDateSelect(date);
                      }
                    }}
                  >
                    {date.getDate()}
                    {isUnavailable && !isPast && (
                      <XMarkIcon className="h-3 w-3 mt-0.5" />
                    )}
                  </div>
                );
              })}
            </div>

            {/* Info section */}
            <div className={`p-3 rounded border ${
              isDarkMode
                ? 'bg-blue-500/10 border-blue-500/30 text-blue-400'
                : 'bg-blue-50 border-blue-200 text-blue-700'
            }`}>
              <p className="text-xs">
                <strong>Note:</strong> Unavailable dates are blocked by admin. Please select an available date to book an appointment.
              </p>
            </div>
          </div>
        )}
      </div>
    </div>
  );
};

export default UnavailableDatesViewer;
