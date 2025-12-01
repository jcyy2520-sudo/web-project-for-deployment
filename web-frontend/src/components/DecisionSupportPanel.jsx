import { useState, useEffect } from 'react';
import axios from 'axios';
import {
  SparklesIcon,
  LightBulbIcon,
  CheckCircleIcon,
  ExclamationIcon,
  ChartBarIcon,
  ClockIcon
} from '@heroicons/react/24/outline';

/**
 * DecisionSupportPanel Component
 * Displays AI-powered recommendations for staff assignments and scheduling
 */
const DecisionSupportPanel = ({ appointmentDate, appointmentTime, serviceType, customerId, isDarkMode = true }) => {
  const [staffRecommendations, setStaffRecommendations] = useState(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);

  useEffect(() => {
    if (appointmentDate && appointmentTime) {
      fetchStaffRecommendations();
    }
  }, [appointmentDate, appointmentTime, serviceType, customerId]);

  const fetchStaffRecommendations = async () => {
    setLoading(true);
    setError(null);

    try {
      const response = await axios.get('/api/decision-support/staff-recommendations', {
        params: {
          appointment_date: appointmentDate,
          appointment_time: appointmentTime,
          service_type: serviceType,
          customer_id: customerId,
        },
      });

      setStaffRecommendations(response.data.data);
    } catch (err) {
      setError('Failed to fetch recommendations');
      console.error(err);
    } finally {
      setLoading(false);
    }
  };

  const getScoreColor = (score) => {
    if (score >= 80) return isDarkMode ? 'text-green-400' : 'text-green-600';
    if (score >= 60) return isDarkMode ? 'text-yellow-400' : 'text-yellow-600';
    return isDarkMode ? 'text-red-400' : 'text-red-600';
  };

  const getScoreBgColor = (score) => {
    if (score >= 80) return isDarkMode ? 'bg-green-500/20' : 'bg-green-50';
    if (score >= 60) return isDarkMode ? 'bg-yellow-500/20' : 'bg-yellow-50';
    return isDarkMode ? 'bg-red-500/20' : 'bg-red-50';
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

  if (!staffRecommendations || staffRecommendations.length === 0) {
    return null;
  }

  return (
    <div className={`p-4 rounded-lg border space-y-3 ${isDarkMode ? 'bg-gray-800 border-gray-700' : 'bg-white border-gray-200'}`}>
      {/* Header */}
      <div className="flex items-center gap-2 pb-3 border-b" style={{borderColor: isDarkMode ? '#374151' : '#e5e7eb'}}>
        <SparklesIcon className="h-5 w-5 text-amber-500" />
        <h3 className={`text-sm font-semibold ${isDarkMode ? 'text-amber-50' : 'text-amber-900'}`}>
          Recommended Staff
        </h3>
      </div>

      {/* Recommendations */}
      <div className="space-y-2">
        {staffRecommendations.map((staff, index) => (
          <div
            key={staff.staff_id}
            className={`p-3 rounded-lg border transition-colors ${
              index === 0
                ? isDarkMode
                  ? 'bg-amber-500/10 border-amber-500/30'
                  : 'bg-amber-50 border-amber-200'
                : isDarkMode
                ? 'bg-gray-700 border-gray-600'
                : 'bg-gray-50 border-gray-200'
            }`}
          >
            <div className="flex items-start justify-between gap-2">
              <div className="flex-1">
                <div className="flex items-center gap-2">
                  <p className={`text-sm font-medium ${isDarkMode ? 'text-gray-100' : 'text-gray-900'}`}>
                    {staff.name}
                  </p>
                  {index === 0 && (
                    <span className={`text-xs font-semibold px-2 py-0.5 rounded-full ${isDarkMode ? 'bg-amber-500/30 text-amber-200' : 'bg-amber-200 text-amber-800'}`}>
                      Best Match
                    </span>
                  )}
                </div>
                <p className={`text-xs mt-1 ${isDarkMode ? 'text-gray-400' : 'text-gray-600'}`}>{staff.email}</p>
              </div>

              {/* Score */}
              <div className={`flex flex-col items-end ${getScoreBgColor(staff.score)} px-3 py-1 rounded`}>
                <p className={`text-sm font-bold ${getScoreColor(staff.score)}`}>{Math.round(staff.score)}</p>
                <p className={`text-xs ${isDarkMode ? 'text-gray-400' : 'text-gray-600'}`}>Match</p>
              </div>
            </div>

            {/* Reasoning */}
            {staff.reasoning && staff.reasoning.length > 0 && (
              <div className="mt-2 space-y-1">
                {staff.reasoning.map((reason, idx) => (
                  <div key={idx} className="flex items-start gap-2">
                    <CheckCircleIcon className={`h-3 w-3 mt-0.5 flex-shrink-0 ${isDarkMode ? 'text-green-400' : 'text-green-600'}`} />
                    <p className={`text-xs ${isDarkMode ? 'text-gray-300' : 'text-gray-700'}`}>{reason}</p>
                  </div>
                ))}
              </div>
            )}
          </div>
        ))}
      </div>

      <p className={`text-xs mt-3 ${isDarkMode ? 'text-gray-500' : 'text-gray-500'}`}>
        💡 Recommendations are based on availability, workload, expertise, and performance history.
      </p>
    </div>
  );
};

export default DecisionSupportPanel;
