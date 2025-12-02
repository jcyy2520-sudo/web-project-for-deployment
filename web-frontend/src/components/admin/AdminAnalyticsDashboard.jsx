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

  const fetchAnalytics = useCallback(async () => {
    try {
      setLoading(true);
      setError(null);
      const response = await axios.get('/api/admin/analytics/dashboard');
      if (response.data.success) {
        setAnalyticsData(response.data.data);
        setRefreshTime(new Date());
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
      } else if (err.code === 'ERR_NETWORK') {
        setError('Network error. Make sure the backend server is running on port 8000.');
      } else {
        setError(`Failed to load analytics: ${errorMsg}`);
      }
      console.error('Analytics fetch error:', err);
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    fetchAnalytics();

    let interval;
    if (autoRefreshEnabled) {
      interval = setInterval(fetchAnalytics, 300000);
    }

    if (window.Echo && typeof window.Echo.channel === 'function') {
      try {
        const channel = window.Echo.channel('analytics-updates');
        channel.listen('.analytics.updated', (data) => {
          if (data.data) {
            setAnalyticsData(data.data);
            setRefreshTime(new Date(data.timestamp));
            setError(null);
          }
        });
        return () => {
          channel.stopListening('.analytics.updated');
        };
      } catch (e) {
        console.warn('Echo listener setup failed:', e);
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
  // The data is directly the analytics object with all the keys
  const { auto_alerts = [], slot_utilization = {} } = data;
  const overall = slot_utilization?.overall || {};

  return (
    <div className="space-y-6">
      {/* Summary Stats */}
      {overall && Object.keys(overall).length > 0 ? (
        <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
          <MetricCard label="Total Capacity" value={overall.total_capacity || 0} />
          <MetricCard label="Booked" value={overall.total_booked || 0} />
          <MetricCard label="Available" value={overall.total_available || 0} />
          <MetricCard label="Utilization" value={`${overall.overall_utilization_rate || 0}%`} />
        </div>
      ) : (
        <p className="text-gray-400">No slot data available</p>
      )}

      {/* Alerts */}
      {auto_alerts && auto_alerts.length > 0 ? (
        <div className="grid grid-cols-1 lg:grid-cols-2 gap-4">
          {auto_alerts.map((alert, idx) => (
            <div key={idx} className="bg-gray-800 border border-gray-700 p-4 rounded-lg">
              <p className="font-semibold text-amber-50">{alert.title}</p>
              <p className="text-gray-300 text-sm mt-2">{alert.message}</p>
            </div>
          ))}
        </div>
      ) : (
        <p className="text-gray-400">No alerts at this time</p>
      )}
    </div>
  );
};

const SlotsTab = ({ data }) => {
  // The data is directly the analytics object
  const { slot_utilization = {} } = data;
  const { overall = {}, underbooked_days = [], overbooked_days = [] } = slot_utilization;

  return (
    <div className="space-y-6">
      {/* Overall Stats */}
      {overall && Object.keys(overall).length > 0 ? (
        <div className="bg-gray-800 border border-gray-700 p-6 rounded-lg">
          <h3 className="text-lg font-semibold text-amber-50 mb-4">Overall Statistics</h3>
          <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
            <StatItem label="Total Capacity" value={overall.total_capacity || 0} />
            <StatItem label="Booked" value={overall.total_booked || 0} />
            <StatItem label="Available" value={overall.total_available || 0} />
            <StatItem label="Utilization" value={`${overall.overall_utilization_rate || 0}%`} />
          </div>
        </div>
      ) : (
        <p className="text-gray-400">No slot utilization data available</p>
      )}

      {/* Problematic Days */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {overbooked_days && overbooked_days.length > 0 ? (
          <div className="bg-gray-800 border border-gray-700 p-6 rounded-lg">
            <h3 className="text-lg font-semibold text-amber-50 mb-4">Overbooked Days</h3>
            <div className="space-y-3">
              {overbooked_days.slice(0, 5).map((day, idx) => (
                <div key={idx} className="p-3 bg-gray-700 rounded">
                  <p className="font-medium text-amber-50">{day.day_name} - {day.date}</p>
                  <p className="text-sm text-gray-300">{day.booked} bookings ({day.utilization_rate}% utilized)</p>
                </div>
              ))}
            </div>
          </div>
        ) : (
          <div className="bg-gray-800 border border-gray-700 p-6 rounded-lg">
            <h3 className="text-lg font-semibold text-amber-50 mb-4">Overbooked Days</h3>
            <p className="text-gray-400">No overbooked days detected</p>
          </div>
        )}

        {underbooked_days && underbooked_days.length > 0 ? (
          <div className="bg-gray-800 border border-gray-700 p-6 rounded-lg">
            <h3 className="text-lg font-semibold text-amber-50 mb-4">Underbooked Days</h3>
            <div className="space-y-3">
              {underbooked_days.slice(0, 5).map((day, idx) => (
                <div key={idx} className="p-3 bg-gray-700 rounded">
                  <p className="font-medium text-amber-50">{day.day_name} - {day.date}</p>
                  <p className="text-sm text-gray-300">{day.booked} bookings ({day.utilization_rate}% utilized)</p>
                </div>
              ))}
            </div>
          </div>
        ) : (
          <div className="bg-gray-800 border border-gray-700 p-6 rounded-lg">
            <h3 className="text-lg font-semibold text-amber-50 mb-4">Underbooked Days</h3>
            <p className="text-gray-400">No underbooked days detected</p>
          </div>
        )}
      </div>
    </div>
  );
};

const NoShowTab = ({ data }) => {
  // The data is directly the analytics object
  const { no_show_patterns = {} } = data;
  const { users_with_high_no_show = [], high_risk_time_slots = [] } = no_show_patterns;

  return (
    <div className="space-y-6">
      {/* High Risk Users */}
      {users_with_high_no_show && users_with_high_no_show.length > 0 ? (
        <div className="bg-gray-800 border border-gray-700 p-6 rounded-lg overflow-x-auto">
          <h3 className="text-lg font-semibold text-amber-50 mb-4">Users with High No-Show Rate</h3>
          <table className="w-full text-sm">
            <thead>
              <tr className="border-b border-gray-700">
                <th className="text-left py-2 px-4 text-amber-50">User</th>
                <th className="text-left py-2 px-4 text-amber-50">No-Shows</th>
                <th className="text-left py-2 px-4 text-amber-50">Rate</th>
              </tr>
            </thead>
            <tbody>
              {users_with_high_no_show.slice(0, 10).map((user, idx) => (
                <tr key={idx} className="border-b border-gray-700 hover:bg-gray-700/30">
                  <td className="py-2 px-4 text-gray-300">{user.user_name}</td>
                  <td className="py-2 px-4 text-gray-300">{user.no_show_count || 0}</td>
                  <td className="py-2 px-4 text-gray-300">{user.no_show_rate || 0}%</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      ) : (
        <p className="text-gray-400">No high no-show users to display</p>
      )}

      {/* High Risk Time Slots */}
      {high_risk_time_slots && high_risk_time_slots.length > 0 ? (
        <div className="bg-gray-800 border border-gray-700 p-6 rounded-lg">
          <h3 className="text-lg font-semibold text-amber-50 mb-4">Time Slots with High No-Show Rate</h3>
          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            {high_risk_time_slots.slice(0, 8).map((slot, idx) => (
              <div key={idx} className="p-4 bg-gray-700 rounded">
                <p className="font-semibold text-amber-50">{slot.time}</p>
                <p className="text-sm text-gray-300 mt-1">Appointments: {slot.total_appointments || 0}</p>
                <p className="text-sm text-gray-300">No-Show Rate: {slot.no_show_rate || 0}%</p>
              </div>
            ))}
          </div>
        </div>
      ) : (
        <p className="text-gray-400">No high-risk time slots detected</p>
      )}
    </div>
  );
};

const ForecastTab = ({ data }) => {
  // The data is directly the analytics object
  const { demand_forecast = {} } = data;
  const { day_of_week_stats = [], forecast = [] } = demand_forecast;

  return (
    <div className="space-y-6">
      {/* Weekly Pattern */}
      {day_of_week_stats && day_of_week_stats.length > 0 ? (
        <div className="bg-gray-800 border border-gray-700 p-6 rounded-lg">
          <h3 className="text-lg font-semibold text-amber-50 mb-4">Weekly Pattern</h3>
          <div className="grid grid-cols-1 md:grid-cols-7 gap-4">
            {day_of_week_stats.map((day, idx) => (
              <div key={idx} className="p-4 bg-gray-700 rounded text-center">
                <p className="font-semibold text-amber-50">{day.day}</p>
                <p className="text-2xl font-bold text-amber-400 my-2">{day.avg_appointments || 0}</p>
                <p className="text-xs text-gray-400">avg appointments</p>
              </div>
            ))}
          </div>
        </div>
      ) : (
        <p className="text-gray-400">No weekly pattern data available</p>
      )}

      {/* 30-Day Forecast */}
      {forecast && forecast.length > 0 ? (
        <div className="bg-gray-800 border border-gray-700 p-6 rounded-lg">
          <h3 className="text-lg font-semibold text-amber-50 mb-4">Next 30 Days Forecast</h3>
          <div className="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-5 gap-3">
            {forecast.slice(0, 15).map((day, idx) => (
              <div key={idx} className="p-4 bg-gray-700 rounded">
                <p className="text-sm font-semibold text-amber-50">{day.date}</p>
                <p className="text-lg font-bold text-amber-400 mt-2">{day.predicted_appointments || 0}</p>
                <p className="text-xs text-gray-400 mt-1">appointments</p>
              </div>
            ))}
          </div>
        </div>
      ) : (
        <p className="text-gray-400">No forecast data available</p>
      )}
    </div>
  );
};

const QualityTab = ({ data }) => {
  // The data is directly the analytics object
  const { quality_report = {} } = data;
  const { overall_stats = {}, service_stats = [], most_popular_services = [], least_popular_services = [] } = quality_report;

  console.log('QualityTab data:', { quality_report, overall_stats, service_stats });

  return (
    <div className="space-y-6">
      {/* Key Metrics */}
      {overall_stats && Object.keys(overall_stats).length > 0 ? (
        <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
          <MetricCard label="Completion Rate" value={`${overall_stats.completion_rate || 0}%`} />
          <MetricCard label="Cancellation Rate" value={`${overall_stats.cancellation_rate || 0}%`} />
          <MetricCard label="Total Appointments" value={overall_stats.total_appointments || 0} />
          <MetricCard label="Total Revenue" value={`$${Number(quality_report.total_revenue || 0).toFixed(2)}`} />
        </div>
      ) : (
        <p className="text-gray-400">No quality report data available</p>
      )}

      {/* Service Performance */}
      {service_stats && service_stats.length > 0 ? (
        <div className="bg-gray-800 border border-gray-700 p-6 rounded-lg overflow-x-auto">
          <h3 className="text-lg font-semibold text-amber-50 mb-4">Service Performance</h3>
          <table className="w-full text-sm">
            <thead>
              <tr className="border-b border-gray-700">
                <th className="text-left py-2 px-4 text-amber-50">Service</th>
                <th className="text-left py-2 px-4 text-amber-50">Completed</th>
                <th className="text-left py-2 px-4 text-amber-50">Cancelled</th>
                <th className="text-left py-2 px-4 text-amber-50">Rate</th>
                <th className="text-left py-2 px-4 text-amber-50">Revenue</th>
              </tr>
            </thead>
            <tbody>
              {service_stats.slice(0, 10).map((service, idx) => (
                <tr key={idx} className="border-b border-gray-700 hover:bg-gray-700/30">
                  <td className="py-2 px-4 font-medium text-amber-50">{service.service_name}</td>
                  <td className="py-2 px-4 text-gray-300">{service.completed || 0}</td>
                  <td className="py-2 px-4 text-gray-300">{service.cancelled || 0}</td>
                  <td className="py-2 px-4 text-gray-300">{service.completion_rate || 0}%</td>
                  <td className="py-2 px-4 text-gray-300">${Number(service.revenue || 0).toFixed(2)}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      ) : (
        <p className="text-gray-400">No service performance data available</p>
      )}

      {/* Popular Services */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {most_popular_services && most_popular_services.length > 0 ? (
          <div className="bg-gray-800 border border-gray-700 p-6 rounded-lg">
            <h3 className="text-lg font-semibold text-amber-50 mb-4">Most Popular Services</h3>
            <div className="space-y-3">
              {most_popular_services.map((service, idx) => (
                <div key={idx} className="p-3 bg-gray-700 rounded">
                  <p className="font-medium text-amber-50">{service.service_name}</p>
                  <p className="text-sm text-gray-300">{service.total_appointments || 0} appointments</p>
                </div>
              ))}
            </div>
          </div>
        ) : (
          <div className="bg-gray-800 border border-gray-700 p-6 rounded-lg">
            <h3 className="text-lg font-semibold text-amber-50 mb-4">Most Popular Services</h3>
            <p className="text-gray-400">No service data available</p>
          </div>
        )}

        {least_popular_services && least_popular_services.length > 0 ? (
          <div className="bg-gray-800 border border-gray-700 p-6 rounded-lg">
            <h3 className="text-lg font-semibold text-amber-50 mb-4">Services to Review</h3>
            <div className="space-y-3">
              {least_popular_services.map((service, idx) => (
                <div key={idx} className="p-3 bg-gray-700 rounded">
                  <p className="font-medium text-amber-50">{service.service_name}</p>
                  <p className="text-sm text-gray-300">{service.total_appointments || 0} appointments</p>
                </div>
              ))}
            </div>
          </div>
        ) : (
          <div className="bg-gray-800 border border-gray-700 p-6 rounded-lg">
            <h3 className="text-lg font-semibold text-amber-50 mb-4">Services to Review</h3>
            <p className="text-gray-400">No service data available</p>
          </div>
        )}
      </div>
    </div>
  );
};

const MetricCard = ({ label, value }) => (
  <div className="bg-gray-800 border border-gray-700 p-4 rounded-lg">
    <p className="text-gray-400 text-sm font-medium">{label}</p>
    <p className="text-2xl font-bold text-amber-50 mt-2">{value}</p>
  </div>
);

const StatItem = ({ label, value }) => (
  <div>
    <p className="text-sm text-gray-400">{label}</p>
    <p className="text-2xl font-bold text-amber-50">{value}</p>
  </div>
);

export default AdminAnalyticsDashboard;
