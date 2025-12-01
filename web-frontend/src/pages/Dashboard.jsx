import { useState, useEffect } from 'react';
import { useAuth } from '../context/AuthContext';
import { useApi } from '../hooks/useApi';
import { Link, useNavigate, useLocation } from 'react-router-dom';
import axios from 'axios';
import TimePicker from '../components/TimePicker';
import ActionLogViewer from '../components/ActionLogViewer';
import MessageCenter from './MessageCenter';
import { formatServiceName } from '../utils/format';
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
  Bars3Icon
} from '@heroicons/react/24/outline';

// Enhanced Status Badge Component
const StatusBadge = ({ status }) => {
  const statusConfig = {
    pending: { 
      color: 'bg-amber-500/20 text-amber-300 border border-amber-500/30', 
      icon: ClockIcon,
      glow: 'shadow-amber-500/20'
    },
    approved: { 
      color: 'bg-blue-500/20 text-blue-300 border border-blue-500/30', 
      icon: CheckCircleIcon,
      glow: 'shadow-blue-500/20'
    },
    completed: { 
      color: 'bg-green-500/20 text-green-300 border border-green-500/30', 
      icon: CheckCircleIcon,
      glow: 'shadow-green-500/20'
    },
    cancelled: { 
      color: 'bg-red-500/20 text-red-300 border border-red-500/30', 
      icon: XCircleIcon,
      glow: 'shadow-red-500/20'
    },
    declined: { 
      color: 'bg-red-500/20 text-red-300 border border-red-500/30', 
      icon: XCircleIcon,
      glow: 'shadow-red-500/20'
    }
  };
  
  const config = statusConfig[status] || statusConfig.pending;
  const IconComponent = config.icon;
  
  return (
    <span className={`inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium ${config.color} ${config.glow} shadow hover:scale-105 transition-transform duration-200`}>
      <IconComponent className="w-3 h-3 mr-1" />
      {status.charAt(0).toUpperCase() + status.slice(1)}
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
  otherValue 
}) => {
  const [isOpen, setIsOpen] = useState(false);
  const [searchTerm, setSearchTerm] = useState('');
  const [showOtherInput, setShowOtherInput] = useState(value === 'other');

  const filteredOptions = options.filter(option => 
    option.label.toLowerCase().includes(searchTerm.toLowerCase())
  );

  const handleSelect = (optionValue, optionLabel) => {
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
        onClick={() => setIsOpen(!isOpen)}
        className={`w-full px-3 py-2 bg-gray-800 border rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-500 transition-all duration-200 text-sm text-white text-left flex justify-between items-center ${
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
        <ChevronDownIcon className={`h-4 w-4 text-amber-400 transition-transform flex-shrink-0 ${isOpen ? 'rotate-180' : ''}`} />
      </button>

      {/* Dropdown Menu */}
      {isOpen && (
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
                className="w-full px-3 py-2 text-left text-xs text-amber-50 hover:bg-amber-500/10 hover:text-amber-300 transition-colors duration-200 flex items-center justify-between"
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
              className="w-full px-3 py-2 text-left text-xs text-amber-50 hover:bg-amber-500/10 hover:text-amber-300 transition-colors duration-200 flex items-center justify-between border-t border-gray-600"
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
            placeholder="Please specify the service type..."
            value={otherValue}
            onChange={handleOtherInputChange}
            className="w-full px-3 py-2 bg-gray-800 border border-amber-500/30 rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-500 transition-all duration-200 text-sm text-white placeholder-gray-400"
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
const EnhancedCalendar = ({ value, onChange, error }) => {
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
    const dateString = date.toISOString().split('T')[0];
    setSelectedDate(date);
    onChange(dateString);
    setIsOpen(false);
  };

  const isDateDisabled = (date) => {
    const dateStr = date.toISOString().split('T')[0];

    // Past dates disabled
    if (date < minDate) return true;

    // Weekends disabled
    const day = date.getDay();
    if (day === 0 || day === 6) return true;

    // Admin-set unavailable/blackout dates
    if (unavailableDates.some(u => {
      const uDate = (u.date || '').toString().split('T')[0];
      if (uDate && uDate === dateStr) return true;
      // recurring blackout entries may include recurring_days array
      if (u.is_recurring && u.recurring_days) {
        const dayName = ['sunday','monday','tuesday','wednesday','thursday','friday','saturday'][day];
        if (u.recurring_days.includes(dayName)) return true;
      }
      // legacy types: some entries may have type === 'weekend' or 'blackout'
      if (u.type === 'weekend' && (day === 0 || day === 6)) return true;
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
            className={`w-full h-8 flex items-center justify-center text-xs rounded border transition-all duration-200 hover:scale-105 ${
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
        onClick={() => setIsOpen(!isOpen)}
        className={`w-full px-3 py-2 bg-gray-800 border rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-500 transition-all duration-200 text-sm text-white text-left flex justify-between items-center ${
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

      {isOpen && (
        <div className="absolute z-50 w-full mt-1 bg-gray-800 border border-amber-500/30 rounded-lg shadow-lg shadow-amber-500/10 p-3">
          {/* Calendar Header */}
          <div className="flex items-center justify-between mb-3">
            <button
              type="button"
              onClick={() => navigateMonth(-1)}
              className="p-1 text-amber-400 hover:text-amber-300 hover:bg-amber-500/10 rounded border border-amber-500/30 transition-colors duration-200"
            >
              <ChevronDownIcon className="h-3 w-3 rotate-90" />
            </button>
            
            <div className="text-amber-50 font-medium text-sm">
              {currentMonth.toLocaleDateString('en-US', { month: 'long', year: 'numeric' })}
            </div>
            
            <button
              type="button"
              onClick={() => navigateMonth(1)}
              className="p-1 text-amber-400 hover:text-amber-300 hover:bg-amber-500/10 rounded border border-amber-500/30 transition-colors duration-200"
            >
              <ChevronDownIcon className="h-3 w-3 -rotate-90" />
            </button>
          </div>

          {/* Week Days */}
          <div className="grid grid-cols-7 gap-1 mb-2">
            {['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'].map(day => (
              <div key={day} className="text-center text-xs font-medium text-amber-400/70 p-1">
                {day}
              </div>
            ))}
          </div>

          {/* Calendar Grid */}
          <div className="grid grid-cols-7 gap-1">
            {renderCalendarGrid()}
          </div>

          {/* Quick Actions */}
          <div className="flex justify-between mt-3 pt-3 border-t border-gray-600">
            <button
              type="button"
              onClick={() => handleDateSelect(today)}
              className="text-xs text-amber-400 hover:text-amber-300 hover:bg-amber-500/10 px-2 py-1 rounded border border-amber-500/30 transition-colors duration-200"
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
              className="text-xs text-amber-400 hover:text-amber-300 hover:bg-amber-500/10 px-2 py-1 rounded border border-amber-500/30 transition-colors duration-200"
            >
              Tomorrow
            </button>
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
        <div className="p-4">
          <div className="flex items-center mb-3">
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
            <h3 className="text-sm font-semibold text-amber-50 ml-2">{title}</h3>
          </div>
          <p className="text-gray-300 text-sm mb-4">{message}</p>
          <div className="flex justify-end space-x-2">
            <button
              onClick={onClose}
              disabled={loading}
              className="px-3 py-2 border border-gray-600 text-gray-300 rounded-lg hover:bg-gray-800 transition-colors duration-200 font-medium text-sm focus:outline-none focus:ring-2 focus:ring-amber-500 focus:ring-offset-2 focus:ring-offset-gray-900 disabled:opacity-50"
            >
              Cancel
            </button>
            <button
              onClick={onConfirm}
              disabled={loading}
              className={`px-3 py-2 text-white rounded-lg transition-colors duration-200 font-medium text-sm focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-offset-gray-900 disabled:opacity-50 ${buttonColors[type]}`}
            >
              {loading ? (
                <div className="flex items-center">
                  <div className="w-3 h-3 border-2 border-white border-t-transparent rounded-full animate-spin mr-1"></div>
                  Processing...
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

const Dashboard = () => {
  const { user, logout } = useAuth();
  const { callApi, loading, error, clearError } = useApi();
  const navigate = useNavigate();
  const location = useLocation();
  
  // Redirect based on user role
  useEffect(() => {
    if (user?.role === 'admin') {
      navigate('/admin/dashboard', { replace: true });
      return;
    }
  }, [user, navigate]);

  const [activeTab, setActiveTab] = useState('home');
  const [isEditing, setIsEditing] = useState(false);
  const [showSettings, setShowSettings] = useState(false);
  const [showLogoutModal, setShowLogoutModal] = useState(false);
  const [showThankYouModal, setShowThankYouModal] = useState(false);
  const [latestAppointment, setLatestAppointment] = useState(null);
  const [isDarkMode, setIsDarkMode] = useState(true);
  const [showMobileSidebar, setShowMobileSidebar] = useState(false);
  
  // Real data states
  const [appointments, setAppointments] = useState([]);
  const [appointmentTypes, setAppointmentTypes] = useState([]);
  const [availableSlots, setAvailableSlots] = useState([]);
  const [messages, setMessages] = useState([]);
  const [staff, setStaff] = useState([]);
  
  // Modal states
  const [showAppointmentDetail, setShowAppointmentDetail] = useState(false);
  const [showCancelModal, setShowCancelModal] = useState(false);
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
    loadInitialData();
  }, [activeTab]);

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
  useEffect(() => {
    const root = document.documentElement;
    if (isDarkMode) {
      // Dark mode - default theme
      root.classList.add('dark');
      root.style.backgroundColor = 'rgb(11, 11, 11)'; // black
      root.style.color = 'rgb(250, 245, 235)'; // amber-50
    } else {
      // Light mode - using gray instead of stone for better appearance
      root.classList.remove('dark');
      root.style.backgroundColor = 'rgb(243, 244, 246)'; // gray-100
      root.style.color = 'rgb(31, 41, 55)'; // gray-800
    }
    
    // Save preference to localStorage
    localStorage.setItem('userTheme', isDarkMode ? 'dark' : 'light');
  }, [isDarkMode]);

  // Load saved theme preference on component mount
  useEffect(() => {
    const savedTheme = localStorage.getItem('userTheme');
    const savedSettings = localStorage.getItem('userSettings');
    
    if (savedSettings) {
      try {
        const parsedSettings = JSON.parse(savedSettings);
        setSettings(parsedSettings);
        if (parsedSettings.theme === 'light') {
          setIsDarkMode(false);
        }
      } catch (e) {
        console.error('Failed to parse saved settings:', e);
      }
    } else if (savedTheme === 'light') {
      setIsDarkMode(false);
    } else {
      setIsDarkMode(true);
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

  const loadInitialData = async () => {
    switch (activeTab) {
      case 'home':
      case 'book':
        await loadAppointmentTypes();
        await loadAppointments();
        break;
      case 'appointments':
        await loadAppointments();
        break;
      case 'messages':
        await loadMessages();
        break;
      case 'profile':
        // Profile data is already loaded from auth context
        break;
    }
  };

  const loadAppointments = async () => {
    const result = await callApi((signal) => 
      axios.get('/api/appointments/my/appointments', { signal })
    );
    
    if (result.success) {
      // Sort appointments by created_at in descending order (newest first)
      const sortedAppointments = (result.data.data || []).sort((a, b) => 
        new Date(b.created_at) - new Date(a.created_at)
      );
      setAppointments(sortedAppointments);
    }
  };

  const loadAppointmentTypes = async () => {
    try {
      // First, try to load services from the Services table
      const servicesResult = await callApi((signal) => 
        axios.get('/api/services', { signal })
      );
      
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
        
        setAppointmentTypes(merged);
      } else {
        // Fallback to static types if services endpoint fails
        const result = await callApi((signal) => 
          axios.get('/api/appointments/types/all', { signal })
        );
        
        if (result.success) {
          setAppointmentTypes(Object.entries(result.data.data || {}).map(([value, label]) => ({
            value,
            label
          })));
        }
      }
    } catch (error) {
      console.error('Failed to load appointment types:', error);
      // Fallback to static types
      const result = await callApi((signal) => 
        axios.get('/api/appointments/types/all', { signal })
      );
      
      if (result.success) {
        setAppointmentTypes(Object.entries(result.data.data || {}).map(([value, label]) => ({
          value,
          label
        })));
      }
    }
  };

  const loadAvailableSlots = async (date) => {
    if (!date) return;
    
    const result = await callApi((signal) => 
      axios.get(`/api/appointments/available-slots/${date}`, { signal })
    );
    
    if (result.success) {
      setAvailableSlots(result.data.data || []);
    } else {
      setAvailableSlots([]);
    }
  };

  const loadMessages = async () => {
    const result = await callApi((signal) => 
      axios.get('/api/messages/all/messages', { signal })
    );
    
    if (result.success) {
      const messagesData = result.data.data || [];
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
  };

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

    // Load available slots when date changes
    if (name === 'appointment_date') {
      loadAvailableSlots(value);
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

    // Get the service ID from the selected appointment type
    const selectedService = appointmentTypes.find(t => t.value === appointmentData.type);
    const serviceId = selectedService?.id || null;

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
      
      // Reload appointments
      await loadAppointments();
    } else {
      if (result.error?.includes('not available') || result.error?.includes('unavailable')) {
        setFormErrors({
          appointment_time: 'The selected date and time is not available. Please choose another time slot.'
        });
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

  const confirmLogout = () => {
    logout();
    setShowLogoutModal(false);
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
                <p className="text-xs font-medium text-gray-400 group-hover:text-amber-300 transition-colors">
                  {stat.name}
                </p>
                <p className="text-lg font-bold text-amber-50 mt-0.5 group-hover:scale-105 transition-transform">
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
      <div className="bg-gray-900 border border-amber-500/20 rounded-lg shadow p-4 sm:p-6 hover:border-amber-500/40 transition-all duration-300">
        <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
          <div className="flex-1">
            <h2 className="text-base sm:text-lg font-bold text-amber-50">Welcome back, {user?.first_name}! 👋</h2>
            <p className="text-amber-400/70 mt-1 text-xs sm:text-sm">Ready to schedule your next notarization service?</p>
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
        <div className="bg-gray-900 border border-amber-500/20 rounded-lg shadow p-4 hover:border-amber-500/40 transition-all duration-300">
          <h3 className="text-sm font-semibold text-amber-50 mb-3 flex items-center">
            <ClockIcon className="h-4 w-4 mr-2" />
            Quick Actions
          </h3>
          <div className="space-y-2">
            <button
              onClick={() => setActiveTab('book')}
              className="w-full text-left p-2 border border-gray-600 rounded hover:border-amber-500/40 hover:bg-amber-500/5 transition-all duration-200 text-amber-50 text-sm flex items-center justify-between group"
            >
              <div className="flex items-center">
                <PlusIcon className="h-4 w-4 mr-2 text-amber-400" />
                <span>Book New Appointment</span>
              </div>
              <ChevronDownIcon className="h-3 w-3 text-amber-400 transform -rotate-90 group-hover:scale-110" />
            </button>
            <button
              onClick={() => setActiveTab('appointments')}
              className="w-full text-left p-2 border border-gray-600 rounded hover:border-amber-500/40 hover:bg-amber-500/5 transition-all duration-200 text-amber-50 text-sm flex items-center justify-between group"
            >
              <div className="flex items-center">
                <CalendarIcon className="h-4 w-4 mr-2 text-amber-400" />
                <span>View All Appointments</span>
              </div>
              <ChevronDownIcon className="h-3 w-3 text-amber-400 transform -rotate-90 group-hover:scale-110" />
            </button>
          </div>
        </div>

        {/* Recent Appointments Preview */}
        <div className="bg-gray-900 border border-amber-500/20 rounded-lg shadow p-4 hover:border-amber-500/40 transition-all duration-300">
          <div className="flex items-center justify-between mb-3">
            <h3 className="text-sm font-semibold text-amber-50 flex items-center">
              <CalendarDaysIcon className="h-4 w-4 mr-2" />
              Recent Appointments
            </h3>
            <button
              onClick={() => setActiveTab('appointments')}
              className="text-amber-400 hover:text-amber-300 text-xs font-medium hover:bg-amber-500/10 px-2 py-1 rounded border border-amber-500/30 transition-colors duration-200"
            >
              View All
            </button>
          </div>
          {appointments.length === 0 ? (
            <div className="text-center py-4">
              <CalendarIcon className="mx-auto h-8 w-8 text-gray-600" />
              <h3 className="mt-1 text-xs font-medium text-amber-50">No appointments</h3>
              <p className="text-amber-400/70 text-xs mt-0.5">Schedule your first appointment to get started</p>
            </div>
          ) : (
            <div className="space-y-2">
              {appointments.slice(0, 3).map((appointment) => (
                <div key={appointment.id} className="flex items-center justify-between p-2 border border-gray-600 rounded hover:border-amber-500/30 hover:bg-amber-500/5 transition-all duration-200 group">
                  <div className="flex items-center space-x-2">
                    <div className="flex-shrink-0">
                      <div className="w-8 h-8 bg-amber-500/20 rounded-full flex items-center justify-center border border-amber-500/30">
                        <DocumentTextIcon className="h-3 w-3 text-amber-400" />
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
      <div className="flex justify-between items-center">
        <div>
          <h2 className="text-lg font-bold text-amber-50">Book New Appointment</h2>
          <p className="text-amber-400/70 mt-1 text-sm">Schedule your document notarization service</p>
        </div>
        <div className="flex items-center space-x-1 text-xs text-amber-400/70">
          <ClockIcon className="h-3 w-3" />
          <span>30 min sessions</span>
        </div>
      </div>

      <div className="bg-gray-900 border border-amber-500/20 rounded-lg shadow p-6 hover:border-amber-500/40 transition-all duration-300">
        <form onSubmit={handleAppointmentSubmit} className="space-y-4">
          <div className="grid grid-cols-1 lg:grid-cols-2 gap-4">
            {/* Service Type with Enhanced Dropdown */}
            <ServiceTypeDropdown
              value={appointmentData.type}
              onChange={handleServiceTypeChange}
              options={appointmentTypes}
              error={formErrors.type}
              onOtherChange={handleCustomServiceChange}
              otherValue={appointmentData.custom_service_type}
            />

            {/* Enhanced Calendar Component */}
            <EnhancedCalendar
              value={appointmentData.appointment_date}
              onChange={(value) => handleAppointmentChange({ target: { name: 'appointment_date', value } })}
              error={formErrors.appointment_date}
            />

            {/* Time Input using TimePicker Component */}
            <TimePicker
              value={appointmentData.appointment_time}
              onChange={(value) => handleAppointmentChange({ target: { name: 'appointment_time', value } })}
              error={formErrors.appointment_time}
            />

            <div className="lg:col-span-2">
              <label className="block text-xs font-medium text-amber-50 mb-1">
                Additional Notes (Optional)
              </label>
              <textarea
                name="notes"
                value={appointmentData.notes}
                onChange={handleAppointmentChange}
                rows="3"
                className="w-full px-3 py-2 bg-gray-800 border border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-500 focus:border-amber-500 transition-all duration-200 text-sm text-white placeholder-gray-400 resize-none"
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
              disabled={loading}
              className="px-6 py-2 bg-gradient-to-r from-amber-600 to-amber-700 text-white rounded-lg hover:from-amber-700 hover:to-amber-800 focus:outline-none focus:ring-2 focus:ring-amber-500 focus:ring-offset-2 focus:ring-offset-gray-900 transition-all duration-200 font-medium text-sm shadow border border-amber-500/30 disabled:opacity-50 disabled:cursor-not-allowed flex items-center transform hover:-translate-y-0.5"
            >
              {loading ? (
                <>
                  <div className="w-3 h-3 border-2 border-white border-t-transparent rounded-full animate-spin mr-2"></div>
                  Scheduling...
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

  const renderAppointments = () => (
    <div className="space-y-6">
      <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
        <div>
          <h2 className="text-lg font-bold text-amber-50">My Appointments</h2>
          <p className="text-amber-400/70 mt-1 text-sm">View and manage your notarization appointments</p>
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

      <div className="bg-gray-900 border border-amber-500/20 rounded-lg shadow overflow-hidden hover:border-amber-500/40 transition-all duration-300">
        {appointments.length === 0 ? (
          <div className="text-center py-8">
            <CalendarIcon className="mx-auto h-12 w-12 text-gray-600" />
            <h3 className="mt-4 text-sm font-medium text-amber-50">No appointments yet</h3>
            <p className="mt-2 text-amber-400/70 text-xs">Schedule your first notarization appointment to get started</p>
            <button
              onClick={() => setActiveTab('book')}
              className="mt-4 px-4 py-2 bg-gradient-to-r from-amber-600 to-amber-700 text-white rounded-lg hover:from-amber-700 hover:to-amber-800 transition-all duration-200 font-medium text-sm shadow border border-amber-500/30"
            >
              Book Appointment
            </button>
          </div>
        ) : (
          <div className="divide-y divide-gray-700">
            {appointments.map((appointment) => (
              <div key={appointment.id} className="p-4 hover:bg-gray-800 transition-all duration-200 group">
                <div className="flex items-center justify-between">
                  <div className="flex items-center space-x-3">
                    <div className="flex-shrink-0">
                      <div className="w-10 h-10 bg-amber-500/20 rounded-full flex items-center justify-center border border-amber-500/30">
                        <DocumentTextIcon className="h-5 w-5 text-amber-400" />
                      </div>
                    </div>
                    <div className="flex-1">
                      <div className="flex items-center space-x-2">
                        <h3 className="text-sm font-semibold text-amber-50 group-hover:text-amber-300">
                          {formatServiceName(appointment)}
                        </h3>
                        <StatusBadge status={appointment.status} />
                      </div>
                      <p className="text-xs text-amber-400/70 mt-1">
                        {new Date(appointment.appointment_date).toLocaleDateString()} at {appointment.appointment_time}
                      </p>
                      {appointment.staff && (
                        <div className="flex items-center space-x-1 mt-1 text-xs text-amber-400/70">
                          <span>Assigned to:</span>
                          <span className="text-amber-300">
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
                  </div>
                </div>
              </div>
            ))}
          </div>
        )}
      </div>
    </div>
  );

  const renderMessages = () => {
    return <MessageCenter isDarkMode={isDarkMode} />;
  };

  const renderProfile = () => (
    <div className="space-y-6">
      <div className="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-4">
        <div>
          <h2 className="text-lg font-bold text-amber-50">Profile Settings</h2>
          <p className="text-amber-400/70 mt-1 text-sm">Manage your personal information and security</p>
        </div>
        <div className="flex gap-2 w-full sm:w-auto">
          <button
            onClick={() => setShowSettings(true)}
            className="flex-1 sm:flex-none px-3 py-2 sm:py-1.5 border border-amber-500/30 text-amber-50 rounded hover:bg-amber-500/10 transition-all duration-200 font-medium text-xs sm:text-sm flex items-center justify-center gap-1 whitespace-nowrap"
          >
            <Cog6ToothIcon className="h-3 w-3" />
            <span className="hidden sm:inline">Preferences</span>
            <span className="sm:hidden text-xs">Pref</span>
          </button>
          <button
            onClick={() => setIsEditing(!isEditing)}
            className="flex-1 sm:flex-none px-3 py-2 sm:py-1.5 border border-amber-500/30 text-amber-50 rounded hover:bg-amber-500/10 transition-all duration-200 font-medium text-xs sm:text-sm flex items-center justify-center gap-1 whitespace-nowrap"
          >
            <PencilIcon className="h-3 w-3" />
            <span className="hidden sm:inline">{isEditing ? 'Cancel Edit' : 'Edit Profile'}</span>
            <span className="sm:hidden text-xs">{isEditing ? 'Cancel' : 'Edit'}</span>
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
      <div className="bg-gray-900 border border-amber-500/20 rounded-lg shadow p-6 hover:border-amber-500/40 transition-all duration-300">
        <div className="flex items-center space-x-3 mb-6">
          <div className="w-12 h-12 bg-gradient-to-r from-amber-500 to-amber-600 rounded-full flex items-center justify-center text-gray-900 text-sm font-bold shadow">
            {user?.first_name?.charAt(0)}{user?.last_name?.charAt(0)}
          </div>
          <div>
            <h3 className="text-sm font-bold text-amber-50">{user?.first_name} {user?.last_name}</h3>
            <p className="text-amber-400/70 text-xs">Client Account</p>
            <p className="text-xs text-amber-400/70">Member since {new Date(user?.created_at).toLocaleDateString()}</p>
          </div>
        </div>

        {/* Profile Information Form */}
        <div className="mb-6">
          <h4 className="text-sm font-semibold text-amber-50 mb-4 flex items-center">
            <UserIcon className="h-4 w-4 mr-2" />
            Personal Information
          </h4>
          
          <form onSubmit={handleProfileSubmit} className="space-y-4">
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label className="block text-xs font-medium text-amber-50 mb-1">
                  Username *
                </label>
                <input
                  type="text"
                  name="username"
                  value={profileData.username}
                  onChange={handleProfileChange}
                  disabled={!isEditing}
                  className="w-full px-3 py-2 bg-gray-800 border border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-500 focus:border-amber-500 transition-all duration-200 text-sm text-white disabled:bg-gray-800/50 disabled:cursor-not-allowed disabled:text-gray-400"
                  required
                />
              </div>

              <div>
                <label className="block text-xs font-medium text-amber-50 mb-1">
                  Email Address *
                </label>
                <input
                  type="email"
                  name="email"
                  value={profileData.email}
                  onChange={handleProfileChange}
                  disabled={!isEditing}
                  className="w-full px-3 py-2 bg-gray-800 border border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-500 focus:border-amber-500 transition-all duration-200 text-sm text-white disabled:bg-gray-800/50 disabled:cursor-not-allowed disabled:text-gray-400"
                  required
                />
              </div>

              <div>
                <label className="block text-xs font-medium text-amber-50 mb-1">
                  First Name *
                </label>
                <input
                  type="text"
                  name="first_name"
                  value={profileData.first_name}
                  onChange={handleProfileChange}
                  disabled={!isEditing}
                  className="w-full px-3 py-2 bg-gray-800 border border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-500 focus:border-amber-500 transition-all duration-200 text-sm text-white disabled:bg-gray-800/50 disabled:cursor-not-allowed disabled:text-gray-400"
                  required
                />
              </div>

              <div>
                <label className="block text-xs font-medium text-amber-50 mb-1">
                  Last Name *
                </label>
                <input
                  type="text"
                  name="last_name"
                  value={profileData.last_name}
                  onChange={handleProfileChange}
                  disabled={!isEditing}
                  className="w-full px-3 py-2 bg-gray-800 border border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-500 focus:border-amber-500 transition-all duration-200 text-sm text-white disabled:bg-gray-800/50 disabled:cursor-not-allowed disabled:text-gray-400"
                  required
                />
              </div>

              <div>
                <label className="block text-xs font-medium text-amber-50 mb-1">
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
                    className="w-full pl-9 pr-3 py-2 bg-gray-800 border border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-500 focus:border-amber-500 transition-all duration-200 text-sm text-white disabled:bg-gray-800/50 disabled:cursor-not-allowed disabled:text-gray-400"
                  />
                </div>
              </div>

              <div>
                <label className="block text-xs font-medium text-amber-50 mb-1">
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
                    className="w-full pl-9 pr-3 py-2 bg-gray-800 border border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-500 focus:border-amber-500 transition-all duration-200 text-sm text-white disabled:bg-gray-800/50 disabled:cursor-not-allowed disabled:text-gray-400 placeholder-gray-400"
                  />
                </div>
              </div>

              <div className="md:col-span-2">
                <label className="block text-xs font-medium text-amber-50 mb-1">
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
                    className="w-full pl-9 pr-3 py-2 bg-gray-800 border border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-500 focus:border-amber-500 transition-all duration-200 text-sm text-white disabled:bg-gray-800/50 disabled:cursor-not-allowed disabled:text-gray-400 resize-none"
                  />
                </div>
              </div>
            </div>

            {isEditing && (
              <div className="flex justify-end space-x-3 pt-4 border-t border-gray-700">
                <button
                  type="button"
                  onClick={() => setIsEditing(false)}
                  className="px-4 py-2 border border-gray-600 text-gray-300 rounded-lg hover:bg-gray-800 transition-all duration-200 font-medium text-sm focus:outline-none focus:ring-2 focus:ring-amber-500 focus:ring-offset-2 focus:ring-offset-gray-900 hover:scale-105"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  disabled={loading}
                  className="px-4 py-2 bg-gradient-to-r from-amber-600 to-amber-700 text-white rounded-lg hover:from-amber-700 hover:to-amber-800 focus:outline-none focus:ring-2 focus:ring-amber-500 focus:ring-offset-2 focus:ring-offset-gray-900 transition-all duration-200 font-medium text-sm shadow border border-amber-500/30 disabled:opacity-50 hover:scale-105"
                >
                  {loading ? (
                    <div className="flex items-center">
                      <div className="w-3 h-3 border-2 border-white border-t-transparent rounded-full animate-spin mr-2"></div>
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
        <div className="border-t border-gray-700 pt-6">
          <h4 className="text-sm font-semibold text-amber-50 mb-4 flex items-center">
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
                <label className="block text-xs font-medium text-amber-50 mb-1">
                  Current Password *
                </label>
                <input
                  type="password"
                  name="current_password"
                  value={passwordData.current_password}
                  onChange={handlePasswordChange}
                  className="w-full px-3 py-2 bg-gray-800 border border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-500 focus:border-amber-500 transition-all duration-200 text-sm text-white"
                  required
                />
              </div>

              <div>
                <label className="block text-xs font-medium text-amber-50 mb-1">
                  New Password *
                </label>
                <input
                  type="password"
                  name="new_password"
                  value={passwordData.new_password}
                  onChange={handlePasswordChange}
                  className="w-full px-3 py-2 bg-gray-800 border border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-500 focus:border-amber-500 transition-all duration-200 text-sm text-white"
                  required
                />
              </div>

              <div className="md:col-span-2">
                <label className="block text-xs font-medium text-amber-50 mb-1">
                  Confirm New Password *
                </label>
                <input
                  type="password"
                  name="new_password_confirmation"
                  value={passwordData.new_password_confirmation}
                  onChange={handlePasswordChange}
                  className="w-full px-3 py-2 bg-gray-800 border border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-500 focus:border-amber-500 transition-all duration-200 text-sm text-white"
                  required
                />
              </div>
            </div>

            <div className="flex justify-end pt-4 border-t border-gray-700">
              <button
                type="submit"
                disabled={loading}
                className="px-4 py-2 bg-gradient-to-r from-amber-600 to-amber-700 text-white rounded-lg hover:from-amber-700 hover:to-amber-800 focus:outline-none focus:ring-2 focus:ring-amber-500 focus:ring-offset-2 focus:ring-offset-gray-900 transition-all duration-200 font-medium text-sm shadow border border-amber-500/30 disabled:opacity-50 hover:scale-105"
              >
                {loading ? (
                  <div className="flex items-center">
                    <div className="w-3 h-3 border-2 border-white border-t-transparent rounded-full animate-spin mr-2"></div>
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
      case 'action-logs': return <ActionLogViewer isDarkMode={isDarkMode} />;
      case 'profile': return renderProfile();
      default: return renderHome();
    }
  };

  // Show loading or redirect message while redirecting
  if (user?.role === 'admin') {
    return (
      <div className="min-h-screen bg-gradient-to-br from-gray-900 to-black flex items-center justify-center">
        <div className="text-center">
          <div className="w-12 h-12 border-4 border-amber-500 border-t-transparent rounded-full animate-spin mx-auto mb-3"></div>
          <p className="text-amber-100 text-sm">Redirecting to your dashboard...</p>
        </div>
      </div>
    );
  }

  return (
    <div className={`min-h-screen ${isDarkMode ? 'bg-gradient-to-br from-gray-900 to-black' : 'bg-gradient-to-br from-gray-100 to-gray-200'} flex flex-col lg:flex-row transition-colors duration-300`}>
      {/* Mobile Hamburger Menu */}
      <div className={`lg:hidden fixed top-0 left-0 right-0 z-40 ${isDarkMode ? 'bg-gray-900 border-amber-500/20' : 'bg-gray-50 border-amber-300/40'} border-b shadow-md transition-colors duration-300`}>
        <div className="flex justify-between items-center px-4 py-3">
          <div className="w-10"></div>
          <span className={`text-base font-bold ${isDarkMode ? 'text-amber-50' : 'text-amber-900'}`}>LEGAL EASE</span>
          <button
            onClick={() => setShowMobileSidebar(!showMobileSidebar)}
            className="text-amber-400 hover:text-amber-300 transition-colors p-2 rounded-lg hover:bg-amber-500/10"
            title="Toggle menu"
          >
            <Bars3Icon className="h-6 w-6" />
          </button>
        </div>
      </div>

      {/* Mobile Sidebar Overlay */}
      {showMobileSidebar && (
        <div
          className="lg:hidden fixed inset-0 bg-black/50 z-30 transition-opacity duration-200"
          onClick={() => setShowMobileSidebar(false)}
        ></div>
      )}

      {/* Sidebar - Hidden on mobile by default, shown on desktop and when toggled on mobile */}
      <div className={`fixed lg:static inset-y-0 right-0 lg:right-auto lg:left-0 z-40 w-64 ${isDarkMode ? 'bg-gradient-to-b from-gray-900 via-gray-800 to-black border-amber-500/20' : 'bg-gradient-to-b from-gray-50 via-gray-100 to-gray-100 border-amber-300/40'} border-l lg:border-l-0 lg:border-r shadow-xl transition-all duration-300 lg:translate-x-0 lg:w-64 ${
        showMobileSidebar ? 'translate-x-0' : 'translate-x-full lg:translate-x-0'
      }`}>
        <div className="flex flex-col h-full overflow-y-auto">
          {/* Logo Section */}
          <div className={`p-4 shadow-md ${isDarkMode ? 'bg-gradient-to-r from-gray-900/80 to-gray-800/80 border-amber-500/30' : 'bg-gradient-to-r from-gray-50/80 to-gray-100/80 border-amber-300/50'} px-3 border-b transition-colors duration-300`}>
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
                <p className={`text-xs font-semibold ${isDarkMode ? 'text-amber-50' : 'text-amber-900'} truncate`}>{user?.first_name} {user?.last_name}</p>
                <p className={`text-xs ${isDarkMode ? 'text-amber-400/60' : 'text-amber-700/60'} truncate`}>{user?.email}</p>
              </div>
            </div>
          </div>

          {/* Navigation */}
          <nav className="flex-1 px-3 py-4 space-y-4 overflow-y-auto">
            {navigation.map((section) => (
              <div key={section.section} className="space-y-2">
                <div className="px-3 py-1">
                  <span className={`text-xs font-semibold ${isDarkMode ? 'text-amber-400/70' : 'text-amber-700/70'} uppercase tracking-wider`}>
                    {section.section}
                  </span>
                </div>
                <div className="space-y-1">
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
                      className={`w-full flex items-center justify-between px-3 py-2.5 text-xs lg:text-xs font-medium rounded-lg transition-all duration-200 border group relative overflow-hidden ${
                        item.current
                          ? `${isDarkMode ? 'bg-gradient-to-r from-amber-500/15 to-amber-600/10' : 'bg-gradient-to-r from-amber-200/30 to-amber-100/20'} text-amber-400 border-amber-500/50 shadow-lg shadow-amber-500/10`
                          : `text-gray-400 border-transparent hover:${isDarkMode ? 'bg-amber-500/8' : 'bg-amber-300/10'} hover:text-amber-300 hover:border-amber-500/20`
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

          {/* Quick Stats */}
          <div className={`mx-3 mb-4 p-3 rounded-lg border-2 ${isDarkMode ? 'bg-gradient-to-br from-gray-800/40 to-gray-900/40 border-amber-500/20' : 'bg-gradient-to-br from-white/40 to-gray-50/40 border-amber-300/30'} transition-all duration-300`}>
            <p className={`text-xs font-semibold ${isDarkMode ? 'text-amber-400' : 'text-amber-700'} mb-2 uppercase tracking-widest`}>Quick Stats</p>
            <div className="space-y-1.5 text-xs">
              <div className="flex justify-between items-center">
                <span className={isDarkMode ? 'text-gray-400' : 'text-gray-600'}>Appointments:</span>
                <span className={`font-bold ${isDarkMode ? 'text-amber-300' : 'text-amber-700'}`}>{appointments?.length || 0}</span>
              </div>
              <div className="flex justify-between items-center">
                <span className={isDarkMode ? 'text-gray-400' : 'text-gray-600'}>Pending:</span>
                <span className={`font-bold ${isDarkMode ? 'text-blue-300' : 'text-blue-700'}`}>{appointments?.filter(a => a.status === 'pending')?.length || 0}</span>
              </div>
              <div className="flex justify-between items-center">
                <span className={isDarkMode ? 'text-gray-400' : 'text-gray-600'}>Completed:</span>
                <span className={`font-bold ${isDarkMode ? 'text-green-300' : 'text-green-700'}`}>{appointments?.filter(a => a.status === 'completed')?.length || 0}</span>
              </div>
            </div>
          </div>

          {/* Footer with Settings and Logout */}
          <div className={`p-3 border-t ${isDarkMode ? 'border-amber-500/20 bg-gray-900/50' : 'border-amber-300/30 bg-gray-50/50'} space-y-2 transition-colors duration-300`}>
            {/* Settings Button */}
            <button
              onClick={() => {
                setShowSettings(true);
                setShowMobileSidebar(false);
              }}
              className={`w-full flex items-center px-3 py-2 text-xs font-medium rounded-lg transition-all duration-200 border ${
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
              onClick={handleLogout}
              className={`w-full flex items-center px-3 py-2 text-xs font-medium rounded-lg border transition-all duration-200 ${
                isDarkMode
                  ? 'text-red-400 border-red-500/30 hover:bg-red-500/10 hover:text-red-300 hover:border-red-500/50'
                  : 'text-red-600 border-red-300/50 hover:bg-red-100/30 hover:text-red-700 hover:border-red-400/50'
              }`}
            >
              <ArrowPathIcon className="mr-2 h-4 w-4 flex-shrink-0" />
              Logout
            </button>
          </div>
        </div>
      </div>

      {/* Main content */}
      <div className="flex-1 flex flex-col min-w-0 lg:mt-0 mt-16">
        {/* Header */}
        <header className={`${isDarkMode ? 'bg-gray-900 border-amber-500/20' : 'bg-gray-50 border-amber-300/40'} border-b shadow flex-shrink-0 transition-colors duration-300`}>
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
        <main className={`flex-1 p-3 sm:p-4 lg:p-6 overflow-auto ${isDarkMode ? '' : 'bg-gray-100'} transition-colors duration-300`}>
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
      />

      <ConfirmationModal
        isOpen={showLogoutModal}
        onClose={() => setShowLogoutModal(false)}
        onConfirm={confirmLogout}
        title="Confirm Logout"
        message="Are you sure you want to logout from your account?"
        confirmText="Logout"
        type="primary"
        loading={loading}
      />

      <SettingsModal
        isOpen={showSettings}
        onClose={() => setShowSettings(false)}
        settings={settings}
        onSettingsChange={handleSettingsChange}
      />

      <ThankYouModal
        isOpen={showThankYouModal}
        onClose={() => setShowThankYouModal(false)}
        appointment={latestAppointment}
      />
    </div>
  );
};

export default Dashboard;