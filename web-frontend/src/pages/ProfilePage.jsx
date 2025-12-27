
import React, { useState, useEffect } from 'react';
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
  ChevronDownIcon
} from '@heroicons/react/24/outline';


const ProfilePage = ({ onBack, onTabChange, onLogout }) => {
  const { user, logout } = useAuth();
  const { isDarkMode, setIsDarkMode } = useTheme(); // Use ThemeContext
  const initials = (user?.first_name?.[0] || '') + (user?.last_name?.[0] || '');
  
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
    // If it's a sub-section within profile (refunds, action-logs, profile), keep the modal open and show back button
    if (tabName === 'refunds' || tabName === 'action-logs' || tabName === 'profile') {
      setCurrentMenuSection(tabName);
    } else {
      // For other tabs, close the modal
      if (onTabChange) {
        onTabChange(tabName);
      }
    }
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
          action: () => {},
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
            {/* Profile Card */}
            <div className="px-4 py-6">
              <div className="flex items-center gap-4">
                <div className="w-16 h-16 rounded-full bg-gradient-to-br from-amber-400 to-amber-600 flex items-center justify-center text-white text-2xl font-bold border-3 border-amber-400 flex-shrink-0">
                  {initials || 'U'}
                </div>
                <div className="flex-1 min-w-0">
                  <p className="font-semibold text-base text-gray-900 dark:text-white truncate">
                    {user?.first_name} {user?.last_name}
                  </p>
                  <p className="text-gray-500 dark:text-gray-400 text-sm truncate">
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
            {/* Sub-section placeholders - Refunds and Action Logs will be opened in Dashboard */}
            {(currentMenuSection === 'refunds' || currentMenuSection === 'action-logs') && (
              <div className="flex items-center justify-center h-full text-center">
                <div className="p-6">
                  <p className="text-gray-600 dark:text-gray-300 mb-4">
                    {currentMenuSection === 'refunds' && 'Refunds section will open in a new view'}
                    {currentMenuSection === 'action-logs' && 'Action Logs section will open in a new view'}
                  </p>
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
