import { useState, useEffect, useCallback } from 'react';
import { useAuth } from '../context/AuthContext';
import { useTheme } from '../context/ThemeContext';
import { useApi } from '../hooks/useApi';
import useRealtimeUpdates from '../hooks/useRealtimeUpdates';
import { Link, useNavigate, useLocation } from 'react-router-dom';
import BottomNav from '../components/BottomNav';
import ProfilePage from './ProfilePage';
import axios from 'axios';
import TimePicker from '../components/TimePicker';
import ActionLogViewer from '../components/ActionLogViewer';
import MessageCenter from './MessageCenter';
import UserFeedback from '../components/user/UserFeedback';
import { formatServiceName, formatTime12Hour } from '../utils/format';
import { 
  HomeIcon,
  CalendarIcon, 
  ChatBubbleLeftRightIcon,
  UserIcon,
  DocumentTextIcon,
  ClockIcon,
  CheckCircleIcon,
  XCircleIcon,
  PlusIcon,
  PencilIcon,
  TrashIcon,
  EyeIcon,
  ExclamationTriangleIcon,
  InformationCircleIcon,
  ArrowPathIcon,
  PhoneIcon,
  EnvelopeIcon,
  MapPinIcon,
  BuildingLibraryIcon,
  DocumentChartBarIcon,
  Cog6ToothIcon,
  SunIcon,
  MoonIcon,
  XMarkIcon,
  ChevronDownIcon,
  MagnifyingGlassIcon,
  CalendarDaysIcon,
  KeyIcon,
  Bars3Icon,
  CurrencyDollarIcon,
  ArrowLeftIcon,
  StarIcon
} from '@heroicons/react/24/outline';

// Enhanced Status Badge Component
const StatusBadge = ({ status }) => {
  const statusConfig = {
    pending: {
      bgColor: 'rgb(254, 243, 199)',
      textColor: 'rgb(120, 53, 15)',
      borderColor: 'rgb(253, 224, 71)',
      darkBg: 'rgb(91, 64, 22)',
      darkText: 'rgb(254, 215, 170)',
      darkBorder: 'rgb(217, 119, 6)'
    },
    approved: {
      bgColor: 'rgb(219, 234, 254)',
      textColor: 'rgb(30, 58, 138)',
      borderColor: 'rgb(96, 165, 250)',
      darkBg: 'rgb(30, 58, 138)',
      darkText: 'rgb(191, 219, 254)',
      darkBorder: 'rgb(59, 130, 246)'
    },
    completed: {
      bgColor: 'rgb(220, 252, 231)',
      textColor: 'rgb(20, 83, 45)',
      borderColor: 'rgb(134, 239, 172)',
      darkBg: 'rgb(20, 83, 45)',
      darkText: 'rgb(187, 247, 208)',
      darkBorder: 'rgb(52, 211, 153)'
    },
    cancelled: {
      bgColor: 'rgb(254, 226, 226)',
      textColor: 'rgb(127, 29, 29)',
      borderColor: 'rgb(252, 86, 86)',
      darkBg: 'rgb(127, 29, 29)',
      darkText: 'rgb(254, 202, 202)',
      darkBorder: 'rgb(239, 68, 68)'
    },
    declined: {
      bgColor: 'rgb(254, 226, 226)',
      textColor: 'rgb(127, 29, 29)',
      borderColor: 'rgb(252, 86, 86)',
      darkBg: 'rgb(127, 29, 29)',
      darkText: 'rgb(254, 202, 202)',
      darkBorder: 'rgb(239, 68, 68)'
    }
  };
  
  const config = statusConfig[status] || {
    bgColor: 'rgb(226, 232, 240)',
    textColor: 'rgb(15, 23, 42)',
    borderColor: 'rgb(148, 163, 184)',
    darkBg: 'rgb(51, 65, 85)',
    darkText: 'rgb(241, 245, 249)',
    darkBorder: 'rgb(71, 85, 105)'
  };
  
  const isDark = document.documentElement.classList.contains('dark');
  
  return (
    <span 
      className="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold border"
      style={{
        backgroundColor: isDark ? config.darkBg : config.bgColor,
        color: isDark ? config.darkText : config.textColor,
        borderColor: isDark ? config.darkBorder : config.borderColor,
        boxShadow: isDark ? `0 4px 6px rgba(0, 0, 0, 0.1)` : `0 4px 6px rgba(0, 0, 0, 0.05)`
      }}
    >
      {status && status.length > 0 ? (
        <>
          {status === 'pending' && <ClockIcon className="w-3 h-3 mr-1" />}
          {(status === 'approved' || status === 'completed') && <CheckCircleIcon className="w-3 h-3 mr-1" />}
          {(status === 'cancelled' || status === 'declined') && <XCircleIcon className="w-3 h-3 mr-1" />}
          {!['pending', 'approved', 'completed', 'cancelled', 'declined'].includes(status) && <ClockIcon className="w-3 h-3 mr-1" />}
          {status.charAt(0).toUpperCase() + status.slice(1)}
        </>
      ) : (
        <>
          <ClockIcon className="w-3 h-3 mr-1" />
          Unknown
        </>
      )}
    </span>
  );
};

// Enhanced Service Type Dropdown with Search
const ServiceTypeDropdown = ({ 
  value, 
  onChange, 
  options, 
  error, 
  onOtherChange,
  otherValue,
  disabled = false
}) => {
  const [isOpen, setIsOpen] = useState(false);
  const [searchTerm, setSearchTerm] = useState('');
  const [showOtherInput, setShowOtherInput] = useState(value === 'other');

  const filteredOptions = options.filter(option => 
    option.label.toLowerCase().includes(searchTerm.toLowerCase())
  );

  const handleSelect = (optionValue, optionLabel) => {
    if (disabled) return;
    if (optionValue === 'other') {
      setShowOtherInput(true);
      onChange('other');
    } else {
      setShowOtherInput(false);
      onChange(optionValue);
      setIsOpen(false);
      setSearchTerm('');
    }
  };

  const handleOtherInputChange = (e) => {
    if (disabled) return;
    onOtherChange(e.target.value);
  };

  return (
    <div className="relative">
      <label className="block text-xs font-medium text-amber-50 mb-1">
        Service Type *
      </label>
      
      {/* Dropdown Trigger */}
      <button
        type="button"
        disabled={disabled}
        onClick={() => !disabled && setIsOpen(!isOpen)}
        className={`w-full px-3 py-2 bg-gray-800 border rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-500 text-sm text-white text-left flex justify-between items-center ${
          disabled ? 'opacity-50 cursor-not-allowed bg-gray-900' : ''
        } ${
          error ? 'border-red-500' : 'border-gray-600 focus:border-amber-500'
        }`}
      >
        <div className="flex flex-col gap-0.5">
          <span className={!value ? 'text-gray-400' : 'text-white'}>
            {value ? options.find(opt => opt.value === value)?.label || 'Other (Custom)' : 'Select service type...'}
          </span>
          {value && options.find(opt => opt.value === value)?.price && (
            <span className="text-amber-400/70 text-xs">
              ${parseFloat(options.find(opt => opt.value === value).price).toFixed(2)}
            </span>
          )}
        </div>
        <ChevronDownIcon className={`h-4 w-4 text-amber-400 flex-shrink-0 ${isOpen ? 'rotate-180' : ''}`} />
      </button>

      {/* Dropdown Menu */}
      {isOpen && !disabled && (
        <div className="absolute z-50 w-full mt-1 bg-gray-800 border border-amber-500/30 rounded-lg shadow-lg shadow-amber-500/10 max-h-60 overflow-y-auto">
          {/* Search Input */}
          <div className="p-2 border-b border-gray-600 sticky top-0 bg-gray-800">
            <div className="relative">
              <MagnifyingGlassIcon className="absolute left-2 top-1/2 transform -translate-y-1/2 h-3 w-3 text-amber-400" />
              <input
                type="text"
                placeholder="Search services..."
                value={searchTerm}
                onChange={(e) => setSearchTerm(e.target.value)}
                className="w-full pl-7 pr-3 py-1.5 bg-gray-700 border border-gray-600 rounded text-xs text-white placeholder-gray-400 focus:outline-none focus:ring-1 focus:ring-amber-500"
                autoFocus
              />
            </div>
          </div>

          {/* Options List */}
          <div className="py-1">
            {filteredOptions.map((option) => (
              <button
                key={option.value}
                type="button"
                onClick={() => handleSelect(option.value, option.label)}
                className="w-full px-3 py-2 text-left text-xs text-amber-50 hover:bg-amber-500/10 hover:text-amber-300 flex items-center justify-between"
              >
                <div className="flex flex-col gap-0.5">
                  <span>{option.label}</span>
                  {option.price && (
                    <span className="text-amber-400/70 text-xs">${parseFloat(option.price).toFixed(2)}</span>
                  )}
                </div>
                {option.value === value && (
                  <CheckCircleIcon className="h-3 w-3 text-amber-400" />
                )}
              </button>
            ))}
            
            {/* Always show "Other" option */}
            <button
              type="button"
              onClick={() => handleSelect('other', 'Other (Specify)')}
              className="w-full px-3 py-2 text-left text-xs text-amber-50 hover:bg-amber-500/10 hover:text-amber-300 flex items-center justify-between border-t border-gray-600"
            >
              <span>Other (Specify)</span>
              {value === 'other' && (
                <CheckCircleIcon className="h-3 w-3 text-amber-400" />
              )}
            </button>
          </div>
        </div>
      )}

      {/* Other Service Input */}
      {showOtherInput && (
        <div className="mt-2">
          <input
            type="text"
            disabled={disabled}
            placeholder="Please specify the service type..."
            value={otherValue}
            onChange={handleOtherInputChange}
            className={`w-full px-3 py-2 bg-gray-800 border border-amber-500/30 rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-500 text-sm text-white placeholder-gray-400 ${
              disabled ? 'opacity-50 cursor-not-allowed bg-gray-900' : ''
            }`}
          />
        </div>
      )}

      {error && (
        <p className="text-red-400 text-xs mt-1 flex items-center">
          <ExclamationTriangleIcon className="h-3 w-3 mr-1" />
          {error}
        </p>
      )}
    </div>
  );
};

// Enhanced Calendar Component
const EnhancedCalendar = ({ value, onChange, error, disabled = false, dailyLimitInfo = {} }) => {
  const [isOpen, setIsOpen] = useState(false);
  const [currentMonth, setCurrentMonth] = useState(new Date());
  const [selectedDate, setSelectedDate] = useState(value ? new Date(value) : null);
  const [unavailableDates, setUnavailableDates] = useState([]);

  const today = new Date();
  const minDate = new Date(today);
  minDate.setDate(today.getDate());

  const getDaysInMonth = (date) => {
    const year = date.getFullYear();
    const month = date.getMonth();
    return new Date(year, month + 1, 0).getDate();
  };

  const getFirstDayOfMonth = (date) => {
    const year = date.getFullYear();
    const month = date.getMonth();
    return new Date(year, month, 1).getDay();
  };

  const navigateMonth = (direction) => {
    setCurrentMonth(prev => {
      const newMonth = new Date(prev);
      newMonth.setMonth(prev.getMonth() + direction);
      return newMonth;
    });
  };

  const handleDateSelect = (date) => {
    if (disabled) return;
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    const dateString = `${year}-${month}-${day}`;
    setSelectedDate(date);
    onChange(dateString);
    setIsOpen(false);
  };

  const isDateDisabled = (date) => {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    const dateStr = `${year}-${month}-${day}`;

    // Past dates disabled
    if (date < minDate) return true;

    // Weekends disabled
    const dayOfWeek = date.getDay();
    if (dayOfWeek === 0 || dayOfWeek === 6) return true;

    // Admin-set unavailable/blackout dates
    if (unavailableDates.some(u => {
      const uDate = (u.date || '').toString().split('T')[0];
      if (uDate && uDate === dateStr) return true;
      // recurring blackout entries may include recurring_days array
      if (u.is_recurring && u.recurring_days) {
        const dayName = ['sunday','monday','tuesday','wednesday','thursday','friday','saturday'][dayOfWeek];
        if (u.recurring_days.includes(dayName)) return true;
      }
      // legacy types: some entries may have type === 'weekend' or 'blackout'
      if (u.type === 'weekend' && (dayOfWeek === 0 || dayOfWeek === 6)) return true;
      return false;
    })) return true;

    return false;
  };

  // Load unavailable dates from admin endpoint
  useEffect(() => {
    let mounted = true;
    const loadUnavailable = async () => {
      try {
        const res = await fetch('/api/unavailable-dates');
        if (!mounted) return;
        if (res.ok) {
          const data = await res.json();
          setUnavailableDates(data.data || data.unavailable_dates || []);
        }
      } catch (err) {
        console.error('Failed to load unavailable dates:', err);
      }
    };
    loadUnavailable();
    return () => { mounted = false; };
  }, []);

  const renderCalendarGrid = () => {
    const daysInMonth = getDaysInMonth(currentMonth);
    const firstDay = getFirstDayOfMonth(currentMonth);
    const days = [];

    // Previous month days
    const prevMonth = new Date(currentMonth);
    prevMonth.setMonth(prevMonth.getMonth() - 1);
    const prevMonthDays = getDaysInMonth(prevMonth);

    for (let i = firstDay - 1; i >= 0; i--) {
      const date = new Date(prevMonth);
      date.setDate(prevMonthDays - i);
      days.push(
        <div key={`prev-${i}`} className="p-1">
          <div className="h-8 flex items-center justify-center text-xs text-gray-600 bg-gray-800/30 rounded border border-gray-700">
            {prevMonthDays - i}
          </div>
        </div>
      );
    }

    // Current month days
    for (let i = 1; i <= daysInMonth; i++) {
      const date = new Date(currentMonth);
      date.setDate(i);
      const isDisabled = isDateDisabled(date);
      const isSelected = selectedDate && 
        date.getDate() === selectedDate.getDate() &&
        date.getMonth() === selectedDate.getMonth() &&
        date.getFullYear() === selectedDate.getFullYear();
      const isToday = date.toDateString() === today.toDateString();

      days.push(
        <div key={`current-${i}`} className="p-1">
          <button
            type="button"
            onClick={() => !isDisabled && handleDateSelect(date)}
            disabled={isDisabled}
            className={`w-full h-8 flex items-center justify-center text-xs rounded border ${
              isDisabled
                ? 'text-gray-600 bg-gray-800/30 border-gray-700 cursor-not-allowed'
                : isSelected
                ? 'bg-amber-500 text-white border-amber-500 shadow-lg shadow-amber-500/25'
                : isToday
                ? 'bg-amber-500/20 text-amber-300 border-amber-500/30 hover:bg-amber-500/30'
                : 'text-amber-50 bg-gray-800/50 border-gray-600 hover:bg-amber-500/10 hover:border-amber-500/40'
            }`}
          >
            {i}
          </button>
        </div>
      );
    }

    // Next month days
    const totalCells = 42; // 6 weeks
    const nextMonth = new Date(currentMonth);
    nextMonth.setMonth(nextMonth.getMonth() + 1);
    
    for (let i = 1; days.length < totalCells; i++) {
      days.push(
        <div key={`next-${i}`} className="p-1">
          <div className="h-8 flex items-center justify-center text-xs text-gray-600 bg-gray-800/30 rounded border border-gray-700">
            {i}
          </div>
        </div>
      );
    }

    return days;
  };

  return (
    <div className="relative">
      <label className="block text-xs font-medium text-amber-50 mb-1">
        Preferred Date *
      </label>
      
      <button
        type="button"
        disabled={disabled}
        onClick={() => !disabled && setIsOpen(!isOpen)}
        className={`w-full px-3 py-2 bg-gray-800 border rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-500 text-sm text-white text-left flex justify-between items-center ${
          disabled ? 'opacity-50 cursor-not-allowed bg-gray-900' : ''
        } ${
          error ? 'border-red-500' : 'border-gray-600 focus:border-amber-500'
        }`}
      >
        <span className={!value ? 'text-gray-400' : 'text-white'}>
          {value ? new Date(value).toLocaleDateString('en-US', { 
            weekday: 'short', 
            year: 'numeric', 
            month: 'short', 
            day: 'numeric' 
          }) : 'Select appointment date...'}
        </span>
        <CalendarDaysIcon className="h-4 w-4 text-amber-400" />
      </button>

      {isOpen && !disabled && (
        <div className="absolute z-50 w-full mt-1 bg-gray-800 border border-amber-500/30 rounded-lg shadow-lg shadow-amber-500/10 p-3">
          {/* Daily Limit Status */}
          {dailyLimitInfo.limit && (
            <div className={`rounded-lg border p-4 flex items-start gap-3 ${
              dailyLimitInfo.hasReachedLimit
                ? 'bg-red-900/20 border-red-500/30'
                : 'bg-blue-900/20 border-blue-500/30'
            }`}>
              {dailyLimitInfo.hasReachedLimit ? (
                <>
                  <InformationCircleIcon className="h-5 w-5 flex-shrink-0 text-blue-400 mt-0.5" />
                  <div>
                    <h3 className="font-semibold text-blue-400">📅 Daily Booking Limit Reached</h3>
                    <p className="text-sm text-blue-300/80 mt-1">
                      {dailyLimitInfo.message || `You have reached your daily booking limit of ${dailyLimitInfo.limit} appointments. You can book again tomorrow.`}
                    </p>
                    {dailyLimitInfo.bookingsToday?.length > 0 && (
                      <div className="mt-3 text-xs text-blue-300/70">
                        <p className="font-medium mb-2">Your appointments today:</p>
                        <ul className="space-y-1 ml-2">
                          {dailyLimitInfo.bookingsToday.map((booking, idx) => (
                            <li key={idx} className="flex items-center gap-2">
                              <span>•</span>
                              <span>{formatTime12Hour(booking.time)} - {booking.service}</span>
                            </li>
                          ))}
                        </ul>
                      </div>
                    )}
                  </div>
                </>
              ) : (
                <>
                  <CheckCircleIcon className="h-5 w-5 flex-shrink-0 text-blue-400 mt-0.5" />
                  <div>
                    <h3 className="font-semibold text-blue-400">Appointment Slots Available</h3>
                    <p className="text-sm text-blue-300/80 mt-1">
                      You have {dailyLimitInfo.remaining} of {dailyLimitInfo.limit} daily appointment slots available.
                    </p>
                  </div>
                </>
              )}
            </div>
              )}

              {/* Calendar Grid */}
              <div className="mt-3">
                <div className="grid grid-cols-7 gap-1">{renderCalendarGrid()}</div>

                {/* Quick-select buttons */}
                <div className="mt-3 flex gap-2 justify-end">
                  <button
                    type="button"
                    onClick={() => handleDateSelect(today)}
                    className="text-xs text-amber-400 hover:text-amber-300 hover:bg-amber-500/10 px-2 py-1 rounded border border-amber-500/30"
                  >
                    Today
                  </button>
                  <button
                    type="button"
                    onClick={() => {
                      const tomorrow = new Date(today);
                      tomorrow.setDate(tomorrow.getDate() + 1);
                      handleDateSelect(tomorrow);
                    }}
                    className="text-xs text-amber-400 hover:text-amber-300 hover:bg-amber-500/10 px-2 py-1 rounded border border-amber-500/30"
                  >
                    Tomorrow
                  </button>
                </div>
              </div>
            </div>
          )}

      {error && (
        <p className="text-red-400 text-xs mt-1 flex items-center">
          <ExclamationTriangleIcon className="h-3 w-3 mr-1" />
          {error}
        </p>
      )}
    </div>
  );
};

