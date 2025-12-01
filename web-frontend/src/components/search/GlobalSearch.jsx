import { useState, useCallback, useMemo } from 'react';
import { useApi } from '../hooks/useApi';
import axios from 'axios';
import { formatServiceName } from '../../utils/format';
import {
  MagnifyingGlassIcon,
  FunnelIcon,
  AdjustmentsHorizontalIcon,
  CheckIcon,
  XMarkIcon
} from '@heroicons/react/24/outline';

const GlobalSearch = ({ isDarkMode = true }) => {
  const { callApi } = useApi();
  const [searchQuery, setSearchQuery] = useState('');
  const [searchResults, setSearchResults] = useState([]);
  const [isOpen, setIsOpen] = useState(false);
  const [loading, setLoading] = useState(false);
  const [selectedFilters, setSelectedFilters] = useState({
    type: 'all', // all, appointments, messages, documents, users
    dateFrom: '',
    dateTo: '',
    status: 'all'
  });

  const handleSearch = useCallback(async (query) => {
    if (query.length < 2) {
      setSearchResults([]);
      return;
    }

    setLoading(true);
    try {
      // Perform multiple searches in parallel
      const [appointmentsRes, messagesRes, documentsRes] = await Promise.all([
        callApi((signal) =>
          axios.get(`/api/appointments?search=${query}`, { signal })
        ),
        callApi((signal) =>
          axios.get(`/api/messages?search=${query}`, { signal })
        ),
        callApi((signal) =>
          axios.get(`/api/documents?search=${query}`, { signal })
        )
      ]);

      const results = [];

      if (appointmentsRes.success && appointmentsRes.data.data) {
        results.push(...appointmentsRes.data.data.map(apt => ({
          id: `apt-${apt.id}`,
          type: 'appointment',
          title: `Appointment: ${formatServiceName(apt) || 'N/A'}`,
          subtitle: `Status: ${apt.status}`,
          date: apt.appointment_date,
          object: apt
        })));
      }

      if (messagesRes.success && messagesRes.data.data) {
        results.push(...messagesRes.data.data.map(msg => ({
          id: `msg-${msg.id}`,
          type: 'message',
          title: `Message: ${msg.message.substring(0, 50)}...`,
          subtitle: msg.sender?.first_name || 'Unknown',
          date: msg.created_at,
          object: msg
        })));
      }

      if (documentsRes.success && documentsRes.data.data) {
        results.push(...documentsRes.data.data.map(doc => ({
          id: `doc-${doc.id}`,
          type: 'document',
          title: `Document: ${doc.original_name}`,
          subtitle: doc.document_type,
          date: doc.created_at,
          object: doc
        })));
      }

      // Apply filters
      let filtered = results;
      if (selectedFilters.type !== 'all') {
        filtered = filtered.filter(r => r.type === selectedFilters.type);
      }
      if (selectedFilters.status !== 'all') {
        filtered = filtered.filter(r => r.object.status === selectedFilters.status);
      }
      if (selectedFilters.dateFrom) {
        filtered = filtered.filter(r => new Date(r.date) >= new Date(selectedFilters.dateFrom));
      }
      if (selectedFilters.dateTo) {
        filtered = filtered.filter(r => new Date(r.date) <= new Date(selectedFilters.dateTo));
      }

      setSearchResults(filtered.slice(0, 20)); // Limit to 20 results
    } catch (error) {
      console.error('Search error:', error);
    }
    setLoading(false);
  }, [callApi, selectedFilters]);

  const handleFilterChange = (filterName, value) => {
    const newFilters = { ...selectedFilters, [filterName]: value };
    setSelectedFilters(newFilters);
    if (searchQuery.length >= 2) {
      handleSearch(searchQuery);
    }
  };

  const clearFilters = () => {
    setSelectedFilters({
      type: 'all',
      dateFrom: '',
      dateTo: '',
      status: 'all'
    });
  };

  const handleResultClick = (result) => {
    window.dispatchEvent(new CustomEvent('searchResultSelected', { detail: result }));
    setIsOpen(false);
    setSearchQuery('');
  };

  const activeFilterCount = Object.values(selectedFilters).filter(v => v !== 'all' && v !== '').length;

  return (
    <div className={`relative w-full max-w-md ${isDarkMode ? 'bg-gray-900' : 'bg-white'}`}>
      {/* Search Input */}
      <div className={`flex items-center gap-2 px-3 py-2 border rounded-lg transition-all ${
        isDarkMode
          ? `bg-gray-800 border-amber-500/30 focus-within:border-amber-500/60 ${isOpen ? 'border-amber-500/60' : ''}`
          : `bg-white border-gray-300 focus-within:border-amber-500 ${isOpen ? 'border-amber-500' : ''}`
      }`}>
        <MagnifyingGlassIcon className={`h-4 w-4 ${isDarkMode ? 'text-amber-400' : 'text-amber-600'}`} />
        <input
          type="text"
          value={searchQuery}
          onChange={(e) => {
            setSearchQuery(e.target.value);
            handleSearch(e.target.value);
          }}
          onFocus={() => setIsOpen(true)}
          onBlur={() => setTimeout(() => setIsOpen(false), 200)}
          placeholder="Search appointments, messages, documents..."
          className={`flex-1 bg-transparent outline-none text-xs placeholder-gray-500 ${
            isDarkMode ? 'text-amber-50' : 'text-gray-900'
          }`}
        />
        {activeFilterCount > 0 && (
          <span className="px-1.5 py-0.5 bg-amber-500/20 text-amber-400 rounded text-xs font-medium">
            {activeFilterCount}
          </span>
        )}
      </div>

      {/* Dropdown */}
      {isOpen && (
        <div className={`absolute top-full left-0 right-0 mt-2 rounded-lg shadow-xl border z-50 ${
          isDarkMode
            ? 'bg-gray-900 border-amber-500/30'
            : 'bg-white border-gray-300'
        }`}>
          {/* Filters */}
          <div className={`border-b p-3 space-y-2 ${isDarkMode ? 'border-gray-700 bg-gray-800/50' : 'border-gray-200 bg-gray-50'}`}>
            <div className="flex items-center justify-between mb-2">
              <label className={`text-xs font-semibold flex items-center ${isDarkMode ? 'text-amber-400' : 'text-amber-600'}`}>
                <FunnelIcon className="h-3 w-3 mr-1" />
                Filters
              </label>
              {activeFilterCount > 0 && (
                <button
                  onClick={clearFilters}
                  className={`text-xs px-2 py-0.5 rounded ${
                    isDarkMode
                      ? 'bg-gray-700 text-gray-300 hover:bg-gray-600'
                      : 'bg-gray-200 text-gray-600 hover:bg-gray-300'
                  }`}
                >
                  Clear all
                </button>
              )}
            </div>

            <div className="grid grid-cols-2 gap-2">
              {/* Type Filter */}
              <select
                value={selectedFilters.type}
                onChange={(e) => handleFilterChange('type', e.target.value)}
                className={`px-2 py-1 text-xs rounded border ${
                  isDarkMode
                    ? 'bg-gray-900 border-gray-600 text-amber-50'
                    : 'bg-white border-gray-300 text-gray-900'
                }`}
              >
                <option value="all">All Types</option>
                <option value="appointment">Appointments</option>
                <option value="message">Messages</option>
                <option value="document">Documents</option>
              </select>

              {/* Status Filter */}
              <select
                value={selectedFilters.status}
                onChange={(e) => handleFilterChange('status', e.target.value)}
                className={`px-2 py-1 text-xs rounded border ${
                  isDarkMode
                    ? 'bg-gray-900 border-gray-600 text-amber-50'
                    : 'bg-white border-gray-300 text-gray-900'
                }`}
              >
                <option value="all">All Status</option>
                <option value="pending">Pending</option>
                <option value="approved">Approved</option>
                <option value="completed">Completed</option>
              </select>

              {/* Date From */}
              <input
                type="date"
                value={selectedFilters.dateFrom}
                onChange={(e) => handleFilterChange('dateFrom', e.target.value)}
                className={`px-2 py-1 text-xs rounded border ${
                  isDarkMode
                    ? 'bg-gray-900 border-gray-600 text-amber-50'
                    : 'bg-white border-gray-300 text-gray-900'
                }`}
              />

              {/* Date To */}
              <input
                type="date"
                value={selectedFilters.dateTo}
                onChange={(e) => handleFilterChange('dateTo', e.target.value)}
                className={`px-2 py-1 text-xs rounded border ${
                  isDarkMode
                    ? 'bg-gray-900 border-gray-600 text-amber-50'
                    : 'bg-white border-gray-300 text-gray-900'
                }`}
              />
            </div>
          </div>

          {/* Results */}
          <div className={`max-h-96 overflow-y-auto ${isDarkMode ? 'bg-gray-900' : 'bg-white'}`}>
            {loading ? (
              <div className={`p-3 text-center text-xs ${isDarkMode ? 'text-gray-400' : 'text-gray-600'}`}>
                Searching...
              </div>
            ) : searchQuery.length < 2 ? (
              <div className={`p-3 text-center text-xs ${isDarkMode ? 'text-gray-500' : 'text-gray-500'}`}>
                Type at least 2 characters to search
              </div>
            ) : searchResults.length === 0 ? (
              <div className={`p-3 text-center text-xs ${isDarkMode ? 'text-gray-400' : 'text-gray-600'}`}>
                No results found
              </div>
            ) : (
              <div className={`divide-y ${isDarkMode ? 'divide-gray-700' : 'divide-gray-200'}`}>
                {searchResults.map(result => (
                  <button
                    key={result.id}
                    onClick={() => handleResultClick(result)}
                    className={`w-full text-left p-3 hover:${isDarkMode ? 'bg-gray-800' : 'bg-gray-100'} transition-colors`}
                  >
                    <div className="flex items-start gap-2">
                      <span className={`text-xs font-semibold px-1.5 py-0.5 rounded ${
                        result.type === 'appointment' ? 'bg-blue-500/20 text-blue-400' :
                        result.type === 'message' ? 'bg-green-500/20 text-green-400' :
                        'bg-purple-500/20 text-purple-400'
                      }`}>
                        {result.type}
                      </span>
                    </div>
                    <h4 className={`text-xs font-medium mt-1 ${isDarkMode ? 'text-amber-50' : 'text-gray-900'}`}>
                      {result.title}
                    </h4>
                    <p className={`text-xs ${isDarkMode ? 'text-gray-400' : 'text-gray-600'}`}>
                      {result.subtitle}
                    </p>
                  </button>
                ))}
              </div>
            )}
          </div>
        </div>
      )}
    </div>
  );
};

export default GlobalSearch;
