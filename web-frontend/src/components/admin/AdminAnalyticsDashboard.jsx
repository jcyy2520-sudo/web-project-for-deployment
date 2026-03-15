import React, { useState, useEffect, useCallback } from 'react';
import axios from 'axios';
import LoadingSpinner from '../LoadingSpinner';

const AdminAnalyticsDashboard = () => {
  const [loading, setLoading] = useState(true);
  const [activeTab, setActiveTab] = useState('overview');
  const [analyticsData, setAnalyticsData] = useState(null);
  const [error, setError] = useState(null);
  const [refreshTime, setRefreshTime] = useState(null);
  const [autoRefreshEnabled, setAutoRefreshEnabled] = useState(true);
  const [retryCount, setRetryCount] = useState(0);
  const MAX_RETRIES = 2;

  const fetchAnalytics = useCallback(async () => {
    try {
      setLoading(true);
      setError(null);
      const response = await axios.get('/api/admin/analytics/dashboard', {
        params: { realtime: 'true' },
        timeout: 30000
      });
      if (response.data.success) {
        setAnalyticsData(response.data.data);
        setRefreshTime(new Date());
        setRetryCount(0);
        console.log('Analytics data loaded:', response.data.data);
      } else {
        setError(response.data.message || 'Failed to load analytics data');
      }
    } catch (err) {
      const errorMsg = err.response?.data?.message || err.message || 'Unknown error';
      if (err.response?.status === 401) {
        setError('Not authenticated. Please log in again.');
      } else if (err.response?.status === 403) {
        setError('You do not have permission to view analytics.');
      } else if (err.response?.status === 422) {
        setError('Invalid request. Please refresh the page and try again.');
      } else if (err.response?.status === 503) {
        if (retryCount < MAX_RETRIES) {
          setError('Analytics service is temporarily unavailable. Retrying...');
          setRetryCount(prev => prev + 1);
          setTimeout(fetchAnalytics, 5000);
        } else {
          setError('Analytics service is temporarily unavailable. Please try again later.');
        }
      } else if (err.code === 'ERR_NETWORK' || err.code === 'ECONNABORTED') {
        setError('Network error. Make sure the backend server is running.');
      } else {
        setError(`Failed to load analytics: ${errorMsg}`);
      }
      console.error('Analytics fetch error:', err);
    } finally {
      setLoading(false);
    }
  }, [retryCount]);

  useEffect(() => {
    fetchAnalytics();

    let interval;
    if (autoRefreshEnabled) {
      // Refresh every 120 seconds for near-real-time updates (reduced from 60s for performance)
      interval = setInterval(fetchAnalytics, 120000);
    }

    // Try to set up real-time WebSocket listener for instant updates
    if (window.Echo && typeof window.Echo.channel === 'function') {
      try {
        const channel = window.Echo.channel('analytics-updates');
        
        // Listen for analytics update events from backend
        const unsubscribeAnalytics = channel.listen('.analytics.updated', (data) => {
          // When notified of a change, immediately fetch fresh data
          console.log('Analytics update event received, refreshing data');
          fetchAnalytics();
        });
        
        return () => {
          if (unsubscribeAnalytics) unsubscribeAnalytics();
          if (interval) clearInterval(interval);
          try {
            channel.stopListening('.analytics.updated');
          } catch (e) {
            // Already unsubscribed
          }
        };
      } catch (e) {
        console.warn('Echo listener setup failed:', e);
        // Continue with polling fallback
      }
    }

    return () => {
      if (interval) clearInterval(interval);
    };
  }, [fetchAnalytics, autoRefreshEnabled]);

  const handleManualRefresh = async () => {
    try {
      setLoading(true);
      setError(null);
      const response = await axios.post('/api/admin/analytics/clear-cache');
      if (response.data.success) {
        setAnalyticsData(response.data.data);
        setRefreshTime(new Date());
      } else {
        setError(response.data.message || 'Failed to refresh analytics');
      }
    } catch (err) {
      const errorMsg = err.response?.data?.message || err.message || 'Unknown error';
      if (err.response?.status === 401) {
        setError('Not authenticated. Please log in again.');
      } else if (err.response?.status === 403) {
        setError('You do not have permission to refresh analytics.');
      } else if (err.code === 'ERR_NETWORK') {
        setError('Network error. Make sure the backend server is running on port 8000.');
      } else {
        setError(`Refresh failed: ${errorMsg}`);
      }
      console.error('Refresh error:', err);
    } finally {
      setLoading(false);
    }
  };

  if (loading && !analyticsData) return <LoadingSpinner />;

  return (
    <div className="min-h-screen bg-gray-900 p-6">
      <div className="max-w-7xl mx-auto">
        {/* Header */}
        <div className="mb-8 flex justify-between items-center">
          <div>
            <h1 className="text-3xl font-bold text-amber-50">Analytics Dashboard</h1>
            <p className="text-gray-400 mt-1">Real-time business insights</p>
          </div>
          <div className="flex gap-3 items-center">
            <button
              onClick={handleManualRefresh}
              disabled={loading}
              className="px-4 py-2 bg-amber-500 hover:bg-amber-600 text-gray-900 font-medium rounded-lg transition disabled:opacity-50"
            >
              {loading ? 'Refreshing...' : 'Refresh'}
            </button>
            <label className="flex items-center gap-2 cursor-pointer text-gray-400 hover:text-amber-50">
              <input
                type="checkbox"
                checked={autoRefreshEnabled}
                onChange={(e) => setAutoRefreshEnabled(e.target.checked)}
                className="rounded"
              />
              <span className="text-sm">Auto-refresh</span>
            </label>
          </div>
        </div>

        {error && (
          <div className="mb-6 p-4 bg-gray-800 border border-gray-700 text-amber-50 rounded-lg">
            {error}
          </div>
        )}

        {/* Tabs */}
        <div className="mb-6 border-b border-gray-700">
          <div className="flex gap-6">
            {['overview', 'slots', 'noshow', 'forecast', 'quality'].map((tab) => (
              <button
                key={tab}
                onClick={() => setActiveTab(tab)}
                className={`px-4 py-3 font-medium border-b-2 transition ${
                  activeTab === tab
                    ? 'border-b-amber-500 text-amber-50'
                    : 'border-transparent text-gray-400 hover:text-amber-50'
                }`}
              >
                {tab.charAt(0).toUpperCase() + tab.slice(1).replace(/([A-Z])/g, ' $1')}
              </button>
            ))}
          </div>
        </div>

        {/* Content */}
        {analyticsData ? (
          <div>
            {activeTab === 'overview' && <OverviewTab data={analyticsData} />}
            {activeTab === 'slots' && <SlotsTab data={analyticsData} />}
            {activeTab === 'noshow' && <NoShowTab data={analyticsData} />}
            {activeTab === 'forecast' && <ForecastTab data={analyticsData} />}
            {activeTab === 'quality' && <QualityTab data={analyticsData} />}
          </div>
        ) : (
          <div className="p-12 bg-gray-800 border border-gray-700 rounded-lg text-center">
            <p className="text-gray-400">No analytics data available</p>
          </div>
        )}

        {/* Footer */}
        <div className="mt-12 pt-6 border-t border-gray-700 text-sm text-gray-400">
          {refreshTime && `Last updated: ${refreshTime.toLocaleTimeString()}`}
        </div>
      </div>
    </div>
  );
};

