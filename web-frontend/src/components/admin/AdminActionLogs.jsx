import React, { useState, useEffect } from 'react';
import axios from 'axios';
import {
  ClockIcon,
  CheckCircleIcon,
  PencilIcon,
  TrashIcon,
  PlusIcon,
  ExclamationTriangleIcon,
  ArrowPathIcon,
  MagnifyingGlassIcon,
  CalendarIcon,
  UserIcon,
  EyeIcon
} from '@heroicons/react/24/outline';
import ActionLogDetailModal from '../modals/ActionLogDetailModal';

const AdminActionLogs = ({ isDarkMode = true }) => {
  const [logs, setLogs] = useState([]);
  const [users, setUsers] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [currentPage, setCurrentPage] = useState(1);
  const [totalPages, setTotalPages] = useState(1);
  const [searchTerm, setSearchTerm] = useState('');
  const [selectedUser, setSelectedUser] = useState('');
  const [actionFilter, setActionFilter] = useState('');
  const [stats, setStats] = useState(null);
  const [selectedLog, setSelectedLog] = useState(null);
  const [showDetailModal, setShowDetailModal] = useState(false);

  useEffect(() => {
    loadLogs();
    loadUsers();
    loadStats();
  }, [currentPage, selectedUser, actionFilter]);

  const loadLogs = async () => {
    try {
      setLoading(true);
      setError('');
      
      const response = await axios.get('/api/action-logs/', {
        params: {
          page: currentPage,
          per_page: 10,
          user_id: selectedUser || undefined,
          action: actionFilter || undefined,
          search: searchTerm
        }
      });

      if (response.data && response.data.data) {
        setLogs(response.data.data);
        if (response.data.pagination) {
          setTotalPages(response.data.pagination.last_page || 1);
        }
      }
    } catch (err) {
      setError(err.response?.data?.message || 'Failed to load action logs');
      console.error('Error loading action logs:', err);
    } finally {
      setLoading(false);
    }
  };

  const loadUsers = async () => {
    try {
      const response = await axios.get('/api/admin/users');
      if (response.data && response.data.data) {
        setUsers(response.data.data);
      }
    } catch (err) {
      console.error('Error loading users:', err);
    }
  };

  const loadStats = async () => {
    try {
      const response = await axios.get('/api/action-logs/stats');
      if (response.data && response.data.data) {
        setStats(response.data.data);
      }
    } catch (err) {
      console.error('Error loading stats:', err);
    }
  };

  const getActionIcon = (action) => {
    const iconProps = { className: 'h-3 w-3' };
    switch(action) {
      case 'create': return <PlusIcon {...iconProps} />;
      case 'update': return <PencilIcon {...iconProps} />;
      case 'delete': return <TrashIcon {...iconProps} />;
      case 'restore': return <ArrowPathIcon {...iconProps} />;
      case 'approve': 
      case 'complete': return <CheckCircleIcon {...iconProps} />;
      default: return <ClockIcon {...iconProps} />;
    }
  };

  const getActionColor = (action) => {
    if (isDarkMode) {
      switch(action) {
        case 'create': return 'text-green-400';
        case 'update': return 'text-blue-400';
        case 'delete': return 'text-red-400';
        case 'restore': return 'text-amber-400';
        case 'approve':
        case 'complete': return 'text-green-400';
        default: return 'text-gray-400';
      }
    } else {
      switch(action) {
        case 'create': return 'text-green-600';
        case 'update': return 'text-blue-600';
        case 'delete': return 'text-red-600';
        case 'restore': return 'text-amber-600';
        case 'approve':
        case 'complete': return 'text-green-600';
        default: return 'text-gray-600';
      }
    }
  };

  const handleSearch = (e) => {
    const value = e.target.value;
    setSearchTerm(value);
    setCurrentPage(1);
  };

  const handleActionFilter = (action) => {
    setActionFilter(action === actionFilter ? '' : action);
    setCurrentPage(1);
  };

  const handleViewDetails = (log) => {
    setSelectedLog(log);
    setShowDetailModal(true);
  };

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex justify-between items-center">
        <div>
          <h2 className={`text-lg font-bold ${isDarkMode ? 'text-amber-50' : 'text-amber-900'} transition-colors duration-300`}>
            Admin Action Logs
          </h2>
          <p className={`${isDarkMode ? 'text-amber-400/70' : 'text-amber-700/70'} mt-1 text-sm transition-colors duration-300`}>
            Track all admin and staff activities in the system
          </p>
        </div>
        <button
          onClick={() => {
            loadLogs();
            loadStats();
          }}
          className={`px-3 py-1.5 border ${isDarkMode ? 'border-amber-500/30 text-amber-50 hover:bg-amber-500/10' : 'border-amber-300 text-amber-900 hover:bg-amber-100'} rounded transition-all duration-200 font-medium text-sm flex items-center`}
        >
          <ArrowPathIcon className="h-3 w-3 mr-1" />
          Refresh
        </button>
      </div>

      {/* Error Message */}
      {error && (
        <div className={`${isDarkMode ? 'bg-red-500/10 border-red-500/30 text-red-400' : 'bg-red-100 border-red-300 text-red-700'} border rounded-lg p-3 transition-colors duration-300`}>
          <div className="flex items-center">
            <ExclamationTriangleIcon className="h-4 w-4 mr-2" />
            <p className="text-sm">{error}</p>
          </div>
        </div>
      )}

      {/* Stats Cards */}
      {stats && (
        <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
          <div className={`${isDarkMode ? 'bg-gray-900 border-amber-500/20' : 'bg-white border-amber-300/40'} border rounded-lg shadow p-4 transition-colors duration-300`}>
            <p className={`text-xs font-medium ${isDarkMode ? 'text-gray-400' : 'text-gray-600'} transition-colors duration-300`}>
              Total Actions
            </p>
            <p className={`text-2xl font-bold ${isDarkMode ? 'text-amber-50' : 'text-amber-900'} mt-1 transition-colors duration-300`}>
              {stats.total_actions || 0}
            </p>
          </div>

          <div className={`${isDarkMode ? 'bg-gray-900 border-amber-500/20' : 'bg-white border-amber-300/40'} border rounded-lg shadow p-4 transition-colors duration-300`}>
            <p className={`text-xs font-medium ${isDarkMode ? 'text-gray-400' : 'text-gray-600'} transition-colors duration-300`}>
              Today's Actions
            </p>
            <p className={`text-2xl font-bold ${isDarkMode ? 'text-amber-50' : 'text-amber-900'} mt-1 transition-colors duration-300`}>
              {stats.today_actions || 0}
            </p>
          </div>

          <div className={`${isDarkMode ? 'bg-gray-900 border-amber-500/20' : 'bg-white border-amber-300/40'} border rounded-lg shadow p-4 transition-colors duration-300`}>
            <p className={`text-xs font-medium ${isDarkMode ? 'text-gray-400' : 'text-gray-600'} transition-colors duration-300`}>
              This Month
            </p>
            <p className={`text-2xl font-bold ${isDarkMode ? 'text-amber-50' : 'text-amber-900'} mt-1 transition-colors duration-300`}>
              {stats.this_month_actions || 0}
            </p>
          </div>
        </div>
      )}

      {/* Filter Bar */}
      <div className={`${isDarkMode ? 'bg-gray-900 border-amber-500/20' : 'bg-white border-amber-300/40'} border rounded-lg shadow p-4 transition-colors duration-300`}>
        <div className="space-y-3">
          <div className="flex items-center space-x-2">
            <MagnifyingGlassIcon className={`h-4 w-4 ${isDarkMode ? 'text-amber-400' : 'text-amber-600'}`} />
            <input
              type="text"
              placeholder="Search activities..."
              value={searchTerm}
              onChange={handleSearch}
              className={`flex-1 pl-3 pr-3 py-1.5 ${isDarkMode ? 'bg-gray-800 border-gray-600 text-white placeholder-gray-400' : 'bg-gray-50 border-gray-300 text-gray-900 placeholder-gray-500'} border rounded-lg focus:outline-none focus:ring-1 focus:ring-amber-500 transition-all duration-200 text-sm`}
            />
          </div>
          
          <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
            <div>
              <label className={`block text-xs font-medium ${isDarkMode ? 'text-gray-400' : 'text-gray-600'} mb-2 transition-colors duration-300`}>
                Filter by User
              </label>
              <select
                value={selectedUser}
                onChange={(e) => {
                  setSelectedUser(e.target.value);
                  setCurrentPage(1);
                }}
                className={`w-full px-3 py-1.5 ${isDarkMode ? 'bg-gray-800 border-gray-600 text-white' : 'bg-gray-50 border-gray-300 text-gray-900'} border rounded-lg focus:outline-none focus:ring-1 focus:ring-amber-500 transition-all duration-200 text-sm`}
              >
                <option value="">All Users</option>
                {users.map(user => (
                  <option key={user.id} value={user.id}>
                    {user.first_name} {user.last_name} ({user.role})
                  </option>
                ))}
              </select>
            </div>

            <div>
              <label className={`block text-xs font-medium ${isDarkMode ? 'text-gray-400' : 'text-gray-600'} mb-2 transition-colors duration-300`}>
                Filter by Action Type
              </label>
              <div className="flex flex-wrap gap-1">
                {['create', 'update', 'delete', 'restore', 'approve'].map(action => (
                  <button
                    key={action}
                    onClick={() => handleActionFilter(action)}
                    className={`px-2 py-1 rounded text-xs font-medium transition-all duration-200 ${
                      actionFilter === action
                        ? isDarkMode
                          ? 'bg-amber-500/30 border border-amber-500/60 text-amber-50'
                          : 'bg-amber-200 border border-amber-400 text-amber-900'
                        : isDarkMode
                        ? 'bg-gray-800 border border-gray-600 text-gray-300 hover:border-amber-500/40'
                        : 'bg-gray-100 border border-gray-300 text-gray-700 hover:bg-gray-200'
                    }`}
                  >
                    {action.charAt(0).toUpperCase() + action.slice(1)}
                  </button>
                ))}
              </div>
            </div>
          </div>
        </div>
      </div>

      {/* Logs Table */}
      <div className={`${isDarkMode ? 'bg-gray-900 border-amber-500/20' : 'bg-white border-amber-300/40'} border rounded-lg shadow overflow-hidden transition-colors duration-300`}>
        {loading ? (
          <div className="flex items-center justify-center py-8">
            <div className="w-6 h-6 border-2 border-amber-500 border-t-transparent rounded-full animate-spin"></div>
          </div>
        ) : logs.length === 0 ? (
          <div className="text-center py-12">
            <CalendarIcon className={`mx-auto h-12 w-12 ${isDarkMode ? 'text-gray-600' : 'text-gray-400'}`} />
            <h3 className={`mt-4 text-sm font-medium ${isDarkMode ? 'text-amber-50' : 'text-amber-900'}`}>
              No activities found
            </h3>
            <p className={`mt-2 ${isDarkMode ? 'text-amber-400/70' : 'text-amber-700/70'} text-xs`}>
              Activities will appear here as admins perform actions
            </p>
          </div>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead>
                <tr className={`${isDarkMode ? 'bg-gray-800' : 'bg-gray-50'} transition-colors duration-300 border-b ${isDarkMode ? 'border-gray-700' : 'border-gray-200'}`}>
                  <th className={`px-4 py-3 text-left font-semibold ${isDarkMode ? 'text-amber-400' : 'text-amber-600'}`}>
                    Time
                  </th>
                  <th className={`px-4 py-3 text-left font-semibold ${isDarkMode ? 'text-amber-400' : 'text-amber-600'}`}>
                    User
                  </th>
                  <th className={`px-4 py-3 text-left font-semibold ${isDarkMode ? 'text-amber-400' : 'text-amber-600'}`}>
                    Action
                  </th>
                  <th className={`px-4 py-3 text-left font-semibold ${isDarkMode ? 'text-amber-400' : 'text-amber-600'}`}>
                    Description
                  </th>
                  <th className={`px-4 py-3 text-left font-semibold ${isDarkMode ? 'text-amber-400' : 'text-amber-600'}`}>
                    Type
                  </th>
                </tr>
              </thead>
              <tbody className={`divide-y ${isDarkMode ? 'divide-gray-700' : 'divide-gray-200'} transition-colors duration-300`}>
                {logs.map((log) => (
                  <tr 
                    key={log.id} 
                    onClick={() => handleViewDetails(log)}
                    className={`${isDarkMode ? 'hover:bg-gray-700 cursor-pointer' : 'hover:bg-gray-100 cursor-pointer'} transition-colors duration-200`}
                  >
                    <td className={`px-4 py-3 text-xs ${isDarkMode ? 'text-gray-400' : 'text-gray-600'}`}>
                      {new Date(log.created_at).toLocaleString()}
                    </td>
                    <td className={`px-4 py-3 text-xs ${isDarkMode ? 'text-gray-300' : 'text-gray-800'}`}>
                      <div className="flex items-center space-x-1">
                        <UserIcon className="h-3 w-3 opacity-70" />
                        <span>
                          {log.user?.first_name} {log.user?.last_name}
                        </span>
                      </div>
                      {log.user?.role && (
                        <span className={`text-xs ${isDarkMode ? 'text-gray-500' : 'text-gray-500'}`}>
                          ({log.user.role})
                        </span>
                      )}
                    </td>
                    <td className="px-4 py-3">
                      <div className="flex items-center space-x-2">
                        <span className={getActionColor(log.action)}>
                          {getActionIcon(log.action)}
                        </span>
                        <span className={`font-medium text-xs ${isDarkMode ? 'text-amber-50' : 'text-amber-900'}`}>
                          {log.action.charAt(0).toUpperCase() + log.action.slice(1)}
                        </span>
                      </div>
                    </td>
                    <td className={`px-4 py-3 text-xs ${isDarkMode ? 'text-gray-300' : 'text-gray-800'}`}>
                      <div className="flex items-center justify-between">
                        <span className="line-clamp-2">{log.description}</span>
                        <EyeIcon className={`h-4 w-4 ml-2 flex-shrink-0 ${isDarkMode ? 'text-gray-500' : 'text-gray-400'}`} />
                      </div>
                    </td>
                    <td className={`px-4 py-3 text-xs ${isDarkMode ? 'text-gray-400' : 'text-gray-600'}`}>
                      {log.model_type || '-'}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}

        {/* Pagination */}
        {!loading && logs.length > 0 && totalPages > 1 && (
          <div className={`flex justify-between items-center px-4 py-3 border-t ${isDarkMode ? 'border-gray-700 bg-gray-800/50' : 'border-gray-200 bg-gray-50'} transition-colors duration-300`}>
            <button
              onClick={() => setCurrentPage(Math.max(1, currentPage - 1))}
              disabled={currentPage === 1}
              className={`px-3 py-1.5 text-xs font-medium rounded ${isDarkMode ? 'border-gray-600 text-gray-300 hover:border-amber-500/40 disabled:opacity-50' : 'border-gray-300 text-gray-700 hover:bg-gray-100 disabled:opacity-50'} border transition-all duration-200`}
            >
              Previous
            </button>
            <span className={`text-xs ${isDarkMode ? 'text-gray-400' : 'text-gray-600'}`}>
              Page {currentPage} of {totalPages}
            </span>
            <button
              onClick={() => setCurrentPage(Math.min(totalPages, currentPage + 1))}
              disabled={currentPage === totalPages}
              className={`px-3 py-1.5 text-xs font-medium rounded ${isDarkMode ? 'border-gray-600 text-gray-300 hover:border-amber-500/40 disabled:opacity-50' : 'border-gray-300 text-gray-700 hover:bg-gray-100 disabled:opacity-50'} border transition-all duration-200`}
            >
              Next
            </button>
          </div>
        )}
      </div>

      {/* Action Log Detail Modal */}
      <ActionLogDetailModal
        isOpen={showDetailModal}
        onClose={() => {
          setShowDetailModal(false);
          setSelectedLog(null);
        }}
        log={selectedLog}
        isDarkMode={isDarkMode}
      />
    </div>
  );
};

export default AdminActionLogs;
