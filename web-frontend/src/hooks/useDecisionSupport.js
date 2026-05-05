import { useState, useCallback } from 'react';
import axios from 'axios';

/**
 * useDecisionSupport
 * ------------------
 * Centralizes every Decision-Support API call behind a single React hook.
 *
 * Auth: uses the shared cookie-backed session configured in axios defaults.
 * The global axios.defaults.baseURL is already configured by main.jsx /
 * AuthContext so all paths here are relative.
 */
export const useDecisionSupport = () => {
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);
  const [modelStatus, setModelStatus] = useState(null);
  const [dataQuality, setDataQuality] = useState(null);
  const [isTraining, setIsTraining] = useState(false);
  const [trainingResult, setTrainingResult] = useState(null);

  // -----------------------------------------------------------------------
  // Internal helper – authenticated request with consistent error handling
  // -----------------------------------------------------------------------
  const apiCall = useCallback(async (method, path, body = null, params = null) => {
    const config = {
      method,
      url: path,
      timeout: 15000,
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
      },
      ...(body ? { data: body } : {}),
      ...(params ? { params } : {}),
    };

    const response = await axios(config);
    return response.data;
  }, []);

  // -----------------------------------------------------------------------
  // Public methods
  // -----------------------------------------------------------------------

  /**
   * Fetch the current data-quality report used by the decision-support model.
   * GET /api/decision-support/data-quality
   */
  const fetchDataQuality = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const data = await apiCall('GET', '/api/decision-support/data-quality');
      setDataQuality(data);
      return { success: true, data };
    } catch (err) {
      const message = err.response?.data?.message || err.message || 'Failed to fetch data quality';
      setError(message);
      return { success: false, error: message };
    } finally {
      setLoading(false);
    }
  }, [apiCall]);

  /**
   * Trigger a model training run.
   * POST /api/decision-support/train
   */
  const trainModel = useCallback(async () => {
    setIsTraining(true);
    setError(null);
    setTrainingResult(null);
    try {
      const data = await apiCall('POST', '/api/decision-support/train');
      setTrainingResult(data);
      // If the response includes model status information, update it
      if (data?.model_status) {
        setModelStatus(data.model_status);
      }
      return { success: true, data };
    } catch (err) {
      const message = err.response?.data?.message || err.message || 'Model training failed';
      setError(message);
      return { success: false, error: message };
    } finally {
      setIsTraining(false);
    }
  }, [apiCall]);

  /**
   * Log an appointment outcome for model feedback.
   * POST /api/decision-support/outcome
   *
   * @param {number|string} appointmentId
   * @param {string}        outcome   – e.g. "completed", "no_show", "cancelled"
   * @param {string|null}   feedback  – optional free-text feedback
   * @param {string|null}   reason    – optional structured reason code
   */
  const logOutcome = useCallback(async (appointmentId, outcome, feedback = null, reason = null) => {
    setLoading(true);
    setError(null);
    try {
      const payload = {
        appointment_id: appointmentId,
        outcome,
        ...(feedback !== null ? { feedback } : {}),
        ...(reason !== null ? { reason } : {}),
      };
      const data = await apiCall('POST', '/api/decision-support/outcome', payload);
      return { success: true, data };
    } catch (err) {
      const message = err.response?.data?.message || err.message || 'Failed to log outcome';
      setError(message);
      return { success: false, error: message };
    } finally {
      setLoading(false);
    }
  }, [apiCall]);

  /**
   * Get staff recommendations for a given date/time window.
   * GET /api/decision-support/staff-recommendations
   *
   * @param {string}      date        – ISO date string (YYYY-MM-DD)
   * @param {string}      time        – time string (HH:mm)
   * @param {string|null} serviceType – optional service type filter
   * @param {number|null} customerId  – optional customer context
   */
  const fetchStaffRecommendations = useCallback(async (date, time, serviceType = null, customerId = null) => {
    setLoading(true);
    setError(null);
    try {
      const params = {
        date,
        time,
        ...(serviceType !== null ? { service_type: serviceType } : {}),
        ...(customerId !== null ? { customer_id: customerId } : {}),
      };
      const data = await apiCall('GET', '/api/decision-support/staff-recommendations', null, params);
      return { success: true, data };
    } catch (err) {
      const message = err.response?.data?.message || err.message || 'Failed to fetch staff recommendations';
      setError(message);
      return { success: false, error: message };
    } finally {
      setLoading(false);
    }
  }, [apiCall]);

  /**
   * Get optimised time-slot recommendations for a date.
   * GET /api/decision-support/time-slot-recommendations
   *
   * @param {string} date            – ISO date string (YYYY-MM-DD)
   * @param {number} durationMinutes – slot length in minutes (default 30)
   */
  const fetchTimeSlotRecommendations = useCallback(async (date, durationMinutes = 30) => {
    setLoading(true);
    setError(null);
    try {
      const params = { date, duration_minutes: durationMinutes };
      const data = await apiCall('GET', '/api/decision-support/time-slot-recommendations', null, params);
      return { success: true, data };
    } catch (err) {
      const message = err.response?.data?.message || err.message || 'Failed to fetch time-slot recommendations';
      setError(message);
      return { success: false, error: message };
    } finally {
      setLoading(false);
    }
  }, [apiCall]);

  /**
   * Fetch the risk assessment for a specific appointment.
   * GET /api/decision-support/appointment-risk/{appointmentId}
   *
   * @param {number|string} appointmentId
   */
  const fetchAppointmentRisk = useCallback(async (appointmentId) => {
    setLoading(true);
    setError(null);
    try {
      const data = await apiCall('GET', `/api/decision-support/appointment-risk/${appointmentId}`);
      return { success: true, data };
    } catch (err) {
      const message = err.response?.data?.message || err.message || 'Failed to fetch appointment risk';
      setError(message);
      return { success: false, error: message };
    } finally {
      setLoading(false);
    }
  }, [apiCall]);

  /**
   * Fetch workload-optimisation suggestions for a given date.
   * GET /api/decision-support/workload-optimization
   *
   * @param {string} date – ISO date string (YYYY-MM-DD)
   */
  const fetchWorkloadOptimization = useCallback(async (date) => {
    setLoading(true);
    setError(null);
    try {
      const params = { date };
      const data = await apiCall('GET', '/api/decision-support/workload-optimization', null, params);
      return { success: true, data };
    } catch (err) {
      const message = err.response?.data?.message || err.message || 'Failed to fetch workload optimization';
      setError(message);
      return { success: false, error: message };
    } finally {
      setLoading(false);
    }
  }, [apiCall]);

  /**
   * Fetch the decision-support dashboard summary.
   * GET /api/decision-support/dashboard
   *
   * @param {string} date – ISO date string (YYYY-MM-DD)
   */
  const fetchDashboard = useCallback(async (date) => {
    setLoading(true);
    setError(null);
    try {
      const params = { date };
      const data = await apiCall('GET', '/api/decision-support/dashboard', null, params);
      // Persist model status if the dashboard response includes it
      if (data?.model_status) {
        setModelStatus(data.model_status);
      }
      return { success: true, data };
    } catch (err) {
      const message = err.response?.data?.message || err.message || 'Failed to fetch dashboard';
      setError(message);
      return { success: false, error: message };
    } finally {
      setLoading(false);
    }
  }, [apiCall]);

  /**
   * Fetch customer-specific insights for decision support.
   * GET /api/decision-support/customer-insights/{customerId}
   *
   * @param {number|string} customerId
   */
  const fetchCustomerInsights = useCallback(async (customerId) => {
    setLoading(true);
    setError(null);
    try {
      const data = await apiCall('GET', `/api/decision-support/customer-insights/${customerId}`);
      return { success: true, data };
    } catch (err) {
      const message = err.response?.data?.message || err.message || 'Failed to fetch customer insights';
      setError(message);
      return { success: false, error: message };
    } finally {
      setLoading(false);
    }
  }, [apiCall]);

  // -----------------------------------------------------------------------
  // Return public API
  // -----------------------------------------------------------------------
  return {
    // State
    loading,
    error,
    modelStatus,
    dataQuality,
    isTraining,
    trainingResult,

    // Actions
    fetchDataQuality,
    trainModel,
    logOutcome,
    fetchStaffRecommendations,
    fetchTimeSlotRecommendations,
    fetchAppointmentRisk,
    fetchWorkloadOptimization,
    fetchDashboard,
    fetchCustomerInsights,
  };
};