const OverviewTab = ({ data }) => {
  const { auto_alerts = [], slot_utilization = {} } = data;
  const overall = slot_utilization?.overall || {};
  
  const utilized = overall.total_booked || 0;
  const total = overall.total_capacity || 0;
  const available = Math.max(0, total - utilized);
  const rate = overall.overall_utilization_rate || 0;

  // Status indicator
  const getStatus = () => {
    if (rate >= 90) return { label: 'At Capacity', color: 'text-red-500', bg: 'bg-red-500/20', border: 'border-red-500/30' };
    if (rate >= 70) return { label: 'High Demand', color: 'text-orange-500', bg: 'bg-orange-500/20', border: 'border-orange-500/30' };
    if (rate >= 50) return { label: 'Healthy', color: 'text-amber-500', bg: 'bg-amber-500/20', border: 'border-amber-500/30' };
    return { label: 'Available Capacity', color: 'text-green-500', bg: 'bg-green-500/20', border: 'border-green-500/30' };
  };

  const status = getStatus();

  return (
    <div className="space-y-8">
      {/* Main Status Card */}
      <div className={`${status.bg} border ${status.border} p-8 rounded-lg`}>
        <div className="max-w-4xl mx-auto">
          {/* Header */}
          <div className="flex items-start justify-between mb-6">
            <div>
              <h3 className="text-2xl font-bold text-amber-50 mb-2">Slot Utilization Status</h3>
              <p className={`text-sm font-semibold ${status.color}`}>{status.label}</p>
            </div>
            <div className={`px-4 py-2 rounded-lg font-bold text-2xl ${status.color}`}>
              {rate}%
            </div>
          </div>

          {/* Visual Progress Bar */}
          <div className="mb-6">
            <div className="w-full bg-gray-700 rounded-full h-4 overflow-hidden">
              <div 
                className={`h-full rounded-full transition-all duration-500 ${
                  rate >= 90 ? 'bg-gradient-to-r from-red-500 to-red-600' :
                  rate >= 70 ? 'bg-gradient-to-r from-orange-500 to-orange-600' :
                  rate >= 50 ? 'bg-gradient-to-r from-amber-500 to-amber-600' :
                  'bg-gradient-to-r from-green-500 to-green-600'
                }`}
                style={{width: `${rate}%`}}
              />
            </div>
          </div>

          {/* Key Metrics Grid */}
          <div className="grid grid-cols-3 gap-4">
            <div className="bg-gray-900/50 p-4 rounded-lg border border-gray-700">
              <p className="text-xs text-gray-400 uppercase tracking-wide mb-2">Booked Slots</p>
              <p className="text-3xl font-bold text-green-400">{utilized}</p>
              <p className="text-xs text-gray-500 mt-1">appointments scheduled</p>
            </div>
            <div className="bg-gray-900/50 p-4 rounded-lg border border-gray-700">
              <p className="text-xs text-gray-400 uppercase tracking-wide mb-2">Available Slots</p>
              <p className="text-3xl font-bold text-blue-400">{available}</p>
              <p className="text-xs text-gray-500 mt-1">can be booked</p>
            </div>
            <div className="bg-gray-900/50 p-4 rounded-lg border border-gray-700">
              <p className="text-xs text-gray-400 uppercase tracking-wide mb-2">Total Capacity</p>
              <p className="text-3xl font-bold text-amber-400">{total}</p>
              <p className="text-xs text-gray-500 mt-1">slots available</p>
            </div>
          </div>

          {/* Status Explanation */}
          <div className="mt-6 p-4 bg-gray-900/50 rounded-lg border border-gray-700">
            <p className="text-sm text-gray-300">
              {rate >= 90 && "⚠️ You're running at full capacity. Consider increasing slot availability or managing demand."}
              {rate >= 70 && rate < 90 && "📊 High demand detected. You have limited slots available. Monitor availability closely."}
              {rate >= 50 && rate < 70 && "✓ Good balance between booked and available slots. Continue monitoring."}
              {rate < 50 && "✓ Plenty of capacity available. Slots are well distributed."}
            </p>
          </div>
        </div>
      </div>

      {/* Show underbooked days if available */}
      <OverviewTabExtra slot_utilization={slot_utilization} />

      {/* Alerts Section - Only show if there are critical alerts */}
      {auto_alerts && auto_alerts.length > 0 && (
        <div className="bg-gray-800/50 border border-amber-500/30 p-6 rounded-lg">
          <h3 className="text-lg font-semibold text-amber-50 mb-4">⚡ System Alerts</h3>
          <div className="space-y-3">
            {auto_alerts.slice(0, 3).map((alert, idx) => (
              <div key={idx} className="bg-gray-900/50 border-l-4 border-amber-500 p-4 rounded">
                <p className="font-semibold text-amber-300 text-sm">{alert.title}</p>
                <p className="text-gray-300 text-sm mt-2">{alert.message}</p>
              </div>
            ))}
          </div>
        </div>
      )}
    </div>
  );
};

