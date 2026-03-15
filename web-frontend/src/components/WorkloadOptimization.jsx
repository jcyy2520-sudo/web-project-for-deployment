import { useState, useEffect } from 'react';
import axios from 'axios';
import {
  UserGroupIcon,
  CheckCircleIcon,
  ExclamationTriangleIcon,
  ClockIcon,
  ArrowTrendingUpIcon,
  LightBulbIcon,
  ChartBarIcon,
} from '@heroicons/react/24/outline';

/**
 * WorkloadOptimization Component
 *
 * Displays staff workload balance with:
 * - Balance score visualization
 * - Staff cards with capacity bars, time distribution, next available
 * - Smart insights & redistribution suggestions
 * - Summary statistics
 */
const WorkloadOptimization = ({ appointmentDate, isDarkMode = true }) => {
  const [staffData, setStaffData] = useState([]);
  const [summary, setSummary] = useState(null);
  const [insights, setInsights] = useState([]);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);
  const [expandedStaff, setExpandedStaff] = useState(null);

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
        params: { appointment_date: appointmentDate },
      });

      const data = response.data.data;
      if (Array.isArray(data)) {
        setStaffData(data);
      } else if (data?.staff) {
        setStaffData(data.staff);
      } else {
        setStaffData(data || []);
      }
      setSummary(response.data.summary || null);
      setInsights(response.data.insights || []);
    } catch (err) {
      setError('Failed to fetch workload data');
      console.error(err);
    } finally {
      setLoading(false);
    }
  };

  const getStatusConfig = (status) => {
    const configs = {
      available: {
        label: 'Available',
        text: isDarkMode ? 'text-green-400' : 'text-green-600',
        bg: isDarkMode ? 'bg-green-500/15' : 'bg-green-50',
        border: isDarkMode ? 'border-green-500/30' : 'border-green-200',
        bar: 'bg-green-500',
        dot: 'bg-green-400',
      },
      busy: {
        label: 'Busy',
        text: isDarkMode ? 'text-amber-400' : 'text-amber-600',
        bg: isDarkMode ? 'bg-amber-500/15' : 'bg-amber-50',
        border: isDarkMode ? 'border-amber-500/30' : 'border-amber-200',
        bar: 'bg-amber-500',
        dot: 'bg-amber-400',
      },
      overloaded: {
        label: 'Overloaded',
        text: isDarkMode ? 'text-red-400' : 'text-red-600',
        bg: isDarkMode ? 'bg-red-500/15' : 'bg-red-50',
        border: isDarkMode ? 'border-red-500/30' : 'border-red-200',
        bar: 'bg-red-500',
        dot: 'bg-red-400',
      },
    };
    return configs[status] || configs.available;
  };

  const getBalanceScoreColor = (score) => {
    if (score >= 80) return { text: isDarkMode ? 'text-green-400' : 'text-green-600', bar: 'bg-green-500', label: 'Excellent' };
    if (score >= 60) return { text: isDarkMode ? 'text-blue-400' : 'text-blue-600', bar: 'bg-blue-500', label: 'Good' };
    if (score >= 40) return { text: isDarkMode ? 'text-amber-400' : 'text-amber-600', bar: 'bg-amber-500', label: 'Fair' };
    return { text: isDarkMode ? 'text-red-400' : 'text-red-600', bar: 'bg-red-500', label: 'Needs Attention' };
  };

  const getInsightIcon = (type) => {
    switch (type) {
      case 'warning': return <ExclamationTriangleIcon className="h-4 w-4 text-amber-500 flex-shrink-0" />;
      case 'success': return <CheckCircleIcon className="h-4 w-4 text-green-500 flex-shrink-0" />;
      case 'suggestion': return <LightBulbIcon className="h-4 w-4 text-blue-400 flex-shrink-0" />;
      default: return <LightBulbIcon className="h-4 w-4 text-gray-400 flex-shrink-0" />;
    }
  };

  if (loading) {
    return (
      <div className={`p-4 rounded-xl border ${isDarkMode ? 'bg-gray-800 border-gray-700' : 'bg-white border-gray-200'}`}>
        <div className="flex items-center justify-center py-6 gap-3">
          <div className="animate-spin rounded-full h-5 w-5 border-2 border-t-transparent border-amber-500"></div>
          <p className={`text-sm ${isDarkMode ? 'text-gray-400' : 'text-gray-500'}`}>Analyzing workload...</p>
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

  if (!staffData || staffData.length === 0) {
    return null;
  }

  // Compute local summaries if backend doesn't provide them
  const totalSlots = staffData.reduce((sum, s) => sum + (s.available_slots || 0), 0);
  const totalScheduled = staffData.reduce((sum, s) => sum + (s.appointments_scheduled || 0), 0);
  const avgLoad = staffData.length > 0 ? (totalScheduled / staffData.length).toFixed(1) : 0;
  const balanceScore = summary?.balance_score ?? null;
  const balanceConfig = balanceScore !== null ? getBalanceScoreColor(balanceScore) : null;

  return (
    <div className={`rounded-xl border overflow-hidden ${isDarkMode ? 'bg-gray-800 border-gray-700' : 'bg-white border-gray-200'}`}>
      {/* Header */}
      <div className={`px-4 py-3 border-b flex items-center justify-between ${isDarkMode ? 'border-gray-700' : 'border-gray-200'}`}>
        <div className="flex items-center gap-2">
          <UserGroupIcon className="h-5 w-5 text-amber-500" />
          <h3 className={`text-sm font-bold ${isDarkMode ? 'text-amber-50' : 'text-amber-900'}`}>
            Staff Workload Overview
          </h3>
        </div>
        <span className={`text-xs ${isDarkMode ? 'text-gray-400' : 'text-gray-500'}`}>
          {staffData.length} staff members
        </span>
      </div>

      {/* Balance Score + Summary Stats */}
      <div className={`px-4 py-3 border-b ${isDarkMode ? 'border-gray-700' : 'border-gray-200'}`}>
        <div className="flex items-center gap-4">
          {/* Balance Score */}
          {balanceConfig && (
            <div className="flex-1">
              <div className="flex items-center justify-between mb-1">
                <span className={`text-xs font-medium ${isDarkMode ? 'text-gray-400' : 'text-gray-500'}`}>Workload Balance</span>
                <span className={`text-xs font-bold ${balanceConfig.text}`}>{balanceScore}% — {balanceConfig.label}</span>
              </div>
              <div className={`w-full h-2 rounded-full overflow-hidden ${isDarkMode ? 'bg-gray-700' : 'bg-gray-200'}`}>
                <div
                  className={`h-full rounded-full transition-all ${balanceConfig.bar}`}
                  style={{ width: `${balanceScore}%` }}
                />
              </div>
            </div>
          )}
        </div>

        {/* Quick Stats Row */}
        <div className="grid grid-cols-3 gap-2 mt-3">
          <div className={`p-2 rounded-lg text-center ${isDarkMode ? 'bg-gray-700/50' : 'bg-gray-50'}`}>
            <p className={`text-xs ${isDarkMode ? 'text-gray-400' : 'text-gray-500'}`}>Available Slots</p>
            <p className={`text-lg font-bold ${isDarkMode ? 'text-green-400' : 'text-green-600'}`}>{totalSlots}</p>
          </div>
          <div className={`p-2 rounded-lg text-center ${isDarkMode ? 'bg-gray-700/50' : 'bg-gray-50'}`}>
            <p className={`text-xs ${isDarkMode ? 'text-gray-400' : 'text-gray-500'}`}>Scheduled</p>
            <p className={`text-lg font-bold ${isDarkMode ? 'text-amber-400' : 'text-amber-600'}`}>{totalScheduled}</p>
          </div>
          <div className={`p-2 rounded-lg text-center ${isDarkMode ? 'bg-gray-700/50' : 'bg-gray-50'}`}>
            <p className={`text-xs ${isDarkMode ? 'text-gray-400' : 'text-gray-500'}`}>Avg Load</p>
            <p className={`text-lg font-bold ${isDarkMode ? 'text-blue-400' : 'text-blue-600'}`}>{avgLoad}</p>
          </div>
        </div>
      </div>

      {/* Staff Cards */}
      <div className="p-4 space-y-2.5">
        {staffData.map((staff) => {
          const config = getStatusConfig(staff.status);
          const isExpanded = expandedStaff === staff.staff_id;

          return (
            <div
              key={staff.staff_id}
              className={`rounded-lg border transition-all cursor-pointer ${config.bg} ${config.border}`}
              onClick={() => setExpandedStaff(isExpanded ? null : staff.staff_id)}
            >
              {/* Staff Main Row */}
              <div className="p-3">
                <div className="flex items-center justify-between mb-2">
                  <div className="flex items-center gap-2">
                    <span className={`w-2 h-2 rounded-full ${config.dot}`}></span>
                    <p className={`text-sm font-semibold ${isDarkMode ? 'text-gray-100' : 'text-gray-900'}`}>
                      {staff.staff_name}
                    </p>
                  </div>
                  <span className={`text-xs font-bold px-2 py-0.5 rounded-full border ${config.text} ${config.bg} ${config.border}`}>
                    {config.label}
                  </span>
                </div>

                {/* Capacity Bar */}
                <div className="flex items-center gap-2 mb-1.5">
                  <div className={`flex-1 h-2 rounded-full overflow-hidden ${isDarkMode ? 'bg-gray-700' : 'bg-gray-200'}`}>
                    <div
                      className={`h-full rounded-full transition-all ${config.bar}`}
                      style={{ width: `${Math.min(staff.capacity_percentage || 0, 100)}%` }}
                    />
                  </div>
                  <span className={`text-xs font-medium w-10 text-right ${config.text}`}>
                    {Math.round(staff.capacity_percentage || 0)}%
                  </span>
                </div>

                {/* Quick Info */}
                <div className="flex items-center gap-3 text-xs">
                  <span className={isDarkMode ? 'text-gray-400' : 'text-gray-500'}>
                    {staff.appointments_scheduled} appts
                  </span>
                  <span className={isDarkMode ? 'text-gray-600' : 'text-gray-300'}>•</span>
                  <span className={isDarkMode ? 'text-gray-400' : 'text-gray-500'}>
                    {staff.available_slots} slots free
                  </span>
                  {staff.next_available && (
                    <>
                      <span className={isDarkMode ? 'text-gray-600' : 'text-gray-300'}>•</span>
                      <span className={`flex items-center gap-1 ${isDarkMode ? 'text-gray-400' : 'text-gray-500'}`}>
                        <ClockIcon className="h-3 w-3" />
                        Next: {staff.next_available}
                      </span>
                    </>
                  )}
                </div>
              </div>

              {/* Expanded Details */}
              {isExpanded && (
                <div className={`px-3 pb-3 pt-1 border-t ${isDarkMode ? 'border-gray-700/50' : 'border-gray-200/80'}`}>
                  {/* Time Distribution */}
                  {staff.time_distribution && Object.keys(staff.time_distribution).length > 0 && (
                    <div className="mb-2">
                      <p className={`text-xs font-medium mb-1.5 ${isDarkMode ? 'text-gray-300' : 'text-gray-600'}`}>
                        Time Distribution
                      </p>
                      <div className="grid grid-cols-3 gap-1.5">
                        {Object.entries(staff.time_distribution).map(([period, count]) => (
                          <div key={period} className={`text-center p-1.5 rounded ${isDarkMode ? 'bg-gray-700/50' : 'bg-white/80'}`}>
                            <p className={`text-xs capitalize ${isDarkMode ? 'text-gray-400' : 'text-gray-500'}`}>{period}</p>
                            <p className={`text-sm font-bold ${isDarkMode ? 'text-gray-200' : 'text-gray-700'}`}>{count}</p>
                          </div>
                        ))}
                      </div>
                    </div>
                  )}

                  {/* Recommendation for this staff */}
                  {staff.recommendation && (
                    <div className={`p-2 rounded text-xs ${isDarkMode ? 'bg-gray-700/30 text-gray-300' : 'bg-white/60 text-gray-600'}`}>
                      <strong>Suggestion:</strong> {staff.recommendation}
                    </div>
                  )}
                </div>
              )}
            </div>
          );
        })}
      </div>

      {/* Smart Insights */}
      {insights.length > 0 && (
        <div className={`px-4 pb-4`}>
          <div className={`rounded-lg border p-3 space-y-2 ${isDarkMode ? 'bg-gray-700/30 border-gray-700' : 'bg-blue-50/50 border-blue-100'}`}>
            <p className={`text-xs font-bold flex items-center gap-1.5 ${isDarkMode ? 'text-gray-200' : 'text-gray-700'}`}>
              <LightBulbIcon className="h-4 w-4 text-amber-500" />
              Optimization Insights
            </p>
            {insights.map((insight, idx) => (
              <div key={idx} className="flex items-start gap-2">
                {getInsightIcon(insight.type)}
                <p className={`text-xs ${isDarkMode ? 'text-gray-300' : 'text-gray-600'}`}>{insight.message}</p>
              </div>
            ))}
          </div>
        </div>
      )}

      {/* Best Assignment Footer */}
      {staffData.length > 0 && (
        <div className={`px-4 py-2.5 border-t text-xs flex items-center gap-2 ${isDarkMode ? 'border-gray-700 bg-blue-500/5 text-blue-300' : 'border-gray-200 bg-blue-50/80 text-blue-700'}`}>
          <ArrowTrendingUpIcon className="h-4 w-4" />
          <span>
            <strong>Best assignment:</strong> {staffData.reduce((best, s) => (s.available_slots || 0) > (best.available_slots || 0) ? s : best, staffData[0]).staff_name}
            {' — '}most available capacity
          </span>
        </div>
      )}
    </div>
  );
};

export default WorkloadOptimization;
