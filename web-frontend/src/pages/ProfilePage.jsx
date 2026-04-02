
import React, { useState, useEffect } from 'react';
import axios from 'axios';
import { useAuth } from '../context/AuthContext';
import { useTheme } from '../context/ThemeContext';
import {
  Cog6ToothIcon,
  ArrowRightOnRectangleIcon,
  ArrowRightIcon,
  UserIcon,
  ShieldCheckIcon,
  BellIcon,
  LanguageIcon,
  InformationCircleIcon,
  AdjustmentsHorizontalIcon,
  CalendarIcon,
  QuestionMarkCircleIcon,
  ArrowLeftIcon,
  CurrencyDollarIcon,
  ClockIcon,
  SunIcon,
  MoonIcon,
  EnvelopeIcon,
  ChevronDownIcon,
  StarIcon,
  CheckCircleIcon,
  ExclamationTriangleIcon,
  XCircleIcon,
  TrashIcon,
  DocumentTextIcon,
  LockClosedIcon,
  MagnifyingGlassIcon,
  ArrowPathIcon,
  ChevronLeftIcon,
  ChevronRightIcon,
  ArrowsUpDownIcon
} from '@heroicons/react/24/outline';


const ProfilePage = ({ onBack, onTabChange, onLogout }) => {
  const { user, logout, updateUser } = useAuth();
  const { isDarkMode, setIsDarkMode } = useTheme(); // Use ThemeContext
  const initials = (user?.first_name?.[0] || '') + (user?.last_name?.[0] || '');
  
  // Profile picture state - use the full URL from the backend (profile_picture_url accessor)
  const [profilePicture, setProfilePicture] = useState(user?.profile_picture_url || null);
  
  // Current menu section state - for mobile back button
  const [currentMenuSection, setCurrentMenuSection] = useState('main');
  
  // Theme menu state
  const [showThemeMenu, setShowThemeMenu] = useState(false);
  
  // Notification preferences state
  const [notificationPreferences, setNotificationPreferences] = useState({
    email_notifications: true,
    sms_notifications: true
  });
  const [showNotifications, setShowNotifications] = useState(false);
  const [loadingNotifications, setLoadingNotifications] = useState(false);
  
  // Refunds state
  const [refunds, setRefunds] = useState([]);
  const [refundsLoading, setRefundsLoading] = useState(false);
  const [refundsError, setRefundsError] = useState('');
  const [expandedRefundId, setExpandedRefundId] = useState(null);
  // Action logs state
  const [actionLogs, setActionLogs] = useState([]);
  const [actionLogsLoading, setActionLogsLoading] = useState(false);
  const [actionLogsError, setActionLogsError] = useState('');
  const [actionLogsPage, setActionLogsPage] = useState(1);
  const [actionLogsTotalPages, setActionLogsTotalPages] = useState(1);
  const [actionLogsSearch, setActionLogsSearch] = useState('');
  const [actionLogsFilter, setActionLogsFilter] = useState('');
  const [actionLogsSort, setActionLogsSort] = useState('desc');
  
  // Delete account state
  const [showDeleteModal, setShowDeleteModal] = useState(false);
  const [deleteConfirmText, setDeleteConfirmText] = useState('');
  const [deleteLoading, setDeleteLoading] = useState(false);
  const [deleteError, setDeleteError] = useState('');
  
  // Load notification preferences from localStorage
  useEffect(() => {
    const savedNotifications = localStorage.getItem('userNotifications');
    if (savedNotifications) {
      try {
        setNotificationPreferences(JSON.parse(savedNotifications));
      } catch (e) {
        console.error('Error loading notification preferences:', e);
      }
    }
  }, []);
  
  // Load refunds when section is opened
  useEffect(() => {
    if (currentMenuSection === 'refunds') {
      loadRefunds();
    }
  }, [currentMenuSection]);
  
  // Load action logs when section is opened or filters change
  useEffect(() => {
    if (currentMenuSection === 'action-logs') {
      loadActionLogs();
    }
  }, [currentMenuSection, actionLogsPage, actionLogsFilter, actionLogsSort]);

  // Debounced search for action logs
  useEffect(() => {
    if (currentMenuSection !== 'action-logs') return;
    const timer = setTimeout(() => {
      setActionLogsPage(1);
      loadActionLogs();
    }, 400);
    return () => clearTimeout(timer);
  }, [actionLogsSearch]);
  
  const loadRefunds = async () => {
    try {
      setRefundsLoading(true);
      setRefundsError('');
      const response = await axios.get('/api/refunds/my', { params: { per_page: 100 } });
      setRefunds(response.data.data || response.data || []);
    } catch (err) {
      setRefundsError(err.response?.data?.message || 'Failed to load refunds');
      console.error('Error loading refunds:', err);
    } finally {
      setRefundsLoading(false);
    }
  };
  
  const loadActionLogs = async () => {
    try {
      setActionLogsLoading(true);
      setActionLogsError('');
      const params = { page: actionLogsPage, per_page: 10, sort: actionLogsSort };
      if (actionLogsFilter) params.action = actionLogsFilter;
      if (actionLogsSearch) params.search = actionLogsSearch;
      const response = await axios.get('/api/action-logs/my/logs', { params });
      setActionLogs(response.data.data || []);
      setActionLogsTotalPages(response.data.pagination?.last_page || 1);
    } catch (err) {
      setActionLogsError(err.response?.data?.message || 'Failed to load action logs');
      console.error('Error loading action logs:', err);
    } finally {
      setActionLogsLoading(false);
    }
  };
  
  const applyTheme = (isDark) => {
    const root = document.documentElement;
    if (isDark) {
      root.classList.add('dark');
      root.style.backgroundColor = 'rgb(11, 11, 11)';
      root.style.color = 'rgb(250, 245, 235)';
      root.classList.remove('user-light');
    } else {
      root.classList.remove('dark');
      root.style.setProperty('--primary', '#1E3A8A');
      root.style.setProperty('--secondary', '#2563EB');
      root.style.setProperty('--accent', '#F59E0B');
      root.style.setProperty('--background', '#F9FAFB');
      root.classList.add('user-light');
    }
  };
  
  const handleThemeChange = (theme) => {
    const isDark = theme === 'dark';
    setIsDarkMode(isDark); // Update global theme
    applyTheme(isDark);
    setShowThemeMenu(false);
  };
  
  const handleNotificationChange = (key, value) => {
    const newPreferences = {
      ...notificationPreferences,
      [key]: value
    };
    setNotificationPreferences(newPreferences);
    localStorage.setItem('userNotifications', JSON.stringify(newPreferences));
  };  // Helper to handle navigation that closes the modal and goes to the section
  const handleNavToSection = (tabName) => {
    // Navigate to the section within the ProfilePage menu
    setCurrentMenuSection(tabName);
  };

  const handleBackToMenu = () => {
    setCurrentMenuSection('main');
  };

  const handleLogout = () => {
    if (onBack) {
      onBack();
    }
    if (onLogout) {
      onLogout();
    } else {
      logout();
    }
  };

  const handleDeleteAccount = async () => {
    if (deleteConfirmText !== 'confirm') return;

    try {
      setDeleteLoading(true);
      setDeleteError('');
      await axios.delete('/api/account/delete', {
        data: { confirmation: 'confirm' }
      });
      // Account deleted — clear local auth state and redirect to landing page
      setShowDeleteModal(false);
      localStorage.removeItem('token');
      localStorage.removeItem('user');
      // Redirect to the landing page
      window.location.href = '/';
    } catch (err) {
      setDeleteError(err.response?.data?.message || 'Failed to delete account.');
    } finally {
      setDeleteLoading(false);
    }
  };

  const menuItems = [
    {
      section: 'Account',
      items: [
        {
          icon: UserIcon,
          label: 'Manage Profile',
          action: () => {
            if (onTabChange) {
              onTabChange('profile');
            }
          },
          color: 'text-blue-500'
        },
        {
          icon: ShieldCheckIcon,
          label: 'Password & Security',
          action: () => {
            if (onTabChange) {
              onTabChange('profile');
            }
          },
          color: 'text-green-500'
        },
        {
          icon: BellIcon,
          label: 'Notifications',
          isExpandable: true,
          color: 'text-orange-500',
          expanded: showNotifications,
          onToggleExpand: () => setShowNotifications(!showNotifications)
        },
        {
          icon: LanguageIcon,
          label: 'Language',
          value: 'English',
          action: () => {},
          color: 'text-purple-500'
        }
      ]
    },
    {
      section: 'Support',
      items: [
        {
          icon: StarIcon,
          label: 'Feedback',
          action: () => {
            if (onTabChange) {
              onTabChange('feedback');
            }
          },
          color: 'text-yellow-500'
        },
        {
          icon: CurrencyDollarIcon,
          label: 'Refunds',
          action: () => handleNavToSection('refunds'),
          color: 'text-amber-500'
        },
        {
          icon: ClockIcon,
          label: 'Action Logs',
          action: () => handleNavToSection('action-logs'),
          color: 'text-blue-500'
        },
        {
          icon: QuestionMarkCircleIcon,
          label: 'Help Center',
          action: () => {},
          color: 'text-yellow-500'
        },
        {
          icon: InformationCircleIcon,
          label: 'About Us',
          action: () => handleNavToSection('about-us'),
          color: 'text-cyan-500'
        },
        {
          icon: DocumentTextIcon,
          label: 'Terms & Conditions',
          action: () => handleNavToSection('terms'),
          color: 'text-amber-500'
        },
        {
          icon: LockClosedIcon,
          label: 'Privacy Policy',
          action: () => handleNavToSection('privacy'),
          color: 'text-green-500'
        }
      ]
    }
  ];

  return (
    <div className="w-full h-full flex flex-col items-stretch justify-start bg-white dark:bg-gray-900 sm:rounded-lg sm:shadow-lg">
      {/* Header */}
      <div className="flex items-center justify-between px-4 py-4 border-b border-gray-200 dark:border-gray-700 sticky top-0 bg-white dark:bg-gray-900">
        <button 
          onClick={currentMenuSection === 'main' ? onBack : handleBackToMenu} 
          className="p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800 focus:outline-none transition-colors"
        >
          <ArrowLeftIcon className="w-5 h-5 text-gray-600 dark:text-gray-300" />
        </button>
        <h2 className="text-lg font-semibold text-gray-900 dark:text-white">
          {currentMenuSection === 'main' 
            ? 'Profile' 
            : currentMenuSection === 'profile'
            ? 'Manage Profile'
            : currentMenuSection === 'refunds' 
            ? 'Refunds' 
            : currentMenuSection === 'action-logs' 
            ? 'Action Logs'
            : currentMenuSection === 'terms'
            ? 'Terms & Conditions'
            : currentMenuSection === 'privacy'
            ? 'Privacy Policy'
            : currentMenuSection === 'about-us'
            ? 'About Us'
            : 'Profile'}
        </h2>
        <div className="w-9"></div>
      </div>

      {/* Scrollable Content */}
      <div className="overflow-y-auto flex-1">
        {currentMenuSection === 'main' ? (
          <>
            {/* Profile Card with Picture Upload */}
            <div className="px-4 py-6 border-b border-gray-200 dark:border-gray-700">
              <div className="flex flex-col items-center">
                {/* Profile Avatar (display only) */}
                <div className="relative w-24 h-24 rounded-full overflow-hidden border-4 border-amber-400 bg-gradient-to-br from-amber-400 to-amber-600 flex items-center justify-center flex-shrink-0">
                  {profilePicture ? (
                    <img src={profilePicture} alt="Profile" className="w-full h-full object-cover" />
                  ) : (
                    <span className="text-white text-4xl font-bold">{initials.toUpperCase() || 'U'}</span>
                  )}
                </div>
                
                {/* User Info */}
                <div className="mt-4 text-center">
                  <p className="font-semibold text-base text-gray-900 dark:text-white">
                    {user?.first_name} {user?.last_name}
                  </p>
                  <p className="text-gray-500 dark:text-gray-400 text-sm">
                    {user?.email}
                  </p>
                </div>
              </div>
            </div>

            {/* Menu Sections */}
            <div className="px-2">
          {menuItems.map((section, sectionIdx) => (
            <div key={sectionIdx} className="mb-6">
              {/* Section Title */}
              <h3 className="px-3 py-2 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                {section.section}
              </h3>

              {/* Section Items */}
              <div className="space-y-1">
                {section.items.map((item, itemIdx) => {
                  const IconComponent = item.icon;
                  return (
                    <div key={itemIdx}>
                      <button
                        onClick={() => {
                          if (item.isExpandable) {
                            item.onToggleExpand?.();
                          } else {
                            item.action?.();
                          }
                        }}
                        className="w-full flex items-center justify-between px-3 py-3 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors focus:outline-none focus:ring-2 focus:ring-amber-500 focus:ring-offset-2 dark:focus:ring-offset-gray-900"
                      >
                        <div className="flex items-center gap-3 flex-1 min-w-0">
                          <IconComponent className={`w-5 h-5 flex-shrink-0 ${item.color}`} />
                          <span className="font-medium text-gray-700 dark:text-gray-200 truncate">
                            {item.label}
                          </span>
                        </div>
                        {item.isExpandable && (
                          <ChevronDownIcon className={`w-4 h-4 text-gray-400 dark:text-gray-500 flex-shrink-0 ml-2 transition-transform ${item.expanded ? 'rotate-180' : ''}`} />
                        )}
                        {item.value && !item.isExpandable && (
                          <span className="text-gray-500 dark:text-gray-400 text-sm ml-2">
                            {item.value}
                          </span>
                        )}
                        {!item.isExpandable && (
                          <ArrowRightIcon className="w-4 h-4 text-gray-400 dark:text-gray-500 flex-shrink-0 ml-2" />
                        )}
                      </button>
                      
                      {/* Expandable Content - Theme */}
                      {item.isExpandable && item.label === 'Theme' && item.expanded && (
                        <div className="px-3 py-3 ml-8 space-y-2 bg-gray-50 dark:bg-gray-800/50 rounded-lg mb-1">
                          <div className="space-y-2">
                            <label className="flex items-center gap-3 cursor-pointer p-2 rounded hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors">
                              <input
                                type="radio"
                                name="theme"
                                checked={isDarkMode === true}
                                onChange={() => handleThemeChange('dark')}
                                className="w-4 h-4 rounded-full"
                              />
                              <MoonIcon className="w-4 h-4 text-gray-600 dark:text-gray-300" />
                              <span className="text-sm text-gray-700 dark:text-gray-300">Dark Mode</span>
                            </label>
                            
                            <label className="flex items-center gap-3 cursor-pointer p-2 rounded hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors">
                              <input
                                type="radio"
                                name="theme"
                                checked={isDarkMode === false}
                                onChange={() => handleThemeChange('light')}
                                className="w-4 h-4 rounded-full"
                              />
                              <SunIcon className="w-4 h-4 text-gray-600 dark:text-gray-300" />
                              <span className="text-sm text-gray-700 dark:text-gray-300">Light Mode</span>
                            </label>
                          </div>
                        </div>
                      )}
                      
                      {/* Expandable Content - Notifications */}
                      {item.isExpandable && item.label === 'Notifications' && item.expanded && (
                        <div className="px-3 py-3 ml-8 space-y-3 bg-gray-50 dark:bg-gray-800/50 rounded-lg mb-1">
                          <label className="flex items-center justify-between p-2 rounded hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors cursor-pointer">
                            <div className="flex items-center gap-3">
                              <EnvelopeIcon className="w-4 h-4 text-amber-500" />
                              <span className="text-sm text-gray-700 dark:text-gray-300">Email Notifications</span>
                            </div>
                            <div className="relative">
                              <input
                                type="checkbox"
                                checked={notificationPreferences.email_notifications}
                                onChange={(e) => handleNotificationChange('email_notifications', e.target.checked)}
                                className="sr-only peer"
                              />
                              <div className="w-9 h-5 bg-gray-300 dark:bg-gray-600 peer-focus:outline-none peer-focus:ring-2 peer-focus:ring-amber-500 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-amber-600"></div>
                            </div>
                          </label>
                          
                          <label className="flex items-center justify-between p-2 rounded hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors cursor-pointer">
                            <div className="flex items-center gap-3">
                              <BellIcon className="w-4 h-4 text-blue-500" />
                              <span className="text-sm text-gray-700 dark:text-gray-300">SMS Notifications</span>
                            </div>
                            <div className="relative">
                              <input
                                type="checkbox"
                                checked={notificationPreferences.sms_notifications}
                                onChange={(e) => handleNotificationChange('sms_notifications', e.target.checked)}
                                className="sr-only peer"
                              />
                              <div className="w-9 h-5 bg-gray-300 dark:bg-gray-600 peer-focus:outline-none peer-focus:ring-2 peer-focus:ring-amber-500 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-amber-600"></div>
                            </div>
                          </label>
                        </div>
                      )}
                    </div>
                  );
                })}
              </div>
            </div>
          ))}

          {/* Logout Button */}
          <div className="mb-6">
            <h3 className="px-3 py-2 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">
              Session
            </h3>
            <button
              onClick={handleLogout}
              className="w-full flex items-center gap-3 px-3 py-3 rounded-lg hover:bg-red-50 dark:hover:bg-red-500/10 transition-colors focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 dark:focus:ring-offset-gray-900"
            >
              <ArrowRightOnRectangleIcon className="w-5 h-5 text-red-500 flex-shrink-0" />
              <span className="font-medium text-red-600 dark:text-red-400">
                Log Out
              </span>
            </button>
            </div>

            {/* Bottom Padding */}
            <div className="h-4"></div>
          </div>
          </>
        ) : (
          <>
            {/* Refunds Section */}
            {currentMenuSection === 'refunds' && (
              <div className="flex flex-col h-full">
                {refundsLoading && (
                  <div className="flex items-center justify-center h-full">
                    <div className="text-center">
                      <div className="w-8 h-8 rounded-full border-4 border-amber-200 border-t-amber-500 animate-spin mx-auto mb-4"></div>
                      <p className="text-gray-600 dark:text-gray-300">Loading refunds...</p>
                    </div>
                  </div>
                )}
                
                {!refundsLoading && refundsError && (
                  <div className="flex items-center justify-center h-full">
                    <div className="text-center">
                      <XCircleIcon className="w-12 h-12 text-red-500 mx-auto mb-4" />
                      <p className="text-red-600 dark:text-red-400">{refundsError}</p>
                    </div>
                  </div>
                )}
                
                {!refundsLoading && !refundsError && refunds.length === 0 && (
                  <div className="flex items-center justify-center h-full">
                    <div className="text-center">
                      <CurrencyDollarIcon className="w-12 h-12 text-gray-300 dark:text-gray-600 mx-auto mb-4" />
                      <p className="text-gray-600 dark:text-gray-300">No refunds yet</p>
                    </div>
                  </div>
                )}
                
                {!refundsLoading && !refundsError && refunds.length > 0 && (
                  <div className="space-y-3 p-4">
                    {refunds.map((refund, idx) => (
                      <div key={refund.id || idx} className="p-4 rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50">
                        <div className="flex items-start justify-between mb-2">
                          <div className="flex-1">
                            <p className="font-semibold text-gray-900 dark:text-white text-sm">
                              ₱{parseFloat(refund.refund_amount || refund.amount || 0).toFixed(2)}
                            </p>
                            <p className="text-xs text-gray-600 dark:text-gray-400 mt-1">
                              {refund.appointment?.service?.name || refund.service_name || 'Service'}
                            </p>
                          </div>
                          <div className="flex items-center gap-2">
                            {refund.status === 'approved' && <CheckCircleIcon className="w-5 h-5 text-green-500" />}
                            {refund.status === 'pending' && <ExclamationTriangleIcon className="w-5 h-5 text-yellow-500" />}
                            {refund.status === 'completed' && <CheckCircleIcon className="w-5 h-5 text-green-500" />}
                            {refund.status === 'rejected' && <XCircleIcon className="w-5 h-5 text-red-500" />}
                          </div>
                        </div>
                        <p className="text-xs text-gray-500 dark:text-gray-400">
                          Status: <span className="font-medium capitalize">{refund.status}</span>
                        </p>
                        {refund.reason && (
                          <p className="text-xs text-gray-600 dark:text-gray-300 mt-1">
                            Reason: {refund.reason.replace(/_/g, ' ')}
                          </p>
                        )}
                        <button
                          onClick={() => setExpandedRefundId(expandedRefundId === (refund.id || idx) ? null : (refund.id || idx))}
                          className="text-xs text-amber-500 hover:text-amber-400 mt-2 font-medium"
                        >
                          {expandedRefundId === (refund.id || idx) ? '▲ Hide Details' : '▼ View Details'}
                        </button>
                        {expandedRefundId === (refund.id || idx) && (
                          <div className="mt-3 pt-3 border-t border-gray-200 dark:border-gray-700 space-y-2 text-xs">
                            {refund.description && (
                              <p className="text-gray-600 dark:text-gray-300 italic">"{refund.description}"</p>
                            )}
                            {refund.appointment?.appointment_date && (
                              <p className="text-gray-500 dark:text-gray-400">
                                Appointment Date: <span className="font-medium text-gray-700 dark:text-gray-300">{new Date(refund.appointment.appointment_date).toLocaleDateString()}</span>
                                {refund.appointment?.appointment_time && ` at ${refund.appointment.appointment_time}`}
                              </p>
                            )}
                            {refund.is_partial !== undefined && (
                              <p className="text-gray-500 dark:text-gray-400">
                                Type: <span className="font-medium text-gray-700 dark:text-gray-300">{refund.is_partial ? 'Partial Refund' : 'Full Refund'}</span>
                              </p>
                            )}
                            <p className="text-gray-500 dark:text-gray-400">
                              Requested: <span className="font-medium text-gray-700 dark:text-gray-300">{new Date(refund.created_at).toLocaleDateString()}</span>
                            </p>
                            {refund.status === 'rejected' && refund.rejection_reason && (
                              <div className="p-2 bg-red-50 dark:bg-red-900/20 rounded border border-red-200 dark:border-red-800">
                                <p className="font-semibold text-red-600 dark:text-red-400">Rejection Reason:</p>
                                <p className="text-red-600 dark:text-red-300 mt-0.5">{refund.rejection_reason}</p>
                              </div>
                            )}
                            {refund.approval_notes && (
                              <div className="p-2 bg-blue-50 dark:bg-blue-900/20 rounded border border-blue-200 dark:border-blue-800">
                                <p className="font-semibold text-blue-600 dark:text-blue-400">Admin Notes:</p>
                                <p className="text-blue-600 dark:text-blue-300 mt-0.5">{refund.approval_notes}</p>
                              </div>
                            )}
                            {refund.completed_at && (
                              <p className="text-gray-500 dark:text-gray-400">
                                Completed: <span className="font-medium text-gray-700 dark:text-gray-300">{new Date(refund.completed_at).toLocaleDateString()}</span>
                              </p>
                            )}
                            {refund.transaction_id && (
                              <p className="text-gray-500 dark:text-gray-400">
                                Transaction ID: <span className="font-mono font-medium text-gray-700 dark:text-gray-300">{refund.transaction_id}</span>
                              </p>
                            )}
                          </div>
                        )}
                      </div>
                    ))}
                  </div>
                )}
              </div>
            )}
            
            {/* Action Logs Section */}
            {currentMenuSection === 'action-logs' && (
              <div className="flex flex-col h-full">
                {/* Search, Filter & Sort Bar */}
                <div className="p-3 border-b border-gray-200 dark:border-gray-700 space-y-2">
                  {/* Search */}
                  <div className="flex items-center gap-2">
                    <div className="relative flex-1">
                      <MagnifyingGlassIcon className="absolute left-2.5 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 dark:text-gray-500" />
                      <input
                        type="text"
                        placeholder="Search activities..."
                        value={actionLogsSearch}
                        onChange={e => setActionLogsSearch(e.target.value)}
                        className="w-full pl-8 pr-3 py-2 text-xs rounded-lg border border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-800 text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:ring-1 focus:ring-amber-500"
                      />
                    </div>
                    <button
                      onClick={() => { setActionLogsSort(prev => prev === 'desc' ? 'asc' : 'desc'); setActionLogsPage(1); }}
                      className="p-2 rounded-lg border border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-800 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors"
                      title={actionLogsSort === 'desc' ? 'Newest first' : 'Oldest first'}
                    >
                      <ArrowsUpDownIcon className="w-4 h-4 text-gray-500 dark:text-gray-400" />
                    </button>

                  </div>
                  {/* Action Type Filters */}
                  <div className="flex flex-wrap gap-1.5">
                    {['create', 'update', 'delete', 'restore', 'approve'].map(action => (
                      <button
                        key={action}
                        onClick={() => { setActionLogsFilter(prev => prev === action ? '' : action); setActionLogsPage(1); }}
                        className={`px-2.5 py-1 rounded-full text-xs font-medium transition-colors ${
                          actionLogsFilter === action
                            ? 'bg-amber-100 dark:bg-amber-500/20 text-amber-700 dark:text-amber-300 border border-amber-300 dark:border-amber-500/40'
                            : 'bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400 border border-gray-200 dark:border-gray-600 hover:bg-gray-200 dark:hover:bg-gray-700'
                        }`}
                      >
                        {action.charAt(0).toUpperCase() + action.slice(1)}
                      </button>
                    ))}
                  </div>
                </div>

                {/* Content Area */}
                <div className="flex-1 overflow-y-auto">
                  {actionLogsLoading && (
                    <div className="flex items-center justify-center py-12">
                      <div className="text-center">
                        <div className="w-8 h-8 rounded-full border-4 border-blue-200 border-t-blue-500 animate-spin mx-auto mb-4"></div>
                        <p className="text-gray-600 dark:text-gray-300 text-sm">Loading action logs...</p>
                      </div>
                    </div>
                  )}

                  {!actionLogsLoading && actionLogsError && (
                    <div className="flex items-center justify-center py-12">
                      <div className="text-center">
                        <XCircleIcon className="w-10 h-10 text-red-500 mx-auto mb-3" />
                        <p className="text-red-600 dark:text-red-400 text-sm">{actionLogsError}</p>
                        <button onClick={loadActionLogs} className="mt-2 text-xs text-amber-600 dark:text-amber-400 hover:underline">
                          Try again
                        </button>
                      </div>
                    </div>
                  )}

                  {!actionLogsLoading && !actionLogsError && actionLogs.length === 0 && (
                    <div className="flex items-center justify-center py-12">
                      <div className="text-center">
                        <ClockIcon className="w-10 h-10 text-gray-300 dark:text-gray-600 mx-auto mb-3" />
                        <p className="text-gray-600 dark:text-gray-300 text-sm">
                          {actionLogsSearch || actionLogsFilter ? 'No matching logs found' : 'No action logs yet'}
                        </p>
                      </div>
                    </div>
                  )}

                  {!actionLogsLoading && !actionLogsError && actionLogs.length > 0 && (
                    <div className="space-y-2 p-3">
                      {actionLogs.map((log, idx) => (
                        <div key={log.id || idx} className="p-3 rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50">
                          <div className="flex items-start justify-between mb-1">
                            <span className={`inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold capitalize ${
                              log.action === 'create' ? 'bg-green-100 text-green-700 dark:bg-green-500/20 dark:text-green-400' :
                              log.action === 'update' ? 'bg-blue-100 text-blue-700 dark:bg-blue-500/20 dark:text-blue-400' :
                              log.action === 'delete' ? 'bg-red-100 text-red-700 dark:bg-red-500/20 dark:text-red-400' :
                              log.action === 'restore' ? 'bg-amber-100 text-amber-700 dark:bg-amber-500/20 dark:text-amber-400' :
                              log.action === 'approve' || log.action === 'complete' ? 'bg-green-100 text-green-700 dark:bg-green-500/20 dark:text-green-400' :
                              'bg-gray-100 text-gray-700 dark:bg-gray-500/20 dark:text-gray-400'
                            }`}>
                              {log.action || 'Action'}
                            </span>
                            <p className="text-[10px] text-gray-500 dark:text-gray-400">
                              {log.created_at ? new Date(log.created_at).toLocaleString(undefined, { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' }) : ''}
                            </p>
                          </div>
                          {log.description && (
                            <p className="text-xs text-gray-600 dark:text-gray-400 mt-1">
                              {log.description}
                            </p>
                          )}
                        </div>
                      ))}
                    </div>
                  )}
                </div>

                {/* Pagination */}
                {actionLogsTotalPages > 1 && (
                  <div className="flex items-center justify-between p-3 border-t border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900">
                    <button
                      onClick={() => setActionLogsPage(p => Math.max(1, p - 1))}
                      disabled={actionLogsPage <= 1}
                      className="flex items-center gap-1 px-3 py-1.5 text-xs font-medium rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 disabled:opacity-40 disabled:cursor-not-allowed transition-colors"
                    >
                      <ChevronLeftIcon className="w-3.5 h-3.5" />
                      Prev
                    </button>
                    <span className="text-xs text-gray-500 dark:text-gray-400">
                      Page {actionLogsPage} of {actionLogsTotalPages}
                    </span>
                    <button
                      onClick={() => setActionLogsPage(p => Math.min(actionLogsTotalPages, p + 1))}
                      disabled={actionLogsPage >= actionLogsTotalPages}
                      className="flex items-center gap-1 px-3 py-1.5 text-xs font-medium rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 disabled:opacity-40 disabled:cursor-not-allowed transition-colors"
                    >
                      Next
                      <ChevronRightIcon className="w-3.5 h-3.5" />
                    </button>
                  </div>
                )}
              </div>
            )}

            {/* About Us Section */}
            {currentMenuSection === 'about-us' && (
              <div className="overflow-y-auto flex-1 p-4 space-y-4">
                {/* Company Info */}
                <div>
                  <h4 className="font-semibold mb-2 text-sm text-amber-600 dark:text-amber-400">
                    NotaryPro Services
                  </h4>
                  <p className="text-xs leading-relaxed text-gray-700 dark:text-gray-300">
                    NotaryPro is your trusted partner for professional notarization services. Founded with a commitment to excellence, we provide fast, reliable, and accessible notarization for all your legal document needs.
                  </p>
                </div>

                {/* Company Details */}
                <div className="p-3 rounded-lg space-y-2 bg-gray-100 dark:bg-gray-800/50 border border-gray-300 dark:border-gray-700">
                  <div className="text-xs">
                    <p className="mb-1 text-amber-600 dark:text-amber-400"><strong>📍 Location:</strong></p>
                    <p className="ml-4 text-gray-700 dark:text-gray-300">San Francisco, California</p>
                  </div>
                  <div className="text-xs">
                    <p className="mb-1 text-amber-600 dark:text-amber-400"><strong>🚀 Founded:</strong></p>
                    <p className="ml-4 text-gray-700 dark:text-gray-300">January 2024</p>
                  </div>
                </div>

                {/* Our Mission */}
                <div>
                  <h4 className="font-semibold mb-2 text-sm text-amber-600 dark:text-amber-400">
                    Our Mission
                  </h4>
                  <p className="text-xs leading-relaxed text-gray-700 dark:text-gray-300">
                    To make notarization services accessible, convenient, and trustworthy for everyone. We believe in providing exceptional service with integrity and professionalism.
                  </p>
                </div>

                {/* What We Offer */}
                <div>
                  <h4 className="font-semibold mb-2 text-sm text-amber-600 dark:text-amber-400">
                    Services
                  </h4>
                  <ul className="space-y-1 text-xs text-gray-700 dark:text-gray-300">
                    <li className="flex items-start">
                      <span className="mr-2 text-amber-600 dark:text-amber-400">✓</span>
                      <span>Professional Notarization Services</span>
                    </li>
                    <li className="flex items-start">
                      <span className="mr-2 text-amber-600 dark:text-amber-400">✓</span>
                      <span>Document Verification & Witnessing</span>
                    </li>
                    <li className="flex items-start">
                      <span className="mr-2 text-amber-600 dark:text-amber-400">✓</span>
                      <span>Certified Notary Public Staff</span>
                    </li>
                    <li className="flex items-start">
                      <span className="mr-2 text-amber-600 dark:text-amber-400">✓</span>
                      <span>Flexible Scheduling & Mobile Service</span>
                    </li>
                  </ul>
                </div>

                {/* Why Choose Us */}
                <div>
                  <h4 className="font-semibold mb-2 text-sm text-amber-600 dark:text-amber-400">
                    Why Choose NotaryPro
                  </h4>
                  <ul className="space-y-1 text-xs text-gray-700 dark:text-gray-300">
                    <li className="flex items-start">
                      <span className="mr-2 text-green-600 dark:text-green-400">✓</span>
                      <span>Licensed & Insured Professionals</span>
                    </li>
                    <li className="flex items-start">
                      <span className="mr-2 text-green-600 dark:text-green-400">✓</span>
                      <span>Fast & Reliable Service</span>
                    </li>
                    <li className="flex items-start">
                      <span className="mr-2 text-green-600 dark:text-green-400">✓</span>
                      <span>Competitive Pricing</span>
                    </li>
                    <li className="flex items-start">
                      <span className="mr-2 text-green-600 dark:text-green-400">✓</span>
                      <span>24/7 Availability</span>
                    </li>
                  </ul>
                </div>




                {/* Contact & Support */}
                <div className="p-3 rounded-lg bg-gray-100 dark:bg-gray-800/50 border border-gray-300 dark:border-gray-700">
                  <h4 className="font-semibold mb-2 text-sm text-amber-600 dark:text-amber-400">
                    Get In Touch
                  </h4>
                  <div className="space-y-1 text-xs text-gray-700 dark:text-gray-300">
                    <p><strong>Email:</strong> support@notarypro.com</p>
                    <p><strong>Phone:</strong> 1-800-NOTARY-1</p>
                    <p><strong>Hours:</strong> 24/7 Service Available</p>
                  </div>
                </div>

                {/* Developers */}
                <div className="p-3 rounded-lg bg-amber-50 dark:bg-amber-900/10 border border-amber-200 dark:border-amber-500/20">
                  <h4 className="font-semibold mb-2 text-sm text-amber-600 dark:text-amber-400">
                    Development Team
                  </h4>
                  <div className="flex items-start gap-2 text-xs text-gray-700 dark:text-gray-300">
                    <span className="text-sm">🎓</span>
                    <p className="leading-relaxed">
                      Developed with pride by the students from <strong>Mindoro State University - Bongabong Campus</strong> as part of their academic pursuit of excellence.
                    </p>
                  </div>
                </div>

                {/* Version */}
                <div className="text-center pt-2 border-t border-gray-300 dark:border-gray-700 text-xs text-gray-500 dark:text-gray-500">
                  <p>Version 1.0.0 • © 2024 NotaryPro Services</p>
                </div>
              </div>
            )}

            {/* Terms & Conditions Section */}
            {currentMenuSection === 'terms' && (
              <div className="overflow-y-auto flex-1 p-4 space-y-4 text-sm leading-relaxed text-gray-700 dark:text-gray-300">
                <p className="text-xs text-gray-400 dark:text-gray-500">Last updated: March 9, 2026</p>

                <div>
                  <h4 className="text-sm font-semibold mt-3 mb-1 text-gray-900 dark:text-amber-300">1. Acceptance of Terms</h4>
                  <p>By accessing and using this appointment management system ("Service"), you agree to be bound by these Terms and Conditions. If you do not agree, please do not use the Service.</p>
                </div>

                <div>
                  <h4 className="text-sm font-semibold mt-3 mb-1 text-gray-900 dark:text-amber-300">2. Account Registration</h4>
                  <p>You must provide accurate, complete, and current information when creating an account. You are responsible for maintaining the confidentiality of your account credentials and for all activities that occur under your account. Notify us immediately of any unauthorized use.</p>
                </div>

                <div>
                  <h4 className="text-sm font-semibold mt-3 mb-1 text-gray-900 dark:text-amber-300">3. Use of Service</h4>
                  <p>You agree to use the Service only for lawful purposes and in accordance with these Terms. You shall not:</p>
                  <ul className="list-disc pl-5 space-y-1 mt-1">
                    <li>Use the Service for any fraudulent or unlawful purpose</li>
                    <li>Attempt to gain unauthorized access to any part of the Service</li>
                    <li>Interfere with or disrupt the Service or its servers</li>
                    <li>Upload malicious content or code</li>
                    <li>Impersonate another person or entity</li>
                  </ul>
                </div>

                <div>
                  <h4 className="text-sm font-semibold mt-3 mb-1 text-gray-900 dark:text-amber-300">4. Appointments & Bookings</h4>
                  <p>Appointment bookings are subject to availability. We reserve the right to cancel or reschedule appointments when necessary. Users are expected to attend booked appointments or cancel in advance. Repeated no-shows may result in account restrictions.</p>
                </div>

                <div>
                  <h4 className="text-sm font-semibold mt-3 mb-1 text-gray-900 dark:text-amber-300">5. Payments & Fees</h4>
                  <p>Applicable fees for services will be clearly displayed before confirmation. All payments are processed securely. Refund policies are subject to the specific service terms and applicable laws.</p>
                </div>

                <div>
                  <h4 className="text-sm font-semibold mt-3 mb-1 text-gray-900 dark:text-amber-300">6. Intellectual Property</h4>
                  <p>All content, features, and functionality of the Service are owned by us and are protected by copyright, trademark, and other intellectual property laws.</p>
                </div>

                <div>
                  <h4 className="text-sm font-semibold mt-3 mb-1 text-gray-900 dark:text-amber-300">7. Limitation of Liability</h4>
                  <p>The Service is provided "as is" without warranties of any kind. We shall not be liable for any indirect, incidental, special, or consequential damages arising from the use of the Service.</p>
                </div>

                <div>
                  <h4 className="text-sm font-semibold mt-3 mb-1 text-gray-900 dark:text-amber-300">8. Account Termination</h4>
                  <p>We reserve the right to suspend or terminate your account if you violate these Terms or engage in activity that may harm the Service or other users. You may also request account deletion by contacting support.</p>
                </div>

                <div>
                  <h4 className="text-sm font-semibold mt-3 mb-1 text-gray-900 dark:text-amber-300">9. Changes to Terms</h4>
                  <p>We may update these Terms from time to time. Continued use of the Service after changes constitutes acceptance of the revised Terms. We will notify users of significant changes via email or in-app notice.</p>
                </div>

                <div>
                  <h4 className="text-sm font-semibold mt-3 mb-1 text-gray-900 dark:text-amber-300">10. Contact</h4>
                  <p>If you have questions about these Terms, please contact us through the system's support features or the chatbot assistant.</p>
                </div>
              </div>
            )}

            {/* Privacy Policy Section */}
            {currentMenuSection === 'privacy' && (
              <div className="overflow-y-auto flex-1 p-4 space-y-4 text-sm leading-relaxed text-gray-700 dark:text-gray-300">
                <p className="text-xs text-gray-400 dark:text-gray-500">Last updated: March 9, 2026</p>

                <div>
                  <h4 className="text-sm font-semibold mt-3 mb-1 text-gray-900 dark:text-amber-300">1. Information We Collect</h4>
                  <p>We collect the following types of information:</p>
                  <ul className="list-disc pl-5 space-y-1 mt-1">
                    <li><strong>Personal information:</strong> name, email address, phone number, and address provided during registration</li>
                    <li><strong>Account information:</strong> username and encrypted password</li>
                    <li><strong>Appointment data:</strong> booking history, service preferences, and scheduling information</li>
                    <li><strong>Usage data:</strong> interactions with the chatbot, pages visited, and feature usage for service improvement</li>
                  </ul>
                </div>

                <div>
                  <h4 className="text-sm font-semibold mt-3 mb-1 text-gray-900 dark:text-amber-300">2. How We Use Your Information</h4>
                  <p>Your information is used to:</p>
                  <ul className="list-disc pl-5 space-y-1 mt-1">
                    <li>Provide and manage your account and appointments</li>
                    <li>Communicate with you regarding bookings, updates, and notifications</li>
                    <li>Improve our services, including AI chatbot responses</li>
                    <li>Ensure security and prevent fraud</li>
                    <li>Comply with legal obligations</li>
                  </ul>
                </div>

                <div>
                  <h4 className="text-sm font-semibold mt-3 mb-1 text-gray-900 dark:text-amber-300">3. Data Storage & Security</h4>
                  <p>Your data is stored securely using industry-standard encryption and access controls. We implement technical and organizational measures to protect your personal information from unauthorized access, alteration, or destruction.</p>
                </div>

                <div>
                  <h4 className="text-sm font-semibold mt-3 mb-1 text-gray-900 dark:text-amber-300">4. Data Sharing</h4>
                  <p>We do not sell your personal information. We may share data only:</p>
                  <ul className="list-disc pl-5 space-y-1 mt-1">
                    <li>With authorized staff who need it to provide services</li>
                    <li>When required by law or legal process</li>
                    <li>To protect the rights, safety, or property of our users or the public</li>
                  </ul>
                </div>

                <div>
                  <h4 className="text-sm font-semibold mt-3 mb-1 text-gray-900 dark:text-amber-300">5. Chatbot Conversations</h4>
                  <p>Conversations with the AI chatbot may be stored to improve service quality. Chat data is associated with your account and is not shared with third parties. You may clear your chat history at any time.</p>
                </div>

                <div>
                  <h4 className="text-sm font-semibold mt-3 mb-1 text-gray-900 dark:text-amber-300">6. Cookies & Local Storage</h4>
                  <p>We use local storage and cookies to maintain your session, remember preferences, and improve your experience. You can manage these through your browser settings.</p>
                </div>

                <div>
                  <h4 className="text-sm font-semibold mt-3 mb-1 text-gray-900 dark:text-amber-300">7. Your Rights</h4>
                  <p>You have the right to:</p>
                  <ul className="list-disc pl-5 space-y-1 mt-1">
                    <li>Access your personal data</li>
                    <li>Request correction of inaccurate data</li>
                    <li>Request deletion of your account and data</li>
                    <li>Withdraw consent for non-essential data processing</li>
                  </ul>
                </div>

                <div>
                  <h4 className="text-sm font-semibold mt-3 mb-1 text-gray-900 dark:text-amber-300">8. Data Retention</h4>
                  <p>We retain your personal information for as long as your account is active or as needed to provide services. After account deletion, data may be retained in anonymized form for analytics purposes.</p>
                </div>

                <div>
                  <h4 className="text-sm font-semibold mt-3 mb-1 text-gray-900 dark:text-amber-300">9. Children's Privacy</h4>
                  <p>The Service is not intended for users under 13 years of age. We do not knowingly collect personal information from children.</p>
                </div>

                <div>
                  <h4 className="text-sm font-semibold mt-3 mb-1 text-gray-900 dark:text-amber-300">10. Changes to This Policy</h4>
                  <p>We may update this Privacy Policy periodically. We will notify you of material changes via email or in-app notification.</p>
                </div>

                <div>
                  <h4 className="text-sm font-semibold mt-3 mb-1 text-gray-900 dark:text-amber-300">11. Contact</h4>
                  <p>For privacy-related inquiries, please contact us through the system's support features or the chatbot assistant.</p>
                </div>
              </div>
            )}
          </>
        )}
      </div>

      {/* Delete Account Confirmation Modal */}
      {showDeleteModal && (
        <div className="fixed inset-0 bg-black/60 z-50 flex items-center justify-center p-4" onClick={() => setShowDeleteModal(false)}>
          <div 
            className="bg-white dark:bg-gray-800 rounded-xl shadow-2xl w-full max-w-md mx-auto overflow-hidden"
            onClick={e => e.stopPropagation()}
          >
            {/* Modal Header */}
            <div className="bg-red-50 dark:bg-red-900/30 px-6 py-4 border-b border-red-200 dark:border-red-800/50">
              <div className="flex items-center gap-3">
                <div className="w-10 h-10 rounded-full bg-red-100 dark:bg-red-900/50 flex items-center justify-center">
                  <ExclamationTriangleIcon className="w-6 h-6 text-red-600 dark:text-red-400" />
                </div>
                <div>
                  <h3 className="text-lg font-semibold text-red-800 dark:text-red-300">Delete Account</h3>
                  <p className="text-xs text-red-600 dark:text-red-400">This action cannot be undone</p>
                </div>
              </div>
            </div>

            {/* Modal Body */}
            <div className="px-6 py-5 space-y-4">
              <div className="p-3 rounded-lg bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800/40">
                <p className="text-sm text-red-700 dark:text-red-300">
                  Deleting your account will permanently remove all your data, appointments, messages, and associated information. This cannot be recovered.
                </p>
              </div>

              {/* Type "confirm" */}
              <div>
                <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                  Type <span className="font-bold text-red-600 dark:text-red-400">"confirm"</span> to proceed
                </label>
                <input
                  type="text"
                  value={deleteConfirmText}
                  onChange={e => setDeleteConfirmText(e.target.value)}
                  placeholder='Type "confirm" here'
                  autoComplete="off"
                  className="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-sm focus:ring-2 focus:ring-red-500 focus:border-red-500 outline-none"
                />
              </div>

              {/* Error message */}
              {deleteError && (
                <div className="p-2.5 rounded-lg bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800/40">
                  <p className="text-sm text-red-600 dark:text-red-400">{deleteError}</p>
                </div>
              )}
            </div>

            {/* Modal Footer */}
            <div className="px-6 py-4 border-t border-gray-200 dark:border-gray-700 flex gap-3">
              <button
                onClick={() => setShowDeleteModal(false)}
                className="flex-1 px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 text-sm font-medium hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors"
              >
                Cancel
              </button>
              <button
                onClick={handleDeleteAccount}
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
};

export default ProfilePage;
