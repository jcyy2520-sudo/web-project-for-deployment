import { useEffect, useRef, useCallback } from 'react';
import axios from 'axios';

/**
 * Custom hook for real-time updates using polling as fallback
 * Checks for changes in slot capacities, appointment settings,
 * unavailable dates, and user appointment status updates
 * Triggers callbacks when changes are detected
 */
export const useRealtimeUpdates = (onCapacityChange, onSettingsChange, onUnavailableDatesChange, onAppointmentStatusChange) => {
  const lastCheckRef = useRef(new Date());
  const pollingIntervalRef = useRef(null);
  const isPollingRef = useRef(false);
  const pollInFlightRef = useRef(false);
  const consecutiveErrorsRef = useRef(0);
  const MAX_CONSECUTIVE_ERRORS = 5;

  /**
   * Start polling for updates
   */
  const startPolling = useCallback(() => {
    if (isPollingRef.current) return;
    
    isPollingRef.current = true;

    const pollUpdates = async () => {
      if (typeof document !== 'undefined' && document.hidden) {
        return;
      }

      if (pollInFlightRef.current) {
        return;
      }

      pollInFlightRef.current = true;

      try {
        const response = await axios.get('/api/realtime/updates', {
          params: {
            last_check: lastCheckRef.current.toISOString()
          }
        });

        if (response.data.success) {
          const changes = response.data.changes;

          // Check for slot capacity changes
          if (changes.slot_capacities && changes.slot_capacities.count > 0) {
            console.log('[RealtimeUpdates] Slot capacities changed:', changes.slot_capacities.data);
            if (onCapacityChange) {
              onCapacityChange(changes.slot_capacities.data);
            }
            // Dispatch event for other listeners
            window.dispatchEvent(new CustomEvent('slotCapacitiesChanged'));
          }

          // Check for appointment settings changes
          if (changes.appointment_settings && changes.appointment_settings.updated) {
            console.log('[RealtimeUpdates] Appointment settings changed:', changes.appointment_settings.data);
            if (onSettingsChange) {
              onSettingsChange(changes.appointment_settings.data);
            }
            // Dispatch event for other listeners
            window.dispatchEvent(new CustomEvent('appointmentSettingsChanged'));
          }

          // Check for unavailable/blackout date changes
          if (changes.unavailable_dates && changes.unavailable_dates.updated) {
            console.log('[RealtimeUpdates] Unavailable dates changed');
            if (onUnavailableDatesChange) {
              onUnavailableDatesChange();
            }
            // Dispatch event for other listeners
            window.dispatchEvent(new CustomEvent('unavailableDatesChanged'));
          }

          // Check for user's appointment status changes (admin approved/declined/cancelled)
          if (changes.appointments && changes.appointments.updated) {
            console.log('[RealtimeUpdates] User appointments changed');
            if (onAppointmentStatusChange) {
              onAppointmentStatusChange();
            }
            window.dispatchEvent(new CustomEvent('appointmentStatusChanged'));
          }

          // Update last check time
          lastCheckRef.current = new Date(response.data.timestamp);
        }
        // Reset consecutive error counter on success
        consecutiveErrorsRef.current = 0;
      } catch (error) {
        // Stop polling on auth errors - user is logged out or token expired
        if (error.response?.status === 401 || error.response?.status === 403) {
          console.warn('[RealtimeUpdates] Auth error, stopping polling');
          stopPollingInternal();
          return;
        }
        consecutiveErrorsRef.current++;
        console.error('[RealtimeUpdates] Polling error:', error);
        // Stop polling after too many consecutive errors to avoid flooding
        if (consecutiveErrorsRef.current >= MAX_CONSECUTIVE_ERRORS) {
          console.warn('[RealtimeUpdates] Too many consecutive errors, stopping polling');
          stopPollingInternal();
          return;
        }
      } finally {
        pollInFlightRef.current = false;
      }
    };

    const stopPollingInternal = () => {
      if (pollingIntervalRef.current) {
        clearInterval(pollingIntervalRef.current);
        pollingIntervalRef.current = null;
      }
      isPollingRef.current = false;
    };

    // Initial check
    pollUpdates();

    // Poll every 30 seconds for changes (reduced from 10s for performance)
    pollingIntervalRef.current = setInterval(pollUpdates, 30000);
  }, [onCapacityChange, onSettingsChange, onUnavailableDatesChange, onAppointmentStatusChange]);

  /**
   * Stop polling
   */
  const stopPolling = useCallback(() => {
    if (pollingIntervalRef.current) {
      clearInterval(pollingIntervalRef.current);
      pollingIntervalRef.current = null;
    }
    isPollingRef.current = false;
    consecutiveErrorsRef.current = 0;
  }, []);

  /**
   * Manually trigger an update check
   */
  const checkNow = useCallback(async () => {
    try {
      const response = await axios.get('/api/realtime/updates', {
        params: {
          last_check: lastCheckRef.current.toISOString()
        }
      });

      if (response.data.success) {
        const changes = response.data.changes;

        if (changes.slot_capacities && changes.slot_capacities.count > 0) {
          if (onCapacityChange) {
            onCapacityChange(changes.slot_capacities.data);
          }
          window.dispatchEvent(new CustomEvent('slotCapacitiesChanged'));
        }

        if (changes.appointment_settings && changes.appointment_settings.updated) {
          if (onSettingsChange) {
            onSettingsChange(changes.appointment_settings.data);
          }
          window.dispatchEvent(new CustomEvent('appointmentSettingsChanged'));
        }

        if (changes.unavailable_dates && changes.unavailable_dates.updated) {
          if (onUnavailableDatesChange) {
            onUnavailableDatesChange();
          }
          window.dispatchEvent(new CustomEvent('unavailableDatesChanged'));
        }

        if (changes.appointments && changes.appointments.updated) {
          if (onAppointmentStatusChange) {
            onAppointmentStatusChange();
          }
          window.dispatchEvent(new CustomEvent('appointmentStatusChanged'));
        }

        lastCheckRef.current = new Date(response.data.timestamp);
      }
    } catch (error) {
      console.error('[RealtimeUpdates] Manual check error:', error);
    }
  }, [onCapacityChange, onSettingsChange, onUnavailableDatesChange, onAppointmentStatusChange]);

  // Cleanup on unmount and listen for auth:logout to stop polling immediately
  useEffect(() => {
    const handleAuthLogout = () => {
      stopPolling();
    };
    window.addEventListener('auth:logout', handleAuthLogout);
    return () => {
      stopPolling();
      window.removeEventListener('auth:logout', handleAuthLogout);
    };
  }, [stopPolling]);

  return {
    startPolling,
    stopPolling,
    checkNow,
    isPolling: isPollingRef.current
  };
};

export default useRealtimeUpdates;