const SlotsTab = ({ data }) => {
  const { slot_utilization = {} } = data;
  const { overall = {}, underbooked_days = [], overbooked_days = [] } = slot_utilization;
  
  // Filter out weekends (Saturday = 6, Sunday = 0)
  const isWeekday = (dateStr) => {
    const dayIndex = new Date(dateStr).getDay();
    return dayIndex !== 0 && dayIndex !== 6;
  };
  
  const weekdayOverbooked = overbooked_days.filter(day => isWeekday(day.date));
  const weekdayUnderbooked = underbooked_days.filter(day => isWeekday(day.date));
  
  // Helper to create bar visualization
  const BarChart = ({ label, value, max, color = 'amber' }) => {
    const percent = max > 0 ? (value / max) * 100 : 0;
    return (
      <div className="mb-3">
        <div className="flex justify-between mb-1">
          <span className="text-sm text-gray-300">{label}</span>
          <span className="text-sm font-semibold text-amber-300">{value}</span>
        </div>
        <div className="w-full bg-gray-700 rounded-full h-2 overflow-hidden">
          <div 
            className={`h-full rounded-full transition-all duration-300 bg-gradient-to-r from-${color}-500 to-${color}-600`}
            style={{width: `${percent}%`}}
          />
        </div>
      </div>
    );
  };

  return (
    <div className="space-y-8">
      {/* Problematic Days Visualization */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {/* Overbooked */}
        {weekdayOverbooked && weekdayOverbooked.length > 0 ? (
          <div className="bg-gray-800/50 border border-red-500/30 p-6 rounded-lg">
            <h3 className="text-lg font-semibold text-red-300 mb-4 flex items-center">
              <span className="w-3 h-3 bg-red-500 rounded-full mr-2"></span>
              Overbooked Days ({weekdayOverbooked.length})
            </h3>
            <div className="space-y-4">
              {weekdayOverbooked.slice(0, 5).map((day, idx) => (
                <div key={idx} className="bg-gray-900/50 p-4 rounded-lg border border-gray-700">
                  <div className="flex justify-between mb-2">
                    <span className="font-medium text-amber-50">{day.day_name}</span>
                    <span className="text-red-400 font-semibold">{day.utilization_rate}%</span>
                  </div>
                  <p className="text-sm text-gray-400">{day.date} • {day.booked} bookings</p>
                  <div className="mt-2 w-full bg-gray-700 rounded-full h-1.5 overflow-hidden">
                    <div 
                      className="h-full bg-red-500 rounded-full"
                      style={{width: `${Math.min(day.utilization_rate, 100)}%`}}
                    />
                  </div>
                </div>
              ))}
            </div>
          </div>
        ) : (
          <div className="bg-gray-800/50 border border-green-500/30 p-6 rounded-lg text-center">
            <div className="w-12 h-12 bg-green-500/20 rounded-full mx-auto mb-3 flex items-center justify-center">
              <span className="text-2xl">✓</span>
            </div>
            <p className="text-green-300 font-medium">No Overbooked Days</p>
            <p className="text-gray-400 text-sm mt-1">All days are properly scheduled</p>
          </div>
        )}

        {/* Underbooked */}
        {weekdayUnderbooked && weekdayUnderbooked.length > 0 ? (
          <div className="bg-gray-800/50 border border-blue-500/30 p-6 rounded-lg">
            <h3 className="text-lg font-semibold text-blue-300 mb-4 flex items-center">
              <span className="w-3 h-3 bg-blue-500 rounded-full mr-2"></span>
              Underbooked Days ({weekdayUnderbooked.length})
            </h3>
            <div className="space-y-4">
              {weekdayUnderbooked.slice(0, 5).map((day, idx) => (
                <div key={idx} className="bg-gray-900/50 p-4 rounded-lg border border-gray-700">
                  <div className="flex justify-between mb-2">
                    <span className="font-medium text-amber-50">{day.day_name}</span>
                    <span className="text-blue-400 font-semibold">{day.utilization_rate}%</span>
                  </div>
                  <p className="text-sm text-gray-400">{day.date} • {day.booked} bookings</p>
                  <div className="mt-2 w-full bg-gray-700 rounded-full h-1.5 overflow-hidden">
                    <div 
                      className="h-full bg-blue-500 rounded-full"
                      style={{width: `${Math.min(day.utilization_rate, 100)}%`}}
                    />
                  </div>
                </div>
              ))}
            </div>
          </div>
        ) : (
          <div className="bg-gray-800/50 border border-green-500/30 p-6 rounded-lg text-center">
            <div className="w-12 h-12 bg-green-500/20 rounded-full mx-auto mb-3 flex items-center justify-center">
              <span className="text-2xl">✓</span>
            </div>
            <p className="text-green-300 font-medium">No Underbooked Days</p>
            <p className="text-gray-400 text-sm mt-1">All slots are well utilized</p>
          </div>
        )}
      </div>
    </div>
  );
};

const OverviewTabExtra = ({ slot_utilization = {} }) => {
  const { underbooked_days = [] } = slot_utilization;
  
  const weekdayUnderbooked = underbooked_days.filter(day => {
    const dayIndex = new Date(day.date).getDay();
    return dayIndex !== 0 && dayIndex !== 6;
  });
  
  if (!weekdayUnderbooked || weekdayUnderbooked.length === 0) return null;
  
  return (
    <div className="bg-gray-800/50 border border-blue-500/30 p-6 rounded-lg">
      <h3 className="text-lg font-semibold text-blue-300 mb-4 flex items-center">
        <span className="w-3 h-3 bg-blue-500 rounded-full mr-2"></span>
        ⬇️ Days with Low Bookings
      </h3>
      <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
        {weekdayUnderbooked.slice(0, 6).map((day, idx) => (
          <div key={idx} className="bg-gray-900/50 p-3 rounded-lg border border-gray-700">
            <div className="flex justify-between items-center">
              <div>
                <p className="font-medium text-blue-300">{day.day_name}</p>
                <p className="text-xs text-gray-400">{day.date}</p>
              </div>
              <div className="text-right">
                <p className="text-2xl font-bold text-blue-400">{day.booked || 0}</p>
                <p className="text-xs text-gray-400">{day.utilization_rate || 0}% utilization</p>
              </div>
            </div>
          </div>
        ))}
      </div>
    </div>
  );
};

const NoShowTab = ({ data }) => {
  const { no_show_patterns = {} } = data;
  const { users_with_high_no_show = [], high_risk_time_slots = [] } = no_show_patterns;
  
  const getNoShowColor = (rate) => {
    if (rate >= 50) return 'text-red-400 bg-red-500/20';
    if (rate >= 25) return 'text-orange-400 bg-orange-500/20';
    return 'text-yellow-400 bg-yellow-500/20';
  };

  return (
    <div className="space-y-8">
      {/* Risk Assessment Overview */}
      <div className="bg-gray-800/50 border border-gray-700 p-6 rounded-lg">
        <h3 className="text-lg font-semibold text-amber-50 mb-6">No-Show Risk Analysis</h3>
        
        <div className="space-y-4">
          {users_with_high_no_show && users_with_high_no_show.length > 0 ? (
            <>
              <div className="text-sm text-gray-400 mb-4">
                {users_with_high_no_show.length} user(s) with high no-show rates
              </div>
              {users_with_high_no_show.slice(0, 8).map((user, idx) => (
                <div key={idx} className="bg-gray-900/50 p-4 rounded-lg border border-gray-700 hover:border-gray-600 transition">
                  <div className="flex items-center justify-between mb-2">
                    <span className="font-medium text-amber-50">{user.user_name}</span>
                    <span className={`px-2.5 py-1 rounded-full text-xs font-semibold ${getNoShowColor(user.no_show_rate)}`}>
                      {user.no_show_rate || 0}%
                    </span>
                  </div>
                  <p className="text-sm text-gray-400">{user.no_show_count || 0} no-shows</p>
                  <div className="mt-2 w-full bg-gray-700 rounded-full h-2 overflow-hidden">
                    <div 
                      className="h-full bg-red-500 rounded-full"
                      style={{width: `${Math.min(user.no_show_rate || 0, 100)}%`}}
                    />
                  </div>
                </div>
              ))}
            </>
          ) : (
            <div className="text-center py-8">
              <div className="w-12 h-12 bg-green-500/20 rounded-full mx-auto mb-3 flex items-center justify-center">
                <span className="text-2xl">✓</span>
              </div>
              <p className="text-green-300 font-medium">No High-Risk Users</p>
              <p className="text-gray-400 text-sm mt-1">All users have good attendance</p>
            </div>
          )}
        </div>
      </div>

      {/* High Risk Time Slots */}
      {high_risk_time_slots && high_risk_time_slots.length > 0 && (
        <div className="bg-gray-800/50 border border-orange-500/30 p-6 rounded-lg">
          <h3 className="text-lg font-semibold text-orange-300 mb-6 flex items-center">
            <span className="w-3 h-3 bg-orange-500 rounded-full mr-2"></span>
            High-Risk Time Slots
          </h3>
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            {high_risk_time_slots.slice(0, 9).map((slot, idx) => (
              <div key={idx} className="bg-gray-900/50 p-4 rounded-lg border border-gray-700">
                <div className="flex items-baseline justify-between mb-2">
                  <span className="font-semibold text-amber-50">{slot.time}</span>
                  <span className="text-xs bg-orange-500/20 text-orange-400 px-2 py-1 rounded">
                    {slot.no_show_rate || 0}%
                  </span>
                </div>
                <p className="text-sm text-gray-400">{slot.total_appointments || 0} appointments</p>
              </div>
            ))}
          </div>
        </div>
      )}
    </div>
  );
};

const ForecastTab = ({ data }) => {
  const { demand_forecast = {} } = data;
  const { day_of_week_stats = [], forecast = [] } = demand_forecast;
  
  // Filter only weekdays (Monday-Friday)
  const weekdayStats = day_of_week_stats.filter(d => {
    const dayIndex = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'].indexOf(d.day);
    return dayIndex >= 0 && dayIndex < 5; // Monday to Friday
  });
  
  const maxAppointments = Math.max(
    ...(weekdayStats?.map(d => d.avg_appointments || 0) || [1])
  );
  
  // Filter only weekdays from forecast
  const weekdayForecast = forecast.filter(day => {
    const date = new Date(day.date);
    const dayOfWeek = date.getDay();
    return dayOfWeek !== 0 && dayOfWeek !== 6; // Exclude Sunday (0) and Saturday (6)
  });

  return (
    <div className="space-y-8">
      {/* Weekly Demand Pattern */}
      {weekdayStats && weekdayStats.length > 0 && (
        <div className="bg-gray-800/50 border border-gray-700 p-6 rounded-lg">
          <div className="mb-6">
            <h3 className="text-lg font-semibold text-amber-50 mb-2">Weekly Demand Pattern</h3>
            <p className="text-sm text-gray-400">Average appointments per weekday</p>
          </div>
          
          <div className="flex items-end justify-between h-40 gap-4 px-2">
            {weekdayStats.map((day, idx) => {
              const height = maxAppointments > 0 ? (day.avg_appointments / maxAppointments) * 100 : 0;
              return (
                <div key={idx} className="flex-1 flex flex-col items-center gap-3">
                  <div className="w-full flex flex-col items-center">
                    <div 
                      className="w-full max-w-16 bg-gradient-to-t from-amber-500 to-amber-400 rounded-t transition-all hover:from-amber-400 hover:to-amber-300 shadow-lg"
                      style={{height: `${Math.max(height, 15)}px`, minHeight: '24px'}}
                      title={`${day.avg_appointments || 0} appointments`}
                    />
                  </div>
                  <div className="text-center">
                    <p className="text-xs font-bold text-amber-50">{day.avg_appointments || 0}</p>
                    <p className="text-xs text-gray-500 font-semibold">{day.day.substring(0, 3)}</p>
                  </div>
                </div>
              );
            })}
          </div>
        </div>
      )}

      {/* 30-Day Weekday Forecast - Clean Layout */}
      {weekdayForecast && weekdayForecast.length > 0 && (
        <div className="bg-gray-800/50 border border-gray-700 p-6 rounded-lg">
          <div className="mb-6">
            <h3 className="text-lg font-semibold text-amber-50 mb-2">30-Day Forecast (Weekdays Only)</h3>
            <p className="text-sm text-gray-400">Predicted daily appointments</p>
          </div>

          {/* Grid Layout - Cleaner than scrolling */}
          <div className="grid grid-cols-5 md:grid-cols-7 lg:grid-cols-10 gap-3">
            {weekdayForecast.slice(0, 20).map((day, idx) => {
              const date = new Date(day.date);
              const dayNum = date.getDate();
              const dayName = date.toLocaleDateString('en-US', { weekday: 'short' });
              const predictions = day.predicted_appointments || 0;
              
              // Color coding based on demand
              let bgColor = 'bg-gray-900/50';
              let borderColor = 'border-gray-700';
              let textColor = 'text-blue-400';
              
              if (predictions >= 8) {
                bgColor = 'bg-red-500/20';
                borderColor = 'border-red-500/50';
                textColor = 'text-red-400';
              } else if (predictions >= 6) {
                bgColor = 'bg-orange-500/20';
                borderColor = 'border-orange-500/50';
                textColor = 'text-orange-400';
              } else if (predictions >= 4) {
                bgColor = 'bg-amber-500/20';
                borderColor = 'border-amber-500/50';
                textColor = 'text-amber-400';
              } else if (predictions >= 2) {
                bgColor = 'bg-green-500/20';
                borderColor = 'border-green-500/50';
                textColor = 'text-green-400';
              }
              
              return (
                <div 
                  key={idx} 
                  className={`${bgColor} border ${borderColor} p-3 rounded-lg text-center hover:border-amber-500/50 transition cursor-default`}
                  title={`${dayName}, ${dayNum} - ${predictions} appointments`}
                >
                  <p className={`text-xl font-bold ${textColor}`}>{predictions}</p>
                  <p className="text-xs text-gray-400 mt-1">{dayName}</p>
                  <p className="text-xs font-semibold text-gray-500">{dayNum}</p>
                </div>
              );
            })}
          </div>
          
          {/* Legend */}
          <div className="mt-6 flex flex-wrap gap-4 text-xs">
            <div className="flex items-center gap-2">
              <div className="w-3 h-3 rounded-full bg-red-500"></div>
              <span className="text-gray-400">High (8+)</span>
            </div>
            <div className="flex items-center gap-2">
              <div className="w-3 h-3 rounded-full bg-orange-500"></div>
              <span className="text-gray-400">Moderate (6-7)</span>
            </div>
            <div className="flex items-center gap-2">
              <div className="w-3 h-3 rounded-full bg-amber-500"></div>
              <span className="text-gray-400">Medium (4-5)</span>
            </div>
            <div className="flex items-center gap-2">
              <div className="w-3 h-3 rounded-full bg-green-500"></div>
              <span className="text-gray-400">Low (2-3)</span>
            </div>
          </div>
        </div>
      )}
    </div>
  );
};

const QualityTab = ({ data }) => {
  const [servicePageSize] = useState(10);
  const [serviceCurrentPage, setServiceCurrentPage] = useState(1);
  
  const { quality_report = {} } = data;
  const { overall_stats = {}, service_stats = [], most_popular_services = [], least_popular_services = [] } = quality_report;

  const serviceTotalPages = Math.ceil(service_stats.length / servicePageSize);
  const serviceStartIdx = (serviceCurrentPage - 1) * servicePageSize;
  const serviceEndIdx = serviceStartIdx + servicePageSize;
  const paginatedServiceStats = service_stats.slice(serviceStartIdx, serviceEndIdx);
  
  // Visual gauge for rates
  const GaugeIndicator = ({ label, value, max = 100, unit = '%' }) => {
    const percent = Math.min((value / max) * 100, 100);
    const color = value >= 80 ? 'green' : value >= 50 ? 'amber' : 'red';
    const colorClass = {
      green: 'from-green-500 to-green-600',
      amber: 'from-amber-500 to-amber-600',
      red: 'from-red-500 to-red-600'
    }[color];
    
    return (
      <div className="bg-gray-900/50 p-6 rounded-lg border border-gray-700">
        <p className="text-xs text-gray-400 uppercase tracking-wide mb-3">{label}</p>
        <div className="relative">
          <svg width="100%" height="80" viewBox="0 0 200 100" className="mx-auto">
            {/* Background arc */}
            <path
              d="M 20 100 A 80 80 0 0 1 180 100"
              fill="none"
              stroke="#374151"
              strokeWidth="8"
            />
            {/* Colored arc */}
            <path
              d="M 20 100 A 80 80 0 0 1 180 100"
              fill="none"
              stroke={`url(#gradient${label})`}
              strokeWidth="8"
              strokeDasharray={`${160 * (percent / 100)} 160`}
              strokeLinecap="round"
              className="transition-all duration-500"
            />
            <defs>
              <linearGradient id={`gradient${label}`} x1="0%" y1="0%" x2="100%" y2="0%">
                <stop offset="0%" className={`stop-color-${color}-500`} />
                <stop offset="100%" className={`stop-color-${color}-600`} />
              </linearGradient>
            </defs>
          </svg>
          <div className="absolute inset-0 flex items-center justify-center">
            <span className="text-2xl font-bold text-amber-400">{value.toFixed(1)}{unit}</span>
          </div>
        </div>
      </div>
    );
  };

  return (
    <div className="space-y-8">
      {/* Quality Metrics - Gauges */}
      {overall_stats && Object.keys(overall_stats).length > 0 ? (
        <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
          <GaugeIndicator 
            label="Completion Rate" 
            value={overall_stats.completion_rate || 0}
          />
          <GaugeIndicator 
            label="Cancellation Rate" 
            value={100 - (overall_stats.cancellation_rate || 0)}
          />
          <div className="bg-gray-900/50 p-6 rounded-lg border border-gray-700">
            <p className="text-xs text-gray-400 uppercase tracking-wide mb-3">Total Revenue</p>
            <p className="text-3xl font-bold text-green-400">₱{Number(quality_report.total_revenue || 0).toLocaleString('en-US', { maximumFractionDigits: 2 })}</p>
            <p className="text-xs text-gray-500 mt-2">{overall_stats.total_appointments || 0} appointments</p>
          </div>
        </div>
      ) : (
        <p className="text-gray-400">No quality report data available</p>
      )}

      {/* Service Performance - Compact Table */}
      {service_stats && service_stats.length > 0 ? (
        <div className="bg-gray-800/50 border border-gray-700 p-6 rounded-lg">
          <div className="flex justify-between items-center mb-4">
            <h3 className="text-lg font-semibold text-amber-50">Service Performance</h3>
            <span className="text-xs text-gray-400">
              {serviceStartIdx + 1}-{Math.min(serviceEndIdx, service_stats.length)} of {service_stats.length}
            </span>
          </div>
          
          <div className="space-y-3 max-h-96 overflow-y-auto">
            {paginatedServiceStats.map((service, idx) => (
              <div key={idx} className="bg-gray-900/50 p-4 rounded-lg border border-gray-700 hover:border-gray-600 transition">
                <div className="flex items-start justify-between mb-2">
                  <div>
                    <p className="font-semibold text-amber-50">{service.service_name}</p>
                    <p className="text-xs text-gray-400 mt-1">{service.completed || 0} completed • {service.cancelled || 0} cancelled</p>
                  </div>
                  <span className={`px-2.5 py-1 rounded text-xs font-semibold ${
                    service.completion_rate >= 80 ? 'bg-green-500/20 text-green-400' :
                    service.completion_rate >= 50 ? 'bg-amber-500/20 text-amber-400' :
                    'bg-red-500/20 text-red-400'
                  }`}>
                    {service.completion_rate || 0}%
                  </span>
                </div>
                <div className="flex items-center justify-between pt-2">
                  <div className="flex-1 bg-gray-700 rounded-full h-2 mr-2 overflow-hidden">
                    <div 
                      className="h-full bg-gradient-to-r from-green-500 to-green-600 rounded-full"
                      style={{width: `${Math.min(service.completion_rate || 0, 100)}%`}}
                    />
                  </div>
                  <p className="text-sm font-semibold text-green-400 min-w-max">₱{Number(service.revenue || 0).toLocaleString('en-US', { maximumFractionDigits: 2 })}</p>
                </div>
              </div>
            ))}
          </div>

          {/* Pagination */}
          {serviceTotalPages > 1 && (
            <div className="mt-4 flex items-center justify-between border-t border-gray-700 pt-4">
              <button
                onClick={() => setServiceCurrentPage(p => Math.max(1, p - 1))}
                disabled={serviceCurrentPage === 1}
                className="px-3 py-1.5 text-sm border border-gray-600 text-gray-300 rounded hover:bg-gray-700 disabled:opacity-50 transition"
              >
                ← Previous
              </button>
              <span className="text-xs text-gray-400">Page {serviceCurrentPage} / {serviceTotalPages}</span>
              <button
                onClick={() => setServiceCurrentPage(p => Math.min(serviceTotalPages, p + 1))}
                disabled={serviceCurrentPage === serviceTotalPages}
                className="px-3 py-1.5 text-sm border border-gray-600 text-gray-300 rounded hover:bg-gray-700 disabled:opacity-50 transition"
              >
                Next →
              </button>
            </div>
          )}
        </div>
      ) : (
        <p className="text-gray-400">No service performance data available</p>
      )}

      {/* Popular Services - Visual Ranking */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {most_popular_services && most_popular_services.length > 0 ? (
          <div className="bg-gray-800/50 border border-green-500/30 p-6 rounded-lg">
            <h3 className="text-lg font-semibold text-green-300 mb-4 flex items-center">
              <span className="w-3 h-3 bg-green-500 rounded-full mr-2"></span>
              Top Services
            </h3>
            <div className="space-y-3">
              {most_popular_services.map((service, idx) => (
                <div key={idx} className="flex items-center gap-3">
                  <div className="w-8 h-8 bg-gradient-to-br from-green-500 to-green-600 rounded-full flex items-center justify-center">
                    <span className="text-white font-bold text-sm">#{idx + 1}</span>
                  </div>
                  <div className="flex-1">
                    <p className="font-medium text-amber-50">{service.service_name}</p>
                    <p className="text-xs text-gray-400">{service.total_appointments || 0} appointments</p>
                  </div>
                </div>
              ))}
            </div>
          </div>
        ) : (
          <div className="bg-gray-800/50 border border-gray-700 p-6 rounded-lg text-center py-8">
            <p className="text-gray-400">No service data available</p>
          </div>
        )}

        {least_popular_services && least_popular_services.length > 0 ? (
          <div className="bg-gray-800/50 border border-orange-500/30 p-6 rounded-lg">
            <h3 className="text-lg font-semibold text-orange-300 mb-4 flex items-center">
              <span className="w-3 h-3 bg-orange-500 rounded-full mr-2"></span>
              Services to Optimize
            </h3>
            <div className="space-y-3">
              {least_popular_services.map((service, idx) => (
                <div key={idx} className="flex items-center gap-3">
                  <div className="w-8 h-8 bg-gradient-to-br from-orange-500 to-orange-600 rounded-full flex items-center justify-center">
                    <span className="text-white font-bold text-sm">!</span>
                  </div>
                  <div className="flex-1">
                    <p className="font-medium text-amber-50">{service.service_name}</p>
                    <p className="text-xs text-gray-400">{service.total_appointments || 0} appointments</p>
                  </div>
                </div>
              ))}
            </div>
          </div>
        ) : (
          <div className="bg-gray-800/50 border border-gray-700 p-6 rounded-lg text-center py-8">
            <p className="text-gray-400">No service data available</p>
          </div>
        )}
      </div>
    </div>
  );
};

const StatItem = ({ label, value }) => (
  <div>
    <p className="text-sm text-gray-400">{label}</p>
    <p className="text-2xl font-bold text-amber-50">{value}</p>
  </div>
);

export default AdminAnalyticsDashboard;