// Appointment Detail Modal
const AppointmentDetailModal = ({ isOpen, onClose, appointment }) => {
  if (!isOpen || !appointment) return null;

  return (
    <div className="fixed inset-0 bg-black bg-opacity-70 flex items-center justify-center z-50 p-4 animate-fadeIn">
      <div className="bg-gray-900 border border-amber-500/30 rounded-lg shadow-xl w-full max-w-2xl max-h-[90vh] overflow-y-auto transform animate-scaleIn">
        <div className="flex justify-between items-center p-4 border-b border-gray-700 sticky top-0 bg-gray-900">
          <div className="flex items-center">
            <DocumentTextIcon className="h-5 w-5 text-amber-400 mr-2" />
            <h3 className="text-sm font-semibold text-amber-50">
              Appointment Details
            </h3>
          </div>
          <button 
            onClick={onClose} 
            className="text-gray-400 hover:text-amber-400 transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-amber-500 rounded p-1"
          >
            <XMarkIcon className="h-4 w-4" />
          </button>
        </div>
        
        <div className="p-4 space-y-4">
          {/* Appointment Header */}
          <div className="flex items-center justify-between p-3 bg-gray-800/50 rounded-lg border border-gray-600">
            <div className="flex items-center space-x-3">
              <div className="w-10 h-10 bg-amber-500/20 rounded-full flex items-center justify-center border border-amber-500/30">
                <DocumentTextIcon className="h-5 w-5 text-amber-400" />
              </div>
              <div>
                <h4 className="text-sm font-bold text-amber-50">{formatServiceName(appointment)}</h4>
                <StatusBadge status={appointment.status} />
              </div>
            </div>
            <div className="text-right">
              <div className="text-sm font-semibold text-amber-50">
                {new Date(appointment.appointment_date).toLocaleDateString()}
              </div>
              <div className="text-xs text-amber-400/70">{appointment.appointment_time}</div>
            </div>
          </div>

          {/* Appointment Details Grid */}
          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div className="space-y-3">
              <div className="p-3 bg-gray-800/30 rounded-lg border border-gray-600">
                <label className="text-xs font-medium text-gray-400 mb-2 block">Service Information</label>
                <div className="space-y-2">
                  <div>
                    <span className="text-xs text-gray-500">Service Type</span>
                    <p className="text-amber-50 font-medium text-sm">{formatServiceName(appointment)}</p>
                  </div>
                  {appointment.service?.price && (
                    <div>
                      <span className="text-xs text-gray-500">Price</span>
                      <p className="text-amber-300 font-semibold text-sm">${parseFloat(appointment.service.price).toFixed(2)}</p>
                    </div>
                  )}
                </div>
              </div>

              <div className="p-3 bg-gray-800/30 rounded-lg border border-gray-600">
                <label className="text-xs font-medium text-gray-400 mb-2 block">Assignee</label>
                {appointment.staff ? (
                  <div className="flex items-center space-x-2">
                    <div className="w-8 h-8 bg-blue-500 rounded-full flex items-center justify-center text-white text-xs font-bold shadow">
                      {appointment.staff.first_name?.charAt(0)}{appointment.staff.last_name?.charAt(0)}
                    </div>
                    <div>
                      <p className="text-amber-50 font-medium text-sm">
                        {appointment.staff.first_name} {appointment.staff.last_name}
                      </p>
                      <p className="text-xs text-amber-400/70 capitalize">{appointment.staff.role}</p>
                    </div>
                  </div>
                ) : (
                  <p className="text-amber-400/70 text-sm">No assignee yet</p>
                )}
              </div>
            </div>

            <div className="space-y-3">
              <div className="p-3 bg-gray-800/30 rounded-lg border border-gray-600">
                <label className="text-xs font-medium text-gray-400 mb-2 block">Additional Information</label>
                <div className="space-y-2">
                  {appointment.notes && (
                    <div>
                      <span className="text-xs text-gray-500">Your Notes</span>
                      <p className="text-amber-50 text-sm">{appointment.notes}</p>
                    </div>
                  )}
                  {appointment.staff_notes && (
                    <div>
                      <span className="text-xs text-gray-500">Internal Notes</span>
                      <p className="text-amber-50 text-sm">{appointment.staff_notes}</p>
                    </div>
                  )}
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
};

// Confirmation Modal
const ConfirmationModal = ({ isOpen, onClose, onConfirm, title, message, confirmText = "Confirm", type = "primary", loading = false }) => {
  if (!isOpen) return null;

  const buttonColors = {
    danger: "bg-red-600 hover:bg-red-700 focus:ring-red-500",
    primary: "bg-amber-600 hover:bg-amber-700 focus:ring-amber-500",
    warning: "bg-yellow-600 hover:bg-yellow-700 focus:ring-yellow-500",
    success: "bg-green-600 hover:bg-green-700 focus:ring-green-500"
  };

  const icons = {
    danger: ExclamationTriangleIcon,
    warning: ExclamationTriangleIcon,
    primary: CheckCircleIcon,
    success: CheckCircleIcon
  };

  const IconComponent = icons[type];

  return (
    <div className="fixed inset-0 bg-black bg-opacity-70 flex items-center justify-center z-50 p-4 animate-fadeIn">
      <div className="bg-gray-900 border border-amber-500/30 rounded-lg shadow-xl w-full max-w-md transform animate-scaleIn">
        <div className="p-6">
          <div className="flex items-center mb-4">
            <div className={`p-2 rounded-lg ${
              type === 'danger' ? 'bg-red-500/20' : 
              type === 'warning' ? 'bg-yellow-500/20' : 
              type === 'success' ? 'bg-green-500/20' : 
              'bg-amber-500/20'
            }`}>
              <IconComponent className={`h-5 w-5 ${
                type === 'danger' ? 'text-red-400' : 
                type === 'warning' ? 'text-yellow-400' : 
                type === 'success' ? 'text-green-400' : 
                'text-amber-400'
              }`} />
            </div>
            <h3 className="text-base font-bold text-amber-50 ml-3">{title}</h3>
          </div>
          <p className="text-gray-300 text-sm mb-6 leading-relaxed">{message}</p>
          <div className="flex flex-col-reverse sm:flex-row gap-3 justify-end">
            <button
              onClick={onClose}
              disabled={loading}
              className="px-4 py-2.5 border-2 border-amber-500/50 text-amber-400 font-semibold text-sm rounded-lg hover:bg-amber-500/10 hover:border-amber-400 transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-amber-500 focus:ring-offset-2 focus:ring-offset-gray-900 disabled:opacity-50 disabled:cursor-not-allowed"
            >
              Cancel
            </button>
            <button
              onClick={onConfirm}
              disabled={loading}
              className={`px-4 py-2.5 text-white font-semibold text-sm rounded-lg transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-offset-gray-900 disabled:opacity-50 disabled:cursor-not-allowed ${buttonColors[type]}`}
            >
              {loading ? (
                <div className="flex items-center justify-center">
                  <div className="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin mr-2"></div>
                  <span>Processing...</span>
                </div>
              ) : (
                confirmText
              )}
            </button>
          </div>
        </div>
      </div>
    </div>
  );
};

// Settings Modal Component
const SettingsModal = ({ isOpen, onClose, settings, onSettingsChange }) => {
  if (!isOpen) return null;

  return (
    <div className="fixed inset-0 bg-black bg-opacity-70 flex items-center justify-center z-50 p-4 animate-fadeIn">
      <div className="bg-gray-900 border border-amber-500/30 rounded-lg shadow-xl w-full max-w-md max-h-[90vh] overflow-y-auto transform animate-scaleIn">
        <div className="flex justify-between items-center p-4 border-b border-gray-700 sticky top-0 bg-gray-900">
          <div className="flex items-center">
            <Cog6ToothIcon className="h-5 w-5 text-amber-400 mr-2" />
            <h3 className="text-sm font-semibold text-amber-50">Settings</h3>
          </div>
          <button 
            onClick={onClose} 
            className="text-gray-400 hover:text-amber-400 transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-amber-500 rounded p-1"
          >
            <XMarkIcon className="h-4 w-4" />
          </button>
        </div>
        
        <div className="p-4 space-y-4">
          {/* Theme Settings */}
          <div className="space-y-3">
            <h4 className="text-xs font-semibold text-amber-50">Appearance</h4>
            
            <div className="flex items-center justify-between p-3 bg-gray-800/50 rounded-lg border border-gray-600">
              <div className="flex items-center space-x-2">
                {settings.theme === 'dark' ? (
                  <MoonIcon className="h-4 w-4 text-amber-400" />
                ) : (
                  <SunIcon className="h-4 w-4 text-amber-400" />
                )}
                <div>
                  <p className="text-amber-50 font-medium text-sm">Theme</p>
                  <p className="text-xs text-amber-400/70">Choose your preferred theme</p>
                </div>
              </div>
              <div className="flex items-center space-x-2">
                <label className="relative inline-flex items-center cursor-pointer">
                  <input
                    type="checkbox"
                    checked={settings.theme === 'light'}
                    onChange={(e) => onSettingsChange('theme', e.target.checked ? 'light' : 'dark')}
                    className="sr-only peer"
                  />
                  <div className="w-10 h-5 bg-gray-600 rounded-full peer-focus:ring-2 peer-focus:ring-amber-500 peer-checked:bg-amber-600 relative after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border after:border-gray-300 after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:after:translate-x-full"></div>
                </label>

                <select
                  value={settings.theme}
                  onChange={(e) => onSettingsChange('theme', e.target.value)}
                  className="bg-gray-800 border border-gray-600 rounded-lg px-2 py-1 text-amber-50 text-xs focus:outline-none focus:ring-2 focus:ring-amber-500 focus:border-amber-500"
                >
                  <option value="light">Light</option>
                  <option value="dark">Dark</option>
                  <option value="system">System</option>
                </select>
              </div>
            </div>
          </div>

          {/* Notification Settings */}
          <div className="space-y-3">
            <h4 className="text-xs font-semibold text-amber-50">Notifications</h4>
            
            <div className="space-y-2">
              <div className="flex items-center justify-between p-2 bg-gray-800/50 rounded-lg border border-gray-600">
                <div>
                  <p className="text-amber-50 font-medium text-sm">Email Notifications</p>
                  <p className="text-xs text-amber-400/70">Receive email updates about your appointments</p>
                </div>
                <label className="relative inline-flex items-center cursor-pointer">
                  <input
                    type="checkbox"
                    checked={settings.emailNotifications}
                    onChange={(e) => onSettingsChange('emailNotifications', e.target.checked)}
                    className="sr-only peer"
                  />
                  <div className="w-9 h-5 bg-gray-600 peer-focus:outline-none peer-focus:ring-amber-500 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-amber-600"></div>
                </label>
              </div>

              <div className="flex items-center justify-between p-2 bg-gray-800/50 rounded-lg border border-gray-600">
                <div>
                  <p className="text-amber-50 font-medium text-sm">SMS Notifications</p>
                  <p className="text-xs text-amber-400/70">Receive text message reminders</p>
                </div>
                <label className="relative inline-flex items-center cursor-pointer">
                  <input
                    type="checkbox"
                    checked={settings.smsNotifications}
                    onChange={(e) => onSettingsChange('smsNotifications', e.target.checked)}
                    className="sr-only peer"
                  />
                  <div className="w-9 h-5 bg-gray-600 peer-focus:outline-none peer-focus:ring-amber-500 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-amber-600"></div>
                </label>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
};

// Thank You Modal Component
const ThankYouModal = ({ isOpen, onClose, appointment }) => {
  if (!isOpen) return null;

  return (
    <div className="fixed inset-0 bg-black bg-opacity-70 flex items-center justify-center z-50 p-4 animate-fadeIn">
      <div className="bg-gray-900 border border-amber-500/30 rounded-lg shadow-xl w-full max-w-md transform animate-scaleIn">
        <div className="p-4">
          <div className="text-center">
            <div className="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-green-500/20 mb-3 border border-green-500/30">
              <CheckCircleIcon className="h-6 w-6 text-green-400" />
            </div>
            <h3 className="text-sm font-semibold text-amber-50 mb-2">
              Appointment Booked Successfully! 🎉
            </h3>
            
            {appointment && (
              <div className="bg-gray-800/50 rounded-lg p-3 mb-3 border border-gray-600">
                <p className="text-xs text-amber-400/70 mb-1">
                  <strong>Date:</strong> {new Date(appointment.appointment_date).toLocaleDateString()}
                </p>
                <p className="text-xs text-amber-400/70 mb-1">
                  <strong>Time:</strong> {appointment.appointment_time}
                </p>
                <p className="text-xs text-amber-400/70">
                  <strong>Status:</strong> <StatusBadge status="pending" />
                </p>
              </div>
            )}
            
            <p className="text-amber-400/70 text-xs mb-4">
              A confirmation email has been sent to your email address. 
              You will receive another email once your appointment is approved.
            </p>
            
            <button
              onClick={onClose}
              className="w-full px-4 py-2 bg-gradient-to-r from-amber-600 to-amber-700 text-white rounded-lg hover:from-amber-700 hover:to-amber-800 transition-all duration-200 font-medium text-sm focus:outline-none focus:ring-2 focus:ring-amber-500 focus:ring-offset-2 focus:ring-offset-gray-900 shadow border border-amber-500/30"
            >
              Close
            </button>
          </div>
        </div>
      </div>
    </div>
  );
};

// About Us Modal Component
const AboutUsModal = ({ isOpen, onClose, isDarkMode = true }) => {
  if (!isOpen) return null;

  const APP_VERSION = '1.0.0';
  const LAUNCH_DATE = 'January 2024';
  const LOCATION = 'San Francisco, California';
  const DEVELOPER = 'John Christian Fajutagana';

  return (
    <div className="fixed inset-0 flex items-center justify-center z-50 p-4 animate-fadeIn" style={{backgroundColor: isDarkMode ? 'rgba(0,0,0,0.7)' : 'rgba(0,0,0,0.4)'}}>
      <div className={`rounded-lg shadow-xl w-full max-w-md transform animate-scaleIn max-h-[90vh] overflow-y-auto border-2 ${
        isDarkMode 
          ? 'bg-gray-900 border-amber-500/30' 
          : 'bg-white border-amber-400'
      }`}>
        {/* Header */}
        <div className={`flex justify-between items-center p-4 border-b sticky top-0 ${
          isDarkMode 
            ? 'bg-gray-900 border-gray-700' 
            : 'bg-white border-gray-200'
        }`}>
          <div className="flex items-center">
            <InformationCircleIcon className={`h-5 w-5 mr-2 ${isDarkMode ? 'text-amber-400' : 'text-amber-600'}`} />
            <h3 className={`text-sm font-semibold ${isDarkMode ? 'text-amber-50' : 'text-gray-900'}`}>About Us</h3>
          </div>
          <button 
            onClick={onClose} 
            className={`rounded p-1 transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-amber-500 ${
              isDarkMode 
                ? 'text-gray-400 hover:text-amber-400' 
                : 'text-gray-600 hover:text-amber-600'
            }`}
          >
            <XMarkIcon className="h-4 w-4" />
          </button>
        </div>

        {/* Content */}
        <div className={`p-4 space-y-5 ${isDarkMode ? 'text-amber-50' : 'text-gray-900'}`}>
          {/* Company Info */}
          <div>
            <h4 className={`font-semibold mb-2 text-sm ${isDarkMode ? 'text-amber-400' : 'text-amber-600'}`}>
              NotaryPro Services
            </h4>
            <p className={`text-xs leading-relaxed ${isDarkMode ? 'text-amber-100/70' : 'text-gray-700'}`}>
              NotaryPro is your trusted partner for professional notarization services. Founded with a commitment to excellence, we provide fast, reliable, and accessible notarization for all your legal document needs.
            </p>
          </div>

          {/* Company Details */}
          <div className={`p-3 rounded-lg space-y-2 ${isDarkMode ? 'bg-gray-800/50 border border-gray-700' : 'bg-gray-100 border border-gray-300'}`}>
            <div className="text-xs">
              <p className={`mb-2 ${isDarkMode ? 'text-amber-400' : 'text-amber-600'}`}><strong>📍 Location:</strong></p>
              <p className={`ml-4 ${isDarkMode ? 'text-amber-100/70' : 'text-gray-700'}`}>{LOCATION}</p>
            </div>
            <div className="text-xs">
              <p className={`mb-2 ${isDarkMode ? 'text-amber-400' : 'text-amber-600'}`}><strong>🚀 Founded:</strong></p>
              <p className={`ml-4 ${isDarkMode ? 'text-amber-100/70' : 'text-gray-700'}`}>{LAUNCH_DATE}</p>
            </div>
          </div>

          {/* Our Mission */}
          <div>
            <h4 className={`font-semibold mb-2 text-sm ${isDarkMode ? 'text-amber-400' : 'text-amber-600'}`}>
              Our Mission
            </h4>
            <p className={`text-xs leading-relaxed ${isDarkMode ? 'text-amber-100/70' : 'text-gray-700'}`}>
              To make notarization services accessible, convenient, and trustworthy for everyone. We believe in providing exceptional service with integrity and professionalism.
            </p>
          </div>

          {/* What We Offer */}
          <div>
            <h4 className={`font-semibold mb-3 text-sm ${isDarkMode ? 'text-amber-400' : 'text-amber-600'}`}>
              Services
            </h4>
            <ul className={`space-y-2 text-xs ${isDarkMode ? 'text-amber-100/70' : 'text-gray-700'}`}>
              <li className="flex items-start">
                <span className={`mr-2 flex-shrink-0 ${isDarkMode ? 'text-amber-400' : 'text-amber-600'}`}>✓</span>
                <span>Professional Notarization Services</span>
              </li>
              <li className="flex items-start">
                <span className={`mr-2 flex-shrink-0 ${isDarkMode ? 'text-amber-400' : 'text-amber-600'}`}>✓</span>
                <span>Document Verification & Witnessing</span>
              </li>
              <li className="flex items-start">
                <span className={`mr-2 flex-shrink-0 ${isDarkMode ? 'text-amber-400' : 'text-amber-600'}`}>✓</span>
                <span>Certified Notary Public Staff</span>
              </li>
              <li className="flex items-start">
                <span className={`mr-2 flex-shrink-0 ${isDarkMode ? 'text-amber-400' : 'text-amber-600'}`}>✓</span>
                <span>Flexible Scheduling & Mobile Service</span>
              </li>
            </ul>
          </div>

          {/* Why Choose Us */}
          <div>
            <h4 className={`font-semibold mb-3 text-sm ${isDarkMode ? 'text-amber-400' : 'text-amber-600'}`}>
              Why Choose NotaryPro
            </h4>
            <ul className={`space-y-2 text-xs ${isDarkMode ? 'text-amber-100/70' : 'text-gray-700'}`}>
              <li className="flex items-start">
                <span className={`mr-2 flex-shrink-0 ${isDarkMode ? 'text-green-400' : 'text-green-600'}`}>✓</span>
                <span>Licensed & Insured Professionals</span>
              </li>
              <li className="flex items-start">
                <span className={`mr-2 flex-shrink-0 ${isDarkMode ? 'text-green-400' : 'text-green-600'}`}>✓</span>
                <span>Fast & Reliable Service</span>
              </li>
              <li className="flex items-start">
                <span className={`mr-2 flex-shrink-0 ${isDarkMode ? 'text-green-400' : 'text-green-600'}`}>✓</span>
                <span>Competitive Pricing</span>
              </li>
              <li className="flex items-start">
                <span className={`mr-2 flex-shrink-0 ${isDarkMode ? 'text-green-400' : 'text-green-600'}`}>✓</span>
                <span>24/7 Availability</span>
              </li>
            </ul>
          </div>

          {/* Developer & Team */}
          <div className={`p-3 rounded-lg ${isDarkMode ? 'bg-gray-800/50 border border-gray-700' : 'bg-gray-100 border border-gray-300'}`}>
            <h4 className={`font-semibold mb-3 text-sm ${isDarkMode ? 'text-amber-400' : 'text-amber-600'}`}>
              👨‍💻 Development Team
            </h4>
            <div className={`text-xs ${isDarkMode ? 'text-amber-100/70' : 'text-gray-700'}`}>
              <p><strong>Lead Developer:</strong></p>
              <p className={`ml-4 ${isDarkMode ? 'text-amber-400' : 'text-amber-600'}`}>{DEVELOPER}</p>
              <p className={`mt-2 text-xs ${isDarkMode ? 'text-amber-100/60' : 'text-gray-600'}`}>
                Full Stack Developer specializing in modern web technologies and professional services platforms.
              </p>
            </div>
          </div>

          {/* Contact & Support */}
          <div className={`p-3 rounded-lg ${isDarkMode ? 'bg-gray-800/50 border border-gray-700' : 'bg-gray-100 border border-gray-300'}`}>
            <h4 className={`font-semibold mb-2 text-sm ${isDarkMode ? 'text-amber-400' : 'text-amber-600'}`}>
              Get In Touch
            </h4>
            <div className={`space-y-1 text-xs ${isDarkMode ? 'text-amber-100/70' : 'text-gray-700'}`}>
              <p><strong>Email:</strong> support@notarypro.com</p>
              <p><strong>Phone:</strong> 1-800-NOTARY-1</p>
              <p><strong>Hours:</strong> 24/7 Service Available</p>
            </div>
          </div>

          {/* Legal Links */}
          <div className={`p-3 rounded-lg space-y-2 ${isDarkMode ? 'bg-gray-800/50 border border-gray-700' : 'bg-gray-100 border border-gray-300'}`}>
            <button className={`w-full text-left text-xs font-medium px-3 py-2 rounded transition-colors ${
              isDarkMode 
                ? 'hover:bg-amber-500/10 text-amber-400' 
                : 'hover:bg-amber-100 text-amber-600'
            }`}>
              📋 Terms of Service
            </button>
            <button className={`w-full text-left text-xs font-medium px-3 py-2 rounded transition-colors ${
              isDarkMode 
                ? 'hover:bg-amber-500/10 text-amber-400' 
                : 'hover:bg-amber-100 text-amber-600'
            }`}>
              🔒 Privacy Policy
            </button>
            <button className={`w-full text-left text-xs font-medium px-3 py-2 rounded transition-colors ${
              isDarkMode 
                ? 'hover:bg-amber-500/10 text-amber-400' 
                : 'hover:bg-amber-100 text-amber-600'
            }`}>
              💬 Contact Support
            </button>
          </div>

          {/* Version */}
          <div className={`text-center pt-2 border-t ${isDarkMode ? 'border-gray-700 text-gray-500' : 'border-gray-300 text-gray-600'}`}>
            <p className="text-xs">
              Version {APP_VERSION} • © 2024 NotaryPro Services
            </p>
          </div>
        </div>

        {/* Close Button */}
        <div className={`p-4 border-t ${isDarkMode ? 'bg-gray-800/50 border-gray-700' : 'bg-gray-50 border-gray-300'}`}>
          <button
            onClick={onClose}
            className={`w-full px-4 py-2 rounded-lg font-medium text-sm transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-offset-2 ${
              isDarkMode
                ? 'bg-amber-600 hover:bg-amber-700 text-white focus:ring-amber-500 focus:ring-offset-gray-900'
                : 'bg-amber-600 hover:bg-amber-700 text-white focus:ring-amber-500 focus:ring-offset-white'
            }`}
          >
            Close
          </button>
        </div>
      </div>
    </div>
  );
};

const Dashboard = () => {
  const { user, logout } = useAuth();
  const { callApi, loading, error, clearError } = useApi();
  const navigate = useNavigate();
  const location = useLocation();
  
  // Redirect based on user role
  useEffect(() => {
    if (user?.role === 'admin') {
      setRedirecting(true);
      // Use a small timeout to allow React to update state
      const timer = setTimeout(() => {
        navigate('/admin/dashboard', { replace: true });
      }, 100);
      return () => clearTimeout(timer);
    }
    if (user?.role === 'staff') {
      setRedirecting(true);
      // Use a small timeout to allow React to update state
      const timer = setTimeout(() => {
        navigate('/cashier', { replace: true });
      }, 100);
      return () => clearTimeout(timer);
    }
    setRedirecting(false);
  }, [user, navigate]);

  const [redirecting, setRedirecting] = useState(false);
  
  const [activeTab, setActiveTab] = useState('home');
  const [isEditing, setIsEditing] = useState(false);

  const [showSettings, setShowSettings] = useState(false);
  const [showAboutUs, setShowAboutUs] = useState(false);
  const [showLogoutModal, setShowLogoutModal] = useState(false);
  const [isLoggingOut, setIsLoggingOut] = useState(false); // Track logout loading state
  const [showThankYouModal, setShowThankYouModal] = useState(false);
  const [latestAppointment, setLatestAppointment] = useState(null);
  const { isDarkMode, setIsDarkMode } = useTheme(); // Use ThemeContext
  const [showMobileSidebar, setShowMobileSidebar] = useState(false);
  const [profileSection, setProfileSection] = useState('overview');
  const [showProfileMenu, setShowProfileMenu] = useState(false);

  // Sync active tab with URL query `?tab=` so BottomNav can navigate using query params
  useEffect(() => {
    try {
      const params = new URLSearchParams(location.search);
      const tab = params.get('tab');
      if (tab) {
        const normalized = tab.toLowerCase();
        const allowed = ['home','book','appointments','messages','refunds','action-logs','profile','settings'];
        if (allowed.includes(normalized)) {
          setActiveTab(normalized === 'home' ? 'home' : normalized);
          // if navigating to profile on mobile, show the profile menu
          if (normalized === 'profile') {
            const section = params.get('section');
            if (section) setProfileSection(section.toLowerCase());
            setShowProfileMenu(true);
          } else {
            setShowProfileMenu(false);
          }
        }
      }
    } catch (e) {
      // ignore
    }
  }, [location.search]);
  
  // Real data states
  const [appointments, setAppointments] = useState([]);
  const [appointmentTypes, setAppointmentTypes] = useState([]);
  const [availableSlots, setAvailableSlots] = useState([]);
  const [messages, setMessages] = useState([]);
  const [staff, setStaff] = useState([]);
  const [refunds, setRefunds] = useState([]);
  const [refundsLoading, setRefundsLoading] = useState(false);
  
  // Pagination state for appointments
  const [appointmentsPagination, setAppointmentsPagination] = useState({
    currentPage: 1,
    itemsPerPage: 10
  });

  // Filter state for appointments
  const [appointmentsStatusFilter, setAppointmentsStatusFilter] = useState('all');
  
  // Refund form state
  const [refundData, setRefundData] = useState({
    reason: 'customer_request',
    description: ''
  });
  const [refundLoading, setRefundLoading] = useState(false);
  
  // Daily appointment limit state
  const [dailyLimitInfo, setDailyLimitInfo] = useState({
    limit: null,
    used: 0,
    remaining: null,
    hasReachedLimit: false,
    message: null,
    bookingsToday: []
  });
  
  // Modal states
  const [showAppointmentDetail, setShowAppointmentDetail] = useState(false);
  const [showCancelModal, setShowCancelModal] = useState(false);
  const [showRefundModal, setShowRefundModal] = useState(false);
  const [selectedAppointment, setSelectedAppointment] = useState(null);

  // Settings state
  const [settings, setSettings] = useState({
    theme: 'dark',
    emailNotifications: true,
    smsNotifications: true,
    showProfile: true,
    language: 'en'
  });

  // Profile form state - based on your User model
  const [profileData, setProfileData] = useState({
    username: '',
    email: '',
    password: '',
    first_name: '',
    last_name: '',
    phone: '',
    address: ''
  });

  // Password state
  const [passwordData, setPasswordData] = useState({
    current_password: '',
    new_password: '',
    new_password_confirmation: ''
  });

  // Appointment form state
  const [appointmentData, setAppointmentData] = useState({
    type: '',
    appointment_date: '',
    appointment_time: '',
    notes: '',
    custom_service_type: ''
  });

  const [formErrors, setFormErrors] = useState({});
  const [passwordErrors, setPasswordErrors] = useState({});
  const [profileSuccess, setProfileSuccess] = useState('');
  const [passwordSuccess, setPasswordSuccess] = useState('');

  // Message reply state - moved to component level to comply with React hooks rules
  const [messageReply, setMessageReply] = useState('');
  const [replyError, setReplyError] = useState('');
  const [sendingReply, setSendingReply] = useState(false);
  const [replyingToMessage, setReplyingToMessage] = useState(null);
  const [remainingReplies, setRemainingReplies] = useState(3);

  // Simplified navigation with sections
  const navigation = [
    {
      section: 'Main',
      items: [
        { 
          name: 'Dashboard', 
          href: '#', 
          icon: HomeIcon, 
          current: activeTab === 'home',
          badge: appointments.filter(apt => apt.status === 'pending').length
        },
        { 
          name: 'My Appointments', 
          href: '#', 
          icon: CalendarIcon, 
          current: activeTab === 'appointments',
          badge: appointments.length
        }
        ,{ 
          name: 'Feedback', 
          href: '#', 
          icon: StarIcon, 
          current: activeTab === 'feedback',
          badge: null
        }
      ]
    },
    {
      section: 'Appointments',
      items: [
        { 
          name: 'Book Appointment', 
          href: '#', 
          icon: PlusIcon, 
          current: activeTab === 'book',
          badge: null
        },
        { 
          name: 'Messages', 
          href: '#', 
          icon: ChatBubbleLeftRightIcon, 
          current: activeTab === 'messages',
          badge: messages.filter(msg => !msg.read).length
        },
        { 
          name: 'Refunds', 
          href: '#', 
          icon: CurrencyDollarIcon, 
          current: activeTab === 'refunds',
          badge: refunds.filter(r => r.status === 'pending').length || null
        }
      ]
    },
    {
      section: 'Account',
      items: [
        { 
          name: 'Action Logs', 
          href: '#', 
          icon: ClockIcon, 
          current: activeTab === 'action-logs',
          badge: null
        },
        { 
          name: 'Profile', 
          href: '#', 
          icon: UserIcon, 
          current: activeTab === 'profile',
          badge: null
        }
      ]
    }
  ];

  // Load data on component mount and tab change
  useEffect(() => {
    if (!user?.id) return; // Wait for user to load
    loadInitialData();
  }, [activeTab, user?.id]);

  // Debug: Log when dailyLimitInfo changes
  useEffect(() => {
    console.log('[Dashboard] dailyLimitInfo updated:', {
      hasReachedLimit: dailyLimitInfo.hasReachedLimit,
      limit: dailyLimitInfo.limit,
      used: dailyLimitInfo.used,
      message: dailyLimitInfo.message
    });
  }, [dailyLimitInfo]);

  // Initialize profile data when user changes
  useEffect(() => {
    if (user) {
      setProfileData({
        username: user.username || '',
        email: user.email || '',
        password: '',
        first_name: user.first_name || '',
        last_name: user.last_name || '',
        phone: user.phone || '',
        address: user.address || ''
      });
    }
  }, [user]);

  // Theme management - apply theme to document
  // Note: Theme persistence is now handled by ThemeContext in main.jsx
  useEffect(() => {
    const root = document.documentElement;
    if (isDarkMode) {
      // Dark mode - default theme
      root.classList.add('dark');
      root.style.backgroundColor = 'rgb(11, 11, 11)'; // black
      root.style.color = 'rgb(250, 245, 235)'; // amber-50
      // Ensure user-light marker removed in dark mode
      root.classList.remove('user-light');
    } else {
      // Light mode - using gray instead of stone for better appearance
      root.classList.remove('dark');
      // Apply user-side light palette via CSS variables so only user pages are affected
      root.style.setProperty('--primary', '#1E3A8A');
      root.style.setProperty('--secondary', '#2563EB');
      root.style.setProperty('--accent', '#F59E0B');
      root.style.setProperty('--background', '#F9FAFB');
      root.style.setProperty('--surface', '#FFFFFF');
      root.style.setProperty('--text-primary', '#111827');
      root.style.setProperty('--text-secondary', '#6B7280');
      root.style.setProperty('--borders', '#E5E7EB');
      root.style.setProperty('--success', '#16A34A');
      root.style.setProperty('--error', '#DC2626');
      // Apply user-light marker so CSS remaps Tailwind amber utilities to user palette
      root.classList.add('user-light');
      // Ensure page background/text use the new variables
      root.style.backgroundColor = 'var(--background)';
      root.style.color = 'var(--text-primary)';
    }
  }, [isDarkMode]);

  // Load saved settings on component mount (but NOT theme - that's handled by ThemeContext)
  useEffect(() => {
    const savedSettings = localStorage.getItem('userSettings');
    
    if (savedSettings) {
      try {
        const parsedSettings = JSON.parse(savedSettings);
        setSettings(parsedSettings);
      } catch (e) {
        console.error('Failed to parse saved settings:', e);
      }
    }
  }, []);

  // Listen for services updates from AdminServices component
  useEffect(() => {
    const handleServicesUpdate = () => {
      console.log('Services updated, reloading appointment types...');
      loadAppointmentTypes();
    };

    window.addEventListener('servicesUpdated', handleServicesUpdate);
    return () => window.removeEventListener('servicesUpdated', handleServicesUpdate);
  }, []);

  // Define checkDailyLimit FIRST before useEffect hooks that use it
  const checkDailyLimit = useCallback(async (dateToCheck = null) => {
    try {
      // Guard: user must be loaded
      if (!user?.id) {
        console.log('User not loaded yet, skipping daily limit check');
        return;
      }
      
      // Use the selected date or today's date
      const checkDate = dateToCheck || new Date().toISOString().split('T')[0];
      
      const result = await callApi((signal) =>
        axios.get(`/api/appointment-settings/user-limit/${user.id}/${checkDate}`, { signal })
      , { abortPrevious: false }); // Don't abort - this may run in parallel with other requests

      if (result.success && result.data && result.data.data) {
        const data = result.data.data;
        console.log('Daily limit info loaded:', data);
        setDailyLimitInfo({
          limit: data.limit,
          used: data.used || 0,
          remaining: data.remaining,
          hasReachedLimit: data.has_reached_limit || false,
          message: data.message,
          bookingsToday: data.bookings_today || [],
          date: checkDate
        });
      }
    } catch (error) {
      console.error('Failed to check daily limit:', error);
      // Silently fail - limits may not be configured
    }
  }, [user?.id, callApi]);

  // Define loadAppointmentTypes before it's used
  const loadAppointmentTypes = useCallback(async () => {
    try {
      // First, try to load services from the Services table
      const servicesResult = await callApi((signal) => 
        axios.get('/api/services', { signal })
      , { abortPrevious: false }); // Don't abort - this runs in parallel with other requests
      
      if (servicesResult.success && servicesResult.data.data && Array.isArray(servicesResult.data.data)) {
        // Map services to appointment type format with pricing
        const serviceTypes = servicesResult.data.data.map(service => ({
          value: service.name.toLowerCase().replace(/\s+/g, '_'),
          label: service.name,
          price: service.price,
          duration: service.duration,
          id: service.id
        }));
        
        // Also add the static types for backward compatibility
        const staticTypes = Object.entries({
          'consultation': 'Legal Consultation',
          'document_review': 'Document Review',
          'contract_drafting': 'Contract Drafting',
          'court_representation': 'Court Representation',
          'notary_services': 'Notary Services',
          'legal_opinion': 'Legal Opinion',
          'case_evaluation': 'Case Evaluation',
          'document_notarization': 'Document Notarization',
          'affidavit': 'Affidavit',
          'power_of_attorney': 'Power of Attorney',
          'loan_signing': 'Loan Signing',
          'real_estate_documents': 'Real Estate Documents',
          'will_and_testament': 'Will and Testament',
          'other': 'Other Legal Services'
        }).map(([value, label]) => ({ value, label }));
        
        // Merge and deduplicate
        const merged = [...serviceTypes];
        staticTypes.forEach(staticType => {
          if (!merged.find(t => t.value === staticType.value)) {
            merged.push(staticType);
          }
        });
        
        console.log('[Dashboard] Loaded appointment types:', merged.length, 'total. With IDs:', merged.filter(t => t.id).length);
        setAppointmentTypes(merged);
      } else {
        console.warn('[Dashboard] Services API returned no data, trying alternate endpoint');
        // Fallback to static types if services endpoint fails - include default prices
        const result = await callApi((signal) => 
          axios.get('/api/appointments/types/all', { signal })
        );
        
        // Default prices for static types
        const defaultPrices = {
          'consultation': 550.00,
          'legal_consultation': 550.00,
          'document_review': 500.00,
          'contract_drafting': 300.00,
          'court_representation': 450.00,
          'notary_services': 250.00,
          'legal_opinion': 550.00,
          'case_evaluation': 500.00,
          'document_notarization': 500.00,
          'affidavit': 500.00,
          'power_of_attorney': 400.00,
          'loan_signing': 350.00,
          'real_estate_documents': 500.00,
          'will_and_testament': 450.00,
          'other': 500.00
        };
        
        if (result.success) {
          setAppointmentTypes(Object.entries(result.data.data || {}).map(([value, label]) => ({
            value,
            label,
            price: defaultPrices[value] || 500.00
          })));
        }
      }
    } catch (error) {
      console.error('Failed to load appointment types:', error);
      // Fallback to static types with default prices
      const defaultPrices = {
        'consultation': 550.00,
        'legal_consultation': 550.00,
        'document_review': 500.00,
        'contract_drafting': 300.00,
        'court_representation': 450.00,
        'notary_services': 250.00,
        'legal_opinion': 550.00,
        'case_evaluation': 500.00,
        'document_notarization': 500.00,
        'affidavit': 500.00,
        'power_of_attorney': 400.00,
        'loan_signing': 350.00,
        'real_estate_documents': 500.00,
        'will_and_testament': 450.00,
        'other': 500.00
      };
      
      const result = await callApi((signal) => 
        axios.get('/api/appointments/types/all', { signal })
      );
      
      if (result.success) {
        setAppointmentTypes(Object.entries(result.data.data || {}).map(([value, label]) => ({
          value,
          label,
          price: defaultPrices[value] || 500.00
        })));
      }
    }
  }, [callApi]);

  // Define loadAppointments before it's used
  const loadAppointments = useCallback(async () => {
    console.log('[Dashboard] Loading user appointments...');
    // Debug: Check if auth header is set
    console.log('[Dashboard] Auth header:', axios.defaults.headers.common['Authorization'] ? 'SET' : 'NOT SET');
    
    const result = await callApi((signal) => 
      axios.get('/api/appointments/my/appointments', { signal })
    , { skipCache: true, abortPrevious: false }); // Don't abort - this runs in parallel with other requests
    
    console.log('[Dashboard] Appointments API result:', result);
    
    // Check for auth errors specifically
    if (result.isAuthError) {
      console.error('[Dashboard] Authentication failed when loading appointments. User may need to re-login.');
      return; // Don't clear appointments on auth error - might be temporary
    }
    
    if (result.success) {
      // Handle both direct array and nested data structure
      const appointmentsData = result.data?.data || result.data || [];
      console.log('[Dashboard] Raw appointments data:', appointmentsData);
      console.log('[Dashboard] appointmentsData type:', typeof appointmentsData, Array.isArray(appointmentsData));
      
      // Sort appointments by created_at in descending order (newest first)
      const sortedAppointments = (Array.isArray(appointmentsData) ? appointmentsData : []).sort((a, b) => 
        new Date(b.created_at) - new Date(a.created_at)
      );
      console.log('[Dashboard] Sorted appointments:', sortedAppointments.length, 'items');
      setAppointments(sortedAppointments);
      // Reset pagination to first page
      setAppointmentsPagination(prev => ({
        ...prev,
        currentPage: 1
      }));
    } else if (!result.isAuthError) {
      // Only clear appointments on non-auth errors
      // Auth errors are handled by AuthContext and will redirect to login if needed
      console.error('[Dashboard] Failed to load appointments (non-auth error):', result.error);
      setAppointments([]);
    }
  }, [callApi]);

  // Define loadRefunds before it's used
  const loadRefunds = useCallback(async () => {
    setRefundsLoading(true);
    try {
      const result = await callApi((signal) => 
        axios.get('/api/refunds/my', { 
          signal,
          params: { per_page: 100 } // Get all refunds
        })
      , { abortPrevious: false }); // Don't abort - this runs in parallel with other requests
      
      if (result.success) {
        // Handle paginated response from Laravel
        let refundsData = [];
        if (result.data?.data && Array.isArray(result.data.data)) {
          // Paginated response: { data: [...], current_page, last_page, ... }
          refundsData = result.data.data;
        } else if (Array.isArray(result.data)) {
          // Direct array response
          refundsData = result.data;
        }
        
        // Sort refunds by created_at in descending order (newest first)
        const sortedRefunds = refundsData.sort((a, b) => 
          new Date(b.created_at) - new Date(a.created_at)
        );
        setRefunds(sortedRefunds);
      } else {
        console.error('Failed to load refunds:', result.error);
        setRefunds([]);
      }
    } catch (error) {
      console.error('Error loading refunds:', error);
      setRefunds([]);
    } finally {
      setRefundsLoading(false);
    }
  }, [callApi]);

  // Define loadAvailableSlots before it's used
  const loadAvailableSlots = useCallback(async (date) => {
    if (!date) return;
    
    const result = await callApi((signal) => 
      axios.get(`/api/appointments/available-slots/${date}`, { signal })
    );
    
    if (result.success) {
      setAvailableSlots(result.data.data || []);
    } else {
      setAvailableSlots([]);
    }
  }, [callApi]);

  // Define loadMessages before it's used
  const loadMessages = useCallback(async () => {
    const result = await callApi((signal) => 
      axios.get('/api/messages/all/messages', { signal })
    );
    
    if (result.success) {
      // Filter out chatbot-type messages to keep only genuine user-admin conversations
      const messagesData = (result.data.data || []).filter(msg => msg.type !== 'chatbot');
      setMessages(messagesData);
      
      // If user is a client, calculate remaining replies to the most recent admin message
      if (user?.role === 'client' && messagesData.length > 0) {
        const adminMessages = messagesData.filter(msg => msg.sender_id !== user?.id);
        if (adminMessages.length > 0) {
          const latestAdminMessage = adminMessages[adminMessages.length - 1];
          // Count user's replies to this message
          const userReplies = messagesData.filter(
            msg => msg.sender_id === user?.id && msg.reply_to_message_id === latestAdminMessage.id
          ).length;
          setReplyingToMessage(latestAdminMessage.id);
          setRemainingReplies(Math.max(0, 3 - userReplies));
        }
      }
    }
  }, [callApi, user?.role, user?.id]);

  // NOW define the useEffect hooks that depend on checkDailyLimit
  // Listen for slot capacity changes so availability reloads automatically
  useEffect(() => {
    const handleSlotCapacitiesChanged = () => {
      if (appointmentData?.appointment_date) {
        console.log('Slot capacities changed, reloading available slots for', appointmentData.appointment_date);
        loadAvailableSlots(appointmentData.appointment_date);
      }
    };

    window.addEventListener('slotCapacitiesChanged', handleSlotCapacitiesChanged);
    return () => window.removeEventListener('slotCapacitiesChanged', handleSlotCapacitiesChanged);
  }, [appointmentData?.appointment_date]);

  // Listen for appointment settings changes and refresh daily limit
  useEffect(() => {
    const handleAppointmentSettingsChanged = () => {
      console.log('Appointment settings changed, checking daily limit...');
      // Check today's limit or the selected appointment date
      const dateToCheck = appointmentData?.appointment_date || new Date().toISOString().split('T')[0];
      checkDailyLimit(dateToCheck);
    };

    window.addEventListener('appointmentSettingsChanged', handleAppointmentSettingsChanged);
    return () => window.removeEventListener('appointmentSettingsChanged', handleAppointmentSettingsChanged);
  }, [appointmentData?.appointment_date, checkDailyLimit]);

  const loadInitialData = useCallback(async () => {
    switch (activeTab) {
      case 'home':
      case 'book':
        // Load all data in PARALLEL for faster loading
        await Promise.all([
          loadAppointmentTypes(),
          loadAppointments(),
          loadRefunds(),
          checkDailyLimit()
        ]);
        break;
      case 'appointments':
        // Load in parallel
        await Promise.all([
          loadAppointments(),
          loadRefunds(),
          checkDailyLimit()
        ]);
        break;
      case 'refunds':
        await loadRefunds();
        break;
      case 'messages':
        await loadMessages();
        break;
      case 'profile':
        // Profile data is already loaded from auth context
        break;
    }
  }, [activeTab, loadAppointmentTypes, loadAppointments, loadRefunds, checkDailyLimit, loadMessages]);

  // Initialize real-time updates polling
  const { startPolling: startRealtimePolling, stopPolling: stopRealtimePolling } = useRealtimeUpdates(
    // onCapacityChange callback
    (capacitiesData) => {
      console.log('[Dashboard] Capacity data received from polling, reloading slots');
      if (appointmentData?.appointment_date) {
        loadAvailableSlots(appointmentData.appointment_date);
      }
    },
    // onSettingsChange callback
    (settingsData) => {
      console.log('[Dashboard] Settings data received from polling, checking daily limit');
      const dateToCheck = appointmentData?.appointment_date || new Date().toISOString().split('T')[0];
      checkDailyLimit(dateToCheck);
    }
  );

  // Start polling when Dashboard mounts
  useEffect(() => {
    startRealtimePolling();
    return () => {
      stopRealtimePolling();
    };
  }, [startRealtimePolling, stopRealtimePolling]);

  // Listen for real-time appointment settings updates (moved here to comply with React hooks rules)
  useEffect(() => {
    const handleSettingsUpdate = (event) => {
      // Refresh daily limit when settings are updated by admin
      checkDailyLimit();
    };

    let channel = null;
    try {
      if (window.Echo && typeof window.Echo.channel === 'function') {
        channel = window.Echo.channel('appointment-settings');
        if (channel && typeof channel.listen === 'function') {
          channel.listen('AppointmentSettingsUpdated', handleSettingsUpdate);
        }
      }
    } catch (error) {
      console.debug('Echo not available for appointment settings:', error);
    }

    return () => {
      try {
        if (channel && typeof channel.stopListening === 'function') {
          channel.stopListening('AppointmentSettingsUpdated');
        }
      } catch (error) {
        // Silently fail if Echo cleanup doesn't work
      }
    };
  }, [user?.id, checkDailyLimit]);

  const handleNavClick = (tabName) => {
    setActiveTab(tabName);
    clearError();
  };

  const handleProfileChange = (e) => {
    const { name, value } = e.target;
    setProfileData(prev => ({
      ...prev,
      [name]: value
    }));
  };

  const handlePasswordChange = (e) => {
    const { name, value } = e.target;
    setPasswordData(prev => ({
      ...prev,
      [name]: value
    }));
  };

  const handleAppointmentChange = (e) => {
    const { name, value } = e.target;
    setAppointmentData(prev => ({
      ...prev,
      [name]: value
    }));

    // Load available slots and check limit when date changes
    if (name === 'appointment_date') {
      loadAvailableSlots(value);
      checkDailyLimit(value);
    }

    // Clear errors when user starts typing
    if (formErrors[name]) {
      setFormErrors(prev => ({
        ...prev,
        [name]: ''
      }));
    }
  };

  const handleServiceTypeChange = (value) => {
    setAppointmentData(prev => ({
      ...prev,
      type: value
    }));

    if (formErrors.type) {
      setFormErrors(prev => ({
        ...prev,
        type: ''
      }));
    }
  };

  const handleCustomServiceChange = (value) => {
    setAppointmentData(prev => ({
      ...prev,
      custom_service_type: value
    }));
  };

  const validateAppointmentForm = () => {
    const errors = {};
    
    if (!appointmentData.type) errors.type = 'Appointment type is required';
    if (!appointmentData.appointment_date) errors.appointment_date = 'Date is required';
    if (!appointmentData.appointment_time) errors.appointment_time = 'Time is required';
    if (appointmentData.type === 'other' && !appointmentData.custom_service_type) {
      errors.type = 'Please specify the service type';
    }
    
    setFormErrors(errors);
    return Object.keys(errors).length === 0;
  };

  const handleProfileSubmit = async (e) => {
    e.preventDefault();
    setProfileSuccess('');
    
    // Remove password from profile update if it's empty
    const submitData = { ...profileData };
    if (!submitData.password) {
      delete submitData.password;
    }

    const result = await callApi((signal) => 
      axios.put('/api/profile/update', submitData, { signal })
    );

    if (result.success) {
      setIsEditing(false);
      setProfileSuccess('Profile updated successfully!');
      // Clear success message after 3 seconds
      setTimeout(() => setProfileSuccess(''), 3000);
    }
  };

  const handlePasswordSubmit = async (e) => {
    e.preventDefault();
    setPasswordSuccess('');
    setPasswordErrors({});

    const result = await callApi((signal) => 
      axios.put('/api/profile/password', passwordData, { signal })
    );

    if (result.success) {
      setPasswordData({
        current_password: '',
        new_password: '',
        new_password_confirmation: ''
      });
      setPasswordSuccess('Password updated successfully!');
      // Clear success message after 3 seconds
      setTimeout(() => setPasswordSuccess(''), 3000);
    } else {
      if (result.error) {
        setPasswordErrors({ general: result.error });
      }
    }
  };

  const handleAppointmentSubmit = async (e) => {
    e.preventDefault();
    
    if (!validateAppointmentForm()) return;

    // Check if user has reached daily limit
    if (dailyLimitInfo.hasReachedLimit) {
      setFormErrors({
        appointment_date: `You have reached your daily booking limit of ${dailyLimitInfo.limit} appointments. ${dailyLimitInfo.message || 'You can book again tomorrow.'}`
      });
      return;
    }

    // Get the service ID from the selected appointment type
    // First try exact match, then try case-insensitive match
    let selectedService = appointmentTypes.find(t => t.value === appointmentData.type);
    if (!selectedService?.id) {
      // Try to find by label matching (case-insensitive)
      const typeLabel = appointmentData.type.replace(/_/g, ' ');
      selectedService = appointmentTypes.find(t => 
        t.label?.toLowerCase() === typeLabel.toLowerCase() && t.id
      ) || selectedService;
    }
    const serviceId = selectedService?.id || null;
    
    // Debug log for service matching
    console.log('[Booking] Service lookup:', { 
      type: appointmentData.type, 
      foundService: selectedService, 
      serviceId,
      availableTypes: appointmentTypes.length 
    });

    const submitData = {
      type: appointmentData.type,
      service_id: serviceId,
      appointment_date: appointmentData.appointment_date,
      appointment_time: appointmentData.appointment_time,
      notes: appointmentData.notes,
      // Store the human-friendly label when available so UI shows proper casing
      service_type: appointmentData.type === 'other'
        ? appointmentData.custom_service_type
        : (selectedService?.label || appointmentData.type)
    };

    const result = await callApi((signal) => 
      axios.post('/api/appointments', submitData, { signal })
    );

    if (result.success) {
      setLatestAppointment(result.data.appointment);
      setShowThankYouModal(true);
      
      // Reset form
      setAppointmentData({
        type: '',
        appointment_date: '',
        appointment_time: '',
        notes: '',
        custom_service_type: ''
      });
      setAvailableSlots([]);
      setFormErrors({});
      
      // Reload appointments and check daily limit
      await loadAppointments();
      await checkDailyLimit();
    } else {
      // Handle different error cases
      if (result.error) {
        if (result.error.includes('daily booking limit') || result.error.includes('reached')) {
          setFormErrors({
            appointment_date: 'Daily booking limit reached. ' + result.error
          });
        } else if (result.error.includes('capacity') || result.error.includes('full')) {
          setFormErrors({
            appointment_time: 'This time slot is at full capacity. Please select another time.'
          });
        } else if (result.error.includes('not available') || result.error.includes('unavailable')) {
          setFormErrors({
            appointment_time: 'The selected date and time is not available. Please choose another time slot.'
          });
        } else if (result.error.includes('blackout')) {
          setFormErrors({
            appointment_time: 'This time is blocked: ' + result.error
          });
        } else {
          setFormErrors({
            general: result.error
          });
        }
      }
    }
  };

  const handleCancelAppointment = async () => {
    if (!selectedAppointment) return;

    const result = await callApi((signal) => 
      axios.put(`/api/appointments/${selectedAppointment.id}/cancel`, {}, { signal })
    );

    if (result.success) {
      setShowCancelModal(false);
      setSelectedAppointment(null);
      await loadAppointments();
    }
  };

  const handleViewAppointmentDetails = (appointment) => {
    setSelectedAppointment(appointment);
    setShowAppointmentDetail(true);
  };

  const handleRequestCancellation = (appointment) => {
    setSelectedAppointment(appointment);
    setShowCancelModal(true);
  };

  const handleRequestRefund = async (e) => {
    e.preventDefault();
    
    if (!selectedAppointment || !refundData.reason) {
      alert('Please select a reason for the refund');
      return;
    }

    // Validate payment amount exists
    if (!selectedAppointment.payment_amount || selectedAppointment.payment_amount <= 0) {
      alert('Cannot process refund: This appointment has no payment amount recorded. Please contact support for assistance.');
      return;
    }

    // Validate payment status
    if (selectedAppointment.payment_status !== 'paid') {
      alert('Cannot process refund: This appointment is not marked as paid. Only paid appointments can be refunded.');
      return;
    }

    setRefundLoading(true);
    try {
      const response = await axios.post('/api/refunds/request', {
        appointment_id: selectedAppointment.id,
        refund_amount: selectedAppointment.payment_amount,
        reason: refundData.reason,
        description: refundData.description
      });

      if (response.data?.success) {
        alert('Refund request submitted successfully. You will receive a notification once it is reviewed.');
        setShowRefundModal(false);
        setSelectedAppointment(null);
        setRefundData({ reason: 'customer_request', description: '' });
        // Refresh appointments and refunds
        loadAppointments();
        loadRefunds();
      }
    } catch (error) {
      console.error('Refund request failed:', error);
      alert(error.response?.data?.message || 'Failed to submit refund request');
    } finally {
      setRefundLoading(false);
    }
  };

  const handleSettingsChange = (key, value) => {
    const newSettings = {
      ...settings,
      [key]: value
    };
    setSettings(newSettings);
    
    // Save settings to localStorage
    localStorage.setItem('userSettings', JSON.stringify(newSettings));
    
    // Apply theme changes immediately
    if (key === 'theme') {
      if (value === 'light') {
        setIsDarkMode(false);
      } else if (value === 'dark') {
        setIsDarkMode(true);
      }
    }
  };

  const handleLogout = () => {
    setShowLogoutModal(true);
  };

  const confirmLogout = async () => {
    setIsLoggingOut(true);
    try {
      await logout();
      // Modal will close automatically when user is cleared
      setShowLogoutModal(false);
    } catch (error) {
      console.error('Logout error:', error);
      setIsLoggingOut(false);
      setShowLogoutModal(false);
    }
  };

  // Stats Cards Component for Home
  const StatsCards = () => {
    const stats = [
      {
        name: 'Total Appointments',
        value: appointments.length.toString(),
        icon: CalendarIcon,
        color: 'bg-purple-500'
      },
      {
        name: 'Pending',
        value: appointments.filter(apt => apt.status === 'pending').length.toString(),
        icon: ClockIcon,
        color: 'bg-amber-500'
      },
      {
        name: 'Completed',
        value: appointments.filter(apt => apt.status === 'completed').length.toString(),
        icon: CheckCircleIcon,
        color: 'bg-green-500'
      }
    ];

    return (
      <div className="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        {stats.map((stat, index) => (
          <div
            key={index}
            className={`${isDarkMode ? 'bg-gray-900 border-amber-500/20 hover:shadow-amber-500/10' : 'bg-white border-amber-300/40 hover:shadow-amber-300/10'} border rounded-lg shadow p-4 hover:border-amber-500/40 transition-all duration-300 cursor-pointer group transform hover:-translate-y-1`}
          >
            <div className="flex items-center justify-between">
              <div>
                <p className={`text-xs font-medium ${isDarkMode ? 'text-gray-400 group-hover:text-amber-300' : 'text-gray-500 group-hover:text-amber-600'} transition-colors`}>
                  {stat.name}
                </p>
                <p className={`text-lg font-bold ${isDarkMode ? 'text-amber-50' : 'text-gray-900'} mt-0.5 group-hover:scale-105 transition-transform`}>
                  {stat.value}
                </p>
              </div>
              <div className={`${stat.color} p-2 rounded-lg shadow group-hover:scale-110 transition-transform`}>
                <stat.icon className="h-5 w-5 text-white" />
              </div>
            </div>
          </div>
        ))}
      </div>
    );
  };

  const renderHome = () => (
    <div className="space-y-6">
      {/* Welcome Section */}
      <div className={`${isDarkMode ? 'bg-gray-900 border-amber-500/20' : 'bg-white border-amber-300/40'} border rounded-lg shadow p-4 sm:p-6 hover:border-amber-500/40 transition-all duration-300`}>
        <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
          <div className="flex-1">
            <h2 className={`text-base sm:text-lg font-bold ${isDarkMode ? 'text-amber-50' : 'text-gray-900'}`}>Welcome back, {user?.first_name}! 👋</h2>
            <p className={`mt-1 text-xs sm:text-sm ${isDarkMode ? 'text-amber-400/70' : 'text-gray-600'}`}>Ready to schedule your next notarization service?</p>
          </div>
          <button
            onClick={() => setActiveTab('book')}
            className="w-full sm:w-auto px-3 sm:px-4 py-2 bg-gradient-to-r from-amber-600 to-amber-700 text-white rounded-lg hover:from-amber-700 hover:to-amber-800 transition-all duration-200 font-medium text-xs sm:text-sm flex items-center justify-center sm:justify-start shadow transform hover:-translate-y-0.5 border border-amber-500/30 whitespace-nowrap"
          >
            <PlusIcon className="h-4 w-4 mr-2" />
            Book Appointment
          </button>
        </div>
      </div>

      {/* Stats Cards */}
      <StatsCards />

      {/* Quick Actions */}
      <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div className={`${isDarkMode ? 'bg-gray-900 border-amber-500/20' : 'bg-white border-amber-300/40'} border rounded-lg shadow p-4 hover:border-amber-500/40 transition-all duration-300`}>
          <h3 className={`text-sm font-semibold ${isDarkMode ? 'text-amber-50' : 'text-gray-900'} mb-3 flex items-center`}>
            <ClockIcon className="h-4 w-4 mr-2" />
            Quick Actions
          </h3>
          <div className="space-y-2">
            <button
              onClick={() => setActiveTab('book')}
              className={`w-full text-left p-2 border rounded hover:border-amber-500/40 hover:bg-amber-500/5 transition-all duration-200 text-sm flex items-center justify-between group ${isDarkMode ? 'border-gray-600 text-amber-50' : 'border-gray-300 text-gray-900'}`}
            >
              <div className="flex items-center">
                <PlusIcon className={`h-4 w-4 mr-2 ${isDarkMode ? 'text-amber-400' : 'text-amber-600'}`} />
                <span>Book New Appointment</span>
              </div>
              <ChevronDownIcon className={`h-3 w-3 transform -rotate-90 group-hover:scale-110 ${isDarkMode ? 'text-amber-400' : 'text-amber-600'}`} />
            </button>
            <button
              onClick={() => setActiveTab('appointments')}
              className={`w-full text-left p-2 border rounded hover:border-amber-500/40 hover:bg-amber-500/5 transition-all duration-200 text-sm flex items-center justify-between group ${isDarkMode ? 'border-gray-600 text-amber-50' : 'border-gray-300 text-gray-900'}`}
            >
              <div className="flex items-center">
                <CalendarIcon className={`h-4 w-4 mr-2 ${isDarkMode ? 'text-amber-400' : 'text-amber-600'}`} />
                <span>View All Appointments</span>
              </div>
              <ChevronDownIcon className={`h-3 w-3 transform -rotate-90 group-hover:scale-110 ${isDarkMode ? 'text-amber-400' : 'text-amber-600'}`} />
            </button>
          </div>
        </div>

        {/* Recent Appointments Preview */}
        <div className={`${isDarkMode ? 'bg-gray-900 border-amber-500/20' : 'bg-white border-amber-300/40'} border rounded-lg shadow p-4 hover:border-amber-500/40 transition-all duration-300`}>
          <div className="flex items-center justify-between mb-3">
            <h3 className={`text-sm font-semibold ${isDarkMode ? 'text-amber-50' : 'text-amber-900'} flex items-center`}>
              <CalendarDaysIcon className="h-4 w-4 mr-2" />
              Recent Appointments
            </h3>
            <button
              onClick={() => setActiveTab('appointments')}
              className={`text-xs font-medium hover:bg-amber-500/10 px-2 py-1 rounded border transition-colors duration-200 ${isDarkMode ? 'text-amber-400 hover:text-amber-300 border-amber-500/30' : 'text-amber-600 hover:text-amber-700 border-amber-400/50'}`}
            >
              View All
            </button>
          </div>
          {appointments.length === 0 ? (
            <div className="text-center py-4">
              <CalendarIcon className={`mx-auto h-8 w-8 ${isDarkMode ? 'text-gray-600' : 'text-gray-400'}`} />
              <h3 className={`mt-1 text-xs font-medium ${isDarkMode ? 'text-amber-50' : 'text-gray-900'}`}>No appointments</h3>
              <p className={`text-xs mt-0.5 ${isDarkMode ? 'text-amber-400/70' : 'text-gray-600'}`}>Schedule your first appointment to get started</p>
            </div>
          ) : (
            <div className="space-y-2">
              {appointments.slice(0, 3).map((appointment) => (
                <div key={appointment.id} className={`flex items-center justify-between p-2 border rounded hover:border-amber-500/30 hover:bg-amber-500/5 transition-all duration-200 group ${isDarkMode ? 'border-gray-600' : 'border-gray-300'}`}>
                  <div className="flex items-center space-x-2">
                    <div className="flex-shrink-0">
                      <div className={`w-8 h-8 rounded-full flex items-center justify-center border ${isDarkMode ? 'bg-amber-500/20 border-amber-500/30' : 'bg-amber-100 border-amber-300'}`}>
                        <DocumentTextIcon className={`h-3 w-3 ${isDarkMode ? 'text-amber-400' : 'text-amber-600'}`} />
                      </div>
                    </div>
                    <div>
                      <p className="font-medium text-amber-50 text-xs group-hover:text-amber-300">
                        {formatServiceName(appointment)}
                      </p>
                      <p className="text-xs text-amber-400/70 group-hover:text-amber-300">
                        {new Date(appointment.appointment_date).toLocaleDateString()} at {appointment.appointment_time}
                      </p>
                    </div>
                  </div>
                  <StatusBadge status={appointment.status} />
                </div>
              ))}
            </div>
          )}
        </div>
      </div>
    </div>
  );

  const renderBookAppointment = () => (
    <div className="space-y-6">
      <div className="hidden lg:flex justify-between items-center">
        <div>
          <h2 className={`text-lg font-bold ${isDarkMode ? 'text-amber-50' : 'text-gray-900'}`}>Book New Appointment</h2>
          <p className={`text-amber-400/70 mt-1 text-sm ${isDarkMode ? '' : 'text-gray-600'}`}>Schedule your document notarization service</p>
        </div>
        <div className="flex items-center space-x-1 text-xs text-amber-400/70">
          <ClockIcon className="h-3 w-3" />
          <span>30 min sessions</span>
        </div>
      </div>

      {/* Daily Limit Status */}
      {dailyLimitInfo.limit && (
        <div className={`rounded-lg border p-4 flex items-start gap-3 ${
          dailyLimitInfo.hasReachedLimit
            ? 'bg-red-900/20 border-red-500/30'
            : 'bg-blue-900/20 border-blue-500/30'
        }`}>
          {dailyLimitInfo.hasReachedLimit ? (
            <>
              <ExclamationTriangleIcon className="h-5 w-5 flex-shrink-0 text-red-400 mt-0.5" />
              <div>
                <h3 className="font-semibold text-red-400">📅 Daily Booking Limit Reached</h3>
                <p className="text-sm text-red-300/80 mt-1">
                  {dailyLimitInfo.message || `You have reached your daily booking limit of ${dailyLimitInfo.limit} appointments. You can book again tomorrow.`}
                </p>
                {dailyLimitInfo.bookingsToday?.length > 0 && (
                  <div className="mt-3 text-xs text-red-300/70">
                    <p className="font-medium mb-2">Your appointments today:</p>
                    <ul className="space-y-1 ml-2">
                      {dailyLimitInfo.bookingsToday.map((booking, idx) => (
                        <li key={idx} className="flex items-center gap-2">
                          <span>•</span>
                          <span>{formatTime12Hour(booking.time)} - {booking.service}</span>
                        </li>
                      ))}
                    </ul>
                  </div>
                )}
              </div>
            </>
          ) : (
            <>
              <CheckCircleIcon className="h-5 w-5 flex-shrink-0 text-blue-400 mt-0.5" />
              <div>
                <h3 className="font-semibold text-blue-400">Appointment Slots Available</h3>
                <p className="text-sm text-blue-300/80 mt-1">
                  You have {dailyLimitInfo.remaining} of {dailyLimitInfo.limit} daily appointment slots available.
                </p>
              </div>
            </>
          )}
        </div>
      )}

      <div className={`${isDarkMode ? 'bg-gray-900 border-amber-500/20' : 'bg-white border-amber-300/40'} border rounded-lg shadow p-6 hover:border-amber-500/40 transition-all duration-300`}>
        <form onSubmit={handleAppointmentSubmit} className="space-y-4">
          {/* Display general form errors */}
          {formErrors.general && (
            <div className="rounded-lg border border-red-500/30 bg-red-900/20 p-4 flex items-start gap-3">
              <ExclamationTriangleIcon className="h-5 w-5 flex-shrink-0 text-red-400 mt-0.5" />
              <div>
                <p className="text-sm text-red-300/90">{formErrors.general}</p>
              </div>
            </div>
          )}

          <div className="grid grid-cols-1 lg:grid-cols-2 gap-4">
            {/* Service Type with Enhanced Dropdown */}
            <ServiceTypeDropdown
              value={appointmentData.type}
              onChange={handleServiceTypeChange}
              options={appointmentTypes}
              error={formErrors.type}
              onOtherChange={handleCustomServiceChange}
              otherValue={appointmentData.custom_service_type}
              disabled={dailyLimitInfo.hasReachedLimit}
            />

            {/* Enhanced Calendar Component */}
            <EnhancedCalendar
              value={appointmentData.appointment_date}
              onChange={(value) => handleAppointmentChange({ target: { name: 'appointment_date', value } })}
              error={formErrors.appointment_date}
              disabled={dailyLimitInfo.hasReachedLimit}
              dailyLimitInfo={dailyLimitInfo}
            />

            {/* Time Input using TimePicker Component */}
            <TimePicker
              value={appointmentData.appointment_time}
              onChange={(value) => handleAppointmentChange({ target: { name: 'appointment_time', value } })}
              error={formErrors.appointment_time}
              disabled={dailyLimitInfo.hasReachedLimit}
              isDarkMode={isDarkMode}
            />

            <div className="lg:col-span-2">
              <label className={`block text-xs font-medium ${isDarkMode ? 'text-amber-50' : 'text-amber-900'} mb-1`}>
                Additional Notes (Optional)
              </label>
              <textarea
                name="notes"
                value={appointmentData.notes}
                onChange={handleAppointmentChange}
                disabled={dailyLimitInfo.hasReachedLimit}
                rows="3"
                className={`w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-500 focus:border-amber-500 transition-all duration-200 text-sm placeholder-gray-400 resize-none ${isDarkMode ? 'bg-gray-800 border-gray-600 text-white' : 'bg-white border-gray-300 text-gray-900'} ${
                  dailyLimitInfo.hasReachedLimit ? 'opacity-50 cursor-not-allowed' : ''
                }`}
                placeholder="Any special requirements, document details, or specific instructions..."
              />
            </div>

            {/* Service Summary with Pricing */}
            {appointmentData.type && appointmentTypes.find(t => t.value === appointmentData.type) && (
              <div className="lg:col-span-2 bg-gradient-to-r from-amber-500/10 to-amber-500/5 border border-amber-500/30 rounded-lg p-4">
                <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                  <div>
                    <p className="text-xs text-amber-400/70 font-medium">Service</p>
                    <p className="text-sm text-amber-50 font-semibold">
                      {appointmentTypes.find(t => t.value === appointmentData.type)?.label}
                    </p>
                  </div>
                  {appointmentTypes.find(t => t.value === appointmentData.type)?.price && (
                    <div>
                      <p className="text-xs text-amber-400/70 font-medium">Price</p>
                      <p className="text-sm text-amber-50 font-semibold">
                        ${parseFloat(appointmentTypes.find(t => t.value === appointmentData.type).price).toFixed(2)}
                      </p>
                    </div>
                  )}
                  {appointmentTypes.find(t => t.value === appointmentData.type)?.duration && (
                    <div>
                      <p className="text-xs text-amber-400/70 font-medium">Duration</p>
                      <p className="text-sm text-amber-50 font-semibold">
                        {appointmentTypes.find(t => t.value === appointmentData.type).duration} minutes
                      </p>
                    </div>
                  )}
                </div>
              </div>
            )}
          </div>

          <div className="flex flex-col sm:flex-row justify-between items-center space-y-3 sm:space-y-0 pt-4 border-t border-gray-700">
            <div className="text-xs text-amber-400/70 space-y-1">
              <p>📋 Bring valid government-issued ID to your appointment</p>
              <p>⏰ Please arrive 5 minutes early</p>
              <p>📄 Have all documents ready for notarization</p>
            </div>
            <button
              type="submit"
              disabled={loading || dailyLimitInfo.hasReachedLimit}
              className="px-6 py-2 bg-gradient-to-r from-amber-600 to-amber-700 text-white rounded-lg hover:from-amber-700 hover:to-amber-800 focus:outline-none focus:ring-2 focus:ring-amber-500 focus:ring-offset-2 focus:ring-offset-gray-900 transition-all duration-200 font-medium text-sm shadow border border-amber-500/30 disabled:opacity-50 disabled:cursor-not-allowed flex items-center transform hover:-translate-y-0.5"
            >
              {loading ? (
                <>
                  <div className="w-3 h-3 border-2 border-white border-t-transparent rounded-full mr-2"></div>
                  Scheduling...
                </>
              ) : dailyLimitInfo.hasReachedLimit ? (
                <>
                  <XCircleIcon className="h-4 w-4 mr-2" />
                  Daily Limit Reached
                </>
              ) : (
                <>
                  <PlusIcon className="h-4 w-4 mr-2" />
                  Schedule Appointment
                </>
              )}
            </button>
          </div>
        </form>
      </div>
    </div>
  );

  const renderAppointments = () => {
    // Filter appointments by status
    let filteredAppointments = appointments;
    if (appointmentsStatusFilter !== 'all') {
      filteredAppointments = appointments.filter(apt => apt.status === appointmentsStatusFilter);
    }

    // Calculate pagination
    const totalAppointments = filteredAppointments.length;
    const totalPages = Math.ceil(totalAppointments / appointmentsPagination.itemsPerPage);
    const startIndex = (appointmentsPagination.currentPage - 1) * appointmentsPagination.itemsPerPage;
    const endIndex = startIndex + appointmentsPagination.itemsPerPage;
    const paginatedAppointments = filteredAppointments.slice(startIndex, endIndex);

    const handlePreviousPage = () => {
      if (appointmentsPagination.currentPage > 1) {
        setAppointmentsPagination(prev => ({
          ...prev,
          currentPage: prev.currentPage - 1
        }));
      }
    };

    const handleNextPage = () => {
      if (appointmentsPagination.currentPage < totalPages) {
        setAppointmentsPagination(prev => ({
          ...prev,
          currentPage: prev.currentPage + 1
        }));
      }
    };

    const handlePageChange = (page) => {
      setAppointmentsPagination(prev => ({
        ...prev,
        currentPage: page
      }))
    };

    return (
      <div className="space-y-6">
        <div className="hidden lg:flex flex-col sm:flex-row sm:justify-between sm:items-center gap-4">
          <div className="flex items-center gap-3">
          <div className="flex items-center gap-3">
            <div>
              <h2 className={`text-lg font-bold ${isDarkMode ? 'text-amber-50' : 'text-gray-900'}`}>My Appointments</h2>
              <p className={`mt-1 text-sm ${isDarkMode ? 'text-amber-400/70' : 'text-gray-600'}`}>View and manage your notarization appointments</p>
            </div>
          </div>
          </div>
          <div className="flex flex-col sm:flex-row gap-2 w-full sm:w-auto">
            <button
              onClick={loadAppointments}
              className="px-2 sm:px-3 py-1.5 border border-amber-500/30 text-amber-50 rounded hover:bg-amber-500/10 transition-all duration-200 font-medium text-xs sm:text-sm flex items-center justify-center flex-1 sm:flex-none"
              title="Refresh appointments"
            >
              <ArrowPathIcon className="h-3 w-3 mr-1" />
              <span className="hidden sm:inline">Refresh</span>
              <span className="sm:hidden">Refresh</span>
            </button>
            <button
              onClick={() => setActiveTab('book')}
              className="px-2 sm:px-3 py-1.5 bg-gradient-to-r from-amber-600 to-amber-700 text-white rounded hover:from-amber-700 hover:to-amber-800 transition-all duration-200 font-medium text-xs sm:text-sm flex items-center justify-center flex-1 sm:flex-none shadow transform hover:-translate-y-0.5 border border-amber-500/30"
            >
              <PlusIcon className="h-3 w-3 mr-1" />
              <span className="hidden sm:inline">New Appointment</span>
              <span className="sm:hidden">New</span>
            </button>
          </div>
        </div>

        {/* Status Filter Dropdown */}
        <div className="flex flex-col sm:flex-row gap-2 items-start sm:items-center">
          <label className={`text-sm font-medium ${isDarkMode ? 'text-amber-400' : 'text-amber-600'}`}>Filter by status:</label>
          <select
            value={appointmentsStatusFilter}
            onChange={(e) => {
              setAppointmentsStatusFilter(e.target.value);
              setAppointmentsPagination(prev => ({ ...prev, currentPage: 1 }));
            }}
            className={`px-3 py-1.5 border rounded text-sm hover:border-amber-500/50 focus:outline-none focus:ring-1 focus:ring-amber-500 transition-all duration-200 ${isDarkMode ? 'bg-gray-800 border-amber-500/30 text-amber-50' : 'bg-white border-amber-300 text-amber-900'}`}
          >
            <option value="all">All Appointments</option>
            <option value="pending">Pending</option>
            <option value="approved">Approved</option>
            <option value="completed">Completed</option>
            <option value="cancelled">Cancelled</option>
            <option value="declined">Declined</option>
          </select>
        </div>

        <div className={`${isDarkMode ? 'bg-gray-900 border-amber-500/20' : 'bg-white border-amber-300/40'} border rounded-lg shadow overflow-hidden hover:border-amber-500/40 transition-all duration-300`}>
          {appointments.length === 0 ? (
            <div className="text-center py-8">
              <CalendarIcon className={`mx-auto h-12 w-12 ${isDarkMode ? 'text-gray-600' : 'text-gray-400'}`} />
              <h3 className={`mt-4 text-sm font-medium ${isDarkMode ? 'text-amber-50' : 'text-amber-900'}`}>No appointments yet</h3>
              <p className={`mt-2 text-xs ${isDarkMode ? 'text-amber-400/70' : 'text-amber-700/70'}`}>Schedule your first notarization appointment to get started</p>
              <button
                onClick={() => setActiveTab('book')}
                className="mt-4 px-4 py-2 bg-gradient-to-r from-amber-600 to-amber-700 text-white rounded-lg hover:from-amber-700 hover:to-amber-800 transition-all duration-200 font-medium text-sm shadow border border-amber-500/30"
              >
                Book Appointment
              </button>
            </div>
          ) : paginatedAppointments.length === 0 ? (
            <div className="text-center py-8">
              <CalendarIcon className={`mx-auto h-12 w-12 ${isDarkMode ? 'text-gray-600' : 'text-gray-400'}`} />
              <h3 className={`mt-4 text-sm font-medium ${isDarkMode ? 'text-amber-50' : 'text-amber-900'}`}>No {appointmentsStatusFilter === 'all' ? '' : appointmentsStatusFilter} appointments</h3>
              <p className={`mt-2 text-xs ${isDarkMode ? 'text-amber-400/70' : 'text-amber-700/70'}`}>Try selecting a different status filter</p>
            </div>
          ) : (
            <>
              <div className={`divide-y ${isDarkMode ? 'divide-gray-700' : 'divide-gray-200'}`}>
                {paginatedAppointments.map((appointment) => (
                  <div key={appointment.id} className={`p-4 transition-all duration-200 group ${isDarkMode ? 'hover:bg-gray-800' : 'hover:bg-gray-50'}`}>
                    <div className="flex items-center justify-between">
                      <div className="flex items-center space-x-3">
                        <div className="flex-shrink-0">
                          <div className={`w-10 h-10 rounded-full flex items-center justify-center border ${isDarkMode ? 'bg-amber-500/20 border-amber-500/30' : 'bg-amber-100 border-amber-300'}`}>
                            <DocumentTextIcon className={`h-5 w-5 ${isDarkMode ? 'text-amber-400' : 'text-amber-600'}`} />
                          </div>
                        </div>
                        <div className="flex-1">
                          <div className="flex items-center space-x-2">
                            <h3 className={`text-sm font-semibold ${isDarkMode ? 'text-amber-50 group-hover:text-amber-300' : 'text-amber-900 group-hover:text-amber-700'}`}>
                              {formatServiceName(appointment)}
                            </h3>
                            <StatusBadge status={appointment.status} />
                          </div>
                          <p className={`text-xs mt-1 ${isDarkMode ? 'text-amber-400/70' : 'text-amber-700/70'}`}>
                            {new Date(appointment.appointment_date).toLocaleDateString()} at {appointment.appointment_time}
                          </p>
                          {appointment.staff && (
                            <div className={`flex items-center space-x-1 mt-1 text-xs ${isDarkMode ? 'text-amber-400/70' : 'text-amber-700/70'}`}>
                              <span>Assigned to:</span>
                              <span className={isDarkMode ? 'text-amber-300' : 'text-amber-600'}>
                                {appointment.staff.first_name} {appointment.staff.last_name}
                              </span>
                            </div>
                          )}
                        </div>
                      </div>
                      <div className="flex items-center space-x-1">
                        <button
                          onClick={() => handleViewAppointmentDetails(appointment)}
                          className="text-amber-400 hover:text-amber-300 transition-colors duration-200 p-1 rounded hover:bg-amber-500/10 border border-amber-500/30"
                          title="View details"
                        >
                          <EyeIcon className="h-3 w-3" />
                        </button>
                        {appointment.status === 'pending' && (
                          <button
                            onClick={() => handleRequestCancellation(appointment)}
                            className="text-red-400 hover:text-red-300 transition-colors duration-200 p-1 rounded hover:bg-red-500/10 border border-red-500/30"
                            title="Cancel appointment"
                          >
                            <TrashIcon className="h-3 w-3" />
                          </button>
                        )}
                        {appointment.status === 'completed' && appointment.payment_status === 'paid' && appointment.payment_amount > 0 && (
                          <button
                            onClick={() => {
                              setSelectedAppointment(appointment);
                              setShowRefundModal(true);
                            }}
                            className="text-green-400 hover:text-green-300 transition-colors duration-200 p-1 rounded hover:bg-green-500/10 border border-green-500/30"
                            title="Request refund"
                          >
                            <CurrencyDollarIcon className="h-3 w-3" />
                          </button>
                        )}
                      </div>
                    </div>
                  </div>
                ))}
              </div>

              {/* Pagination Controls */}
              <div className={`p-4 border-t border-gray-700 flex items-center justify-between flex-wrap gap-4 ${isDarkMode ? 'bg-gray-800/50' : 'bg-gray-50'}`}>
                <div className="text-xs text-amber-400/70">
                  Showing {startIndex + 1} to {Math.min(endIndex, totalAppointments)} of {totalAppointments} appointments
                </div>
                
                <div className="flex items-center gap-1">
                  <button
                    onClick={handlePreviousPage}
                    disabled={appointmentsPagination.currentPage === 1}
                    className={`px-2 py-1 rounded border transition-all duration-200 text-xs font-medium ${
                      appointmentsPagination.currentPage === 1
                        ? 'border-gray-600 text-gray-500 cursor-not-allowed'
                        : 'border-amber-500/30 text-amber-50 hover:bg-amber-500/10 hover:border-amber-500/50'
                    }`}
                  >
                    ← Previous
                  </button>

                  <div className="flex gap-1 mx-2">
                    {Array.from({ length: totalPages }, (_, i) => i + 1).map(page => (
                      <button
                        key={page}
                        onClick={() => handlePageChange(page)}
                        className={`w-7 h-7 rounded border text-xs font-medium transition-all duration-200 ${
                          appointmentsPagination.currentPage === page
                            ? 'bg-amber-500/20 border-amber-500/50 text-amber-300'
                            : 'border-gray-600 text-gray-400 hover:border-amber-500/30 hover:text-amber-300'
                        }`}
                      >
                        {page}
                      </button>
                    ))}
                  </div>

                  <button
                    onClick={handleNextPage}
                    disabled={appointmentsPagination.currentPage === totalPages}
                    className={`px-2 py-1 rounded border transition-all duration-200 text-xs font-medium ${
                      appointmentsPagination.currentPage === totalPages
                        ? 'border-gray-600 text-gray-500 cursor-not-allowed'
                        : 'border-amber-500/30 text-amber-50 hover:bg-amber-500/10 hover:border-amber-500/50'
                    }`}
                  >
                    Next →
                  </button>
                </div>
              </div>
            </>
          )}
        </div>
      </div>
    );
  };

  const renderMessages = () => {
    return <MessageCenter isDarkMode={isDarkMode} />;
  };

  const renderRefunds = () => {
    const getStatusColor = (status) => {
      switch (status) {
        case 'pending':
          return 'bg-amber-500/20 text-amber-300 border border-amber-500/30';
        case 'approved':
          return 'bg-green-500/20 text-green-300 border border-green-500/30';
        case 'completed':
          return 'bg-blue-500/20 text-blue-300 border border-blue-500/30';
        case 'rejected':
          return 'bg-red-500/20 text-red-300 border border-red-500/30';
        default:
          return 'bg-gray-500/20 text-gray-300 border border-gray-500/30';
      }
    };

    const getStatusLabel = (status) => {
      return status.charAt(0).toUpperCase() + status.slice(1);
    };

    const getRejectionReason = (refund) => {
      if (refund.status === 'rejected' && refund.rejection_reason) {
        return refund.rejection_reason.replace(/_/g, ' ');
      }
      return null;
    };

    return (
      <div className="space-y-6">
        {/* Mobile Header with Back Button */}
        <div className="flex lg:hidden items-center gap-3 -mx-3 -mt-3 px-3 py-3 border-b border-gray-700">
          <button
            onClick={() => navigate('/dashboard?tab=home')}
            className="p-2 rounded-lg hover:bg-gray-800 transition-colors"
          >
            <ArrowLeftIcon className="w-5 h-5 text-gray-300" />
          </button>
          <div className="flex-1">
            <h2 className={`text-lg font-bold ${isDarkMode ? 'text-amber-50' : 'text-gray-900'}`}>My Refunds</h2>
            <p className={`text-sm ${isDarkMode ? 'text-amber-400/70' : 'text-gray-600'}`}>View and manage your refund requests</p>
          </div>
          <button
            onClick={loadRefunds}
            disabled={refundsLoading}
            className="p-2 rounded-lg hover:bg-gray-800 transition-colors disabled:opacity-50"
            title="Refresh refunds"
          >
            <ArrowPathIcon className={`h-5 w-5 text-amber-400 ${refundsLoading ? 'animate-spin' : ''}`} />
          </button>
        </div>
        
        {/* Desktop Header */}
        <div className="hidden lg:flex flex-col sm:flex-row sm:justify-between sm:items-center gap-4">
          <div className="flex items-center gap-3">
            <div>
              <h2 className={`text-lg font-bold ${isDarkMode ? 'text-amber-50' : 'text-gray-900'}`}>My Refunds</h2>
              <p className={`mt-1 text-sm ${isDarkMode ? 'text-amber-400/70' : 'text-gray-600'}`}>View and manage your refund requests</p>
            </div>
          </div>
          <button
            onClick={loadRefunds}
            disabled={refundsLoading}
            className="px-3 py-1.5 border border-amber-500/30 text-amber-50 rounded hover:bg-amber-500/10 transition-all duration-200 font-medium text-xs sm:text-sm flex items-center justify-center flex-1 sm:flex-none disabled:opacity-50"
            title="Refresh refunds"
          >
            <ArrowPathIcon className={`h-3 w-3 mr-1 ${refundsLoading ? 'animate-spin' : ''}`} />
            <span className="hidden sm:inline">{refundsLoading ? 'Loading...' : 'Refresh'}</span>
            <span className="sm:hidden">{refundsLoading ? '...' : 'Refresh'}</span>
          </button>
        </div>

        {/* Refunds List */}
        <div className={`${isDarkMode ? 'bg-gray-900 border-gray-800' : 'bg-white border-gray-200'} border rounded-lg shadow overflow-hidden`}>
          {refundsLoading && refunds.length === 0 ? (
            <div className="p-6 text-center">
              <div className="flex flex-col items-center justify-center">
                <ArrowPathIcon className="h-8 w-8 text-amber-400 animate-spin mb-3" />
                <p className={`text-sm ${isDarkMode ? 'text-gray-400' : 'text-gray-600'}`}>Loading refunds...</p>
              </div>
            </div>
          ) : refunds.length === 0 ? (
            <div className="p-6 text-center">
              <div className={`${isDarkMode ? 'text-gray-400' : 'text-gray-600'}`}>
                <CurrencyDollarIcon className="h-12 w-12 mx-auto mb-3 opacity-50" />
                <p className="text-sm">No refund requests yet</p>
              </div>
            </div>
          ) : (
            <div className={`divide-y ${isDarkMode ? 'divide-gray-700' : 'divide-gray-200'}`}>
              {refunds.map((refund) => (
                <div key={refund.id} className={`p-4 transition-all duration-200 ${isDarkMode ? 'hover:bg-gray-800/50' : 'hover:bg-gray-50'}`}>
                  <div className="flex items-start justify-between gap-4">
                    <div className="flex-1">
                      <div className="flex items-center gap-2 mb-2">
                        <h3 className={`text-sm font-semibold ${isDarkMode ? 'text-amber-50' : 'text-amber-900'}`}>
                          Appointment #{refund.appointment_id}
                        </h3>
                        <span className={`px-2 py-0.5 rounded-full text-xs font-medium ${getStatusColor(refund.status)}`}>
                          {getStatusLabel(refund.status)}
                        </span>
                      </div>
                      
                      <div className="grid grid-cols-2 sm:grid-cols-4 gap-3 mt-3 text-xs">
                        <div>
                          <p className={isDarkMode ? 'text-gray-400' : 'text-gray-600'}>Service</p>
                          <p className={`font-medium ${isDarkMode ? 'text-amber-50' : 'text-amber-900'}`}>{refund.appointment?.service?.name || formatServiceName(refund.appointment) || 'N/A'}</p>
                        </div>
                        <div>
                          <p className={isDarkMode ? 'text-gray-400' : 'text-gray-600'}>Amount</p>
                          <p className="text-green-400 font-medium">₱{parseFloat(refund.refund_amount).toFixed(2)}</p>
                        </div>
                        <div>
                          <p className={isDarkMode ? 'text-gray-400' : 'text-gray-600'}>Reason</p>
                          <p className="text-amber-50 font-medium">{refund.reason?.replace(/_/g, ' ') || 'N/A'}</p>
                        </div>
                        <div>
                          <p className="text-gray-400">Requested</p>
                          <p className="text-amber-50 font-medium">{new Date(refund.created_at).toLocaleDateString()}</p>
                        </div>
                      </div>

                      {getRejectionReason(refund) && (
                        <div className="mt-3 p-3 bg-red-500/10 border border-red-500/30 rounded-lg">
                          <p className="text-xs text-red-300">
                            <strong>Rejection Reason:</strong> {getRejectionReason(refund)}
                          </p>
                          {refund.rejection_notes && (
                            <p className="text-xs text-red-200 mt-1">
                              <strong>Notes:</strong> {refund.rejection_notes}
                            </p>
                          )}
                        </div>
                      )}

                      {refund.status === 'completed' && refund.transaction_id && (
                        <div className="mt-3 p-3 bg-green-500/10 border border-green-500/30 rounded-lg">
                          <p className="text-xs text-green-300">
                            <strong>Transaction ID:</strong> {refund.transaction_id}
                          </p>
                          <p className="text-xs text-green-200 mt-1">
                            <strong>Completed:</strong> {new Date(refund.completed_at).toLocaleString()}
                          </p>
                        </div>
                      )}

                      {refund.status === 'approved' && (
                        <div className="mt-3 p-3 bg-green-500/10 border border-green-500/30 rounded-lg">
                          <p className="text-xs text-green-300">
                            <strong>Approved:</strong> {new Date(refund.updated_at).toLocaleString()}
                          </p>
                          {refund.approval_notes && (
                            <p className="text-xs text-green-200 mt-1">
                              <strong>Notes:</strong> {refund.approval_notes}
                            </p>
                          )}
                        </div>
                      )}
                    </div>
                  </div>
                </div>
              ))}
            </div>
          )}
        </div>
      </div>
    );
  };

  const renderProfile = () => (
    <div className="space-y-6">
      {/* Mobile Back Button */}
      <div className="lg:hidden flex items-center gap-2 mb-4">
        <button
          onClick={() => setActiveTab('home')}
          className="p-2 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-800 transition-colors"
          title="Back to home"
        >
          <ArrowLeftIcon className={`h-5 w-5 ${isDarkMode ? 'text-amber-400' : 'text-amber-600'}`} />
        </button>
        <h2 className={`text-lg font-bold ${isDarkMode ? 'text-amber-50' : 'text-gray-900'}`}>Profile Settings</h2>
      </div>

      <div className="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-4">
        <div className="flex items-center gap-3">
          <div>
            <h2 className={`text-lg font-bold ${isDarkMode ? 'text-amber-50' : 'text-gray-900'}`}>Profile Settings</h2>
            <p className={`mt-1 text-sm ${isDarkMode ? 'text-amber-400/70' : 'text-gray-600'}`}>Manage your personal information and security</p>
          </div>
        </div>
        <div className="flex gap-2 w-full sm:w-auto">
          <button
            onClick={() => setIsEditing(!isEditing)}
            className="flex-1 sm:flex-none px-3 py-2 sm:py-1.5 border border-amber-500/30 text-amber-50 rounded hover:bg-amber-500/10 transition-all duration-200 font-medium text-xs sm:text-sm flex items-center justify-center gap-1 whitespace-nowrap"
          >
            <PencilIcon className="h-3 w-3" />
            <span className="hidden sm:inline">{isEditing ? 'Cancel Edit' : 'Edit Profile'}</span>
            <span className="sm:hidden text-xs">{isEditing ? 'Cancel' : 'Edit'}</span>
          </button>
          <button
            onClick={() => setShowSettings(true)}
            className="flex-1 sm:flex-none px-3 py-2 sm:py-1.5 border border-amber-500/30 text-amber-50 rounded hover:bg-amber-500/10 transition-all duration-200 font-medium text-xs sm:text-sm items-center justify-center gap-1 whitespace-nowrap"
          >
            <Cog6ToothIcon className="h-3 w-3" />
            <span className="hidden sm:inline">Preferences</span>
          </button>
        </div>
      </div>

      {/* Success Messages */}
      {profileSuccess && (
        <div className="bg-green-500/10 border border-green-500/30 rounded-lg p-3 animate-fadeIn">
          <div className="flex items-center">
            <CheckCircleIcon className="h-4 w-4 text-green-400 mr-2" />
            <p className="text-green-300 text-sm">{profileSuccess}</p>
          </div>
        </div>
      )}

      {/* Profile Overview */}
      <div className={`${isDarkMode ? 'bg-gray-900 border-amber-500/20' : 'bg-white border-amber-300/40'} border rounded-lg shadow p-6 hover:border-amber-500/40 transition-all duration-300`}>
        <div className="flex items-center space-x-3 mb-6">
          <div className="w-12 h-12 bg-gradient-to-r from-amber-500 to-amber-600 rounded-full flex items-center justify-center text-gray-900 text-sm font-bold shadow">
            {user?.first_name?.charAt(0)}{user?.last_name?.charAt(0)}
          </div>
          <div>
            <h3 className={`text-sm font-bold ${isDarkMode ? 'text-amber-50' : 'text-amber-900'}`}>{user?.first_name} {user?.last_name}</h3>
            <p className={`text-xs ${isDarkMode ? 'text-amber-400/70' : 'text-amber-700/70'}`}>Client Account</p>
            <p className={`text-xs ${isDarkMode ? 'text-amber-400/70' : 'text-amber-700/70'}`}>Member since {new Date(user?.created_at).toLocaleDateString()}</p>
          </div>
        </div>

        {/* Profile Information Form */}
        <div className="mb-6">
          <h4 className={`text-sm font-semibold ${isDarkMode ? 'text-amber-50' : 'text-amber-900'} mb-4 flex items-center`}>
            <UserIcon className="h-4 w-4 mr-2" />
            Personal Information
          </h4>
          
          <form onSubmit={handleProfileSubmit} className="space-y-4">
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label className={`block text-xs font-medium ${isDarkMode ? 'text-amber-50' : 'text-amber-900'} mb-1`}>
                  Username *
                </label>
                <input
                  type="text"
                  name="username"
                  value={profileData.username}
                  onChange={handleProfileChange}
                  disabled={!isEditing}
                  className={`w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-500 focus:border-amber-500 transition-all duration-200 text-sm ${isDarkMode ? 'bg-gray-800 border-gray-600 text-white disabled:bg-gray-800/50 disabled:text-gray-400' : 'bg-white border-gray-300 text-gray-900 disabled:bg-gray-100 disabled:text-gray-500'} disabled:cursor-not-allowed`}
                  required
                />
              </div>

              <div>
                <label className={`block text-xs font-medium ${isDarkMode ? 'text-amber-50' : 'text-amber-900'} mb-1`}>
                  Email Address *
                </label>
                <input
                  type="email"
                  name="email"
                  value={profileData.email}
                  onChange={handleProfileChange}
                  disabled={!isEditing}
                  className={`w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-500 focus:border-amber-500 transition-all duration-200 text-sm ${isDarkMode ? 'bg-gray-800 border-gray-600 text-white disabled:bg-gray-800/50 disabled:text-gray-400' : 'bg-white border-gray-300 text-gray-900 disabled:bg-gray-100 disabled:text-gray-500'} disabled:cursor-not-allowed`}
                  required
                />
              </div>

              <div>
                <label className={`block text-xs font-medium ${isDarkMode ? 'text-amber-50' : 'text-amber-900'} mb-1`}>
                  First Name *
                </label>
                <input
                  type="text"
                  name="first_name"
                  value={profileData.first_name}
                  onChange={handleProfileChange}
                  disabled={!isEditing}
                  className={`w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-500 focus:border-amber-500 transition-all duration-200 text-sm ${isDarkMode ? 'bg-gray-800 border-gray-600 text-white disabled:bg-gray-800/50 disabled:text-gray-400' : 'bg-white border-gray-300 text-gray-900 disabled:bg-gray-100 disabled:text-gray-500'} disabled:cursor-not-allowed`}
                  required
                />
              </div>

              <div>
                <label className={`block text-xs font-medium ${isDarkMode ? 'text-amber-50' : 'text-amber-900'} mb-1`}>
                  Last Name *
                </label>
                <input
                  type="text"
                  name="last_name"
                  value={profileData.last_name}
                  onChange={handleProfileChange}
                  disabled={!isEditing}
                  className={`w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-500 focus:border-amber-500 transition-all duration-200 text-sm ${isDarkMode ? 'bg-gray-800 border-gray-600 text-white disabled:bg-gray-800/50 disabled:text-gray-400' : 'bg-white border-gray-300 text-gray-900 disabled:bg-gray-100 disabled:text-gray-500'} disabled:cursor-not-allowed`}
                  required
                />
              </div>

              <div>
                <label className={`block text-xs font-medium ${isDarkMode ? 'text-amber-50' : 'text-amber-900'} mb-1`}>
                  Phone Number
                </label>
                <div className="relative">
                  <PhoneIcon className="absolute left-3 top-1/2 transform -translate-y-1/2 h-3 w-3 text-amber-400" />
                  <input
                    type="tel"
                    name="phone"
                    value={profileData.phone}
                    onChange={handleProfileChange}
                    disabled={!isEditing}
                    className={`w-full pl-9 pr-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-500 focus:border-amber-500 transition-all duration-200 text-sm ${isDarkMode ? 'bg-gray-800 border-gray-600 text-white disabled:bg-gray-800/50 disabled:text-gray-400' : 'bg-white border-gray-300 text-gray-900 disabled:bg-gray-100 disabled:text-gray-500'} disabled:cursor-not-allowed`}
                  />
                </div>
              </div>

              <div>
                <label className={`block text-xs font-medium ${isDarkMode ? 'text-amber-50' : 'text-amber-900'} mb-1`}>
                  New Password
                </label>
                <div className="relative">
                  <KeyIcon className="absolute left-3 top-1/2 transform -translate-y-1/2 h-3 w-3 text-amber-400" />
                  <input
                    type="password"
                    name="password"
                    value={profileData.password}
                    onChange={handleProfileChange}
                    disabled={!isEditing}
                    placeholder="Leave blank to keep current password"
                    className={`w-full pl-9 pr-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-500 focus:border-amber-500 transition-all duration-200 text-sm ${isDarkMode ? 'bg-gray-800 border-gray-600 text-white disabled:bg-gray-800/50 disabled:text-gray-400 placeholder-gray-400' : 'bg-white border-gray-300 text-gray-900 disabled:bg-gray-100 disabled:text-gray-500 placeholder-gray-400'} disabled:cursor-not-allowed`}
                  />
                </div>
              </div>

              <div className="md:col-span-2">
                <label className={`block text-xs font-medium ${isDarkMode ? 'text-amber-50' : 'text-amber-900'} mb-1`}>
                  Address
                </label>
                <div className="relative">
                  <MapPinIcon className="absolute left-3 top-3 h-3 w-3 text-amber-400" />
                  <textarea
                    name="address"
                    value={profileData.address}
                    onChange={handleProfileChange}
                    disabled={!isEditing}
                    rows="2"
                    className={`w-full pl-9 pr-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-500 focus:border-amber-500 transition-all duration-200 text-sm ${isDarkMode ? 'bg-gray-800 border-gray-600 text-white disabled:bg-gray-800/50 disabled:text-gray-400' : 'bg-white border-gray-300 text-gray-900 disabled:bg-gray-100 disabled:text-gray-500'} disabled:cursor-not-allowed resize-none`}
                  />
                </div>
              </div>
            </div>

            {isEditing && (
              <div className={`flex justify-end space-x-3 pt-4 border-t ${isDarkMode ? 'border-gray-700' : 'border-gray-200'}`}>
                <button
                  type="button"
                  onClick={() => setIsEditing(false)}
                  className={`px-4 py-2 border rounded-lg transition-all duration-200 font-medium text-sm focus:outline-none focus:ring-2 focus:ring-amber-500 focus:ring-offset-2 hover:scale-105 ${isDarkMode ? 'border-gray-600 text-gray-300 hover:bg-gray-800 focus:ring-offset-gray-900' : 'border-gray-300 text-gray-600 hover:bg-gray-100 focus:ring-offset-white'}`}
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  disabled={loading}
                  className={`px-4 py-2 bg-gradient-to-r from-amber-600 to-amber-700 text-white rounded-lg hover:from-amber-700 hover:to-amber-800 focus:outline-none focus:ring-2 focus:ring-amber-500 focus:ring-offset-2 transition-all duration-200 font-medium text-sm shadow border border-amber-500/30 disabled:opacity-50 hover:scale-105 ${isDarkMode ? 'focus:ring-offset-gray-900' : 'focus:ring-offset-white'}`}
                >
                  {loading ? (
                    <div className="flex items-center">
                      <div className="w-3 h-3 border-2 border-white border-t-transparent rounded-full mr-2"></div>
                      Saving...
                    </div>
                  ) : (
                    'Save Changes'
                  )}
                </button>
              </div>
            )}
          </form>
        </div>

        {/* Password Change Section */}
        <div className={`border-t pt-6 ${isDarkMode ? 'border-gray-700' : 'border-gray-200'}`}>
          <h4 className={`text-sm font-semibold ${isDarkMode ? 'text-amber-50' : 'text-amber-900'} mb-4 flex items-center`}>
            <KeyIcon className="h-4 w-4 mr-2" />
            Change Password
          </h4>
          
          {passwordSuccess && (
            <div className="bg-green-500/10 border border-green-500/30 rounded-lg p-3 mb-4 animate-fadeIn">
              <div className="flex items-center">
                <CheckCircleIcon className="h-4 w-4 text-green-400 mr-2" />
                <p className="text-green-300 text-sm">{passwordSuccess}</p>
              </div>
            </div>
          )}

          {passwordErrors.general && (
            <div className="bg-red-500/10 border border-red-500/30 rounded-lg p-3 mb-4 animate-fadeIn">
              <div className="flex items-center">
                <ExclamationTriangleIcon className="h-4 w-4 text-red-400 mr-2" />
                <p className="text-red-300 text-sm">{passwordErrors.general}</p>
              </div>
            </div>
          )}

          <form onSubmit={handlePasswordSubmit} className="space-y-4">
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label className={`block text-xs font-medium ${isDarkMode ? 'text-amber-50' : 'text-amber-900'} mb-1`}>
                  Current Password *
                </label>
                <input
                  type="password"
                  name="current_password"
                  value={passwordData.current_password}
                  onChange={handlePasswordChange}
                  className={`w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-500 focus:border-amber-500 transition-all duration-200 text-sm ${isDarkMode ? 'bg-gray-800 border-gray-600 text-white' : 'bg-white border-gray-300 text-gray-900'}`}
                  required
                />
              </div>

              <div>
                <label className={`block text-xs font-medium ${isDarkMode ? 'text-amber-50' : 'text-amber-900'} mb-1`}>
                  New Password *
                </label>
                <input
                  type="password"
                  name="new_password"
                  value={passwordData.new_password}
                  onChange={handlePasswordChange}
                  className={`w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-500 focus:border-amber-500 transition-all duration-200 text-sm ${isDarkMode ? 'bg-gray-800 border-gray-600 text-white' : 'bg-white border-gray-300 text-gray-900'}`}
                  required
                />
              </div>

              <div className="md:col-span-2">
                <label className={`block text-xs font-medium ${isDarkMode ? 'text-amber-50' : 'text-amber-900'} mb-1`}>
                  Confirm New Password *
                </label>
                <input
                  type="password"
                  name="new_password_confirmation"
                  value={passwordData.new_password_confirmation}
                  onChange={handlePasswordChange}
                  className={`w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-500 focus:border-amber-500 transition-all duration-200 text-sm ${isDarkMode ? 'bg-gray-800 border-gray-600 text-white' : 'bg-white border-gray-300 text-gray-900'}`}
                  required
                />
              </div>
            </div>

            <div className={`flex justify-end pt-4 border-t ${isDarkMode ? 'border-gray-700' : 'border-gray-200'}`}>
              <button
                type="submit"
                disabled={loading}
                className={`px-4 py-2 bg-gradient-to-r from-amber-600 to-amber-700 text-white rounded-lg hover:from-amber-700 hover:to-amber-800 focus:outline-none focus:ring-2 focus:ring-amber-500 focus:ring-offset-2 transition-all duration-200 font-medium text-sm shadow border border-amber-500/30 disabled:opacity-50 hover:scale-105 ${isDarkMode ? 'focus:ring-offset-gray-900' : 'focus:ring-offset-white'}`}
              >
                {loading ? (
                  <div className="flex items-center">
                    <div className="w-3 h-3 border-2 border-white border-t-transparent rounded-full mr-2"></div>
                    Updating...
                  </div>
                ) : (
                  'Change Password'
                )}
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  );

  const renderContent = () => {
    switch (activeTab) {
      case 'home': return renderHome();
      case 'book': return renderBookAppointment();
      case 'appointments': return renderAppointments();
      case 'messages': return renderMessages();
      case 'refunds': return renderRefunds();
      case 'action-logs': return <ActionLogViewer isDarkMode={isDarkMode} />;
      case 'feedback': return <UserFeedback />;
      case 'profile': return renderProfile();
      case 'settings': 
        return (
          <div className="space-y-6">
            <div className="hidden lg:block">
              <h2 className={`text-lg font-bold ${isDarkMode ? 'text-amber-50' : 'text-amber-900'}`}>Settings</h2>
              <p className={`mt-1 text-sm ${isDarkMode ? 'text-amber-400/70' : 'text-amber-700/70'}`}>Manage your application preferences</p>
            </div>
            <div className="flex flex-col sm:flex-row gap-3">
              <button
                onClick={() => setShowSettings(true)}
                className="px-4 py-2 bg-gradient-to-r from-amber-600 to-amber-700 text-white rounded-lg hover:from-amber-700 hover:to-amber-800 transition-all duration-200 font-medium"
              >
                Open Settings
              </button>
              <button
                onClick={() => setShowAboutUs(true)}
                className="px-4 py-2 bg-gradient-to-r from-cyan-600 to-blue-700 text-white rounded-lg hover:from-cyan-700 hover:to-blue-800 transition-all duration-200 font-medium"
              >
                About Us
              </button>
            </div>
          </div>
        );
      default: return renderHome();
    }
  };

  // Show loading or redirect message while redirecting
  if (redirecting || user?.role === 'admin' || user?.role === 'staff') {
    return (
      <div className="min-h-screen bg-gradient-to-br from-gray-900 to-black flex items-center justify-center">
        <div className="text-center">
          <div className="w-12 h-12 border-4 border-amber-500 border-t-transparent rounded-full mx-auto mb-3"></div>
          <p className="text-amber-100 text-sm">Redirecting to your dashboard...</p>
        </div>
      </div>
    );
  }

  return (
    <div className={`h-screen ${isDarkMode ? 'bg-gray-900' : 'bg-gray-50'} flex flex-col lg:flex-row transition-colors duration-300 overflow-hidden`}>
      {/* Mobile Hamburger Menu - Hidden on all mobile screens */}
      {/* Removed: Full header with menu button now hidden on mobile */}

      {/* Mobile Sidebar Overlay */}
      {showMobileSidebar && (
        <div
          className="lg:hidden fixed inset-0 bg-black/50 z-30 transition-opacity duration-200"
          onClick={() => setShowMobileSidebar(false)}
        ></div>
      )}

      {/* Sidebar - Hidden on mobile by default, shown on desktop and when toggled on mobile */}
      <div className={`hidden lg:block fixed inset-y-0 right-0 lg:right-auto lg:left-0 z-40 w-64 h-screen ${isDarkMode ? 'bg-gray-900 border-amber-500/20' : 'bg-white border-amber-300/40'} border-l lg:border-l-0 lg:border-r shadow-xl transition-all duration-300 lg:translate-x-0 ${
        showMobileSidebar ? 'translate-x-0' : 'translate-x-full lg:translate-x-0'
      }`}>
        <div className="flex flex-col h-full">
          {/* Logo Section */}
          <div className={`p-4 shadow-md ${isDarkMode ? 'bg-gray-800 border-amber-500/30' : 'bg-gray-50 border-amber-300/50'} px-3 border-b transition-colors duration-300`}>
            <div className="flex items-center justify-center space-x-3">
              <div className="w-10 h-10 bg-gradient-to-br from-amber-400 to-amber-600 rounded-xl flex items-center justify-center shadow-lg shadow-amber-500/30 flex-shrink-0">
                <BuildingLibraryIcon className="h-5 w-5 text-white" />
              </div>
              <div className="flex-1 min-w-0">
                <h1 className={`text-sm font-bold tracking-wider ${isDarkMode ? 'text-amber-50' : 'text-amber-900'} transition-colors duration-300 truncate`}>LEGAL EASE</h1>
                <p className={`text-xs ${isDarkMode ? 'text-amber-400/60' : 'text-amber-700/60'}`}>Notarization</p>
              </div>
              <button
                onClick={() => setShowMobileSidebar(false)}
                className="lg:hidden text-gray-400 hover:text-amber-400 transition-colors p-1 flex-shrink-0"
              >
                <XMarkIcon className="h-5 w-5" />
              </button>
            </div>
          </div>

          {/* User Profile Section */}
          <div className={`mx-3 mt-4 p-3 rounded-lg border ${isDarkMode ? 'bg-gray-800/50 border-amber-500/20' : 'bg-white/50 border-amber-300/30'} transition-colors duration-300`}>
            <div className="flex items-center space-x-3">
              <div className="w-10 h-10 bg-gradient-to-br from-amber-400 to-amber-600 rounded-full flex items-center justify-center text-gray-900 text-xs font-bold shadow flex-shrink-0">
                {user?.first_name?.charAt(0)}{user?.last_name?.charAt(0)}
              </div>
              <div className="flex-1 min-w-0">
                <p className={`text-xs font-semibold ${isDarkMode ? 'text-amber-50' : 'text-gray-900'} truncate`}>{user?.first_name} {user?.last_name}</p>
                <p className={`text-xs ${isDarkMode ? 'text-amber-400/60' : 'text-gray-600'} truncate`}>{user?.email}</p>
              </div>
            </div>
          </div>

          {/* Navigation */}
          <nav className="flex-1 px-2.5 py-2.5 space-y-2.5 overflow-y-auto">
            {navigation.map((section) => (
              <div key={section.section} className="space-y-2">
                <div className="px-2.5 py-0.5">
                  <span className={`text-xs font-semibold ${isDarkMode ? 'text-amber-400/70' : 'text-amber-700/70'} uppercase tracking-wider`}>
                    {section.section}
                  </span>
                </div>
                <div className="space-y-0.5">
                  {section.items.map((item) => (
                    <button
                      key={item.name}
                      onClick={() => {
                        let tabName = item.name;
                        if (tabName === 'Book Appointment') tabName = 'book';
                        else if (tabName === 'My Appointments') tabName = 'appointments';
                        else if (tabName === 'Action Logs') tabName = 'action-logs';
                        else tabName = tabName.toLowerCase().replace(/\s+/g, '-');
                        handleNavClick(tabName);
                        setShowMobileSidebar(false);
                      }}
                      className={`w-full flex items-center justify-between px-2.5 py-1.5 text-xs lg:text-xs font-medium rounded-none transition-all duration-200 border group relative overflow-hidden ${
                        item.current
                          ? (isDarkMode ? 'text-amber-400 border-amber-500/30' : 'text-gray-900 border-amber-300/30')
                          : (isDarkMode ? 'text-gray-400 border-transparent hover:text-amber-300' : 'text-gray-700 border-transparent hover:text-gray-900')
                      }`}
                    >
                      <div className="flex items-center flex-1 min-w-0">
                        <item.icon className={`mr-2.5 h-4 w-4 transition-all duration-200 flex-shrink-0 ${
                          item.current ? 'text-amber-400 scale-110' : 'text-gray-500 group-hover:text-amber-400 group-hover:scale-105'
                        }`} />
                        <span className="truncate">{item.name}</span>
                      </div>
                      {item.badge !== null && item.badge > 0 && (
                        <span className={`ml-2 px-2 py-0.5 rounded-full text-xs font-bold flex-shrink-0 ${
                          item.current
                            ? 'bg-amber-500 text-white shadow-lg shadow-amber-500/30'
                            : `${isDarkMode ? 'bg-gray-700 text-gray-200' : 'bg-gray-200 text-gray-700'} group-hover:bg-amber-500 group-hover:text-white`
                        }`}>
                          {item.badge}
                        </span>
                      )}
                    </button>
                  ))}
                </div>
              </div>
            ))}
          </nav>

          {/* Footer with Settings (Desktop only) and Logout */}
          <div className={`p-3 border-t ${isDarkMode ? 'border-amber-500/20 bg-gray-900/50' : 'border-amber-300/30 bg-gray-50/50'} space-y-2 transition-colors duration-300`}>
            {/* Settings Button - Hidden on mobile */}
            <button
              onClick={() => {
                setShowSettings(true);
                setShowMobileSidebar(false);
              }}
              className={`hidden lg:flex w-full items-center px-3 py-2 text-xs font-medium rounded-lg transition-all duration-200 border ${
                isDarkMode
                  ? 'text-gray-400 border-gray-700 hover:border-amber-500/30 hover:bg-amber-500/8 hover:text-amber-300'
                  : 'text-gray-600 border-gray-300 hover:border-amber-400/50 hover:bg-amber-200/10 hover:text-amber-700'
              }`}
            >
              <Cog6ToothIcon className="mr-2 h-4 w-4 flex-shrink-0" />
              Settings
            </button>

            {/* Logout Button */}
            <button
              onClick={() => setShowLogoutModal(true)}
              className={`w-full flex items-center px-3 py-2 text-xs font-medium rounded-lg border transition-all duration-200 ${
                isDarkMode
                  ? 'text-red-400 border-red-500/30 hover:bg-red-500/10 hover:text-red-300 hover:border-red-500/50'
                  : 'text-white bg-red-600 border-red-700 hover:bg-red-700 hover:border-red-800 active:bg-red-800'
              }`}
            >
              <ArrowPathIcon className="mr-2 h-4 w-4 flex-shrink-0" />
              Logout
            </button>
          </div>
        </div>
      </div>

      {/* Main content */}
      <div className="flex-1 flex flex-col min-w-0 lg:mt-0 mt-0 lg:ml-64 h-auto lg:h-screen overflow-y-auto lg:overflow-hidden lg:h-screen">
        {/* Header */}
        <header className={`${isDarkMode ? 'bg-gray-900 border-amber-500/20' : 'bg-gray-50 border-amber-300/40'} border-b shadow flex-shrink-0 transition-colors duration-300 hidden lg:flex flex-col`}>
          <div className="flex justify-between items-center px-4 lg:px-6 py-3 lg:py-4">
            <div className="flex items-center space-x-3 min-w-0">
              <div>
                <h1 className={`text-base lg:text-lg font-bold ${isDarkMode ? 'text-amber-50' : 'text-amber-900'} transition-colors duration-300`}>
                  {(() => {
                    for (const section of navigation) {
                      const found = section.items?.find(item => item.current);
                      if (found) return found.name;
                    }
                    return 'Dashboard';
                  })()}
                </h1>
                <p className={`${isDarkMode ? 'text-amber-400/70' : 'text-amber-700/70'} mt-0.5 text-xs lg:text-sm capitalize transition-colors duration-300 hidden sm:block`}>
                  Welcome back, {user?.first_name} {user?.last_name}
                </p>
              </div>
              {activeTab !== 'home' && (
                <button
                  onClick={loadInitialData}
                  className={`p-1.5 ml-2 flex-shrink-0 ${isDarkMode ? 'text-amber-400 hover:text-amber-300 hover:bg-amber-500/10 border-amber-500/30' : 'text-amber-700 hover:text-amber-600 hover:bg-amber-500/20 border-amber-300/30'} rounded border transition-colors duration-200`}
                  title="Refresh data"
                >
                  <ArrowPathIcon className="h-3 w-3 lg:h-4 lg:w-4" />
                </button>
              )}
            </div>
            <div className="flex-shrink-0">
              <div className={`text-xs lg:text-sm ${isDarkMode ? 'text-amber-400/70' : 'text-amber-700/70'} transition-colors duration-300 text-right`}>
                {new Date().toLocaleDateString('en-US', { 
                  weekday: 'short', 
                  year: 'numeric', 
                  month: 'short', 
                  day: 'numeric' 
                })}
              </div>
            </div>
          </div>
        </header>

        {/* Error Alert */}
        {error && (
          <div className="mx-2 sm:mx-4 mt-4 bg-red-500/10 border border-red-500/30 rounded-lg p-2 sm:p-3 animate-fadeIn">
            <div className="flex justify-between items-center gap-2">
              <div className="flex items-center min-w-0">
                <ExclamationTriangleIcon className="h-4 w-4 text-red-400 mr-1.5 flex-shrink-0" />
                <p className="text-red-300 text-xs line-clamp-2">{error}</p>
              </div>
              <button
                onClick={clearError}
                className="text-red-400 hover:text-red-300 transition-colors duration-200 p-0.5 rounded hover:bg-red-500/10 flex-shrink-0"
              >
                <XMarkIcon className="h-3 w-3" />
              </button>
            </div>
          </div>
        )}

        {/* Page content */}
        <main className={`flex-1 p-3 sm:p-4 lg:p-6 pb-24 lg:pb-6 overflow-y-auto scrollbar-hide ${isDarkMode ? '' : 'bg-gray-100'} transition-colors duration-300`}>
          {renderContent()}
        </main>
      </div>

      {/* Modals */}
      <AppointmentDetailModal
        isOpen={showAppointmentDetail}
        onClose={() => {
          setShowAppointmentDetail(false);
          setSelectedAppointment(null);
        }}
        appointment={selectedAppointment}
      />

      <ConfirmationModal
        isOpen={showCancelModal}
        onClose={() => {
          setShowCancelModal(false);
          setSelectedAppointment(null);
        }}
        onConfirm={handleCancelAppointment}
        title="Cancel Appointment"
        message={`Are you sure you want to cancel your ${formatServiceName(selectedAppointment)} appointment on ${selectedAppointment ? new Date(selectedAppointment.appointment_date).toLocaleDateString() : ''}? This action cannot be undone.`}
        confirmText="Cancel Appointment"
        type="danger"
        loading={loading}
        isDarkMode={isDarkMode}
      />

      <ConfirmationModal
        isOpen={showLogoutModal}
        onClose={() => !isLoggingOut && setShowLogoutModal(false)}
        onConfirm={confirmLogout}
        title="Confirm Logout"
        message="Are you sure you want to log out? You will need to sign in again to access your account."
        confirmText="Yes, Logout"
        type="danger"
        loading={isLoggingOut}
        isDarkMode={isDarkMode}
      />

      <SettingsModal
        isOpen={showSettings}
        onClose={() => setShowSettings(false)}
        settings={settings}
        onSettingsChange={handleSettingsChange}
      />

      <AboutUsModal
        isOpen={showAboutUs}
        onClose={() => setShowAboutUs(false)}
        isDarkMode={isDarkMode}
      />

      <ThankYouModal
        isOpen={showThankYouModal}
        onClose={() => setShowThankYouModal(false)}
        appointment={latestAppointment}
      />

      {/* Refund Request Modal */}
      {showRefundModal && selectedAppointment && (
        <div className="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4 overflow-y-auto">
          <div className="bg-gray-900 rounded-lg shadow-2xl max-w-2xl w-full my-8 border border-amber-500/20">
            {/* Header */}
            <div className="bg-gradient-to-r from-amber-900 to-gray-900 border-b border-amber-500/20 p-6 flex items-center justify-between sticky top-0">
              <div>
                <h2 className="text-2xl font-bold text-amber-50">Request Refund</h2>
                <p className="text-amber-400/70 text-sm mt-1">Appointment #{selectedAppointment.id}</p>
              </div>
              <button
                onClick={() => {
                  setShowRefundModal(false);
                  setSelectedAppointment(null);
                  setRefundData({ reason: 'customer_request', description: '' });
                }}
                className="text-amber-400 hover:text-amber-300 p-2 rounded hover:bg-amber-500/10 transition-colors"
              >
                <XMarkIcon className="h-6 w-6" />
              </button>
            </div>

            {/* Content */}
            <div className="p-6 space-y-6 max-h-[calc(100vh-200px)] overflow-y-auto">
              {/* Appointment Details */}
              <div className="bg-gray-800/50 p-4 rounded-lg border border-gray-700">
                <h3 className="font-semibold text-amber-50 mb-3">📅 Appointment Details</h3>
                <div className="grid grid-cols-2 gap-3 text-sm">
                  <div>
                    <p className="text-gray-400">Service Type</p>
                    <p className="text-amber-50 font-medium">{formatServiceName(selectedAppointment)}</p>
                  </div>
                  <div>
                    <p className="text-gray-400">Status</p>
                    <p className="text-green-400 font-medium">{selectedAppointment.status?.charAt(0).toUpperCase() + selectedAppointment.status?.slice(1)}</p>
                  </div>
                  <div>
                    <p className="text-gray-400">Date</p>
                    <p className="text-amber-50 font-medium">{new Date(selectedAppointment.appointment_date).toLocaleDateString()}</p>
                  </div>
                  <div>
                    <p className="text-gray-400">Time</p>
                    <p className="text-amber-50 font-medium">{selectedAppointment.appointment_time}</p>
                  </div>
                </div>
              </div>

                           {/* User Information */}
              <div className="bg-gray-800/50 p-4 rounded-lg border border-gray-700">
                <h3 className="font-semibold text-amber-50 mb-3">👤 User Information</h3>
                <div className="space-y-2 text-sm">
                  <div className="text-gray-400">
                    <span className="text-amber-300 font-medium">Name:</span>{" "}
                    {selectedAppointment.user?.first_name}{" "}
                    {selectedAppointment.user?.last_name}
                  </div>

                  {user && user.role !== "admin" && user.role !== "staff" && (
                    <div
                      className={`block sm:hidden p-3 rounded-lg border ${
                        isDarkMode
                          ? "bg-gray-800/50 border-amber-500/20"
                          : "bg-white/50 border-amber-300/30"
                      } transition-colors duration-200`}
                    >
                      <div className="grid grid-cols-4 gap-2">
                        <button
                          onClick={() => setActiveTab("refunds")}
                          className="flex flex-col items-center justify-center p-2 rounded-lg border text-xs text-amber-50 bg-gray-800/40 hover:bg-amber-500/10"
                          title="Refunds"
                        >
                          <CurrencyDollarIcon className="h-5 w-5 mb-1" />
                          <span className="text-[11px]">Refunds</span>
                        </button>

                        <button
                          onClick={() => setActiveTab("action-logs")}
                          className="flex flex-col items-center justify-center p-2 rounded-lg border text-xs text-amber-50 bg-gray-800/40 hover:bg-amber-500/10"
                          title="Action Logs"
                        >
                          <DocumentTextIcon className="h-5 w-5 mb-1" />
                          <span className="text-[11px]">Actions</span>
                        </button>

                        <button
                          onClick={handleLogout}
                          className="flex flex-col items-center justify-center p-2 rounded-lg border text-xs text-red-400 bg-gray-800/40 hover:bg-red-500/8"
                          title="Logout"
                        >
                          <ArrowPathIcon className="h-5 w-5 mb-1" />
                          <span className="text-[11px]">Logout</span>
                        </button>
                      </div>
                    </div>
                  )}

                  <div>
                    <span className="text-green-300 font-semibold">
                      Total Refundable Amount:
                    </span>{" "}
                    <span className="text-green-300 font-bold">
                      ₱
                      {parseFloat(
                        selectedAppointment.payment_amount || 0
                      ).toFixed(2)}
                    </span>
                  </div>
                </div>
              </div>

              {/* Refund Form */}
              <form onSubmit={handleRequestRefund} className="space-y-4">
                <div>
                  <label className="block text-sm font-medium text-amber-400 mb-2">
                    Refund Reason *
                  </label>
                  <select
                    value={refundData.reason}
                    onChange={(e) =>
                      setRefundData({
                        ...refundData,
                        reason: e.target.value,
                      })
                    }
                    className="w-full px-4 py-2 bg-gray-800 border border-gray-600 rounded-lg text-amber-50 focus:outline-none focus:ring-2 focus:ring-amber-500 transition-all"
                    required
                  >
                    <option value="customer_request">
                      Customer Request
                    </option>
                    <option value="service_not_provided">
                      Service Not Provided
                    </option>
                    <option value="duplicate_payment">
                      Duplicate Payment
                    </option>
                    <option value="service_cancellation">
                      Service Cancellation
                    </option>
                    <option value="poor_service">Poor Service</option>
                    <option value="other">Other</option>
                  </select>
                </div>

                <div>
                  <label className="block text-sm font-medium text-amber-400 mb-2">
                    Description (Optional)
                  </label>
                  <textarea
                    value={refundData.description}
                    onChange={(e) =>
                      setRefundData({
                        ...refundData,
                        description: e.target.value,
                      })
                    }
                    placeholder="Please provide additional details about your refund request..."
                    className="w-full px-4 py-2 bg-gray-800 border border-gray-600 rounded-lg text-amber-50 placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-amber-500 transition-all"
                    rows="4"
                  />
                </div>

                <div className="bg-blue-500/10 border border-blue-500/30 p-3 rounded-lg">
                  <p className="text-sm text-blue-300">
                    <strong>Note:</strong> Your refund request will be reviewed by
                    our admin team. You will receive a notification once your
                    request is approved or declined.
                  </p>
                </div>

                <div className="flex gap-3">
                  <button
                    type="submit"
                    disabled={refundLoading}
                    className="flex-1 px-4 py-2 bg-gradient-to-r from-green-600 to-green-700 text-white rounded-lg hover:from-green-700 hover:to-green-800 disabled:from-gray-600 disabled:to-gray-700 transition-all font-medium border border-green-500/30"
                  >
                    {refundLoading
                      ? "Submitting..."
                      : "Submit Refund Request"}
                  </button>
                  <button
                    type="button"
                    onClick={() => {
                      setShowRefundModal(false);
                      setSelectedAppointment(null);
                      setRefundData({
                        reason: "customer_request",
                        description: "",
                      });
                    }}
                    className="flex-1 px-4 py-2 bg-gray-700 text-gray-50 rounded-lg hover:bg-gray-600 transition-all font-medium border border-gray-600"
                  >
                    Cancel
                  </button>
                </div>
              </form>
            </div>
          </div>
        </div>
      )}


      {/* Mobile bottom navigation for user side and ProfilePage overlay */}
      {user && user.role !== "admin" && user.role !== "staff" && (
        <>
          <BottomNav />
          {showProfileMenu && (
            <div className="fixed inset-0 z-[100] flex items-start justify-center bg-black/40 md:hidden">
              <div className="w-full h-full flex items-start justify-center">
                <ProfilePage 
                  onBack={() => {
                    setShowProfileMenu(false);
                    setActiveTab('home');
                  }} 
                  onTabChange={(tabName) => {
                    setShowProfileMenu(false);
                    setActiveTab(tabName);
                  }}
                  onLogout={() => setShowLogoutModal(true)}
                />
              </div>
            </div>
          )}
        </>
      )}

    </div>
  );
};

export default Dashboard;
