import { useState, useEffect, useCallback, useRef } from 'react';
import {
  ChevronLeftIcon,
  ChevronRightIcon,
  CalendarDaysIcon,
  XMarkIcon,
  ArrowPathIcon,
  InformationCircleIcon,
} from '@heroicons/react/24/outline';
import axios from 'axios';

const DAY_NAMES = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

/**
 * Admin Appointment Calendar Modal
 *
 * Read-only calendar overlay that lets admins visually pick a date to filter
 * the appointments table. No database writes — purely a filtering tool.
 *
 * Props:
 *   isOpen        – boolean controlling visibility
 *   onClose       – callback when the modal should close
 *   onSelectDate  – (dateStr: string | null, label: string) => void
 *   selectedDate  – currently active date filter (YYYY-MM-DD | null)
 *   isDarkMode    – theme flag
 */
const AppointmentCalendarModal = ({
  isOpen,
  onClose,
  onSelectDate,
  selectedDate,
  isDarkMode = true,
}) => {
  const today = new Date();
  const todayStr = formatDateStr(today);

  // Calendar display month
  const [viewDate, setViewDate] = useState(new Date(today.getFullYear(), today.getMonth(), 1));
  const [monthData, setMonthData] = useState(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);
  const [tooltip, setTooltip] = useState(null); // { x, y, content }

  const modalRef = useRef(null);
  const calendarGridRef = useRef(null);
  // Track focused day for keyboard navigation (1-based day number)
  const [focusedDay, setFocusedDay] = useState(null);
  const dayButtonRefs = useRef({});

  // ---- Helpers ----
  function formatDateStr(d) {
    const y = d.getFullYear();
    const m = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return `${y}-${m}-${day}`;
  }

  function daysInMonth(year, month) {
    return new Date(year, month + 1, 0).getDate();
  }

  // ---- Data fetching ----
  const fetchMonthData = useCallback(async (year, month) => {
    setLoading(true);
    setError(null);
    try {
      const res = await axios.get('/api/admin/appointments/monthly-summary', {
        params: { year, month: month + 1 }, // API expects 1-indexed month
      });
      if (res.data?.success) {
        setMonthData(res.data.data);
      } else {
        setError('Failed to load calendar data');
      }
    } catch (err) {
      console.error('[AppointmentCalendarModal] fetch error:', err);
      setError('Unable to load calendar data. Please try again.');
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    if (isOpen) {
      fetchMonthData(viewDate.getFullYear(), viewDate.getMonth());
    }
  }, [isOpen, viewDate, fetchMonthData]);

  // Reset view to today's month when modal opens
  useEffect(() => {
    if (isOpen) {
      setViewDate(new Date(today.getFullYear(), today.getMonth(), 1));
      setTooltip(null);
      setFocusedDay(null);
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [isOpen]);

  // Focus the day button when focusedDay changes
  useEffect(() => {
    if (focusedDay && dayButtonRefs.current[focusedDay]) {
      dayButtonRefs.current[focusedDay].focus();
    }
  }, [focusedDay]);

  // ---- Navigation ----
  const goToPrevMonth = () => {
    setViewDate(new Date(viewDate.getFullYear(), viewDate.getMonth() - 1, 1));
    setFocusedDay(null);
  };
  const goToNextMonth = () => {
    setViewDate(new Date(viewDate.getFullYear(), viewDate.getMonth() + 1, 1));
    setFocusedDay(null);
  };
  const goToToday = () => {
    setViewDate(new Date(today.getFullYear(), today.getMonth(), 1));
    setFocusedDay(today.getDate());
  };

  // ---- Quick actions ----
  const handleSelectToday = () => {
    onSelectDate(todayStr, `Today (${todayStr})`);
    onClose();
  };

  const handleSelectNext7Days = () => {
    const end = new Date(today);
    end.setDate(end.getDate() + 6);
    const endStr = formatDateStr(end);
    onSelectDate(`${todayStr}..${endStr}`, `Next 7 Days (${todayStr} – ${endStr})`);
    onClose();
  };

  const handleViewAll = () => {
    onSelectDate(null, 'All Appointments');
    onClose();
  };

  const handleResetToToday = () => {
    handleSelectToday();
  };

  // ---- Date click ----
  const handleDayClick = (dateStr, isBlocked, blockReason) => {
    if (isBlocked) {
      // Show a friendly message rather than filtering
      setTooltip(null);
      window.showToast?.(
        'Date Unavailable',
        `The date ${dateStr} is unavailable: ${blockReason}. Please choose another date.`,
        'warning',
      );
      return;
    }
    onSelectDate(dateStr, dateStr);
    onClose();
  };

  // ---- Tooltip on hover ----
  const showTooltip = (e, dateStr, content) => {
    const rect = e.currentTarget.getBoundingClientRect();
    const modalRect = modalRef.current?.getBoundingClientRect() || { left: 0, top: 0 };
    setTooltip({
      x: rect.left - modalRect.left + rect.width / 2,
      y: rect.top - modalRect.top - 4,
      content,
    });
  };
  const hideTooltip = () => setTooltip(null);

  // ---- Keyboard navigation ----
  const handleCalendarKeyDown = (e) => {
    const totalDays = daysInMonth(viewDate.getFullYear(), viewDate.getMonth());
    let newDay = focusedDay;

    switch (e.key) {
      case 'ArrowRight':
        e.preventDefault();
        newDay = focusedDay ? Math.min(focusedDay + 1, totalDays) : 1;
        break;
      case 'ArrowLeft':
        e.preventDefault();
        newDay = focusedDay ? Math.max(focusedDay - 1, 1) : 1;
        break;
      case 'ArrowDown':
        e.preventDefault();
        newDay = focusedDay ? Math.min(focusedDay + 7, totalDays) : 1;
        break;
      case 'ArrowUp':
        e.preventDefault();
        newDay = focusedDay ? Math.max(focusedDay - 7, 1) : 1;
        break;
      case 'Home':
        e.preventDefault();
        newDay = 1;
        break;
      case 'End':
        e.preventDefault();
        newDay = totalDays;
        break;
      case 'Escape':
        e.preventDefault();
        onClose();
        return;
      default:
        return;
    }

    setFocusedDay(newDay);
  };

  // ---- Render calendar grid ----
  const renderCalendar = () => {
    const year = viewDate.getFullYear();
    const month = viewDate.getMonth();
    const firstDayOfWeek = new Date(year, month, 1).getDay();
    const totalDays = daysInMonth(year, month);

    const cells = [];

    // Empty padding cells
    for (let i = 0; i < firstDayOfWeek; i++) {
      cells.push(<div key={`pad-${i}`} className="aspect-square" />);
    }

    for (let day = 1; day <= totalDays; day++) {
      const date = new Date(year, month, day);
      const dateStr = formatDateStr(date);
      const isToday = dateStr === todayStr;
      const isSelected = dateStr === selectedDate;
      const isWeekend = date.getDay() === 0 || date.getDay() === 6;

      // Data from API
      const count = monthData?.appointment_counts?.[dateStr] || 0;
      const statuses = monthData?.status_counts?.[dateStr] || {};
      const blockReason = monthData?.blocked_dates?.[dateStr] || null;
      const isBlocked = !!blockReason || isWeekend;
      const effectiveReason = blockReason || (isWeekend ? 'Weekend — Closed' : null);

      // Color logic
      let bgClass, textClass, borderClass, hoverClass;
      if (isSelected) {
        bgClass = 'bg-amber-500';
        textClass = 'text-white';
        borderClass = 'border-amber-600';
        hoverClass = '';
      } else if (isBlocked) {
        bgClass = isDarkMode ? 'bg-red-900/40' : 'bg-red-100';
        textClass = isDarkMode ? 'text-red-400' : 'text-red-600';
        borderClass = isDarkMode ? 'border-red-700/50' : 'border-red-300';
        hoverClass = isDarkMode ? 'hover:bg-red-900/60' : 'hover:bg-red-200';
      } else if (count > 0) {
        bgClass = isDarkMode ? 'bg-emerald-900/40' : 'bg-emerald-50';
        textClass = isDarkMode ? 'text-emerald-300' : 'text-emerald-700';
        borderClass = isDarkMode ? 'border-emerald-700/50' : 'border-emerald-300';
        hoverClass = isDarkMode ? 'hover:bg-emerald-900/60' : 'hover:bg-emerald-100';
      } else {
        bgClass = isDarkMode ? 'bg-gray-800' : 'bg-gray-100';
        textClass = isDarkMode ? 'text-gray-400' : 'text-gray-500';
        borderClass = isDarkMode ? 'border-gray-700' : 'border-gray-200';
        hoverClass = isDarkMode ? 'hover:bg-gray-700' : 'hover:bg-gray-200';
      }

      // Today ring
      const todayRing = isToday && !isSelected
        ? isDarkMode
          ? 'ring-2 ring-amber-400 ring-offset-1 ring-offset-gray-900'
          : 'ring-2 ring-amber-500 ring-offset-1 ring-offset-white'
        : '';

      // Build tooltip content
      let tooltipContent = '';
      if (isBlocked) {
        tooltipContent = `🚫 ${effectiveReason}`;
        if (count > 0) tooltipContent += `\n📋 ${count} appointment${count > 1 ? 's' : ''}`;
      } else if (count > 0) {
        const parts = [`📋 ${count} appointment${count > 1 ? 's' : ''}`];
        if (statuses.pending) parts.push(`⏳ ${statuses.pending} pending`);
        if (statuses.approved) parts.push(`✅ ${statuses.approved} approved`);
        if (statuses.completed) parts.push(`✔️ ${statuses.completed} completed`);
        if (statuses.declined) parts.push(`❌ ${statuses.declined} declined`);
        if (statuses.cancelled) parts.push(`🚫 ${statuses.cancelled} cancelled`);
        tooltipContent = parts.join('\n');
      } else {
        tooltipContent = 'No appointments';
      }

      const isFocused = focusedDay === day;

      cells.push(
        <button
          key={day}
          ref={(el) => { dayButtonRefs.current[day] = el; }}
          type="button"
          tabIndex={isFocused ? 0 : -1}
          onClick={() => handleDayClick(dateStr, isBlocked, effectiveReason)}
          onMouseEnter={(e) => showTooltip(e, dateStr, tooltipContent)}
          onMouseLeave={hideTooltip}
          onFocus={(e) => showTooltip(e, dateStr, tooltipContent)}
          onBlur={hideTooltip}
          aria-label={`${dateStr}${isToday ? ' (today)' : ''}${isBlocked ? ' — blocked' : ''}${count > 0 ? `, ${count} appointments` : ''}`}
          className={`
            relative aspect-square flex flex-col items-center justify-center rounded-lg border text-sm font-medium
            transition-all duration-150 cursor-pointer select-none
            focus:outline-none focus:ring-2 focus:ring-amber-400
            ${bgClass} ${textClass} ${borderClass} ${hoverClass} ${todayRing}
          `}
        >
          <span className="text-sm leading-none">{day}</span>
          {/* Appointment count badge */}
          {count > 0 && !isSelected && (
            <span
              className={`absolute -top-1 -right-1 min-w-[18px] h-[18px] flex items-center justify-center rounded-full text-[10px] font-bold leading-none px-1 ${
                isDarkMode
                  ? 'bg-amber-500 text-gray-900'
                  : 'bg-amber-500 text-white'
              }`}
            >
              {count}
            </span>
          )}
          {/* Blocked indicator dot */}
          {isBlocked && !isWeekend && (
            <span className="absolute bottom-0.5 left-1/2 -translate-x-1/2 w-1.5 h-1.5 rounded-full bg-red-500" />
          )}
        </button>,
      );
    }

    return cells;
  };

  if (!isOpen) return null;

  const monthLabel = viewDate.toLocaleDateString('en-US', {
    month: 'long',
    year: 'numeric',
  });

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4" role="dialog" aria-modal="true" aria-label="Appointment calendar">
      {/* Backdrop */}
      <div
        className={`absolute inset-0 ${isDarkMode ? 'bg-black/70' : 'bg-black/20'} backdrop-blur-sm`}
        onClick={onClose}
      />

      {/* Modal panel */}
      <div
        ref={modalRef}
        className={`relative w-full max-w-lg rounded-2xl p-5 shadow-2xl transform transition-all overflow-hidden ${
          isDarkMode
            ? 'bg-gray-900 border border-amber-500/20'
            : 'bg-white border border-gray-200'
        }`}
        style={{ maxHeight: '90vh', overflowY: 'auto' }}
      >
        {/* Header */}
        <div className="flex items-center justify-between mb-4">
          <div className="flex items-center gap-2">
            <CalendarDaysIcon className={`h-5 w-5 ${isDarkMode ? 'text-amber-400' : 'text-amber-600'}`} />
            <h3 className={`text-lg font-semibold ${isDarkMode ? 'text-amber-50' : 'text-gray-900'}`}>
              Filter by Date
            </h3>
          </div>
          <button
            type="button"
            onClick={onClose}
            aria-label="Close calendar"
            className={`p-1.5 rounded-lg transition-colors ${
              isDarkMode ? 'hover:bg-gray-800 text-gray-400' : 'hover:bg-gray-100 text-gray-500'
            }`}
          >
            <XMarkIcon className="h-5 w-5" />
          </button>
        </div>

        {/* Quick actions */}
        <div className="flex flex-wrap gap-2 mb-4">
          <button
            type="button"
            onClick={handleSelectToday}
            className={`px-3 py-1.5 rounded-lg text-xs font-semibold transition-all border ${
              isDarkMode
                ? 'border-amber-500/40 text-amber-300 hover:bg-amber-500/10'
                : 'border-amber-400 text-amber-700 hover:bg-amber-50'
            }`}
          >
            📅 Today
          </button>
          <button
            type="button"
            onClick={handleSelectNext7Days}
            className={`px-3 py-1.5 rounded-lg text-xs font-semibold transition-all border ${
              isDarkMode
                ? 'border-blue-500/40 text-blue-300 hover:bg-blue-500/10'
                : 'border-blue-400 text-blue-700 hover:bg-blue-50'
            }`}
          >
            📆 Next 7 Days
          </button>
          <button
            type="button"
            onClick={handleViewAll}
            className={`px-3 py-1.5 rounded-lg text-xs font-semibold transition-all border ${
              isDarkMode
                ? 'border-gray-600 text-gray-300 hover:bg-gray-800'
                : 'border-gray-300 text-gray-600 hover:bg-gray-100'
            }`}
          >
            📋 View All
          </button>
          <button
            type="button"
            onClick={handleResetToToday}
            className={`px-3 py-1.5 rounded-lg text-xs font-semibold transition-all border ${
              isDarkMode
                ? 'border-emerald-500/40 text-emerald-300 hover:bg-emerald-500/10'
                : 'border-emerald-400 text-emerald-700 hover:bg-emerald-50'
            }`}
          >
            🔄 Reset to Today
          </button>
        </div>

        {/* Month navigation */}
        <div className="flex items-center justify-between mb-3">
          <button
            type="button"
            onClick={goToPrevMonth}
            aria-label="Previous month"
            className={`p-2 rounded-lg transition-colors ${
              isDarkMode ? 'hover:bg-gray-800 text-gray-300' : 'hover:bg-gray-100 text-gray-600'
            }`}
          >
            <ChevronLeftIcon className="h-5 w-5" />
          </button>
          <button
            type="button"
            onClick={goToToday}
            className={`text-sm font-semibold px-3 py-1 rounded-lg transition-colors ${
              isDarkMode ? 'text-amber-200 hover:bg-gray-800' : 'text-amber-700 hover:bg-gray-100'
            }`}
            title="Jump to current month"
          >
            {monthLabel}
          </button>
          <button
            type="button"
            onClick={goToNextMonth}
            aria-label="Next month"
            className={`p-2 rounded-lg transition-colors ${
              isDarkMode ? 'hover:bg-gray-800 text-gray-300' : 'hover:bg-gray-100 text-gray-600'
            }`}
          >
            <ChevronRightIcon className="h-5 w-5" />
          </button>
        </div>

        {/* Loading / Error */}
        {loading && (
          <div className="flex items-center justify-center py-8">
            <ArrowPathIcon className={`h-5 w-5 animate-spin ${isDarkMode ? 'text-amber-400' : 'text-amber-600'}`} />
            <span className={`ml-2 text-sm ${isDarkMode ? 'text-gray-400' : 'text-gray-500'}`}>Loading…</span>
          </div>
        )}
        {error && !loading && (
          <div className={`p-3 rounded-lg mb-3 text-sm flex items-start gap-2 ${
            isDarkMode ? 'bg-red-900/30 text-red-300 border border-red-700/40' : 'bg-red-50 text-red-700 border border-red-200'
          }`}>
            <InformationCircleIcon className="h-5 w-5 flex-shrink-0 mt-0.5" />
            <div>
              <p>{error}</p>
              <button
                type="button"
                onClick={() => fetchMonthData(viewDate.getFullYear(), viewDate.getMonth())}
                className="underline mt-1 text-xs"
              >
                Retry
              </button>
            </div>
          </div>
        )}

        {/* Calendar grid */}
        {!loading && (
          <div
            ref={calendarGridRef}
            onKeyDown={handleCalendarKeyDown}
            role="grid"
            aria-label={`Calendar for ${monthLabel}`}
          >
            {/* Day headers */}
            <div className="grid grid-cols-7 gap-1.5 mb-1.5" role="row">
              {DAY_NAMES.map((name) => (
                <div
                  key={name}
                  role="columnheader"
                  className={`text-center text-xs font-semibold py-1.5 ${
                    isDarkMode ? 'text-gray-500' : 'text-gray-400'
                  }`}
                >
                  {name}
                </div>
              ))}
            </div>

            {/* Day cells */}
            <div className="grid grid-cols-7 gap-1.5">
              {renderCalendar()}
            </div>
          </div>
        )}

        {/* Tooltip */}
        {tooltip && (
          <div
            className={`absolute z-10 px-3 py-2 rounded-lg text-xs font-medium shadow-lg pointer-events-none whitespace-pre-line max-w-[200px] ${
              isDarkMode
                ? 'bg-gray-800 text-gray-100 border border-gray-700'
                : 'bg-white text-gray-800 border border-gray-200 shadow-md'
            }`}
            style={{
              left: `${tooltip.x}px`,
              top: `${tooltip.y}px`,
              transform: 'translate(-50%, -100%)',
            }}
          >
            {tooltip.content}
          </div>
        )}

        {/* Legend */}
        <div className={`mt-4 pt-3 border-t ${isDarkMode ? 'border-gray-700' : 'border-gray-200'}`}>
          <div className="grid grid-cols-2 sm:grid-cols-4 gap-2 text-xs">
            <div className="flex items-center gap-1.5">
              <span className={`w-3 h-3 rounded ${isDarkMode ? 'bg-emerald-900/60 border border-emerald-700/50' : 'bg-emerald-50 border border-emerald-300'}`} />
              <span className={isDarkMode ? 'text-gray-400' : 'text-gray-500'}>Has appointments</span>
            </div>
            <div className="flex items-center gap-1.5">
              <span className={`w-3 h-3 rounded ${isDarkMode ? 'bg-red-900/60 border border-red-700/50' : 'bg-red-100 border border-red-300'}`} />
              <span className={isDarkMode ? 'text-gray-400' : 'text-gray-500'}>Blocked / Weekend</span>
            </div>
            <div className="flex items-center gap-1.5">
              <span className={`w-3 h-3 rounded ${isDarkMode ? 'bg-gray-800 border border-gray-700' : 'bg-gray-100 border border-gray-200'}`} />
              <span className={isDarkMode ? 'text-gray-400' : 'text-gray-500'}>No appointments</span>
            </div>
            <div className="flex items-center gap-1.5">
              <span className="w-3 h-3 rounded bg-amber-500" />
              <span className={isDarkMode ? 'text-gray-400' : 'text-gray-500'}>Selected</span>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
};

export default AppointmentCalendarModal;
