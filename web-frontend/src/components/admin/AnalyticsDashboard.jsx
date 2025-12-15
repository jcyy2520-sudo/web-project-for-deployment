import React, { useState, useEffect } from 'react';
import axios from 'axios';
import LoadingSpinner from './LoadingSpinner';

const AnalyticsDashboard = () => {
  const [metrics, setMetrics] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [hours, setHours] = useState(24);

  useEffect(() => {
    fetchMetrics();
    const interval = setInterval(fetchMetrics, 30000); // Refresh every 30 seconds
    return () => clearInterval(interval);
  }, [hours]);

  const fetchMetrics = async () => {
    try {
      const response = await axios.get('/api/analytics/dashboard', {
        params: { hours }
      });
      setMetrics(response.data);
      setError(null);
    } catch (err) {
      setError(err.response?.data?.message || 'Failed to fetch metrics');
    } finally {
      setLoading(false);
    }
  };

  if (loading) return <LoadingSpinner />;
  if (error) return <div className="alert alert-danger">{error}</div>;
  if (!metrics) return <div>No data available</div>;

  const { latest, historical } = metrics;
  const healthColor = {
    healthy: 'text-success',
    warning: 'text-warning',
    critical: 'text-danger'
  };

  return (
    <div className="analytics-dashboard p-4">
      <div className="d-flex justify-content-between align-items-center mb-4">
        <h1>System Analytics Dashboard</h1>
        <select 
          value={hours} 
          onChange={(e) => setHours(Number(e.target.value))}
          className="form-select w-auto"
        >
          <option value={1}>Last 1 Hour</option>
          <option value={6}>Last 6 Hours</option>
          <option value={24}>Last 24 Hours</option>
          <option value={168}>Last 7 Days</option>
        </select>
      </div>

      {/* Health Status */}
      <div className="row mb-4">
        <div className="col-12">
          <div className="card">
            <div className="card-header bg-light">
              <h5 className="mb-0">System Health Status</h5>
            </div>
            <div className="card-body">
              <div className={`h3 mb-3 ${healthColor[latest?.health_status] || 'text-secondary'}`}>
                {latest?.health_status?.toUpperCase()}
              </div>
              {latest?.is_under_stress && (
                <div className="alert alert-warning">
                  ⚠️ System is currently under stress. Monitor performance closely.
                </div>
              )}
            </div>
          </div>
        </div>
      </div>

      {/* Performance Metrics Grid */}
      <div className="row mb-4">
        {/* CPU */}
        <div className="col-md-3 mb-3">
          <div className="card">
            <div className="card-body">
              <h6 className="card-title">CPU Usage</h6>
              <div className="d-flex align-items-end">
                <div className={`h4 mb-0 ${latest?.cpu?.status === 'critical' ? 'text-danger' : latest?.cpu?.status === 'warning' ? 'text-warning' : 'text-success'}`}>
                  {latest?.cpu?.usage}%
                </div>
              </div>
              <div className="progress mt-2">
                <div
                  className={`progress-bar ${latest?.cpu?.status === 'critical' ? 'bg-danger' : latest?.cpu?.status === 'warning' ? 'bg-warning' : 'bg-success'}`}
                  style={{ width: `${latest?.cpu?.usage}%` }}
                />
              </div>
            </div>
          </div>
        </div>

        {/* Memory */}
        <div className="col-md-3 mb-3">
          <div className="card">
            <div className="card-body">
              <h6 className="card-title">Memory Usage</h6>
              <div className="d-flex align-items-end">
                <div className={`h4 mb-0 ${latest?.memory?.status === 'critical' ? 'text-danger' : latest?.memory?.status === 'warning' ? 'text-warning' : 'text-success'}`}>
                  {latest?.memory?.percent}%
                </div>
              </div>
              <small className="text-muted">
                {latest?.memory?.usage_mb} MB / {latest?.memory?.total_mb} MB
              </small>
              <div className="progress mt-2">
                <div
                  className={`progress-bar ${latest?.memory?.status === 'critical' ? 'bg-danger' : latest?.memory?.status === 'warning' ? 'bg-warning' : 'bg-success'}`}
                  style={{ width: `${latest?.memory?.percent}%` }}
                />
              </div>
            </div>
          </div>
        </div>

        {/* Disk */}
        <div className="col-md-3 mb-3">
          <div className="card">
            <div className="card-body">
              <h6 className="card-title">Disk Usage</h6>
              <div className="d-flex align-items-end">
                <div className={`h4 mb-0 ${latest?.disk?.status === 'critical' ? 'text-danger' : latest?.disk?.status === 'warning' ? 'text-warning' : 'text-success'}`}>
                  {latest?.disk?.percent}%
                </div>
              </div>
              <small className="text-muted">
                {latest?.disk?.usage_mb} MB / {latest?.disk?.total_mb} MB
              </small>
              <div className="progress mt-2">
                <div
                  className={`progress-bar ${latest?.disk?.status === 'critical' ? 'bg-danger' : latest?.disk?.status === 'warning' ? 'bg-warning' : 'bg-success'}`}
                  style={{ width: `${latest?.disk?.percent}%` }}
                />
              </div>
            </div>
          </div>
        </div>

        {/* Database Connections */}
        <div className="col-md-3 mb-3">
          <div className="card">
            <div className="card-body">
              <h6 className="card-title">Database</h6>
              <div className="h5 mb-1">{latest?.database?.connections} Connections</div>
              <small className="text-muted">
                Size: {latest?.database?.size_mb} MB
              </small>
            </div>
          </div>
        </div>
      </div>

      {/* Queue Status */}
      <div className="row mb-4">
        <div className="col-md-6">
          <div className="card">
            <div className="card-body">
              <h6 className="card-title">Queue Status</h6>
              <div className="row">
                <div className="col-6">
                  <div className="h5 text-info">{latest?.queue?.pending}</div>
                  <small className="text-muted">Pending Jobs</small>
                </div>
                <div className="col-6">
                  <div className={`h5 ${latest?.queue?.failed > 0 ? 'text-danger' : 'text-success'}`}>
                    {latest?.queue?.failed}
                  </div>
                  <small className="text-muted">Failed Jobs</small>
                </div>
              </div>
            </div>
          </div>
        </div>

        {/* Load Average */}
        <div className="col-md-6">
          <div className="card">
            <div className="card-body">
              <h6 className="card-title">Load Average</h6>
              <div className="row text-center">
                <div className="col-4">
                  <div className="h6">{latest?.load_average?.['1min']}</div>
                  <small className="text-muted">1 min</small>
                </div>
                <div className="col-4">
                  <div className="h6">{latest?.load_average?.['5min']}</div>
                  <small className="text-muted">5 min</small>
                </div>
                <div className="col-4">
                  <div className="h6">{latest?.load_average?.['15min']}</div>
                  <small className="text-muted">15 min</small>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      {/* Historical Data Info */}
      {historical && Object.keys(historical).length > 0 && (
        <div className="row">
          <div className="col-12">
            <div className="card">
              <div className="card-header bg-light">
                <h6 className="mb-0">Historical Data</h6>
              </div>
              <div className="card-body">
                <p className="text-muted mb-2">Samples analyzed: {historical?.sample_count}</p>
                <div className="row">
                  <div className="col-md-3">
                    <strong>CPU</strong>
                    <p className="mb-1"><small>Avg: {historical?.cpu?.avg}%</small></p>
                    <p className="mb-0"><small>Max: {historical?.cpu?.max}%</small></p>
                  </div>
                  <div className="col-md-3">
                    <strong>Memory</strong>
                    <p className="mb-1"><small>Avg: {historical?.memory?.avg} MB</small></p>
                    <p className="mb-0"><small>Max: {historical?.memory?.max} MB</small></p>
                  </div>
                  <div className="col-md-3">
                    <strong>Disk</strong>
                    <p className="mb-1"><small>Avg: {historical?.disk?.avg} GB</small></p>
                    <p className="mb-0"><small>Max: {historical?.disk?.max} GB</small></p>
                  </div>
                  <div className="col-md-3">
                    <strong>Network</strong>
                    <p className="mb-1"><small>In: {historical?.total_in_bytes}</small></p>
                    <p className="mb-0"><small>Out: {historical?.total_out_bytes}</small></p>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      )}
    </div>
  );
};

export default AnalyticsDashboard;
