import { useState, useEffect } from 'react';
import axios from 'axios';
import {
  ExclamationTriangleIcon,
  CheckCircleIcon,
  ClipboardDocumentCheckIcon,
  ShieldCheckIcon,
  UserIcon,
  ChevronDownIcon,
  ChevronUpIcon,
  ArrowPathIcon,
  BellAlertIcon,
  CalendarDaysIcon,
  HandThumbUpIcon,
} from '@heroicons/react/24/outline';

/**
 * AppointmentRiskAssessment Component
 *
 * Displays comprehensive risk assessment with:
 * - Risk level header with score gauge
 * - Structured risk factors with impact/points
 * - Positive factors section
 * - Customer history stats
 * - Actionable recommendations with icons & priority
 */
const AppointmentRiskAssessment = ({ appointmentId, isDarkMode = true }) => {
  const [assessment, setAssessment] = useState(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);
  const [showAllFactors, setShowAllFactors] = useState(false);
  const [showRecommendations, setShowRecommendations] = useState(true);

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

  const getRiskConfig = (level) => {
    const configs = {
      high: {
        label: 'High Risk',
        icon: <ExclamationTriangleIcon className="h-5 w-5" />,
        text: isDarkMode ? 'text-red-400' : 'text-red-600',
        bg: isDarkMode ? 'bg-red-500/10' : 'bg-red-50',
        border: isDarkMode ? 'border-red-500/30' : 'border-red-200',
        headerBg: isDarkMode ? 'bg-red-500/15' : 'bg-red-50',
        bar: 'bg-red-500',
        gaugeBg: isDarkMode ? 'bg-red-500/20' : 'bg-red-100',
      },
      medium: {
        label: 'Medium Risk',
        icon: <ExclamationTriangleIcon className="h-5 w-5" />,
        text: isDarkMode ? 'text-amber-400' : 'text-amber-600',
        bg: isDarkMode ? 'bg-amber-500/10' : 'bg-amber-50',
        border: isDarkMode ? 'border-amber-500/30' : 'border-amber-200',
        headerBg: isDarkMode ? 'bg-amber-500/15' : 'bg-amber-50',
        bar: 'bg-amber-500',
        gaugeBg: isDarkMode ? 'bg-amber-500/20' : 'bg-amber-100',
      },
      low: {
        label: 'Low Risk',
        icon: <ShieldCheckIcon className="h-5 w-5" />,
        text: isDarkMode ? 'text-green-400' : 'text-green-600',
        bg: isDarkMode ? 'bg-green-500/10' : 'bg-green-50',
        border: isDarkMode ? 'border-green-500/30' : 'border-green-200',
        headerBg: isDarkMode ? 'bg-green-500/15' : 'bg-green-50',
        bar: 'bg-green-500',
        gaugeBg: isDarkMode ? 'bg-green-500/20' : 'bg-green-100',
      },
    };
    return configs[level] || configs.low;
  };

  const getImpactBadge = (impact) => {
    const configs = {
      high: isDarkMode ? 'bg-red-500/20 text-red-300 border-red-500/30' : 'bg-red-50 text-red-700 border-red-200',
      medium: isDarkMode ? 'bg-amber-500/20 text-amber-300 border-amber-500/30' : 'bg-amber-50 text-amber-700 border-amber-200',
      low: isDarkMode ? 'bg-blue-500/20 text-blue-300 border-blue-500/30' : 'bg-blue-50 text-blue-700 border-blue-200',
    };
    const cls = configs[impact] || configs.low;
    return <span className={`text-xs font-medium px-1.5 py-0.5 rounded border ${cls}`}>{impact}</span>;
  };

  const getRecIcon = (icon) => {
    const iconMap = {
      bell: <BellAlertIcon className="h-4 w-4" />,
      calendar: <CalendarDaysIcon className="h-4 w-4" />,
      refresh: <ArrowPathIcon className="h-4 w-4" />,
      check: <CheckCircleIcon className="h-4 w-4" />,
      clipboard: <ClipboardDocumentCheckIcon className="h-4 w-4" />,
      user: <UserIcon className="h-4 w-4" />,
    };
    return iconMap[icon] || <ClipboardDocumentCheckIcon className="h-4 w-4" />;
  };

  if (loading) {
    return (
      <div className={`p-4 rounded-xl border ${isDarkMode ? 'bg-gray-800 border-gray-700' : 'bg-white border-gray-200'}`}>
        <div className="flex items-center justify-center py-4 gap-3">
          <div className="animate-spin rounded-full h-5 w-5 border-2 border-t-transparent border-amber-500"></div>
          <p className={`text-sm ${isDarkMode ? 'text-gray-400' : 'text-gray-500'}`}>Assessing risk...</p>
        </div>
      </div>
    );
  }

  if (error || !assessment) {
    return null;
  }

  const {
    risk_level,
    risk_score,
    max_risk_score,
    risk_factors = [],
    positive_factors = [],
    customer_stats,
    recommendations = [],
  } = assessment;

  const config = getRiskConfig(risk_level);
  const scorePercent = max_risk_score ? Math.min((risk_score / max_risk_score) * 100, 100) : 0;

  // Support both old format (string array) and new format (object array)
  const structuredFactors = risk_factors.map((f) =>
    typeof f === 'string' ? { factor: f, detail: '', impact: 'medium', points: 0 } : f
  );

  const visibleFactors = showAllFactors ? structuredFactors : structuredFactors.slice(0, 3);

  return (
    <div className={`rounded-xl border overflow-hidden ${config.bg} ${config.border}`}>
      {/* Risk Level Header */}
      <div className={`px-4 py-3 ${config.headerBg}`}>
        <div className="flex items-center justify-between">
          <div className="flex items-center gap-2">
            <span className={config.text}>{config.icon}</span>
            <h3 className={`text-sm font-bold capitalize ${config.text}`}>
              {config.label}
            </h3>
          </div>
          <div className="flex items-center gap-2">
            <span className={`text-2xl font-black ${config.text}`}>{risk_score}</span>
            {max_risk_score && (
              <span className={`text-xs ${isDarkMode ? 'text-gray-400' : 'text-gray-500'}`}>/ {max_risk_score}</span>
            )}
          </div>
        </div>

        {/* Score Gauge Bar */}
        {max_risk_score && (
          <div className={`w-full h-1.5 rounded-full overflow-hidden mt-2 ${isDarkMode ? 'bg-gray-700' : 'bg-gray-200'}`}>
            <div
              className={`h-full rounded-full transition-all ${config.bar}`}
              style={{ width: `${scorePercent}%` }}
            />
          </div>
        )}
      </div>

      {/* Customer Stats Mini Card */}
      {customer_stats && (
        <div className={`px-4 py-2.5 border-t ${isDarkMode ? 'border-gray-700/50' : 'border-gray-200/80'}`}>
          <div className="flex items-center gap-1.5 mb-2">
            <UserIcon className={`h-3.5 w-3.5 ${isDarkMode ? 'text-gray-400' : 'text-gray-500'}`} />
            <span className={`text-xs font-bold ${isDarkMode ? 'text-gray-300' : 'text-gray-600'}`}>Customer History</span>
          </div>
          <div className="grid grid-cols-3 gap-2">
            {customer_stats.total_appointments !== undefined && (
              <div className={`text-center p-1.5 rounded ${isDarkMode ? 'bg-gray-700/40' : 'bg-white/80'}`}>
                <p className={`text-xs ${isDarkMode ? 'text-gray-400' : 'text-gray-500'}`}>Total</p>
                <p className={`text-sm font-bold ${isDarkMode ? 'text-gray-200' : 'text-gray-700'}`}>{customer_stats.total_appointments}</p>
              </div>
            )}
            {customer_stats.completed !== undefined && (
              <div className={`text-center p-1.5 rounded ${isDarkMode ? 'bg-gray-700/40' : 'bg-white/80'}`}>
                <p className={`text-xs ${isDarkMode ? 'text-gray-400' : 'text-gray-500'}`}>Completed</p>
                <p className={`text-sm font-bold ${isDarkMode ? 'text-green-400' : 'text-green-600'}`}>{customer_stats.completed}</p>
              </div>
            )}
            {customer_stats.cancelled !== undefined && (
              <div className={`text-center p-1.5 rounded ${isDarkMode ? 'bg-gray-700/40' : 'bg-white/80'}`}>
                <p className={`text-xs ${isDarkMode ? 'text-gray-400' : 'text-gray-500'}`}>Cancelled</p>
                <p className={`text-sm font-bold ${isDarkMode ? 'text-red-400' : 'text-red-600'}`}>{customer_stats.cancelled}</p>
              </div>
            )}
            {customer_stats.no_shows !== undefined && (
              <div className={`text-center p-1.5 rounded ${isDarkMode ? 'bg-gray-700/40' : 'bg-white/80'}`}>
                <p className={`text-xs ${isDarkMode ? 'text-gray-400' : 'text-gray-500'}`}>No Shows</p>
                <p className={`text-sm font-bold ${isDarkMode ? 'text-amber-400' : 'text-amber-600'}`}>{customer_stats.no_shows}</p>
              </div>
            )}
            {customer_stats.cancel_rate !== undefined && (
              <div className={`text-center p-1.5 rounded ${isDarkMode ? 'bg-gray-700/40' : 'bg-white/80'}`}>
                <p className={`text-xs ${isDarkMode ? 'text-gray-400' : 'text-gray-500'}`}>Cancel Rate</p>
                <p className={`text-sm font-bold ${
                  customer_stats.cancel_rate > 30
                    ? isDarkMode ? 'text-red-400' : 'text-red-600'
                    : isDarkMode ? 'text-gray-200' : 'text-gray-700'
                }`}>{customer_stats.cancel_rate}%</p>
              </div>
            )}
          </div>
        </div>
      )}

      {/* Risk Factors */}
      {structuredFactors.length > 0 && (
        <div className={`px-4 py-3 border-t ${isDarkMode ? 'border-gray-700/50' : 'border-gray-200/80'}`}>
          <p className={`text-xs font-bold mb-2 ${isDarkMode ? 'text-gray-300' : 'text-gray-700'}`}>
            Risk Factors ({structuredFactors.length})
          </p>
          <div className="space-y-1.5">
            {visibleFactors.map((factor, idx) => (
              <div
                key={idx}
                className={`flex items-start justify-between gap-2 p-2 rounded-lg ${isDarkMode ? 'bg-gray-700/30' : 'bg-white/60'}`}
              >
                <div className="flex items-start gap-2 flex-1 min-w-0">
                  <ExclamationTriangleIcon className={`h-4 w-4 mt-0.5 flex-shrink-0 ${isDarkMode ? 'text-red-400' : 'text-red-500'}`} />
                  <div className="min-w-0">
                    <p className={`text-xs font-medium ${isDarkMode ? 'text-gray-200' : 'text-gray-800'}`}>
                      {factor.factor}
                    </p>
                    {factor.detail && (
                      <p className={`text-xs mt-0.5 ${isDarkMode ? 'text-gray-400' : 'text-gray-500'}`}>
                        {factor.detail}
                      </p>
                    )}
                  </div>
                </div>
                <div className="flex items-center gap-1.5 flex-shrink-0">
                  {factor.impact && getImpactBadge(factor.impact)}
                  {factor.points > 0 && (
                    <span className={`text-xs font-bold ${isDarkMode ? 'text-red-400' : 'text-red-600'}`}>
                      +{factor.points}
                    </span>
                  )}
                </div>
              </div>
            ))}
          </div>

          {structuredFactors.length > 3 && (
            <button
              onClick={() => setShowAllFactors(!showAllFactors)}
              className={`flex items-center gap-1 mt-2 text-xs font-medium ${isDarkMode ? 'text-gray-400 hover:text-gray-200' : 'text-gray-500 hover:text-gray-700'}`}
            >
              {showAllFactors ? (
                <>Show less <ChevronUpIcon className="h-3 w-3" /></>
              ) : (
                <>Show {structuredFactors.length - 3} more <ChevronDownIcon className="h-3 w-3" /></>
              )}
            </button>
          )}
        </div>
      )}

      {/* Positive Factors */}
      {positive_factors && positive_factors.length > 0 && (
        <div className={`px-4 py-3 border-t ${isDarkMode ? 'border-gray-700/50' : 'border-gray-200/80'}`}>
          <p className={`text-xs font-bold mb-2 ${isDarkMode ? 'text-green-300' : 'text-green-700'}`}>
            <HandThumbUpIcon className="h-3.5 w-3.5 inline mr-1" />
            Positive Factors
          </p>
          <div className="space-y-1">
            {positive_factors.map((factor, idx) => (
              <div key={idx} className="flex items-center gap-2">
                <CheckCircleIcon className={`h-4 w-4 flex-shrink-0 ${isDarkMode ? 'text-green-400' : 'text-green-600'}`} />
                <p className={`text-xs ${isDarkMode ? 'text-green-300' : 'text-green-700'}`}>{factor}</p>
              </div>
            ))}
          </div>
        </div>
      )}

      {/* Recommendations */}
      {recommendations.length > 0 && (
        <div className={`px-4 py-3 border-t ${isDarkMode ? 'border-gray-700/50' : 'border-gray-200/80'}`}>
          <button
            onClick={() => setShowRecommendations(!showRecommendations)}
            className={`flex items-center justify-between w-full mb-2`}
          >
            <p className={`text-xs font-bold ${isDarkMode ? 'text-gray-300' : 'text-gray-700'}`}>
              Recommended Actions ({recommendations.length})
            </p>
            {showRecommendations ? (
              <ChevronUpIcon className={`h-3.5 w-3.5 ${isDarkMode ? 'text-gray-400' : 'text-gray-500'}`} />
            ) : (
              <ChevronDownIcon className={`h-3.5 w-3.5 ${isDarkMode ? 'text-gray-400' : 'text-gray-500'}`} />
            )}
          </button>

          {showRecommendations && (
            <div className="space-y-2">
              {recommendations.map((rec, idx) => {
                const isHighPriority = rec.priority === 'high';
                return (
                  <div
                    key={idx}
                    className={`p-2.5 rounded-lg border ${
                      isHighPriority
                        ? isDarkMode
                          ? 'bg-red-500/10 border-red-500/30'
                          : 'bg-red-50 border-red-200'
                        : isDarkMode
                        ? 'bg-gray-700/30 border-gray-600/50'
                        : 'bg-gray-50 border-gray-200'
                    }`}
                  >
                    <div className="flex items-start gap-2">
                      <span className={`mt-0.5 flex-shrink-0 ${
                        isHighPriority
                          ? isDarkMode ? 'text-red-400' : 'text-red-600'
                          : isDarkMode ? 'text-gray-400' : 'text-gray-500'
                      }`}>
                        {getRecIcon(rec.icon)}
                      </span>
                      <div className="flex-1 min-w-0">
                        <div className="flex items-center gap-2">
                          <p className={`text-xs font-semibold ${isDarkMode ? 'text-gray-100' : 'text-gray-900'}`}>
                            {rec.title || rec.description}
                          </p>
                          {rec.priority && (
                            <span className={`text-xs px-1.5 py-0.5 rounded border font-medium ${
                              isHighPriority
                                ? isDarkMode ? 'bg-red-500/20 text-red-300 border-red-500/30' : 'bg-red-100 text-red-700 border-red-200'
                                : rec.priority === 'medium'
                                ? isDarkMode ? 'bg-amber-500/20 text-amber-300 border-amber-500/30' : 'bg-amber-100 text-amber-700 border-amber-200'
                                : isDarkMode ? 'bg-gray-600 text-gray-300 border-gray-500' : 'bg-gray-100 text-gray-600 border-gray-200'
                            }`}>
                              {rec.priority}
                            </span>
                          )}
                          {rec.automated && (
                            <span className={`text-xs px-1.5 py-0.5 rounded ${isDarkMode ? 'bg-blue-500/20 text-blue-300' : 'bg-blue-50 text-blue-600'}`}>
                              Auto
                            </span>
                          )}
                        </div>
                        {rec.title && rec.description && (
                          <p className={`text-xs mt-0.5 ${isDarkMode ? 'text-gray-400' : 'text-gray-500'}`}>
                            {rec.description}
                          </p>
                        )}
                      </div>
                    </div>
                  </div>
                );
              })}
            </div>
          )}
        </div>
      )}
    </div>
  );
};

export default AppointmentRiskAssessment;
