import React, { useState, useEffect, useCallback, useRef } from 'react';
import { useAuth } from '../context/AuthContext';
import { useTheme } from '../context/ThemeContext';
import { useApi } from '../hooks/useApi';
import useRealtimeUpdates from '../hooks/useRealtimeUpdates';
import { Link, useNavigate, useLocation } from 'react-router-dom';
import BottomNav from '../components/BottomNav';
import ProfilePage from './ProfilePage';
import axios from 'axios';
import ActionLogViewer from '../components/ActionLogViewer';
import MessageCenter from './MessageCenter';
import UserFeedback from '../components/user/UserFeedback';
import UserInsights from '../components/user/UserInsights';
import ProfileCompletionBanner from '../components/ProfileCompletionBanner';
import UserNotifications from '../components/user/UserNotifications';
import TermsPrivacyModal from '../components/auth/TermsPrivacyModal';

import NotificationBell from '../components/user/NotificationBell';
import MobileNotificationBell from '../components/user/MobileNotificationBell';
import ThemeToggle from '../components/ui/ThemeToggle';
import { formatServiceName, formatTime12Hour, formatDateDisplay } from '../utils/format';

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
  EyeSlashIcon,
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
  StarIcon,
  BellIcon,
  ChevronRightIcon
} from '@heroicons/react/24/outline';

// Enhanced Status Badge Component
const StatusBadge = ({ status }) => {
  const statusConfig = {
    pending: {
      color: 'bg-amber-100 text-amber-800 border border-amber-200 dark:bg-amber-500/20 dark:text-amber-300 dark:border-amber-500/30',
      icon: ClockIcon,
      glow: 'shadow-amber-100'
    },
    approved: {
      color: 'bg-blue-100 text-blue-800 border border-blue-200 dark:bg-blue-500/20 dark:text-blue-300 dark:border-blue-500/30',
      icon: CheckCircleIcon,
      glow: 'shadow-blue-100'
    },
    completed: {
      color: 'bg-green-100 text-green-800 border border-green-200 dark:bg-green-500/20 dark:text-green-300 dark:border-green-500/30',
      icon: CheckCircleIcon,
      glow: 'shadow-green-100'
    },
    cancelled: {
      color: 'bg-red-100 text-red-800 border border-red-200 dark:bg-red-500/20 dark:text-red-300 dark:border-red-500/30',
      icon: XCircleIcon,
      glow: 'shadow-red-100'
    },
    declined: {
      color: 'bg-red-100 text-red-800 border border-red-200 dark:bg-red-500/20 dark:text-red-300 dark:border-red-500/30',
      icon: XCircleIcon,
      glow: 'shadow-red-100'
    }
  };
  
  const config = statusConfig[status] || statusConfig.pending;
  const IconComponent = config.icon;
  
  return (
    <span className={`inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold ${config.color} ${config.glow} shadow hover:scale-105 transition-transform duration-200`}>
      <IconComponent className="w-3 h-3 mr-1" />
      {status && status.length > 0
        ? status.charAt(0).toUpperCase() + status.slice(1)
        : 'Unknown'}
    </span>
  );
};

// Booking Preview Modal Component
const BookingPreviewModal = ({ 
  isOpen, 
  onClose, 
  onConfirm, 
  appointmentData, 
  appointmentTypes, 
  loading,
  isDarkMode = true
}) => {
  if (!isOpen) return null;

  const selectedServices = appointmentTypes.filter(t => 
    Array.isArray(appointmentData.type) && appointmentData.type.includes(t.value)
  );
  
  const totalPrice = selectedServices.reduce((sum, s) => sum + parseFloat(s.price || 0), 0);
  
  return (
    <div className="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm animate-in fade-in duration-200">
      <div className={`w-full max-w-md overflow-hidden rounded-2xl border shadow-2xl animate-in zoom-in-95 duration-200 ${
        isDarkMode ? 'bg-gray-900 border-gray-800' : 'bg-white border-gray-200'
      }`}>
        <div className="p-6">
          <div className="flex items-center justify-between mb-6">
            <h3 className={`text-lg font-bold tracking-tight ${isDarkMode ? 'text-white' : 'text-gray-900'}`}>
              Review Appointment
            </h3>
            <button 
              onClick={onClose} 
              className={`p-1 rounded-lg transition-colors ${
                isDarkMode ? 'text-gray-400 hover:text-white hover:bg-gray-800' : 'text-gray-400 hover:text-gray-900 hover:bg-gray-100'
              }`}
            >
              <XMarkIcon className="h-5 w-5" />
            </button>
          </div>

          <div className="space-y-4">
            <div className={`rounded-xl p-4 border ${
              isDarkMode ? 'bg-gray-800/50 border-gray-700' : 'bg-gray-50 border-gray-100'
            }`}>
              <p className={`text-[10px] font-bold uppercase tracking-wider mb-3 ${
                isDarkMode ? 'text-amber-500' : 'text-amber-600'
              }`}>Selected Services</p>
              
              <div className="space-y-3">
                {selectedServices.map(service => (
                  <div key={service.value} className="flex justify-between items-center">
                    <span className={`text-sm font-medium ${isDarkMode ? 'text-gray-200' : 'text-gray-700'}`}>
                      {service.label}
                    </span>
                    <span className={`text-sm font-mono ${isDarkMode ? 'text-gray-400' : 'text-gray-500'}`}>
                      ₱{parseFloat(service.price).toLocaleString(undefined, { minimumFractionDigits: 2 })}
                    </span>
                  </div>
                ))}
                
                {Array.isArray(appointmentData.type) && appointmentData.type.includes('other') && (
                  <div className={`pt-2 border-t mt-2 ${isDarkMode ? 'border-gray-700' : 'border-gray-100'}`}>
                    <div className="flex justify-between items-center">
                      <span className={`text-sm font-medium ${isDarkMode ? 'text-gray-200' : 'text-gray-700'}`}>
                        Other: {appointmentData.custom_service_type}
                      </span>
                      <span className="text-xs italic text-gray-500">TBD</span>
                    </div>
                  </div>
                )}
              </div>
              
              <div className={`mt-4 pt-4 border-t flex justify-between items-center ${
                isDarkMode ? 'border-gray-700' : 'border-gray-100'
              }`}>
                <span className={`text-sm font-bold ${isDarkMode ? 'text-white' : 'text-gray-900'}`}>Total</span>
                <span className={`text-lg font-bold ${isDarkMode ? 'text-amber-400' : 'text-amber-600'}`}>
                  ₱{totalPrice.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                </span>
              </div>
            </div>

            <div className="grid grid-cols-2 gap-3">
              <div className={`rounded-xl p-3 border ${
                isDarkMode ? 'bg-gray-800/30 border-gray-700' : 'bg-gray-50 border-gray-100'
              }`}>
                <p className="text-[10px] font-bold uppercase tracking-wider text-gray-500 mb-1">Date</p>
                <div className="flex items-center gap-2">
                  <CalendarDaysIcon className={`h-4 w-4 ${isDarkMode ? 'text-amber-500' : 'text-amber-600'}`} />
                  <span className={`text-sm font-semibold ${isDarkMode ? 'text-gray-200' : 'text-gray-800'}`}>
                    {new Date(appointmentData.appointment_date).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })}
                  </span>
                </div>
              </div>
              <div className={`rounded-xl p-3 border ${
                isDarkMode ? 'bg-gray-800/30 border-gray-700' : 'bg-gray-50 border-gray-100'
              }`}>
                <p className="text-[10px] font-bold uppercase tracking-wider text-gray-500 mb-1">Time</p>
                <div className="flex items-center gap-2">
                  <ClockIcon className={`h-4 w-4 ${isDarkMode ? 'text-amber-500' : 'text-amber-600'}`} />
                  <span className={`text-sm font-semibold ${isDarkMode ? 'text-gray-200' : 'text-gray-800'}`}>
                    {formatTime12Hour(appointmentData.appointment_time)}
                  </span>
                </div>
              </div>
            </div>

          </div>

          <div className="mt-6 flex flex-col gap-2">
            <button
              onClick={onConfirm}
              disabled={loading}
              className={`w-full py-3 rounded-xl font-bold transition-all shadow-sm flex items-center justify-center gap-2 ${
                isDarkMode 
                  ? 'bg-amber-600 hover:bg-amber-500 text-white' 
                  : 'bg-amber-600 hover:bg-amber-700 text-white shadow-amber-200/50 shadow-lg'
              } disabled:opacity-50`}
            >
              {loading ? (
                <div className="w-5 h-5 border-2 border-white/30 border-t-white rounded-full animate-spin"></div>
              ) : (
                <>
                  <CheckCircleIcon className="h-5 w-5" />
                  Confirm Booking
                </>
              )}
            </button>
            <button
              onClick={onClose}
              disabled={loading}
              className={`w-full py-3 rounded-xl font-semibold transition-all ${
                isDarkMode ? 'text-gray-400 hover:text-white hover:bg-gray-800' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-100'
              }`}
            >
              Cancel
            </button>
          </div>
        </div>
      </div>
    </div>
  );
};

// Service Requirements Modal Component
const ServiceRequirementsModal = ({ isOpen, onClose, services, isDarkMode = true }) => {
  if (!isOpen || !services || services.length === 0) return null;

  // Aggregate requirements across the provided services
  const allReqs = [];
  services.forEach(s => {
    if (s.public_requirements && Array.isArray(s.public_requirements)) {
      allReqs.push(...s.public_requirements);
    }
  });
  const uniqueReqs = [...new Set(allReqs)];

  if (uniqueReqs.length === 0) return null;

  return (
    <div className={`fixed inset-0 flex items-center justify-center z-[100] p-4 animate-fadeIn ${isDarkMode ? 'bg-black/70' : 'bg-gray-900/40 backdrop-blur-sm'}`}>
      <div className={`rounded-xl shadow-2xl w-full max-w-sm transform animate-scaleIn border ${isDarkMode ? 'bg-gray-900 border-amber-500/30' : 'bg-white border-gray-200'}`}>
        <div className={`p-4 border-b flex justify-between items-center ${isDarkMode ? 'border-gray-800' : 'border-gray-100'}`}>
          <div className="flex items-center gap-2">
            <InformationCircleIcon className={`h-5 w-5 ${isDarkMode ? 'text-amber-400' : 'text-amber-600'}`} />
            <h3 className={`font-bold text-sm ${isDarkMode ? 'text-amber-50' : 'text-gray-900'}`}>
              Service Requirements
            </h3>
          </div>
          <button onClick={onClose} className={`rounded-full p-1 transition-colors ${isDarkMode ? 'text-gray-400 hover:text-white hover:bg-gray-800' : 'text-gray-500 hover:text-gray-900 hover:bg-gray-100'}`}>
            <XMarkIcon className="h-4 w-4" />
          </button>
        </div>
        <div className="p-4 space-y-3 max-h-[60vh] overflow-y-auto">
          <p className={`text-xs ${isDarkMode ? 'text-gray-300' : 'text-gray-600'}`}>
            Please prepare the following items before your appointment:
          </p>
          <ul className="space-y-2">
            {uniqueReqs.map((req, idx) => (
              <li key={idx} className="flex items-start gap-2">
                <div className={`mt-1.5 flex-shrink-0 h-1.5 w-1.5 rounded-full ${isDarkMode ? 'bg-amber-400' : 'bg-amber-500'}`} />
                <span className={`text-xs ${isDarkMode ? 'text-amber-50/90' : 'text-gray-800'}`}>{req}</span>
              </li>
            ))}
          </ul>
        </div>
        <div className={`p-3 border-t flex justify-end ${isDarkMode ? 'border-gray-800 bg-gray-900' : 'border-gray-100 bg-gray-50 rounded-b-xl'}`}>
          <button
            onClick={onClose}
            className="px-4 py-1.5 bg-gradient-to-r from-amber-600 to-amber-700 text-white rounded-lg hover:from-amber-700 hover:to-amber-800 font-medium text-xs shadow-md"
          >
            Got it
          </button>
        </div>
      </div>
    </div>
  );
};

