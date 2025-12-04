import { useState, useEffect, useRef } from 'react';
import axios from 'axios';
import { useApi } from '../../hooks/useApi';
import {
  ExclamationTriangleIcon,
  CheckCircleIcon,
  ClockIcon,
  ArrowPathIcon
} from '@heroicons/react/24/outline';
import LoadingSpinner from '../LoadingSpinner';

/**
 * Time Slot Capacity Management Component
 * Simplified interface to set max appointments per hour
 */
const TimeSlotCapacityManagement = ({ isDarkMode = true }) => {
  const { callApi, loading } = useApi();
  const [mode, setMode] = useState('apply-all'); // 'apply-all' or 'customize'
  const [globalCapacity, setGlobalCapacity] = useState(3);
  const [customCapacities, setCustomCapacities] = useState({});
  const [error, setError] = useState(null);
  const [success, setSuccess] = useState(null);
  const saveTimeoutRef = useRef(null);
  const pendingSavesRef = useRef({});

  // Present hours instead of 30-min slots to simplify the UI.
  // Each hour represents two 30-min slots (e.g., 08:00 -> applies to 08:00-08:30 and 08:30-09:00)
  const hours = [
    '08:00','09:00','10:00','11:00','13:00','14:00','15:00','16:00'
  ];

  // Convert 24-hour time to 12-hour format
  const formatTimeAmPm = (timeStr) => {
    const [hours, minutes] = timeStr.split(':');
    const hour = parseInt(hours);
    const ampm = hour >= 12 ? 'PM' : 'AM';
    const displayHour = hour > 12 ? hour - 12 : (hour === 0 ? 12 : hour);
    return `${displayHour}:${minutes} ${ampm}`;
  };

  useEffect(() => {
    loadCapacities();
  }, []);

  const loadCapacities = async () => {
    const result = await callApi(() =>
      axios.get('/api/admin/slot-capacities')
    );

    if (result.success && result.data.data) {
      const capacities = result.data.data;
      // Build custom capacities map
      const customMap = {};
      capacities.forEach(cap => {
        if (cap.start_time && cap.end_time) {
          // Map by the hour (start time) for display purposes
          // e.g., "08:00" to represent both 08:00-08:30 and 08:30-09:00
          if (cap.start_time.endsWith(':00')) {
            customMap[cap.start_time] = cap.max_appointments_per_slot;
          }
        }
      });
      setCustomCapacities(customMap);
      
      // Also set the global capacity to the first hour's capacity if all are the same
      if (Object.keys(customMap).length > 0) {
        const firstCapacity = Object.values(customMap)[0];
        const allSame = Object.values(customMap).every(cap => cap === firstCapacity);
        if (allSame) {
          setGlobalCapacity(firstCapacity);
        }
      }
    }
  };

  const handleApplyToAll = async () => {
    if (globalCapacity < 1 || globalCapacity > 20) {
      setError('Capacity must be between 1 and 20');
      return;
    }

    setError(null);
    setSuccess(null);

    try {
      // Call the backend endpoint to apply to all slots at once
      const response = await axios.post('/api/admin/slot-capacities/apply-all', {
        max_appointments_per_slot: globalCapacity
      });

      if (response.data.success) {
        setSuccess(`All time slots updated to ${globalCapacity} max appointments! (${response.data.data.total} slots configured)`);
        
        // Update customCapacities state to reflect the change BEFORE switching modes
        const updatedCapacities = {};
        hours.forEach(hour => {
          updatedCapacities[hour] = globalCapacity;
        });
        setCustomCapacities(updatedCapacities);
        
        // notify clients
        window.dispatchEvent(new CustomEvent('slotCapacitiesChanged'));
        
        // Switch to customize mode to show the updated values immediately
        setTimeout(() => {
          setMode('customize');
        }, 200);
      } else {
        setError(response.data.message || 'Failed to apply capacity to all slots');
      }
    } catch (err) {
      setError('An error occurred: ' + (err.response?.data?.message || err.message || 'Unknown error'));
    }
  };
  // Save capacities for an hour (applies to both half-hour slots in that hour)
  const handleCustomizeHour = (hourStart, capacity) => {
    if (capacity < 1 || capacity > 20) {
      setError('Capacity must be between 1 and 20');
      return;
    }

    setError(null);
    setSuccess(null);

    // Update local state immediately
    setCustomCapacities(prev => ({ ...prev, [hourStart]: capacity }));

    // Debounce actual API saves so rapid typing won't spam the server
    pendingSavesRef.current[hourStart] = capacity;
    if (saveTimeoutRef.current) clearTimeout(saveTimeoutRef.current);

    saveTimeoutRef.current = setTimeout(async () => {
      const saves = { ...pendingSavesRef.current };
      pendingSavesRef.current = {};

      try {
        // For each hour, post two half-hour entries
        for (const [h, cap] of Object.entries(saves)) {
          const [hh, mm] = h.split(':');

          // First half slot: hh:00 - hh:30
          await axios.post('/api/admin/slot-capacities', {
            start_time: `${hh}:00`,
            end_time: `${hh}:30`,
            day_of_week: null,
            max_appointments_per_slot: cap
          });

          // Second half slot: hh:30 - (hh+1):00
          const nextHour = String(Number(hh) + 1).padStart(2, '0');
          await axios.post('/api/admin/slot-capacities', {
            start_time: `${hh}:30`,
            end_time: `${nextHour}:00`,
            day_of_week: null,
            max_appointments_per_slot: cap
          });
        }

        setSuccess('Updated selected hours');
        await loadCapacities();
        // notify clients that capacities changed
        window.dispatchEvent(new CustomEvent('slotCapacitiesChanged'));
      } catch (err) {
        setError('An error occurred: ' + (err.response?.data?.message || err.message || 'Unknown error'));
      }
    }, 700);
  };

  return (
    <div className="space-y-4 w-full">
      {/* Messages */}
      {error && (
        <div className={`p-3 rounded-lg border flex items-start gap-2 ${isDarkMode ? 'bg-red-500/10 border-red-500/30 text-red-400' : 'bg-red-50 border-red-200 text-red-700'}`}>
          <ExclamationTriangleIcon className="h-4 w-4 mt-0.5 flex-shrink-0" />
          <p className="text-sm">{error}</p>
        </div>
      )}

      {success && (
        <div className={`p-3 rounded-lg border flex items-start gap-2 ${isDarkMode ? 'bg-green-500/10 border-green-500/30 text-green-400' : 'bg-green-50 border-green-200 text-green-700'}`}>
          <CheckCircleIcon className="h-4 w-4 mt-0.5 flex-shrink-0" />
          <p className="text-sm">{success}</p>
        </div>
      )}

      {/* Mode Toggle */}
      <div className={`rounded-lg border p-4 ${isDarkMode ? 'bg-gray-800 border-gray-700' : 'bg-white border-gray-200'}`}>
        <p className={`text-sm font-medium mb-3 ${isDarkMode ? 'text-gray-300' : 'text-gray-700'}`}>
          Choose your approach:
        </p>
        <div className="flex gap-3">
          <button
            onClick={() => setMode('apply-all')}
            className={`flex-1 px-4 py-3 rounded-lg border-2 transition-all font-medium text-sm ${
              mode === 'apply-all'
                ? isDarkMode
                  ? 'bg-amber-500/20 border-amber-500 text-amber-50'
                  : 'bg-amber-50 border-amber-600 text-amber-900'
                : isDarkMode
                ? 'bg-gray-700 border-gray-600 text-gray-300 hover:border-gray-500'
                : 'bg-gray-100 border-gray-300 text-gray-700 hover:border-gray-400'
            }`}
          >
            Apply to All Hours
          </button>
          <button
            onClick={() => setMode('customize')}
            className={`flex-1 px-4 py-3 rounded-lg border-2 transition-all font-medium text-sm ${
              mode === 'customize'
                ? isDarkMode
                  ? 'bg-amber-500/20 border-amber-500 text-amber-50'
                  : 'bg-amber-50 border-amber-600 text-amber-900'
                : isDarkMode
                ? 'bg-gray-700 border-gray-600 text-gray-300 hover:border-gray-500'
                : 'bg-gray-100 border-gray-300 text-gray-700 hover:border-gray-400'
            }`}
          >
            Customize Hours
          </button>
        </div>
      </div>

      {/* Apply to All Mode */}
      {mode === 'apply-all' && (
        <div className={`rounded-lg border p-6 ${isDarkMode ? 'bg-gray-800 border-gray-700' : 'bg-white border-gray-200'}`}>
          <div className="mb-4">
            <h3 className={`font-semibold mb-2 ${isDarkMode ? 'text-amber-50' : 'text-gray-900'}`}>
              Set capacity for all time slots
            </h3>
            <p className={`text-sm ${isDarkMode ? 'text-gray-400' : 'text-gray-600'}`}>
              This will apply the same maximum number of appointments to all 16 available time slots (8 AM - 5 PM, excluding lunch)
            </p>
          </div>

          <div className="space-y-4">
            <div>
              <label className={`block text-sm font-medium mb-2 ${isDarkMode ? 'text-gray-300' : 'text-gray-700'}`}>
                Max Appointments Per Time Slot
              </label>
              <input
                type="number"
                min="1"
                max="20"
                value={globalCapacity}
                onChange={(e) => setGlobalCapacity(parseInt(e.target.value) || 3)}
                className={`w-32 px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-500 text-lg font-semibold ${
                  isDarkMode
                    ? 'bg-gray-700 border-gray-600 text-white'
                    : 'bg-white border-gray-300 text-gray-900'
                }`}
              />
              <p className={`text-xs mt-2 ${isDarkMode ? 'text-gray-400' : 'text-gray-600'}`}>
                Allows up to {globalCapacity} clients per 30-minute slot
              </p>
            </div>

            <button
              onClick={handleApplyToAll}
              disabled={loading}
              className="w-full px-4 py-3 bg-amber-600 text-white rounded-lg font-medium hover:bg-amber-700 transition-colors disabled:opacity-50 flex items-center justify-center gap-2"
            >
              {loading && <ArrowPathIcon className="h-4 w-4 animate-spin" />}
              Apply to All {globalCapacity > 0 && `(${16} slots)`}
            </button>
          </div>
        </div>
      )}

      {/* Customize Mode */}
      {mode === 'customize' && (
        <div className={`rounded-lg border p-4 ${isDarkMode ? 'bg-gray-800 border-gray-700' : 'bg-white border-gray-200'}`}>
          <h3 className={`font-semibold mb-4 ${isDarkMode ? 'text-amber-50' : 'text-gray-900'}`}>
            Customize capacity for specific time slots
          </h3>

          {loading && !Object.keys(customCapacities).length ? (
            <div className="flex justify-center py-6">
              <LoadingSpinner size="sm" />
            </div>
          ) : (
            <div className="space-y-6">
              <div>
                <h4 className={`text-sm font-semibold mb-3 ${isDarkMode ? 'text-gray-300' : 'text-gray-700'}`}>
                  ⏰ Working Hours (set capacity per hour)
                </h4>
                <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3">
                  {hours.map(h => (
                    <div key={h} className={`flex items-center justify-between p-3 rounded-lg border ${isDarkMode ? 'bg-gray-700 border-gray-600' : 'bg-gray-50 border-gray-200'}`}>
                      <label className={`text-sm font-medium ${isDarkMode ? 'text-gray-300' : 'text-gray-700'}`}>{formatTimeAmPm(h)}</label>
                      <input
                        type="number"
                        min="1"
                        max="20"
                        value={customCapacities[h] || 3}
                        onChange={(e) => handleCustomizeHour(h, parseInt(e.target.value) || 3)}
                        className={`w-16 px-2 py-1 border rounded text-center font-semibold focus:outline-none focus:ring-2 focus:ring-amber-500 ${isDarkMode ? 'bg-gray-600 border-gray-500 text-white' : 'bg-white border-gray-300 text-gray-900'}`}
                      />
                    </div>
                  ))}
                </div>

                <p className={`text-xs mt-3 ${isDarkMode ? 'text-gray-400' : 'text-gray-600'}`}>
                  💡 Change any value above and it will be saved automatically (applies to both 30-min slots within the hour)
                </p>
              </div>
            </div>
          )}
        </div>
      )}
    </div>
  );
};

export default TimeSlotCapacityManagement;
