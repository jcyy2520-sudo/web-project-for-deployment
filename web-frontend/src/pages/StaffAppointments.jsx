import { useState, useEffect, useMemo, useCallback, useRef } from 'react';
import axios from 'axios';
import { useAuth } from '../context/AuthContext';
import { useApi } from '../hooks/useApi';
import Modal from '../components/Modal';
import LoadingSpinner from '../components/LoadingSpinner';
import { formatServiceName, formatDateDisplay } from '../utils/format';
import { 
  CheckCircleIcon,
  XCircleIcon,
  ClockIcon,
  UserIcon,
  ChevronLeftIcon,
  ChevronRightIcon,
  FunnelIcon
} from '@heroicons/react/24/outline';

const StaffAppointments = () => {
  const { user } = useAuth();
  const { callApi, loading } = useApi();
  const [appointments, setAppointments] = useState([]);
  const [selectedAppointment, setSelectedAppointment] = useState(null);
  const [isDetailsModalOpen, setIsDetailsModalOpen] = useState(false);
  const [statusFilter, setStatusFilter] = useState('all');
  const [sortBy, setSortBy] = useState('date');
  const [currentPage, setCurrentPage] = useState(1);
  const appointmentsPerPage = 8;

  const loadInFlightRef = useRef(false);

  const loadAppointments = useCallback(async () => {
    if (loadInFlightRef.current) return;
    loadInFlightRef.current = true;

    const url = statusFilter === 'all'
      ? '/api/appointments'
      : `/api/appointments?status=${statusFilter}`;

    try {
      const result = await callApi((signal) => axios.get(url, { signal }), {
        abortPrevious: false,
      });

      if (result.success) {
        setAppointments(result.data.data || result.data);
        setCurrentPage(1);
      }
    } finally {
      loadInFlightRef.current = false;
    }
  }, [callApi, statusFilter]);

  useEffect(() => {
    loadAppointments();
  }, [loadAppointments]);

  // Poll for appointment changes as a fallback when real-time is not configured
  useEffect(() => {
    const connectionState = window?.Echo?.connector?.pusher?.connection?.state;
    const POLL_INTERVAL_MS = connectionState === 'connected' ? 30000 : 10000;
    const id = setInterval(() => {
      if (typeof document !== 'undefined' && document.hidden) {
        return;
      }
      loadAppointments();
    }, POLL_INTERVAL_MS);

    const handleLogout = () => clearInterval(id);
    window.addEventListener('auth:logout', handleLogout);

    const handleVisibilityChange = () => {
      if (!document.hidden) {
        loadAppointments();
      }
    };
    document.addEventListener('visibilitychange', handleVisibilityChange);

    return () => {
      clearInterval(id);
      window.removeEventListener('auth:logout', handleLogout);
      document.removeEventListener('visibilitychange', handleVisibilityChange);
    };
  }, [loadAppointments]);

  // Real-time subscription via Laravel Echo (if configured)
  useEffect(() => {
    if (!window?.Echo || typeof window.Echo.channel !== 'function') {
      return;
    }

    const channel = window.Echo.channel('appointments');

    const handler = (payload) => {
      // payload may contain `appointment` or `data` depending on broadcast structure
      try {
        // If appointment in payload, reload appointments to ensure consistent state
        if (payload && (payload.appointment || payload.data || payload)) {
          loadAppointments();
        }
      } catch (e) {
        console.debug('Realtime appointment handler error', e);
      }
    };

    try {
      channel.listen('AppointmentUpdated', handler);
      channel.listen('AppointmentCreated', handler);
    } catch (e) {
      // Some Echo wrappers expose .listen differently; attempt to bind via _pusher
      try {
        if (channel._pusher) {
          channel._pusher.bind('AppointmentUpdated', handler);
          channel._pusher.bind('AppointmentCreated', handler);
        }
      } catch (err) {
        console.debug('Failed to attach realtime appointment listeners', err);
      }
    }

    return () => {
      try { channel.stopListening('AppointmentUpdated'); } catch (e) {}
      try { channel.stopListening('AppointmentCreated'); } catch (e) {}
      try { if (channel._pusher) { channel._pusher.unbind('AppointmentUpdated'); channel._pusher.unbind('AppointmentCreated'); } } catch (e) {}
    };
  }, [loadAppointments]);

  // Sort appointments
  const sortedAppointments = useMemo(() => {
    const sorted = [...appointments];
    
    switch (sortBy) {
      case 'date':
        return sorted.sort((a, b) => new Date(a.appointment_date) - new Date(b.appointment_date));
      case 'date-desc':
        return sorted.sort((a, b) => new Date(b.appointment_date) - new Date(a.appointment_date));
      case 'status':
        return sorted.sort((a, b) => a.status.localeCompare(b.status));
      case 'client':
        return sorted.sort((a, b) => 
          `${a.user?.first_name} ${a.user?.last_name}`.localeCompare(`${b.user?.first_name} ${b.user?.last_name}`)
        );
      default:
        return sorted;
    }
  }, [appointments, sortBy]);

  // Paginate appointments
  const totalPages = Math.ceil(sortedAppointments.length / appointmentsPerPage);
  const startIdx = (currentPage - 1) * appointmentsPerPage;
  const paginatedAppointments = sortedAppointments.slice(startIdx, startIdx + appointmentsPerPage);

  const handleStatusUpdate = async (appointmentId, newStatus, staffNotes = '') => {
    const result = await callApi((signal) =>
      axios.put(`/api/appointments/${appointmentId}/status`, { status: newStatus, staff_notes: staffNotes }, { signal })
    );

    if (result.success) {
      loadAppointments();
      setIsDetailsModalOpen(false);
      window.showToast?.('Success', `Appointment ${newStatus} successfully!`, 'success');
    }
  };

  const viewAppointmentDetails = (appointment) => {
    setSelectedAppointment(appointment);
    setIsDetailsModalOpen(true);
  };

  const getStatusColor = (status) => {
    switch (status) {
      case 'completed': return 'bg-green-100 text-green-800 dark:bg-green-500/20 dark:text-green-300';
      case 'approved': return 'bg-blue-100 text-blue-800 dark:bg-blue-500/20 dark:text-blue-300';
      case 'declined': return 'bg-red-100 text-red-800 dark:bg-red-500/20 dark:text-red-300';
      case 'cancelled': return 'bg-slate-300 text-slate-900 dark:bg-slate-500/30 dark:text-slate-200';
      default: return 'bg-yellow-100 text-yellow-800 dark:bg-yellow-500/20 dark:text-yellow-300';
    }
  };

  const getStatusActions = (appointment) => {
    switch (appointment.status) {
      case 'pending':
        return (
          <div className="flex space-x-2">
            <button
              onClick={() => handleStatusUpdate(appointment.id, 'approved')}
              className="btn-primary text-sm"
            >
              Approve
            </button>
            <button
              onClick={() => handleStatusUpdate(appointment.id, 'declined')}
              className="btn-secondary text-sm bg-red-500 text-white hover:bg-red-600"
            >
              Decline
            </button>
          </div>
        );
      case 'approved':
        return (
          <button
            onClick={() => handleStatusUpdate(appointment.id, 'completed')}
            className="btn-primary text-sm"
          >
            Mark Completed
          </button>
        );
      default:
        return null;
    }
  };

  return (
    <div className="min-h-screen bg-gray-50">
      {/* Header */}
      <header className="bg-white shadow-sm border-b">
        <div className="container mx-auto px-4 py-4">
          <div className="flex justify-between items-center">
            <div>
              <h1 className="text-2xl font-bold text-gray-900">Appointment Management</h1>
              <p className="text-gray-600">Manage client appointments</p>
            </div>
            <div className="flex items-center space-x-4">
              <select
                value={statusFilter}
                onChange={(e) => {
                  setStatusFilter(e.target.value);
                  setCurrentPage(1);
                }}
                className="input-field"
              >
                <option value="all">All Status</option>
                <option value="pending">Pending</option>
                <option value="approved">Approved</option>
                <option value="completed">Completed</option>
                <option value="declined">Declined</option>
              </select>
            </div>
          </div>
        </div>
      </header>

      {/* Main Content */}
      <main className="container mx-auto px-4 py-8">
        <div className="bg-white rounded-lg shadow-sm border">
          {appointments.length === 0 ? (
            <div className="text-center py-12">
              <ClockIcon className="h-12 w-12 text-gray-400 mx-auto mb-4" />
              <h3 className="text-lg font-semibold text-gray-900">No appointments found</h3>
              <p className="text-gray-600 mt-2">No appointments match your current filter.</p>
            </div>
          ) : (
            <>
              {/* Sort Controls */}
              <div className="border-b p-4">
                <div className="flex items-center gap-2 mb-2">
                  <FunnelIcon className="h-4 w-4 text-gray-600" />
                  <span className="text-sm font-medium text-gray-700">Sort</span>
                </div>
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
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
                      <option value="client">Client Name</option>
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
                    <p className="text-gray-500">No appointments to display</p>
                  </div>
                ) : (
                  paginatedAppointments.map((appointment) => (
                    <div key={appointment.id} className="p-6 hover:bg-gray-50">
                      <div className="flex items-center justify-between">
                        <div className="flex items-center space-x-4">
                          <div className="flex-shrink-0">
                            <UserIcon className="h-8 w-8 text-gray-400" />
                          </div>
                          <div>
                            <h4 className="font-semibold text-gray-900">
                              {appointment.user?.first_name} {appointment.user?.last_name}
                            </h4>
                            <p className="text-sm text-gray-600">
                              {formatDateDisplay(appointment.appointment_date)} at {appointment.appointment_time}
                            </p>
                            <p className="text-sm text-gray-500">
                              {formatServiceName(appointment)}
                              {appointment.service?.price && (
                                <span className="text-sm text-gray-700 font-medium"> &nbsp;— &nbsp;₱{parseFloat(appointment.service.price).toFixed(2)}</span>
                              )}
                            </p>
                          </div>
                        </div>
                        
                        <div className="flex items-center space-x-4">
                          <span className={`px-3 py-1 rounded-full text-xs font-medium ${getStatusColor(appointment.status)}`}>
                            {appointment.status}
                          </span>
                          
                          {getStatusActions(appointment)}
                          
                          <button
                            onClick={() => viewAppointmentDetails(appointment)}
                            className="btn-secondary text-sm"
                          >
                            View Details
                          </button>
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

      {/* Appointment Details Modal */}
      <Modal
        isOpen={isDetailsModalOpen}
        onClose={() => setIsDetailsModalOpen(false)}
        title="Appointment Details"
        size="lg"
      >
        {selectedAppointment && (
          <div className="space-y-4">
            <div className="grid grid-cols-2 gap-4">
              <div>
                <label className="block text-sm font-medium text-gray-700">Client</label>
                <p className="mt-1 text-sm text-gray-900">
                  {selectedAppointment.user?.first_name} {selectedAppointment.user?.last_name}
                </p>
              </div>
              <div>
                <label className="block text-sm font-medium text-gray-700">Email</label>
                <p className="mt-1 text-sm text-gray-900">{selectedAppointment.user?.email}</p>
              </div>
            </div>

            <div className="grid grid-cols-2 gap-4">
              <div>
                <label className="block text-sm font-medium text-gray-700">Date & Time</label>
                <p className="mt-1 text-sm text-gray-900">
                  {formatDateDisplay(selectedAppointment.appointment_date)} at {selectedAppointment.appointment_time}
                </p>
              </div>
              <div>
                <label className="block text-sm font-medium text-gray-700">Status</label>
                <span className={`mt-1 px-2 py-1 rounded-full text-xs font-medium ${getStatusColor(selectedAppointment.status)}`}>
                  {selectedAppointment.status}
                </span>
              </div>
            </div>

            <div>
              <label className="block text-sm font-medium text-gray-700">Purpose</label>
              <p className="mt-1 text-sm text-gray-900">{selectedAppointment.purpose}</p>
            </div>

            {selectedAppointment.notes && (
              <div>
                <label className="block text-sm font-medium text-gray-700">Client Notes</label>
                <p className="mt-1 text-sm text-gray-900">{selectedAppointment.notes}</p>
              </div>
            )}

            {selectedAppointment.staff_notes && (
              <div>
                <label className="block text-sm font-medium text-gray-700">Internal Notes</label>
                <p className="mt-1 text-sm text-gray-900">{selectedAppointment.staff_notes}</p>
              </div>
            )}

            <div className="flex justify-end space-x-3 pt-4 border-t">
              {getStatusActions(selectedAppointment)}
              <button
                onClick={() => setIsDetailsModalOpen(false)}
                className="btn-secondary"
              >
                Close
              </button>
            </div>
          </div>
        )}
      </Modal>
    </div>
  );
};

export default StaffAppointments;