// Enhanced Service Type Dropdown with Search
const ServiceTypeDropdown = ({ 
  value, // Now an array of values
  onChange, 
  options, 
  error, 
  onOtherChange,
  otherValue,
  disabled = false,
  isDarkMode = true,
  onViewRequirements
}) => {
  const [isOpen, setIsOpen] = useState(false);
  const [searchTerm, setSearchTerm] = useState('');
  const [showOtherInput, setShowOtherInput] = useState(Array.isArray(value) && value.includes('other'));
  const dropdownRef = React.useRef(null);

  const filteredOptions = options.filter(option => 
    option.label.toLowerCase().includes(searchTerm.toLowerCase())
  );

  // Close dropdown on Escape key or click outside
  React.useEffect(() => {
    if (!isOpen) return;
    const handleKeyDown = (e) => {
      if (e.key === 'Escape') { setIsOpen(false); setSearchTerm(''); }
    };
    const handleClickOutside = (e) => {
      if (dropdownRef.current && !dropdownRef.current.contains(e.target)) {
        setIsOpen(false); setSearchTerm('');
      }
    };
    document.addEventListener('keydown', handleKeyDown);
    document.addEventListener('mousedown', handleClickOutside);
    return () => {
      document.removeEventListener('keydown', handleKeyDown);
      document.removeEventListener('mousedown', handleClickOutside);
    };
  }, [isOpen]);

  const handleToggle = (optionValue) => {
    if (disabled) return;
    
    let newValue;
    const currentValues = Array.isArray(value) ? value : (value ? [value] : []);
    
    if (currentValues.includes(optionValue)) {
      newValue = currentValues.filter(v => v !== optionValue);
    } else {
      newValue = [...currentValues, optionValue];
    }
    
    if (optionValue === 'other') {
      setShowOtherInput(!currentValues.includes('other'));
    }
    
    onChange(newValue);
  };

  const handleOtherInputChange = (e) => {
    if (disabled) return;
    onOtherChange(e.target.value);
  };

  const selectedOptions = options.filter(opt => Array.isArray(value) && value.includes(opt.value));
  const totalSelectedPrice = selectedOptions.reduce((sum, opt) => sum + parseFloat(opt.price || 0), 0);

  return (
    <div className={`relative ${isOpen && !disabled ? 'z-50' : 'z-10'}`} ref={dropdownRef}>
      <label className={`block text-xs font-medium mb-1 ${isDarkMode ? 'text-amber-50' : 'text-gray-700'}`}>
        Service Type(s) <span className="text-red-500">*</span>
      </label>
      
      {/* Dropdown Trigger */}
      <button
        type="button"
        disabled={disabled}
        onClick={() => !disabled && setIsOpen(!isOpen)}
        aria-expanded={isOpen}
        aria-haspopup="listbox"
        className={`w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-500 text-sm text-left flex justify-between items-center transition-colors ${
          isDarkMode ? 'bg-gray-800 text-white' : 'bg-white text-gray-900'
        } ${
          disabled ? 'opacity-50 cursor-not-allowed' : ''
        } ${
          error ? 'border-red-500' : isDarkMode ? 'border-gray-600 focus:border-amber-500' : 'border-gray-300 focus:border-amber-500'
        }`}
      >
        <div className="flex flex-col gap-0.5 overflow-hidden">
          <span className={`truncate ${!value || (Array.isArray(value) && value.length === 0) ? 'text-gray-400' : (isDarkMode ? 'text-white' : 'text-gray-900')}`}>
            {Array.isArray(value) && value.length > 0 
              ? selectedOptions.map(opt => opt.label).join(', ') + (value.includes('other') ? (selectedOptions.length > 0 ? ', Other' : 'Other') : '')
              : 'Select service(s)...'}
          </span>
          {totalSelectedPrice > 0 && (
            <span className="text-amber-400 font-semibold text-xs">
              Total: ₱{totalSelectedPrice.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
            </span>
          )}
        </div>
        <ChevronDownIcon className={`h-4 w-4 text-amber-400 flex-shrink-0 transition-transform ${isOpen ? 'rotate-180' : ''}`} />
      </button>

      {/* Dropdown Menu */}
      {isOpen && !disabled && (
        <div className="absolute z-50 w-full mt-1 bg-gray-900 border border-amber-500/30 rounded-lg shadow-xl shadow-black/50 overflow-hidden flex flex-col max-h-[400px]">
          {/* Search Input */}
          <div className="p-2 border-b border-gray-700/50 bg-gray-900/95 backdrop-blur-sm sticky top-0 z-10">
            <div className="relative">
              <MagnifyingGlassIcon className="absolute left-2 top-1/2 transform -translate-y-1/2 h-3.5 w-3.5 text-amber-400/60" />
              <input
                type="text"
                placeholder="Search services..."
                value={searchTerm}
                onChange={(e) => setSearchTerm(e.target.value)}
                className="w-full pl-8 pr-3 py-2 bg-gray-800/80 border border-gray-700 rounded-md text-xs text-white placeholder-gray-500 focus:outline-none focus:ring-1 focus:ring-amber-500/50 transition-all"
                autoFocus
              />
            </div>
          </div>

          {/* Options List */}
          <div className="overflow-y-auto py-1 scrollbar-thin scrollbar-thumb-amber-500/20">
            {filteredOptions.length > 0 ? (
              filteredOptions.map((option) => (
                <button
                  key={option.value}
                  type="button"
                  onClick={() => !option.is_unavailable && handleToggle(option.value)}
                  disabled={option.is_unavailable}
                  className={`w-full px-3 py-2.5 text-left text-xs transition-colors group flex items-start gap-3 ${
                    option.is_unavailable
                      ? 'opacity-60 cursor-not-allowed bg-red-500/5'
                      : `hover:bg-amber-500/10 ${Array.isArray(value) && value.includes(option.value) ? 'bg-amber-500/5' : ''}`
                  }`}
                >
                  <div className={`mt-0.5 w-4 h-4 rounded border flex-shrink-0 flex items-center justify-center transition-all ${
                    option.is_unavailable
                      ? 'border-red-500/40 bg-red-500/10'
                      : Array.isArray(value) && value.includes(option.value)
                        ? 'bg-amber-500 border-amber-500'
                        : 'border-gray-600 group-hover:border-amber-500/50'
                  }`}>
                    {option.is_unavailable ? (
                      <svg className="w-2.5 h-2.5 text-red-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth="3">
                        <path strokeLinecap="round" strokeLinejoin="round" d="M6 18L18 6M6 6l12 12" />
                      </svg>
                    ) : Array.isArray(value) && value.includes(option.value) ? (
                      <svg className="w-2.5 h-2.5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth="4">
                        <path strokeLinecap="round" strokeLinejoin="round" d="M5 13l4 4L19 7" />
                      </svg>
                    ) : null}
                  </div>
                  <div className="flex flex-col gap-0.5 min-w-0 flex-1">
                    <span className={`font-medium truncate ${
                      option.is_unavailable ? 'text-red-400/70 line-through' :
                      Array.isArray(value) && value.includes(option.value) ? 'text-amber-400' : 'text-gray-200'
                    }`}>
                      {option.label}
                    </span>
                    {option.is_unavailable ? (
                      <span className="text-red-400/60 text-[10px]">
                        Unavailable{option.unavailability_reason ? `: ${option.unavailability_reason}` : ''}
                        {option.unavailable_until ? ` (until ${new Date(option.unavailable_until).toLocaleDateString()})` : ''}
                      </span>
                    ) : option.price ? (
                      <span className="text-amber-400/60 text-[10px] font-mono">₱{parseFloat(option.price).toLocaleString(undefined, { minimumFractionDigits: 2 })}</span>
                    ) : null}
                  </div>
                  {/* View Requirements Icon */}
                  {!option.is_unavailable && option.public_requirements && option.public_requirements.length > 0 && (
                    <span
                      role="button"
                      tabIndex={0}
                      title="View Requirements"
                      onClick={(e) => {
                        e.stopPropagation(); // Prevents toggling the selection
                        if (onViewRequirements) onViewRequirements(option);
                      }}
                      onKeyDown={(e) => {
                        if (e.key === 'Enter' || e.key === ' ') {
                          e.stopPropagation();
                          if (onViewRequirements) onViewRequirements(option);
                        }
                      }}
                      className="ml-auto text-amber-500/70 hover:text-amber-400 px-1 py-1 rounded cursor-pointer"
                    >
                      <InformationCircleIcon className="w-4 h-4" />
                    </span>
                  )}
                </button>
              ))
            ) : (
              <div className="px-3 py-4 text-center">
                <p className="text-xs text-gray-500 italic">No services found match your search</p>
              </div>
            )}
            
            <div className="border-t border-gray-800 my-1"></div>

            {/* Always show "Other" option */}
            <button
              type="button"
              onClick={() => handleToggle('other')}
              className={`w-full px-3 py-2.5 text-left text-xs transition-colors group flex items-start gap-3 hover:bg-amber-500/10 ${
                Array.isArray(value) && value.includes('other') ? 'bg-amber-500/5' : ''
              }`}
            >
              <div className={`mt-0.5 w-4 h-4 rounded border flex-shrink-0 flex items-center justify-center transition-all ${
                Array.isArray(value) && value.includes('other')
                  ? 'bg-amber-500 border-amber-500'
                  : 'border-gray-600 group-hover:border-amber-500/50'
              }`}>
                {Array.isArray(value) && value.includes('other') && (
                  <svg className="w-2.5 h-2.5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth="4">
                    <path strokeLinecap="round" strokeLinejoin="round" d="M5 13l4 4L19 7" />
                  </svg>
                )}
              </div>
              <span className={`font-medium ${Array.isArray(value) && value.includes('other') ? 'text-amber-400' : 'text-gray-200'}`}>
                Other (Specify)
              </span>
            </button>
          </div>
          
          {/* Footer with summary */}
          {Array.isArray(value) && value.length > 0 && (
            <div className="p-2 border-t border-gray-700 bg-gray-900/90 backdrop-blur-sm sticky bottom-0 flex justify-between items-center">
              <span className="text-[10px] text-gray-400">{value.length} selected</span>
              <button 
                type="button"
                onClick={() => setIsOpen(false)}
                className="text-xs px-2 py-1 bg-amber-500 hover:bg-amber-600 text-white rounded font-medium transition-colors"
              >
                Done
              </button>
            </div>
          )}
        </div>
      )}

      {/* Other Service Input */}
      {showOtherInput && (
        <div className="mt-2 animate-in fade-in slide-in-from-top-1 duration-200">
          <input
            type="text"
            disabled={disabled}
            placeholder="Please specify the service type..."
            value={otherValue}
            onChange={handleOtherInputChange}
            className={`w-full px-3 py-2 bg-gray-800 border border-amber-500/30 rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-500 text-sm text-white placeholder-gray-500 ${
              disabled ? 'opacity-50 cursor-not-allowed bg-gray-900' : ''
            }`}
          />
        </div>
      )}

      {error && (
        <p className="text-red-400 text-xs mt-1.5 flex items-center gap-1.5 px-1">
          <ExclamationTriangleIcon className="h-3.5 w-3.5 flex-shrink-0" />
          {error}
        </p>
      )}
    </div>
  );
};

// Enhanced Calendar Component
const EnhancedCalendar = ({ value, onChange, error, disabled = false, dailyLimitInfo = {}, isDarkMode = true }) => {
  const [isOpen, setIsOpen] = useState(false);
  const [currentMonth, setCurrentMonth] = useState(new Date());
  const [selectedDate, setSelectedDate] = useState(value ? new Date(value) : null);
  const calendarRef = React.useRef(null);
  const [unavailableDates, setUnavailableDates] = useState([]);
  const [blockInfo, setBlockInfo] = useState(null); // { date, reason, timeRange, type }

  // Close calendar on Escape or click outside
  React.useEffect(() => {
    if (!isOpen) return;
    const handleKeyDown = (e) => { if (e.key === 'Escape') setIsOpen(false); };
    const handleClickOutside = (e) => {
      if (calendarRef.current && !calendarRef.current.contains(e.target)) setIsOpen(false);
    };
    document.addEventListener('keydown', handleKeyDown);
    document.addEventListener('mousedown', handleClickOutside);
    return () => {
      document.removeEventListener('keydown', handleKeyDown);
      document.removeEventListener('mousedown', handleClickOutside);
    };
  }, [isOpen]);

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

  // Get detailed block info for a date
  const getDateBlockInfo = (date) => {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    const dateStr = `${year}-${month}-${day}`;
    const dayOfWeek = date.getDay();
    const dayName = ['sunday','monday','tuesday','wednesday','thursday','friday','saturday'][dayOfWeek];

    // Past dates
    if (date < minDate) return { isBlocked: true, reason: 'Past date', type: 'past', timeRange: null };

    // Weekends
    if (dayOfWeek === 0 || dayOfWeek === 6) {
      return { isBlocked: true, reason: `${dayName.charAt(0).toUpperCase() + dayName.slice(1)} — Office Closed`, type: 'weekend', timeRange: null };
    }

    // Check admin-set unavailable/blackout dates
    const matchingEntries = unavailableDates.filter(u => {
      const uDate = (u.date || '').toString().split('T')[0];
      if (uDate && uDate === dateStr) return true;
      if (u.is_recurring && u.recurring_days && u.recurring_days.includes(dayName)) return true;
      if (u.type === 'weekend' && (dayOfWeek === 0 || dayOfWeek === 6)) return true;
      return false;
    });

    if (matchingEntries.length > 0) {
      // Check if any entry is a full-day block (no time range)
      const fullDayBlock = matchingEntries.find(u => {
        const hasTimeRange = (u.start_time && u.end_time) || (u.time_range);
        const isAllDay = u.all_day === true || u.all_day === 1;
        return !hasTimeRange || isAllDay;
      });

      if (fullDayBlock) {
        return {
          isBlocked: true,
          reason: fullDayBlock.reason || 'Blocked by admin',
          type: 'full_block',
          timeRange: null
        };
      }

      // All entries have time ranges — partial block
      const timeRanges = matchingEntries.map(u => ({
        reason: u.reason || 'Blocked by admin',
        timeRange: u.time_range || (u.start_time && u.end_time ? `${u.start_time} - ${u.end_time}` : null)
      })).filter(t => t.timeRange);

      if (timeRanges.length > 0) {
        return {
          isBlocked: false, // Date is still selectable
          isPartialBlock: true,
          reason: timeRanges.map(t => t.reason).join(', '),
          type: 'time_range',
          timeRanges: timeRanges
        };
      }

      // Fallback: treat as full block
      return {
        isBlocked: true,
        reason: matchingEntries[0].reason || 'Blocked by admin',
        type: 'full_block',
        timeRange: null
      };
    }

    return { isBlocked: false, isPartialBlock: false, reason: null, type: null, timeRange: null };
  };

  const isDateDisabled = (date) => {
    const info = getDateBlockInfo(date);
    return info.isBlocked;
  };

  // Handle blocked date click — show reason popup
  const handleBlockedDateClick = (date) => {
    const info = getDateBlockInfo(date);
    if (info.isBlocked || info.isPartialBlock) {
      setBlockInfo({
        date: date.toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' }),
        reason: info.reason,
        type: info.type,
        timeRanges: info.timeRanges || null,
        isPartialBlock: info.isPartialBlock || false
      });
    }
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

    // Listen for admin changes to unavailable dates (via realtime polling)
    const handleUnavailableDatesChanged = () => {
      loadUnavailable();
    };
    window.addEventListener('unavailableDatesChanged', handleUnavailableDatesChanged);

    return () => {
      mounted = false;
      window.removeEventListener('unavailableDatesChanged', handleUnavailableDatesChanged);
    };
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
      const date = new Date(currentMonth.getFullYear(), currentMonth.getMonth(), i);
      const dateBlockInfo = getDateBlockInfo(date);
      const isDisabled = dateBlockInfo.isBlocked;
      const isPartial = dateBlockInfo.isPartialBlock;
      const isSelected = selectedDate && 
        date.getDate() === selectedDate.getDate() &&
        date.getMonth() === selectedDate.getMonth() &&
        date.getFullYear() === selectedDate.getFullYear();
      const isToday = date.toDateString() === today.toDateString();

      days.push(
        <div key={`current-${i}`} className="p-1">
          <button
            type="button"
            onClick={() => {
              if (isDisabled) {
                handleBlockedDateClick(date);
              } else if (isPartial) {
                handleBlockedDateClick(date);
                handleDateSelect(date);
              } else {
                handleDateSelect(date);
              }
            }}
            className={`w-full h-8 flex items-center justify-center text-xs rounded border relative ${
              isDisabled
                ? 'text-red-400 bg-red-900/30 border-red-700/50 cursor-pointer hover:bg-red-900/50'
                : isPartial
                ? isSelected
                  ? 'bg-amber-500 text-white border-amber-500 shadow-lg shadow-amber-500/25'
                  : 'text-amber-300 bg-amber-900/20 border-amber-500/40 hover:bg-amber-500/20 cursor-pointer'
                : isSelected
                ? 'bg-amber-500 text-white border-amber-500 shadow-lg shadow-amber-500/25'
                : isToday
                ? 'bg-amber-500/20 text-amber-300 border-amber-500/30 hover:bg-amber-500/30'
                : 'text-amber-50 bg-gray-800/50 border-gray-600 hover:bg-amber-500/10 hover:border-amber-500/40'
            }`}
            title={isDisabled ? dateBlockInfo.reason : isPartial ? `⚠ Partial block: ${dateBlockInfo.reason}` : ''}
          >
            {i}
            {isDisabled && dateBlockInfo.type !== 'past' && (
              <span className="absolute -top-0.5 -right-0.5 w-2 h-2 rounded-full bg-red-500" />
            )}
            {isPartial && (
              <span className="absolute -top-0.5 -right-0.5 w-2 h-2 rounded-full bg-amber-500" />
            )}
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
    <div className="relative" ref={calendarRef}>
      <label className={`block text-xs font-medium mb-1 ${isDarkMode ? 'text-amber-50' : 'text-gray-700'}`}>
        Preferred Date <span className="text-red-500">*</span>
      </label>
      
      <button
        type="button"
        disabled={disabled}
        onClick={() => !disabled && setIsOpen(!isOpen)}
        aria-expanded={isOpen}
        aria-haspopup="dialog"
        className={`w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-500 text-sm text-left flex justify-between items-center transition-colors ${
          isDarkMode ? 'bg-gray-800 text-white' : 'bg-white text-gray-900'
        } ${
          disabled ? 'opacity-50 cursor-not-allowed' : ''
        } ${
          error ? 'border-red-500' : isDarkMode ? 'border-gray-600 focus:border-amber-500' : 'border-gray-300 focus:border-amber-500'
        }`}
      >
        <span className={!value ? 'text-gray-400' : (isDarkMode ? 'text-white' : 'text-gray-900')}>
          {value ? new Date(value).toLocaleDateString('en-US', { 
            weekday: 'short', 
            year: 'numeric', 
            month: 'short', 
            day: 'numeric' 
          }) : 'Select appointment date...'}
        </span>
        <CalendarDaysIcon className={`h-4 w-4 ${isDarkMode ? 'text-amber-400' : 'text-amber-600'}`} />
      </button>

      {/* Block Info Popup */}
      {blockInfo && (
        <div className="absolute z-[60] w-full mt-1 bg-gray-800 border border-red-500/40 rounded-lg shadow-lg shadow-red-500/10 p-4 animate-in slide-in-from-top-1">
          <div className="flex items-start justify-between mb-2">
            <div className="flex items-center gap-2">
              {blockInfo.isPartialBlock ? (
                <ClockIcon className="h-5 w-5 text-amber-400 flex-shrink-0" />
              ) : (
                <ExclamationTriangleIcon className="h-5 w-5 text-red-400 flex-shrink-0" />
              )}
              <h4 className={`text-sm font-semibold ${blockInfo.isPartialBlock ? 'text-amber-300' : 'text-red-300'}`}>
                {blockInfo.isPartialBlock ? 'Partially Blocked Date' : 'Date Unavailable'}
              </h4>
            </div>
            <button
              type="button"
              onClick={() => setBlockInfo(null)}
              className="text-gray-400 hover:text-gray-200 p-0.5 rounded hover:bg-gray-700"
            >
              <XMarkIcon className="h-4 w-4" />
            </button>
          </div>
          <div className="space-y-2">
            <p className="text-xs text-gray-300">
              <span className="font-medium text-gray-200">{blockInfo.date}</span>
            </p>
            <div className={`text-xs px-3 py-2 rounded-lg border ${
              blockInfo.isPartialBlock
                ? 'bg-amber-900/20 border-amber-500/30 text-amber-200'
                : 'bg-red-900/20 border-red-500/30 text-red-200'
            }`}>
              <p className="font-medium mb-1">Reason:</p>
              <p>{blockInfo.reason}</p>
            </div>
            {blockInfo.timeRanges && blockInfo.timeRanges.length > 0 && (
              <div className="text-xs bg-amber-900/20 border border-amber-500/30 rounded-lg px-3 py-2 text-amber-200">
                <p className="font-medium mb-1 flex items-center gap-1">
                  <ClockIcon className="h-3 w-3" /> Blocked Time Ranges:
                </p>
                {blockInfo.timeRanges.map((tr, idx) => (
                  <div key={idx} className="flex items-center gap-2 ml-2 mt-1">
                    <span className="w-1.5 h-1.5 rounded-full bg-amber-400"></span>
                    <span className="font-mono">{tr.timeRange}</span>
                    <span className="text-amber-300/70">— {tr.reason}</span>
                  </div>
                ))}
                <p className="mt-2 text-amber-300/80 italic">
                  You may still book this date outside the blocked time ranges.
                </p>
              </div>
            )}
            {!blockInfo.isPartialBlock && (
              <p className="text-xs text-gray-400 italic">
                Please select a different date for your appointment.
              </p>
            )}
          </div>
        </div>
      )}

      {isOpen && !disabled && (
        <div className="absolute z-50 w-full mt-1 bg-gray-800 border border-amber-500/30 rounded-lg shadow-lg shadow-amber-500/10 p-3">
          {/* Month Navigation */}
          <div className="flex items-center justify-between mb-3">
            <button
              type="button"
              onClick={() => navigateMonth(-1)}
              className="p-1 rounded text-gray-300 hover:bg-gray-700 hover:text-amber-300 transition-colors"
            >
              <ArrowLeftIcon className="h-4 w-4" />
            </button>
            <span className="text-sm font-semibold text-amber-200">
              {currentMonth.toLocaleDateString('en-US', { month: 'long', year: 'numeric' })}
            </span>
            <button
              type="button"
              onClick={() => navigateMonth(1)}
              className="p-1 rounded text-gray-300 hover:bg-gray-700 hover:text-amber-300 transition-colors"
            >
              <ChevronDownIcon className="h-4 w-4 -rotate-90" />
            </button>
          </div>

          {/* Day headers */}
          <div className="grid grid-cols-7 gap-1 mb-1">
            {['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'].map(d => (
              <div key={d} className="text-center text-[10px] font-semibold text-gray-500 py-1">{d}</div>
            ))}
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
                  <InformationCircleIcon className="h-5 w-5 flex-shrink-0 text-blue-400 mt-0.5" />
                  <div>
                    <h3 className="font-semibold text-blue-400">📅 Booking Limit Reached</h3>
                    <p className="text-sm text-blue-300/80 mt-1">
                      {dailyLimitInfo.message || `You have reached your booking limit of ${dailyLimitInfo.limit} appointments per 24 hours.`}
                    </p>
                    {dailyLimitInfo.bookingsToday?.length > 0 && (
                      <div className="mt-3 text-xs text-blue-300/70">
                        <p className="font-medium mb-2">Your recent bookings (last 24h):</p>
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

                {/* Legend */}
                <div className="mt-2 flex flex-wrap gap-x-3 gap-y-1 text-[10px]">
                  <div className="flex items-center gap-1">
                    <span className="w-2 h-2 rounded-full bg-green-400/60"></span>
                    <span className="text-gray-400">Available</span>
                  </div>
                  <div className="flex items-center gap-1">
                    <span className="w-2 h-2 rounded-full bg-red-500"></span>
                    <span className="text-gray-400">Blocked</span>
                  </div>
                  <div className="flex items-center gap-1">
                    <span className="w-2 h-2 rounded-full bg-amber-500"></span>
                    <span className="text-gray-400">Partial Block</span>
                  </div>
                </div>

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
const AppointmentDetailModal = ({ isOpen, onClose, appointment, isDarkMode = true }) => {
  useEffect(() => {
    if (!isOpen) return;
    const handleEsc = (e) => { if (e.key === 'Escape') onClose(); };
    document.addEventListener('keydown', handleEsc);
    return () => document.removeEventListener('keydown', handleEsc);
  }, [isOpen, onClose]);

  if (!isOpen || !appointment) return null;

  const hasMultipleServices = appointment.services && appointment.services.length > 0;
  const servicesToDisplay = hasMultipleServices ? appointment.services : (appointment.service ? [appointment.service] : []);
  const totalAmount = appointment.payment_amount || appointment.original_price || 0;

  const createdAtDate = appointment.created_at ? new Date(appointment.created_at) : null;
  const bookedOnText = createdAtDate
    ? `${createdAtDate.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })} at ${createdAtDate.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' })}`
    : null;

  const statusConfig = {
    pending: { label: 'Pending', color: isDarkMode ? 'text-amber-400 bg-amber-400/10 border-amber-400/20' : 'text-amber-600 bg-amber-50 border-amber-200' },
    approved: { label: 'Approved', color: isDarkMode ? 'text-blue-400 bg-blue-400/10 border-blue-400/20' : 'text-blue-600 bg-blue-50 border-blue-200' },
    confirmed: { label: 'Confirmed', color: isDarkMode ? 'text-blue-400 bg-blue-400/10 border-blue-400/20' : 'text-blue-600 bg-blue-50 border-blue-200' },
    completed: { label: 'Completed', color: isDarkMode ? 'text-emerald-400 bg-emerald-400/10 border-emerald-400/20' : 'text-emerald-600 bg-emerald-50 border-emerald-200' },
    cancelled: { label: 'Cancelled', color: isDarkMode ? 'text-red-400 bg-red-400/10 border-red-400/20' : 'text-red-600 bg-red-50 border-red-200' },
    declined: { label: 'Declined', color: isDarkMode ? 'text-red-400 bg-red-400/10 border-red-400/20' : 'text-red-600 bg-red-50 border-red-200' },
    no_show: { label: 'No Show', color: isDarkMode ? 'text-gray-400 bg-gray-400/10 border-gray-400/20' : 'text-gray-600 bg-gray-50 border-gray-200' },
  };
  const sc = statusConfig[appointment.status] || statusConfig.pending;

  return (
    <div className="fixed inset-0 z-[100] flex items-end sm:items-center justify-center bg-black/60" onClick={onClose}>
      <div
        className={`relative w-full sm:max-w-lg max-h-[90vh] sm:max-h-[85vh] flex flex-col overflow-hidden rounded-t-2xl sm:rounded-xl border ${
          isDarkMode ? 'bg-gray-900 border-gray-800' : 'bg-white border-gray-200'
        }`}
        onClick={e => e.stopPropagation()}
      >
        {/* Mobile drag handle */}
        <div className="flex justify-center pt-2 pb-1 sm:hidden">
          <div className={`w-8 h-1 rounded-full ${isDarkMode ? 'bg-gray-700' : 'bg-gray-300'}`} />
        </div>

        {/* Header */}
        <div className={`px-5 pt-3 sm:pt-5 pb-4 flex items-center justify-between flex-shrink-0 border-b ${isDarkMode ? 'border-gray-800' : 'border-gray-100'}`}>
          <div className="min-w-0 flex-1">
            <h3 className={`text-base font-bold ${isDarkMode ? 'text-white' : 'text-gray-900'}`}>
              Appointment Details
            </h3>
            {bookedOnText && (
              <p className={`text-xs mt-0.5 ${isDarkMode ? 'text-gray-500' : 'text-gray-400'}`}>
                Booked {bookedOnText}
              </p>
            )}
          </div>
          <button
            onClick={onClose}
            className={`p-1.5 rounded-lg flex-shrink-0 ml-3 ${
              isDarkMode ? 'text-gray-500 hover:text-white hover:bg-gray-800' : 'text-gray-400 hover:text-gray-900 hover:bg-gray-100'
            }`}
          >
            <XMarkIcon className="h-5 w-5" />
          </button>
        </div>

        {/* Content */}
        <div className="flex-1 overflow-y-auto px-5 py-4 space-y-4">
          {/* Status + ID row */}
          <div className="flex items-center justify-between">
            <span className={`inline-flex items-center px-2.5 py-1 rounded-md text-xs font-semibold border ${sc.color}`}>
              {sc.label}
            </span>
            <span className={`text-xs font-mono ${isDarkMode ? 'text-gray-600' : 'text-gray-400'}`}>#{appointment.id}</span>
          </div>

          {/* Date & Time */}
          <div className={`grid grid-cols-2 gap-3`}>
            <div className={`p-3 rounded-lg ${isDarkMode ? 'bg-gray-800/60' : 'bg-gray-50'}`}>
              <p className={`text-[10px] font-semibold uppercase tracking-wider mb-1 ${isDarkMode ? 'text-gray-500' : 'text-gray-400'}`}>Date</p>
              <p className={`text-sm font-medium ${isDarkMode ? 'text-white' : 'text-gray-900'}`}>
                {formatDateDisplay(appointment.appointment_date)}
              </p>
            </div>
            <div className={`p-3 rounded-lg ${isDarkMode ? 'bg-gray-800/60' : 'bg-gray-50'}`}>
              <p className={`text-[10px] font-semibold uppercase tracking-wider mb-1 ${isDarkMode ? 'text-gray-500' : 'text-gray-400'}`}>Time</p>
              <p className={`text-sm font-medium ${isDarkMode ? 'text-white' : 'text-gray-900'}`}>
                {formatTime12Hour(appointment.appointment_time)}
              </p>
            </div>
          </div>

          {/* Assigned Staff */}
          {appointment.staff && (
            <div className={`p-3 rounded-lg ${isDarkMode ? 'bg-gray-800/60' : 'bg-gray-50'}`}>
              <p className={`text-[10px] font-semibold uppercase tracking-wider mb-1 ${isDarkMode ? 'text-gray-500' : 'text-gray-400'}`}>Assigned Staff</p>
              <p className={`text-sm font-medium ${isDarkMode ? 'text-white' : 'text-gray-900'}`}>
                {appointment.staff.first_name} {appointment.staff.last_name}
              </p>
            </div>
          )}

          {/* Services */}
          <div>
            <p className={`text-[10px] font-semibold uppercase tracking-wider mb-2 ${isDarkMode ? 'text-gray-500' : 'text-gray-400'}`}>
              Services ({servicesToDisplay.length})
            </p>
            <div className={`rounded-lg overflow-hidden border ${isDarkMode ? 'border-gray-800' : 'border-gray-200'}`}>
              {servicesToDisplay.map((service, idx) => (
                <div
                  key={idx}
                  className={`px-3 py-2.5 flex items-center justify-between text-sm ${
                    idx > 0 ? (isDarkMode ? 'border-t border-gray-800' : 'border-t border-gray-100') : ''
                  } ${isDarkMode ? 'bg-gray-800/40' : 'bg-gray-50'}`}
                >
                  <span className={`${isDarkMode ? 'text-gray-200' : 'text-gray-700'}`}>
                    {service.name || formatServiceName({ service })}
                  </span>
                  <span className={`font-mono text-xs ${isDarkMode ? 'text-gray-400' : 'text-gray-500'}`}>
                    ₱{parseFloat(service.price || 0).toLocaleString(undefined, { minimumFractionDigits: 2 })}
                  </span>
                </div>
              ))}
              <div className={`px-3 py-2.5 flex items-center justify-between border-t ${isDarkMode ? 'border-gray-700 bg-gray-800/70' : 'border-gray-200 bg-gray-100/60'}`}>
                <span className={`text-xs font-bold uppercase tracking-wider ${isDarkMode ? 'text-gray-300' : 'text-gray-700'}`}>Total</span>
                <span className={`text-sm font-bold ${isDarkMode ? 'text-amber-400' : 'text-amber-600'}`}>
                  ₱{parseFloat(totalAmount).toLocaleString(undefined, { minimumFractionDigits: 2 })}
                </span>
              </div>
            </div>
          </div>

          {/* Notes */}
          {appointment.notes && (
            <div className={`p-3 rounded-lg ${isDarkMode ? 'bg-gray-800/60' : 'bg-gray-50'}`}>
              <p className={`text-[10px] font-semibold uppercase tracking-wider mb-1 ${isDarkMode ? 'text-gray-500' : 'text-gray-400'}`}>Notes</p>
              <p className={`text-sm leading-relaxed ${isDarkMode ? 'text-gray-300' : 'text-gray-600'}`}>
                {appointment.notes}
              </p>
            </div>
          )}
        </div>

        {/* Footer */}
        <div className={`px-5 py-3 border-t flex-shrink-0 ${isDarkMode ? 'border-gray-800' : 'border-gray-100'}`}>
          <button
            onClick={onClose}
            className={`w-full py-2.5 rounded-lg text-sm font-medium ${
              isDarkMode
                ? 'bg-gray-800 text-gray-300 hover:text-white hover:bg-gray-700'
                : 'bg-gray-100 text-gray-600 hover:bg-gray-200'
            }`}
          >
            Close
          </button>
        </div>
      </div>
    </div>
  );
};

// Confirmation Modal
const ConfirmationModal = ({ isOpen, onClose, onConfirm, title, message, confirmText = "Confirm", type = "primary", loading = false, isDarkMode = true }) => {
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

  const iconBgColors = {
    danger: isDarkMode ? 'bg-red-500/20' : 'bg-red-100',
    warning: isDarkMode ? 'bg-yellow-500/20' : 'bg-yellow-100',
    success: isDarkMode ? 'bg-green-500/20' : 'bg-green-100',
    primary: isDarkMode ? 'bg-amber-500/20' : 'bg-amber-100'
  };

  const iconTextColors = {
    danger: isDarkMode ? 'text-red-400' : 'text-red-600',
    warning: isDarkMode ? 'text-yellow-400' : 'text-yellow-600',
    success: isDarkMode ? 'text-green-400' : 'text-green-600',
    primary: isDarkMode ? 'text-amber-400' : 'text-amber-600'
  };

  const IconComponent = icons[type];

  return (
    <div className={`fixed inset-0 flex items-center justify-center z-50 p-4 animate-fadeIn ${
      isDarkMode ? 'bg-black/70' : 'bg-gray-900/40 backdrop-blur-sm'
    }`}>
      <div className={`rounded-2xl shadow-2xl w-full max-w-md transform animate-scaleIn ${
        isDarkMode 
          ? 'bg-gray-900 border border-amber-500/30' 
          : 'bg-white border border-gray-200 shadow-gray-200/50'
      }`}>
        <div className="p-6">
          {/* Icon + Title */}
          <div className="flex items-center mb-4">
            <div className={`p-2.5 rounded-xl ${iconBgColors[type] || iconBgColors.primary}`}>
              <IconComponent className={`h-5 w-5 ${iconTextColors[type] || iconTextColors.primary}`} />
            </div>
            <h3 className={`text-base font-bold ml-3 ${
              isDarkMode ? 'text-amber-50' : 'text-gray-900'
            }`}>{title}</h3>
          </div>

          {/* Message */}
          <p className={`text-sm mb-6 leading-relaxed ${
            isDarkMode ? 'text-gray-300' : 'text-gray-600'
          }`}>{message}</p>

          {/* Action Buttons */}
          <div className="flex flex-col-reverse sm:flex-row gap-3 justify-end">
            <button
              onClick={onClose}
              disabled={loading}
              className={`px-4 py-2.5 font-semibold text-sm rounded-xl transition-all duration-200 focus:outline-none focus:ring-2 disabled:opacity-50 disabled:cursor-not-allowed ${
                isDarkMode 
                  ? 'border-2 border-amber-500/50 text-amber-400 hover:bg-amber-500/10 hover:border-amber-400 focus:ring-amber-500 focus:ring-offset-2 focus:ring-offset-gray-900' 
                  : 'border border-gray-300 text-gray-700 bg-white hover:bg-gray-50 hover:border-gray-400 focus:ring-gray-400 focus:ring-offset-2'
              }`}
            >
              Cancel
            </button>
            <button
              onClick={onConfirm}
              disabled={loading}
              className={`px-4 py-2.5 text-white font-semibold text-sm rounded-xl transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed ${
                isDarkMode ? 'focus:ring-offset-gray-900' : 'focus:ring-offset-white'
              } ${buttonColors[type]}`}
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
const SettingsModal = ({ isOpen, onClose, settings, onSettingsChange, onOpenTerms, isDarkMode = true }) => {
  useEffect(() => {
    if (!isOpen) return;
    const handleEsc = (e) => { if (e.key === 'Escape') onClose(); };
    document.addEventListener('keydown', handleEsc);
    return () => document.removeEventListener('keydown', handleEsc);
  }, [isOpen, onClose]);

  if (!isOpen) return null;

  return (
    <div className={`fixed inset-0 flex items-center justify-center z-50 p-4 animate-fadeIn ${isDarkMode ? 'bg-black/70' : 'bg-gray-900/40 backdrop-blur-sm'}`} role="dialog" aria-modal="true" aria-label="Settings">
      <div className={`rounded-lg shadow-xl w-full max-w-md max-h-[90vh] overflow-y-auto transform animate-scaleIn border ${isDarkMode ? 'bg-gray-900 border-amber-500/30' : 'bg-white border-gray-200'}`}>
        <div className={`flex justify-between items-center p-4 border-b sticky top-0 ${isDarkMode ? 'bg-gray-900 border-gray-700' : 'bg-white border-gray-200'}`}>
          <div className="flex items-center">
            <Cog6ToothIcon className={`h-5 w-5 mr-2 ${isDarkMode ? 'text-amber-400' : 'text-amber-600'}`} aria-hidden="true" />
            <h3 className={`text-sm font-semibold ${isDarkMode ? 'text-amber-50' : 'text-gray-900'}`}>Settings</h3>
          </div>
          <button 
            onClick={onClose} 
            className={`rounded p-1 transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-amber-500 ${isDarkMode ? 'text-gray-400 hover:text-amber-400' : 'text-gray-500 hover:text-amber-600'}`}
            aria-label="Close settings"
          >
            <XMarkIcon className="h-4 w-4" />
          </button>
        </div>
        
        <div className="p-4 space-y-4">
          {/* Theme Settings */}
          <div className="space-y-3">
            <h4 className={`text-xs font-semibold ${isDarkMode ? 'text-amber-50' : 'text-gray-900'}`}>Appearance</h4>
            
            <div className={`flex items-center justify-between p-3 rounded-lg border ${isDarkMode ? 'bg-gray-800/50 border-gray-600' : 'bg-gray-50 border-gray-200'}`}>
              <div className="flex items-center space-x-2">
                {settings.theme === 'dark' ? (
                  <MoonIcon className={`h-4 w-4 ${isDarkMode ? 'text-amber-400' : 'text-amber-600'}`} aria-hidden="true" />
                ) : (
                  <SunIcon className={`h-4 w-4 ${isDarkMode ? 'text-amber-400' : 'text-amber-600'}`} aria-hidden="true" />
                )}
                <div>
                  <p className={`font-medium text-sm ${isDarkMode ? 'text-amber-50' : 'text-gray-900'}`}>Theme</p>
                  <p className={`text-xs ${isDarkMode ? 'text-amber-400/70' : 'text-gray-500'}`}>Choose your preferred theme</p>
                </div>
              </div>
              <div className="flex items-center space-x-2">
                <label className="relative inline-flex items-center cursor-pointer">
                  <input
                    type="checkbox"
                    checked={settings.theme === 'light'}
                    onChange={(e) => onSettingsChange('theme', e.target.checked ? 'light' : 'dark')}
                    className="sr-only peer"
                    aria-label="Toggle light mode"
                  />
                  <div className={`w-10 h-5 rounded-full peer-focus:ring-2 peer-focus:ring-amber-500 peer-checked:bg-amber-600 relative after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border after:border-gray-300 after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:after:translate-x-full ${isDarkMode ? 'bg-gray-600' : 'bg-gray-300'}`}></div>
                </label>

                <select
                  value={settings.theme}
                  onChange={(e) => onSettingsChange('theme', e.target.value)}
                  className={`border rounded-lg px-2 py-1 text-xs focus:outline-none focus:ring-2 focus:ring-amber-500 focus:border-amber-500 ${isDarkMode ? 'bg-gray-800 border-gray-600 text-amber-50' : 'bg-white border-gray-300 text-gray-900'}`}
                  aria-label="Select theme"
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
            <h4 className={`text-xs font-semibold ${isDarkMode ? 'text-amber-50' : 'text-gray-900'}`}>Notifications</h4>
            
            <div className="space-y-2">
              <div className={`flex items-center justify-between p-2 rounded-lg border ${isDarkMode ? 'bg-gray-800/50 border-gray-600' : 'bg-gray-50 border-gray-200'}`}>
                <div>
                  <p className={`font-medium text-sm ${isDarkMode ? 'text-amber-50' : 'text-gray-900'}`}>Email Notifications</p>
                  <p className={`text-xs ${isDarkMode ? 'text-amber-400/70' : 'text-gray-500'}`}>Receive email updates about your appointments</p>
                </div>
                <label className="relative inline-flex items-center cursor-pointer">
                  <input
                    type="checkbox"
                    checked={settings.emailNotifications}
                    onChange={(e) => onSettingsChange('emailNotifications', e.target.checked)}
                    className="sr-only peer"
                    aria-label="Toggle email notifications"
                  />
                  <div className={`w-9 h-5 peer-focus:outline-none peer-focus:ring-amber-500 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-amber-600 ${isDarkMode ? 'bg-gray-600' : 'bg-gray-300'}`}></div>
                </label>
              </div>

              <div className={`flex items-center justify-between p-2 rounded-lg border ${isDarkMode ? 'bg-gray-800/50 border-gray-600' : 'bg-gray-50 border-gray-200'}`}>
                <div>
                  <p className={`font-medium text-sm ${isDarkMode ? 'text-amber-50' : 'text-gray-900'}`}>SMS Notifications</p>
                  <p className={`text-xs ${isDarkMode ? 'text-amber-400/70' : 'text-gray-500'}`}>Receive text message reminders</p>
                </div>
                <label className="relative inline-flex items-center cursor-pointer">
                  <input
                    type="checkbox"
                    checked={settings.smsNotifications}
                    onChange={(e) => onSettingsChange('smsNotifications', e.target.checked)}
                    className="sr-only peer"
                    aria-label="Toggle SMS notifications"
                  />
                  <div className={`w-9 h-5 peer-focus:outline-none peer-focus:ring-amber-500 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-amber-600 ${isDarkMode ? 'bg-gray-600' : 'bg-gray-300'}`}></div>
                </label>
              </div>
            </div>
          </div>

          {/* Legal */}
          <div className={`p-3 rounded-lg border ${isDarkMode ? 'bg-gray-800/50 border-gray-700' : 'bg-gray-50 border-gray-200'}`}>
            <h4 className={`font-semibold mb-2 text-sm ${isDarkMode ? 'text-amber-400' : 'text-amber-600'}`}>
              Legal
            </h4>
            <div className="space-y-2">
              <button
                onClick={() => onOpenTerms && onOpenTerms('terms')}
                className={`w-full flex items-center justify-between p-2 rounded-lg border transition-colors ${isDarkMode ? 'bg-gray-800/50 border-gray-600 hover:bg-amber-500/10' : 'bg-gray-50 border-gray-200 hover:bg-amber-50'}`}
              >
                <div className="flex items-center">
                  <span className="mr-2">📋</span>
                  <p className={`font-medium text-sm ${isDarkMode ? 'text-amber-50' : 'text-gray-900'}`}>Terms & Conditions</p>
                </div>
                <ChevronRightIcon className={`h-4 w-4 ${isDarkMode ? 'text-gray-500' : 'text-gray-400'}`} />
              </button>
              <button
                onClick={() => onOpenTerms && onOpenTerms('privacy')}
                className={`w-full flex items-center justify-between p-2 rounded-lg border transition-colors ${isDarkMode ? 'bg-gray-800/50 border-gray-600 hover:bg-amber-500/10' : 'bg-gray-50 border-gray-200 hover:bg-amber-50'}`}
              >
                <div className="flex items-center">
                  <span className="mr-2">🔒</span>
                  <p className={`font-medium text-sm ${isDarkMode ? 'text-amber-50' : 'text-gray-900'}`}>Privacy Policy</p>
                </div>
                <ChevronRightIcon className={`h-4 w-4 ${isDarkMode ? 'text-gray-500' : 'text-gray-400'}`} />
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
};

// Thank You Modal Component
const ThankYouModal = ({ isOpen, onClose, appointment, isDarkMode = true }) => {
  useEffect(() => {
    if (!isOpen) return;
    const handleEsc = (e) => { if (e.key === 'Escape') onClose(); };
    document.addEventListener('keydown', handleEsc);
    return () => document.removeEventListener('keydown', handleEsc);
  }, [isOpen, onClose]);

  if (!isOpen) return null;

  return (
    <div className={`fixed inset-0 flex items-center justify-center z-50 p-4 animate-fadeIn ${isDarkMode ? 'bg-black/70' : 'bg-gray-900/40 backdrop-blur-sm'}`} role="dialog" aria-modal="true" aria-label="Appointment booked successfully">
      <div className={`rounded-lg shadow-xl w-full max-w-md transform animate-scaleIn border ${isDarkMode ? 'bg-gray-900 border-amber-500/30' : 'bg-white border-gray-200'}`}>
        <div className="p-4">
          <div className="text-center">
            <div className={`mx-auto flex items-center justify-center h-12 w-12 rounded-full mb-3 border ${isDarkMode ? 'bg-green-500/20 border-green-500/30' : 'bg-green-100 border-green-300'}`}>
              <CheckCircleIcon className={`h-6 w-6 ${isDarkMode ? 'text-green-400' : 'text-green-600'}`} aria-hidden="true" />
            </div>
            <h3 className={`text-sm font-semibold mb-2 ${isDarkMode ? 'text-amber-50' : 'text-gray-900'}`}>
              Appointment Booked Successfully! 🎉
            </h3>
            
            {appointment && (
              <div className={`rounded-lg p-3 mb-3 border ${isDarkMode ? 'bg-gray-800/50 border-gray-600' : 'bg-gray-50 border-gray-200'}`}>
                <p className={`text-xs mb-1 ${isDarkMode ? 'text-amber-400/70' : 'text-gray-600'}`}>
                  <strong>Date:</strong> {formatDateDisplay(appointment.appointment_date)}
                </p>
                <p className={`text-xs mb-1 ${isDarkMode ? 'text-amber-400/70' : 'text-gray-600'}`}>
                  <strong>Time:</strong> {appointment.appointment_time}
                </p>
                <p className={`text-xs ${isDarkMode ? 'text-amber-400/70' : 'text-gray-600'}`}>
                  <strong>Status:</strong> <StatusBadge status="pending" />
                </p>
              </div>
            )}
            
            <p className={`text-xs mb-4 ${isDarkMode ? 'text-amber-400/70' : 'text-gray-500'}`}>
              A confirmation email has been sent to your email address. 
              You will receive another email once your appointment is approved.
            </p>
            
            <button
              onClick={onClose}
              className={`w-full px-4 py-2 text-white rounded-lg transition-all duration-200 font-medium text-sm focus:outline-none focus:ring-2 focus:ring-amber-500 focus:ring-offset-2 shadow border ${
                isDarkMode
                  ? 'bg-gradient-to-r from-amber-600 to-amber-700 hover:from-amber-700 hover:to-amber-800 focus:ring-offset-gray-900 border-amber-500/30'
                  : 'bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-600 hover:to-amber-700 focus:ring-offset-white border-amber-400'
              }`}
            >
              Close
            </button>
          </div>
        </div>
      </div>
    </div>
  );
};

// About Us Modal Component — Redesigned with modern card layout
const AboutUsModal = ({ isOpen, onClose, onOpenTerms, isDarkMode = true }) => {
  if (!isOpen) return null;

  const APP_VERSION = '1.0.0';
  const LAUNCH_DATE = '2024';
  const LOCATION = '233 Aljenjay Building, Vicente Ylagan Street, Bagong Bayan 2, Bongabong, Oriental Mindoro';

  // Professional system description
  const SYSTEM_DESCRIPTION = 'Legal Ease is a secure, modern platform for managing notarization and verification services. Our mission is to provide accessible, reliable, and efficient solutions for document authentication, appointment scheduling, and client support.';

  const services = [
    { icon: '📜', title: 'Notarization', desc: 'Certified document notarization for individuals and businesses.' },
    { icon: '✅', title: 'Verification', desc: 'Comprehensive document verification and witnessing.' },
    { icon: '👨‍⚖️', title: 'Professional Staff', desc: 'Licensed and accredited notary public professionals.' },
    { icon: '📅', title: 'Flexible Scheduling', desc: 'Convenient online booking and mobile service options.' },
  ];

  const stats = [
    { value: '24/7', label: 'Availability' },
    { value: '100%', label: 'Licensed' },
    { value: '5★', label: 'Client Satisfaction' },
    { value: 'Fast', label: 'Service Delivery' },
  ];

  return (
    <div className="fixed inset-0 flex items-center justify-center z-50 p-4 animate-fadeIn" style={{backgroundColor: isDarkMode ? 'rgba(0,0,0,0.75)' : 'rgba(0,0,0,0.4)'}}>
      <div className={`rounded-2xl shadow-2xl w-full max-w-lg transform animate-scaleIn max-h-[90vh] overflow-hidden flex flex-col ${
        isDarkMode 
          ? 'bg-gray-900 border border-amber-500/20' 
          : 'bg-white border border-gray-200'
      }`}>
        {/* Hero Header */}
        <div className={`relative px-6 pt-6 pb-5 ${isDarkMode ? 'bg-gradient-to-br from-amber-600/20 via-gray-900 to-gray-900' : 'bg-gradient-to-br from-amber-50 via-white to-white'}`}>
          <button 
            onClick={onClose} 
            className={`absolute top-4 right-4 rounded-full p-1.5 transition-all duration-200 focus:outline-none ${
              isDarkMode 
                ? 'text-gray-400 hover:text-white hover:bg-white/10' 
                : 'text-gray-500 hover:text-gray-900 hover:bg-gray-100'
            }`}
          >
            <XMarkIcon className="h-5 w-5" />
          </button>

          <div className="flex items-center gap-4">
            <div className={`w-14 h-14 rounded-2xl flex items-center justify-center shadow-lg flex-shrink-0 ${
              isDarkMode ? 'bg-gradient-to-br from-amber-400 to-amber-600 shadow-amber-500/30' : 'bg-gradient-to-br from-amber-400 to-amber-600 shadow-amber-300/40'
            }`}>
              <BuildingLibraryIcon className="h-7 w-7 text-white" />
            </div>
            <div>
              <h3 className={`text-lg font-bold ${isDarkMode ? 'text-white' : 'text-gray-900'}`}>Legal Ease</h3>
              <p className={`text-sm ${isDarkMode ? 'text-amber-400/80' : 'text-amber-700'}`}>Notarization Services</p>
            </div>
          </div>

          {/* Stats Row */}
          <div className="grid grid-cols-4 gap-2 mt-5">
            {stats.map((stat, i) => (
              <div key={i} className={`text-center py-2 rounded-xl ${isDarkMode ? 'bg-white/5 border border-white/10' : 'bg-amber-50 border border-amber-100'}`}>
                <div className={`text-sm font-bold ${isDarkMode ? 'text-amber-400' : 'text-amber-600'}`}>{stat.value}</div>
                <div className={`text-[10px] ${isDarkMode ? 'text-gray-400' : 'text-gray-600'}`}>{stat.label}</div>
              </div>
            ))}
          </div>
        </div>

        {/* Scrollable Content */}
        <div className="flex-1 overflow-y-auto px-6 py-5 space-y-5">
          {/* Mission */}
          <div>
            <p className={`text-sm leading-relaxed ${isDarkMode ? 'text-gray-300' : 'text-gray-700'}`}>
              {SYSTEM_DESCRIPTION}
            </p>
          </div>

          {/* Services Grid */}
          <div>
            <h4 className={`text-xs font-semibold uppercase tracking-wider mb-3 ${isDarkMode ? 'text-amber-400/70' : 'text-amber-700'}`}>Our Services</h4>
            <div className="grid grid-cols-2 gap-2">
              {services.map((svc, i) => (
                <div key={i} className={`p-3 rounded-xl transition-colors ${isDarkMode ? 'bg-gray-800/60 border border-gray-700/50 hover:border-amber-500/30' : 'bg-gray-50 border border-gray-100 hover:border-amber-200'}`}>
                  <span className="text-lg">{svc.icon}</span>
                  <p className={`text-xs font-semibold mt-1 ${isDarkMode ? 'text-white' : 'text-gray-900'}`}>{svc.title}</p>
                  <p className={`text-[11px] mt-0.5 leading-tight ${isDarkMode ? 'text-gray-400' : 'text-gray-600'}`}>{svc.desc}</p>
                </div>
              ))}
            </div>
          </div>

          {/* Company Details */}
          <div className={`flex items-center gap-4 p-4 rounded-xl ${isDarkMode ? 'bg-gray-800/40 border border-gray-700/50' : 'bg-gray-50 border border-gray-100'}`}>
            <div className="flex-1 space-y-2">
              <div className="flex items-center gap-2 text-xs">
                <MapPinIcon className={`h-4 w-4 flex-shrink-0 ${isDarkMode ? 'text-amber-400' : 'text-amber-600'}`} />
                <span className={isDarkMode ? 'text-gray-300' : 'text-gray-700'}>{LOCATION}</span>
              </div>
              <div className="flex items-center gap-2 text-xs">
                <CalendarIcon className={`h-4 w-4 flex-shrink-0 ${isDarkMode ? 'text-amber-400' : 'text-amber-600'}`} />
                <span className={isDarkMode ? 'text-gray-300' : 'text-gray-700'}>Founded {LAUNCH_DATE}</span>
              </div>
            </div>
          </div>



          {/* Contact */}
          <div className={`p-4 rounded-xl ${isDarkMode ? 'bg-gray-800/40 border border-gray-700/50' : 'bg-gray-50 border border-gray-100'}`}>
            <h4 className={`text-xs font-semibold uppercase tracking-wider mb-3 ${isDarkMode ? 'text-amber-400/70' : 'text-amber-700'}`}>Get In Touch</h4>
            <div className="space-y-2">
              <div className="flex items-center gap-2 text-xs">
                <EnvelopeIcon className={`h-4 w-4 flex-shrink-0 ${isDarkMode ? 'text-gray-500' : 'text-gray-400'}`} />
                <span className={isDarkMode ? 'text-gray-300' : 'text-gray-700'}>peejaydeguzmanlegal@gmail.com</span>
              </div>
              <div className="flex items-center gap-2 text-xs">
                <PhoneIcon className={`h-4 w-4 flex-shrink-0 ${isDarkMode ? 'text-gray-500' : 'text-gray-400'}`} />
                <span className={isDarkMode ? 'text-gray-300' : 'text-gray-700'}>09765075274</span>
              </div>
            </div>
          </div>

          {/* Developers */}
          <div className={`p-4 rounded-xl ${isDarkMode ? 'bg-amber-900/10 border border-amber-500/20' : 'bg-amber-50 border border-amber-200'}`}>
            <h4 className={`text-xs font-semibold uppercase tracking-wider mb-2 ${isDarkMode ? 'text-amber-400' : 'text-amber-800'}`}>Development Team</h4>
            <div className="flex items-start gap-2">
              <span className="text-sm">🎓</span>
              <p className={`text-xs leading-relaxed ${isDarkMode ? 'text-amber-50/90' : 'text-gray-800'}`}>
                Developed with pride by the students from <strong>Mindoro State University - Bongabong Campus</strong> as part of their academic pursuit of excellence.
              </p>
            </div>
          </div>

          {/* Legal Links */}
          <div className="flex gap-2">
            <button
              onClick={() => onOpenTerms && onOpenTerms('terms')}
              className={`flex-1 text-xs font-medium px-3 py-2.5 rounded-xl transition-all ${
                isDarkMode 
                  ? 'bg-gray-800/60 border border-gray-700/50 hover:border-amber-500/30 text-gray-300 hover:text-amber-400' 
                  : 'bg-gray-50 border border-gray-200 hover:border-amber-300 text-gray-700 hover:text-amber-700'
              }`}
            >
              📋 Terms
            </button>
            <button
              onClick={() => onOpenTerms && onOpenTerms('privacy')}
              className={`flex-1 text-xs font-medium px-3 py-2.5 rounded-xl transition-all ${
                isDarkMode 
                  ? 'bg-gray-800/60 border border-gray-700/50 hover:border-amber-500/30 text-gray-300 hover:text-amber-400' 
                  : 'bg-gray-50 border border-gray-200 hover:border-amber-300 text-gray-700 hover:text-amber-700'
              }`}
            >
              🔒 Privacy
            </button>
          </div>

          {/* Version */}
          <div className={`text-center pt-3 border-t ${isDarkMode ? 'border-gray-800 text-gray-600' : 'border-gray-100 text-gray-400'}`}>
            <p className="text-[11px]">v{APP_VERSION} · © 2024 Legal Ease Platform — Peejayy De Guzman Legal Services. All rights reserved.</p>
          </div>
        </div>

        {/* Footer */}
        <div className={`px-6 py-4 border-t flex-shrink-0 ${isDarkMode ? 'border-gray-800 bg-gray-900/80' : 'border-gray-100 bg-gray-50'}`}>
          <button
            onClick={onClose}
            className="w-full px-4 py-2.5 rounded-xl font-medium text-sm transition-all duration-200 bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-600 hover:to-amber-700 text-white shadow-lg shadow-amber-500/20 hover:shadow-amber-500/30"
          >
            Close
          </button>
        </div>
      </div>
    </div>
  );
};

const Dashboard = () => {
  const { user, logout, updateUser } = useAuth();
  const { callApi, loading, error, clearError } = useApi();
  const navigate = useNavigate();
  const location = useLocation();
  const [redirecting, setRedirecting] = useState(false);
  
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
    if (user?.role === 'staff' || user?.role === 'cashier') {
      setRedirecting(true);
      // Use a small timeout to allow React to update state
      const timer = setTimeout(() => {
        navigate('/cashier', { replace: true });
      }, 100);
      return () => clearTimeout(timer);
    }
    setRedirecting(false);
  }, [user, navigate]);
  
  const [activeTab, setActiveTab] = useState('home');
  const [isEditing, setIsEditing] = useState(false);
  const [isMobileNotificationsOpen, setIsMobileNotificationsOpen] = useState(false);

  const [showSettings, setShowSettings] = useState(false);
  const [showAboutUs, setShowAboutUs] = useState(false);
  const [showTermsPrivacyModal, setShowTermsPrivacyModal] = useState(false);
  const [termsPrivacyTab, setTermsPrivacyTab] = useState('terms');
  const [showLogoutModal, setShowLogoutModal] = useState(false);
  const [isLoggingOut, setIsLoggingOut] = useState(false); // Track logout loading state
  const [showThankYouModal, setShowThankYouModal] = useState(false);
  const [showRequirementsModalFor, setShowRequirementsModalFor] = useState(null);
  const [latestAppointment, setLatestAppointment] = useState(null);
  const { isDarkMode, setIsDarkMode } = useTheme(); // Use ThemeContext
  const [showMobileSidebar, setShowMobileSidebar] = useState(false);
  const [profileSection, setProfileSection] = useState('overview');
  const [showProfileMenu, setShowProfileMenu] = useState(false);

  const [showMobileNotifications, setShowMobileNotifications] = useState(false);
  const [showProfileDropdown, setShowProfileDropdown] = useState(false);
  const profileDropdownRef = useRef(null);

  // Close profile dropdown on outside click
  useEffect(() => {
    const handler = (e) => {
      if (profileDropdownRef.current && !profileDropdownRef.current.contains(e.target)) {
        setShowProfileDropdown(false);
      }
    };
    if (showProfileDropdown) document.addEventListener('mousedown', handler);
    return () => document.removeEventListener('mousedown', handler);
  }, [showProfileDropdown]);

  // Sync active tab with URL query `?tab=` so BottomNav can navigate using query params
  useEffect(() => {
    try {
      const params = new URLSearchParams(location.search);
      const tab = params.get('tab');
      if (tab) {
        const normalized = tab.toLowerCase();
        const allowed = ['home','book','appointments','messages','refunds','action-logs','profile','settings','notifications'];
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
  const [slotDetails, setSlotDetails] = useState([]);
  const [slotsLoading, setSlotsLoading] = useState(false);
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
  const [appointmentsSearchQuery, setAppointmentsSearchQuery] = useState('');
  
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
  const [showPreviewModal, setShowPreviewModal] = useState(false);
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
    type: [], // Now an array of service values
    appointment_date: '',
    appointment_time: '',
    notes: '',
    custom_service_type: ''
  });

  const [formErrors, setFormErrors] = useState({});
  const [passwordErrors, setPasswordErrors] = useState({});
  const [profileSuccess, setProfileSuccess] = useState('');
  const [passwordSuccess, setPasswordSuccess] = useState('');

  // Password visibility toggles
  const [showCurrentPassword, setShowCurrentPassword] = useState(false);
  const [showNewPassword, setShowNewPassword] = useState(false);
  const [showConfirmPassword, setShowConfirmPassword] = useState(false);
  const [showProfilePassword, setShowProfilePassword] = useState(false);

  // Delete account state
  const [showDeleteModal, setShowDeleteModal] = useState(false);
  const [deleteConfirmText, setDeleteConfirmText] = useState('');
  const [deleteLoading, setDeleteLoading] = useState(false);
  const [deleteError, setDeleteError] = useState('');

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
        },
        {
          name: 'Notifications',
          href: '#',
          icon: BellIcon,
          current: activeTab === 'notifications',
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
  // Load data on tab change (lazy loading for non-preloaded tabs)
  useEffect(() => {
    if (!user?.id) return; // Wait for user to load
    loadInitialData();
  }, [activeTab, user?.id]); // eslint-disable-line react-hooks/exhaustive-deps

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
      root.style.setProperty('--primary', '#1D3557');
      root.style.setProperty('--secondary', '#3F6FA6');
      root.style.setProperty('--accent', '#C96D02');
      root.style.setProperty('--background', '#ECF1F6');
      root.style.setProperty('--sidebar-bg', '#F4F7FB');
      root.style.setProperty('--surface', '#F8FAFD');
      root.style.setProperty('--text-primary', '#0F172A');
      root.style.setProperty('--text-secondary', '#475569');
      root.style.setProperty('--borders', '#CFDAE6');
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
  // Always checks today's date - counts how many appointments booked today regardless of appointment date
  const checkDailyLimit = useCallback(async () => {
    try {
      // Guard: user must be loaded
      if (!user?.id) {
        console.log('User not loaded yet, skipping daily limit check');
        return;
      }
      
      // Always use today's date (use local time, not UTC)
      const now = new Date();
      const checkDate = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}`;
      
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
          id: service.id,
          public_requirements: service.public_requirements,
          is_unavailable: !!service.is_unavailable,
          unavailability_reason: service.unavailability_reason || null,
          unavailability_category: service.unavailability_category || null,
          unavailable_until: service.unavailable_until || null,
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
      
      try {
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
      } catch (fallbackError) {
        console.error('Fallback appointment types also failed:', fallbackError);
        // Set static types as last resort
        setAppointmentTypes(Object.entries(defaultPrices).map(([value, price]) => ({
          value,
          label: value.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase()),
          price
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
    setSlotsLoading(true);
    
    try {
      const result = await callApi((signal) => 
        axios.get(`/api/calendar/available-slots`, { params: { date }, signal })
      );
      
      if (result.success) {
        setAvailableSlots(result.data.available_slots || []);
        setSlotDetails(result.data.slot_details || []);
      } else {
        setAvailableSlots([]);
        setSlotDetails([]);
      }
    } finally {
      setSlotsLoading(false);
    }
  }, [callApi]);

  // Define loadMessages before it's used
  const loadMessages = useCallback(async () => {
    const result = await callApi((signal) => 
      axios.get('/api/messages/all/messages', { signal })
    , { abortPrevious: false });
    
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
      checkDailyLimit();
    };

    window.addEventListener('appointmentSettingsChanged', handleAppointmentSettingsChanged);
    return () => window.removeEventListener('appointmentSettingsChanged', handleAppointmentSettingsChanged);
  }, [checkDailyLimit]);

  // Track which data sets have been loaded
  const [userDataLoaded, setUserDataLoaded] = useState({
    home: false,
    appointments: false,
    refunds: false,
    messages: false
  });

  const loadInitialData = useCallback(async () => {
    switch (activeTab) {
      case 'home':
      case 'book':
        if (!userDataLoaded.home) {
          // Keep initial load focused on immediately visible data.
          await Promise.all([
            loadAppointmentTypes(),
            loadAppointments(),
            checkDailyLimit()
          ]);
          setUserDataLoaded(prev => ({ ...prev, home: true, appointments: true }));
        }
        break;
      case 'appointments':
        if (!userDataLoaded.appointments) {
          // Load in parallel
          await Promise.all([
            loadAppointments(),
            loadRefunds(),
            checkDailyLimit()
          ]);
          setUserDataLoaded(prev => ({ ...prev, appointments: true, refunds: true }));
        }
        break;
      case 'refunds':
        if (!userDataLoaded.refunds) {
          await loadRefunds();
          setUserDataLoaded(prev => ({ ...prev, refunds: true }));
        }
        break;
      case 'messages':
        if (!userDataLoaded.messages) {
          await loadMessages();
          setUserDataLoaded(prev => ({ ...prev, messages: true }));
        }
        break;
      case 'profile':
        // Profile data is already loaded from auth context
        break;
    }
  }, [activeTab, userDataLoaded, loadAppointmentTypes, loadAppointments, loadRefunds, checkDailyLimit, loadMessages]);

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
      checkDailyLimit();
    },
    // onUnavailableDatesChange callback
    () => {
      console.log('[Dashboard] Unavailable dates changed from polling, dispatching event');
      // The EnhancedCalendar listens for this event and will reload
    },
    // onAppointmentStatusChange callback - admin approved/declined/cancelled user's appointment
    () => {
      console.log('[Dashboard] Appointment status changed from polling, reloading appointments');
      loadAppointments();
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
    // Clear errors when user starts typing
    if (passwordErrors.general || passwordErrors[name]) {
      setPasswordErrors(prev => ({ ...prev, general: '', [name]: '' }));
    }
  };

  // Password strength calculator (matches the one in register)
  const getPasswordStrength = (password) => {
    if (!password) return { level: 0, label: '', color: '' };
    let score = 0;
    if (password.length >= 8) score++;
    if (password.length >= 12) score++;
    if (/[a-z]/.test(password)) score++;
    if (/[A-Z]/.test(password)) score++;
    if (/[0-9]/.test(password)) score++;
    if (/[^a-zA-Z0-9]/.test(password)) score++;

    if (score <= 2) return { level: 1, label: 'Weak', color: 'red' };
    if (score <= 3) return { level: 2, label: 'Fair', color: 'orange' };
    if (score <= 4) return { level: 3, label: 'Good', color: 'yellow' };
    if (score <= 5) return { level: 4, label: 'Strong', color: 'green' };
    return { level: 5, label: 'Very Strong', color: 'emerald' };
  };

  const newPasswordStrength = getPasswordStrength(passwordData.new_password);
  const passwordsMatch = passwordData.new_password_confirmation.length === 0 || passwordData.new_password === passwordData.new_password_confirmation;

  const handleAppointmentChange = (e) => {
    const { name, value } = e.target;
    setAppointmentData(prev => ({
      ...prev,
      [name]: value,
      // Clear selected time when date changes (user must re-pick from grid)
      ...(name === 'appointment_date' ? { appointment_time: '' } : {})
    }));

    // Load available slots and check limit when date changes
    if (name === 'appointment_date') {
      loadAvailableSlots(value);
      checkDailyLimit();
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
      type: Array.isArray(value) ? value : [value]
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
    
    if (!appointmentData.type || (Array.isArray(appointmentData.type) && appointmentData.type.length === 0)) {
      errors.type = 'At least one service type is required';
    }
    if (!appointmentData.appointment_date) errors.appointment_date = 'Date is required';
    if (!appointmentData.appointment_time) errors.appointment_time = 'Time is required';
    if (Array.isArray(appointmentData.type) && appointmentData.type.includes('other') && !appointmentData.custom_service_type) {
      errors.type = 'Please specify the custom service type';
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

    // Client-side validation
    if (passwordData.new_password.length < 8) {
      setPasswordErrors({ new_password: 'Password must be at least 8 characters' });
      return;
    }
    if (passwordData.new_password !== passwordData.new_password_confirmation) {
      setPasswordErrors({ new_password_confirmation: 'Passwords do not match' });
      return;
    }

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
      setTimeout(() => setPasswordSuccess(''), 5000);
    } else {
      // Handle field-specific validation errors from Laravel
      const fieldErrors = result.data?.errors || {};
      const generalMsg = result.error || result.data?.message || 'Failed to update password';
      setPasswordErrors({
        general: Object.keys(fieldErrors).length === 0 ? generalMsg : '',
        current_password: fieldErrors.current_password?.[0] || '',
        new_password: fieldErrors.new_password?.[0] || '',
        new_password_confirmation: fieldErrors.new_password_confirmation?.[0] || '',
      });
    }
  };

  const handleAppointmentSubmit = (e) => {
    if (e) e.preventDefault();
    if (!validateAppointmentForm()) return;
    
    // Bypass the preview modal and book directly as requested
    confirmAppointmentBooking();
  };

  const confirmAppointmentBooking = async () => {
    // Check if user has reached daily limit
    if (dailyLimitInfo.hasReachedLimit) {
      setFormErrors({
        appointment_date: `You have reached your daily booking limit of ${dailyLimitInfo.limit} appointments. ${dailyLimitInfo.message || 'You can book again tomorrow.'}`
      });
      return;
    }

    // Collect all selected services
    const selectedServices = appointmentTypes.filter(t => 
      Array.isArray(appointmentData.type) && appointmentData.type.includes(t.value)
    );
    
    // Extract service IDs (filter out non-Eloquent types if any)
    const serviceIds = selectedServices.map(s => s.id).filter(id => id);
    
    // Calculate total price for confirmation/logging
    const totalPrice = selectedServices.reduce((sum, s) => sum + parseFloat(s.price || 0), 0);
    
    // Debug log for service matching
    console.log('[Booking] Multi-service lookup:', { 
      types: appointmentData.type, 
      foundServices: selectedServices.length, 
      serviceIds,
      totalPrice,
      availableTypes: appointmentTypes.length 
    });
    
    // Construct friendly service labels description
    let serviceLabels = selectedServices.map(s => s.label).join(', ');
    if (Array.isArray(appointmentData.type) && appointmentData.type.includes('other')) {
      const otherLabel = appointmentData.custom_service_type || 'Other';
      serviceLabels = serviceLabels ? `${serviceLabels}, ${otherLabel}` : otherLabel;
    }

    const submitData = {
      service_ids: serviceIds,
      type: Array.isArray(appointmentData.type) ? appointmentData.type[0] : appointmentData.type, // Fallback for legacy
      appointment_date: appointmentData.appointment_date,
      appointment_time: appointmentData.appointment_time,
      service_type: serviceLabels || 'General Service'
    };

    const result = await callApi((signal) => 
      axios.post('/api/appointments', submitData, { signal })
    );

    if (result.success) {
      setLatestAppointment(result.data.appointment);
      setShowThankYouModal(true);
      
      // Reset form
      setAppointmentData({
        type: [],
        appointment_date: '',
        appointment_time: '',
        notes: '',
        custom_service_type: ''
      });
      setAvailableSlots([]);
      setSlotDetails([]);
      setFormErrors({});
      
      // Reload appointments and check daily limit
      await loadAppointments();
      await checkDailyLimit();
      setShowPreviewModal(false);
    } else {
      setShowPreviewModal(false);
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
      window.showToast?.('Warning', 'Please select a reason for the refund', 'warning');
      return;
    }

    // Validate payment amount exists
    if (!selectedAppointment.payment_amount || selectedAppointment.payment_amount <= 0) {
      window.showToast?.('Error', 'Cannot process refund: This appointment has no payment amount recorded. Please contact support for assistance.', 'error');
      return;
    }

    // Validate payment status
    if (selectedAppointment.payment_status !== 'paid') {
      window.showToast?.('Error', 'Cannot process refund: This appointment is not marked as paid. Only paid appointments can be refunded.', 'error');
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
        window.showToast?.('Success', 'Refund request submitted successfully. You will receive a notification once it is reviewed.', 'success');
        setShowRefundModal(false);
        setSelectedAppointment(null);
        setRefundData({ reason: 'customer_request', description: '' });
        // Refresh appointments and refunds
        loadAppointments();
        loadRefunds();
      }
    } catch (error) {
      console.error('Refund request failed:', error);
      window.showToast?.('Error', error.response?.data?.message || 'Failed to submit refund request', 'error');
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
      // Stop realtime polling BEFORE logout to prevent 401 floods
      stopRealtimePolling();
      await logout();
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
        iconColor: isDarkMode ? 'text-purple-400' : 'text-purple-600'
      },
      {
        name: 'Pending',
        value: appointments.filter(apt => apt.status === 'pending').length.toString(),
        icon: ClockIcon,
        iconColor: isDarkMode ? 'text-amber-400' : 'text-amber-600'
      },
      {
        name: 'Completed',
        value: appointments.filter(apt => apt.status === 'completed').length.toString(),
        icon: CheckCircleIcon,
        iconColor: isDarkMode ? 'text-green-400' : 'text-green-600'
      }
    ];

    return (
      <div className="grid grid-cols-3 gap-3 sm:gap-6 mb-4 sm:mb-6">
        {stats.map((stat, index) => (
          <div
            key={index}
            className={`${isDarkMode ? 'bg-gray-900 border-amber-500/20 hover:shadow-amber-500/10' : 'bg-white border-amber-300/40 hover:shadow-amber-300/10'} border rounded-lg shadow p-2.5 sm:p-4 hover:border-amber-500/40 transition-all duration-300 cursor-pointer group transform hover:-translate-y-1`}
          >
            <div className="flex flex-col sm:flex-row items-center sm:justify-between gap-1 sm:gap-0">
              <div className="text-center sm:text-left order-2 sm:order-1">
                <p className={`text-[10px] sm:text-xs font-medium ${isDarkMode ? 'text-gray-400 group-hover:text-amber-300' : 'text-gray-500 group-hover:text-amber-600'} transition-colors`}>
                  {stat.name}
                </p>
                <p className={`text-base sm:text-lg font-bold ${isDarkMode ? 'text-amber-50' : 'text-gray-900'} mt-0.5 group-hover:scale-105 transition-transform`}>
                  {stat.value}
                </p>
              </div>
              <div className="order-1 sm:order-2 transition-transform group-hover:scale-110">
                <stat.icon className={`h-6 w-6 sm:h-8 sm:w-8 ${stat.iconColor} drop-shadow-sm`} />
              </div>
            </div>
          </div>
        ))}
      </div>
    );
  };

  const renderHome = () => (
    <div className="space-y-5 sm:space-y-8 pb-10">
      <ProfileCompletionBanner isDarkMode={isDarkMode} />
      
      {/* Welcome Section */}
      <div className={`${isDarkMode ? 'bg-gray-900 border-amber-500/20' : 'bg-white border-amber-300/40'} border rounded-xl shadow-lg p-5 sm:p-8 hover:border-amber-500/40 transition-all duration-300 relative overflow-hidden group`}>
        <div className="absolute top-0 right-0 w-32 h-32 bg-amber-500/5 rounded-full -mr-16 -mt-16 group-hover:bg-amber-500/10 transition-all duration-500" />
        <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 sm:gap-6 relative z-10">
          <div className="flex-1">
            <h2 className={`text-base sm:text-2xl font-bold ${isDarkMode ? 'text-amber-50' : 'text-gray-900'}`}>Welcome back, {user?.first_name}! 👋</h2>
            <p className={`mt-1 sm:mt-2 text-xs sm:text-base ${isDarkMode ? 'text-amber-400/80' : 'text-gray-600'}`}>Ready to schedule your next notarization service?</p>
          </div>
          <button
            onClick={() => setActiveTab('book')}
            className="w-full sm:w-auto px-5 sm:px-8 py-2.5 sm:py-3.5 bg-gradient-to-r from-amber-600 to-amber-700 text-white rounded-xl hover:from-amber-700 hover:to-amber-800 transition-all duration-200 font-bold text-sm sm:text-base flex items-center justify-center shadow-amber-500/20 shadow-xl transform hover:-translate-y-1 active:scale-95 border border-amber-500/30 whitespace-nowrap"
          >
            <PlusIcon className="h-5 w-5 mr-2" />
            Book Appointment
          </button>
        </div>
      </div>

      {/* Stats Cards */}
      <StatsCards />

      {/* Recent Appointments Preview */}
      <div className="grid grid-cols-1 gap-2 sm:gap-4">
        <div className={`${isDarkMode ? 'bg-gray-900 border-amber-500/20' : 'bg-white border-amber-300/40'} border rounded-lg shadow p-3 sm:p-4 hover:border-amber-500/40 transition-all duration-300`}>
          <div className="flex items-center justify-between mb-2 sm:mb-3">
            <h3 className={`text-xs sm:text-sm font-semibold ${isDarkMode ? 'text-amber-50' : 'text-amber-900'} flex items-center`}>
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
                        {formatDateDisplay(appointment.appointment_date)} at {appointment.appointment_time}
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

      {/* Insights & Analytics */}
      <UserInsights appointments={appointments} isDarkMode={isDarkMode} />
    </div>
  );

  const renderBookAppointment = () => {
    const hasService = Array.isArray(appointmentData.type) && appointmentData.type.length > 0;
    const hasDate = !!appointmentData.appointment_date;
    const hasTime = !!appointmentData.appointment_time;

    // Determine current step for visual indicator
    const currentStep = !hasService ? 1 : !hasDate ? 2 : !hasTime ? 3 : 4;

    return (
    <div className="space-y-6">
      <div className="hidden lg:flex justify-between items-center">
        <div>
          <h2 className={`text-lg font-bold ${isDarkMode ? 'text-amber-50' : 'text-gray-900'}`}>Book New Appointment</h2>
          <p className={`text-amber-400/70 mt-1 text-sm ${isDarkMode ? '' : 'text-gray-600'}`}>Schedule your document notarization service</p>
        </div>

      </div>

      {/* Step Progress Indicator */}
      <div className="flex items-center gap-2">
        {[
          { num: 1, label: 'Service' },
          { num: 2, label: 'Date' },
          { num: 3, label: 'Time' },
          { num: 4, label: 'Confirm' },
        ].map((step, idx) => (
          <React.Fragment key={step.num}>
            {idx > 0 && (
              <div className={`flex-1 h-0.5 rounded ${currentStep > step.num - 1 ? 'bg-amber-500' : 'bg-gray-700'}`} />
            )}
            <div className={`flex items-center gap-1.5 text-xs font-medium ${
              currentStep >= step.num ? 'text-amber-400' : 'text-gray-500'
            }`}>
              <div className={`w-6 h-6 rounded-full flex items-center justify-center text-[10px] font-bold border ${
                currentStep > step.num
                  ? 'bg-amber-500 border-amber-500 text-white'
                  : currentStep === step.num
                    ? 'border-amber-500 text-amber-400'
                    : 'border-gray-600 text-gray-500'
              }`}>
                {currentStep > step.num ? '✓' : step.num}
              </div>
              <span className="hidden sm:inline">{step.label}</span>
            </div>
          </React.Fragment>
        ))}
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
                <h3 className="font-semibold text-red-400">Daily Booking Limit Reached</h3>
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
        <form onSubmit={handleAppointmentSubmit} className="space-y-5">
          {/* Display general form errors */}
          {formErrors.general && (
            <div className="rounded-lg border border-red-500/30 bg-red-900/20 p-4 flex items-start gap-3">
              <ExclamationTriangleIcon className="h-5 w-5 flex-shrink-0 text-red-400 mt-0.5" />
              <p className="text-sm text-red-300/90">{formErrors.general}</p>
            </div>
          )}

          {/* Step 1: Service Type */}
          <div>
            <ServiceTypeDropdown
              value={appointmentData.type}
              onChange={handleServiceTypeChange}
              options={appointmentTypes}
              error={formErrors.type}
              onOtherChange={handleCustomServiceChange}
              otherValue={appointmentData.custom_service_type}
              disabled={dailyLimitInfo.hasReachedLimit}
              isDarkMode={isDarkMode}
              onViewRequirements={setShowRequirementsModalFor}
            />
            {/* Service Requirements link */}
            {(() => {
              const selectedServices = appointmentTypes.filter(t =>
                Array.isArray(appointmentData.type) && appointmentData.type.includes(t.value)
              );
              const hasRequirements = selectedServices.some(s => s.public_requirements && s.public_requirements.length > 0);
              if (hasRequirements && !dailyLimitInfo.hasReachedLimit) {
                return (
                  <div className="flex justify-start mt-1.5">
                    <button
                      type="button"
                      onClick={() => setShowRequirementsModalFor(selectedServices)}
                      className={`text-[11px] flex items-center gap-1.5 hover:underline transition-colors ${
                        isDarkMode ? 'text-amber-400/90 hover:text-amber-300' : 'text-amber-600 hover:text-amber-700'
                      }`}
                    >
                      <InformationCircleIcon className="w-3.5 h-3.5" />
                      View setup requirements
                    </button>
                  </div>
                );
              }
              return null;
            })()}
          </div>

          {/* Step 2: Date Selection */}
          <div>
            <EnhancedCalendar
              value={appointmentData.appointment_date}
              onChange={(value) => handleAppointmentChange({ target: { name: 'appointment_date', value } })}
              error={formErrors.appointment_date}
              disabled={dailyLimitInfo.hasReachedLimit}
              dailyLimitInfo={dailyLimitInfo}
              isDarkMode={isDarkMode}
            />
          </div>

          {/* Step 3: Time Slot Grid (only after date selected) */}
          {hasDate && (
            <div>
              <label className={`block text-xs font-medium mb-2 ${isDarkMode ? 'text-amber-50' : 'text-gray-700'}`}>
                Select Time <span className="text-red-500">*</span>
              </label>

              {slotsLoading ? (
                <div className="flex items-center justify-center py-8">
                  <div className="w-5 h-5 border-2 border-amber-500 border-t-transparent rounded-full animate-spin mr-2" />
                  <span className="text-sm text-gray-400">Loading available slots...</span>
                </div>
              ) : slotDetails.length === 0 ? (
                <div className="text-center py-6 rounded-lg border border-gray-700 bg-gray-800/50">
                  <ExclamationTriangleIcon className="h-8 w-8 text-gray-500 mx-auto mb-2" />
                  <p className="text-sm text-gray-400">No time slots available for this date.</p>
                  <p className="text-xs text-gray-500 mt-1">Try selecting a different date.</p>
                </div>
              ) : (
                <div className="grid grid-cols-3 sm:grid-cols-4 lg:grid-cols-5 gap-2">
                  {slotDetails.map((slot) => {
                    const isFull = slot.status === 'full';
                    const isPartial = slot.status === 'partial';
                    const isSelected = appointmentData.appointment_time === slot.time;
                    const displayTime = new Date(`2000-01-01T${slot.time}`).toLocaleTimeString('en-US', {
                      hour: 'numeric',
                      minute: '2-digit',
                      hour12: true,
                    });

                    return (
                      <button
                        key={slot.time}
                        type="button"
                        disabled={isFull || dailyLimitInfo.hasReachedLimit}
                        onClick={() => {
                          setAppointmentData(prev => ({ ...prev, appointment_time: slot.time }));
                          if (formErrors.appointment_time) {
                            setFormErrors(prev => ({ ...prev, appointment_time: '' }));
                          }
                        }}
                        className={`relative px-2 py-3 rounded-lg border text-center transition-all text-sm ${
                          isFull
                            ? 'border-red-500/30 bg-red-900/20 text-red-400/60 cursor-not-allowed opacity-60'
                            : isSelected
                              ? 'border-amber-500 bg-amber-500/20 text-amber-300 ring-2 ring-amber-500/50'
                              : isPartial
                                ? 'border-amber-500/30 bg-amber-900/10 text-amber-200 hover:border-amber-500/60 hover:bg-amber-500/15 cursor-pointer'
                                : 'border-gray-600 bg-gray-800 text-gray-200 hover:border-amber-500/50 hover:bg-gray-700 cursor-pointer'
                        }`}
                      >
                        <span className="font-medium block">{displayTime}</span>
                        <span className={`text-[10px] block mt-0.5 ${
                          isFull ? 'text-red-400' : isPartial ? 'text-amber-400/70' : 'text-green-400/70'
                        }`}>
                          {isFull ? 'FULL' : `${slot.booked}/${slot.capacity} booked`}
                        </span>
                        {isSelected && (
                          <div className="absolute -top-1 -right-1 w-4 h-4 bg-amber-500 rounded-full flex items-center justify-center">
                            <svg className="w-2.5 h-2.5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth="4">
                              <path strokeLinecap="round" strokeLinejoin="round" d="M5 13l4 4L19 7" />
                            </svg>
                          </div>
                        )}
                      </button>
                    );
                  })}
                </div>
              )}
              {formErrors.appointment_time && (
                <p className="text-red-400 text-xs mt-1.5 flex items-center gap-1.5 px-1">
                  <ExclamationTriangleIcon className="h-3.5 w-3.5 flex-shrink-0" />
                  {formErrors.appointment_time}
                </p>
              )}
            </div>
          )}

          {/* Step 4: Booking Summary (only after time selected) */}
          {hasTime && (
            <>
              {/* Booking Summary Card */}
              <div className="bg-gradient-to-r from-amber-500/10 to-amber-500/5 border border-amber-500/30 rounded-lg p-4">
                <div className="space-y-4">
                  {/* Service Highlight */}
                  <div className="bg-amber-500/10 border border-amber-500/20 rounded-lg p-3">
                    <p className="text-[10px] text-amber-400 font-bold uppercase tracking-wider mb-1">Selected Service</p>
                    <p className="text-base sm:text-lg text-amber-50 font-bold leading-tight">
                      {(() => {
                        const selectedServices = appointmentTypes.filter(t =>
                          Array.isArray(appointmentData.type) && appointmentData.type.includes(t.value)
                        );
                        let labels = selectedServices.map(s => s.label).join(', ');
                        if (Array.isArray(appointmentData.type) && appointmentData.type.includes('other')) {
                          const otherLabel = appointmentData.custom_service_type || 'Other';
                          labels = labels ? `${labels}, ${otherLabel}` : otherLabel;
                        }
                        return labels || 'General Service';
                      })()}
                    </p>
                  </div>

                  <div className="grid grid-cols-2 gap-3">
                    <div className="bg-gray-800/40 p-2.5 rounded-lg border border-gray-700/50">
                      <p className="text-[10px] text-gray-400 font-medium uppercase mb-0.5">Date</p>
                      <p className="text-sm text-amber-50 font-medium">
                        {new Date(appointmentData.appointment_date + 'T00:00:00').toLocaleDateString('en-US', {
                          weekday: 'short',
                          month: 'short',
                          day: 'numeric',
                          year: 'numeric',
                        })}
                      </p>
                    </div>
                    <div className="bg-gray-800/40 p-2.5 rounded-lg border border-gray-700/50">
                      <p className="text-[10px] text-gray-400 font-medium uppercase mb-0.5">Time</p>
                      <p className="text-sm text-amber-50 font-medium">
                        {new Date(`2000-01-01T${appointmentData.appointment_time}`).toLocaleTimeString('en-US', {
                          hour: 'numeric',
                          minute: '2-digit',
                          hour12: true,
                        })}
                      </p>
                    </div>
                  </div>
                </div>
                {(() => {
                  const selectedServices = appointmentTypes.filter(t =>
                    Array.isArray(appointmentData.type) && appointmentData.type.includes(t.value)
                  );
                  const totalPrice = selectedServices.reduce((sum, s) => sum + parseFloat(s.price || 0), 0);
                  if (selectedServices.length > 0) {
                    return (
                      <div className="mt-3 pt-3 border-t border-amber-500/20 space-y-1.5">
                        {selectedServices.map(s => (
                          <div key={s.value} className="flex justify-between items-center">
                            <span className="text-xs text-amber-50/80">{s.label}</span>
                            <span className="text-xs font-mono text-amber-400/70">₱{parseFloat(s.price || 0).toLocaleString(undefined, { minimumFractionDigits: 2 })}</span>
                          </div>
                        ))}
                        {selectedServices.length > 1 && (
                          <div className="flex justify-between items-center pt-1.5 border-t border-amber-500/20">
                            <span className="text-xs text-amber-400/70 font-semibold">Total</span>
                            <span className="text-amber-400 font-semibold">₱{totalPrice.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</span>
                          </div>
                        )}
                      </div>
                    );
                  }
                  return null;
                })()}
              </div>
            </>
          )}

          {/* Submit Section */}
          <div className="flex justify-end pt-4 border-t border-gray-700">
            <button
              type="submit"
              disabled={loading || dailyLimitInfo.hasReachedLimit || !hasService || !hasDate || !hasTime}
              className="px-6 py-2 bg-gradient-to-r from-amber-600 to-amber-700 text-white rounded-lg hover:from-amber-700 hover:to-amber-800 focus:outline-none focus:ring-2 focus:ring-amber-500 focus:ring-offset-2 focus:ring-offset-gray-900 transition-all duration-200 font-medium text-sm shadow border border-amber-500/30 disabled:opacity-50 disabled:cursor-not-allowed flex items-center transform hover:-translate-y-0.5"
            >
              {loading ? (
                <>
                  <div className="w-3 h-3 border-2 border-white border-t-transparent rounded-full mr-2 animate-spin"></div>
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

      {/* Service Requirements Modal */}
      <ServiceRequirementsModal 
        isOpen={!!showRequirementsModalFor} 
        onClose={() => setShowRequirementsModalFor(null)} 
        services={showRequirementsModalFor ? (Array.isArray(showRequirementsModalFor) ? showRequirementsModalFor : [showRequirementsModalFor]) : []} 
        isDarkMode={isDarkMode} 
      />
    </div>
    );
  };

  const renderAppointments = () => {
    const statusTabs = [
      { key: 'all', label: 'All' },
      { key: 'pending', label: 'Pending' },
      { key: 'approved', label: 'Approved' },
      { key: 'completed', label: 'Completed' },
      { key: 'cancelled', label: 'Cancelled' },
      { key: 'declined', label: 'Declined' },
    ];

    const getStatusBorderColor = (status) => {
      switch (status) {
        case 'pending': return 'border-l-amber-400';
        case 'approved': return 'border-l-blue-400';
        case 'completed': return 'border-l-emerald-400';
        case 'cancelled': return 'border-l-red-400';
        case 'declined': return 'border-l-red-400';
        default: return 'border-l-gray-400';
      }
    };

    // Filter by status
    let filteredAppointments = appointments;
    if (appointmentsStatusFilter !== 'all') {
      filteredAppointments = appointments.filter(apt => apt.status === appointmentsStatusFilter);
    }

    // Filter by search query
    if (appointmentsSearchQuery.trim()) {
      const q = appointmentsSearchQuery.toLowerCase();
      filteredAppointments = filteredAppointments.filter(apt => {
        const serviceName = formatServiceName(apt).toLowerCase();
        const staffName = apt.staff ? `${apt.staff.first_name} ${apt.staff.last_name}`.toLowerCase() : '';
        const date = apt.appointment_date || '';
        return serviceName.includes(q) || staffName.includes(q) || date.includes(q);
      });
    }

    // Calculate pagination
    const totalAppointments = filteredAppointments.length;
    const totalPages = Math.ceil(totalAppointments / appointmentsPagination.itemsPerPage);
    const startIndex = (appointmentsPagination.currentPage - 1) * appointmentsPagination.itemsPerPage;
    const endIndex = startIndex + appointmentsPagination.itemsPerPage;
    const paginatedAppointments = filteredAppointments.slice(startIndex, endIndex);

    const handlePreviousPage = () => {
      if (appointmentsPagination.currentPage > 1) {
        setAppointmentsPagination(prev => ({ ...prev, currentPage: prev.currentPage - 1 }));
      }
    };

    const handleNextPage = () => {
      if (appointmentsPagination.currentPage < totalPages) {
        setAppointmentsPagination(prev => ({ ...prev, currentPage: prev.currentPage + 1 }));
      }
    };

    const handlePageChange = (page) => {
      setAppointmentsPagination(prev => ({ ...prev, currentPage: page }));
    };

    const handleFilterChange = (key) => {
      setAppointmentsStatusFilter(key);
      setAppointmentsPagination(prev => ({ ...prev, currentPage: 1 }));
    };

    return (
      <div className="space-y-4">
        {/* Header */}
        <div className="hidden lg:flex flex-col sm:flex-row sm:justify-between sm:items-center gap-4">
          <div>
            <h2 className={`text-lg font-bold ${isDarkMode ? 'text-amber-50' : 'text-gray-900'}`}>My Appointments</h2>
              <p className={`mt-1 text-sm ${isDarkMode ? 'text-amber-400/70' : 'text-gray-600'}`}>View and manage your notarization appointments</p>
          </div>
          <button
            onClick={() => setActiveTab('book')}
            className="px-3 py-1.5 bg-gradient-to-r from-amber-600 to-amber-700 text-white rounded hover:from-amber-700 hover:to-amber-800 font-medium text-sm flex items-center shadow border border-amber-500/30"
          >
            <PlusIcon className="h-3.5 w-3.5 mr-1.5" />
            New Appointment
          </button>
        </div>

        {/* Search + Filter */}
        <div className="flex flex-col sm:flex-row sm:items-center gap-3">
          <div className="relative flex-1 max-w-xs">
            <MagnifyingGlassIcon className={`absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 ${isDarkMode ? 'text-gray-500' : 'text-gray-400'}`} />
            <input
              type="text"
              placeholder="Search appointments..."
              value={appointmentsSearchQuery}
              onChange={(e) => {
                setAppointmentsSearchQuery(e.target.value);
                setAppointmentsPagination(prev => ({ ...prev, currentPage: 1 }));
              }}
              className={`w-full pl-9 pr-3 py-2 rounded-lg border text-sm focus:outline-none focus:ring-1 focus:ring-amber-500 ${isDarkMode ? 'bg-gray-800 border-gray-700 text-amber-50 placeholder-gray-500' : 'bg-white border-gray-200 text-gray-900 placeholder-gray-400'}`}
            />
          </div>
          <select
            value={appointmentsStatusFilter}
            onChange={(e) => handleFilterChange(e.target.value)}
            className={`px-3 py-2 rounded-lg border text-sm focus:outline-none focus:ring-1 focus:ring-amber-500 ${isDarkMode ? 'bg-gray-800 border-gray-700 text-amber-50' : 'bg-white border-gray-200 text-gray-900'}`}
          >
            {statusTabs.map(tab => (
              <option key={tab.key} value={tab.key}>{tab.label}</option>
            ))}
          </select>
        </div>

        {/* Appointments List */}
        <div className={`${isDarkMode ? 'bg-gray-900 border-gray-800' : 'bg-white border-gray-200'} border rounded-lg overflow-hidden`}>
          {appointments.length === 0 ? (
            <div className="text-center py-12">
              <CalendarIcon className={`mx-auto h-12 w-12 ${isDarkMode ? 'text-gray-600' : 'text-gray-400'}`} />
              <h3 className={`mt-4 text-sm font-medium ${isDarkMode ? 'text-amber-50' : 'text-gray-900'}`}>No appointments yet</h3>
              <p className={`mt-2 text-xs ${isDarkMode ? 'text-gray-500' : 'text-gray-500'}`}>Schedule your first notarization appointment to get started</p>
              <button
                onClick={() => setActiveTab('book')}
                className="mt-4 px-4 py-2 bg-gradient-to-r from-amber-600 to-amber-700 text-white rounded-lg hover:from-amber-700 hover:to-amber-800 font-medium text-sm shadow border border-amber-500/30"
              >
                Book Appointment
              </button>
            </div>
          ) : paginatedAppointments.length === 0 ? (
            <div className="text-center py-12">
              <CalendarIcon className={`mx-auto h-12 w-12 ${isDarkMode ? 'text-gray-600' : 'text-gray-400'}`} />
              <h3 className={`mt-4 text-sm font-medium ${isDarkMode ? 'text-amber-50' : 'text-gray-900'}`}>No {appointmentsStatusFilter === 'all' ? '' : appointmentsStatusFilter} appointments</h3>
              <p className={`mt-2 text-xs ${isDarkMode ? 'text-gray-500' : 'text-gray-500'}`}>Try a different filter or search term</p>
            </div>
          ) : (
            <>
              <div className="space-y-2 p-3">
                {paginatedAppointments.map((appointment) => {
                  const services = appointment.services && appointment.services.length > 0 ? appointment.services : (appointment.service ? [appointment.service] : []);
                  const serviceCount = services.length;
                  const firstName = services[0]?.name || formatServiceName(appointment);
                  const mobileServiceLabel = serviceCount > 1 ? `${services[0]?.name} & ${serviceCount - 1} more` : firstName;

                  return (
                    <div
                      key={appointment.id}
                      className={`rounded-lg border ${isDarkMode ? 'bg-gray-800/50 border-gray-700/50' : 'bg-gray-50 border-gray-200'} p-4`}
                    >
                      <div className="flex items-start justify-between gap-3">
                        <div className="min-w-0 flex-1">
                          <div className="flex flex-col sm:flex-row sm:items-center gap-1.5 sm:gap-2">
                            <h3 className={`text-sm font-semibold truncate ${isDarkMode ? 'text-amber-50' : 'text-gray-900'}`}>
                              <span className="sm:hidden">{mobileServiceLabel}</span>
                              <span className="hidden sm:inline">{formatServiceName(appointment)}</span>
                            </h3>
                            <StatusBadge status={appointment.status} />
                          </div>
                          <div className={`flex flex-wrap items-center gap-x-4 gap-y-1 mt-2 text-xs ${isDarkMode ? 'text-gray-400' : 'text-gray-500'}`}>
                            <span className="flex items-center gap-1">
                              <CalendarIcon className="h-3.5 w-3.5" />
                              {formatDateDisplay(appointment.appointment_date)}
                            </span>
                            <span className="flex items-center gap-1">
                              <ClockIcon className="h-3.5 w-3.5" />
                              {formatTime12Hour(appointment.appointment_time)}
                            </span>
                            {appointment.staff && (
                              <span className="flex items-center gap-1">
                                <UserIcon className="h-3.5 w-3.5" />
                                {appointment.staff.first_name} {appointment.staff.last_name}
                              </span>
                            )}
                          </div>
                        </div>
                        <div className="flex items-center gap-1.5 flex-shrink-0">
                          <button
                            onClick={() => handleViewAppointmentDetails(appointment)}
                            className={`p-1.5 rounded-lg border ${isDarkMode ? 'text-amber-400 hover:text-amber-300 border-amber-500/30 hover:bg-amber-500/10' : 'text-amber-600 hover:text-amber-700 border-amber-300 hover:bg-amber-50'}`}
                            title="View details"
                          >
                            <EyeIcon className="h-4 w-4" />
                          </button>
                          {appointment.status === 'pending' && (
                            <button
                              onClick={() => handleRequestCancellation(appointment)}
                              className={`p-1.5 rounded-lg border ${isDarkMode ? 'text-red-400 hover:text-red-300 border-red-500/30 hover:bg-red-500/10' : 'text-red-500 hover:text-red-600 border-red-300 hover:bg-red-50'}`}
                              title="Cancel appointment"
                            >
                              <TrashIcon className="h-4 w-4" />
                            </button>
                          )}
                          {appointment.status === 'completed' && appointment.payment_status === 'paid' && appointment.payment_amount > 0 && (
                            <button
                              onClick={() => {
                                setSelectedAppointment(appointment);
                                setShowRefundModal(true);
                              }}
                              className={`p-1.5 rounded-lg border ${isDarkMode ? 'text-green-400 hover:text-green-300 border-green-500/30 hover:bg-green-500/10' : 'text-green-500 hover:text-green-600 border-green-300 hover:bg-green-50'}`}
                              title="Request refund"
                            >
                              <CurrencyDollarIcon className="h-4 w-4" />
                            </button>
                          )}
                        </div>
                      </div>
                    </div>
                  );
                })}
              </div>

              {/* Pagination */}
              {totalPages > 1 && (
                <div className={`px-4 py-3 border-t flex items-center justify-between ${isDarkMode ? 'border-gray-700 bg-gray-800/50' : 'border-gray-200 bg-gray-50'}`}>
                  <span className={`text-xs ${isDarkMode ? 'text-gray-500' : 'text-gray-500'}`}>
                    {startIndex + 1}–{Math.min(endIndex, totalAppointments)} of {totalAppointments}
                  </span>
                  <div className="flex items-center gap-1">
                    <button
                      onClick={handlePreviousPage}
                      disabled={appointmentsPagination.currentPage === 1}
                      className={`px-2 py-1 rounded text-xs font-medium border ${
                        appointmentsPagination.currentPage === 1
                          ? isDarkMode ? 'border-gray-700 text-gray-600 cursor-not-allowed' : 'border-gray-200 text-gray-300 cursor-not-allowed'
                          : isDarkMode ? 'border-gray-600 text-gray-300 hover:bg-gray-700' : 'border-gray-300 text-gray-600 hover:bg-gray-100'
                      }`}
                    >
                      Prev
                    </button>
                    {Array.from({ length: totalPages }, (_, i) => i + 1).map(page => (
                      <button
                        key={page}
                        onClick={() => handlePageChange(page)}
                        className={`w-7 h-7 rounded text-xs font-medium border ${
                          appointmentsPagination.currentPage === page
                            ? isDarkMode ? 'bg-amber-500/20 border-amber-500/50 text-amber-300' : 'bg-amber-100 border-amber-400 text-amber-800'
                            : isDarkMode ? 'border-gray-700 text-gray-400 hover:border-gray-600' : 'border-gray-200 text-gray-500 hover:border-gray-300'
                        }`}
                      >
                        {page}
                      </button>
                    ))}
                    <button
                      onClick={handleNextPage}
                      disabled={appointmentsPagination.currentPage === totalPages}
                      className={`px-2 py-1 rounded text-xs font-medium border ${
                        appointmentsPagination.currentPage === totalPages
                          ? isDarkMode ? 'border-gray-700 text-gray-600 cursor-not-allowed' : 'border-gray-200 text-gray-300 cursor-not-allowed'
                          : isDarkMode ? 'border-gray-600 text-gray-300 hover:bg-gray-700' : 'border-gray-300 text-gray-600 hover:bg-gray-100'
                      }`}
                    >
                      Next
                    </button>
                  </div>
                </div>
              )}
            </>
          )}
        </div>
      </div>
    );
  };

  const renderMessages = () => {
    return (
      <div className="flex flex-col h-full" style={{ minHeight: '500px' }}>
        <MessageCenter isDarkMode={isDarkMode} hideMobileHeader />
      </div>
    );
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
            onClick={() => navigate('/dashboard?tab=profile')}
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
          onClick={() => navigate('/dashboard?tab=home')}
          className="p-2 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-800 transition-colors"
          title="Back"
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
        <div className="flex items-center space-x-4 mb-6">
          {/* Profile Avatar */}
          <div className="relative w-24 h-24 rounded-full overflow-hidden border-4 border-amber-400 bg-gradient-to-br from-amber-400 to-amber-600 flex items-center justify-center flex-shrink-0">
            <span className="text-white text-4xl font-bold">
              {(user?.first_name?.[0] || '').toUpperCase()}{(user?.last_name?.[0] || '').toUpperCase() || 'U'}
            </span>
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
                    type={showProfilePassword ? 'text' : 'password'}
                    name="password"
                    value={profileData.password}
                    onChange={handleProfileChange}
                    disabled={!isEditing}
                    placeholder="Leave blank to keep current password"
                    className={`w-full pl-9 pr-9 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-500 focus:border-amber-500 transition-all duration-200 text-sm ${isDarkMode ? 'bg-gray-800 border-gray-600 text-white disabled:bg-gray-800/50 disabled:text-gray-400 placeholder-gray-400' : 'bg-white border-gray-300 text-gray-900 disabled:bg-gray-100 disabled:text-gray-500 placeholder-gray-400'} disabled:cursor-not-allowed`}
                  />
                  <button
                    type="button"
                    onClick={() => setShowProfilePassword(!showProfilePassword)}
                    className="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-400 hover:text-amber-400 transition-colors"
                    tabIndex={-1}
                  >
                    {showProfilePassword ? <EyeSlashIcon className="h-4 w-4" /> : <EyeIcon className="h-4 w-4" />}
                  </button>
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
          <div className="flex items-center justify-between mb-4">
            <h4 className={`text-sm font-semibold ${isDarkMode ? 'text-amber-50' : 'text-amber-900'} flex items-center`}>
              <KeyIcon className="h-4 w-4 mr-2" />
              Change Password
            </h4>
            <button
              onClick={() => setIsEditing(!isEditing)}
              className={`px-3 py-1.5 border rounded text-xs font-medium flex items-center gap-1 whitespace-nowrap ${isDarkMode ? 'border-amber-500/30 text-amber-50 hover:bg-amber-500/10' : 'border-amber-500/30 text-amber-900 hover:bg-amber-100'}`}
            >
              <PencilIcon className="h-3 w-3" />
              {isEditing ? 'Cancel Edit' : 'Edit Profile'}
            </button>
          </div>
          
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
                <div className="relative">
                  <input
                    type={showCurrentPassword ? 'text' : 'password'}
                    name="current_password"
                    value={passwordData.current_password}
                    onChange={handlePasswordChange}
                    className={`w-full px-3 pr-9 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-500 focus:border-amber-500 transition-all duration-200 text-sm ${
                      passwordErrors.current_password
                        ? isDarkMode ? 'bg-gray-800 border-red-500 text-white' : 'bg-white border-red-400 text-gray-900'
                        : isDarkMode ? 'bg-gray-800 border-gray-600 text-white' : 'bg-white border-gray-300 text-gray-900'
                    }`}
                    required
                  />
                  <button
                    type="button"
                    onClick={() => setShowCurrentPassword(!showCurrentPassword)}
                    className="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-400 hover:text-amber-400 transition-colors"
                    tabIndex={-1}
                  >
                    {showCurrentPassword ? <EyeSlashIcon className="h-4 w-4" /> : <EyeIcon className="h-4 w-4" />}
                  </button>
                </div>
                {passwordErrors.current_password && (
                  <p className="text-xs mt-1 text-red-400">{passwordErrors.current_password}</p>
                )}
              </div>

              <div>
                <label className={`block text-xs font-medium ${isDarkMode ? 'text-amber-50' : 'text-amber-900'} mb-1`}>
                  New Password *
                </label>
                <div className="relative">
                  <input
                    type={showNewPassword ? 'text' : 'password'}
                    name="new_password"
                    value={passwordData.new_password}
                    onChange={handlePasswordChange}
                    className={`w-full px-3 pr-9 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-500 focus:border-amber-500 transition-all duration-200 text-sm ${
                      passwordErrors.new_password
                        ? isDarkMode ? 'bg-gray-800 border-red-500 text-white' : 'bg-white border-red-400 text-gray-900'
                        : isDarkMode ? 'bg-gray-800 border-gray-600 text-white' : 'bg-white border-gray-300 text-gray-900'
                    }`}
                    required
                    minLength="8"
                  />
                  <button
                    type="button"
                    onClick={() => setShowNewPassword(!showNewPassword)}
                    className="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-400 hover:text-amber-400 transition-colors"
                    tabIndex={-1}
                  >
                    {showNewPassword ? <EyeSlashIcon className="h-4 w-4" /> : <EyeIcon className="h-4 w-4" />}
                  </button>
                </div>
                {passwordErrors.new_password && (
                  <p className="text-xs mt-1 text-red-400">{passwordErrors.new_password}</p>
                )}
                {/* Password Strength Indicator */}
                {passwordData.new_password.length > 0 && (
                  <div className="mt-1.5 space-y-1">
                    <div className="flex gap-1">
                      {[1, 2, 3, 4, 5].map((i) => (
                        <div
                          key={i}
                          className={`h-1 flex-1 rounded-full transition-all duration-300 ${
                            i <= newPasswordStrength.level
                              ? newPasswordStrength.color === 'red' ? 'bg-red-500'
                              : newPasswordStrength.color === 'orange' ? 'bg-orange-500'
                              : newPasswordStrength.color === 'yellow' ? 'bg-yellow-500'
                              : newPasswordStrength.color === 'green' ? 'bg-green-500'
                              : 'bg-emerald-500'
                              : isDarkMode ? 'bg-gray-700' : 'bg-gray-200'
                          }`}
                        />
                      ))}
                    </div>
                    <div className="flex justify-between items-center">
                      <p className={`text-xs ${
                        newPasswordStrength.color === 'red' ? 'text-red-400'
                        : newPasswordStrength.color === 'orange' ? 'text-orange-400'
                        : newPasswordStrength.color === 'yellow' ? 'text-yellow-400'
                        : newPasswordStrength.color === 'green' ? 'text-green-400'
                        : 'text-emerald-400'
                      }`}>
                        {newPasswordStrength.label} password
                      </p>
                      <p className={`text-xs ${isDarkMode ? 'text-gray-500' : 'text-gray-400'}`}>Min 8 characters</p>
                    </div>
                  </div>
                )}
              </div>

              <div className="md:col-span-2">
                <label className={`block text-xs font-medium ${isDarkMode ? 'text-amber-50' : 'text-amber-900'} mb-1`}>
                  Confirm New Password *
                </label>
                <div className="relative">
                  <input
                    type={showConfirmPassword ? 'text' : 'password'}
                    name="new_password_confirmation"
                    value={passwordData.new_password_confirmation}
                    onChange={handlePasswordChange}
                    className={`w-full px-3 pr-9 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-500 focus:border-amber-500 transition-all duration-200 text-sm ${
                      passwordData.new_password_confirmation.length > 0 && !passwordsMatch
                        ? isDarkMode ? 'bg-gray-800 border-red-500 text-white' : 'bg-white border-red-400 text-gray-900'
                        : passwordData.new_password_confirmation.length > 0 && passwordsMatch
                          ? isDarkMode ? 'bg-gray-800 border-green-500 text-white' : 'bg-white border-green-400 text-gray-900'
                          : isDarkMode ? 'bg-gray-800 border-gray-600 text-white' : 'bg-white border-gray-300 text-gray-900'
                    }`}
                    required
                  />
                  <button
                    type="button"
                    onClick={() => setShowConfirmPassword(!showConfirmPassword)}
                    className="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-400 hover:text-amber-400 transition-colors"
                    tabIndex={-1}
                  >
                    {showConfirmPassword ? <EyeSlashIcon className="h-4 w-4" /> : <EyeIcon className="h-4 w-4" />}
                  </button>
                </div>
                {/* Real-time password match indicator */}
                {passwordData.new_password_confirmation.length > 0 && !passwordsMatch && (
                  <p className="text-xs mt-1 text-red-400 flex items-center">
                    <ExclamationTriangleIcon className="h-3 w-3 mr-1 flex-shrink-0" />
                    Passwords do not match
                  </p>
                )}
                {passwordData.new_password_confirmation.length > 0 && passwordsMatch && (
                  <p className="text-xs mt-1 text-green-400 flex items-center">
                    <CheckCircleIcon className="h-3 w-3 mr-1 flex-shrink-0" />
                    Passwords match
                  </p>
                )}
                {passwordErrors.new_password_confirmation && (
                  <p className="text-xs mt-1 text-red-400">{passwordErrors.new_password_confirmation}</p>
                )}
              </div>
            </div>

            <div className={`flex justify-end pt-4 border-t ${isDarkMode ? 'border-gray-700' : 'border-gray-200'}`}>
              <button
                type="submit"
                disabled={loading || (passwordData.new_password_confirmation.length > 0 && !passwordsMatch)}
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

      {/* Danger Zone - Delete Account */}
      {user?.role !== 'admin' && (
        <div className={`${isDarkMode ? 'bg-gray-900 border-red-500/20' : 'bg-white border-red-300/40'} border rounded-lg shadow p-6 hover:border-red-500/40 transition-all duration-300`}>
          <h4 className={`text-sm font-semibold text-red-500 mb-4 flex items-center`}>
            <ExclamationTriangleIcon className="h-4 w-4 mr-2" />
            Danger Zone
          </h4>
          <p className={`text-xs ${isDarkMode ? 'text-gray-400' : 'text-gray-600'} mb-4`}>
            Once you delete your account, there is no going back. All your data, appointments, messages, and associated information will be permanently removed.
          </p>
          <button
            onClick={() => { setShowDeleteModal(true); setDeleteConfirmText(''); setDeleteError(''); }}
            className="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 transition-all duration-200 font-medium text-sm flex items-center gap-2"
          >
            <TrashIcon className="h-4 w-4" />
            Delete Account
          </button>
        </div>
      )}

      {/* Delete Account Confirmation Modal */}
      {showDeleteModal && (
        <div className="fixed inset-0 bg-black/60 z-50 flex items-center justify-center p-4" onClick={() => setShowDeleteModal(false)}>
          <div 
            className={`${isDarkMode ? 'bg-gray-800' : 'bg-white'} rounded-xl shadow-2xl w-full max-w-md mx-auto overflow-hidden`}
            onClick={e => e.stopPropagation()}
          >
            {/* Modal Header */}
            <div className={`${isDarkMode ? 'bg-red-900/30 border-red-800/50' : 'bg-red-50 border-red-200'} px-6 py-4 border-b`}>
              <div className="flex items-center gap-3">
                <div className={`w-10 h-10 rounded-full ${isDarkMode ? 'bg-red-900/50' : 'bg-red-100'} flex items-center justify-center`}>
                  <ExclamationTriangleIcon className={`w-6 h-6 ${isDarkMode ? 'text-red-400' : 'text-red-600'}`} />
                </div>
                <div>
                  <h3 className={`text-lg font-semibold ${isDarkMode ? 'text-red-300' : 'text-red-800'}`}>Delete Account</h3>
                  <p className={`text-xs ${isDarkMode ? 'text-red-400' : 'text-red-600'}`}>This action cannot be undone</p>
                </div>
              </div>
            </div>

            {/* Modal Body */}
            <div className="px-6 py-5 space-y-4">
              <div className={`p-3 rounded-lg ${isDarkMode ? 'bg-red-900/20 border-red-800/40' : 'bg-red-50 border-red-200'} border`}>
                <p className={`text-sm ${isDarkMode ? 'text-red-300' : 'text-red-700'}`}>
                  Deleting your account will permanently remove all your data, appointments, messages, and associated information. This cannot be recovered.
                </p>
              </div>

              {/* Type "confirm" */}
              <div>
                <label className={`block text-sm font-medium ${isDarkMode ? 'text-gray-300' : 'text-gray-700'} mb-1.5`}>
                  Type <span className={`font-bold ${isDarkMode ? 'text-red-400' : 'text-red-600'}`}>"confirm"</span> to proceed
                </label>
                <input
                  type="text"
                  value={deleteConfirmText}
                  onChange={e => setDeleteConfirmText(e.target.value)}
                  placeholder='Type "confirm" here'
                  autoComplete="off"
                  className={`w-full px-3 py-2 rounded-lg border ${isDarkMode ? 'border-gray-600 bg-gray-700 text-white' : 'border-gray-300 bg-white text-gray-900'} text-sm focus:ring-2 focus:ring-red-500 focus:border-red-500 outline-none`}
                />
              </div>

              {/* Error message */}
              {deleteError && (
                <div className={`p-2.5 rounded-lg ${isDarkMode ? 'bg-red-900/20 border-red-800/40' : 'bg-red-50 border-red-200'} border`}>
                  <p className={`text-sm ${isDarkMode ? 'text-red-400' : 'text-red-600'}`}>{deleteError}</p>
                </div>
              )}
            </div>

            {/* Modal Footer */}
            <div className={`px-6 py-4 border-t ${isDarkMode ? 'border-gray-700' : 'border-gray-200'} flex gap-3`}>
              <button
                onClick={() => setShowDeleteModal(false)}
                className={`flex-1 px-4 py-2.5 rounded-lg border ${isDarkMode ? 'border-gray-600 text-gray-300 hover:bg-gray-700' : 'border-gray-300 text-gray-700 hover:bg-gray-50'} text-sm font-medium transition-colors`}
              >
                Cancel
              </button>
              <button
                onClick={async () => {
                  if (deleteConfirmText !== 'confirm') return;
                  try {
                    setDeleteLoading(true);
                    setDeleteError('');
                    await axios.delete('/api/account/delete', { data: { confirmation: 'confirm' } });
                    setShowDeleteModal(false);
                    localStorage.removeItem('token');
                    localStorage.removeItem('user');
                    window.location.href = '/';
                  } catch (err) {
                    setDeleteError(err.response?.data?.message || 'Failed to delete account.');
                  } finally {
                    setDeleteLoading(false);
                  }
                }}
                disabled={deleteConfirmText !== 'confirm' || deleteLoading}
                className={`flex-1 px-4 py-2.5 rounded-lg text-sm font-medium text-white transition-colors ${
                  deleteConfirmText === 'confirm' && !deleteLoading
                    ? 'bg-red-600 hover:bg-red-700'
                    : 'bg-red-300 dark:bg-red-800 cursor-not-allowed'
                }`}
              >
                {deleteLoading ? (
                  <span className="flex items-center justify-center gap-2">
                    <span className="w-4 h-4 border-2 border-white/30 border-t-white rounded-full animate-spin"></span>
                    Deleting...
                  </span>
                ) : (
                  'Delete My Account'
                )}
              </button>
            </div>
          </div>
        </div>
      )}
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
      case 'notifications': return <UserNotifications />;
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
  if (redirecting || user?.role === 'admin' || user?.role === 'staff' || user?.role === 'cashier') {
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
      <div className={`fixed inset-y-0 right-0 lg:right-auto lg:left-0 z-40 w-64 h-screen ${isDarkMode ? 'bg-gray-900 border-amber-500/20' : 'bg-white border-amber-300/40'} border-l lg:border-l-0 lg:border-r shadow-xl ${
        showMobileSidebar ? 'translate-x-0' : 'translate-x-full lg:translate-x-0'
      }`} role="navigation" aria-label="Main navigation">
        <div className="flex flex-col h-full">
          {/* Logo Section */}
          <div className={`p-4 shadow-md ${isDarkMode ? 'bg-gray-800 border-amber-500/30' : 'bg-gray-50 border-amber-300/50'} px-3 border-b transition-colors duration-300`}>
            <div className="flex items-center justify-center space-x-3">
              <div className="w-12 h-12 flex items-center justify-center flex-shrink-0">
                <img 
                  src={isDarkMode ? '/logo-dark-v2.png' : '/logo-light-v2.png'} 
                  alt="Logo" 
                  className="h-full w-full object-contain pointer-events-none drop-shadow-sm transition-opacity duration-300"
                  onError={(e) => {
                    e.target.onerror = null;
                    e.target.src = '/logo.png';
                  }}
                />
              </div>
              <div className="flex-1 min-w-0">
                <h1 className={`text-sm font-bold tracking-wider ${isDarkMode ? 'text-amber-50' : 'text-amber-900'} transition-colors duration-300 truncate`}>LEGAL EASE</h1>
                <p className={`text-xs ${isDarkMode ? 'text-amber-400/60' : 'text-amber-700/60'}`}>Notarization</p>
              </div>
              <button
                onClick={() => setShowMobileSidebar(false)}
                className="lg:hidden text-gray-400 hover:text-amber-400 transition-colors p-1 flex-shrink-0"
                aria-label="Close navigation menu"
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
                      className={`w-full flex items-center justify-between px-2.5 py-1.5 text-xs lg:text-xs font-medium rounded-none border group relative overflow-hidden ${
                        item.current
                          ? (isDarkMode ? 'text-amber-400 border-amber-500/30' : 'text-gray-900 border-amber-300/30')
                          : (isDarkMode ? 'text-gray-400 border-transparent hover:text-amber-300' : 'text-gray-700 border-transparent hover:text-gray-900')
                      }`}
                    >
                      <div className="flex items-center flex-1 min-w-0">
                        <item.icon className={`mr-2.5 h-4 w-4 flex-shrink-0 ${
                          item.current ? 'text-amber-400' : 'text-gray-500'
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

          {/* Footer with Settings, About Us (Desktop only) and Logout */}
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

            {/* About Us Button - Hidden on mobile */}
            <button
              onClick={() => {
                setShowAboutUs(true);
                setShowMobileSidebar(false);
              }}
              className={`hidden lg:flex w-full items-center px-3 py-2 text-xs font-medium rounded-lg transition-all duration-200 border ${
                isDarkMode
                  ? 'text-gray-400 border-gray-700 hover:border-cyan-500/30 hover:bg-cyan-500/8 hover:text-cyan-300'
                  : 'text-gray-600 border-gray-300 hover:border-cyan-400/50 hover:bg-cyan-200/10 hover:text-cyan-700'
              }`}
            >
              <InformationCircleIcon className="mr-2 h-4 w-4 flex-shrink-0" />
              About Us
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
      <div className="flex-1 flex flex-col min-w-0 lg:mt-0 mt-0 lg:ml-64 h-auto lg:h-screen overflow-y-auto lg:overflow-hidden">
        {/* Mobile Header - visible only on small screens */}
        <header className={`lg:hidden flex items-center justify-between px-4 py-2.5 ${isDarkMode ? 'bg-gray-900 border-amber-500/20' : 'bg-white border-amber-300/40'} border-b shadow-sm flex-shrink-0`}>
          <div className="flex items-center gap-2 min-w-0">
            {/* Hamburger menu - hidden for regular users who have bottom nav */}
            {(user?.role === 'admin' || user?.role === 'staff') && (
              <button
                onClick={() => setShowMobileSidebar(true)}
                className={`p-1.5 rounded-lg ${isDarkMode ? 'text-amber-400 hover:bg-amber-500/10' : 'text-amber-700 hover:bg-amber-100'}`}
                aria-label="Open navigation menu"
              >
                <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 6h16M4 12h16M4 18h16" />
                </svg>
              </button>
            )}
            {activeTab !== 'home' && (user?.role === 'admin' || user?.role === 'staff') && (
              <button
                onClick={() => setActiveTab('home')}
                className={`p-1.5 mr-1 rounded-lg ${isDarkMode ? 'text-amber-400 hover:bg-amber-500/10' : 'text-amber-700 hover:bg-amber-100'}`}
                aria-label="Back"
              >
                <ArrowLeftIcon className="h-5 w-5" />
              </button>
            )}
            <div className="w-12 h-12 flex items-center justify-center flex-shrink-0">
               <img 
                 src={isDarkMode ? '/logo-dark-v2.png' : '/logo-light-v2.png'} 
                 alt="Legal Ease Logo" 
                 className="h-full w-full object-contain pointer-events-none drop-shadow-sm transition-opacity duration-300"
                 onError={(e) => {
                   e.target.onerror = null;
                   e.target.src = '/logo.png';
                 }}
               />
            </div>
            <div className="min-w-0">
              <h1 className={`text-sm font-bold tracking-wide ${isDarkMode ? 'text-amber-50' : 'text-amber-900'}`}>
                {(() => {
                  const tabNames = { home: 'Home', book: 'Book Appointment', appointments: 'My Appointments', messages: 'Messages', refunds: 'Refunds', 'action-logs': 'Action Logs', feedback: 'Feedback', notifications: 'Notifications', profile: 'Profile', settings: 'Settings' };
                  return tabNames[activeTab] || 'LEGAL EASE';
                })()}
              </h1>
            </div>
          </div>
          <div className="flex items-center gap-1">
            <ThemeToggle />
            {/* Notification Bell for mobile instead of profile dropdown */}
            <MobileNotificationBell 
              isOpen={isMobileNotificationsOpen}
              onToggle={() => setIsMobileNotificationsOpen(!isMobileNotificationsOpen)}
              onClose={() => setIsMobileNotificationsOpen(false)}
              onViewAll={() => handleNavClick('notifications')}
              isDarkMode={isDarkMode}
            />
          </div>
        </header>

        {/* Desktop Header */}
        <header className={`${isDarkMode ? 'bg-gray-900 border-amber-500/20' : 'bg-gray-50 border-amber-300/40'} border-b shadow flex-shrink-0 transition-colors duration-300 hidden lg:flex flex-col`}>
          <div className="flex justify-between items-center px-4 lg:px-6 py-3 lg:py-4">
            <div className="flex items-center space-x-3 min-w-0">
              {activeTab !== 'home' && (
                <button
                  onClick={loadInitialData}
                  className={`p-1.5 flex-shrink-0 ${isDarkMode ? 'text-amber-400 hover:text-amber-300 hover:bg-amber-500/10 border-amber-500/30' : 'text-amber-700 hover:text-amber-600 hover:bg-amber-500/20 border-amber-300/30'} rounded border transition-colors duration-200`}
                  title="Refresh data"
                >
                  <ArrowPathIcon className="h-3 w-3 lg:h-4 lg:w-4" />
                </button>
              )}
            </div>
            <div className="flex items-center gap-2 flex-shrink-0">
              <ThemeToggle />
              <NotificationBell onViewAll={() => handleNavClick('notifications')} />
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
        <main className={`flex-1 ${activeTab === 'messages' ? 'p-2 sm:p-4 lg:p-6' : 'p-3 sm:p-4 lg:p-6'} pb-24 lg:pb-6 overflow-y-auto scrollbar-hide ${isDarkMode ? '' : 'bg-gray-100'} transition-colors duration-300`}>
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
        isDarkMode={isDarkMode}
      />

      <ConfirmationModal
        isOpen={showCancelModal}
        onClose={() => {
          setShowCancelModal(false);
          setSelectedAppointment(null);
        }}
        onConfirm={handleCancelAppointment}
        title="Cancel Appointment"
        message={`Are you sure you want to cancel your ${formatServiceName(selectedAppointment)} appointment on ${selectedAppointment ? formatDateDisplay(selectedAppointment.appointment_date) : ''}? This action cannot be undone.`}
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
        onOpenTerms={(tab) => { setTermsPrivacyTab(tab); setShowTermsPrivacyModal(true); }}
        isDarkMode={isDarkMode}
      />

      <AboutUsModal
        isOpen={showAboutUs}
        onClose={() => setShowAboutUs(false)}
        onOpenTerms={(tab) => { setTermsPrivacyTab(tab); setShowTermsPrivacyModal(true); }}
        isDarkMode={isDarkMode}
      />

      <TermsPrivacyModal
        isOpen={showTermsPrivacyModal}
        onClose={() => setShowTermsPrivacyModal(false)}
        initialTab={termsPrivacyTab}
        isDarkMode={isDarkMode}
      />

      <ThankYouModal
        isOpen={showThankYouModal}
        onClose={() => setShowThankYouModal(false)}
        appointment={latestAppointment}
        isDarkMode={isDarkMode}
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
                    <p className="text-amber-50 font-medium">{formatDateDisplay(selectedAppointment.appointment_date)}</p>
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
                    if (window.history.length > 1) {
                      navigate(-1);
                    } else {
                      setShowProfileMenu(false);
                      setActiveTab('home');
                    }
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
