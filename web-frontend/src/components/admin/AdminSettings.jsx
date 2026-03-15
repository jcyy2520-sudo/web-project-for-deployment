import { useState } from 'react';
import { CalendarDaysIcon, CogIcon, ChatBubbleBottomCenterTextIcon } from '@heroicons/react/24/outline';
import CalendarManagement from './CalendarManagement';
import AppointmentSettingsManagement from './AppointmentSettingsManagement';
import AdminFeedbackSettings from './AdminFeedbackSettings';

/**
 * System Configuration Component
 * Tabbed interface for Calendar, Appointment, and Feedback settings
 */
const AdminSettings = ({ isDarkMode = true }) => {
  const [activeTab, setActiveTab] = useState('calendar');

  const tabs = [
    {
      key: 'calendar',
      label: 'Calendar Settings',
      icon: CalendarDaysIcon,
      component: <CalendarManagement isDarkMode={isDarkMode} />
    },
    {
      key: 'appointments',
      label: 'Appointment Settings',
      icon: CogIcon,
      component: <AppointmentSettingsManagement isDarkMode={isDarkMode} />
    },
    {
      key: 'feedback',
      label: 'Feedback Settings',
      icon: ChatBubbleBottomCenterTextIcon,
      component: <AdminFeedbackSettings isDarkMode={isDarkMode} />
    }
  ];

  return (
    <div className="space-y-6">
      <div>
        <h2 className={`text-lg font-bold ${isDarkMode ? 'text-amber-50' : 'text-gray-900'}`}>
          Operation
        </h2>
        <p className={`text-sm ${isDarkMode ? 'text-gray-400' : 'text-gray-600'}`}>
          Configure calendar, appointments, and feedback settings
        </p>
      </div>

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

export default AdminSettings;
