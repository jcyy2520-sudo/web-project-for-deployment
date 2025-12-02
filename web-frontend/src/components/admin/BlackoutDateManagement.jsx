import { useState, useEffect } from 'react';
import axios from 'axios';
import { useApi } from '../../hooks/useApi';
import {
  PlusIcon,
  TrashIcon,
  PencilIcon,
  ExclamationTriangleIcon,
  CheckCircleIcon,
  CalendarIcon,
  XMarkIcon
} from '@heroicons/react/24/outline';
import LoadingSpinner from '../LoadingSpinner';
import Modal from '../Modal';

/**
 * Blackout Date Management Component
 * Allows admins to block specific dates/times from bookings
 */
const BlackoutDateManagement = ({ isDarkMode = true }) => {
  const { callApi, loading } = useApi();
  const [blackoutDates, setBlackoutDates] = useState([]);
  const [showModal, setShowModal] = useState(false);
  const [editingId, setEditingId] = useState(null);
  const [error, setError] = useState(null);
  const [success, setSuccess] = useState(null);

  const [formData, setFormData] = useState({
    date: '',
    reason: '',
    start_time: '',
    end_time: '',
    is_recurring: false,
    recurring_days: []
  });

  const daysOfWeek = [
    { value: 'monday', label: 'Monday' },
    { value: 'tuesday', label: 'Tuesday' },
    { value: 'wednesday', label: 'Wednesday' },
    { value: 'thursday', label: 'Thursday' },
    { value: 'friday', label: 'Friday' }
  ];

  useEffect(() => {
    loadBlackoutDates();
  }, []);

  const loadBlackoutDates = async () => {
    const result = await callApi(() =>
      axios.get('/api/admin/blackout-dates')
    );

    if (result.success) {
      setBlackoutDates(result.data.data || []);
    }
  };

  const handleOpenModal = (blackout = null) => {
    if (blackout) {
      setEditingId(blackout.id);
      setFormData({
        date: blackout.date || '',
        reason: blackout.reason || '',
        start_time: blackout.start_time || '',
        end_time: blackout.end_time || '',
        is_recurring: blackout.is_recurring || false,
        recurring_days: blackout.recurring_days || []
      });
    } else {
      setEditingId(null);
      setFormData({
        date: '',
        reason: '',
        start_time: '',
        end_time: '',
        is_recurring: false,
        recurring_days: []
      });
    }
    setShowModal(true);
  };

  const toggleRecurringDay = (day) => {
    setFormData(prev => ({
      ...prev,
      recurring_days: prev.recurring_days.includes(day)
        ? prev.recurring_days.filter(d => d !== day)
        : [...prev.recurring_days, day]
    }));
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    setError(null);
    setSuccess(null);

    // Validation
    if (!formData.is_recurring && !formData.date) {
      setError('Please select a date');
      return;
    }
    if (formData.is_recurring && formData.recurring_days.length === 0) {
      setError('Please select at least one recurring day');
      return;
    }
    if (!formData.reason) {
      setError('Please enter a reason');
      return;
    }

    try {
      if (editingId) {
        const result = await callApi(() =>
          axios.put(`/api/admin/blackout-dates/${editingId}`, formData)
        );

        if (result.success) {
          setSuccess('Blackout date updated successfully!');
          await loadBlackoutDates();
        } else {
          setError(result.message || 'Failed to update blackout date');
        }
      } else {
        const result = await callApi(() =>
          axios.post('/api/admin/blackout-dates', formData)
        );

        if (result.success) {
          setSuccess('Blackout date created successfully!');
          await loadBlackoutDates();
        } else {
          setError(result.message || 'Failed to create blackout date');
        }
      }

      setShowModal(false);
    } catch (err) {
      setError('An error occurred: ' + (err.message || 'Unknown error'));
    }
  };

  const handleDelete = async (id) => {
    if (!window.confirm('Are you sure you want to delete this blackout date?')) return;

    const result = await callApi(() =>
      axios.delete(`/api/admin/blackout-dates/${id}`)
    );

    if (result.success) {
      setSuccess('Blackout date deleted successfully!');
      await loadBlackoutDates();
    } else {
      setError('Failed to delete blackout date');
    }
  };

  return (
    <div className={`rounded-lg border shadow-lg ${isDarkMode ? 'bg-gray-800 border-gray-700' : 'bg-white border-gray-200'}`}>
      {/* Header */}
      <div className={`p-4 border-b flex justify-between items-center ${isDarkMode ? 'border-gray-700 bg-gray-900' : 'border-gray-200 bg-gray-50'}`}>
        <div className="flex items-center gap-2">
          <CalendarIcon className={`h-5 w-5 ${isDarkMode ? 'text-red-400' : 'text-red-600'}`} />
          <div>
            <h3 className={`font-semibold ${isDarkMode ? 'text-amber-50' : 'text-amber-900'}`}>
              Blackout Dates & Times
            </h3>
            <p className={`text-xs mt-1 ${isDarkMode ? 'text-gray-400' : 'text-gray-600'}`}>
              Block specific dates or times from bookings
            </p>
          </div>
        </div>
        <button
          onClick={() => handleOpenModal()}
          className="flex items-center gap-2 px-3 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors"
        >
          <PlusIcon className="h-4 w-4" />
          Block Date/Time
        </button>
      </div>

      {/* Messages */}
      {error && (
        <div className={`m-4 p-3 rounded-lg border flex items-start gap-2 ${isDarkMode ? 'bg-red-500/10 border-red-500/30 text-red-400' : 'bg-red-50 border-red-200 text-red-700'}`}>
          <ExclamationTriangleIcon className="h-4 w-4 mt-0.5 flex-shrink-0" />
          <p className="text-sm">{error}</p>
        </div>
      )}

      {success && (
        <div className={`m-4 p-3 rounded-lg border flex items-start gap-2 ${isDarkMode ? 'bg-green-500/10 border-green-500/30 text-green-400' : 'bg-green-50 border-green-200 text-green-700'}`}>
          <CheckCircleIcon className="h-4 w-4 mt-0.5 flex-shrink-0" />
          <p className="text-sm">{success}</p>
        </div>
      )}

      {/* Content */}
      <div className="p-4">
        {loading && !blackoutDates.length ? (
          <div className="flex justify-center py-6">
            <LoadingSpinner size="sm" />
          </div>
        ) : blackoutDates.length === 0 ? (
          <div className={`text-center py-8 ${isDarkMode ? 'text-gray-400' : 'text-gray-600'}`}>
            <p>No blackout dates configured</p>
            <p className="text-xs mt-1">All dates and times are available for booking</p>
          </div>
        ) : (
          <div className="space-y-2">
            {blackoutDates.map((blackout) => (
              <div
                key={blackout.id}
                className={`p-3 rounded-lg border flex justify-between items-start ${
                  isDarkMode
                    ? 'bg-gray-700 border-gray-600 hover:border-red-500/50'
                    : 'bg-gray-50 border-gray-200 hover:border-red-300'
                }`}
              >
                <div className="flex-1">
                  <div className="flex items-center gap-2">
                    <span className={`text-xs font-semibold px-2 py-1 rounded ${
                      blackout.is_recurring
                        ? isDarkMode ? 'bg-purple-500/20 text-purple-300' : 'bg-purple-100 text-purple-700'
                        : isDarkMode ? 'bg-red-500/20 text-red-300' : 'bg-red-100 text-red-700'
                    }`}>
                      {blackout.is_recurring ? 'Recurring' : 'Single Date'}
                    </span>
                    <p className={`font-semibold ${isDarkMode ? 'text-amber-50' : 'text-amber-900'}`}>
                      {blackout.reason}
                    </p>
                  </div>

                  <div className={`text-xs mt-2 space-y-1 ${isDarkMode ? 'text-gray-400' : 'text-gray-600'}`}>
                    {blackout.is_recurring ? (
                      <p>
                        Every: {blackout.recurring_days?.map(d => d.charAt(0).toUpperCase() + d.slice(1)).join(', ')}
                      </p>
                    ) : (
                      <p>Date: {new Date(blackout.date).toLocaleDateString()}</p>
                    )}

                    {blackout.start_time && blackout.end_time && (
                      <p>Time: {blackout.start_time} - {blackout.end_time}</p>
                    )}
                    {!blackout.start_time && !blackout.end_time && (
                      <p>Time: All day blocked</p>
                    )}
                  </div>
                </div>

                <div className="flex gap-2">
                  <button
                    onClick={() => handleOpenModal(blackout)}
                    className={`p-1 rounded transition-colors ${isDarkMode ? 'text-blue-400 hover:bg-blue-500/10' : 'text-blue-600 hover:bg-blue-100'}`}
                    title="Edit"
                  >
                    <PencilIcon className="h-4 w-4" />
                  </button>
                  <button
                    onClick={() => handleDelete(blackout.id)}
                    className={`p-1 rounded transition-colors ${isDarkMode ? 'text-red-400 hover:bg-red-500/10' : 'text-red-600 hover:bg-red-100'}`}
                    title="Delete"
                  >
                    <TrashIcon className="h-4 w-4" />
                  </button>
                </div>
              </div>
            ))}
          </div>
        )}
      </div>

      {/* Modal */}
      <Modal
        isOpen={showModal}
        onClose={() => setShowModal(false)}
        title={editingId ? 'Edit Blackout Date' : 'Add Blackout Date'}
        size="md"
      >
        <form onSubmit={handleSubmit} className="space-y-4">
          <div>
            <label className={`block text-sm font-medium mb-2 ${isDarkMode ? 'text-gray-300' : 'text-gray-700'}`}>
              Type
            </label>
            <div className="flex gap-4">
              <label className="flex items-center">
                <input
                  type="radio"
                  checked={!formData.is_recurring}
                  onChange={() => setFormData({ ...formData, is_recurring: false })}
                  className="rounded border-gray-300"
                />
                <span className={`ml-2 text-sm ${isDarkMode ? 'text-gray-300' : 'text-gray-700'}`}>
                  Single Date
                </span>
              </label>
              <label className="flex items-center">
                <input
                  type="radio"
                  checked={formData.is_recurring}
                  onChange={() => setFormData({ ...formData, is_recurring: true })}
                  className="rounded border-gray-300"
                />
                <span className={`ml-2 text-sm ${isDarkMode ? 'text-gray-300' : 'text-gray-700'}`}>
                  Recurring
                </span>
              </label>
            </div>
          </div>

          {!formData.is_recurring && (
            <div>
              <label className={`block text-sm font-medium mb-1 ${isDarkMode ? 'text-gray-300' : 'text-gray-700'}`}>
                Date
              </label>
              <input
                type="date"
                value={formData.date}
                onChange={(e) => setFormData({ ...formData, date: e.target.value })}
                className={`w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500 ${
                  isDarkMode
                    ? 'bg-gray-700 border-gray-600 text-white'
                    : 'bg-white border-gray-300 text-gray-900'
                }`}
              />
            </div>
          )}

          {formData.is_recurring && (
            <div>
              <label className={`block text-sm font-medium mb-2 ${isDarkMode ? 'text-gray-300' : 'text-gray-700'}`}>
                Recurring Days
              </label>
              <div className="grid grid-cols-2 gap-2">
                {daysOfWeek.map((day) => (
                  <label key={day.value} className="flex items-center">
                    <input
                      type="checkbox"
                      checked={formData.recurring_days.includes(day.value)}
                      onChange={() => toggleRecurringDay(day.value)}
                      className="rounded border-gray-300"
                    />
                    <span className={`ml-2 text-sm ${isDarkMode ? 'text-gray-300' : 'text-gray-700'}`}>
                      {day.label}
                    </span>
                  </label>
                ))}
              </div>
            </div>
          )}

          <div>
            <label className={`block text-sm font-medium mb-1 ${isDarkMode ? 'text-gray-300' : 'text-gray-700'}`}>
              Reason
            </label>
            <input
              type="text"
              value={formData.reason}
              onChange={(e) => setFormData({ ...formData, reason: e.target.value })}
              placeholder="e.g., Holiday, Maintenance, Closed, etc."
              className={`w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500 ${
                isDarkMode
                  ? 'bg-gray-700 border-gray-600 text-white placeholder-gray-500'
                  : 'bg-white border-gray-300 text-gray-900 placeholder-gray-400'
              }`}
              required
            />
          </div>

          <div>
            <label className={`block text-sm font-medium mb-1 ${isDarkMode ? 'text-gray-300' : 'text-gray-700'}`}>
              Block Time Range (Optional)
            </label>
            <p className={`text-xs mb-2 ${isDarkMode ? 'text-gray-400' : 'text-gray-600'}`}>
              Leave empty to block the entire day
            </p>
            <div className="grid grid-cols-2 gap-4">
              <input
                type="time"
                value={formData.start_time}
                onChange={(e) => setFormData({ ...formData, start_time: e.target.value })}
                className={`px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500 ${
                  isDarkMode
                    ? 'bg-gray-700 border-gray-600 text-white'
                    : 'bg-white border-gray-300 text-gray-900'
                }`}
                placeholder="Start Time"
              />
              <input
                type="time"
                value={formData.end_time}
                onChange={(e) => setFormData({ ...formData, end_time: e.target.value })}
                className={`px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500 ${
                  isDarkMode
                    ? 'bg-gray-700 border-gray-600 text-white'
                    : 'bg-white border-gray-300 text-gray-900'
                }`}
                placeholder="End Time"
              />
            </div>
          </div>

          <div className="flex justify-end gap-3 pt-4">
            <button
              type="button"
              onClick={() => setShowModal(false)}
              className={`px-4 py-2 border rounded-lg text-sm font-medium transition-colors ${
                isDarkMode
                  ? 'border-gray-600 text-gray-300 hover:bg-gray-700'
                  : 'border-gray-300 text-gray-700 hover:bg-gray-100'
              }`}
            >
              Cancel
            </button>
            <button
              type="submit"
              disabled={loading}
              className="px-4 py-2 bg-red-600 text-white rounded-lg text-sm font-medium hover:bg-red-700 transition-colors disabled:opacity-50"
            >
              {loading ? 'Saving...' : editingId ? 'Update' : 'Create'}
            </button>
          </div>
        </form>
      </Modal>
    </div>
  );
};

export default BlackoutDateManagement;
