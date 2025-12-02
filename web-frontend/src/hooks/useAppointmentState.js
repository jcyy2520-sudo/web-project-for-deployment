/**
 * Appointment State Management Hooks
 * Centralized hooks for appointment-related operations
 */

import { useState, useCallback } from 'react';
import axios from 'axios';

/**
 * useAppointments - Fetch and manage appointments list
 * 
 * @param {Object} options Configuration options
 * @param {number} options.page Current page
 * @param {number} options.perPage Items per page
 * @param {string} options.status Filter by status
 * @returns {Object} Appointments state and methods
 */
export function useAppointments(options = {}) {
  const [appointments, setAppointments] = useState([]);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);
  const [pagination, setPagination] = useState(null);

  const fetchAppointments = useCallback(async (filters = {}) => {
    setLoading(true);
    setError(null);
    
    try {
      const response = await axios.get('/api/appointments', {
        params: {
          page: options.page || 1,
          per_page: options.perPage || 15,
          status: options.status,
          ...filters,
        }
      });

      if (response.data.success) {
        setAppointments(response.data.data);
        setPagination(response.data.pagination);
      }
    } catch (err) {
      setError(err.response?.data?.message || 'Failed to fetch appointments');
    } finally {
      setLoading(false);
    }
  }, [options]);

  return {
    appointments,
    loading,
    error,
    pagination,
    fetchAppointments,
  };
}

/**
 * useAppointmentForm - Manage appointment creation/editing
 * 
 * @param {Object} onSuccess Callback on successful submission
 * @returns {Object} Form state and methods
 */
export function useAppointmentForm(onSuccess) {
  const [formData, setFormData] = useState({
    appointment_date: '',
    appointment_time: '',
    service_id: null,
    notes: '',
  });
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);

  const handleChange = useCallback((e) => {
    const { name, value } = e.target;
    setFormData(prev => ({
      ...prev,
      [name]: value,
    }));
  }, []);

  const submit = useCallback(async (appointmentId = null) => {
    setLoading(true);
    setError(null);

    try {
      const url = appointmentId 
        ? `/api/appointments/${appointmentId}`
        : '/api/appointments';
      
      const method = appointmentId ? 'put' : 'post';
      
      const response = await axios({
        method,
        url,
        data: formData,
      });

      if (response.data.success) {
        onSuccess?.(response.data.data);
        setFormData({
          appointment_date: '',
          appointment_time: '',
          service_id: null,
          notes: '',
        });
      } else {
        setError(response.data.message || 'Failed to save appointment');
      }
    } catch (err) {
      setError(err.response?.data?.message || 'An error occurred');
    } finally {
      setLoading(false);
    }
  }, [formData, onSuccess]);

  const reset = useCallback(() => {
    setFormData({
      appointment_date: '',
      appointment_time: '',
      service_id: null,
      notes: '',
    });
    setError(null);
  }, []);

  return {
    formData,
    loading,
    error,
    handleChange,
    submit,
    reset,
    setFormData,
  };
}

/**
 * useAppointmentStatus - Get appointment status options
 * 
 * @returns {Object} Status configuration
 */
export function useAppointmentStatus() {
  const statuses = {
    pending: { label: 'Pending', color: 'yellow', icon: 'clock' },
    approved: { label: 'Approved', color: 'blue', icon: 'check-circle' },
    completed: { label: 'Completed', color: 'green', icon: 'checkmark' },
    cancelled: { label: 'Cancelled', color: 'red', icon: 'x-circle' },
    no_show: { label: 'No Show', color: 'orange', icon: 'exclamation' },
  };

  return {
    statuses,
    getStatus: (status) => statuses[status] || statuses.pending,
    statusList: Object.entries(statuses).map(([key, value]) => ({
      value: key,
      ...value,
    })),
  };
}
