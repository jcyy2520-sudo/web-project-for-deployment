import { useState, useEffect, useMemo } from 'react';
import { useAuth } from '../context/AuthContext';
import { useApi } from '../hooks/useApi';
import Modal from '../components/Modal';
import LoadingSpinner from '../components/LoadingSpinner';
import TimeSlotRecommendations from '../components/TimeSlotRecommendations';
import AppointmentRiskAssessment from '../components/AppointmentRiskAssessment';
import UnavailableDatesViewer from '../components/UnavailableDatesViewer';
import BookingDecisionSupport from '../components/BookingDecisionSupport';
import UnavailabilityMessage from '../components/UnavailabilityMessage';
import { 
  CalendarIcon, 
  ClockIcon, 
  CheckCircleIcon,
  XCircleIcon,
  ExclamationTriangleIcon,
  FunnelIcon,
  ChevronLeftIcon,
  ChevronRightIcon,
  TrashIcon,
  InformationCircleIcon,
  SparklesIcon
} from '@heroicons/react/24/outline';
import axios from 'axios';

const ClientAppointments = () => {
  const { user } = useAuth();
  const { callApi, loading } = useApi();
  const [appointments, setAppointments] = useState([]);
  const [isBookModalOpen, setIsBookModalOpen] = useState(false);
  const [isCancelModalOpen, setIsCancelModalOpen] = useState(false);
  const [selectedAppointmentToCancel, setSelectedAppointmentToCancel] = useState(null);
  const [availableSlots, setAvailableSlots] = useState([]);
  const [selectedDate, setSelectedDate] = useState('');
  const [selectedTime, setSelectedTime] = useState('');
  const [unavailableDates, setUnavailableDates] = useState([]);
  const [slotCapacities, setSlotCapacities] = useState({});
  const [statusFilter, setStatusFilter] = useState('all');
  const [sortBy, setSortBy] = useState('newest');
  const [currentPage, setCurrentPage] = useState(1);
  const appointmentsPerPage = 8;
  const [slotUnavailabilityReason, setSlotUnavailabilityReason] = useState(null);
  const [slotAlternatives, setSlotAlternatives] = useState([]);
  const [formData, setFormData] = useState({
    appointment_date: '',
    appointment_time: '',
    notes: ''
  });
  const [calendarMonth, setCalendarMonth] = useState(new Date());

  useEffect(() => {
    loadAppointments();
    loadUnavailableDates();
  }, []);

  useEffect(() => {
    const handler = () => loadUnavailableDates();
    window.addEventListener('unavailableDatesChanged', handler);
    return () => window.removeEventListener('unavailableDatesChanged', handler);
  }, []);

  // Reload available slots when admin updates slot capacities
  useEffect(() => {
    const onSlotCapacitiesChanged = () => {
      if (selectedDate) {
        console.log('Slot capacities changed, reloading available slots for', selectedDate);
        loadAvailableSlots(selectedDate);
      }
    };

    window.addEventListener('slotCapacitiesChanged', onSlotCapacitiesChanged);
    return () => window.removeEventListener('slotCapacitiesChanged', onSlotCapacitiesChanged);
  }, [selectedDate]);

  const loadAppointments = async () => {
    const result = await callApi((signal) =>
      axios.get('/api/appointments', { signal })
    );
    if (result.success) {
      setAppointments(result.data.data || result.data);
      setCurrentPage(1);
    }
  };

  const loadUnavailableDates = async () => {
    const result = await callApi((signal) =>
      axios.get('/api/unavailable-dates', { signal })
    , { skipCache: true, cache: false });
    if (result.success) {
      const dates = result.data.data || result.data;
      console.log('Loaded unavailable dates:', dates);
      setUnavailableDates(dates);
    } else {
      console.error('Failed to load unavailable dates:', result);
    }
  };

  const isDateUnavailable = (date) => {
    const dateStr = date.toISOString().split('T')[0];
    const dayOfWeek = date.getDay();
    
    // Check for weekend
    if (dayOfWeek === 0 || dayOfWeek === 6) {
      return true;
    }

    // Check against unavailable dates - handle both formats
    return unavailableDates.some(u => {
      if (!u.date) return false;
      // Convert to YYYY-MM-DD format for comparison
      const uDate = typeof u.date === 'string' ? u.date.split('T')[0] : u.date;
      return uDate === dateStr;
    });
  };

  const getUnavailableReason = (date) => {
    const dateStr = date.toISOString().split('T')[0];
    const dayOfWeek = date.getDay();

    if (dayOfWeek === 0 || dayOfWeek === 6) {
      return 'Weekend - Closed';
    }

    const matching = unavailableDates.find(u => {
      if (!u.date) return false;
      const uDate = typeof u.date === 'string' ? u.date.split('T')[0] : u.date;
      return uDate === dateStr;
    });
    return matching?.reason || 'Not available';
  };

  const isTimeSlotAvailable = (time) => {
    if (!time) return false;
    
    // Parse time (format: HH:mm)
    const [hours, minutes] = time.split(':').map(Number);
    
    // Check working hours (8 AM to 5 PM, exclusive of 5 PM)
    if (hours < 8 || hours >= 17) {
      return false;
    }

    // Check lunch break (12 PM to 1 PM)
    if (hours === 12) {
      return false;
    }

    return true;
  };

  const getTimeSlotInfo = (time) => {
    if (!isTimeSlotAvailable(time)) {
      const [hours] = time.split(':').map(Number);
      if (hours === 12) return 'Lunch break (12-1 PM)';
      if (hours < 8 || hours >= 17) return 'Outside working hours (8 AM-5 PM)';
    }
    return null;
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
      case 'newest':
        return sorted.sort((a, b) => new Date(b.created_at) - new Date(a.created_at));
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
      setAvailableSlots(result.data.available_slots || result.data.data || []);
    }
  };

  const handleDateChange = (date) => {
    setSelectedDate(date);
    setFormData(prev => ({ ...prev, appointment_date: date }));
    loadAvailableSlots(date);
    // Clear any prior alternatives when changing date
    setSlotAlternatives([]);
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
        notes: ''
      });
      loadAppointments();
      alert('Appointment booked successfully!');
    }
  };

  const handleCancelAppointment = async () => {
    if (!selectedAppointmentToCancel) return;

    const result = await callApi((signal) =>
      axios.put(`/api/appointments/${selectedAppointmentToCancel.id}/cancel`, {}, { signal })
    );

    if (result.success) {
      setIsCancelModalOpen(false);
      setSelectedAppointmentToCancel(null);
      await loadAppointments();
      alert('Appointment cancelled successfully!');
    }
  };

  const openCancelModal = (appointment) => {
    setSelectedAppointmentToCancel(appointment);
    setIsCancelModalOpen(true);
  };  const getStatusIcon = (status) => {
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
        {/* Unavailable Dates Viewer */}
        <div className="mb-8">
          <UnavailableDatesViewer isDarkMode={false} />
        </div>

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
                      <option value="newest">Newest First</option>
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
                      <div className="flex items-center justify-between mb-3">
                        <div className="flex items-center space-x-4">
                          {getStatusIcon(appointment.status)}
                          <div>
                            <h4 className="font-semibold text-gray-900">
                              {new Date(appointment.appointment_date).toLocaleDateString()} at {appointment.appointment_time}
                            </h4>
                            {appointment.staff_notes && (
                              <p className="text-sm text-gray-500 mt-1">
                                Notes: {appointment.staff_notes}
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
                              Assignee: {appointment.staff.first_name} {appointment.staff.last_name}
                            </span>
                          )}
                          {(appointment.status === 'pending' || appointment.status === 'approved') && (
                            <button
                              onClick={() => openCancelModal(appointment)}
                              className="text-red-500 hover:text-red-700 transition-colors duration-200 p-1 rounded hover:bg-red-100"
                              title="Cancel appointment"
                            >
                              <TrashIcon className="h-4 w-4" />
                            </button>
                          )}
                        </div>
                      </div>
                      
                      {/* Risk Assessment for pending/approved appointments */}
                      {(appointment.status === 'pending' || appointment.status === 'approved') && (
                        <div className="mt-3 pt-3 border-t">
                          <AppointmentRiskAssessment 
                            appointmentId={appointment.id}
                            isDarkMode={false}
                          />
                        </div>
                      )}
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

      {/* Cancel Appointment Modal */}
      <Modal
        isOpen={isCancelModalOpen}
        onClose={() => {
          setIsCancelModalOpen(false);
          setSelectedAppointmentToCancel(null);
        }}
        title="Cancel Appointment"
        size="md"
      >
        {selectedAppointmentToCancel && (
          <div className="space-y-4">
            <div className="bg-red-50 border border-red-200 rounded p-4">
              <p className="text-sm text-red-800">
                <strong>Warning:</strong> Are you sure you want to cancel this appointment?
              </p>
            </div>

            <div className="bg-gray-50 rounded p-4 space-y-2">
              <div className="text-sm">
                <strong>Date:</strong> {new Date(selectedAppointmentToCancel.appointment_date).toLocaleDateString()} at {selectedAppointmentToCancel.appointment_time}
              </div>
              {selectedAppointmentToCancel.staff && (
                <div className="text-sm">
                  <strong>Assignee:</strong> {selectedAppointmentToCancel.staff.first_name} {selectedAppointmentToCancel.staff.last_name}
                </div>
              )}
            </div>

            <p className="text-sm text-gray-600">
              A cancellation notification will be sent to your email, and the admin will be notified.
            </p>

            <div className="flex justify-end gap-3">
              <button
                type="button"
                onClick={() => {
                  setIsCancelModalOpen(false);
                  setSelectedAppointmentToCancel(null);
                }}
                className="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded hover:bg-gray-50"
              >
                Keep Appointment
              </button>
              <button
                type="button"
                onClick={handleCancelAppointment}
                disabled={loading}
                className="px-4 py-2 text-sm font-medium text-white bg-red-600 border border-red-600 rounded hover:bg-red-700 disabled:opacity-50 disabled:cursor-not-allowed"
              >
                {loading ? 'Cancelling...' : 'Cancel Appointment'}
              </button>
            </div>
          </div>
        )}
      </Modal>

      {/* Book Appointment Modal */}
      <Modal
        isOpen={isBookModalOpen}
        onClose={() => {
          setIsBookModalOpen(false);
          setSelectedDate('');
          setFormData({ appointment_date: '', appointment_time: '', notes: '' });
          setCalendarMonth(new Date());
        }}
        title="Book New Appointment"
        size="xl"
      >
        <form onSubmit={handleBookAppointment} className="space-y-4">
          {/* Calendar Picker */}
          <div className="space-y-3">
            <div className="flex items-center justify-between">
              <h3 className="text-sm font-semibold text-gray-700">Select Date</h3>
              <div className="flex gap-2">
                <button
                  type="button"
                  onClick={() => setCalendarMonth(new Date(calendarMonth.getFullYear(), calendarMonth.getMonth() - 1))}
                  className="p-1 border border-gray-300 rounded hover:bg-gray-100"
                >
                  <ChevronLeftIcon className="h-4 w-4" />
                </button>
                <span className="text-xs font-medium text-gray-700 min-w-[120px] text-center">
                  {calendarMonth.toLocaleDateString('en-US', { month: 'long', year: 'numeric' })}
                </span>
                <button
                  type="button"
                  onClick={() => setCalendarMonth(new Date(calendarMonth.getFullYear(), calendarMonth.getMonth() + 1))}
                  className="p-1 border border-gray-300 rounded hover:bg-gray-100"
                >
                  <ChevronRightIcon className="h-4 w-4" />
                </button>
              </div>
            </div>

            {/* Calendar Grid */}
            <div className="border rounded-lg p-4 bg-gray-50">
              {/* Day headers */}
              <div className="grid grid-cols-7 gap-2 mb-2">
                {['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'].map(day => (
                  <div key={day} className="text-center text-xs font-semibold text-gray-600 py-2">
                    {day}
                  </div>
                ))}
              </div>

              {/* Days */}
              <div className="grid grid-cols-7 gap-2">
                {(() => {
                  const firstDay = new Date(calendarMonth.getFullYear(), calendarMonth.getMonth(), 1).getDay();
                  const daysInMonth = new Date(calendarMonth.getFullYear(), calendarMonth.getMonth() + 1, 0).getDate();
                  const days = [];

                  // Empty cells
                  for (let i = 0; i < firstDay; i++) {
                    days.push(<div key={`empty-${i}`}></div>);
                  }

                  // Days
                  for (let day = 1; day <= daysInMonth; day++) {
                    const date = new Date(calendarMonth.getFullYear(), calendarMonth.getMonth(), day);
                    const dateStr = date.toISOString().split('T')[0];
                    const isToday = dateStr === new Date().toISOString().split('T')[0];
                    const isPast = date < new Date() && !isToday;
                    const isUnavail = isDateUnavailable(date);
                    const isSelected = selectedDate === dateStr;

                    days.push(
                      <button
                        key={day}
                        type="button"
                        onClick={() => !isPast && !isUnavail && handleDateChange(dateStr)}
                        disabled={isPast || isUnavail}
                        className={`p-2 text-sm font-medium rounded transition-all ${
                          isSelected
                            ? 'bg-black text-white'
                            : isPast
                            ? 'bg-gray-200 text-gray-400 cursor-not-allowed'
                            : isUnavail
                            ? 'bg-red-100 text-red-600 cursor-not-allowed'
                            : isToday
                            ? 'bg-blue-100 text-blue-700 border-2 border-blue-400'
                            : 'bg-white text-gray-700 border border-gray-300 hover:border-black'
                        }`}
                        title={isPast ? 'Past date' : isUnavail ? getUnavailableReason(date) : ''}
                      >
                        {day}
                      </button>
                    );
                  }

                  return days;
                })()}
              </div>

              {/* Legend */}
              <div className="mt-4 pt-3 border-t grid grid-cols-2 gap-2 text-xs">
                <div className="flex items-center gap-2">
                  <div className="w-3 h-3 bg-black rounded"></div>
                  <span>Selected</span>
                </div>
                <div className="flex items-center gap-2">
                  <div className="w-3 h-3 bg-red-100 border border-red-600 rounded"></div>
                  <span>Unavailable</span>
                </div>
                <div className="flex items-center gap-2">
                  <div className="w-3 h-3 bg-blue-100 border-2 border-blue-400 rounded"></div>
                  <span>Today</span>
                </div>
                <div className="flex items-center gap-2">
                  <div className="w-3 h-3 bg-gray-200 rounded"></div>
                  <span>Past</span>
                </div>
              </div>
            </div>
          </div>

          {/* Time Slot Selection */}
          {selectedDate && !isDateUnavailable(new Date(selectedDate + 'T00:00:00')) && (
            <div className="space-y-4">
              <h3 className="text-sm font-semibold text-gray-700">Select Time</h3>
              
              {/* Decision Support - Suggest Alternatives */}
              <BookingDecisionSupport 
                selectedDate={selectedDate}
                isDarkMode={false}
                onSuggestions={(alts) => {
                  const normalized = (alts || []).map(a => ({
                    date: a.date,
                    time: a.first_available_time || (a.available_times && a.available_times[0]) || null,
                    available_slots: a.available_slots ?? a.available_slots_count ?? (a.available_slots ? a.available_slots : (a.available_times ? a.available_times.length : 0)),
                    ...a
                  }));
                  setSlotAlternatives(normalized);
                }}
              />

              {/* Unavailability Message - Shows when time is not available */}
              {selectedTime && slotUnavailabilityReason && (
                <UnavailabilityMessage
                  selectedDate={selectedDate}
                  selectedTime={selectedTime}
                  isUnavailable={true}
                  reason={slotUnavailabilityReason}
                  alternatives={slotAlternatives}
                  isDarkMode={false}
                  onSelectAlternative={(alt) => {
                    setSelectedDate(alt.date);
                    setSelectedTime(alt.time);
                    setFormData(prev => ({ 
                      ...prev, 
                      appointment_date: alt.date,
                      appointment_time: alt.time 
                    }));
                  }}
                />
              )}

              {/* Available Time Slots Grid */}
              <div>
                <div className="flex items-center gap-2 mb-3">
                  <ClockIcon className="h-4 w-4 text-gray-600" />
                  <label className="block text-xs font-medium text-gray-700">
                    Available Times (8 AM - 5 PM, Lunch 12-1 PM Closed)
                  </label>
                </div>

                <div className="grid grid-cols-4 gap-2">
                  {[
                    '08:00', '08:30', '09:00', '09:30', '10:00', '10:30', '11:00', '11:30',
                    '13:00', '13:30', '14:00', '14:30', '15:00', '15:30', '16:00', '16:30'
                  ].map((time) => {
                    const isAvailable = isTimeSlotAvailable(time);
                    const isSelected = formData.appointment_time === time;

                    return (
                      <button
                        key={time}
                        type="button"
                        onClick={() => {
                          if (isAvailable) {
                            setSelectedTime(time);
                            setFormData(prev => ({ ...prev, appointment_time: time }));
                            setSlotUnavailabilityReason(null);
                            setSlotAlternatives([]);
                          } else {
                            setSelectedTime(time);
                            setSlotUnavailabilityReason({
                              type: 'capacity',
                              message: 'This time slot is fully booked. Please choose another time or date.'
                            });

                            // Request alternative suggestions for this date
                            (async () => {
                              try {
                                const res = await callApi(() => axios.post('/api/appointments/suggest-alternative', { preferred_date: selectedDate, days_ahead: 14 }));
                                if (res.success) {
                                  const alts = res.data.alternatives || [];
                                  // Normalize some fields for UnavailabilityMessage
                                  const normalized = alts.map(a => ({
                                    date: a.date,
                                    time: a.first_available_time || (a.available_times && a.available_times[0]) || (a.available_times ? a.available_times[0] : null),
                                    available_slots: a.available_slots ?? a.available_slots_count ?? a.available_capacity ?? (a.available_times ? a.available_times.length : 0),
                                    ...a
                                  }));
                                  setSlotAlternatives(normalized);
                                }
                              } catch (err) {
                                console.error('Failed to fetch slot alternatives:', err);
                                setSlotAlternatives([]);
                              }
                            })();
                          }
                        }}
                        className={`p-2 text-xs font-medium rounded transition-all ${
                          !isAvailable
                            ? 'bg-red-100 text-red-600 cursor-not-allowed'
                            : isSelected
                            ? 'bg-black text-white border border-black'
                            : 'bg-white text-gray-700 border border-gray-300 hover:border-black hover:bg-gray-50'
                        }`}
                        title={!isAvailable ? 'This slot is fully booked' : 'Click to select'}
                      >
                        {time}
                      </button>
                    );
                  })}
                </div>

                {/* Info Message */}
                <div className="mt-3 p-3 bg-blue-50 border border-blue-200 rounded flex items-start gap-2">
                  <InformationCircleIcon className="h-4 w-4 text-blue-600 mt-0.5 flex-shrink-0" />
                  <div className="text-xs text-blue-700">
                    <p className="font-semibold mb-1">🕐 Working Hours</p>
                    <p><strong>Mon-Fri:</strong> 8 AM - 5 PM</p>
                    <p><strong>Lunch Break:</strong> 12 PM - 1 PM (Closed)</p>
                    <p><strong>Sat-Sun:</strong> Closed</p>
                  </div>
                </div>
              </div>
            </div>
          )}

          {selectedDate && isDateUnavailable(new Date(selectedDate + 'T00:00:00')) && (
            <div className="p-4 bg-red-50 border-2 border-red-200 rounded">
              <p className="text-sm font-semibold text-red-800">
                <strong>🚫 {getUnavailableReason(new Date(selectedDate + 'T00:00:00'))}</strong>
              </p>
              <p className="text-xs text-red-600 mt-2">Please select a different date from the calendar</p>
            </div>
          )}

          {/* Notes */}
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">
              Additional Notes (Optional)
            </label>
            <textarea
              value={formData.notes}
              onChange={(e) => setFormData(prev => ({ ...prev, notes: e.target.value }))}
              className="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-black"
              rows="2"
              placeholder="Any additional information..."
            />
          </div>

          <div className="flex justify-end space-x-3 pt-4 border-t">
            <button
              type="button"
              onClick={() => {
                setIsBookModalOpen(false);
                setSelectedDate('');
                setSelectedTime('');
                setSlotUnavailabilityReason(null);
                setFormData({ appointment_date: '', appointment_time: '', notes: '' });
                setCalendarMonth(new Date());
              }}
              className="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded hover:bg-gray-50"
            >
              Cancel
            </button>
            <button
              type="submit"
              disabled={loading || !formData.appointment_time}
              className="px-4 py-2 text-sm font-medium text-white bg-black rounded hover:bg-gray-900 disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2"
            >
              {loading ? (
                <>
                  <LoadingSpinner size="xs" />
                  Booking...
                </>
              ) : (
                <>
                  <SparklesIcon className="h-4 w-4" />
                  Book Appointment
                </>
              )}
            </button>
          </div>
        </form>
      </Modal>
    </div>
  );
};

export default ClientAppointments;