import { useCallback } from 'react';
import axios from 'axios';
import { useApi } from '../useApi';

/**
 * Hook to handle all appointment-related API calls
 */
export const useAppointmentAPI = () => {
  const { callApi } = useApi();

  const fetchAppointments = useCallback(async () => {
    try {
      const result = await callApi(async () => {
        const response = await axios.get('/api/admin/appointments', { timeout: 10000 });
        let appointmentsData = [];
        const payload = response.data?.data || response.data?.data?.data || response.data || response.data?.appointments || response.data;
        
        if (Array.isArray(payload)) {
          appointmentsData = payload;
        } else if (payload && typeof payload === 'object') {
          appointmentsData = Object.values(payload).filter(item => item && typeof item === 'object');
        }
        
        return { data: appointmentsData };
      });

      return result.success ? result.data : [];
    } catch (error) {
      console.error('Failed to fetch appointments:', error);
      return [];
    }
  }, [callApi]);

  const fetchUnavailableDates = useCallback(async () => {
    try {
      const result = await callApi(async () => {
        const response = await axios.get('/api/admin/unavailable-dates', { timeout: 10000 });
        let datesData = [];
        const payload = response.data?.data || response.data || response.data?.dates || response.data;
        
        if (Array.isArray(payload)) {
          datesData = payload;
        } else if (payload && typeof payload === 'object') {
          datesData = Object.values(payload).filter(item => item && typeof item === 'object');
        }
        
        return { data: datesData };
      });

      return result.success ? result.data : [];
    } catch (error) {
      console.error('Failed to fetch unavailable dates:', error);
      return [];
    }
  }, [callApi]);

  const addUnavailableDate = useCallback(async (dateData) => {
    try {
      const result = await callApi(() => axios({
        method: 'POST',
        url: '/api/admin/unavailable-dates',
        data: dateData,
        timeout: 15000
      }));

      return result.success ? result.data?.data || result.data : null;
    } catch (error) {
      console.error('Error adding unavailable date:', error);
      return null;
    }
  }, [callApi]);

  const deleteUnavailableDate = useCallback(async (dateId) => {
    try {
      const result = await callApi(() => 
        axios.delete(`/api/admin/unavailable-dates/${dateId}`, { timeout: 15000 })
      );
      return result.success;
    } catch (error) {
      console.error('Error deleting unavailable date:', error);
      return false;
    }
  }, [callApi]);

  const updateAppointmentStatus = useCallback(async (appointmentId, status, reason = null) => {
    try {
      const url = `/api/appointments/${appointmentId}`;
      const data = { status };
      if (reason) data.decline_reason = reason;

      const result = await callApi(() => 
        axios.put(url, data, { timeout: 15000 })
      );
      return result.success;
    } catch (error) {
      console.error('Error updating appointment status:', error);
      return false;
    }
  }, [callApi]);

  return {
    fetchAppointments,
    fetchUnavailableDates,
    addUnavailableDate,
    deleteUnavailableDate,
    updateAppointmentStatus
  };
};

export default useAppointmentAPI;
