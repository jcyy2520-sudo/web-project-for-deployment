import { useState } from 'react';
import { CalendarDaysIcon, CogIcon, ChatBubbleBottomCenterTextIcon, ChevronRightIcon } from '@heroicons/react/24/outline';
import CalendarManagement from './CalendarManagement';
import AppointmentSettingsManagement from './AppointmentSettingsManagement';
import AdminFeedbackSettings from './AdminFeedbackSettings';

/**
 * System Configuration Component
 * Sidebar layout for Calendar, Appointment, and Feedback settings
 */
const AdminSettings = ({ isDarkMode = true }) => {
  const [activeTab, setActiveTab] = useState('calendar');

  const tabs = [
    {
      key: 'calendar',
      label: 'Calendar Settings',
      description: 'Manage blackout dates & working hours',
      icon: CalendarDaysIcon,
      component: <CalendarManagement isDarkMode={isDarkMode} />
    },
    {
      key: 'appointments',
      label: 'Appointment Settings',
      description: 'Slot capacity & booking limits',
      icon: CogIcon,
      component: <AppointmentSettingsManagement isDarkMode={isDarkMode} />
    },
    {
      key: 'feedback',
      label: 'Feedback Settings',
      description: 'Configure feedback & reviews',
      icon: ChatBubbleBottomCenterTextIcon,
      component: <AdminFeedbackSettings isDarkMode={isDarkMode} />
    }
  ];

  const activeTabData = tabs.find(tab => tab.key === activeTab);

  return (
    <div className="space-y-4">
      <div>
        <h2 className={`text-lg font-bold ${isDarkMode ? 'text-amber-50' : 'text-gray-900'}`}>
          Operation
        </h2>
        <p className={`text-sm ${isDarkMode ? 'text-gray-400' : 'text-gray-600'}`}>
          Configure calendar, appointments, and feedback settings
        </p>
      </div>

      {/* Sidebar + Content Layout */}
      <div className="flex gap-4 min-h-[600px]">
        {/* Sidebar */}
        <div className={`w-56 flex-shrink-0 rounded-xl border overflow-hidden ${
          isDarkMode ? 'bg-gray-900/80 border-amber-500/20' : 'bg-white border-gray-200'
        }`}>
          <nav className="flex flex-col py-2">
            {tabs.map((tab) => {
              const IconComponent = tab.icon;
              const isActive = activeTab === tab.key;
              return (
                <button
                  key={tab.key}
                  onClick={() => setActiveTab(tab.key)}
                  className={`w-full px-4 py-3 text-left transition-all duration-200 flex items-center gap-3 relative group ${
                    isActive
                      ? isDarkMode
                        ? 'bg-amber-500/10 text-amber-50'
                        : 'bg-amber-50 text-amber-900'
                      : isDarkMode
                      ? 'text-gray-400 hover:text-gray-200 hover:bg-gray-800/60'
                      : 'text-gray-600 hover:text-gray-900 hover:bg-gray-50'
                  }`}
                >
                  {isActive && (
                    <div className={`absolute left-0 top-1.5 bottom-1.5 w-[3px] rounded-r-full ${
                      isDarkMode ? 'bg-amber-400' : 'bg-amber-500'
                    }`} />
                  )}
                  <IconComponent className={`h-5 w-5 flex-shrink-0 transition-transform group-hover:scale-110 ${
                    isActive
                      ? isDarkMode ? 'text-amber-400' : 'text-amber-600'
                      : ''
                  }`} />
                  <div className="min-w-0 flex-1">
                    <p className={`text-sm font-semibold truncate ${
                      isActive ? '' : 'font-medium'
                    }`}>{tab.label}</p>
                    <p className={`text-[10px] truncate mt-0.5 ${
                      isDarkMode ? 'text-gray-500' : 'text-gray-400'
                    }`}>{tab.description}</p>
                  </div>
                  {isActive && (
                    <ChevronRightIcon className={`h-3.5 w-3.5 flex-shrink-0 ${
                      isDarkMode ? 'text-amber-400/60' : 'text-amber-500/60'
                    }`} />
                  )}
                </button>
              );
            })}
          </nav>
        </div>

        {/* Content */}
        <div className={`flex-1 min-w-0 rounded-xl border overflow-hidden ${
          isDarkMode ? 'bg-gray-900/80 border-amber-500/20' : 'bg-white border-gray-200'
        }`}>
          <div className={`px-6 py-4 border-b ${
            isDarkMode ? 'border-gray-800' : 'border-gray-100'
          }`}>
            <div className="flex items-center gap-2">
              {activeTabData && <activeTabData.icon className={`h-5 w-5 ${isDarkMode ? 'text-amber-400' : 'text-amber-600'}`} />}
              <h3 className={`text-sm font-bold ${isDarkMode ? 'text-amber-50' : 'text-gray-900'}`}>
                {activeTabData?.label}
              </h3>
            </div>
          </div>
          <div className="p-6">
            {activeTabData?.component}
          </div>
        </div>
      </div>
    </div>
  );
};

export default AdminSettings;
