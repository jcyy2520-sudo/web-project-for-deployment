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
  EyeIcon
} from '@heroicons/react/24/outline';
import ActionLogDetailModal from './modals/ActionLogDetailModal';

const ActionLogViewer = ({ isDarkMode = true }) => {
  const [logs, setLogs] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [currentPage, setCurrentPage] = useState(1);
  const [totalPages, setTotalPages] = useState(1);
  const [searchTerm, setSearchTerm] = useState('');
  const [actionFilter, setActionFilter] = useState('');
  const [selectedLog, setSelectedLog] = useState(null);
  const [showDetailModal, setShowDetailModal] = useState(false);

  useEffect(() => {
    loadLogs();
  }, [currentPage]);

  const loadLogs = async () => {
    try {
      setLoading(true);
      setError('');
      
      const response = await axios.get('/api/action-logs/my/logs', {
        params: {
          page: currentPage,
          per_page: 10,
          action: actionFilter,
          search: searchTerm
        }
      });

      if (response.data && response.data.data) {
        setLogs(response.data.data);
        setTotalPages(response.data.pagination?.last_page || 1);
      }
    } catch (err) {
      setError(err.response?.data?.message || 'Failed to load action logs');
      console.error('Error loading action logs:', err);
    } finally {
      setLoading(false);
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
            Action Log
          </h2>
          <p className={`${isDarkMode ? 'text-amber-400/70' : 'text-amber-700/70'} mt-1 text-sm transition-colors duration-300`}>
            View your recent activities and changes
          </p>
        </div>
        <button
          onClick={loadLogs}
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

      {/* Filter Bar */}
      <div className={`${isDarkMode ? 'bg-gray-900 border-amber-500/20' : 'bg-white border-amber-300/40'} border rounded-lg shadow p-3 sm:p-4 transition-colors duration-300`}>
        <div className="space-y-3">
          <div className="flex items-center space-x-2 mb-3">
            <MagnifyingGlassIcon className={`h-4 w-4 flex-shrink-0 ${isDarkMode ? 'text-amber-400' : 'text-amber-600'}`} />
            <input
              type="text"
              placeholder="Search activities..."
              value={searchTerm}
              onChange={handleSearch}
              className={`flex-1 pl-2 pr-3 py-1.5 ${isDarkMode ? 'bg-gray-800 border-gray-600 text-white placeholder-gray-400' : 'bg-gray-50 border-gray-300 text-gray-900 placeholder-gray-500'} border rounded-lg focus:outline-none focus:ring-1 focus:ring-amber-500 transition-all duration-200 text-xs sm:text-sm`}
            />
          </div>
          
          <div className="flex flex-wrap gap-1 sm:gap-2">
            {['create', 'update', 'delete', 'restore', 'approve'].map(action => (
              <button
                key={action}
                onClick={() => handleActionFilter(action)}
                className={`px-2 sm:px-3 py-1 rounded text-xs font-medium transition-all duration-200 whitespace-nowrap ${
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
              No activities yet
            </h3>
            <p className={`mt-2 ${isDarkMode ? 'text-amber-400/70' : 'text-amber-700/70'} text-xs`}>
              Your activities will appear here
            </p>
          </div>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full text-xs sm:text-sm">
              <thead>
                <tr className={`${isDarkMode ? 'bg-gray-800' : 'bg-gray-50'} transition-colors duration-300 border-b ${isDarkMode ? 'border-gray-700' : 'border-gray-200'}`}>
                  <th className={`px-2 sm:px-4 py-2 sm:py-3 text-left font-semibold ${isDarkMode ? 'text-amber-400' : 'text-amber-600'}`}>
                    Time
                  </th>
                  <th className={`px-2 sm:px-4 py-2 sm:py-3 text-left font-semibold ${isDarkMode ? 'text-amber-400' : 'text-amber-600'}`}>
                    Action
                  </th>
                  <th className={`px-2 sm:px-4 py-2 sm:py-3 text-left font-semibold hidden sm:table-cell ${isDarkMode ? 'text-amber-400' : 'text-amber-600'}`}>
                    Description
                  </th>
                  <th className={`px-2 sm:px-4 py-2 sm:py-3 text-left font-semibold hidden md:table-cell ${isDarkMode ? 'text-amber-400' : 'text-amber-600'}`}>
                    Type
                  </th>
                </tr>
              </thead>
              <tbody className={`divide-y ${isDarkMode ? 'divide-gray-700' : 'divide-gray-200'} transition-colors duration-300`}>
                {logs.map((log) => (
                  <tr 
                    key={log.id} 
                    className={`${isDarkMode ? 'hover:bg-gray-800' : 'hover:bg-gray-50'} transition-colors duration-200`}
                  >
                    <td className={`px-2 sm:px-4 py-2 sm:py-3 text-xs ${isDarkMode ? 'text-gray-400' : 'text-gray-600'} whitespace-nowrap`}>
                      {new Date(log.created_at).toLocaleDateString([], { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' })}
                    </td>
                    <td className="px-2 sm:px-4 py-2 sm:py-3">
                      <div className="flex items-center space-x-1 sm:space-x-2">
                        <span className={getActionColor(log.action)}>
                          {getActionIcon(log.action)}
                        </span>
                        <span className={`font-medium text-xs ${isDarkMode ? 'text-amber-50' : 'text-amber-900'} whitespace-nowrap`}>
                          {log.action.charAt(0).toUpperCase() + log.action.slice(1)}
                        </span>
                      </div>
                    </td>
                    <td className={`px-2 sm:px-4 py-2 sm:py-3 text-xs ${isDarkMode ? 'text-gray-300' : 'text-gray-800'} hidden sm:table-cell max-w-xs truncate`}>
                      {log.description}
                    </td>
                    <td className={`px-2 sm:px-4 py-2 sm:py-3 text-xs ${isDarkMode ? 'text-gray-400' : 'text-gray-600'} hidden md:table-cell`}>
                      {log.model_type || '-'}
                    </td>
                    <td className="px-2 sm:px-4 py-2 sm:py-3 text-center">
                      <button
                        onClick={() => handleViewDetails(log)}
                        className={`p-1 rounded transition-colors ${isDarkMode ? 'text-amber-400 hover:text-amber-300 hover:bg-amber-500/10' : 'text-amber-600 hover:text-amber-700 hover:bg-amber-50'}`}
                        title="View details"
                      >
                        <EyeIcon className="h-3.5 w-3.5" />
                      </button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}

        {/* Pagination */}
        {!loading && logs.length > 0 && totalPages > 1 && (
          <div className={`flex justify-between items-center px-2 sm:px-4 py-2 sm:py-3 border-t gap-2 ${isDarkMode ? 'border-gray-700 bg-gray-800/50' : 'border-gray-200 bg-gray-50'} transition-colors duration-300 flex-wrap`}>
            <button
              onClick={() => setCurrentPage(Math.max(1, currentPage - 1))}
              disabled={currentPage === 1}
              className={`px-2 sm:px-3 py-1 sm:py-1.5 text-xs font-medium rounded ${isDarkMode ? 'border-gray-600 text-gray-300 hover:border-amber-500/40 disabled:opacity-50' : 'border-gray-300 text-gray-700 hover:bg-gray-100 disabled:opacity-50'} border transition-all duration-200`}
            >
              Prev
            </button>
            <span className={`text-xs ${isDarkMode ? 'text-gray-400' : 'text-gray-600'} whitespace-nowrap`}>
              {currentPage}/{totalPages}
            </span>
            <button
              onClick={() => setCurrentPage(Math.min(totalPages, currentPage + 1))}
              disabled={currentPage === totalPages}
              className={`px-2 sm:px-3 py-1 sm:py-1.5 text-xs font-medium rounded ${isDarkMode ? 'border-gray-600 text-gray-300 hover:border-amber-500/40 disabled:opacity-50' : 'border-gray-300 text-gray-700 hover:bg-gray-100 disabled:opacity-50'} border transition-all duration-200`}
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

export default ActionLogViewer;
