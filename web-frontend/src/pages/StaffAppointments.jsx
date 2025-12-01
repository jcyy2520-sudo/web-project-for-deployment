import { useState, useEffect, useMemo } from 'react';
import { useAuth } from '../context/AuthContext';
import { useApi } from '../hooks/useApi';
import Modal from '../components/Modal';
import LoadingSpinner from '../components/LoadingSpinner';
import { formatServiceName } from '../utils/format';
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

  useEffect(() => {
    loadAppointments();
  }, [statusFilter]);

  const loadAppointments = async () => {
    const url = statusFilter === 'all' 
      ? '/api/appointments'
      : `/api/appointments?status=${statusFilter}`;
    
    const result = await callApi((signal) => axios.get(url, { signal }));
    if (result.success) {
      setAppointments(result.data.data || result.data);
      setCurrentPage(1);
    }
  };

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
      alert(`Appointment ${newStatus} successfully!`);
    }
  };

  const viewAppointmentDetails = (appointment) => {
    setSelectedAppointment(appointment);
    setIsDetailsModalOpen(true);
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
          {loading ? (
            <div className="flex justify-center p-8">
              <LoadingSpinner size="lg" />
            </div>
          ) : appointments.length === 0 ? (
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
                              {new Date(appointment.appointment_date).toLocaleDateString()} at {appointment.appointment_time}
                            </p>
                            <p className="text-sm text-gray-500">
                              {formatServiceName(appointment)}
                              {appointment.service?.price && (
                                <span className="text-sm text-gray-700 font-medium"> &nbsp;— &nbsp;${parseFloat(appointment.service.price).toFixed(2)}</span>
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
                  {new Date(selectedAppointment.appointment_date).toLocaleDateString()} at {selectedAppointment.appointment_time}
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