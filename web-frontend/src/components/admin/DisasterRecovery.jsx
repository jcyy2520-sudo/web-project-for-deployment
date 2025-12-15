import React, { useState, useEffect } from 'react';
import axios from 'axios';
import LoadingSpinner from '../LoadingSpinner';

const DisasterRecovery = () => {
  const [backups, setBackups] = useState([]);
  const [scheduleStatus, setScheduleStatus] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [selectedBackup, setSelectedBackup] = useState(null);
  const [showRecoveryPlan, setShowRecoveryPlan] = useState(false);

  useEffect(() => {
    fetchBackupData();
  }, []);

  const fetchBackupData = async () => {
    try {
      const [backupsRes, scheduleRes] = await Promise.all([
        axios.get('/api/backups?limit=20'),
        axios.get('/api/backups/schedule/status')
      ]);
      setBackups(backupsRes.data.data || []);
      setScheduleStatus(scheduleRes.data);
      setError(null);
    } catch (err) {
      setError(err.response?.data?.message || 'Failed to fetch backup data');
    } finally {
      setLoading(false);
    }
  };

  const createBackup = async () => {
    if (!confirm('Create a new database backup now?')) return;
    try {
      setLoading(true);
      const response = await axios.post('/api/backups/create');
      alert('Backup created successfully!');
      fetchBackupData();
    } catch (err) {
      alert(err.response?.data?.message || 'Failed to create backup');
    } finally {
      setLoading(false);
    }
  };

  const verifyBackup = async (id) => {
    try {
      const response = await axios.get(`/api/backups/${id}/verify`);
      alert(response.data.message || 'Backup verified successfully!');
      fetchBackupData();
    } catch (err) {
      alert(err.response?.data?.message || 'Verification failed');
    }
  };

  const testRestore = async (id) => {
    try {
      const response = await axios.post(`/api/backups/${id}/test-restore`);
      if (response.data.success) {
        alert(`Test Passed!\n${response.data.message}\nFile Size: ${response.data.file_size_mb} MB`);
      } else {
        alert(`Test Failed: ${response.data.error}`);
      }
    } catch (err) {
      alert(err.response?.data?.message || 'Test failed');
    }
  };

  const getRecoveryPlan = async (id) => {
    try {
      const response = await axios.get(`/api/backups/${id}/recovery-plan`);
      setSelectedBackup(response.data);
      setShowRecoveryPlan(true);
    } catch (err) {
      alert(err.response?.data?.message || 'Failed to get recovery plan');
    }
  };

  if (loading) return <LoadingSpinner />;
  if (error) return <div className="alert alert-danger">{error}</div>;

  return (
    <div className="disaster-recovery p-4">
      <div className="d-flex justify-content-between align-items-center mb-4">
        <h1>Disaster Recovery & Backups</h1>
        <button 
          className="btn btn-primary"
          onClick={createBackup}
        >
          + Create Backup Now
        </button>
      </div>

      {/* Backup Schedule Status */}
      {scheduleStatus && (
        <div className="row mb-4">
          <div className="col-12">
            <div className="card">
              <div className="card-header bg-light">
                <h6 className="mb-0">Backup Schedule Status</h6>
              </div>
              <div className="card-body">
                <div className="row">
                  <div className="col-md-3">
                    <p className="text-muted mb-1">Frequency</p>
                    <p className="mb-0"><strong>{scheduleStatus.backup_frequency}</strong></p>
                  </div>
                  <div className="col-md-3">
                    <p className="text-muted mb-1">Last Backup</p>
                    <p className="mb-0">
                      <strong>{scheduleStatus.last_backup?.completed_at ? new Date(scheduleStatus.last_backup.completed_at).toLocaleString() : 'Never'}</strong>
                    </p>
                  </div>
                  <div className="col-md-3">
                    <p className="text-muted mb-1">Total Backup Size</p>
                    <p className="mb-0"><strong>{scheduleStatus.total_backup_size_mb} MB</strong></p>
                  </div>
                  <div className="col-md-3">
                    <p className="text-muted mb-1">Available Backups</p>
                    <p className="mb-0"><strong>{scheduleStatus.backups_available}</strong></p>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      )}

      {/* Recovery Readiness */}
      <div className="row mb-4">
        <div className="col-12">
          <div className="card border-success">
            <div className="card-header bg-success text-white">
              <h6 className="mb-0">✓ Recovery Readiness</h6>
            </div>
            <div className="card-body">
              <ul className="list-unstyled mb-0">
                <li className="mb-2">✓ Automated daily backups enabled</li>
                <li className="mb-2">✓ Point-in-time recovery available</li>
                <li className="mb-2">✓ Backup verification procedures in place</li>
                <li className="mb-2">✓ Recovery testing recommended monthly</li>
              </ul>
            </div>
          </div>
        </div>
      </div>

      {/* Backups List */}
      <div className="row">
        <div className="col-12">
          <div className="card">
            <div className="card-header bg-light">
              <h6 className="mb-0">Available Backups</h6>
            </div>
            <div className="table-responsive">
              <table className="table table-hover mb-0">
                <thead className="table-light">
                  <tr>
                    <th>Filename</th>
                    <th>Size</th>
                    <th>Created</th>
                    <th>Status</th>
                    <th>Verified</th>
                    <th>Actions</th>
                  </tr>
                </thead>
                <tbody>
                  {backups.map((backup) => (
                    <tr key={backup.id}>
                      <td><code>{backup.filename}</code></td>
                      <td>{(backup.size / 1024 / 1024).toFixed(2)} MB</td>
                      <td>{new Date(backup.created_at).toLocaleString()}</td>
                      <td>
                        <span className={`badge bg-${backup.status === 'completed' ? 'success' : backup.status === 'failed' ? 'danger' : 'warning'}`}>
                          {backup.status}
                        </span>
                      </td>
                      <td>
                        {backup.is_verified ? (
                          <span className="badge bg-success">✓</span>
                        ) : (
                          <span className="badge bg-secondary">-</span>
                        )}
                      </td>
                      <td>
                        <div className="btn-group btn-group-sm" role="group">
                          <button
                            className="btn btn-outline-info"
                            onClick={() => verifyBackup(backup.id)}
                            title="Verify backup integrity"
                          >
                            Verify
                          </button>
                          <button
                            className="btn btn-outline-primary"
                            onClick={() => testRestore(backup.id)}
                            title="Test restore procedure"
                          >
                            Test
                          </button>
                          <button
                            className="btn btn-outline-secondary"
                            onClick={() => getRecoveryPlan(backup.id)}
                            title="View recovery plan"
                          >
                            Plan
                          </button>
                        </div>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>

      {/* Recovery Plan Modal */}
      {showRecoveryPlan && selectedBackup && (
        <div className="modal d-block" style={{ backgroundColor: 'rgba(0,0,0,0.5)' }}>
          <div className="modal-dialog modal-lg">
            <div className="modal-content">
              <div className="modal-header">
                <h5 className="modal-title">Recovery Plan: {selectedBackup.backup_info?.filename}</h5>
                <button
                  className="btn-close"
                  onClick={() => setShowRecoveryPlan(false)}
                />
              </div>
              <div className="modal-body">
                <h6>Pre-Recovery Steps:</h6>
                <ol>
                  {selectedBackup.pre_recovery_steps?.map((step, i) => (
                    <li key={i}>{step}</li>
                  ))}
                </ol>

                <h6 className="mt-3">Recovery Procedure:</h6>
                <ol>
                  {selectedBackup.recovery_steps?.map((step, i) => (
                    <li key={i}>{step}</li>
                  ))}
                </ol>

                <h6 className="mt-3">Post-Recovery Verification:</h6>
                <ol>
                  {selectedBackup.post_recovery_steps?.map((step, i) => (
                    <li key={i}>{step}</li>
                  ))}
                </ol>

                <div className="alert alert-info mt-3">
                  <strong>Note:</strong> Always perform a test restore before relying on backups for recovery.
                </div>
              </div>
              <div className="modal-footer">
                <button
                  className="btn btn-secondary"
                  onClick={() => setShowRecoveryPlan(false)}
                >
                  Close
                </button>
              </div>
            </div>
          </div>
        </div>
      )}
    </div>
  );
};

export default DisasterRecovery;
