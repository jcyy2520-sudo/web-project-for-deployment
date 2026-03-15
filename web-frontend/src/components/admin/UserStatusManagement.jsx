import { useState } from 'react';
import { UserMinusIcon, NoSymbolIcon } from '@heroicons/react/24/outline';
import AdminBlockedUsers from './AdminBlockedUsers';

/**
 * User Status Management Component
 * Tabbed interface for managing deactivated and blocked user accounts
 */
const UserStatusManagement = ({ isDarkMode = true, deactivatedContent, onDataChange }) => {
  const [activeTab, setActiveTab] = useState('deactivated');

  const tabs = [
    {
      key: 'deactivated',
      label: 'Deactivated Accounts',
      icon: UserMinusIcon,
      component: deactivatedContent
    },
    {
      key: 'blocked',
      label: 'Blocked Users',
      icon: NoSymbolIcon,
      component: <AdminBlockedUsers isDarkMode={isDarkMode} onDataChange={onDataChange} />
    }
  ];

  return (
    <div className="space-y-6">
      {/* Tab Navigation */}
      <div className={`rounded-lg shadow border overflow-hidden ${
        isDarkMode ? 'bg-gray-900 border-amber-500/20' : 'bg-white border-gray-200'
      }`}>
        <div className={`flex border-b ${isDarkMode ? 'border-gray-700' : 'border-gray-200'}`}>
          {tabs.map((tab) => {
            const IconComponent = tab.icon;
            const isActive = activeTab === tab.key;
            return (
              <button
                key={tab.key}
                onClick={() => setActiveTab(tab.key)}
                className={`flex-1 px-6 py-4 text-sm font-semibold transition-all duration-300 flex items-center justify-center gap-2 relative group ${
                  isActive
                    ? isDarkMode
                      ? 'text-amber-50'
                      : 'text-amber-900'
                    : isDarkMode
                    ? 'text-gray-400 hover:text-gray-300'
                    : 'text-gray-600 hover:text-gray-900'
                }`}
              >
                <IconComponent className="h-5 w-5 transition-transform group-hover:scale-110" />
                {tab.label}
                {isActive && (
                  <div className={`absolute bottom-0 left-0 right-0 h-1 ${
                    isDarkMode ? 'bg-gradient-to-r from-amber-400 to-amber-500' : 'bg-gradient-to-r from-amber-500 to-amber-600'
                  }`}></div>
                )}
              </button>
            );
          })}
        </div>

        {/* Tab Content */}
        <div className={`p-6 ${isDarkMode ? 'bg-gray-900' : 'bg-white'}`}>
          {tabs.find(tab => tab.key === activeTab)?.component}
        </div>
      </div>
    </div>
  );
};

export default UserStatusManagement;
