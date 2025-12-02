import { useState, useEffect } from 'react';
import { useAuth } from '../context/AuthContext';
import { useApi } from '../hooks/useApi';
import Modal from '../components/Modal';
import LoadingSpinner from '../components/LoadingSpinner';
import { 
  PlusIcon,
  PencilIcon,
  TrashIcon,
  CalendarIcon
} from '@heroicons/react/24/outline';



const CalendarManagement = () => {
  const { user } = useAuth();
  const { callApi, loading } = useApi();
  const [events, setEvents] = useState([]);
  const [isCreateModalOpen, setIsCreateModalOpen] = useState(false);
  const [isEditModalOpen, setIsEditModalOpen] = useState(false);
  const [selectedEvent, setSelectedEvent] = useState(null);
  const [dateRange, setDateRange] = useState({
    start_date: new Date().toISOString().split('T')[0],
    end_date: new Date(Date.now() + 30 * 24 * 60 * 60 * 1000).toISOString().split('T')[0]
  });

  const [formData, setFormData] = useState({
    event_date: '',
    type: 'available',
    reason: '',
    start_time: '',
    end_time: '',
    is_recurring: false,
    recurring_days: []
  });

  useEffect(() => {
    loadEvents();
  }, [dateRange]);

  const loadEvents = async () => {
    const result = await callApi((signal) =>
      axios.get('/api/calendar', { params: { start_date: dateRange.start_date, end_date: dateRange.end_date }, signal })
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
      alert('Calendar event created successfully!');
    }
  };

  const handleUpdateEvent = async (e) => {
    e.preventDefault();
    const result = await callApi((signal) => axios.put(`/api/calendar/${selectedEvent.id}`, formData, { signal }));

    if (result.success) {
      setIsEditModalOpen(false);
      resetForm();
      loadEvents();
      alert('Calendar event updated successfully!');
    }
  };

  const handleDeleteEvent = async (eventId) => {
    if (!confirm('Are you sure you want to delete this calendar event?')) return;

    const result = await callApi((signal) => axios.delete(`/api/calendar/${eventId}`, { signal }));

    if (result.success) {
      loadEvents();
      alert('Calendar event deleted successfully!');
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
      recurring_days: []
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
      recurring_days: event.recurring_days || []
    });
    setIsEditModalOpen(true);
  };

  const getEventColor = (type) => {
    switch (type) {
      case 'available': return 'bg-green-100 text-green-800 border-green-200';
      case 'unavailable': return 'bg-red-100 text-red-800 border-red-200';
      case 'holiday': return 'bg-blue-100 text-blue-800 border-blue-200';
      default: return 'bg-gray-100 text-gray-800 border-gray-200';
    }
  };

  const getEventIcon = (type) => {
    switch (type) {
      case 'available': return '✅';
      case 'unavailable': return '❌';
      case 'holiday': return '🎉';
      default: return '📅';
    }
  };

  const daysOfWeek = [
    { value: 'monday', label: 'Monday' },
    { value: 'tuesday', label: 'Tuesday' },
    { value: 'wednesday', label: 'Wednesday' },
    { value: 'thursday', label: 'Thursday' },
    { value: 'friday', label: 'Friday' }
  ];

  const toggleRecurringDay = (day) => {
    setFormData(prev => ({
      ...prev,
      recurring_days: prev.recurring_days.includes(day)
        ? prev.recurring_days.filter(d => d !== day)
        : [...prev.recurring_days, day]
    }));
  };

  return (
    <div className="min-h-screen bg-gray-50">
      {/* Header */}
      <header className="bg-white shadow-sm border-b">
        <div className="container mx-auto px-4 py-4">
          <div className="flex flex-col sm:flex-row sm:justify-between sm:items-start gap-3 sm:gap-4">
            <div>
              <h1 className="text-xl sm:text-2xl font-bold text-gray-900">Calendar Management</h1>
              <p className="text-sm sm:text-base text-gray-600">Manage availability and special dates</p>
            </div>
            <button
              onClick={() => setIsCreateModalOpen(true)}
              className="btn-primary flex items-center justify-center sm:justify-start space-x-2 w-full sm:w-auto"
            >
              <PlusIcon className="h-4 w-4" />
              <span>Add Event</span>
            </button>
          </div>
        </div>
      </header>

      {/* Filters */}
      <div className="container mx-auto px-4 py-6">
        <div className="bg-white rounded-lg shadow-sm border p-3 sm:p-4">
          <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3 sm:gap-4">
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">
                Start Date
              </label>
              <input
                type="date"
                value={dateRange.start_date}
                onChange={(e) => setDateRange(prev => ({ ...prev, start_date: e.target.value }))}
                className="input-field"
              />
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">
                End Date
              </label>
              <input
                type="date"
                value={dateRange.end_date}
                onChange={(e) => setDateRange(prev => ({ ...prev, end_date: e.target.value }))}
                className="input-field"
              />
            </div>
            <div className="flex items-end">
              <button
                onClick={loadEvents}
                className="btn-secondary w-full"
              >
                Refresh
              </button>
            </div>
          </div>
        </div>
      </div>

      {/* Main Content */}
      <main className="container mx-auto px-4 pb-8">
        <div className="bg-white rounded-lg shadow-sm border">
          {loading ? (
            <div className="flex justify-center p-8">
              <LoadingSpinner size="lg" />
            </div>
          ) : events.length === 0 ? (
            <div className="text-center py-12">
              <CalendarIcon className="h-12 w-12 text-gray-400 mx-auto mb-4" />
              <h3 className="text-lg font-semibold text-gray-900">No calendar events</h3>
              <p className="text-gray-600 mt-2">Add events to manage your availability.</p>
            </div>
          ) : (
            <div className="divide-y">
              {events.map((event) => (
                <div key={event.id} className="p-6 hover:bg-gray-50">
                  <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 sm:gap-4">
                    <div className="flex items-center space-x-3 sm:space-x-4 min-w-0 flex-1">
                      <span className="text-lg">{getEventIcon(event.type)}</span>
                      <div>
                        <h4 className="font-semibold text-gray-900 capitalize">
                          {event.type} - {new Date(event.event_date).toLocaleDateString()}
                        </h4>
                        <p className="text-sm text-gray-600">
                          {event.start_time && event.end_time 
                            ? `${event.start_time} - ${event.end_time}`
                            : 'All day'
                          }
                        </p>
                        {event.reason && (
                          <p className="text-sm text-gray-500 mt-1">{event.reason}</p>
                        )}
                        {event.is_recurring && (
                          <p className="text-sm text-blue-600 mt-1">Recurring event</p>
                        )}
                      </div>
                    </div>
                    
                    <div className="flex flex-wrap items-center gap-2 flex-shrink-0">
                      <span className={`px-2 py-1 rounded-full text-xs font-medium border ${getEventColor(event.type)}`}>
                        {event.type}
                      </span>
                      
                      <button
                        onClick={() => openEditModal(event)}
                        className="text-green-600 hover:text-green-900 hover:bg-green-50 p-2 rounded transition-colors duration-200"
                        title="Edit"
                      >
                        <PencilIcon className="h-4 w-4" />
                      </button>
                      
                      <button
                        onClick={() => handleDeleteEvent(event.id)}
                        className="text-red-600 hover:text-red-900 hover:bg-red-50 p-2 rounded transition-colors duration-200"
                        title="Delete"
                      >
                        <TrashIcon className="h-4 w-4" />
                      </button>
                    </div>
                  </div>
                </div>
              ))}
            </div>
          )}
        </div>
      </main>

      {/* Create Event Modal */}
      <Modal
        isOpen={isCreateModalOpen}
        onClose={() => {
          setIsCreateModalOpen(false);
          resetForm();
        }}
        title="Add Calendar Event"
        size="lg"
      >
        <form onSubmit={handleCreateEvent} className="space-y-4">
          <div className="grid grid-cols-2 gap-4">
            <div>
              <label className="block text-sm font-medium text-gray-700">Date</label>
              <input
                type="date"
                value={formData.event_date}
                onChange={(e) => setFormData(prev => ({ ...prev, event_date: e.target.value }))}
                className="input-field"
                required
              />
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700">Type</label>
              <select
                value={formData.type}
                onChange={(e) => setFormData(prev => ({ ...prev, type: e.target.value }))}
                className="input-field"
                required
              >
                <option value="available">Available</option>
                <option value="unavailable">Unavailable</option>
                <option value="holiday">Holiday</option>
              </select>
            </div>
          </div>

          <div className="grid grid-cols-2 gap-4">
            <div>
              <label className="block text-sm font-medium text-gray-700">Start Time (Optional)</label>
              <input
                type="time"
                value={formData.start_time}
                onChange={(e) => setFormData(prev => ({ ...prev, start_time: e.target.value }))}
                className="input-field"
              />
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700">End Time (Optional)</label>
              <input
                type="time"
                value={formData.end_time}
                onChange={(e) => setFormData(prev => ({ ...prev, end_time: e.target.value }))}
                className="input-field"
              />
            </div>
          </div>

          <div>
            <label className="block text-sm font-medium text-gray-700">Reason (Optional)</label>
            <input
              type="text"
              value={formData.reason}
              onChange={(e) => setFormData(prev => ({ ...prev, reason: e.target.value }))}
              className="input-field"
              placeholder="e.g., Vacation, Maintenance, etc."
            />
          </div>

          <div>
            <label className="flex items-center">
              <input
                type="checkbox"
                checked={formData.is_recurring}
                onChange={(e) => setFormData(prev => ({ ...prev, is_recurring: e.target.checked }))}
                className="rounded border-gray-300 text-black focus:ring-black"
              />
              <span className="ml-2 text-sm text-gray-700">Recurring Event</span>
            </label>
          </div>

          {formData.is_recurring && (
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">Recurring Days</label>
              <div className="grid grid-cols-2 sm:grid-cols-4 gap-2">
                {daysOfWeek.map((day) => (
                  <label key={day.value} className="flex items-center">
                    <input
                      type="checkbox"
                      checked={formData.recurring_days.includes(day.value)}
                      onChange={() => toggleRecurringDay(day.value)}
                      className="rounded border-gray-300 text-black focus:ring-black"
                    />
                    <span className="ml-2 text-sm text-gray-700">{day.label}</span>
                  </label>
                ))}
              </div>
            </div>
          )}

          <div className="flex justify-end space-x-3 pt-4">
            <button
              type="button"
              onClick={() => {
                setIsCreateModalOpen(false);
                resetForm();
              }}
              className="btn-secondary"
            >
              Cancel
            </button>
            <button
              type="submit"
              disabled={loading}
              className="btn-primary"
            >
              {loading ? <LoadingSpinner size="sm" /> : 'Create Event'}
            </button>
          </div>
        </form>
      </Modal>

      {/* Edit Event Modal */}
      <Modal
        isOpen={isEditModalOpen}
        onClose={() => {
          setIsEditModalOpen(false);
          resetForm();
        }}
        title="Edit Calendar Event"
        size="lg"
      >
        {selectedEvent && (
          <form onSubmit={handleUpdateEvent} className="space-y-4">
            <div className="grid grid-cols-2 gap-4">
              <div>
                <label className="block text-sm font-medium text-gray-700">Date</label>
                <input
                  type="date"
                  value={formData.event_date}
                  onChange={(e) => setFormData(prev => ({ ...prev, event_date: e.target.value }))}
                  className="input-field"
                  required
                />
              </div>
              <div>
                <label className="block text-sm font-medium text-gray-700">Type</label>
                <select
                  value={formData.type}
                  onChange={(e) => setFormData(prev => ({ ...prev, type: e.target.value }))}
                  className="input-field"
                  required
                >
                  <option value="available">Available</option>
                  <option value="unavailable">Unavailable</option>
                  <option value="holiday">Holiday</option>
                </select>
              </div>
            </div>

            <div className="grid grid-cols-2 gap-4">
              <div>
                <label className="block text-sm font-medium text-gray-700">Start Time (Optional)</label>
                <input
                  type="time"
                  value={formData.start_time}
                  onChange={(e) => setFormData(prev => ({ ...prev, start_time: e.target.value }))}
                  className="input-field"
                />
              </div>
              <div>
                <label className="block text-sm font-medium text-gray-700">End Time (Optional)</label>
                <input
                  type="time"
                  value={formData.end_time}
                  onChange={(e) => setFormData(prev => ({ ...prev, end_time: e.target.value }))}
                  className="input-field"
                />
              </div>
            </div>

            <div>
              <label className="block text-sm font-medium text-gray-700">Reason (Optional)</label>
              <input
                type="text"
                value={formData.reason}
                onChange={(e) => setFormData(prev => ({ ...prev, reason: e.target.value }))}
                className="input-field"
                placeholder="e.g., Vacation, Maintenance, etc."
              />
            </div>

            <div>
              <label className="flex items-center">
                <input
                  type="checkbox"
                  checked={formData.is_recurring}
                  onChange={(e) => setFormData(prev => ({ ...prev, is_recurring: e.target.checked }))}
                  className="rounded border-gray-300 text-black focus:ring-black"
                />
                <span className="ml-2 text-sm text-gray-700">Recurring Event</span>
              </label>
            </div>

            {formData.is_recurring && (
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-2">Recurring Days</label>
                <div className="grid grid-cols-2 sm:grid-cols-4 gap-2">
                  {daysOfWeek.map((day) => (
                    <label key={day.value} className="flex items-center">
                      <input
                        type="checkbox"
                        checked={formData.recurring_days.includes(day.value)}
                        onChange={() => toggleRecurringDay(day.value)}
                        className="rounded border-gray-300 text-black focus:ring-black"
                      />
                      <span className="ml-2 text-sm text-gray-700">{day.label}</span>
                    </label>
                  ))}
                </div>
              </div>
            )}

            <div className="flex justify-end space-x-3 pt-4">
              <button
                type="button"
                onClick={() => {
                  setIsEditModalOpen(false);
                  resetForm();
                }}
                className="btn-secondary"
              >
                Cancel
              </button>
              <button
                type="submit"
                disabled={loading}
                className="btn-primary"
              >
                {loading ? <LoadingSpinner size="sm" /> : 'Update Event'}
              </button>
            </div>
          </form>
        )}
      </Modal>
    </div>
  );
};

export default CalendarManagement;