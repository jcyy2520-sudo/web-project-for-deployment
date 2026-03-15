import { useState, useEffect } from 'react';
import axios from 'axios';
import { useAuth } from '../context/AuthContext';
import { useTheme } from '../context/ThemeContext';
import { useApi } from '../hooks/useApi';
import LoadingSpinner from '../components/LoadingSpinner';
import {
  PlusIcon,
  PencilIcon,
  TrashIcon,
  CalendarIcon,
  CalendarDaysIcon,
  XMarkIcon,
  ArrowPathIcon,
  FunnelIcon,
  ClockIcon,
  ExclamationTriangleIcon,
} from '@heroicons/react/24/outline';

const CalendarManagement = () => {
  const { user } = useAuth();
  const { isDarkMode } = useTheme();
  const { callApi, loading } = useApi();
  const [events, setEvents] = useState([]);
  const [isCreateModalOpen, setIsCreateModalOpen] = useState(false);
  const [isEditModalOpen, setIsEditModalOpen] = useState(false);
  const [selectedEvent, setSelectedEvent] = useState(null);
  const [dateRange, setDateRange] = useState({
    start_date: new Date().toISOString().split('T')[0],
    end_date: new Date(Date.now() + 30 * 24 * 60 * 60 * 1000).toISOString().split('T')[0],
  });

  const [formData, setFormData] = useState({
    event_date: '',
    type: 'available',
    reason: '',
    start_time: '',
    end_time: '',
    is_recurring: false,
    recurring_days: [],
  });

  useEffect(() => {
    loadEvents();
  }, [dateRange]);

  const loadEvents = async () => {
    const result = await callApi((signal) =>
      axios.get('/api/calendar', {
        params: { start_date: dateRange.start_date, end_date: dateRange.end_date },
        signal,
      })
    );
    if (result.success) {
      setEvents(result.data);
    }
  };

  const handleCreateEvent = async (e) => {
    e.preventDefault();
    const result = await callApi((signal) => axios.post('/api/calendar', formData, { signal }));
    if (result.success) {
      setIsCreateModalOpen(false);
      resetForm();
      loadEvents();
      window.showToast?.('Success', 'Calendar event created successfully!', 'success');
    }
  };

  const handleUpdateEvent = async (e) => {
    e.preventDefault();
    const result = await callApi((signal) =>
      axios.put(`/api/calendar/${selectedEvent.id}`, formData, { signal })
    );
    if (result.success) {
      setIsEditModalOpen(false);
      resetForm();
      loadEvents();
      window.showToast?.('Success', 'Calendar event updated successfully!', 'success');
    }
  };

  const handleDeleteEvent = async (eventId) => {
    if (!confirm('Are you sure you want to delete this calendar event?')) return;
    const result = await callApi((signal) => axios.delete(`/api/calendar/${eventId}`, { signal }));
    if (result.success) {
      loadEvents();
      window.showToast?.('Success', 'Calendar event deleted successfully!', 'success');
    }
  };

  const resetForm = () => {
    setFormData({
      event_date: '',
      type: 'available',
      reason: '',
      start_time: '',
      end_time: '',
      is_recurring: false,
      recurring_days: [],
    });
    setSelectedEvent(null);
  };

  const openEditModal = (event) => {
    setSelectedEvent(event);
    setFormData({
      event_date: event.event_date,
      type: event.type,
      reason: event.reason || '',
      start_time: event.start_time || '',
      end_time: event.end_time || '',
      is_recurring: event.is_recurring,
      recurring_days: event.recurring_days || [],
    });
    setIsEditModalOpen(true);
  };

  const getEventConfig = (type) => {
    switch (type) {
      case 'available':
        return {
          bg: isDarkMode ? 'bg-emerald-500/10' : 'bg-emerald-50',
          text: isDarkMode ? 'text-emerald-400' : 'text-emerald-700',
          border: isDarkMode ? 'border-emerald-500/30' : 'border-emerald-200',
          badge: isDarkMode ? 'bg-emerald-500/20 text-emerald-300 border-emerald-500/30' : 'bg-emerald-100 text-emerald-700 border-emerald-200',
          icon: '',
        };
      case 'unavailable':
        return {
          bg: isDarkMode ? 'bg-red-500/10' : 'bg-red-50',
          text: isDarkMode ? 'text-red-400' : 'text-red-700',
          border: isDarkMode ? 'border-red-500/30' : 'border-red-200',
          badge: isDarkMode ? 'bg-red-500/20 text-red-300 border-red-500/30' : 'bg-red-100 text-red-700 border-red-200',
          icon: '',
        };
      case 'holiday':
        return {
          bg: isDarkMode ? 'bg-blue-500/10' : 'bg-blue-50',
          text: isDarkMode ? 'text-blue-400' : 'text-blue-700',
          border: isDarkMode ? 'border-blue-500/30' : 'border-blue-200',
          badge: isDarkMode ? 'bg-blue-500/20 text-blue-300 border-blue-500/30' : 'bg-blue-100 text-blue-700 border-blue-200',
          icon: '',
        };
      default:
        return {
          bg: isDarkMode ? 'bg-gray-500/10' : 'bg-gray-50',
          text: isDarkMode ? 'text-gray-400' : 'text-gray-700',
          border: isDarkMode ? 'border-gray-500/30' : 'border-gray-200',
          badge: isDarkMode ? 'bg-gray-500/20 text-gray-300 border-gray-500/30' : 'bg-gray-100 text-gray-700 border-gray-200',
          icon: '',
        };
    }
  };

  const daysOfWeek = [
    { value: 'monday', label: 'Monday' },
    { value: 'tuesday', label: 'Tuesday' },
    { value: 'wednesday', label: 'Wednesday' },
    { value: 'thursday', label: 'Thursday' },
    { value: 'friday', label: 'Friday' },
  ];

  const toggleRecurringDay = (day) => {
    setFormData((prev) => ({
      ...prev,
      recurring_days: prev.recurring_days.includes(day)
        ? prev.recurring_days.filter((d) => d !== day)
        : [...prev.recurring_days, day],
    }));
  };

  const eventTypes = [
    { value: 'available', label: 'Available', color: isDarkMode ? 'text-emerald-400' : 'text-emerald-600' },
    { value: 'unavailable', label: 'Unavailable', color: isDarkMode ? 'text-red-400' : 'text-red-600' },
    { value: 'holiday', label: 'Holiday', color: isDarkMode ? 'text-blue-400' : 'text-blue-600' },
  ];

  // Form Modal Component
  const FormModal = ({ isOpen, onClose, title, onSubmit }) => {
    if (!isOpen) return null;
    return (
      <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm">
        <div
          className={`w-full max-w-lg rounded-xl shadow-2xl border max-h-[90vh] overflow-y-auto ${
            isDarkMode ? 'bg-gray-900 border-amber-500/20' : 'bg-white border-gray-200'
          }`}
        >
          {/* Modal Header */}
          <div
            className={`flex items-center justify-between px-5 py-4 border-b ${
              isDarkMode ? 'border-amber-500/10' : 'border-gray-100'
            }`}
          >
            <div className="flex items-center gap-2">
              <CalendarDaysIcon className={`h-5 w-5 ${isDarkMode ? 'text-amber-400' : 'text-amber-600'}`} />
              <h3 className={`text-sm font-semibold ${isDarkMode ? 'text-amber-50' : 'text-gray-900'}`}>
                {title}
              </h3>
            </div>
            <button
              onClick={onClose}
              className={`p-1 rounded-lg transition-colors ${
                isDarkMode ? 'text-gray-400 hover:bg-gray-800' : 'text-gray-500 hover:bg-gray-100'
              }`}
            >
              <XMarkIcon className="h-5 w-5" />
            </button>
          </div>

          {/* Modal Body */}
          <div className="px-5 py-4">
            <form onSubmit={onSubmit} className="space-y-4">
              {/* Date & Type */}
              <div className="grid grid-cols-2 gap-4">
                <div>
                  <label
                    className={`block text-xs font-medium mb-1 ${
                      isDarkMode ? 'text-amber-50' : 'text-gray-700'
                    }`}
                  >
                    Date <span className="text-red-400">*</span>
                  </label>
                  <input
                    type="date"
                    value={formData.event_date}
                    onChange={(e) => setFormData((prev) => ({ ...prev, event_date: e.target.value }))}
                    className={`w-full px-3 py-2 rounded-lg border text-sm focus:outline-none focus:ring-2 focus:ring-amber-500 ${
                      isDarkMode
                        ? 'bg-gray-800 border-gray-600 text-white'
                        : 'bg-white border-gray-300 text-gray-900'
                    }`}
                    required
                  />
                </div>
                <div>
                  <label
                    className={`block text-xs font-medium mb-1 ${
                      isDarkMode ? 'text-amber-50' : 'text-gray-700'
                    }`}
                  >
                    Type <span className="text-red-400">*</span>
                  </label>
                  <select
                    value={formData.type}
                    onChange={(e) => setFormData((prev) => ({ ...prev, type: e.target.value }))}
                    className={`w-full px-3 py-2 rounded-lg border text-sm focus:outline-none focus:ring-2 focus:ring-amber-500 ${
                      isDarkMode
                        ? 'bg-gray-800 border-gray-600 text-white'
                        : 'bg-white border-gray-300 text-gray-900'
                    }`}
                    required
                  >
                    {eventTypes.map((t) => (
                      <option key={t.value} value={t.value}>
                        {t.label}
                      </option>
                    ))}
                  </select>
                </div>
              </div>

              {/* Time Range */}
              <div className="grid grid-cols-2 gap-4">
                <div>
                  <label
                    className={`block text-xs font-medium mb-1 ${
                      isDarkMode ? 'text-amber-50' : 'text-gray-700'
                    }`}
                  >
                    Start Time
                  </label>
                  <input
                    type="time"
                    value={formData.start_time}
                    onChange={(e) => setFormData((prev) => ({ ...prev, start_time: e.target.value }))}
                    className={`w-full px-3 py-2 rounded-lg border text-sm focus:outline-none focus:ring-2 focus:ring-amber-500 ${
                      isDarkMode
                        ? 'bg-gray-800 border-gray-600 text-white'
                        : 'bg-white border-gray-300 text-gray-900'
                    }`}
                  />
                </div>
                <div>
                  <label
                    className={`block text-xs font-medium mb-1 ${
                      isDarkMode ? 'text-amber-50' : 'text-gray-700'
                    }`}
                  >
                    End Time
                  </label>
                  <input
                    type="time"
                    value={formData.end_time}
                    onChange={(e) => setFormData((prev) => ({ ...prev, end_time: e.target.value }))}
                    className={`w-full px-3 py-2 rounded-lg border text-sm focus:outline-none focus:ring-2 focus:ring-amber-500 ${
                      isDarkMode
                        ? 'bg-gray-800 border-gray-600 text-white'
                        : 'bg-white border-gray-300 text-gray-900'
                    }`}
                  />
                </div>
              </div>

              {/* Reason */}
              <div>
                <label
                  className={`block text-xs font-medium mb-1 ${
                    isDarkMode ? 'text-amber-50' : 'text-gray-700'
                  }`}
                >
                  Reason
                </label>
                <input
                  type="text"
                  value={formData.reason}
                  onChange={(e) => setFormData((prev) => ({ ...prev, reason: e.target.value }))}
                  className={`w-full px-3 py-2 rounded-lg border text-sm focus:outline-none focus:ring-2 focus:ring-amber-500 ${
                    isDarkMode
                      ? 'bg-gray-800 border-gray-600 text-white placeholder-gray-500'
                      : 'bg-white border-gray-300 text-gray-900 placeholder-gray-400'
                  }`}
                  placeholder="e.g., Vacation, Maintenance, etc."
                />
              </div>

              {/* Recurring Toggle */}
              <div>
                <label className="flex items-center gap-2 cursor-pointer">
                  <input
                    type="checkbox"
                    checked={formData.is_recurring}
                    onChange={(e) =>
                      setFormData((prev) => ({ ...prev, is_recurring: e.target.checked }))
                    }
                    className="rounded border-gray-300 text-amber-500 focus:ring-amber-500"
                  />
                  <span className={`text-sm ${isDarkMode ? 'text-gray-300' : 'text-gray-700'}`}>
                    Recurring Event
                  </span>
                </label>
              </div>

              {/* Recurring Days */}
              {formData.is_recurring && (
                <div>
                  <label
                    className={`block text-xs font-medium mb-2 ${
                      isDarkMode ? 'text-amber-50' : 'text-gray-700'
                    }`}
                  >
                    Recurring Days
                  </label>
                  <div className="flex flex-wrap gap-2">
                    {daysOfWeek.map((day) => (
                      <button
                        key={day.value}
                        type="button"
                        onClick={() => toggleRecurringDay(day.value)}
                        className={`px-3 py-1.5 text-xs font-medium rounded-lg border transition-all ${
                          formData.recurring_days.includes(day.value)
                            ? isDarkMode
                              ? 'bg-amber-500/20 border-amber-500/30 text-amber-400 ring-1 ring-amber-500/50'
                              : 'bg-amber-50 border-amber-300 text-amber-700 ring-1 ring-amber-500/50'
                            : isDarkMode
                            ? 'border-gray-700 text-gray-400 hover:border-gray-600'
                            : 'border-gray-200 text-gray-500 hover:border-gray-300'
                        }`}
                      >
                        {day.label}
                      </button>
                    ))}
                  </div>
                </div>
              )}

              {/* Actions */}
              <div
                className={`flex justify-end gap-2 pt-3 border-t ${
                  isDarkMode ? 'border-gray-700' : 'border-gray-200'
                }`}
              >
                <button
                  type="button"
                  onClick={onClose}
                  className={`px-4 py-2 text-xs font-medium rounded-lg border transition-colors ${
                    isDarkMode
                      ? 'border-gray-600 text-gray-300 hover:bg-gray-800'
                      : 'border-gray-300 text-gray-700 hover:bg-gray-100'
                  }`}
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  disabled={loading}
                  className="px-4 py-2 text-xs font-medium rounded-lg bg-gradient-to-r from-amber-600 to-amber-700 text-white hover:from-amber-700 hover:to-amber-800 transition-all disabled:opacity-50 shadow border border-amber-500/30"
                >
                  {loading ? (
                    <span className="flex items-center gap-1.5">
                      <div className="w-3 h-3 border-2 border-white border-t-transparent rounded-full animate-spin" />
                      Saving...
                    </span>
                  ) : selectedEvent ? (
                    'Update Event'
                  ) : (
                    'Create Event'
                  )}
                </button>
              </div>
            </form>
          </div>
        </div>
      </div>
    );
  };

  return (
    <div
      className={`min-h-screen transition-colors duration-300 ${
        isDarkMode ? 'bg-gradient-to-br from-gray-900 to-black' : 'bg-gradient-to-br from-gray-100 to-gray-200'
      }`}
    >
      {/* Header */}
      <header
        className={`shadow-sm border-b ${
          isDarkMode ? 'bg-gray-900 border-amber-500/20' : 'bg-white border-gray-200'
        }`}
      >
        <div className="container mx-auto px-4 py-4">
          <div className="flex flex-col sm:flex-row sm:justify-between sm:items-start gap-3 sm:gap-4">
            <div>
              <h1
                className={`text-xl sm:text-2xl font-bold ${
                  isDarkMode ? 'text-amber-50' : 'text-gray-900'
                }`}
              >
                Calendar Management
              </h1>
              <p className={`text-sm ${isDarkMode ? 'text-gray-400' : 'text-gray-600'}`}>
                Manage availability and special dates
              </p>
            </div>
            <div className="flex items-center gap-2">
              <button
                onClick={loadEvents}
                className={`p-2 rounded-lg border transition-colors ${
                  isDarkMode
                    ? 'border-amber-500/30 text-amber-400 hover:bg-amber-500/10'
                    : 'border-amber-300 text-amber-700 hover:bg-amber-50'
                }`}
                title="Refresh"
              >
                <ArrowPathIcon className={`h-4 w-4 ${loading ? 'animate-spin' : ''}`} />
              </button>
              <button
                onClick={() => {
                  resetForm();
                  setIsCreateModalOpen(true);
                }}
                className="flex items-center gap-1.5 px-3 py-2 text-xs font-medium rounded-lg bg-gradient-to-r from-amber-600 to-amber-700 text-white hover:from-amber-700 hover:to-amber-800 transition-all shadow border border-amber-500/30"
              >
                <PlusIcon className="h-4 w-4" />
                <span className="hidden sm:inline">Add Event</span>
                <span className="sm:hidden">Add</span>
              </button>
            </div>
          </div>
        </div>
      </header>

      {/* Filters */}
      <div className="container mx-auto px-4 py-4">
        <div
          className={`rounded-lg shadow border p-4 ${
            isDarkMode ? 'bg-gray-900 border-amber-500/20' : 'bg-white border-gray-200'
          }`}
        >
          <div className="flex items-center gap-2 mb-3">
            <FunnelIcon className={`h-4 w-4 ${isDarkMode ? 'text-amber-400' : 'text-amber-600'}`} />
            <span className={`text-xs font-semibold ${isDarkMode ? 'text-amber-50' : 'text-gray-700'}`}>
              Date Range Filter
            </span>
          </div>
          <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3">
            <div>
              <label
                className={`block text-xs font-medium mb-1 ${
                  isDarkMode ? 'text-gray-400' : 'text-gray-600'
                }`}
              >
                Start Date
              </label>
              <input
                type="date"
                value={dateRange.start_date}
                onChange={(e) => setDateRange((prev) => ({ ...prev, start_date: e.target.value }))}
                className={`w-full px-3 py-2 rounded-lg border text-sm focus:outline-none focus:ring-2 focus:ring-amber-500 ${
                  isDarkMode
                    ? 'bg-gray-800 border-gray-700 text-white'
                    : 'bg-white border-gray-300 text-gray-900'
                }`}
              />
            </div>
            <div>
              <label
                className={`block text-xs font-medium mb-1 ${
                  isDarkMode ? 'text-gray-400' : 'text-gray-600'
                }`}
              >
                End Date
              </label>
              <input
                type="date"
                value={dateRange.end_date}
                onChange={(e) => setDateRange((prev) => ({ ...prev, end_date: e.target.value }))}
                className={`w-full px-3 py-2 rounded-lg border text-sm focus:outline-none focus:ring-2 focus:ring-amber-500 ${
                  isDarkMode
                    ? 'bg-gray-800 border-gray-700 text-white'
                    : 'bg-white border-gray-300 text-gray-900'
                }`}
              />
            </div>
          </div>
        </div>
      </div>

      {/* Events List */}
      <main className="container mx-auto px-4 pb-8">
        <div
          className={`rounded-lg shadow border overflow-hidden ${
            isDarkMode ? 'bg-gray-900 border-amber-500/20' : 'bg-white border-gray-200'
          }`}
        >
          {loading && events.length === 0 ? (
            <div className="flex justify-center py-12">
              <div
                className={`w-8 h-8 border-3 rounded-full animate-spin ${
                  isDarkMode ? 'border-amber-500 border-t-transparent' : 'border-amber-600 border-t-transparent'
                }`}
              />
            </div>
          ) : events.length === 0 ? (
            <div
              className={`text-center py-12 ${
                isDarkMode ? 'bg-gray-900/50' : 'bg-gray-50'
              }`}
            >
              <CalendarIcon
                className={`h-12 w-12 mx-auto mb-3 ${isDarkMode ? 'text-gray-600' : 'text-gray-300'}`}
              />
              <h3
                className={`text-sm font-medium ${isDarkMode ? 'text-gray-400' : 'text-gray-600'}`}
              >
                No calendar events
              </h3>
              <p className={`text-xs mt-1 ${isDarkMode ? 'text-gray-500' : 'text-gray-400'}`}>
                Add events to manage your availability
              </p>
            </div>
          ) : (
            <div className="space-y-0">
              {events.map((event) => {
                const config = getEventConfig(event.type);
                return (
                  <div
                    key={event.id}
                    className={`border-b last:border-b-0 px-4 py-4 transition-all hover:shadow-md ${
                      isDarkMode
                        ? 'border-gray-800 hover:bg-gray-800/50'
                        : 'border-gray-100 hover:bg-gray-50'
                    }`}
                  >
                    <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                      <div className="flex items-start gap-3 min-w-0 flex-1">
                        {/* Icon */}
                        <div
                          className={`flex-shrink-0 w-10 h-10 rounded-lg flex items-center justify-center text-lg border ${config.bg} ${config.border}`}
                        >
                          {config.icon}
                        </div>
                        <div className="min-w-0 flex-1">
                          <div className="flex items-center gap-2 flex-wrap">
                            <h4
                              className={`text-sm font-semibold capitalize ${
                                isDarkMode ? 'text-amber-50' : 'text-gray-900'
                              }`}
                            >
                              {event.type}
                            </h4>
                            <span
                              className={`px-2 py-0.5 text-[10px] font-medium rounded-full border ${config.badge}`}
                            >
                              {new Date(event.event_date).toLocaleDateString('en-US', {
                                weekday: 'short',
                                month: 'short',
                                day: 'numeric',
                                year: 'numeric',
                              })}
                            </span>
                          </div>
                          <div
                            className={`flex items-center gap-3 mt-1.5 text-xs ${
                              isDarkMode ? 'text-gray-500' : 'text-gray-400'
                            }`}
                          >
                            <span className="flex items-center gap-1">
                              <ClockIcon className="h-3 w-3" />
                              {event.start_time && event.end_time
                                ? `${event.start_time} - ${event.end_time}`
                                : 'All day'}
                            </span>
                            {event.is_recurring && (
                              <span
                                className={`flex items-center gap-1 ${
                                  isDarkMode ? 'text-purple-400' : 'text-purple-600'
                                }`}
                              >
                                <ArrowPathIcon className="h-3 w-3" />
                                Recurring
                              </span>
                            )}
                          </div>
                          {event.reason && (
                            <p
                              className={`text-xs mt-1 ${
                                isDarkMode ? 'text-gray-400' : 'text-gray-600'
                              }`}
                            >
                              {event.reason}
                            </p>
                          )}
                        </div>
                      </div>
                      {/* Actions */}
                      <div className="flex items-center gap-1 flex-shrink-0">
                        <button
                          onClick={() => openEditModal(event)}
                          className={`p-1.5 rounded-lg transition-colors ${
                            isDarkMode
                              ? 'text-gray-400 hover:text-blue-400 hover:bg-blue-500/10'
                              : 'text-gray-500 hover:text-blue-600 hover:bg-blue-50'
                          }`}
                          title="Edit"
                        >
                          <PencilIcon className="h-4 w-4" />
                        </button>
                        <button
                          onClick={() => handleDeleteEvent(event.id)}
                          className={`p-1.5 rounded-lg transition-colors ${
                            isDarkMode
                              ? 'text-gray-400 hover:text-red-400 hover:bg-red-500/10'
                              : 'text-gray-500 hover:text-red-600 hover:bg-red-50'
                          }`}
                          title="Delete"
                        >
                          <TrashIcon className="h-4 w-4" />
                        </button>
                      </div>
                    </div>
                  </div>
                );
              })}
            </div>
          )}
        </div>

        {/* Legend */}
        <div
          className={`mt-4 rounded-lg border p-3 ${
            isDarkMode ? 'bg-gray-900/50 border-gray-800' : 'bg-gray-50 border-gray-200'
          }`}
        >
          <div className="flex flex-wrap gap-4 text-xs">
            {[
              { icon: '', label: 'Available', color: isDarkMode ? 'text-emerald-400' : 'text-emerald-600' },
              { icon: '', label: 'Unavailable', color: isDarkMode ? 'text-red-400' : 'text-red-600' },
              { icon: '', label: 'Holiday', color: isDarkMode ? 'text-blue-400' : 'text-blue-600' },
            ].map((item) => (
              <div key={item.label} className="flex items-center gap-1.5">
                <span>{item.icon}</span>
                <span className={isDarkMode ? 'text-gray-400' : 'text-gray-500'}>{item.label}</span>
              </div>
            ))}
          </div>
        </div>
      </main>

      {/* Create Modal */}
      <FormModal
        isOpen={isCreateModalOpen}
        onClose={() => {
          setIsCreateModalOpen(false);
          resetForm();
        }}
        title="Add Calendar Event"
        onSubmit={handleCreateEvent}
      />

      {/* Edit Modal */}
      <FormModal
        isOpen={isEditModalOpen}
        onClose={() => {
          setIsEditModalOpen(false);
          resetForm();
        }}
        title="Edit Calendar Event"
        onSubmit={handleUpdateEvent}
      />
    </div>
  );
};

export default CalendarManagement;
