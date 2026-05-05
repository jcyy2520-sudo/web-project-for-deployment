import React, { useState, useEffect } from 'react';
import axios from 'axios';

/**
 * System Monitoring Dashboard
 * Displays health checks, alerts, backups, job metrics, and frontend errors
 */
const SystemMonitoringDashboard = () => {
  const [activeTab, setActiveTab] = useState('health');
  const [loading, setLoading] = useState(false);
  const [data, setData] = useState({
    health: null,
    metrics: null,
    alerts: null,
    backups: null,
    jobs: null,
    frontendErrors: null,
  });

  // Fetch health check
  const fetchHealth = async () => {
    try {
      const response = await axios.get('/api/health/detailed');
      setData(prev => ({ ...prev, health: response.data }));
    } catch (error) {
      console.error('Failed to fetch health:', error);
    }
  };

  // Fetch metrics dashboard
  const fetchMetrics = async () => {
    try {
      const response = await axios.get('/api/admin/metrics/dashboard');
      setData(prev => ({ ...prev, metrics: response.data }));
    } catch (error) {
      console.error('Failed to fetch metrics:', error);
    }
  };

  // Fetch alerts dashboard
  const fetchAlerts = async () => {
    try {
      const response = await axios.get('/api/admin/alerts/dashboard');
      setData(prev => ({ ...prev, alerts: response.data }));
    } catch (error) {
      console.error('Failed to fetch alerts:', error);
    }
  };

  // Fetch backups
  const fetchBackups = async () => {
    try {
      const response = await axios.get('/api/admin/backups');
      setData(prev => ({ ...prev, backups: response.data }));
    } catch (error) {
      console.error('Failed to fetch backups:', error);
    }
  };

  // Fetch job metrics
  const fetchJobs = async () => {
    try {
      const response = await axios.get('/api/admin/jobs/dashboard');
      setData(prev => ({ ...prev, jobs: response.data }));
    } catch (error) {
      console.error('Failed to fetch jobs:', error);
    }
  };

  // Fetch frontend errors
  const fetchFrontendErrors = async () => {
    try {
      const response = await axios.get('/api/admin/frontend-errors/stats');
      setData(prev => ({ ...prev, frontendErrors: response.data }));
    } catch (error) {
      console.error('Failed to fetch frontend errors:', error);
    }
  };

  // Load data when tab changes
  useEffect(() => {
    setLoading(true);
    const loadData = async () => {
      switch (activeTab) {
        case 'health':
          await fetchHealth();
          break;
        case 'metrics':
          await fetchMetrics();
          break;
        case 'alerts':
          await fetchAlerts();
          break;
        case 'backups':
          await fetchBackups();
          break;
        case 'jobs':
          await fetchJobs();
          break;
        case 'frontend-errors':
          await fetchFrontendErrors();
          break;
        default:
          break;
      }
      setLoading(false);
    };
    loadData();
  }, [activeTab]);

  return (
    <div className="min-h-screen bg-gray-900 text-white p-6">
      <div className="max-w-7xl mx-auto">
        <h1 className="text-4xl font-bold mb-8 text-amber-400">System Monitoring</h1>

        {/* Tabs */}
        <div className="flex gap-2 mb-8 flex-wrap">
          {[
            { id: 'health', label: '🏥 Health' },
            { id: 'metrics', label: '📊 Metrics' },
            { id: 'alerts', label: '⚠️ Alerts' },
            { id: 'backups', label: '💾 Backups' },
            { id: 'jobs', label: '⚙️ Jobs' },
            { id: 'frontend-errors', label: '🐛 Frontend Errors' },
          ].map(tab => (
            <button
              key={tab.id}
              onClick={() => setActiveTab(tab.id)}
              className={`px-4 py-2 rounded transition-colors ${
                activeTab === tab.id
                  ? 'bg-amber-500 text-gray-900 font-bold'
                  : 'bg-gray-700 hover:bg-gray-600'
              }`}
            >
              {tab.label}
            </button>
          ))}
        </div>

        {/* Loading indicator */}
        {loading && (
          <div className="text-center text-amber-400 mb-4">Loading...</div>
        )}

        {/* Health Check Tab */}
        {activeTab === 'health' && data.health && (
          <div className="bg-gray-800 rounded-lg p-6 mb-8">
            <h2 className="text-2xl font-bold mb-4 text-green-400">System Health</h2>
            <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
              {data.health.checks?.map((check, idx) => (
                <div key={idx} className={`p-4 rounded ${check.status === 'healthy' ? 'bg-green-900' : 'bg-red-900'}`}>
                  <h3 className="font-bold capitalize">{check.component}</h3>
                  <p className={check.status === 'healthy' ? 'text-green-300' : 'text-red-300'}>
                    {check.status}
                  </p>
                  {check.details && <p className="text-sm text-gray-300 mt-2">{check.details}</p>}
                </div>
              ))}
            </div>
          </div>
        )}

        {/* Metrics Tab */}
        {activeTab === 'metrics' && data.metrics && (
          <div className="bg-gray-800 rounded-lg p-6 mb-8">
            <h2 className="text-2xl font-bold mb-4 text-blue-400">Performance Metrics</h2>
            <div className="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
              <div className="bg-gray-700 p-4 rounded">
                <p className="text-gray-300">Total Requests</p>
                <p className="text-3xl font-bold text-blue-400">{data.metrics.total_requests}</p>
              </div>
              <div className="bg-gray-700 p-4 rounded">
                <p className="text-gray-300">Avg Response Time</p>
                <p className="text-3xl font-bold text-blue-400">{data.metrics.avg_response_time?.toFixed(2)}ms</p>
              </div>
              <div className="bg-gray-700 p-4 rounded">
                <p className="text-gray-300">Error Rate</p>
                <p className="text-3xl font-bold text-red-400">{data.metrics.error_rate?.toFixed(2)}%</p>
              </div>
              <div className="bg-gray-700 p-4 rounded">
                <p className="text-gray-300">Success Rate</p>
                <p className="text-3xl font-bold text-green-400">{(100 - (data.metrics.error_rate || 0))?.toFixed(2)}%</p>
              </div>
            </div>
          </div>
        )}

        {/* Alerts Tab */}
        {activeTab === 'alerts' && data.alerts && (
          <div className="bg-gray-800 rounded-lg p-6 mb-8">
            <h2 className="text-2xl font-bold mb-4 text-yellow-400">Active Alerts</h2>
            <div className="mb-6">
              <p className="text-gray-300">Total: <span className="font-bold">{data.alerts.total}</span></p>
              <p className="text-gray-300">Active: <span className="font-bold text-red-400">{data.alerts.active}</span></p>
              <p className="text-gray-300">Acknowledged: <span className="font-bold text-blue-400">{data.alerts.acknowledged}</span></p>
            </div>
            {data.alerts.recent_alerts?.slice(0, 5).map(alert => (
              <div key={alert.id} className="bg-gray-700 p-4 rounded mb-2">
                <p className="font-bold text-yellow-400">{alert.title}</p>
                <p className="text-sm text-gray-300">{alert.description}</p>
              </div>
            ))}
          </div>
        )}

        {/* Backups Tab */}
        {activeTab === 'backups' && data.backups && (
          <div className="bg-gray-800 rounded-lg p-6 mb-8">
            <h2 className="text-2xl font-bold mb-4 text-cyan-400">Database Backups</h2>
            <div className="mb-6">
              <p className="text-gray-300">Total Backups: <span className="font-bold">{data.backups.data?.length || 0}</span></p>
              <p className="text-gray-300">Total Size: <span className="font-bold">{(data.backups.total_size / 1024 / 1024)?.toFixed(2)} MB</span></p>
            </div>
            <div className="overflow-x-auto">
              <table className="w-full text-sm">
                <thead className="bg-gray-700">
                  <tr>
                    <th className="px-4 py-2 text-left">Filename</th>
                    <th className="px-4 py-2 text-left">Size</th>
                    <th className="px-4 py-2 text-left">Created At</th>
                  </tr>
                </thead>
                <tbody>
                  {data.backups.data?.slice(0, 10).map((backup, idx) => (
                    <tr key={idx} className="border-t border-gray-700">
                      <td className="px-4 py-2">{backup.filename}</td>
                      <td className="px-4 py-2">{(backup.file_size / 1024 / 1024)?.toFixed(2)} MB</td>
                      <td className="px-4 py-2">{new Date(backup.created_at).toLocaleDateString()}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </div>
        )}

        {/* Jobs Tab */}
        {activeTab === 'jobs' && data.jobs && (
          <div className="bg-gray-800 rounded-lg p-6 mb-8">
            <h2 className="text-2xl font-bold mb-4 text-purple-400">Background Jobs</h2>
            <div className="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
              <div className="bg-gray-700 p-4 rounded">
                <p className="text-gray-300">Total</p>
                <p className="text-3xl font-bold text-purple-400">{data.jobs.stats?.total}</p>
              </div>
              <div className="bg-gray-700 p-4 rounded">
                <p className="text-gray-300">Completed</p>
                <p className="text-3xl font-bold text-green-400">{data.jobs.stats?.completed}</p>
              </div>
              <div className="bg-gray-700 p-4 rounded">
                <p className="text-gray-300">Failed</p>
                <p className="text-3xl font-bold text-red-400">{data.jobs.stats?.failed}</p>
              </div>
              <div className="bg-gray-700 p-4 rounded">
                <p className="text-gray-300">Success Rate</p>
                <p className="text-3xl font-bold text-purple-400">{data.jobs.stats?.success_rate?.toFixed(2)}%</p>
              </div>
            </div>
          </div>
        )}

        {/* Frontend Errors Tab */}
        {activeTab === 'frontend-errors' && data.frontendErrors && (
          <div className="bg-gray-800 rounded-lg p-6 mb-8">
            <h2 className="text-2xl font-bold mb-4 text-red-400">Frontend Errors</h2>
            <div className="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
              <div className="bg-gray-700 p-4 rounded">
                <p className="text-gray-300">Total Errors</p>
                <p className="text-3xl font-bold text-red-400">{data.frontendErrors.total}</p>
              </div>
              <div className="bg-gray-700 p-4 rounded">
                <p className="text-gray-300">Critical</p>
                <p className="text-3xl font-bold text-red-500">{data.frontendErrors.critical}</p>
              </div>
              <div className="bg-gray-700 p-4 rounded">
                <p className="text-gray-300">Unreported</p>
                <p className="text-3xl font-bold text-yellow-400">{data.frontendErrors.unreported}</p>
              </div>
              <div className="bg-gray-700 p-4 rounded">
                <p className="text-gray-300">Affected Users</p>
                <p className="text-3xl font-bold text-blue-400">{data.frontendErrors.affected_users}</p>
              </div>
            </div>
            {data.frontendErrors.by_type && (
              <div className="mt-4">
                <h3 className="font-bold mb-2">Errors by Type:</h3>
                <div className="bg-gray-700 p-4 rounded">
                  {Object.entries(data.frontendErrors.by_type).map(([type, count]) => (
                    <p key={type} className="text-gray-300">{type}: <span className="font-bold">{count}</span></p>
                  ))}
                </div>
              </div>
            )}
          </div>
        )}
      </div>
    </div>
  );
};

export default SystemMonitoringDashboard;
