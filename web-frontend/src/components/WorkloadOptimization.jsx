import { useState, useEffect } from 'react';
import axios from 'axios';
import {
  UserGroupIcon,
  CheckCircleIcon,
  ExclamationIcon,
} from '@heroicons/react/24/outline';

/**
 * WorkloadOptimization Component
 * Displays staff workload balance and optimization recommendations
 */
const WorkloadOptimization = ({ appointmentDate, isDarkMode = true }) => {
  const [recommendations, setRecommendations] = useState(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);

  useEffect(() => {
    if (appointmentDate) {
      fetchWorkloadOptimization();
    }
  }, [appointmentDate]);

  const fetchWorkloadOptimization = async () => {
    setLoading(true);
    setError(null);

    try {
      const response = await axios.get('/api/decision-support/workload-optimization', {
        params: {
          appointment_date: appointmentDate,
        },
      });

      setRecommendations(response.data.data);
    } catch (err) {
      setError('Failed to fetch workload data');
      console.error(err);
    } finally {
      setLoading(false);
    }
  };

  const getStatusColor = (status) => {
    switch (status) {
      case 'available':
        return isDarkMode ? 'text-green-400' : 'text-green-600';
      case 'busy':
        return isDarkMode ? 'text-yellow-400' : 'text-yellow-600';
      case 'overloaded':
        return isDarkMode ? 'text-red-400' : 'text-red-600';
      default:
        return isDarkMode ? 'text-gray-400' : 'text-gray-600';
    }
  };

  const getStatusBgColor = (status) => {
    switch (status) {
      case 'available':
        return isDarkMode ? 'bg-green-500/20' : 'bg-green-50';
      case 'busy':
        return isDarkMode ? 'bg-yellow-500/20' : 'bg-yellow-50';
      case 'overloaded':
        return isDarkMode ? 'bg-red-500/20' : 'bg-red-50';
      default:
        return isDarkMode ? 'bg-gray-700' : 'bg-gray-100';
    }
  };

  const getProgressColor = (status) => {
    switch (status) {
      case 'available':
        return 'bg-green-500';
      case 'busy':
        return 'bg-yellow-500';
      case 'overloaded':
        return 'bg-red-500';
      default:
        return 'bg-gray-500';
    }
  };

  if (loading) {
    return (
      <div className={`p-4 rounded-lg border ${isDarkMode ? 'bg-gray-800 border-gray-700' : 'bg-white border-gray-200'}`}>
        <div className="flex items-center justify-center py-6">
          <div className="animate-spin rounded-full h-6 w-6 border-b-2 border-amber-500"></div>
        </div>
      </div>
    );
  }

  if (error) {
    return (
      <div className={`p-4 rounded-lg border ${isDarkMode ? 'bg-red-500/10 border-red-500/30' : 'bg-red-50 border-red-200'}`}>
        <p className={`text-sm ${isDarkMode ? 'text-red-400' : 'text-red-600'}`}>{error}</p>
      </div>
    );
  }

  if (!recommendations || recommendations.length === 0) {
    return null;
  }

  const totalSlots = recommendations.reduce((sum, staff) => sum + staff.available_slots, 0);
  const averageLoad = (recommendations.reduce((sum, staff) => sum + staff.appointments_scheduled, 0) / recommendations.length).toFixed(1);

  return (
    <div className={`p-4 rounded-lg border space-y-4 ${isDarkMode ? 'bg-gray-800 border-gray-700' : 'bg-white border-gray-200'}`}>
      {/* Header */}
      <div className="flex items-center gap-2 pb-3 border-b" style={{borderColor: isDarkMode ? '#374151' : '#e5e7eb'}}>
        <UserGroupIcon className="h-5 w-5 text-amber-500" />
        <h3 className={`text-sm font-semibold ${isDarkMode ? 'text-amber-50' : 'text-amber-900'}`}>
          Staff Workload Overview
        </h3>
      </div>

      {/* Summary Stats */}
      <div className="grid grid-cols-3 gap-2">
        <div className={`p-2 rounded-lg ${isDarkMode ? 'bg-gray-700' : 'bg-gray-100'}`}>
          <p className={`text-xs ${isDarkMode ? 'text-gray-400' : 'text-gray-600'}`}>Total Available</p>
          <p className={`text-lg font-bold ${isDarkMode ? 'text-green-400' : 'text-green-600'}`}>{totalSlots}</p>
        </div>
        <div className={`p-2 rounded-lg ${isDarkMode ? 'bg-gray-700' : 'bg-gray-100'}`}>
          <p className={`text-xs ${isDarkMode ? 'text-gray-400' : 'text-gray-600'}`}>Avg Load</p>
          <p className={`text-lg font-bold ${isDarkMode ? 'text-yellow-400' : 'text-yellow-600'}`}>{averageLoad}</p>
        </div>
        <div className={`p-2 rounded-lg ${isDarkMode ? 'bg-gray-700' : 'bg-gray-100'}`}>
          <p className={`text-xs ${isDarkMode ? 'text-gray-400' : 'text-gray-600'}`}>Staff Count</p>
          <p className={`text-lg font-bold ${isDarkMode ? 'text-blue-400' : 'text-blue-600'}`}>{recommendations.length}</p>
        </div>
      </div>

      {/* Staff List */}
      <div className="space-y-2">
        {recommendations.map((staff) => (
          <div
            key={staff.staff_id}
            className={`p-3 rounded-lg border ${getStatusBgColor(staff.status)} ${
              isDarkMode ? 'border-gray-600' : 'border-gray-200'
            }`}
          >
            {/* Staff Name and Status */}
            <div className="flex items-center justify-between mb-2">
              <div>
                <p className={`text-sm font-medium ${isDarkMode ? 'text-gray-100' : 'text-gray-900'}`}>
                  {staff.staff_name}
                </p>
              </div>
              <span className={`text-xs font-semibold px-2 py-1 rounded capitalize ${getStatusColor(staff.status)} ${getStatusBgColor(staff.status)}`}>
                {staff.status}
              </span>
            </div>

            {/* Appointments Info */}
            <div className="flex items-center justify-between text-xs mb-2">
              <span className={isDarkMode ? 'text-gray-400' : 'text-gray-600'}>
                {staff.appointments_scheduled} appointments
              </span>
              <span className={isDarkMode ? 'text-gray-400' : 'text-gray-600'}>
                {staff.available_slots} slots available
              </span>
            </div>

            {/* Progress Bar */}
            <div className={`w-full h-2 rounded-full overflow-hidden ${isDarkMode ? 'bg-gray-700' : 'bg-gray-300'}`}>
              <div
                className={`h-full transition-all ${getProgressColor(staff.status)}`}
                style={{width: `${staff.capacity_percentage}%`}}
              ></div>
            </div>

            {/* Capacity Percentage */}
            <p className={`text-xs mt-1 ${isDarkMode ? 'text-gray-400' : 'text-gray-600'}`}>
              Capacity: {Math.round(staff.capacity_percentage)}%
            </p>
          </div>
        ))}
      </div>

      {/* Recommendations */}
      <div className={`p-3 rounded-lg ${isDarkMode ? 'bg-blue-500/10' : 'bg-blue-50'} border ${isDarkMode ? 'border-blue-500/30' : 'border-blue-200'}`}>
        <p className={`text-xs font-semibold ${isDarkMode ? 'text-blue-300' : 'text-blue-700'}`}>
          💡 Assign new appointments to: {recommendations[0]?.staff_name || 'N/A'}
        </p>
        <p className={`text-xs mt-1 ${isDarkMode ? 'text-blue-200' : 'text-blue-600'}`}>
          Most available capacity ({recommendations[0]?.available_slots || 0} slots)
        </p>
      </div>
    </div>
  );
};

export default WorkloadOptimization;
