import { useEffect, useRef, useCallback } from 'react';
import axios from 'axios';

/**
 * Custom hook for real-time updates using polling as fallback
 * Checks for changes in slot capacities and appointment settings
 * Triggers callbacks when changes are detected
 */
export const useRealtimeUpdates = (onCapacityChange, onSettingsChange) => {
  const lastCheckRef = useRef(new Date());
  const pollingIntervalRef = useRef(null);
  const isPollingRef = useRef(false);

  /**
   * Start polling for updates
   */
  const startPolling = useCallback(() => {
    if (isPollingRef.current) return;
    
    isPollingRef.current = true;

    const pollUpdates = async () => {
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

          // Update last check time
          lastCheckRef.current = new Date(response.data.timestamp);
        }
      } catch (error) {
        console.error('[RealtimeUpdates] Polling error:', error);
        // Continue polling even on error
      }
    };

    // Initial check
    pollUpdates();

    // Poll every 10 seconds for changes
    pollingIntervalRef.current = setInterval(pollUpdates, 10000);
  }, [onCapacityChange, onSettingsChange]);

  /**
   * Stop polling
   */
  const stopPolling = useCallback(() => {
    if (pollingIntervalRef.current) {
      clearInterval(pollingIntervalRef.current);
      pollingIntervalRef.current = null;
    }
    isPollingRef.current = false;
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

        lastCheckRef.current = new Date(response.data.timestamp);
      }
    } catch (error) {
      console.error('[RealtimeUpdates] Manual check error:', error);
    }
  }, [onCapacityChange, onSettingsChange]);

  // Cleanup on unmount
  useEffect(() => {
    return () => {
      stopPolling();
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
