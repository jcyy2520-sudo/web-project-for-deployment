import { useState, useEffect } from 'react';
import axios from 'axios';
import {
  ExclamationTriangleIcon,
  CheckCircleIcon,
  ClipboardDocumentCheckIcon,
} from '@heroicons/react/24/outline';

/**
 * AppointmentRiskAssessment Component
 * Displays risk assessment and mitigation recommendations for appointments
 */
const AppointmentRiskAssessment = ({ appointmentId, isDarkMode = true }) => {
  const [assessment, setAssessment] = useState(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);

  useEffect(() => {
    if (appointmentId) {
      fetchRiskAssessment();
    }
  }, [appointmentId]);

  const fetchRiskAssessment = async () => {
    setLoading(true);
    setError(null);

    try {
      const response = await axios.get(`/api/decision-support/appointment-risk/${appointmentId}`);
      setAssessment(response.data.data);
    } catch (err) {
      setError('Failed to fetch risk assessment');
      console.error(err);
    } finally {
      setLoading(false);
    }
  };

  const getRiskColor = (level) => {
    switch (level) {
      case 'high':
        return isDarkMode ? 'text-red-400' : 'text-red-600';
      case 'medium':
        return isDarkMode ? 'text-yellow-400' : 'text-yellow-600';
      case 'low':
        return isDarkMode ? 'text-green-400' : 'text-green-600';
      default:
        return isDarkMode ? 'text-gray-400' : 'text-gray-600';
    }
  };

  const getRiskBgColor = (level) => {
    switch (level) {
      case 'high':
        return isDarkMode ? 'bg-red-500/20' : 'bg-red-50';
      case 'medium':
        return isDarkMode ? 'bg-yellow-500/20' : 'bg-yellow-50';
      case 'low':
        return isDarkMode ? 'bg-green-500/20' : 'bg-green-50';
      default:
        return isDarkMode ? 'bg-gray-700' : 'bg-gray-100';
    }
  };

  const getRiskBorderColor = (level) => {
    switch (level) {
      case 'high':
        return isDarkMode ? 'border-red-500/30' : 'border-red-200';
      case 'medium':
        return isDarkMode ? 'border-yellow-500/30' : 'border-yellow-200';
      case 'low':
        return isDarkMode ? 'border-green-500/30' : 'border-green-200';
      default:
        return isDarkMode ? 'border-gray-600' : 'border-gray-200';
    }
  };

  if (loading) {
    return (
      <div className={`p-4 rounded-lg border ${isDarkMode ? 'bg-gray-800 border-gray-700' : 'bg-white border-gray-200'}`}>
        <div className="flex items-center justify-center py-4">
          <div className="animate-spin rounded-full h-5 w-5 border-b-2 border-amber-500"></div>
        </div>
      </div>
    );
  }

  if (error || !assessment) {
    return null;
  }

  const { risk_level, risk_score, risk_factors, recommendations } = assessment;

  return (
    <div className={`p-4 rounded-lg border ${getRiskBgColor(risk_level)} ${getRiskBorderColor(risk_level)}`}>
      {/* Header */}
      <div className="flex items-start justify-between pb-3 border-b" style={{borderColor: isDarkMode ? '#374151' : '#e5e7eb'}}>
        <div className="flex items-center gap-2">
          {risk_level === 'high' && <ExclamationTriangleIcon className="h-5 w-5 text-red-500" />}
          {risk_level === 'medium' && <ExclamationTriangleIcon className="h-5 w-5 text-yellow-500" />}
          {risk_level === 'low' && <CheckCircleIcon className="h-5 w-5 text-green-500" />}
          <h3 className={`text-sm font-semibold capitalize ${getRiskColor(risk_level)}`}>
            {risk_level} Risk Assessment
          </h3>
        </div>
        <div className={`text-2xl font-bold ${getRiskColor(risk_level)}`}>{risk_score}</div>
      </div>

      {/* Risk Factors */}
      {risk_factors && risk_factors.length > 0 && (
        <div className="mt-3 space-y-1">
          <p className={`text-xs font-semibold ${isDarkMode ? 'text-gray-300' : 'text-gray-700'} mb-2`}>Risk Factors:</p>
          {risk_factors.map((factor, idx) => (
            <div key={idx} className="flex items-start gap-2">
              <span className={`text-lg leading-none ${isDarkMode ? 'text-gray-500' : 'text-gray-400'}`}>•</span>
              <p className={`text-xs ${isDarkMode ? 'text-gray-300' : 'text-gray-700'}`}>{factor}</p>
            </div>
          ))}
        </div>
      )}

      {/* Recommendations */}
      {recommendations && recommendations.length > 0 && (
        <div className="mt-3 pt-3 border-t" style={{borderColor: isDarkMode ? '#374151' : '#e5e7eb'}}>
          <p className={`text-xs font-semibold ${isDarkMode ? 'text-gray-300' : 'text-gray-700'} mb-2`}>Recommended Actions:</p>
          <div className="space-y-1">
            {recommendations.map((rec, idx) => (
              <div
                key={idx}
                className={`flex items-start gap-2 p-2 rounded text-xs ${
                  rec.priority === 'high'
                    ? isDarkMode
                      ? 'bg-red-500/10'
                      : 'bg-red-100'
                    : isDarkMode
                    ? 'bg-gray-700/50'
                    : 'bg-gray-100'
                }`}
              >
                <ClipboardDocumentCheckIcon className={`h-4 w-4 mt-0.5 flex-shrink-0 ${
                  rec.priority === 'high'
                    ? isDarkMode ? 'text-red-400' : 'text-red-600'
                    : isDarkMode ? 'text-gray-400' : 'text-gray-600'
                }`} />
                <div>
                  <p className={`font-medium ${isDarkMode ? 'text-gray-100' : 'text-gray-900'}`}>
                    {rec.description}
                  </p>
                  {rec.priority && (
                    <p className={`text-xs mt-0.5 ${
                      rec.priority === 'high'
                        ? isDarkMode ? 'text-red-400' : 'text-red-600'
                        : isDarkMode ? 'text-gray-400' : 'text-gray-600'
                    }`}>
                      Priority: {rec.priority}
                    </p>
                  )}
                </div>
              </div>
            ))}
          </div>
        </div>
      )}
    </div>
  );
};

export default AppointmentRiskAssessment;
