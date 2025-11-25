import React, { useState, useEffect } from 'react';
import axios from 'axios';
import {
  DocumentChartBarIcon,
  ArrowPathIcon,
  ExclamationTriangleIcon,
  CheckCircleIcon
} from '@heroicons/react/24/outline';

const DocumentManagement = ({ isDarkMode = true }) => {
  const [serviceStats, setServiceStats] = useState([]);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');
  const [totalAppointments, setTotalAppointments] = useState(0);

  useEffect(() => {
    loadStats();
  }, []);

  const loadStats = async () => {
    try {
      setLoading(true);
      setError('');
      
      const [statsRes, appointmentRes] = await Promise.all([
        axios.get('/api/admin/services/stats'),
        axios.get('/api/appointments/stats')
      ]);

      if (statsRes.data && statsRes.data.data) {
        setServiceStats(statsRes.data.data);
      }

      if (appointmentRes.data) {
        setTotalAppointments(appointmentRes.data.total || 0);
      }
    } catch (err) {
      setError(err.response?.data?.message || 'Failed to load document statistics');
      console.error('Error loading stats:', err);
    } finally {
      setLoading(false);
    }
  };

  const getTotalUsage = () => {
    return serviceStats.reduce((sum, stat) => sum + (stat.count || 0), 0);
  };

  const getPercentage = (count) => {
    const total = getTotalUsage();
    if (total === 0) return 0;
    return Math.round((count / total) * 100);
  };

  const sortedStats = [...serviceStats].sort((a, b) => (b.count || 0) - (a.count || 0));

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex justify-between items-center">
        <div>
          <h2 className={`text-lg font-bold ${isDarkMode ? 'text-amber-50' : 'text-amber-900'} transition-colors duration-300`}>
            Document & Service Usage Report
          </h2>
          <p className={`${isDarkMode ? 'text-amber-400/70' : 'text-amber-700/70'} mt-1 text-sm transition-colors duration-300`}>
            View how often each service type has been used
          </p>
        </div>
        <button
          onClick={loadStats}
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

      {/* Summary Cards */}
      <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div className={`${isDarkMode ? 'bg-gray-900 border-amber-500/20 hover:border-amber-500/40' : 'bg-white border-amber-300/40 hover:border-amber-400'} border rounded-lg shadow p-4 transition-all duration-300`}>
          <div className="flex items-center justify-between">
            <div>
              <p className={`text-xs font-medium ${isDarkMode ? 'text-gray-400' : 'text-gray-600'} transition-colors duration-300`}>
                Total Appointments
              </p>
              <p className={`text-2xl font-bold ${isDarkMode ? 'text-amber-50' : 'text-amber-900'} mt-1 transition-colors duration-300`}>
                {totalAppointments}
              </p>
            </div>
            <div className={`p-3 ${isDarkMode ? 'bg-amber-500/20' : 'bg-amber-100'} rounded-lg transition-colors duration-300`}>
              <DocumentChartBarIcon className={`h-6 w-6 ${isDarkMode ? 'text-amber-400' : 'text-amber-600'}`} />
            </div>
          </div>
        </div>

        <div className={`${isDarkMode ? 'bg-gray-900 border-amber-500/20 hover:border-amber-500/40' : 'bg-white border-amber-300/40 hover:border-amber-400'} border rounded-lg shadow p-4 transition-all duration-300`}>
          <div className="flex items-center justify-between">
            <div>
              <p className={`text-xs font-medium ${isDarkMode ? 'text-gray-400' : 'text-gray-600'} transition-colors duration-300`}>
                Service Types Used
              </p>
              <p className={`text-2xl font-bold ${isDarkMode ? 'text-amber-50' : 'text-amber-900'} mt-1 transition-colors duration-300`}>
                {serviceStats.filter(s => s.count > 0).length}
              </p>
            </div>
            <div className={`p-3 ${isDarkMode ? 'bg-blue-500/20' : 'bg-blue-100'} rounded-lg transition-colors duration-300`}>
              <CheckCircleIcon className={`h-6 w-6 ${isDarkMode ? 'text-blue-400' : 'text-blue-600'}`} />
            </div>
          </div>
        </div>

        <div className={`${isDarkMode ? 'bg-gray-900 border-amber-500/20 hover:border-amber-500/40' : 'bg-white border-amber-300/40 hover:border-amber-400'} border rounded-lg shadow p-4 transition-all duration-300`}>
          <div className="flex items-center justify-between">
            <div>
              <p className={`text-xs font-medium ${isDarkMode ? 'text-gray-400' : 'text-gray-600'} transition-colors duration-300`}>
                Total Services
              </p>
              <p className={`text-2xl font-bold ${isDarkMode ? 'text-amber-50' : 'text-amber-900'} mt-1 transition-colors duration-300`}>
                {serviceStats.length}
              </p>
            </div>
            <div className={`p-3 ${isDarkMode ? 'bg-green-500/20' : 'bg-green-100'} rounded-lg transition-colors duration-300`}>
              <DocumentChartBarIcon className={`h-6 w-6 ${isDarkMode ? 'text-green-400' : 'text-green-600'}`} />
            </div>
          </div>
        </div>
      </div>

      {/* Service Usage Table */}
      <div className={`${isDarkMode ? 'bg-gray-900 border-amber-500/20' : 'bg-white border-amber-300/40'} border rounded-lg shadow overflow-hidden transition-colors duration-300`}>
        {loading ? (
          <div className="flex items-center justify-center py-12">
            <div className="w-6 h-6 border-2 border-amber-500 border-t-transparent rounded-full animate-spin"></div>
          </div>
        ) : serviceStats.length === 0 ? (
          <div className="text-center py-12">
            <DocumentChartBarIcon className={`mx-auto h-12 w-12 ${isDarkMode ? 'text-gray-600' : 'text-gray-400'}`} />
            <h3 className={`mt-4 text-sm font-medium ${isDarkMode ? 'text-amber-50' : 'text-amber-900'}`}>
              No service data yet
            </h3>
            <p className={`mt-2 ${isDarkMode ? 'text-amber-400/70' : 'text-amber-700/70'} text-xs`}>
              Services will appear here as appointments are created
            </p>
          </div>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead>
                <tr className={`${isDarkMode ? 'bg-gray-800' : 'bg-gray-50'} transition-colors duration-300 border-b ${isDarkMode ? 'border-gray-700' : 'border-gray-200'}`}>
                  <th className={`px-4 py-3 text-left font-semibold ${isDarkMode ? 'text-amber-400' : 'text-amber-600'}`}>
                    Service Type
                  </th>
                  <th className={`px-4 py-3 text-right font-semibold ${isDarkMode ? 'text-amber-400' : 'text-amber-600'}`}>
                    Count
                  </th>
                  <th className={`px-4 py-3 text-right font-semibold ${isDarkMode ? 'text-amber-400' : 'text-amber-600'}`}>
                    Percentage
                  </th>
                  <th className={`px-4 py-3 text-left font-semibold ${isDarkMode ? 'text-amber-400' : 'text-amber-600'}`}>
                    Usage Distribution
                  </th>
                </tr>
              </thead>
              <tbody className={`divide-y ${isDarkMode ? 'divide-gray-700' : 'divide-gray-200'} transition-colors duration-300`}>
                {sortedStats.map((stat) => {
                  const percentage = getPercentage(stat.count || 0);
                  return (
                    <tr 
                      key={stat.id} 
                      className={`${isDarkMode ? 'hover:bg-gray-800' : 'hover:bg-gray-50'} transition-colors duration-200`}
                    >
                      <td className={`px-4 py-3 font-medium ${isDarkMode ? 'text-amber-50' : 'text-amber-900'}`}>
                        <div>
                          <p>{stat.name}</p>
                          {stat.description && (
                            <p className={`text-xs ${isDarkMode ? 'text-gray-400' : 'text-gray-600'}`}>
                              {stat.description}
                            </p>
                          )}
                        </div>
                      </td>
                      <td className={`px-4 py-3 text-right ${isDarkMode ? 'text-gray-300' : 'text-gray-800'}`}>
                        <span className={`inline-block px-2 py-1 rounded ${isDarkMode ? 'bg-amber-500/20 text-amber-300' : 'bg-amber-100 text-amber-800'} font-semibold transition-colors duration-300`}>
                          {stat.count || 0}
                        </span>
                      </td>
                      <td className={`px-4 py-3 text-right ${isDarkMode ? 'text-gray-300' : 'text-gray-800'}`}>
                        <span className="font-semibold">{percentage}%</span>
                      </td>
                      <td className="px-4 py-3">
                        <div className="flex items-center space-x-2 max-w-xs">
                          <div className={`flex-1 h-2 ${isDarkMode ? 'bg-gray-700' : 'bg-gray-200'} rounded-full overflow-hidden transition-colors duration-300`}>
                            <div 
                              className="h-full bg-gradient-to-r from-amber-500 to-amber-600 transition-all duration-500"
                              style={{ width: `${percentage}%` }}
                            />
                          </div>
                          <span className={`text-xs ${isDarkMode ? 'text-gray-400' : 'text-gray-600'} w-8 text-right`}>
                            {percentage}%
                          </span>
                        </div>
                      </td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </div>
        )}

        {/* Summary Footer */}
        {!loading && serviceStats.length > 0 && (
          <div className={`border-t ${isDarkMode ? 'border-gray-700 bg-gray-800/50' : 'border-gray-200 bg-gray-50'} px-4 py-3 transition-colors duration-300`}>
            <p className={`text-xs ${isDarkMode ? 'text-gray-400' : 'text-gray-600'}`}>
              Total Service Usage: <span className="font-semibold">{getTotalUsage()}</span> appointments across <span className="font-semibold">{serviceStats.length}</span> service types
            </p>
          </div>
        )}
      </div>
    </div>
  );
};

export default DocumentManagement;
