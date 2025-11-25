import { useCallback } from 'react';
import axios from 'axios';
import { useApi } from './useApi';
import logger from '../utils/logger';

/**
 * Custom hook for efficiently loading admin dashboard data
 * Uses parallel loading, caching, and smart data prioritization
 */
export const useAdminData = () => {
  const { callApi } = useApi();

  // Load critical stats first (fast endpoint)
  const loadCriticalStats = useCallback(async () => {
    return callApi(async () => {
      const response = await axios.get('/api/admin/stats/summary', {
        timeout: 5000 // Shorter timeout for critical data
      });
      return response;
    }, {
      showLoading: false,
      cache: true
    });
  }, [callApi]);

  // Load all admin data in parallel
  const loadAllAdminData = useCallback(async () => {
    try {
      // Start all requests in parallel
      const promises = [
        callApi(async () => axios.get('/api/admin/stats', { timeout: 10000 }), {
          cache: true
        }),
        callApi(async () => axios.get('/api/users', { timeout: 10000 }), {
          cache: true
        }),
        callApi(async () => axios.get('/api/admin/appointments', { timeout: 10000 }), {
          cache: true
        }),
        callApi(async () => axios.get('/api/admin/unavailable-dates', { timeout: 10000 }), {
          cache: true
        }),
        callApi(async () => axios.get('/api/admin/services', { timeout: 10000 }), {
          cache: true
        })
      ];

      // Wait for all requests to complete
      const results = await Promise.allSettled(promises);
      
      // Extract results, preserving order
      return results.map(result => 
        result.status === 'fulfilled' ? result.value : null
      );
    } catch (error) {
      logger.error('Error loading admin data:', error);
      throw error;
    }
  }, [callApi]);

  // Load data for specific admin tabs (lazy loading)
  const loadTabData = useCallback(async (tabKey) => {
    const endpoints = {
      users: '/api/users',
      appointments: '/api/admin/appointments',
      calendar: '/api/admin/unavailable-dates',
      services: '/api/admin/services',
      adminProfile: '/api/users?role=admin,staff',
      archive: '/api/users/archived/list',
      messages: '/api/admin/messages'
    };

    const endpoint = endpoints[tabKey];
    if (!endpoint) return null;

    return callApi(async () => {
      const response = await axios.get(endpoint, { timeout: 10000 });
      return response;
    }, {
      cache: true
    });
  }, [callApi]);

  return {
    loadCriticalStats,
    loadAllAdminData,
    loadTabData
  };
};
