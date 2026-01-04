
import React, { useState, useEffect } from 'react';
import axios from 'axios';
import { useAuth } from '../context/AuthContext';
import { useTheme } from '../context/ThemeContext';
import ProfilePictureUpload from '../components/ProfilePictureUpload';
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
  XCircleIcon
} from '@heroicons/react/24/outline';


const ProfilePage = ({ onBack, onTabChange, onLogout }) => {
  const { user, logout } = useAuth();
  const { isDarkMode, setIsDarkMode } = useTheme(); // Use ThemeContext
  const initials = (user?.first_name?.[0] || '') + (user?.last_name?.[0] || '');
  
  // Profile picture state
  const [profilePicture, setProfilePicture] = useState(user?.profile_picture ? `${window.location.origin}/storage/${user.profile_picture}` : null);
  
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
  
  // Action logs state
  const [actionLogs, setActionLogs] = useState([]);
  const [actionLogsLoading, setActionLogsLoading] = useState(false);
  const [actionLogsError, setActionLogsError] = useState('');
  
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
  
  // Load action logs when section is opened
  useEffect(() => {
    if (currentMenuSection === 'action-logs') {
      loadActionLogs();
    }
  }, [currentMenuSection]);
  
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
      const response = await axios.get('/api/action-logs/my/logs', { params: { per_page: 100 } });
      setActionLogs(response.data.data || response.data || []);
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
      section: 'Preferences',
      items: [
        {
          icon: InformationCircleIcon,
          label: 'About Us',
          action: () => handleNavToSection('about-us'),
          color: 'text-cyan-500'
        },
        {
          icon: AdjustmentsHorizontalIcon,
          label: 'Theme',
          isExpandable: true,
          color: 'text-pink-500',
          expanded: showThemeMenu,
          onToggleExpand: () => setShowThemeMenu(!showThemeMenu)
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
                {/* Profile Picture Upload Component */}
                <ProfilePictureUpload
                  currentImage={profilePicture}
                  user={user}
                  onUploadSuccess={(imageUrl) => {
                    setProfilePicture(imageUrl);
                  }}
                  onDeleteSuccess={() => {
                    setProfilePicture(null);
                  }}
                />
                
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
                      <div key={idx} className="p-4 rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50">
                        <div className="flex items-start justify-between mb-2">
                          <div className="flex-1">
                            <p className="font-semibold text-gray-900 dark:text-white text-sm">
                              ${refund.amount || 0}
                            </p>
                            <p className="text-xs text-gray-600 dark:text-gray-400 mt-1">
                              {refund.service_name || 'Service'}
                            </p>
                          </div>
                          <div className="flex items-center">
                            {refund.status === 'approved' && <CheckCircleIcon className="w-5 h-5 text-green-500" />}
                            {refund.status === 'pending' && <ExclamationTriangleIcon className="w-5 h-5 text-yellow-500" />}
                            {refund.status === 'rejected' && <XCircleIcon className="w-5 h-5 text-red-500" />}
                          </div>
                        </div>
                        <p className="text-xs text-gray-500 dark:text-gray-400">
                          Status: <span className="font-medium capitalize">{refund.status}</span>
                        </p>
                        {refund.reason && (
                          <p className="text-xs text-gray-600 dark:text-gray-300 mt-2">
                            {refund.reason}
                          </p>
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
                {actionLogsLoading && (
                  <div className="flex items-center justify-center h-full">
                    <div className="text-center">
                      <div className="w-8 h-8 rounded-full border-4 border-blue-200 border-t-blue-500 animate-spin mx-auto mb-4"></div>
                      <p className="text-gray-600 dark:text-gray-300">Loading action logs...</p>
                    </div>
                  </div>
                )}
                
                {!actionLogsLoading && actionLogsError && (
                  <div className="flex items-center justify-center h-full">
                    <div className="text-center">
                      <XCircleIcon className="w-12 h-12 text-red-500 mx-auto mb-4" />
                      <p className="text-red-600 dark:text-red-400">{actionLogsError}</p>
                    </div>
                  </div>
                )}
                
                {!actionLogsLoading && !actionLogsError && actionLogs.length === 0 && (
                  <div className="flex items-center justify-center h-full">
                    <div className="text-center">
                      <ClockIcon className="w-12 h-12 text-gray-300 dark:text-gray-600 mx-auto mb-4" />
                      <p className="text-gray-600 dark:text-gray-300">No action logs yet</p>
                    </div>
                  </div>
                )}
                
                {!actionLogsLoading && !actionLogsError && actionLogs.length > 0 && (
                  <div className="space-y-2 p-4">
                    {actionLogs.map((log, idx) => (
                      <div key={idx} className="p-3 rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50">
                        <div className="flex items-start justify-between mb-1">
                          <p className="font-semibold text-gray-900 dark:text-white text-xs capitalize">
                            {log.action || 'Action'}
                          </p>
                          <p className="text-xs text-gray-500 dark:text-gray-400">
                            {log.created_at ? new Date(log.created_at).toLocaleDateString() : ''}
                          </p>
                        </div>
                        {log.description && (
                          <p className="text-xs text-gray-600 dark:text-gray-400">
                            {log.description}
                          </p>
                        )}
                      </div>
                    ))}
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

                {/* Developer & Team */}
                <div className="p-3 rounded-lg bg-gray-100 dark:bg-gray-800/50 border border-gray-300 dark:border-gray-700">
                  <h4 className="font-semibold mb-2 text-sm text-amber-600 dark:text-amber-400">
                    👨‍💻 Development Team
                  </h4>
                  <div className="text-xs text-gray-700 dark:text-gray-300">
                    <p><strong>Lead Developer:</strong></p>
                    <p className="text-amber-600 dark:text-amber-400 ml-4">John Christian Fajutagana</p>
                    <p className="mt-2 text-xs text-gray-600 dark:text-gray-400">
                      Full Stack Developer specializing in modern web technologies and professional services platforms.
                    </p>
                  </div>
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

                {/* Version */}
                <div className="text-center pt-2 border-t border-gray-300 dark:border-gray-700 text-xs text-gray-500 dark:text-gray-500">
                  <p>Version 1.0.0 • © 2024 NotaryPro Services</p>
                </div>
              </div>
            )}
          </>
        )}
      </div>
    </div>
  );
};

export default ProfilePage;
