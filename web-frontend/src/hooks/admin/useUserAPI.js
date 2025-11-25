import { useCallback } from 'react';
import axios from 'axios';
import { useApi } from '../useApi';

/**
 * Hook to handle all user-related API calls
 */
export const useUserAPI = () => {
  const { callApi } = useApi();

  const fetchUsers = useCallback(async () => {
    try {
      const result = await callApi(async () => {
        const response = await axios.get('/api/users', { timeout: 10000 });
        let usersData = [];
        const payload = response.data?.data || response.data || response.data?.users || response.data;
        
        if (Array.isArray(payload)) {
          usersData = payload;
        } else if (payload && typeof payload === 'object') {
          usersData = Object.values(payload).filter(item => item && typeof item === 'object');
        }
        
        return { 
          data: usersData.map(user => ({ 
            ...user, 
            status: user.is_active ? 'active' : 'inactive'
          })) 
        };
      });

      return result.success ? result.data : [];
    } catch (error) {
      console.error('Failed to fetch users:', error);
      return [];
    }
  }, [callApi]);

  const fetchAdmins = useCallback(async () => {
    try {
      const result = await callApi(async () => {
        const response = await axios.get('/api/users', { timeout: 10000 });
        let allUsers = [];
        const payload = response.data?.data || response.data || response.data?.users || response.data;
        
        if (Array.isArray(payload)) {
          allUsers = payload;
        } else if (payload && typeof payload === 'object') {
          allUsers = Object.values(payload).filter(item => item && typeof item === 'object');
        }
        
        const adminsData = allUsers.filter(user => user.role === 'admin' || user.role === 'staff');
        return { 
          data: adminsData.map(admin => ({ 
            ...admin, 
            status: admin.is_active ? 'active' : 'inactive'
          })) 
        };
      });

      return result.success ? result.data : [];
    } catch (error) {
      console.error('Failed to fetch admins:', error);
      return [];
    }
  }, [callApi]);

  const saveUser = useCallback(async (userData, userId = null) => {
    try {
      const url = userId ? `/api/users/${userId}` : '/api/users';
      const method = userId ? 'PUT' : 'POST';

      const requestData = {
        first_name: userData.first_name,
        last_name: userData.last_name,
        email: userData.email,
        phone: userData.phone,
        address: userData.address,
        role: userData.role,
        ...(userData.password && { password: userData.password })
      };

      const result = await callApi(() => axios({
        method,
        url,
        data: requestData,
        timeout: 15000
      }));

      return result.success ? result.data?.data || result.data : null;
    } catch (error) {
      console.error('Error saving user:', error);
      return null;
    }
  }, [callApi]);

  const deleteUser = useCallback(async (userId) => {
    try {
      const result = await callApi(() => 
        axios.delete(`/api/users/${userId}`, { timeout: 15000 })
      );
      return result.success;
    } catch (error) {
      console.error('Error deleting user:', error);
      return false;
    }
  }, [callApi]);

  const toggleUserStatus = useCallback(async (userId, newStatus) => {
    try {
      const result = await callApi(() => 
        axios.put(`/api/users/${userId}`, { is_active: newStatus }, { timeout: 15000 })
      );
      return result.success;
    } catch (error) {
      console.error('Error toggling user status:', error);
      return false;
    }
  }, [callApi]);

  return {
    fetchUsers,
    fetchAdmins,
    saveUser,
    deleteUser,
    toggleUserStatus
  };
};

export default useUserAPI;
