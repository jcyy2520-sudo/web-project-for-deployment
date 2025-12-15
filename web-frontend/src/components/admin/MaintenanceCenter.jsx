import React, { useState, useEffect } from 'react';
import axios from 'axios';
import LoadingSpinner from '../LoadingSpinner';

const MaintenanceCenter = () => {
  const [taskStatus, setTaskStatus] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [running, setRunning] = useState(false);
  const [lastRunResults, setLastRunResults] = useState(null);

  useEffect(() => {
    fetchTaskStatus();
  }, []);

  const fetchTaskStatus = async () => {
    try {
      const response = await axios.get('/api/maintenance/tasks/status');
      setTaskStatus(response.data);
      setError(null);
    } catch (err) {
      setError(err.response?.data?.message || 'Failed to fetch task status');
    } finally {
      setLoading(false);
    }
  };

  const runCleanup = async (component = 'all') => {
    if (!confirm(`Run ${component} cleanup task?`)) return;
    
    try {
      setRunning(true);
      const endpoint = component === 'all' 
        ? '/api/maintenance/cleanup'
        : `/api/maintenance/cleanup/${component}`;
      
      const response = await axios.post(endpoint);
      setLastRunResults(response.data);
      
      // Refresh task status
      await fetchTaskStatus();
    } catch (err) {
      alert(err.response?.data?.message || 'Cleanup task failed');
    } finally {
      setRunning(false);
    }
  };

  if (loading) return <LoadingSpinner />;
  if (error) return <div className="alert alert-danger">{error}</div>;

  return (
    <div className="maintenance-center p-4">
      <div className="d-flex justify-content-between align-items-center mb-4">
        <h1>Maintenance Center</h1>
        <button
          className="btn btn-danger"
          onClick={() => runCleanup('all')}
          disabled={running}
        >
          {running ? 'Running...' : '⚙️ Run Full Cleanup'}
        </button>
      </div>

      {/* Last Run Results */}
      {lastRunResults && (
        <div className="row mb-4">
          <div className="col-12">
            <div className={`alert alert-${lastRunResults.success ? 'success' : 'warning'}`}>
              <strong>{lastRunResults.success ? '✓ Cleanup Completed' : '⚠ Cleanup Completed with Issues'}</strong>
              <p className="mt-2 mb-0">
                Run at: {new Date(lastRunResults.timestamp).toLocaleString()}
              </p>
            </div>
          </div>
        </div>
      )}

      {/* Quick Action Buttons */}
      <div className="row mb-4">
        <div className="col-12">
          <div className="card">
            <div className="card-header bg-light">
              <h6 className="mb-0">Quick Cleanup Tasks</h6>
            </div>
            <div className="card-body">
              <div className="row">
                <div className="col-md-6">
                  <button
                    className="btn btn-outline-primary w-100 mb-2"
                    onClick={() => runCleanup('logs')}
                    disabled={running}
                  >
                    🔄 Rotate Logs
                  </button>
                </div>
                <div className="col-md-6">
                  <button
                    className="btn btn-outline-primary w-100 mb-2"
                    onClick={() => runCleanup('cache')}
                    disabled={running}
                  >
                    💾 Clear Cache
                  </button>
                </div>
                <div className="col-md-6">
                  <button
                    className="btn btn-outline-primary w-100 mb-2"
                    onClick={() => runCleanup('sessions')}
                    disabled={running}
                  >
                    🧹 Clean Sessions
                  </button>
                </div>
                <div className="col-md-6">
                  <button
                    className="btn btn-outline-primary w-100 mb-2"
                    onClick={() => runCleanup('temp')}
                    disabled={running}
                  >
                    📁 Remove Temp Files
                  </button>
                </div>
                <div className="col-md-6">
                  <button
                    className="btn btn-outline-primary w-100"
                    onClick={() => runCleanup('backups')}
                    disabled={running}
                  >
                    📦 Archive Old Backups
                  </button>
                </div>
                <div className="col-md-6">
                  <button
                    className="btn btn-outline-primary w-100"
                    onClick={() => runCleanup('metrics')}
                    disabled={running}
                  >
                    📊 Archive Old Metrics
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      {/* Scheduled Tasks */}
      {taskStatus && taskStatus.scheduled_tasks && (
        <div className="row">
          <div className="col-12">
            <div className="card">
              <div className="card-header bg-light">
                <h6 className="mb-0">Scheduled Maintenance Tasks</h6>
              </div>
              <div className="table-responsive">
                <table className="table table-hover mb-0">
                  <thead className="table-light">
                    <tr>
                      <th>Task</th>
                      <th>Frequency</th>
                      <th>Last Run</th>
                      <th>Next Run</th>
                      <th>Status</th>
                    </tr>
                  </thead>
                  <tbody>
                    {taskStatus.scheduled_tasks.map((task, index) => (
                      <tr key={index}>
                        <td><strong>{task.name}</strong></td>
                        <td>{task.frequency}</td>
                        <td>{new Date(task.last_run).toLocaleString()}</td>
                        <td>{new Date(task.next_run).toLocaleString()}</td>
                        <td>
                          <span className="badge bg-success">{task.status}</span>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </div>

            <div className="alert alert-info mt-3">
              <strong>💡 Tip:</strong> Scheduled tasks run automatically at their configured times. No action needed unless you want to run them manually.
            </div>
          </div>
        </div>
      )}
    </div>
  );
};

export default MaintenanceCenter;
