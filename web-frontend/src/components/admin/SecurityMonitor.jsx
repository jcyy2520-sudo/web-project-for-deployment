import React, { useState, useEffect } from 'react';
import axios from 'axios';
import LoadingSpinner from '../LoadingSpinner';

const SecurityMonitor = () => {
  const [securityData, setSecurityData] = useState(null);
  const [blockedIps, setBlockedIps] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [ipToBlock, setIpToBlock] = useState('');
  const [autoRefresh, setAutoRefresh] = useState(true);

  useEffect(() => {
    fetchSecurityData();
    if (autoRefresh) {
      const interval = setInterval(fetchSecurityData, 10000); // Refresh every 10 seconds
      return () => clearInterval(interval);
    }
  }, [autoRefresh]);

  const fetchSecurityData = async () => {
    try {
      const [securityRes, blockedRes] = await Promise.all([
        axios.get('/api/security/summary'),
        axios.get('/api/security/blocked-ips')
      ]);
      setSecurityData(securityRes.data);
      setBlockedIps(blockedRes.data.blocked_ips || []);
      setError(null);
    } catch (err) {
      setError(err.response?.data?.message || 'Failed to fetch security data');
    } finally {
      setLoading(false);
    }
  };

  const blockIp = async () => {
    if (!ipToBlock) return;
    try {
      await axios.post('/api/security/ip/block', { ip_address: ipToBlock });
      setIpToBlock('');
      fetchSecurityData();
    } catch (err) {
      alert(err.response?.data?.message || 'Failed to block IP');
    }
  };

  const unblockIp = async (ip) => {
    try {
      await axios.post('/api/security/ip/unblock', { ip_address: ip });
      fetchSecurityData();
    } catch (err) {
      alert(err.response?.data?.message || 'Failed to unblock IP');
    }
  };

  if (loading) return <LoadingSpinner />;
  if (error) return <div className="alert alert-danger">{error}</div>;

  const riskLevel = securityData?.high_risk_events > 0 ? 'danger' : 
                    securityData?.suspicious_events > 0 ? 'warning' : 'success';

  return (
    <div className="security-monitor p-4">
      <div className="d-flex justify-content-between align-items-center mb-4">
        <h1>Security Monitor</h1>
        <div>
          <label className="me-2">
            <input 
              type="checkbox" 
              checked={autoRefresh} 
              onChange={(e) => setAutoRefresh(e.target.checked)}
            />
            Auto Refresh
          </label>
          <button 
            className="btn btn-sm btn-outline-secondary"
            onClick={fetchSecurityData}
          >
            Refresh Now
          </button>
        </div>
      </div>

      {/* Security Overview */}
      <div className="row mb-4">
        <div className="col-md-4">
          <div className={`card border-${riskLevel}`}>
            <div className="card-body">
              <h6 className="card-title">Risk Status</h6>
              <div className={`h3 text-${riskLevel}`}>
                {riskLevel.toUpperCase()}
              </div>
            </div>
          </div>
        </div>
        <div className="col-md-4">
          <div className="card">
            <div className="card-body">
              <h6 className="card-title">Recent Security Events</h6>
              <div className="h3">{securityData?.total_events}</div>
            </div>
          </div>
        </div>
        <div className="col-md-4">
          <div className="card">
            <div className="card-body">
              <h6 className="card-title">Blocked IPs</h6>
              <div className="h3">{blockedIps?.length || 0}</div>
            </div>
          </div>
        </div>
      </div>

      {/* Threat Summary */}
      <div className="row mb-4">
        <div className="col-12">
          <div className="card">
            <div className="card-header bg-light">
              <h6 className="mb-0">Threat Summary</h6>
            </div>
            <div className="card-body">
              <div className="row">
                <div className="col-md-3">
                  <p className="mb-1"><strong>Suspicious Events</strong></p>
                  <div className={`h5 text-${securityData?.suspicious_events > 0 ? 'warning' : 'success'}`}>
                    {securityData?.suspicious_events}
                  </div>
                </div>
                <div className="col-md-3">
                  <p className="mb-1"><strong>High Risk Events</strong></p>
                  <div className={`h5 text-${securityData?.high_risk_events > 0 ? 'danger' : 'success'}`}>
                    {securityData?.high_risk_events}
                  </div>
                </div>
                <div className="col-md-3">
                  <p className="mb-1"><strong>Currently Blocked IPs</strong></p>
                  <div className="h5 text-danger">{securityData?.blocked_ips}</div>
                </div>
                <div className="col-md-3">
                  <p className="mb-1"><strong>By Type</strong></p>
                  {securityData?.by_type && Object.entries(securityData.by_type).map(([type, count]) => (
                    <small key={type} className="d-block">{type}: {count}</small>
                  ))}
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      {/* Block IP Section */}
      <div className="row mb-4">
        <div className="col-12">
          <div className="card">
            <div className="card-header bg-light">
              <h6 className="mb-0">Manual IP Blocking</h6>
            </div>
            <div className="card-body">
              <div className="input-group">
                <input
                  type="text"
                  className="form-control"
                  placeholder="Enter IP address to block"
                  value={ipToBlock}
                  onChange={(e) => setIpToBlock(e.target.value)}
                  pattern="\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}"
                />
                <button
                  className="btn btn-danger"
                  onClick={blockIp}
                  disabled={!ipToBlock}
                >
                  Block IP
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>

      {/* Blocked IPs List */}
      {blockedIps.length > 0 && (
        <div className="row">
          <div className="col-12">
            <div className="card">
              <div className="card-header bg-light">
                <h6 className="mb-0">Currently Blocked IPs</h6>
              </div>
              <div className="table-responsive">
                <table className="table table-hover mb-0">
                  <thead className="table-light">
                    <tr>
                      <th>IP Address</th>
                      <th>Risk Score</th>
                      <th>Blocked Since</th>
                      <th>Blocked Until</th>
                      <th>Reason</th>
                      <th>Action</th>
                    </tr>
                  </thead>
                  <tbody>
                    {blockedIps.map((block) => (
                      <tr key={block.ip}>
                        <td><code>{block.ip}</code></td>
                        <td>
                          <span className={`badge bg-${block.risk_score > 80 ? 'danger' : 'warning'}`}>
                            {block.risk_score}
                          </span>
                        </td>
                        <td>{new Date(block.blocked_since).toLocaleString()}</td>
                        <td>{new Date(block.blocked_until).toLocaleString()}</td>
                        <td>{block.reason}</td>
                        <td>
                          <button
                            className="btn btn-sm btn-success"
                            onClick={() => unblockIp(block.ip)}
                          >
                            Unblock
                          </button>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>
      )}
    </div>
  );
};

export default SecurityMonitor;
