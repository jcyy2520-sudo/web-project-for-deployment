import { useState, useEffect } from 'react';
import { useApi } from '../../hooks/useApi';
import axios from 'axios';
import {
  getDevicePushStatus,
  subscribeDeviceToPush,
  unsubscribeDeviceFromPush,
} from '../../utils/pushNotifications';
import {
  BellIcon,
  EnvelopeIcon,
  ClockIcon,
  DevicePhoneMobileIcon
} from '@heroicons/react/24/outline';

const NotificationPreferences = ({ isDarkMode = true }) => {
  const { callApi } = useApi();
  const [preferences, setPreferences] = useState(null);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [deviceStatus, setDeviceStatus] = useState({ supported: false, permission: 'default', subscribed: false });
  const [deviceBusy, setDeviceBusy] = useState(false);

  useEffect(() => {
    loadPreferences();
    refreshDeviceStatus();
  }, []);

  const refreshDeviceStatus = async () => {
    try {
      setDeviceStatus(await getDevicePushStatus());
    } catch (error) {
      console.error('Failed to read device push status:', error);
    }
  };

  const loadPreferences = async () => {
    setLoading(true);
    const result = await callApi((signal) =>
      axios.get('/api/notifications/preferences', { signal })
    );

    if (result.success) {
      setPreferences(result.data.data);
    }
    setLoading(false);
  };

  const updatePreference = async (field, value) => {
    setSaving(true);
    const result = await callApi((signal) =>
      axios.put('/api/notifications/preferences', {
        [field]: value
      }, { signal })
    );

    if (result.success) {
      setPreferences(result.data.data);
      window.showToast?.('Success', 'Preferences updated', 'success');
    }
    setSaving(false);
  };

  const handleQuietHoursChange = async (field, value) => {
    const newQuietHours = {
      ...(preferences?.quiet_hours || { enabled: false, start: '22:00', end: '08:00' }),
      [field]: value
    };

    setSaving(true);
    const result = await callApi((signal) =>
      axios.put('/api/notifications/preferences', {
        quiet_hours: newQuietHours
      }, { signal })
    );

    if (result.success) {
      setPreferences(result.data.data);
    }
    setSaving(false);
  };

  const handleEnableDevice = async () => {
    try {
      setDeviceBusy(true);
      const result = await subscribeDeviceToPush({ requestPermission: true });

      if (result.success) {
        window.showToast?.('Push Notifications', result.message, 'success');
      } else {
        window.showToast?.('Push Notifications', result.message || 'Unable to enable push notifications.', 'warning');
      }
    } catch (error) {
      window.showToast?.('Push Notifications', error.response?.data?.message || 'Failed to enable push notifications.', 'error');
    } finally {
      await refreshDeviceStatus();
      setDeviceBusy(false);
    }
  };

  const handleDisableDevice = async () => {
    try {
      setDeviceBusy(true);
      const result = await unsubscribeDeviceFromPush({ unsubscribeBrowser: true });
      window.showToast?.('Push Notifications', result.message, 'success');
    } catch (error) {
      window.showToast?.('Push Notifications', error.response?.data?.message || 'Failed to disable push notifications.', 'error');
    } finally {
      await refreshDeviceStatus();
      setDeviceBusy(false);
    }
  };

  if (loading || !preferences) {
    return <div className={`p-4 text-center ${isDarkMode ? 'text-gray-400' : 'text-gray-600'}`}>Loading...</div>;
  }

  return (
    <div className="space-y-6">
      <div>
        <h3 className={`text-sm font-semibold flex items-center mb-4 ${isDarkMode ? 'text-amber-50' : 'text-amber-900'}`}>
          <BellIcon className="h-4 w-4 mr-2" />
          Notification Preferences
        </h3>

        {/* Email Notifications */}
        <div className={`space-y-3 mb-6 p-4 rounded-lg border ${
          isDarkMode ? 'bg-gray-800/50 border-gray-700' : 'bg-white border-gray-200'
        }`}>
          <h4 className={`text-xs font-semibold flex items-center ${isDarkMode ? 'text-amber-400' : 'text-amber-600'}`}>
            <EnvelopeIcon className="h-3.5 w-3.5 mr-2" />
            Email Notifications
          </h4>

          <label className="flex items-center justify-between cursor-pointer">
            <span className={`text-xs ${isDarkMode ? 'text-gray-300' : 'text-gray-700'}`}>
              All Email Notifications
            </span>
            <input
              type="checkbox"
              checked={preferences.email_notifications}
              onChange={(e) => updatePreference('email_notifications', e.target.checked)}
              disabled={saving}
              className="rounded"
            />
          </label>

          <div className={`pl-4 space-y-2 border-l-2 ${isDarkMode ? 'border-gray-600' : 'border-gray-300'}`}>
            <label className="flex items-center justify-between cursor-pointer">
              <span className={`text-xs ${isDarkMode ? 'text-gray-400' : 'text-gray-600'}`}>
                Appointment Approved
              </span>
              <input
                type="checkbox"
                checked={preferences.email_appointment_approved}
                onChange={(e) => updatePreference('email_appointment_approved', e.target.checked)}
                disabled={saving || !preferences.email_notifications}
                className="rounded"
              />
            </label>

            <label className="flex items-center justify-between cursor-pointer">
              <span className={`text-xs ${isDarkMode ? 'text-gray-400' : 'text-gray-600'}`}>
                Appointment Declined
              </span>
              <input
                type="checkbox"
                checked={preferences.email_appointment_declined}
                onChange={(e) => updatePreference('email_appointment_declined', e.target.checked)}
                disabled={saving || !preferences.email_notifications}
                className="rounded"
              />
            </label>

            <label className="flex items-center justify-between cursor-pointer">
              <span className={`text-xs ${isDarkMode ? 'text-gray-400' : 'text-gray-600'}`}>
                New Messages
              </span>
              <input
                type="checkbox"
                checked={preferences.email_new_message}
                onChange={(e) => updatePreference('email_new_message', e.target.checked)}
                disabled={saving || !preferences.email_notifications}
                className="rounded"
              />
            </label>
          </div>
        </div>

        {/* In-App Notifications */}
        <div className={`space-y-3 mb-6 p-4 rounded-lg border ${
          isDarkMode ? 'bg-gray-800/50 border-gray-700' : 'bg-white border-gray-200'
        }`}>
          <h4 className={`text-xs font-semibold flex items-center ${isDarkMode ? 'text-amber-400' : 'text-amber-600'}`}>
            <BellIcon className="h-3.5 w-3.5 mr-2" />
            In-App Notifications
          </h4>

          <label className="flex items-center justify-between cursor-pointer">
            <span className={`text-xs ${isDarkMode ? 'text-gray-300' : 'text-gray-700'}`}>
              All In-App Notifications
            </span>
            <input
              type="checkbox"
              checked={preferences.in_app_notifications}
              onChange={(e) => updatePreference('in_app_notifications', e.target.checked)}
              disabled={saving}
              className="rounded"
            />
          </label>

          <div className={`pl-4 space-y-2 border-l-2 ${isDarkMode ? 'border-gray-600' : 'border-gray-300'}`}>
            <label className="flex items-center justify-between cursor-pointer">
              <span className={`text-xs ${isDarkMode ? 'text-gray-400' : 'text-gray-600'}`}>
                Appointment Updates
              </span>
              <input
                type="checkbox"
                checked={preferences.in_app_appointment_updates}
                onChange={(e) => updatePreference('in_app_appointment_updates', e.target.checked)}
                disabled={saving || !preferences.in_app_notifications}
                className="rounded"
              />
            </label>

            <label className="flex items-center justify-between cursor-pointer">
              <span className={`text-xs ${isDarkMode ? 'text-gray-400' : 'text-gray-600'}`}>
                Messages
              </span>
              <input
                type="checkbox"
                checked={preferences.in_app_messages}
                onChange={(e) => updatePreference('in_app_messages', e.target.checked)}
                disabled={saving || !preferences.in_app_notifications}
                className="rounded"
              />
            </label>

            <label className="flex items-center justify-between cursor-pointer">
              <span className={`text-xs ${isDarkMode ? 'text-gray-400' : 'text-gray-600'}`}>
                Reminders
              </span>
              <input
                type="checkbox"
                checked={preferences.in_app_reminders}
                onChange={(e) => updatePreference('in_app_reminders', e.target.checked)}
                disabled={saving || !preferences.in_app_notifications}
                className="rounded"
              />
            </label>
          </div>
        </div>

        {/* Quiet Hours */}
        <div className={`space-y-3 p-4 rounded-lg border ${
          isDarkMode ? 'bg-gray-800/50 border-gray-700' : 'bg-white border-gray-200'
        }`}>
          <h4 className={`text-xs font-semibold flex items-center ${isDarkMode ? 'text-amber-400' : 'text-amber-600'}`}>
            <DevicePhoneMobileIcon className="h-3.5 w-3.5 mr-2" />
            Web Push Notifications
          </h4>

          <label className="flex items-center justify-between cursor-pointer">
            <span className={`text-xs ${isDarkMode ? 'text-gray-300' : 'text-gray-700'}`}>
              Allow Push Notifications
            </span>
            <input
              type="checkbox"
              checked={preferences.push_notifications}
              onChange={(e) => updatePreference('push_notifications', e.target.checked)}
              disabled={saving}
              className="rounded"
            />
          </label>

          <div className={`pl-4 space-y-2 border-l-2 ${isDarkMode ? 'border-gray-600' : 'border-gray-300'}`}>
            <label className="flex items-center justify-between cursor-pointer">
              <span className={`text-xs ${isDarkMode ? 'text-gray-400' : 'text-gray-600'}`}>
                Appointment Updates
              </span>
              <input
                type="checkbox"
                checked={preferences.push_appointment_updates}
                onChange={(e) => updatePreference('push_appointment_updates', e.target.checked)}
                disabled={saving || !preferences.push_notifications}
                className="rounded"
              />
            </label>
          </div>

          <div className={`rounded-lg border px-3 py-3 ${
            isDarkMode ? 'border-gray-700 bg-gray-900/50' : 'border-gray-200 bg-gray-50'
          }`}>
            <p className={`text-xs font-medium ${isDarkMode ? 'text-amber-50' : 'text-gray-900'}`}>
              This Device
            </p>
            <p className={`mt-1 text-xs ${isDarkMode ? 'text-gray-400' : 'text-gray-600'}`}>
              {deviceStatus.supported
                ? `Permission: ${deviceStatus.permission}. ${deviceStatus.subscribed ? 'This device is subscribed.' : 'This device is not subscribed yet.'}`
                : 'This browser does not support push notifications.'}
            </p>

            <div className="mt-3 flex gap-2">
              <button
                type="button"
                onClick={handleEnableDevice}
                disabled={deviceBusy || !deviceStatus.supported || !preferences.push_notifications}
                className={`px-3 py-2 text-xs font-medium rounded transition-colors ${
                  isDarkMode
                    ? 'bg-amber-500 text-gray-950 hover:bg-amber-400 disabled:bg-gray-700 disabled:text-gray-400'
                    : 'bg-amber-600 text-white hover:bg-amber-700 disabled:bg-gray-200 disabled:text-gray-500'
                }`}
              >
                {deviceBusy ? 'Working...' : (deviceStatus.subscribed ? 'Reconnect Device' : 'Enable on This Device')}
              </button>

              <button
                type="button"
                onClick={handleDisableDevice}
                disabled={deviceBusy || !deviceStatus.supported || !deviceStatus.subscribed}
                className={`px-3 py-2 text-xs font-medium rounded transition-colors ${
                  isDarkMode
                    ? 'bg-gray-700 text-amber-50 hover:bg-gray-600 disabled:bg-gray-800 disabled:text-gray-500'
                    : 'bg-gray-200 text-gray-800 hover:bg-gray-300 disabled:bg-gray-100 disabled:text-gray-400'
                }`}
              >
                Disable Device
              </button>
            </div>
          </div>
        </div>

        <div className={`space-y-3 p-4 rounded-lg border ${
          isDarkMode ? 'bg-gray-800/50 border-gray-700' : 'bg-white border-gray-200'
        }`}>
          <h4 className={`text-xs font-semibold flex items-center ${isDarkMode ? 'text-amber-400' : 'text-amber-600'}`}>
            <ClockIcon className="h-3.5 w-3.5 mr-2" />
            Quiet Hours
          </h4>

          <label className="flex items-center justify-between cursor-pointer">
            <span className={`text-xs ${isDarkMode ? 'text-gray-300' : 'text-gray-700'}`}>
              Enable quiet hours (no notifications)
            </span>
            <input
              type="checkbox"
              checked={preferences.quiet_hours?.enabled || false}
              onChange={(e) => handleQuietHoursChange('enabled', e.target.checked)}
              disabled={saving}
              className="rounded"
            />
          </label>

          {preferences.quiet_hours?.enabled && (
            <div className="space-y-2 pl-4 border-l-2 border-amber-500/30">
              <div>
                <label className={`block text-xs mb-1 ${isDarkMode ? 'text-gray-400' : 'text-gray-600'}`}>
                  From
                </label>
                <input
                  type="time"
                  value={preferences.quiet_hours?.start || '22:00'}
                  onChange={(e) => handleQuietHoursChange('start', e.target.value)}
                  disabled={saving}
                  className={`w-full px-2 py-1 text-xs rounded border ${
                    isDarkMode
                      ? 'bg-gray-900 border-gray-600 text-amber-50'
                      : 'bg-white border-gray-300 text-gray-900'
                  }`}
                />
              </div>

              <div>
                <label className={`block text-xs mb-1 ${isDarkMode ? 'text-gray-400' : 'text-gray-600'}`}>
                  To
                </label>
                <input
                  type="time"
                  value={preferences.quiet_hours?.end || '08:00'}
                  onChange={(e) => handleQuietHoursChange('end', e.target.value)}
                  disabled={saving}
                  className={`w-full px-2 py-1 text-xs rounded border ${
                    isDarkMode
                      ? 'bg-gray-900 border-gray-600 text-amber-50'
                      : 'bg-white border-gray-300 text-gray-900'
                  }`}
                />
              </div>
            </div>
          )}
        </div>
      </div>
    </div>
  );
};

export default NotificationPreferences;
