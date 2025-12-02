import { useState, useEffect } from 'react';
import axios from 'axios';
import { useApi } from '../../hooks/useApi';
import {
  CheckCircleIcon,
  ExclamationTriangleIcon,
  ArrowPathIcon
} from '@heroicons/react/24/outline';
import LoadingSpinner from '../LoadingSpinner';

/**
 * Appointment Settings Management Component
 * Allows admin to set daily booking limits per user
 */
const AppointmentSettingsManagement = ({ isDarkMode = true }) => {
  const { callApi, loading } = useApi();
  const [dailyLimit, setDailyLimit] = useState(3);
  const [isActive, setIsActive] = useState(true);
  const [description, setDescription] = useState('');
  const [error, setError] = useState(null);
  const [success, setSuccess] = useState(null);
  const [settings, setSettings] = useState(null);
  const [saving, setSaving] = useState(false);
  const [history, setHistory] = useState([]);
  const [showHistory, setShowHistory] = useState(false);

  // Load current settings
  useEffect(() => {
    loadSettings();
  }, []);

  const loadSettings = async () => {
    try {
      const result = await callApi(async () => {
        const response = await axios.get('/api/admin/appointment-settings');
        return response.data;
      });

      if (result.success && result.data) {
        setSettings(result.data);
        setDailyLimit(result.data.daily_booking_limit_per_user);
        setIsActive(result.data.is_active);
        setDescription(result.data.description || '');
      }
    } catch (err) {
      console.error('Failed to load appointment settings:', err);
      setError('Failed to load appointment settings');
    }
  };

  const loadHistory = async () => {
    try {
      const result = await callApi(async () => {
        const response = await axios.get('/api/admin/appointment-settings/history');
        return response.data;
      });

      if (result.success && result.data) {
        setHistory(result.data);
      }
    } catch (err) {
      console.error('Failed to load settings history:', err);
    }
  };

  const handleSave = async () => {
    // Validation
    if (dailyLimit < 1 || dailyLimit > 50) {
      setError('Daily limit must be between 1 and 50');
      return;
    }

    setSaving(true);
    setError(null);
    setSuccess(null);

    try {
      const result = await callApi(async () => {
        const response = await axios.put('/api/admin/appointment-settings', {
          daily_booking_limit_per_user: parseInt(dailyLimit),
          is_active: isActive,
          description: description.trim(),
        });
        return response.data;
      });

      if (result.success) {
        setSettings(result.data);
        setSuccess('Appointment settings updated successfully! Changes will be reflected immediately for all users.');
        
        // Notify all clients about the settings change
        window.dispatchEvent(new CustomEvent('appointmentSettingsChanged', {
          detail: {
            limit: result.data.daily_booking_limit_per_user,
            isActive: result.data.is_active,
            updatedAt: result.data.updated_at
          }
        }));
        
        // Reload history
        setTimeout(() => loadHistory(), 500);
      } else {
        setError(result.message || 'Failed to update settings');
      }
    } catch (err) {
      console.error('Error saving settings:', err);
      setError(err.message || 'Failed to save appointment settings');
    } finally {
      setSaving(false);
    }
  };

  const handleRefresh = async () => {
    await loadSettings();
    setError(null);
    setSuccess(null);
  };

  if (loading && !settings) {
    return <LoadingSpinner isDarkMode={isDarkMode} />;
  }

  return (
    <div className="space-y-6">
      <div className="flex justify-between items-center mb-4">
        <div>
          <h2 className={`text-lg font-bold ${isDarkMode ? 'text-amber-50' : 'text-gray-900'}`}>
            Appointment Settings
          </h2>
          <p className={`text-sm ${isDarkMode ? 'text-gray-400' : 'text-gray-600'}`}>
            Configure daily booking limits for all users
          </p>
        </div>
        <button
          onClick={handleRefresh}
          className="p-2 rounded-lg hover:bg-gray-700 transition-colors"
          title="Refresh settings"
        >
          <ArrowPathIcon className="h-5 w-5 text-amber-400" />
        </button>
      </div>

      {/* Error Alert */}
      {error && (
        <div className={`p-4 rounded-lg flex items-start gap-3 ${
          isDarkMode 
            ? 'bg-red-900/20 border border-red-500/30' 
            : 'bg-red-50 border border-red-200'
        }`}>
          <ExclamationTriangleIcon className={`h-5 w-5 flex-shrink-0 mt-0.5 ${
            isDarkMode ? 'text-red-400' : 'text-red-600'
          }`} />
          <div>
            <h3 className={`font-medium ${isDarkMode ? 'text-red-400' : 'text-red-900'}`}>
              Error
            </h3>
            <p className={`text-sm mt-1 ${isDarkMode ? 'text-red-300/80' : 'text-red-700'}`}>
              {error}
            </p>
          </div>
        </div>
      )}

      {/* Success Alert */}
      {success && (
        <div className={`p-4 rounded-lg flex items-start gap-3 ${
          isDarkMode 
            ? 'bg-green-900/20 border border-green-500/30' 
            : 'bg-green-50 border border-green-200'
        }`}>
          <CheckCircleIcon className={`h-5 w-5 flex-shrink-0 mt-0.5 ${
            isDarkMode ? 'text-green-400' : 'text-green-600'
          }`} />
          <div>
            <h3 className={`font-medium ${isDarkMode ? 'text-green-400' : 'text-green-900'}`}>
              Success
            </h3>
            <p className={`text-sm mt-1 ${isDarkMode ? 'text-green-300/80' : 'text-green-700'}`}>
              {success}
            </p>
          </div>
        </div>
      )}

      {/* Main Settings Card */}
      <div className={`rounded-lg border p-6 ${
        isDarkMode 
          ? 'bg-gray-800 border-gray-700' 
          : 'bg-white border-gray-200'
      }`}>
        <div className="space-y-6">
          {/* Daily Limit Input */}
          <div>
            <label className={`block text-sm font-semibold mb-3 ${
              isDarkMode ? 'text-amber-50' : 'text-gray-900'
            }`}>
              Daily Booking Limit Per User
            </label>
            <p className={`text-xs mb-3 ${isDarkMode ? 'text-gray-400' : 'text-gray-600'}`}>
              Set the maximum number of appointments each user can book per day. This limit applies to all users equally.
            </p>
            
            <div className="flex items-center gap-4">
              <input
                type="number"
                min="1"
                max="50"
                value={dailyLimit}
                onChange={(e) => {
                  const val = parseInt(e.target.value);
                  if (!isNaN(val)) {
                    setDailyLimit(val);
                  }
                }}
                className={`px-4 py-3 border rounded-lg text-lg font-semibold focus:outline-none focus:ring-2 focus:ring-amber-500 w-32 ${
                  isDarkMode
                    ? 'bg-gray-700 border-gray-600 text-amber-50'
                    : 'bg-white border-gray-300 text-gray-900'
                }`}
              />
              <div>
                <p className={`text-sm font-medium ${isDarkMode ? 'text-amber-50' : 'text-gray-900'}`}>
                  {dailyLimit} {dailyLimit === 1 ? 'appointment' : 'appointments'} per day
                </p>
                <p className={`text-xs mt-1 ${isDarkMode ? 'text-gray-400' : 'text-gray-600'}`}>
                  Per user per day
                </p>
              </div>
            </div>

            {/* Input Range Indicators */}
            <div className="mt-3 flex gap-2 text-xs">
              <span className={`px-2 py-1 rounded ${
                dailyLimit <= 2 
                  ? isDarkMode ? 'bg-amber-900/40 text-amber-300' : 'bg-amber-100 text-amber-700'
                  : isDarkMode ? 'bg-gray-700 text-gray-400' : 'bg-gray-100 text-gray-600'
              }`}>
                Restricted
              </span>
              <span className={`px-2 py-1 rounded ${
                dailyLimit > 2 && dailyLimit <= 5
                  ? isDarkMode ? 'bg-blue-900/40 text-blue-300' : 'bg-blue-100 text-blue-700'
                  : isDarkMode ? 'bg-gray-700 text-gray-400' : 'bg-gray-100 text-gray-600'
              }`}>
                Moderate
              </span>
              <span className={`px-2 py-1 rounded ${
                dailyLimit > 5
                  ? isDarkMode ? 'bg-green-900/40 text-green-300' : 'bg-green-100 text-green-700'
                  : isDarkMode ? 'bg-gray-700 text-gray-400' : 'bg-gray-100 text-gray-600'
              }`}>
                Generous
              </span>
            </div>
          </div>

          {/* Status Toggle */}
          <div className="border-t border-gray-700 pt-6">
            <label className={`flex items-center gap-3 cursor-pointer ${
              isDarkMode ? 'text-amber-50' : 'text-gray-900'
            }`}>
              <input
                type="checkbox"
                checked={isActive}
                onChange={(e) => setIsActive(e.target.checked)}
                className="w-4 h-4 rounded cursor-pointer"
              />
              <span className="text-sm font-medium">
                Enable Daily Booking Limit
              </span>
            </label>
            <p className={`text-xs mt-2 ${isDarkMode ? 'text-gray-400' : 'text-gray-600'}`}>
              {isActive 
                ? 'Users will be unable to book more than the specified limit per day.' 
                : 'Users can book unlimited appointments per day.'
              }
            </p>
          </div>

          {/* Description */}
          <div className="border-t border-gray-700 pt-6">
            <label className={`block text-sm font-semibold mb-2 ${
              isDarkMode ? 'text-amber-50' : 'text-gray-900'
            }`}>
              Notes (Optional)
            </label>
            <textarea
              value={description}
              onChange={(e) => setDescription(e.target.value)}
              placeholder="Add any notes about this setting for reference..."
              maxLength={500}
              rows={3}
              className={`w-full px-4 py-3 border rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-500 resize-none ${
                isDarkMode
                  ? 'bg-gray-700 border-gray-600 text-amber-50 placeholder-gray-500'
                  : 'bg-white border-gray-300 text-gray-900 placeholder-gray-400'
              }`}
            />
            <p className={`text-xs mt-2 ${isDarkMode ? 'text-gray-400' : 'text-gray-600'}`}>
              {description.length}/500 characters
            </p>
          </div>

          {/* Current Settings Display */}
          {settings && (
            <div className={`border-t border-gray-700 pt-6 ${
              isDarkMode ? 'bg-gray-700/30' : 'bg-gray-50'
            } rounded-lg p-4`}>
              <p className={`text-xs font-semibold uppercase tracking-wide mb-2 ${
                isDarkMode ? 'text-gray-400' : 'text-gray-600'
              }`}>
                Current Settings
              </p>
              <div className="grid grid-cols-2 gap-4 text-sm">
                <div>
                  <p className={`${isDarkMode ? 'text-gray-400' : 'text-gray-600'}`}>
                    Limit Status
                  </p>
                  <p className={`font-medium ${isDarkMode ? 'text-amber-50' : 'text-gray-900'}`}>
                    {isActive ? '✓ Active' : '✗ Inactive'}
                  </p>
                </div>
                <div>
                  <p className={`${isDarkMode ? 'text-gray-400' : 'text-gray-600'}`}>
                    Last Updated
                  </p>
                  <p className={`font-medium ${isDarkMode ? 'text-amber-50' : 'text-gray-900'}`}>
                    {settings.updated_at ? new Date(settings.updated_at).toLocaleDateString() : 'N/A'}
                  </p>
                </div>
              </div>
            </div>
          )}

          {/* Save Button */}
          <div className="border-t border-gray-700 pt-6">
            <button
              onClick={handleSave}
              disabled={saving || loading}
              className="w-full px-6 py-3 bg-amber-600 text-white rounded-lg font-semibold hover:bg-amber-700 transition-all disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2"
            >
              {saving ? (
                <>
                  <div className="animate-spin">
                    <ArrowPathIcon className="h-4 w-4" />
                  </div>
                  Saving...
                </>
              ) : (
                <>
                  <CheckCircleIcon className="h-5 w-5" />
                  Save Changes
                </>
              )}
            </button>
            <p className={`text-xs text-center mt-3 ${isDarkMode ? 'text-gray-400' : 'text-gray-600'}`}>
              Changes will take effect immediately for all users
            </p>
          </div>
        </div>
      </div>

      {/* Settings History */}
      <div>
        <button
          onClick={() => {
            if (!showHistory && history.length === 0) {
              loadHistory();
            }
            setShowHistory(!showHistory);
          }}
          className={`w-full px-4 py-3 text-sm font-medium rounded-lg border transition-all ${
            isDarkMode
              ? 'bg-gray-800 border-gray-700 text-amber-400 hover:bg-gray-700'
              : 'bg-white border-gray-200 text-amber-600 hover:bg-gray-50'
          } flex items-center justify-between`}
        >
          <span>Settings History</span>
          <span className={`text-xs px-2 py-1 rounded-full ${
            isDarkMode ? 'bg-gray-700' : 'bg-gray-100'
          }`}>
            {history.length}
          </span>
        </button>

        {showHistory && (
          <div className={`mt-3 rounded-lg border p-4 space-y-3 max-h-[400px] overflow-y-auto ${
            isDarkMode
              ? 'bg-gray-800 border-gray-700'
              : 'bg-white border-gray-200'
          }`}>
            {history.length === 0 ? (
              <p className={`text-sm ${isDarkMode ? 'text-gray-400' : 'text-gray-600'}`}>
                No history available yet
              </p>
            ) : (
              history.map((record, idx) => (
                <div
                  key={idx}
                  className={`p-3 rounded-lg border ${
                    isDarkMode
                      ? 'bg-gray-700/30 border-gray-600'
                      : 'bg-gray-50 border-gray-200'
                  }`}
                >
                  <div className="flex justify-between items-start">
                    <div>
                      <p className={`text-sm font-medium ${isDarkMode ? 'text-amber-50' : 'text-gray-900'}`}>
                        Daily Limit: <span className="text-amber-400">{record.daily_booking_limit_per_user}</span>
                      </p>
                      <p className={`text-xs mt-1 ${isDarkMode ? 'text-gray-400' : 'text-gray-600'}`}>
                        Status: {record.is_active ? '✓ Active' : '✗ Inactive'}
                      </p>
                    </div>
                    <p className={`text-xs ${isDarkMode ? 'text-gray-500' : 'text-gray-500'}`}>
                      {new Date(record.updated_at).toLocaleString()}
                    </p>
                  </div>
                  {record.description && (
                    <p className={`text-xs mt-2 ${isDarkMode ? 'text-gray-400' : 'text-gray-600'}`}>
                      {record.description}
                    </p>
                  )}
                </div>
              ))
            )}
          </div>
        )}
      </div>
    </div>
  );
};

export default AppointmentSettingsManagement;
