import { useState, useEffect, useMemo } from 'react';
import { useAuth } from '../context/AuthContext';
import { useApi } from '../hooks/useApi';
import Modal from '../components/Modal';
import LoadingSpinner from '../components/LoadingSpinner';
import { 
  CalendarIcon, 
  ClockIcon, 
  CheckCircleIcon,
  XCircleIcon,
  ExclamationTriangleIcon,
  FunnelIcon,
  ChevronLeftIcon,
  ChevronRightIcon
} from '@heroicons/react/24/outline';

const ClientAppointments = () => {
  const { user } = useAuth();
  const { callApi, loading } = useApi();
  const [appointments, setAppointments] = useState([]);
  const [isBookModalOpen, setIsBookModalOpen] = useState(false);
  const [availableSlots, setAvailableSlots] = useState([]);
  const [selectedDate, setSelectedDate] = useState('');
  const [statusFilter, setStatusFilter] = useState('all');
  const [sortBy, setSortBy] = useState('date');
  const [currentPage, setCurrentPage] = useState(1);
  const appointmentsPerPage = 8;
  const [formData, setFormData] = useState({
    appointment_date: '',
    appointment_time: '',
    purpose: '',
    notes: ''
  });

  useEffect(() => {
    loadAppointments();
  }, []);

  const loadAppointments = async () => {
    const result = await callApi((signal) =>
      // use axios to leverage centralized settings (withCredentials) and consistent error handling
      axios.get('/api/appointments', { signal })
    );
    if (result.success) {
      setAppointments(result.data.data || result.data);
      setCurrentPage(1);
    }
  };

  // Filter appointments
  const filteredAppointments = useMemo(() => {
    let filtered = appointments;

    if (statusFilter !== 'all') {
      filtered = filtered.filter(apt => apt.status === statusFilter);
    }

    return filtered;
  }, [appointments, statusFilter]);

  // Sort appointments
  const sortedAppointments = useMemo(() => {
    const sorted = [...filteredAppointments];
    
    switch (sortBy) {
      case 'date':
        return sorted.sort((a, b) => new Date(a.appointment_date) - new Date(b.appointment_date));
      case 'date-desc':
        return sorted.sort((a, b) => new Date(b.appointment_date) - new Date(a.appointment_date));
      case 'status':
        return sorted.sort((a, b) => a.status.localeCompare(b.status));
      default:
        return sorted;
    }
  }, [filteredAppointments, sortBy]);

  // Paginate appointments
  const totalPages = Math.ceil(sortedAppointments.length / appointmentsPerPage);
  const startIdx = (currentPage - 1) * appointmentsPerPage;
  const paginatedAppointments = sortedAppointments.slice(startIdx, startIdx + appointmentsPerPage);

  const loadAvailableSlots = async (date) => {
    const result = await callApi((signal) =>
      axios.get(`/api/calendar/available-slots`, { params: { date }, signal })
    );
    if (result.success) {
      setAvailableSlots(result.data.available_slots || []);
    }
  };

  const handleDateChange = (date) => {
    setSelectedDate(date);
    setFormData(prev => ({ ...prev, appointment_date: date }));
    loadAvailableSlots(date);
  };

  const handleBookAppointment = async (e) => {
    e.preventDefault();
    const result = await callApi((signal) =>
      axios.post('/api/appointments', formData, { signal })
    );

    if (result.success) {
      setIsBookModalOpen(false);
      setFormData({
        appointment_date: '',
        appointment_time: '',
        purpose: '',
        notes: ''
      });
      loadAppointments();
      alert('Appointment booked successfully!');
    }
  };

  const getStatusIcon = (status) => {
    switch (status) {
      case 'completed':
        return <CheckCircleIcon className="h-5 w-5 text-green-500" />;
      case 'approved':
        return <CheckCircleIcon className="h-5 w-5 text-blue-500" />;
      case 'declined':
        return <XCircleIcon className="h-5 w-5 text-red-500" />;
      case 'cancelled':
        return <XCircleIcon className="h-5 w-5 text-gray-500" />;
      default:
        return <ExclamationTriangleIcon className="h-5 w-5 text-yellow-500" />;
    }
  };

  const getStatusColor = (status) => {
    switch (status) {
      case 'completed': return 'bg-green-100 text-green-800';
      case 'approved': return 'bg-blue-100 text-blue-800';
      case 'declined': return 'bg-red-100 text-red-800';
      case 'cancelled': return 'bg-gray-100 text-gray-800';
      default: return 'bg-yellow-100 text-yellow-800';
    }
  };

  return (
    <div className="min-h-screen bg-gray-50">
      {/* Header */}
      <header className="bg-white shadow-sm border-b">
        <div className="container mx-auto px-4 py-4">
          <div className="flex justify-between items-center">
            <div>
              <h1 className="text-2xl font-bold text-gray-900">My Appointments</h1>
              <p className="text-gray-600">Manage your notarization appointments</p>
            </div>
            <button
              onClick={() => setIsBookModalOpen(true)}
              className="btn-primary"
            >
              Book New Appointment
            </button>
          </div>
        </div>
      </header>

      {/* Main Content */}
      <main className="container mx-auto px-4 py-8">
        <div className="bg-white rounded-lg shadow-sm border">
          {loading ? (
            <div className="flex justify-center p-8">
              <LoadingSpinner size="lg" />
            </div>
          ) : appointments.length === 0 ? (
            <div className="text-center py-12">
              <CalendarIcon className="h-12 w-12 text-gray-400 mx-auto mb-4" />
              <h3 className="text-lg font-semibold text-gray-900">No appointments yet</h3>
              <p className="text-gray-600 mt-2">Book your first appointment to get started.</p>
            </div>
          ) : (
            <>
              {/* Filter and Sort Controls */}
              <div className="border-b p-4 space-y-3">
                <div className="flex items-center gap-2 mb-2">
                  <FunnelIcon className="h-4 w-4 text-gray-600" />
                  <span className="text-sm font-medium text-gray-700">Filters & Sort</span>
                </div>
                <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
                  <div>
                    <label className="block text-xs font-medium text-gray-600 mb-1">Status</label>
                    <select
                      value={statusFilter}
                      onChange={(e) => {
                        setStatusFilter(e.target.value);
                        setCurrentPage(1);
                      }}
                      className="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-black"
                    >
                      <option value="all">All Status</option>
                      <option value="pending">Pending</option>
                      <option value="approved">Approved</option>
                      <option value="completed">Completed</option>
                      <option value="declined">Declined</option>
                      <option value="cancelled">Cancelled</option>
                    </select>
                  </div>
                  <div>
                    <label className="block text-xs font-medium text-gray-600 mb-1">Sort By</label>
                    <select
                      value={sortBy}
                      onChange={(e) => {
                        setSortBy(e.target.value);
                        setCurrentPage(1);
                      }}
                      className="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-black"
                    >
                      <option value="date">Date (Oldest First)</option>
                      <option value="date-desc">Date (Newest First)</option>
                      <option value="status">Status</option>
                    </select>
                  </div>
                  <div>
                    <p className="text-xs font-medium text-gray-600 mb-1">Results</p>
                    <p className="text-sm text-gray-700">{sortedAppointments.length} appointment{sortedAppointments.length !== 1 ? 's' : ''}</p>
                  </div>
                </div>
              </div>

              {/* Appointments List */}
              <div className="divide-y">
                {paginatedAppointments.length === 0 ? (
                  <div className="text-center py-8">
                    <p className="text-gray-500">No appointments match your filters</p>
                  </div>
                ) : (
                  paginatedAppointments.map((appointment) => (
                    <div key={appointment.id} className="p-6 hover:bg-gray-50">
                      <div className="flex items-center justify-between">
                        <div className="flex items-center space-x-4">
                          {getStatusIcon(appointment.status)}
                          <div>
                            <h4 className="font-semibold text-gray-900">
                              {new Date(appointment.appointment_date).toLocaleDateString()} at {appointment.appointment_time}
                            </h4>
                            <p className="text-sm text-gray-600">{appointment.purpose}</p>
                            {appointment.staff_notes && (
                              <p className="text-sm text-gray-500 mt-1">
                                Staff notes: {appointment.staff_notes}
                              </p>
                            )}
                          </div>
                        </div>
                        <div className="flex items-center space-x-4">
                          <span className={`px-3 py-1 rounded-full text-xs font-medium ${getStatusColor(appointment.status)}`}>
                            {appointment.status}
                          </span>
                          {appointment.staff && (
                            <span className="text-sm text-gray-600">
                              Staff: {appointment.staff.first_name} {appointment.staff.last_name}
                            </span>
                          )}
                        </div>
                      </div>
                    </div>
                  ))
                )}
              </div>

              {/* Pagination Controls */}
              {totalPages > 1 && (
                <div className="border-t p-4 flex items-center justify-between">
                  <div className="text-sm text-gray-600">
                    Showing {startIdx + 1}-{Math.min(startIdx + appointmentsPerPage, sortedAppointments.length)} of {sortedAppointments.length}
                  </div>
                  <div className="flex items-center gap-2">
                    <button
                      onClick={() => setCurrentPage(Math.max(1, currentPage - 1))}
                      disabled={currentPage === 1}
                      className="p-2 border border-gray-300 rounded hover:bg-gray-100 disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                      <ChevronLeftIcon className="h-4 w-4" />
                    </button>
                    <span className="text-sm text-gray-600">
                      Page {currentPage} of {totalPages}
                    </span>
                    <button
                      onClick={() => setCurrentPage(Math.min(totalPages, currentPage + 1))}
                      disabled={currentPage === totalPages}
                      className="p-2 border border-gray-300 rounded hover:bg-gray-100 disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                      <ChevronRightIcon className="h-4 w-4" />
                    </button>
                  </div>
                </div>
              )}
            </>
          )}
        </div>
      </main>

      {/* Book Appointment Modal */}
      <Modal
        isOpen={isBookModalOpen}
        onClose={() => setIsBookModalOpen(false)}
        title="Book New Appointment"
        size="lg"
      >
        <form onSubmit={handleBookAppointment} className="space-y-4">
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">
              Appointment Date
            </label>
            <input
              type="date"
              min={new Date().toISOString().split('T')[0]}
              value={selectedDate}
              onChange={(e) => handleDateChange(e.target.value)}
              className="input-field"
              required
            />
          </div>

          {selectedDate && (
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">
                Available Time Slots
              </label>
              {availableSlots.length === 0 ? (
                <p className="text-sm text-gray-500">No available slots for this date</p>
              ) : (
                <div className="grid grid-cols-3 gap-2">
                  {availableSlots.map((slot) => (
                    <button
                      key={slot}
                      type="button"
                      onClick={() => setFormData(prev => ({ ...prev, appointment_time: slot }))}
                      className={`p-2 text-sm border rounded ${
                        formData.appointment_time === slot
                          ? 'bg-black text-white border-black'
                          : 'bg-white text-gray-700 border-gray-300 hover:border-black'
                      }`}
                    >
                      {slot}
                    </button>
                  ))}
                </div>
              )}
            </div>
          )}

          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">
              Purpose
            </label>
            <textarea
              value={formData.purpose}
              onChange={(e) => setFormData(prev => ({ ...prev, purpose: e.target.value }))}
              className="input-field"
              rows="3"
              required
              placeholder="Describe the purpose of your notarization..."
            />
          </div>

          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">
              Additional Notes (Optional)
            </label>
            <textarea
              value={formData.notes}
              onChange={(e) => setFormData(prev => ({ ...prev, notes: e.target.value }))}
              className="input-field"
              rows="2"
              placeholder="Any additional information..."
            />
          </div>

          <div className="flex justify-end space-x-3 pt-4">
            <button
              type="button"
              onClick={() => setIsBookModalOpen(false)}
              className="btn-secondary"
            >
              Cancel
            </button>
            <button
              type="submit"
              disabled={loading || !formData.appointment_time}
              className="btn-primary"
            >
              {loading ? <LoadingSpinner size="sm" /> : 'Book Appointment'}
            </button>
          </div>
        </form>
      </Modal>
    </div>
  );
};

export default ClientAppointments;