import { useCallback } from 'react';
import axios from 'axios';
import { useApi } from '../useApi';

/**
 * Hook to handle all service-related API calls
 */
export const useServiceAPI = () => {
  const { callApi } = useApi();

  const fetchServices = useCallback(async () => {
    try {
      const result = await callApi(async () => {
        const response = await axios.get('/api/admin/services', { timeout: 10000 });
        let servicesData = [];
        const payload = response.data?.data || response.data || response.data?.services || response.data;
        
        if (Array.isArray(payload)) {
          servicesData = payload;
        } else if (payload && typeof payload === 'object') {
          servicesData = Object.values(payload).filter(item => item && typeof item === 'object');
        }
        
        const activeServices = servicesData.filter(s => !s.deleted_at);
        return { data: activeServices };
      });

      return result.success ? result.data : [];
    } catch (error) {
      console.error('Failed to fetch services:', error);
      return [];
    }
  }, [callApi]);

  const fetchStats = useCallback(async () => {
    try {
      const result = await callApi(async () => {
        const response = await axios.get('/api/admin/stats', { timeout: 10000 });
        const payload = response.data?.data || response.data || {};
        return { data: payload };
      });

      return result.success ? result.data : {};
    } catch (error) {
      console.error('Failed to fetch stats:', error);
      return {};
    }
  }, [callApi]);

  return {
    fetchServices,
    fetchStats
  };
};

export default useServiceAPI;
