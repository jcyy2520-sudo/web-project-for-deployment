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
 * Clicking on unavailable dates shows the reason and time range
 */
const UnavailableDatesViewer = ({ isDarkMode = true, onDateSelect = null }) => {
  const { callApi, loading } = useApi();
  const [unavailableDates, setUnavailableDates] = useState([]);
  const [currentMonth, setCurrentMonth] = useState(new Date());
  const [selectedDateInfo, setSelectedDateInfo] = useState(null); // Popup for blocked date info

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
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    const dateStr = `${year}-${month}-${day}`;
    return unavailableDates.some(u => {
      const uDate = (u.date || '').toString().split('T')[0];
      return uDate === dateStr || isBlackedOut(date);
    });
  };

  // Check for blackout dates
  const isBlackedOut = (date) => {
    const dayOfWeek = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'][date.getDay()];
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    const dateStr = `${year}-${month}-${day}`;

    return unavailableDates.some(u => {
      // Weekend check
      if (u.type === 'weekend') {
        return dayOfWeek === 'saturday' || dayOfWeek === 'sunday';
      }

      // Specific blackout date
      const uDate = (u.date || '').toString().split('T')[0];
      if (uDate && uDate === dateStr) {
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
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    const dateStr = `${year}-${month}-${day}`;
    const dayOfWeek = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'][date.getDay()];

    const matching = unavailableDates.find(u => {
      if (u.type === 'weekend' && (dayOfWeek === 'saturday' || dayOfWeek === 'sunday')) {
        return true;
      }
      const uDate = (u.date || '').toString().split('T')[0];
      if (uDate === dateStr) {
        return true;
      }
      if (u.is_recurring && u.recurring_days?.includes(dayOfWeek)) {
        return true;
      }
      return false;
    });

    return matching?.reason || 'Not available';
  };

  // Get detailed unavailable info including time ranges
  const getDateDetailedInfo = (date) => {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    const dateStr = `${year}-${month}-${day}`;
    const dayOfWeek = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'][date.getDay()];

    const matchingEntries = unavailableDates.filter(u => {
      if (u.type === 'weekend' && (dayOfWeek === 'saturday' || dayOfWeek === 'sunday')) return true;
      const uDate = (u.date || '').toString().split('T')[0];
      if (uDate === dateStr) return true;
      if (u.is_recurring && u.recurring_days?.includes(dayOfWeek)) return true;
      return false;
    });

    if (matchingEntries.length === 0) return null;

    // Check for full-day blocks
    const fullDayBlock = matchingEntries.find(u => {
      if (u.type === 'weekend') return true;
      const hasTimeRange = (u.start_time && u.end_time) || u.time_range;
      const isAllDay = u.all_day === true || u.all_day === 1;
      return !hasTimeRange || isAllDay;
    });

    // Collect time ranges
    const timeRanges = matchingEntries
      .filter(u => u.type !== 'weekend')
      .map(u => ({
        reason: u.reason || 'Blocked by admin',
        timeRange: u.time_range || (u.start_time && u.end_time ? `${u.start_time} - ${u.end_time}` : null),
        isAllDay: (!u.start_time && !u.end_time && !u.time_range) || u.all_day === true || u.all_day === 1
      }));

    return {
      date: date.toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' }),
      isFullBlock: !!fullDayBlock,
      isWeekend: matchingEntries.some(u => u.type === 'weekend') || dayOfWeek === 'saturday' || dayOfWeek === 'sunday',
      reason: fullDayBlock?.reason || matchingEntries[0]?.reason || 'Not available',
      entries: timeRanges
    };
  };

  // Handle date click to show info
  const handleDateClick = (date) => {
    const isPast = date < new Date();
    const isUnavailable = isDateUnavailable(date);
    const isDayWeekend = date.getDay() === 0 || date.getDay() === 6;

    if (isUnavailable || isDayWeekend) {
      const info = getDateDetailedInfo(date);
      if (info) {
        setSelectedDateInfo(info);
      }
    } else if (!isPast && onDateSelect) {
      onDateSelect(date);
      setSelectedDateInfo(null);
    }
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
          <div className="flex items-center gap-2">
            <div className={`w-3 h-3 rounded ${isDarkMode ? 'bg-amber-500' : 'bg-amber-400'}`}></div>
            <span className={isDarkMode ? 'text-gray-300' : 'text-gray-600'}>Partial Block</span>
          </div>
        </div>
        <p className={`text-xs mt-2 ${isDarkMode ? 'text-gray-500' : 'text-gray-400'}`}>
          Click on blocked dates to see why they are unavailable
        </p>
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
                const detailedInfo = getDateDetailedInfo(date);
                const hasTimeRange = detailedInfo?.entries?.some(e => e.timeRange && !e.isAllDay);
                const isPartialBlock = hasTimeRange && !detailedInfo?.isFullBlock && !isDayWeekend;

                let bgColor = isDarkMode ? 'bg-gray-700' : 'bg-gray-100';
                let textColor = isDarkMode ? 'text-gray-100' : 'text-gray-900';
                let borderColor = isDarkMode ? 'border-gray-600' : 'border-gray-300';

                // Apply colors based on state
                if (isPast) {
                  bgColor = isDarkMode ? 'bg-gray-800/50' : 'bg-gray-200/50';
                  textColor = isDarkMode ? 'text-gray-500' : 'text-gray-500';
                } else if (isPartialBlock) {
                  bgColor = isDarkMode ? 'bg-amber-500/20' : 'bg-amber-100';
                  textColor = isDarkMode ? 'text-amber-400' : 'text-amber-700';
                  borderColor = isDarkMode ? 'border-amber-500/30' : 'border-amber-300';
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
                    className={`aspect-square flex flex-col items-center justify-center rounded border-2 p-1 text-xs font-semibold cursor-pointer transition-all hover:shadow-md relative ${bgColor} ${textColor} ${borderColor}`}
                    title={
                      isUnavailable && !isPast
                        ? `${getUnavailableReason(date)}${hasTimeRange ? ' (click for details)' : ''}`
                        : isPast
                        ? 'Past date'
                        : 'Available'
                    }
                    onClick={() => handleDateClick(date)}
                  >
                    {date.getDate()}
                    {isUnavailable && !isPast && !isPartialBlock && (
                      <XMarkIcon className="h-3 w-3 mt-0.5" />
                    )}
                    {isPartialBlock && !isPast && (
                      <ClockIcon className="h-3 w-3 mt-0.5" />
                    )}
                  </div>
                );
              })}
            </div>

            {/* Block Info Popup */}
            {selectedDateInfo && (
              <div className={`p-4 rounded-lg border ${
                isDarkMode
                  ? 'bg-gray-900 border-red-500/30'
                  : 'bg-red-50 border-red-200'
              }`}>
                <div className="flex items-start justify-between mb-2">
                  <div className="flex items-center gap-2">
                    {selectedDateInfo.isWeekend ? (
                      <CalendarIcon className={`h-5 w-5 flex-shrink-0 ${isDarkMode ? 'text-gray-400' : 'text-gray-600'}`} />
                    ) : selectedDateInfo.entries?.some(e => e.timeRange && !e.isAllDay) ? (
                      <ClockIcon className={`h-5 w-5 flex-shrink-0 ${isDarkMode ? 'text-amber-400' : 'text-amber-600'}`} />
                    ) : (
                      <ExclamationTriangleIcon className={`h-5 w-5 flex-shrink-0 ${isDarkMode ? 'text-red-400' : 'text-red-600'}`} />
                    )}
                    <h4 className={`text-sm font-semibold ${isDarkMode ? 'text-amber-50' : 'text-gray-900'}`}>
                      {selectedDateInfo.isWeekend ? 'Weekend — Closed' : 
                       selectedDateInfo.isFullBlock ? 'Date Unavailable' : 'Partially Blocked'}
                    </h4>
                  </div>
                  <button
                    onClick={() => setSelectedDateInfo(null)}
                    className={`p-0.5 rounded transition-colors ${isDarkMode ? 'text-gray-400 hover:text-gray-200 hover:bg-gray-700' : 'text-gray-500 hover:text-gray-700 hover:bg-gray-200'}`}
                  >
                    <XMarkIcon className="h-4 w-4" />
                  </button>
                </div>

                <p className={`text-xs mb-2 ${isDarkMode ? 'text-gray-300' : 'text-gray-600'}`}>
                  {selectedDateInfo.date}
                </p>

                {!selectedDateInfo.isWeekend && (
                  <div className={`text-xs space-y-2 ${isDarkMode ? 'text-gray-300' : 'text-gray-700'}`}>
                    <div className={`px-3 py-2 rounded border ${
                      isDarkMode ? 'bg-red-900/20 border-red-500/30' : 'bg-red-100 border-red-200'
                    }`}>
                      <p className="font-medium mb-1">Reason:</p>
                      <p>{selectedDateInfo.reason}</p>
                    </div>

                    {selectedDateInfo.entries?.length > 0 && selectedDateInfo.entries.some(e => e.timeRange) && (
                      <div className={`px-3 py-2 rounded border ${
                        isDarkMode ? 'bg-amber-900/20 border-amber-500/30' : 'bg-amber-50 border-amber-200'
                      }`}>
                        <p className={`font-medium mb-1 flex items-center gap-1 ${isDarkMode ? 'text-amber-300' : 'text-amber-700'}`}>
                          <ClockIcon className="h-3 w-3" /> Blocked Time Ranges:
                        </p>
                        {selectedDateInfo.entries.filter(e => e.timeRange).map((entry, idx) => (
                          <div key={idx} className="flex items-center gap-2 ml-2 mt-1">
                            <span className={`w-1.5 h-1.5 rounded-full ${isDarkMode ? 'bg-amber-400' : 'bg-amber-500'}`}></span>
                            <span className="font-mono">{entry.timeRange}</span>
                            <span className={isDarkMode ? 'text-amber-300/70' : 'text-amber-600'}>— {entry.reason}</span>
                          </div>
                        ))}
                        {!selectedDateInfo.isFullBlock && (
                          <p className={`mt-2 italic ${isDarkMode ? 'text-amber-300/80' : 'text-amber-600'}`}>
                            You may still book this date outside the blocked time ranges.
                          </p>
                        )}
                      </div>
                    )}

                    {selectedDateInfo.entries?.length > 0 && selectedDateInfo.entries.some(e => e.isAllDay) && (
                      <div className={`px-3 py-2 rounded border ${
                        isDarkMode ? 'bg-red-900/20 border-red-500/30' : 'bg-red-100 border-red-200'
                      }`}>
                        <p className={`font-medium ${isDarkMode ? 'text-red-300' : 'text-red-700'}`}>
                          Entire day is blocked
                        </p>
                      </div>
                    )}
                  </div>
                )}

                {selectedDateInfo.isWeekend && (
                  <p className={`text-xs ${isDarkMode ? 'text-gray-400' : 'text-gray-500'}`}>
                    The office is closed on weekends. Please select a weekday.
                  </p>
                )}
              </div>
            )}

            {/* Info section */}
            <div className={`p-3 rounded border ${
              isDarkMode
                ? 'bg-blue-500/10 border-blue-500/30 text-blue-400'
                : 'bg-blue-50 border-blue-200 text-blue-700'
            }`}>
              <p className="text-xs">
                <strong>Tip:</strong> Click on any blocked or unavailable date to see the reason and details. Dates with a <ClockIcon className="inline h-3 w-3" /> icon have specific time ranges blocked.
              </p>
            </div>
          </div>
        )}
      </div>
    </div>
  );
};

export default UnavailableDatesViewer;
