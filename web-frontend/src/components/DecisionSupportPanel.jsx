import { useState, useEffect } from 'react';
import axios from 'axios';
import {
  SparklesIcon,
  CheckCircleIcon,
  ChevronDownIcon,
  ChevronUpIcon,
  ShieldCheckIcon,
  ExclamationTriangleIcon,
  UserIcon,
  InformationCircleIcon,
} from '@heroicons/react/24/outline';

/**
 * DecisionSupportPanel Component
 * 
 * Displays AI-powered staff recommendations with:
 * - Score breakdown per criterion (visual bars)
 * - Confidence indicator
 * - Strengths & considerations
 * - Scoring criteria explanation
 */
const DecisionSupportPanel = ({ appointmentDate, appointmentTime, serviceType, customerId, isDarkMode = true }) => {
  const [staffRecommendations, setStaffRecommendations] = useState(null);
  const [meta, setMeta] = useState(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);
  const [expandedStaff, setExpandedStaff] = useState(null);
  const [showCriteria, setShowCriteria] = useState(false);

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
      setMeta(response.data.meta || null);
    } catch (err) {
      setError('Failed to fetch recommendations');
      console.error(err);
    } finally {
      setLoading(false);
    }
  };

  const getScoreColor = (pct) => {
    if (pct >= 75) return isDarkMode ? 'text-green-400' : 'text-green-600';
    if (pct >= 50) return isDarkMode ? 'text-amber-400' : 'text-amber-600';
    return isDarkMode ? 'text-red-400' : 'text-red-600';
  };

  const getScoreBarColor = (pct) => {
    if (pct >= 75) return 'bg-green-500';
    if (pct >= 50) return 'bg-amber-500';
    return 'bg-red-500';
  };

  const getConfidenceDisplay = (level) => {
    const configs = {
      high: { label: 'High confidence', color: isDarkMode ? 'text-green-400' : 'text-green-600', icon: ShieldCheckIcon },
      medium: { label: 'Medium confidence', color: isDarkMode ? 'text-amber-400' : 'text-amber-600', icon: InformationCircleIcon },
      low: { label: 'Low confidence', color: isDarkMode ? 'text-gray-400' : 'text-gray-500', icon: ExclamationTriangleIcon },
    };
    return configs[level] || configs.medium;
  };

  if (loading) {
    return (
      <div className={`p-4 rounded-xl border ${isDarkMode ? 'bg-gray-800 border-gray-700' : 'bg-white border-gray-200'}`}>
        <div className="flex items-center justify-center py-6 gap-3">
          <div className="animate-spin rounded-full h-5 w-5 border-2 border-t-transparent border-amber-500"></div>
          <p className={`text-sm ${isDarkMode ? 'text-gray-400' : 'text-gray-500'}`}>Analyzing staff availability...</p>
        </div>
      </div>
    );
  }

  if (error) {
    return (
      <div className={`p-4 rounded-xl border ${isDarkMode ? 'bg-red-500/10 border-red-500/30' : 'bg-red-50 border-red-200'}`}>
        <p className={`text-sm ${isDarkMode ? 'text-red-400' : 'text-red-600'}`}>{error}</p>
      </div>
    );
  }

  if (!staffRecommendations || staffRecommendations.length === 0) {
    return null;
  }

  return (
    <div className={`rounded-xl border space-y-0 overflow-hidden ${isDarkMode ? 'bg-gray-800 border-gray-700' : 'bg-white border-gray-200'}`}>
      {/* Header */}
      <div className={`px-4 py-3 border-b flex items-center justify-between ${isDarkMode ? 'border-gray-700 bg-gray-800' : 'border-gray-200 bg-gray-50'}`}>
        <div className="flex items-center gap-2">
          <SparklesIcon className="h-5 w-5 text-amber-500" />
          <h3 className={`text-sm font-bold ${isDarkMode ? 'text-amber-50' : 'text-amber-900'}`}>
            Staff Recommendations
          </h3>
        </div>
        {meta && (
          <span className={`text-xs ${isDarkMode ? 'text-gray-400' : 'text-gray-500'}`}>
            {meta.available_staff}/{meta.total_staff} available
          </span>
        )}
      </div>

      {/* Recommendations */}
      <div className="divide-y" style={{ borderColor: isDarkMode ? '#374151' : '#e5e7eb' }}>
        {staffRecommendations.map((staff, index) => {
          const isExpanded = expandedStaff === staff.staff_id;
          const pct = staff.score_percentage || (staff.max_score > 0 ? Math.round((staff.score / staff.max_score) * 100) : staff.score);
          const conf = getConfidenceDisplay(staff.confidence);

          return (
            <div
              key={staff.staff_id}
              className={`transition-colors ${
                index === 0
                  ? isDarkMode ? 'bg-amber-500/5' : 'bg-amber-50/50'
                  : ''
              }`}
            >
              {/* Main Row */}
              <button
                onClick={() => setExpandedStaff(isExpanded ? null : staff.staff_id)}
                className={`w-full px-4 py-3 flex items-center gap-3 text-left hover:bg-opacity-50 transition-colors ${
                  isDarkMode ? 'hover:bg-gray-700' : 'hover:bg-gray-50'
                }`}
              >
                {/* Rank Badge */}
                <div className={`w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold flex-shrink-0 ${
                  index === 0
                    ? 'bg-amber-500 text-white'
                    : index === 1
                    ? isDarkMode ? 'bg-gray-600 text-gray-200' : 'bg-gray-200 text-gray-700'
                    : isDarkMode ? 'bg-gray-700 text-gray-400' : 'bg-gray-100 text-gray-500'
                }`}>
                  {index + 1}
                </div>

                {/* Name & Info */}
                <div className="flex-1 min-w-0">
                  <div className="flex items-center gap-2">
                    <p className={`text-sm font-semibold truncate ${isDarkMode ? 'text-gray-100' : 'text-gray-900'}`}>
                      {staff.name}
                    </p>
                    {index === 0 && (
                      <span className={`text-xs font-bold px-2 py-0.5 rounded-full ${isDarkMode ? 'bg-amber-500/20 text-amber-300' : 'bg-amber-100 text-amber-700'}`}>
                        Best Match
                      </span>
                    )}
                    {!staff.available && (
                      <span className={`text-xs font-medium px-2 py-0.5 rounded-full ${isDarkMode ? 'bg-red-500/20 text-red-300' : 'bg-red-100 text-red-600'}`}>
                        Unavailable
                      </span>
                    )}
                  </div>
                  <p className={`text-xs truncate mt-0.5 ${isDarkMode ? 'text-gray-400' : 'text-gray-500'}`}>
                    {staff.email}
                  </p>
                </div>

                {/* Score */}
                <div className="flex items-center gap-3 flex-shrink-0">
                  <div className="text-right">
                    <p className={`text-lg font-bold ${getScoreColor(pct)}`}>{pct}%</p>
                    <p className={`text-xs ${isDarkMode ? 'text-gray-500' : 'text-gray-400'}`}>match</p>
                  </div>
                  {isExpanded
                    ? <ChevronUpIcon className={`h-4 w-4 ${isDarkMode ? 'text-gray-400' : 'text-gray-500'}`} />
                    : <ChevronDownIcon className={`h-4 w-4 ${isDarkMode ? 'text-gray-400' : 'text-gray-500'}`} />
                  }
                </div>
              </button>

              {/* Expanded Details */}
              {isExpanded && (
                <div className={`px-4 pb-4 pt-1 space-y-3 ${isDarkMode ? 'bg-gray-800/50' : 'bg-gray-50/50'}`}>
                  {/* Score Breakdown */}
                  {staff.details && Object.keys(staff.details).length > 0 && (
                    <div>
                      <p className={`text-xs font-semibold mb-2 ${isDarkMode ? 'text-gray-300' : 'text-gray-700'}`}>Score Breakdown</p>
                      <div className="space-y-2">
                        {Object.entries(staff.details).map(([key, detail]) => {
                          const val = typeof detail === 'object' ? detail.score : detail;
                          const max = typeof detail === 'object' ? detail.max : 25;
                          const barPct = max > 0 ? Math.round((val / max) * 100) : 0;
                          const label = key.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase());

                          return (
                            <div key={key}>
                              <div className="flex items-center justify-between text-xs mb-1">
                                <span className={isDarkMode ? 'text-gray-400' : 'text-gray-600'}>{label}</span>
                                <span className={`font-medium ${isDarkMode ? 'text-gray-300' : 'text-gray-700'}`}>{val}/{max}</span>
                              </div>
                              <div className={`w-full h-1.5 rounded-full overflow-hidden ${isDarkMode ? 'bg-gray-700' : 'bg-gray-200'}`}>
                                <div
                                  className={`h-full rounded-full transition-all ${getScoreBarColor(barPct)}`}
                                  style={{ width: `${barPct}%` }}
                                />
                              </div>
                            </div>
                          );
                        })}
                      </div>
                    </div>
                  )}

                  {/* Confidence */}
                  {staff.confidence && staff.confidence !== 'n/a' && (
                    <div className="flex items-center gap-2">
                      <conf.icon className={`h-4 w-4 ${conf.color}`} />
                      <span className={`text-xs font-medium ${conf.color}`}>{conf.label}</span>
                    </div>
                  )}

                  {/* Strengths */}
                  {staff.strengths && staff.strengths.length > 0 && (
                    <div>
                      <p className={`text-xs font-semibold mb-1 ${isDarkMode ? 'text-green-400' : 'text-green-700'}`}>Strengths</p>
                      <div className="flex flex-wrap gap-1.5">
                        {staff.strengths.map((s, i) => (
                          <span key={i} className={`text-xs px-2 py-0.5 rounded-full ${isDarkMode ? 'bg-green-500/15 text-green-300' : 'bg-green-50 text-green-700 border border-green-200'}`}>
                            {s}
                          </span>
                        ))}
                      </div>
                    </div>
                  )}

                  {/* Considerations */}
                  {staff.considerations && staff.considerations.length > 0 && (
                    <div>
                      <p className={`text-xs font-semibold mb-1 ${isDarkMode ? 'text-amber-400' : 'text-amber-700'}`}>Considerations</p>
                      <div className="flex flex-wrap gap-1.5">
                        {staff.considerations.map((c, i) => (
                          <span key={i} className={`text-xs px-2 py-0.5 rounded-full ${isDarkMode ? 'bg-amber-500/15 text-amber-300' : 'bg-amber-50 text-amber-700 border border-amber-200'}`}>
                            {c}
                          </span>
                        ))}
                      </div>
                    </div>
                  )}

                  {/* Reasoning */}
                  {staff.reasoning && staff.reasoning.length > 0 && (
                    <div className="space-y-1">
                      {staff.reasoning.map((reason, idx) => (
                        <div key={idx} className="flex items-start gap-2">
                          <CheckCircleIcon className={`h-3.5 w-3.5 mt-0.5 flex-shrink-0 ${isDarkMode ? 'text-green-400' : 'text-green-600'}`} />
                          <p className={`text-xs ${isDarkMode ? 'text-gray-300' : 'text-gray-700'}`}>{reason}</p>
                        </div>
                      ))}
                    </div>
                  )}
                </div>
              )}
            </div>
          );
        })}
      </div>

      {/* Scoring Criteria Toggle */}
      {meta?.scoring_criteria && (
        <div className={`border-t ${isDarkMode ? 'border-gray-700' : 'border-gray-200'}`}>
          <button
            onClick={() => setShowCriteria(!showCriteria)}
            className={`w-full px-4 py-2.5 flex items-center justify-between text-xs transition-colors ${
              isDarkMode ? 'text-gray-400 hover:bg-gray-700' : 'text-gray-500 hover:bg-gray-50'
            }`}
          >
            <span className="flex items-center gap-1.5">
              <InformationCircleIcon className="h-3.5 w-3.5" />
              How are scores calculated?
            </span>
            {showCriteria ? <ChevronUpIcon className="h-3.5 w-3.5" /> : <ChevronDownIcon className="h-3.5 w-3.5" />}
          </button>
          {showCriteria && (
            <div className={`px-4 pb-3 space-y-2`}>
              {meta.scoring_criteria.map((criterion, idx) => (
                <div key={idx} className="flex items-start gap-2">
                  <span className={`text-xs font-bold w-16 flex-shrink-0 ${isDarkMode ? 'text-amber-400' : 'text-amber-600'}`}>
                    {criterion.weight}
                  </span>
                  <div>
                    <p className={`text-xs font-medium ${isDarkMode ? 'text-gray-200' : 'text-gray-700'}`}>{criterion.name}</p>
                    <p className={`text-xs ${isDarkMode ? 'text-gray-500' : 'text-gray-400'}`}>{criterion.description}</p>
                  </div>
                </div>
              ))}
            </div>
          )}
        </div>
      )}
    </div>
  );
};

export default DecisionSupportPanel;
