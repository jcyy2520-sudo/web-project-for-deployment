import { useState } from 'react';
import { ClockIcon, CalendarDaysIcon } from '@heroicons/react/24/outline';
import TimeSlotCapacityManagement from './TimeSlotCapacityManagement';
import BlackoutDateManagement from './BlackoutDateManagement';

/**
 * Calendar Management Component
 * Manages capacity and blackout dates with tabbed interface
 */
const CalendarManagement = ({ isDarkMode = true }) => {
  const [activeTab, setActiveTab] = useState('capacity');

  return (
    <div className="space-y-4">
      <div className="flex justify-between items-center mb-4">
        <div>
          <h2 className={`text-lg font-bold ${isDarkMode ? 'text-amber-50' : 'text-gray-900'}`}>
            Calendar Management
          </h2>
          <p className={`text-sm ${isDarkMode ? 'text-gray-400' : 'text-gray-600'}`}>
            Manage availability, capacity, and blackout dates
          </p>
        </div>
      </div>

      {/* Tab Navigation */}
      <div className={`rounded-lg shadow border overflow-hidden ${
        isDarkMode ? 'bg-gray-900 border-amber-500/20' : 'bg-white border-gray-200'
      }`}>
        <div className={`flex border-b ${isDarkMode ? 'border-gray-700' : 'border-gray-200'}`}>
          <button
            onClick={() => setActiveTab('capacity')}
            className={`flex-1 px-4 py-3 text-sm font-medium transition-all flex items-center justify-center gap-2 ${
              activeTab === 'capacity'
                ? isDarkMode
                  ? 'border-b-2 border-amber-400 text-amber-50 bg-amber-500/5'
                  : 'border-b-2 border-black text-gray-900 bg-gray-50'
                : isDarkMode
                ? 'text-gray-400 hover:text-gray-300'
                : 'text-gray-600 hover:text-gray-900'
            }`}
          >
            <ClockIcon className="h-4 w-4" />
            Time Slot Capacity
          </button>
          <button
            onClick={() => setActiveTab('blackout')}
            className={`flex-1 px-4 py-3 text-sm font-medium transition-all flex items-center justify-center gap-2 ${
              activeTab === 'blackout'
                ? isDarkMode
                  ? 'border-b-2 border-amber-400 text-amber-50 bg-amber-500/5'
                  : 'border-b-2 border-black text-gray-900 bg-gray-50'
                : isDarkMode
                ? 'text-gray-400 hover:text-gray-300'
                : 'text-gray-600 hover:text-gray-900'
            }`}
          >
            <CalendarDaysIcon className="h-4 w-4" />
            Unavailable Dates & Times
          </button>
        </div>

        {/* Tab Content */}
        <div className="p-4">
          {activeTab === 'capacity' && (
            <TimeSlotCapacityManagement isDarkMode={isDarkMode} />
          )}
          {activeTab === 'blackout' && (
            <BlackoutDateManagement isDarkMode={isDarkMode} />
          )}
        </div>
      </div>
    </div>
  );
};

export default CalendarManagement;
