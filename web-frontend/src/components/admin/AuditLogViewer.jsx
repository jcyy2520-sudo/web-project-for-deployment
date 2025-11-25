import { useState, useEffect } from 'react';
import { useApi } from '../hooks/useApi';
import axios from 'axios';
import {
  ShieldCheckIcon,
  FunnelIcon,
  DocumentMagnifyingGlassIcon,
  ExclamationTriangleIcon
} from '@heroicons/react/24/outline';

const AuditLogViewer = ({ isDarkMode = true }) => {
  const { callApi } = useApi();
  const [logs, setLogs] = useState([]);
  const [loading, setLoading] = useState(false);
  const [filters, setFilters] = useState({
    action: '',
    entity_type: '',
    user_id: '',
    status: '',
    start_date: '',
    end_date: ''
  });
  const [currentPage, setCurrentPage] = useState(1);
  const [pageCount, setPageCount] = useState(0);

  useEffect(() => {
    loadLogs();
  }, [currentPage, filters]);

  const loadLogs = async () => {
    setLoading(true);
    const params = {
      per_page: 50,
      page: currentPage,
      ...Object.fromEntries(
        Object.entries(filters).filter(([_, value]) => value !== '')
      )
    };

    const result = await callApi((signal) =>
      axios.get('/api/audit-logs', { signal, params })
    );

    if (result.success) {
      setLogs(result.data.data?.data || []);
      setPageCount(result.data.data?.last_page || 1);
    }
    setLoading(false);
  };

  const handleFilterChange = (filterName, value) => {
    setFilters(prev => ({
      ...prev,
      [filterName]: value
    }));
    setCurrentPage(1);
  };

  const getActionColor = (action) => {
    if (action.includes('create') || action.includes('post')) return 'bg-green-500/20 text-green-400';
    if (action.includes('update') || action.includes('put')) return 'bg-blue-500/20 text-blue-400';
    if (action.includes('delete')) return 'bg-red-500/20 text-red-400';
    if (action.includes('view') || action.includes('get')) return 'bg-gray-500/20 text-gray-400';
    return 'bg-amber-500/20 text-amber-400';
  };

  const getActionColorLight = (action) => {
    if (action.includes('create') || action.includes('post')) return 'bg-green-100 text-green-700';
    if (action.includes('update') || action.includes('put')) return 'bg-blue-100 text-blue-700';
    if (action.includes('delete')) return 'bg-red-100 text-red-700';
    if (action.includes('view') || action.includes('get')) return 'bg-gray-200 text-gray-700';
    return 'bg-amber-100 text-amber-700';
  };

  const getStatusIcon = (status) => {
    if (status === 'success') return '✓';
    if (status === 'failed') return '✗';
    if (status === 'unauthorized') return '⚠';
    return '—';
  };

  const getStatusColor = (status) => {
    if (status === 'success') return 'text-green-400';
    if (status === 'failed') return 'text-red-400';
    if (status === 'unauthorized') return 'text-yellow-400';
    return 'text-gray-400';
  };

  const getStatusColorLight = (status) => {
    if (status === 'success') return 'text-green-700';
    if (status === 'failed') return 'text-red-700';
    if (status === 'unauthorized') return 'text-yellow-700';
    return 'text-gray-700';
  };

  return (
    <div className="space-y-4">
      {/* Header */}
      <div className="flex justify-between items-center">
        <div>
          <h2 className={`text-lg font-bold flex items-center ${isDarkMode ? 'text-amber-50' : 'text-amber-900'}`}>
            <ShieldCheckIcon className="h-5 w-5 mr-2" />
            Security Audit Logs
          </h2>
          <p className={`text-sm ${isDarkMode ? 'text-gray-400' : 'text-gray-600'} mt-1`}>
            Track all user actions and system events
          </p>
        </div>
      </div>

      {/* Filters */}
      <div className={`grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-2 p-4 rounded-lg border ${
        isDarkMode ? 'bg-gray-800/50 border-gray-700' : 'bg-white border-gray-200'
      }`}>
        <div>
          <label className={`block text-xs font-medium mb-1 ${isDarkMode ? 'text-amber-400' : 'text-amber-600'}`}>
            Action
          </label>
          <input
            type="text"
            value={filters.action}
            onChange={(e) => handleFilterChange('action', e.target.value)}
            placeholder="e.g., create"
            className={`w-full px-2 py-1 text-xs rounded border ${
              isDarkMode
                ? 'bg-gray-900 border-gray-600 text-amber-50'
                : 'bg-white border-gray-300 text-gray-900'
            }`}
          />
        </div>

        <div>
          <label className={`block text-xs font-medium mb-1 ${isDarkMode ? 'text-amber-400' : 'text-amber-600'}`}>
            Entity Type
          </label>
          <select
            value={filters.entity_type}
            onChange={(e) => handleFilterChange('entity_type', e.target.value)}
            className={`w-full px-2 py-1 text-xs rounded border ${
              isDarkMode
                ? 'bg-gray-900 border-gray-600 text-amber-50'
                : 'bg-white border-gray-300 text-gray-900'
            }`}
          >
            <option value="">All</option>
            <option value="User">User</option>
            <option value="Appointment">Appointment</option>
            <option value="Document">Document</option>
            <option value="Message">Message</option>
          </select>
        </div>

        <div>
          <label className={`block text-xs font-medium mb-1 ${isDarkMode ? 'text-amber-400' : 'text-amber-600'}`}>
            Status
          </label>
          <select
            value={filters.status}
            onChange={(e) => handleFilterChange('status', e.target.value)}
            className={`w-full px-2 py-1 text-xs rounded border ${
              isDarkMode
                ? 'bg-gray-900 border-gray-600 text-amber-50'
                : 'bg-white border-gray-300 text-gray-900'
            }`}
          >
            <option value="">All</option>
            <option value="success">Success</option>
            <option value="failed">Failed</option>
            <option value="unauthorized">Unauthorized</option>
          </select>
        </div>

        <div>
          <label className={`block text-xs font-medium mb-1 ${isDarkMode ? 'text-amber-400' : 'text-amber-600'}`}>
            From Date
          </label>
          <input
            type="date"
            value={filters.start_date}
            onChange={(e) => handleFilterChange('start_date', e.target.value)}
            className={`w-full px-2 py-1 text-xs rounded border ${
              isDarkMode
                ? 'bg-gray-900 border-gray-600 text-amber-50'
                : 'bg-white border-gray-300 text-gray-900'
            }`}
          />
        </div>

        <div>
          <label className={`block text-xs font-medium mb-1 ${isDarkMode ? 'text-amber-400' : 'text-amber-600'}`}>
            To Date
          </label>
          <input
            type="date"
            value={filters.end_date}
            onChange={(e) => handleFilterChange('end_date', e.target.value)}
            className={`w-full px-2 py-1 text-xs rounded border ${
              isDarkMode
                ? 'bg-gray-900 border-gray-600 text-amber-50'
                : 'bg-white border-gray-300 text-gray-900'
            }`}
          />
        </div>

        <div>
          <label className={`block text-xs font-medium mb-1 ${isDarkMode ? 'text-amber-400' : 'text-amber-600'}`}>
            Action
          </label>
          <button
            onClick={() => {
              setFilters({
                action: '',
                entity_type: '',
                user_id: '',
                status: '',
                start_date: '',
                end_date: ''
              });
              setCurrentPage(1);
            }}
            className="w-full px-2 py-1 text-xs rounded bg-amber-500/20 text-amber-400 hover:bg-amber-500/30 border border-amber-500/50 transition-all"
          >
            Reset
          </button>
        </div>
      </div>

      {/* Logs Table */}
      <div className={`border rounded-lg overflow-hidden ${
        isDarkMode ? 'bg-gray-900 border-gray-700' : 'bg-white border-gray-200'
      }`}>
        <div className="overflow-x-auto">
          <table className="w-full text-xs">
            <thead className={isDarkMode ? 'bg-gray-800' : 'bg-gray-100'}>
              <tr>
                <th className={`px-4 py-3 text-left font-semibold ${isDarkMode ? 'text-amber-400' : 'text-amber-600'}`}>
                  Timestamp
                </th>
                <th className={`px-4 py-3 text-left font-semibold ${isDarkMode ? 'text-amber-400' : 'text-amber-600'}`}>
                  User
                </th>
                <th className={`px-4 py-3 text-left font-semibold ${isDarkMode ? 'text-amber-400' : 'text-amber-600'}`}>
                  Action
                </th>
                <th className={`px-4 py-3 text-left font-semibold ${isDarkMode ? 'text-amber-400' : 'text-amber-600'}`}>
                  Entity
                </th>
                <th className={`px-4 py-3 text-left font-semibold ${isDarkMode ? 'text-amber-400' : 'text-amber-600'}`}>
                  Description
                </th>
                <th className={`px-4 py-3 text-left font-semibold ${isDarkMode ? 'text-amber-400' : 'text-amber-600'}`}>
                  Status
                </th>
              </tr>
            </thead>
            <tbody>
              {loading ? (
                <tr>
                  <td colSpan="6" className={`px-4 py-8 text-center ${isDarkMode ? 'text-gray-400' : 'text-gray-600'}`}>
                    Loading...
                  </td>
                </tr>
              ) : logs.length === 0 ? (
                <tr>
                  <td colSpan="6" className={`px-4 py-8 text-center ${isDarkMode ? 'text-gray-400' : 'text-gray-600'}`}>
                    No audit logs found
                  </td>
                </tr>
              ) : (
                logs.map((log, idx) => (
                  <tr key={log.id} className={`border-t ${isDarkMode ? 'border-gray-700 hover:bg-gray-800/50' : 'border-gray-200 hover:bg-gray-50'} transition-colors`}>
                    <td className={`px-4 py-3 ${isDarkMode ? 'text-gray-400' : 'text-gray-700'}`}>
                      {new Date(log.created_at).toLocaleString()}
                    </td>
                    <td className={`px-4 py-3 ${isDarkMode ? 'text-amber-50' : 'text-gray-900'}`}>
                      {log.user?.first_name} {log.user?.last_name}
                    </td>
                    <td className={`px-4 py-3`}>
                      <span className={`px-2 py-1 rounded text-xs font-medium ${
                        isDarkMode ? getActionColor(log.action) : getActionColorLight(log.action)
                      }`}>
                        {log.action}
                      </span>
                    </td>
                    <td className={`px-4 py-3 ${isDarkMode ? 'text-gray-400' : 'text-gray-700'}`}>
                      {log.entity_type} #{log.entity_id}
                    </td>
                    <td className={`px-4 py-3 ${isDarkMode ? 'text-gray-400' : 'text-gray-700'} max-w-xs truncate`}>
                      {log.description}
                    </td>
                    <td className={`px-4 py-3`}>
                      <span className={`${
                        isDarkMode ? getStatusColor(log.status) : getStatusColorLight(log.status)
                      } font-semibold`}>
                        {getStatusIcon(log.status)} {log.status}
                      </span>
                    </td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>
      </div>

      {/* Pagination */}
      {pageCount > 1 && (
        <div className="flex justify-center items-center gap-2">
          <button
            onClick={() => setCurrentPage(prev => Math.max(1, prev - 1))}
            disabled={currentPage === 1}
            className="px-3 py-1 text-xs rounded border border-amber-500/30 text-amber-50 hover:bg-amber-500/10 disabled:opacity-50 disabled:cursor-not-allowed transition-all"
          >
            Previous
          </button>

          <div className={`text-xs font-medium ${isDarkMode ? 'text-gray-400' : 'text-gray-600'}`}>
            Page {currentPage} of {pageCount}
          </div>

          <button
            onClick={() => setCurrentPage(prev => Math.min(pageCount, prev + 1))}
            disabled={currentPage === pageCount}
            className="px-3 py-1 text-xs rounded border border-amber-500/30 text-amber-50 hover:bg-amber-500/10 disabled:opacity-50 disabled:cursor-not-allowed transition-all"
          >
            Next
          </button>
        </div>
      )}
    </div>
  );
};

export default AuditLogViewer;
