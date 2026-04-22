import { useState, useEffect, useMemo, useCallback } from 'react';
import { useAuth } from '../context/AuthContext';
import { useTheme } from '../context/ThemeContext';
import { useApi } from '../hooks/useApi';
import Modal from '../components/Modal';
import LoadingSpinner from '../components/LoadingSpinner';
import TimeSlotRecommendations from '../components/TimeSlotRecommendations';
import AppointmentRiskAssessment from '../components/AppointmentRiskAssessment';
import UnavailableDatesViewer from '../components/UnavailableDatesViewer';
import BookingDecisionSupport from '../components/BookingDecisionSupport';
import CancellationRiskNotice from '../components/CancellationRiskNotice';
import UnavailabilityMessage from '../components/UnavailabilityMessage';
import AlternativeSlotSuggestion from '../components/AlternativeSlotSuggestion';
import AppointmentRefundStatus from '../components/AppointmentRefundStatus';
import UserRefundRequest from '../components/UserRefundRequest';
import UserRefundHistory from '../components/UserRefundHistory';
import RefundDetailsModal from '../components/modals/RefundDetailsModal';
import { 
  CalendarIcon, 
  ClockIcon, 
  CheckCircleIcon,
  XCircleIcon,
  ExclamationTriangleIcon,
  FunnelIcon,
  ChevronLeftIcon,
  ChevronRightIcon,
  InformationCircleIcon,
  SparklesIcon,
  MagnifyingGlassIcon
} from '@heroicons/react/24/outline';
import axios from 'axios';

import { formatDateDisplay } from '../utils/format';

const ClientAppointments = () => {
  const { user } = useAuth();
  const { isDarkMode } = useTheme();
  const { callApi, loading } = useApi();
  const [appointments, setAppointments] = useState([]);
  const [isBookModalOpen, setIsBookModalOpen] = useState(false);
  const [isCancelModalOpen, setIsCancelModalOpen] = useState(false);
  const [selectedAppointmentToCancel, setSelectedAppointmentToCancel] = useState(null);
  const [cancelReason, setCancelReason] = useState('');
  const [customCancelReason, setCustomCancelReason] = useState('');
  const [isViewModalOpen, setIsViewModalOpen] = useState(false);
  const [selectedAppointmentToView, setSelectedAppointmentToView] = useState(null);
  const [availableSlots, setAvailableSlots] = useState([]);
  const [slotDetails, setSlotDetails] = useState([]);
  const [slotsLoading, setSlotsLoading] = useState(false);
  const [selectedDate, setSelectedDate] = useState('');
  const [selectedTime, setSelectedTime] = useState('');
  const [unavailableDates, setUnavailableDates] = useState([]);
  const [slotCapacities, setSlotCapacities] = useState({});
  const [statusFilter, setStatusFilter] = useState('all');
  const [searchQuery, setSearchQuery] = useState('');
  const [sortBy, setSortBy] = useState('newest');
  const [currentPage, setCurrentPage] = useState(1);
  const appointmentsPerPage = 8;
  const [slotUnavailabilityReason, setSlotUnavailabilityReason] = useState(null);
  const [slotAlternatives, setSlotAlternatives] = useState([]);
  const [showAlternativeSlots, setShowAlternativeSlots] = useState(false);
  const [unavailableSelectedTime, setUnavailableSelectedTime] = useState(null);
  const [dailyLimitInfo, setDailyLimitInfo] = useState({
    limit: null,
    used: 0,
    remaining: null,
    hasReachedLimit: false,
    message: null,
    bookingsToday: [],
    date: null,
    next_available_time: null
  });
  const [formData, setFormData] = useState({
    appointment_date: '',
    appointment_time: '',
    type: 'consultation',
    notes: ''
  });
  const [services, setServices] = useState([]);
  const [calendarMonth, setCalendarMonth] = useState(new Date());
  const [activeTab, setActiveTab] = useState('appointments');
  
  // Refund Management State
  const [showRefundRequestModal, setShowRefundRequestModal] = useState(false);
  const [selectedAppointmentForRefund, setSelectedAppointmentForRefund] = useState(null);
  const [refundFormData, setRefundFormData] = useState({
    refund_amount: '',
    reason: 'customer_request',
    description: ''
  });
  const [refundLoading, setRefundLoading] = useState(false);
  const [refundError, setRefundError] = useState('');
  const [isSubmittingBooking, setIsSubmittingBooking] = useState(false);
  
  // Refund Details Modal State
  const [showRefundDetailsModal, setShowRefundDetailsModal] = useState(false);
  const [selectedAppointmentForDetails, setSelectedAppointmentForDetails] = useState(null);

  // Define callback functions BEFORE they're used in useEffect
  const loadAppointments = useCallback(async () => {
    console.log('[loadAppointments] Starting...');
    const result = await callApi((signal) =>
      axios.get('/api/appointments', { signal })
    , { skipCache: true }); // Skip cache to ensure fresh data
    
    console.log('[ClientAppointments] API result:', result);
    
    if (result.success) {
      const appointmentsData = result.data?.data || result.data || [];
      console.log('[ClientAppointments] Appointments data:', appointmentsData);
      setAppointments(Array.isArray(appointmentsData) ? appointmentsData : []);
      setCurrentPage(1);
      console.log('[loadAppointments] Done');
    } else {
      console.error('[ClientAppointments] Failed to load:', result.error);
      setAppointments([]);
    }
  }, [callApi]);

  const loadUnavailableDates = useCallback(async () => {
    console.log('[loadUnavailableDates] Starting...');
    const result = await callApi((signal) =>
      axios.get('/api/unavailable-dates', { signal })
    , { skipCache: true, cache: false });
    if (result.success) {
      const dates = result.data.data || result.data;
      console.log('[loadUnavailableDates] Loaded dates:', dates);
      setUnavailableDates(dates);
    } else {
      console.error('[loadUnavailableDates] Failed:', result);
    }
  }, [callApi]);

  // Always checks today's date - counts how many appointments booked today regardless of appointment date
  const checkDailyLimit = useCallback(async () => {
    try {
      // Guard: user must be loaded
      if (!user?.id) {
        console.log('[checkDailyLimit] User not loaded yet, skipping');
        return;
      }
      
      // Always use today's date
      const checkDate = new Date().toISOString().split('T')[0];
      console.log('[checkDailyLimit] Fetching limit for user', user.id, 'date', checkDate);
      
      const res = await callApi((signal) =>
        axios.get(`/api/appointment-settings/user-limit/${user.id}/${checkDate}`, { signal })
      );

      console.log('[checkDailyLimit] API Response:', res);

      if (res.success && res.data && res.data.data) {
        const data = res.data.data;
        console.log('[checkDailyLimit] Setting daily limit info:', {
          limit: data.limit,
          used: data.used,
          remaining: data.remaining,
          hasReachedLimit: data.has_reached_limit
        });
        setDailyLimitInfo({
          limit: data.limit,
          used: data.used || 0,
          remaining: data.remaining,
          hasReachedLimit: data.has_reached_limit || false,
          message: data.message || null,
          bookingsToday: data.bookings_today || [],
          date: data.date || checkDate,
          next_available_time: data.next_available_time || null
        });
      } else {
        console.error('[checkDailyLimit] API failed or no data:', res);
      }
    } catch (err) {
      console.error('[checkDailyLimit] Error:', err);
    }
  }, [user?.id, callApi]);

  useEffect(() => {
    if (!user?.id) {
      console.log('[ClientAppointments] User not loaded yet, waiting...');
      return;
    }
    console.log('[ClientAppointments] Loading initial data for user', user.id);
    loadAppointments();
    loadUnavailableDates();
    checkDailyLimit();

    // Load available services
    axios.get('/api/services')
      .then(res => {
        if (res.data?.success && res.data?.data) {
          setServices(res.data.data);
          // Set intelligent default for service type if available (pick first available one)
          const availableServices = res.data.data.filter(s => !s.is_unavailable);
          if (availableServices.length > 0) {
            setFormData(prev => ({ ...prev, type: availableServices[0].name }));
          } else if (res.data.data.length > 0) {
            setFormData(prev => ({ ...prev, type: res.data.data[0].name }));
          }
        }
      })
      .catch(err => console.error('Failed to load services', err));
  }, [user?.id, loadAppointments, loadUnavailableDates, checkDailyLimit]);

  // Debug: Log when dailyLimitInfo changes
  useEffect(() => {
    console.log('[ClientAppointments] dailyLimitInfo updated:', {
      hasReachedLimit: dailyLimitInfo.hasReachedLimit,
      limit: dailyLimitInfo.limit,
      used: dailyLimitInfo.used,
      message: dailyLimitInfo.message
    });
  }, [dailyLimitInfo]);

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

  // Listen for appointment settings changes and refresh daily limit
  useEffect(() => {
    const handler = (e) => {
      console.log('Appointment settings changed, refreshing daily limit...');
      checkDailyLimit();
    };

    window.addEventListener('appointmentSettingsChanged', handler);
    return () => window.removeEventListener('appointmentSettingsChanged', handler);
  }, [selectedDate]);

  const isDateUnavailable = (date) => {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    const dateStr = `${year}-${month}-${day}`;
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
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    const dateStr = `${year}-${month}-${day}`;
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

  const formatTime12Hour = (timeStr) => {
    if (!timeStr) return '';
    try {
      // Expecting 'HH:mm' or similar
      const [hh, mm] = timeStr.split(':').map(Number);
      const date = new Date();
      date.setHours(hh, mm, 0, 0);
      return date.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' });
    } catch (e) {
      return timeStr;
    }
  };

  // Filter appointments
  const filteredAppointments = useMemo(() => {
    let filtered = appointments;

    if (statusFilter !== 'all') {
      filtered = filtered.filter(apt => apt.status === statusFilter);
    }

    if (searchQuery.trim()) {
      const q = searchQuery.toLowerCase();
      filtered = filtered.filter(apt =>
        apt.type?.toLowerCase().includes(q) ||
        apt.notes?.toLowerCase().includes(q) ||
        apt.appointment_date?.toLowerCase().includes(q)
      );
    }

    return filtered;
  }, [appointments, statusFilter, searchQuery]);

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
    setSlotsLoading(true);
    setSlotDetails([]);
    setAvailableSlots([]);
    try {
      const result = await callApi((signal) =>
        axios.get(`/api/calendar/available-slots`, { params: { date }, signal })
      );
      if (result.success) {
        setAvailableSlots(result.data.available_slots || result.data.data || []);
        setSlotDetails(result.data.slot_details || []);
      }
    } finally {
      setSlotsLoading(false);
    }
  };

  const handleDateChange = (date) => {
    console.log('[handleDateChange] Date selected:', date);
    setSelectedDate(date);
    setFormData(prev => ({ ...prev, appointment_date: date, appointment_time: '' }));
    setSelectedTime('');
    setSlotDetails([]);
    loadAvailableSlots(date);
    // Clear any prior alternatives when changing date
    setSlotAlternatives([]);
    setShowAlternativeSlots(false);
    setUnavailableSelectedTime(null);
    setSlotUnavailabilityReason(null);
    console.log('[handleDateChange] Calling checkDailyLimit');
    checkDailyLimit();
  };

  // Refresh daily limit info when modal opens and whenever checkDailyLimit changes
  useEffect(() => {
    if (isBookModalOpen) {
      console.log('[useEffect] Modal opened, refreshing daily limit');
      checkDailyLimit();
    }
  }, [isBookModalOpen, checkDailyLimit]);

  // Poll slot availability every 20s while modal is open and a date is selected
  useEffect(() => {
    if (!isBookModalOpen || !selectedDate) return;
    const interval = setInterval(() => {
      if (!isSubmittingBooking) {
        loadAvailableSlots(selectedDate);
      }
    }, 20000);
    return () => clearInterval(interval);
  }, [isBookModalOpen, selectedDate, isSubmittingBooking]);

  const handleBookAppointment = async (e) => {
    e.preventDefault();
    console.log('[handleBookAppointment] Submit clicked. Current limit info:', dailyLimitInfo);
    
    // Prevent double-submit
    if (isSubmittingBooking) return;
    
    // Capture the appointment date BEFORE anything else changes
    const bookedDate = formData.appointment_date;
    console.log('[handleBookAppointment] Captured booked date:', bookedDate);

    // GUARD 0: Check if selected service is unavailable
    const selectedSvc = services.find(s => s.name === formData.type);
    if (selectedSvc && selectedSvc.is_unavailable) {
      window.showToast?.('Warning', `${selectedSvc.name} is currently unavailable: ${selectedSvc.unavailability_reason || 'Please select a different service.'}`, 'warning');
      return;
    }
    
    // GUARD 1: Double-check if user has reached their daily limit before allowing submission
    if (dailyLimitInfo.hasReachedLimit === true) {
      console.log('[handleBookAppointment] User has reached limit, preventing booking');
      window.showToast?.('Warning', dailyLimitInfo.message || `You have reached your daily booking limit of ${dailyLimitInfo.limit} appointments.`, 'warning');
      return;
    }
    
    // GUARD 2: Additional safety check - if used >= limit, prevent booking
    if (dailyLimitInfo.used !== undefined && dailyLimitInfo.limit !== undefined) {
      if (dailyLimitInfo.used >= dailyLimitInfo.limit) {
        console.log('[handleBookAppointment] User bookings (' + dailyLimitInfo.used + ') >= limit (' + dailyLimitInfo.limit + '), preventing');
        window.showToast?.('Warning', `You have reached your daily booking limit of ${dailyLimitInfo.limit} appointments. Please try again tomorrow.`, 'warning');
        return;
      }
    }
    
    console.log('[handleBookAppointment] Limit check passed, submitting booking for date:', formData.appointment_date);
    setIsSubmittingBooking(true);
    try {
    const result = await callApi((signal) =>
      axios.post('/api/appointments', formData, { signal })
    );

    if (result.success) {
      setIsBookModalOpen(false);
      setFormData({
        appointment_date: '',
        appointment_time: '',
        type: 'consultation',
        notes: ''
      });
      loadAppointments();
      window.showToast?.('Success', result.data?.email_warning ? ('Appointment booked! ⚠️ ' + result.data.email_warning) : 'Appointment booked successfully!', result.data?.email_warning ? 'warning' : 'success');
      // Refresh daily limit after booking with the captured date (await to ensure state updates before closing)
      console.log('[handleBookAppointment] Refreshing daily limit after booking');
      await checkDailyLimit();
    } else {
      // Handle booking failure (e.g., limit reached)
      const errMsg = result.error || (result.data && result.data.message) || 'Failed to book appointment';

      // If backend indicates limit reached, update dailyLimitInfo to reflect this immediately
      if (result.status === 422 && result.data) {
        const payload = result.data;
        if (payload.has_reached_limit || (payload.next_available_time && payload.limit)) {
          setDailyLimitInfo(prev => ({
            ...prev,
            limit: payload.limit || prev.limit,
            hasReachedLimit: true,
            message: payload.message || prev.message || `You have reached your daily booking limit of ${payload.limit || prev.limit}.`,
            next_available_time: payload.next_available_time || prev.next_available_time
          }));
        }

        // If slot is full, refresh available slots so UI updates
        if (errMsg.toLowerCase().includes('full capacity') || errMsg.toLowerCase().includes('slot')) {
          if (selectedDate) {
            loadAvailableSlots(selectedDate);
          }
        }
      }

      // Show inline message in modal rather than popup if possible
      window.showToast?.('Error', errMsg, 'error');
    }
    } finally {
      setIsSubmittingBooking(false);
    }
  };

  const handleCancelAppointment = async () => {
    if (!selectedAppointmentToCancel) return;

    const reason = cancelReason === 'other' ? customCancelReason : cancelReason;

    const result = await callApi((signal) =>
      axios.put(`/api/appointments/${selectedAppointmentToCancel.id}/cancel`, {
        cancellation_reason: reason || ''
      }, { signal })
    );

    if (result.success) {
      setIsCancelModalOpen(false);
      setSelectedAppointmentToCancel(null);
      setCancelReason('');
      setCustomCancelReason('');
      await loadAppointments();
      window.showToast?.('Success', 'Appointment cancelled successfully!', 'success');
    }
  };

  const handleRequestRefund = async (e) => {
    e.preventDefault();
    setRefundError('');

    if (!selectedAppointmentForRefund) return;

    // Validate payment status
    if (selectedAppointmentForRefund.payment_status !== 'paid') {
      setRefundError('Cannot process refund: This appointment is not marked as paid. Only paid appointments can be refunded.');
      return;
    }

    // Validate payment amount exists
    if (!selectedAppointmentForRefund.payment_amount || selectedAppointmentForRefund.payment_amount <= 0) {
      setRefundError('Cannot process refund: This appointment has no payment amount recorded. Please contact support.');
      return;
    }

    if (!refundFormData.refund_amount || parseFloat(refundFormData.refund_amount) <= 0) {
      setRefundError('Please enter a valid refund amount');
      return;
    }

    if (parseFloat(refundFormData.refund_amount) > (selectedAppointmentForRefund.payment_amount || 0)) {
      setRefundError('Refund amount cannot exceed the original payment');
      return;
    }

    setRefundLoading(true);
    try {
      const response = await axios.post('/api/refunds/request', {
        appointment_id: selectedAppointmentForRefund.id,
        refund_amount: parseFloat(refundFormData.refund_amount),
        reason: refundFormData.reason,
        description: refundFormData.description
      });

      if (response.data?.success) {
        setShowRefundRequestModal(false);
        setSelectedAppointmentForRefund(null);
        setRefundFormData({ refund_amount: '', reason: 'customer_request', description: '' });
        await loadAppointments();
        window.showToast?.('Refund Request', 'Your refund request has been submitted successfully', 'success');
      } else {
        setRefundError(response.data?.message || 'Failed to submit refund request');
      }
    } catch (err) {
      console.error('Refund request error:', err);
      setRefundError(err.response?.data?.message || 'Failed to submit refund request. Please try again.');
    } finally {
      setRefundLoading(false);
    }
  };

  const openRefundModal = (appointment) => {
    // Validate before opening modal
    if (appointment.payment_status !== 'paid') {
      window.showToast?.('Cannot Request Refund', 'This appointment is not marked as paid. Only paid appointments can be refunded.', 'error');
      return;
    }

    if (!appointment.payment_amount || appointment.payment_amount <= 0) {
      window.showToast?.('Cannot Request Refund', 'This appointment has no payment amount recorded. Please contact support.', 'error');
      return;
    }

    setSelectedAppointmentForRefund(appointment);
    setRefundFormData({
      refund_amount: appointment.payment_amount || '',
      reason: 'customer_request',
      description: ''
    });
    setRefundError('');
    setShowRefundRequestModal(true);
  };

  const openRefundDetailsModal = (appointment) => {
    // Validate before opening modal
    if (appointment.payment_status !== 'paid') {
      window.showToast?.('Cannot Request Refund', 'This appointment is not marked as paid. Only paid appointments can be refunded.', 'error');
      return;
    }

    if (!appointment.payment_amount || appointment.payment_amount <= 0) {
      window.showToast?.('Cannot Request Refund', 'This appointment has no payment amount recorded. Please contact support.', 'error');
      return;
    }

    setSelectedAppointmentForDetails(appointment);
    setShowRefundDetailsModal(true);
  };
  const openCancelModal = (appointment) => {
    setSelectedAppointmentToCancel(appointment);
    setCancelReason('');
    setCustomCancelReason('');
    setIsCancelModalOpen(true);
  };

  const openViewModal = (appointment) => {
    setSelectedAppointmentToView(appointment);
    setIsViewModalOpen(true);
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
    if (isDarkMode) {
      switch (status) {
        case 'completed': return 'bg-green-500/20 text-green-400 border border-green-500/30';
        case 'approved': return 'bg-blue-500/20 text-blue-400 border border-blue-500/30';
        case 'declined': return 'bg-red-500/20 text-red-400 border border-red-500/30';
        case 'cancelled': return 'bg-gray-500/20 text-gray-400 border border-gray-500/30';
        default: return 'bg-amber-500/20 text-amber-400 border border-amber-500/30';
      }
    }
    switch (status) {
      case 'completed': return 'bg-green-50 text-green-700 border border-green-200';
      case 'approved': return 'bg-blue-50 text-blue-700 border border-blue-200';
      case 'declined': return 'bg-red-50 text-red-700 border border-red-200';
      case 'cancelled': return 'bg-gray-100 text-gray-600 border border-gray-200';
      default: return 'bg-amber-50 text-amber-700 border border-amber-200';
    }
  };

  const getStatusBorderColor = (status) => {
    switch (status) {
      case 'completed': return 'border-l-green-500';
      case 'approved': return 'border-l-blue-500';
      case 'declined': return 'border-l-red-500';
      case 'cancelled': return 'border-l-gray-400';
      default: return 'border-l-amber-500';
    }
  };

  const formatAppointmentDateTime = (dateStr, timeStr) => {
    try {
      const date = new Date(dateStr + 'T00:00:00');
      const formatted = date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
      const time = formatTime12Hour((timeStr || '').substring(0, 5));
      return `${formatted} · ${time}`;
    } catch {
      return `${dateStr} ${timeStr || ''}`;
    }
  };

  const STATUS_TABS = [
    { value: 'all', label: 'All' },
    { value: 'pending', label: 'Pending' },
    { value: 'approved', label: 'Approved' },
    { value: 'completed', label: 'Completed' },
    { value: 'declined', label: 'Declined' },
    { value: 'cancelled', label: 'Cancelled' },
  ];

  const dm = isDarkMode;

  return (
    <>
    <div className={`min-h-screen ${dm ? 'bg-gray-900' : 'bg-gray-50'}`}>

      {/* ── Page Header ── */}
      <div className={`border-b px-4 sm:px-6 py-4 sm:py-5 flex flex-col sm:flex-row sm:items-center gap-3 sm:justify-between ${dm ? 'bg-gray-900 border-gray-800' : 'bg-white border-gray-200'}`}>
        <div>
          <h1 className={`text-lg sm:text-xl font-bold ${dm ? 'text-white' : 'text-gray-900'}`}>My Appointments</h1>
          <p className={`text-xs sm:text-sm mt-0.5 ${dm ? 'text-gray-400' : 'text-gray-500'}`}>View and manage your appointments</p>
        </div>
        <button
          onClick={() => { setIsBookModalOpen(true); checkDailyLimit(); }}
          disabled={dailyLimitInfo.hasReachedLimit}
          className={`w-full sm:w-auto px-4 py-2 rounded-lg text-sm font-semibold disabled:opacity-50 disabled:cursor-not-allowed ${
            dm ? 'bg-amber-500 text-gray-900 hover:bg-amber-400' : 'bg-amber-500 text-white hover:bg-amber-600'
          }`}
        >
          {dailyLimitInfo.hasReachedLimit ? 'Limit Reached' : '+ New Appointment'}
        </button>
      </div>

      <div className="px-4 sm:px-6 py-4 sm:py-5 space-y-4">
        {/* Daily limit banner */}
        {dailyLimitInfo.hasReachedLimit && (
          <div className={`rounded-lg border p-4 ${dm ? 'bg-amber-500/10 border-amber-500/30' : 'bg-amber-50 border-amber-200'}`}>
            <p className={`text-sm font-semibold ${dm ? 'text-amber-400' : 'text-amber-700'}`}>Booking Limit Reached</p>
            <p className={`text-sm mt-0.5 ${dm ? 'text-amber-300/80' : 'text-amber-600'}`}>
              {dailyLimitInfo.message || `You've reached your booking limit of ${dailyLimitInfo.limit} per 24 hours.`}
            </p>
          </div>
        )}

        {/* ── Section Tabs ── */}
        <div className={`flex gap-1 border-b ${dm ? 'border-gray-700' : 'border-gray-200'}`}>
          {[{ key: 'appointments', label: 'My Appointments' }, { key: 'refunds', label: 'Refund History' }].map(t => (
            <button
              key={t.key}
              onClick={() => setActiveTab(t.key)}
              className={`px-4 py-2.5 text-sm font-medium border-b-2 -mb-px ${
                activeTab === t.key
                  ? dm ? 'border-amber-500 text-amber-400' : 'border-amber-500 text-amber-600'
                  : dm ? 'border-transparent text-gray-400 hover:text-gray-200' : 'border-transparent text-gray-500 hover:text-gray-700'
              }`}
            >
              {t.label}{t.key === 'appointments' ? ` (${appointments.length})` : ''}
            </button>
          ))}
        </div>

        {/* ── Appointments Tab ── */}
        {activeTab === 'appointments' && (
          <div className="space-y-4">
            {/* Status filter pills */}
            <div className="flex flex-wrap gap-2">
              {STATUS_TABS.map(tab => {
                const count = tab.value === 'all'
                  ? appointments.length
                  : appointments.filter(a => a.status === tab.value).length;
                const isActive = statusFilter === tab.value;
                return (
                  <button
                    key={tab.value}
                    onClick={() => { setStatusFilter(tab.value); setCurrentPage(1); }}
                    className={`px-3 py-1 rounded-full text-xs font-semibold border ${
                      isActive
                        ? dm ? 'bg-amber-500 border-amber-500 text-gray-900' : 'bg-amber-500 border-amber-500 text-white'
                        : dm ? 'bg-transparent border-gray-700 text-gray-400 hover:text-gray-200 hover:border-gray-500' : 'bg-white border-gray-200 text-gray-600 hover:border-gray-300'
                    }`}
                  >
                    {tab.label}{count > 0 ? ` ${count}` : ''}
                  </button>
                );
              })}
            </div>

            {/* Search + Sort */}
            <div className="flex flex-col sm:flex-row gap-3">
              <div className="relative flex-1 max-w-sm">
                <MagnifyingGlassIcon className={`absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 ${dm ? 'text-gray-500' : 'text-gray-400'}`} />
                <input
                  type="text"
                  placeholder="Search by service or notes…"
                  value={searchQuery}
                  onChange={e => { setSearchQuery(e.target.value); setCurrentPage(1); }}
                  className={`w-full pl-9 pr-3 py-2 text-sm rounded-lg border focus:outline-none focus:ring-1 focus:ring-amber-500 ${
                    dm
                      ? 'bg-gray-800 border-gray-700 text-white placeholder-gray-500 focus:border-amber-500'
                      : 'bg-white border-gray-200 text-gray-900 placeholder-gray-400 focus:border-amber-500'
                  }`}
                />
              </div>
              <select
                value={sortBy}
                onChange={e => { setSortBy(e.target.value); setCurrentPage(1); }}
                className={`px-3 py-2 text-sm rounded-lg border focus:outline-none focus:ring-1 focus:ring-amber-500 ${
                  dm
                    ? 'bg-gray-800 border-gray-700 text-white focus:border-amber-500'
                    : 'bg-white border-gray-200 text-gray-900 focus:border-amber-500'
                }`}
              >
                <option value="newest">Newest First</option>
                <option value="date">Date Asc</option>
                <option value="date-desc">Date Desc</option>
                <option value="status">By Status</option>
              </select>
            </div>

            {/* Results count */}
            <p className={`text-xs ${dm ? 'text-gray-500' : 'text-gray-400'}`}>
              {sortedAppointments.length} appointment{sortedAppointments.length !== 1 ? 's' : ''}
            </p>

            {/* Empty states */}
            {appointments.length === 0 ? (
              <div className={`rounded-xl border py-16 text-center ${dm ? 'bg-gray-800 border-gray-700' : 'bg-white border-gray-200'}`}>
                <CalendarIcon className={`h-10 w-10 mx-auto mb-3 ${dm ? 'text-gray-600' : 'text-gray-300'}`} />
                <p className={`font-semibold ${dm ? 'text-gray-300' : 'text-gray-700'}`}>No appointments yet</p>
                <p className={`text-sm mt-1 ${dm ? 'text-gray-500' : 'text-gray-400'}`}>Book your first appointment to get started.</p>
              </div>
            ) : paginatedAppointments.length === 0 ? (
              <div className={`rounded-xl border py-10 text-center ${dm ? 'bg-gray-800 border-gray-700' : 'bg-white border-gray-200'}`}>
                <p className={`text-sm ${dm ? 'text-gray-400' : 'text-gray-500'}`}>No appointments match your filters.</p>
              </div>
            ) : (
              <>
                {/* Appointment cards */}
                <div className="space-y-2">
                  {paginatedAppointments.map(appointment => (
                    <div
                      key={appointment.id}
                      className={`rounded-xl border-l-4 border ${getStatusBorderColor(appointment.status)} ${
                        dm ? 'bg-gray-800 border-gray-700' : 'bg-white border-gray-200'
                      }`}
                    >
                      {/* Main row */}
                      <div className="flex flex-col sm:flex-row sm:items-center gap-3 px-4 py-3 sm:px-5 sm:py-4">
                        {/* Info */}
                        <div className="flex-1 min-w-0">
                          <p className={`font-semibold text-sm truncate ${dm ? 'text-white' : 'text-gray-900'}`}>
                            {appointment.type || 'Appointment'}
                          </p>
                          <p className={`text-xs mt-0.5 ${dm ? 'text-gray-400' : 'text-gray-500'}`}>
                            {formatAppointmentDateTime(appointment.appointment_date, appointment.appointment_time)}
                          </p>
                          {appointment.staff_notes && (
                            <p className={`text-xs mt-1 truncate ${dm ? 'text-gray-500' : 'text-gray-400'}`}>
                              {appointment.staff_notes}
                            </p>
                          )}
                        </div>

                        {/* Status + Actions row */}
                        <div className="flex items-center justify-between sm:justify-end gap-2 sm:gap-3">
                          {/* Status badge */}
                          <span className={`shrink-0 px-2.5 py-0.5 rounded-full text-xs font-semibold capitalize ${getStatusColor(appointment.status)}`}>
                            {appointment.status}
                          </span>

                          {/* Actions */}
                          <div className="flex items-center gap-1.5 shrink-0">
                          <button
                            onClick={() => openViewModal(appointment)}
                            className={`px-2.5 py-1 rounded text-xs font-medium ${dm ? 'text-blue-400 hover:bg-blue-500/10' : 'text-blue-600 hover:bg-blue-50'}`}
                          >
                            View
                          </button>
                          {appointment.status === 'completed' &&
                           appointment.payment_status === 'paid' &&
                           appointment.payment_amount > 0 &&
                           !appointment.refunds?.some(r => ['pending', 'approved'].includes(r.status)) && (
                            <button
                              onClick={() => openRefundDetailsModal(appointment)}
                              className={`px-2.5 py-1 rounded text-xs font-medium ${dm ? 'text-amber-400 hover:bg-amber-500/10' : 'text-amber-600 hover:bg-amber-50'}`}
                              title="Request refund"
                            >
                              Refund
                            </button>
                          )}
                          {(appointment.status === 'pending' || appointment.status === 'approved') && (
                            <button
                              onClick={() => openCancelModal(appointment)}
                              className={`px-2.5 py-1 rounded text-xs font-medium ${dm ? 'text-red-400 hover:bg-red-500/10' : 'text-red-500 hover:bg-red-50'}`}
                            >
                              Cancel
                            </button>
                          )}
                        </div>
                        </div>
                      </div>

                      {/* Completion row */}
                      {appointment.status === 'completed' && appointment.completed_at && (
                        <div className={`px-5 pb-3 pt-0 border-t text-xs ${dm ? 'border-gray-700 text-green-400' : 'border-gray-100 text-green-600'}`}>
                          Completed {new Date(appointment.completed_at).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })} at {new Date(appointment.completed_at).toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' })}
                          {appointment.completion_notes && <span className="ml-2 opacity-70">· {appointment.completion_notes}</span>}
                        </div>
                      )}

                      {/* Payment row */}
                      {appointment.payment_status === 'paid' && (
                        <div className={`px-5 pb-3 pt-0 border-t text-xs ${dm ? 'border-gray-700 text-blue-400' : 'border-gray-100 text-blue-600'}`}>
                          Paid ₱{parseFloat(appointment.payment_amount || 0).toFixed(2)}
                          {appointment.discount_amount > 0 && <span className="ml-1 opacity-70">(Discount ₱{parseFloat(appointment.discount_amount).toFixed(2)})</span>}
                        </div>
                      )}

                      {/* Refund status */}
                      <AppointmentRefundStatus
                        appointment={appointment}
                        onRefundRequested={() => loadAppointments()}
                      />

                      {/* Risk assessment for active appointments */}
                      {(appointment.status === 'pending' || appointment.status === 'approved') && (
                        <div className={`px-5 pb-4 pt-0 border-t ${dm ? 'border-gray-700' : 'border-gray-100'}`}>
                          <AppointmentRiskAssessment appointmentId={appointment.id} isDarkMode={isDarkMode} />
                        </div>
                      )}
                    </div>
                  ))}
                </div>

                {/* Pagination */}
                {totalPages > 1 && (
                  <div className={`flex items-center justify-between pt-1 text-sm ${dm ? 'text-gray-400' : 'text-gray-500'}`}>
                    <span>{startIdx + 1}–{Math.min(startIdx + appointmentsPerPage, sortedAppointments.length)} of {sortedAppointments.length}</span>
                    <div className="flex items-center gap-1">
                      <button
                        onClick={() => setCurrentPage(p => Math.max(1, p - 1))}
                        disabled={currentPage === 1}
                        className={`p-2 rounded disabled:opacity-30 disabled:cursor-not-allowed ${dm ? 'hover:bg-gray-700' : 'hover:bg-gray-100'}`}
                      >
                        <ChevronLeftIcon className="h-4 w-4" />
                      </button>
                      <span className="px-2 text-xs">Page {currentPage} of {totalPages}</span>
                      <button
                        onClick={() => setCurrentPage(p => Math.min(totalPages, p + 1))}
                        disabled={currentPage === totalPages}
                        className={`p-2 rounded disabled:opacity-30 disabled:cursor-not-allowed ${dm ? 'hover:bg-gray-700' : 'hover:bg-gray-100'}`}
                      >
                        <ChevronRightIcon className="h-4 w-4" />
                      </button>
                    </div>
                  </div>
                )}
              </>
            )}
          </div>
        )}

        {/* ── Refunds Tab ── */}
        {activeTab === 'refunds' && (
          <div className={`rounded-xl border p-6 ${dm ? 'bg-gray-800 border-gray-700' : 'bg-white border-gray-200'}`}>
            <UserRefundHistory />
          </div>
        )}
      </div>
    </div>

      {/* Cancel Appointment Modal */}
      <Modal
        isOpen={isCancelModalOpen}
        onClose={() => {
          setIsCancelModalOpen(false);
          setSelectedAppointmentToCancel(null);
          setCancelReason('');
          setCustomCancelReason('');
        }}
        title="Cancel Appointment"
        size="md"
      >
        {selectedAppointmentToCancel && (
          <div className="space-y-4">
            <div className={`rounded-lg p-4 ${dm ? 'bg-red-500/10 border border-red-500/20' : 'bg-red-50 border border-red-200'}`}>
              <p className={`text-sm font-medium ${dm ? 'text-red-400' : 'text-red-800'}`}>
                Are you sure you want to cancel this appointment?
              </p>
            </div>

            <div className={`rounded-lg p-4 space-y-1.5 ${dm ? 'bg-gray-700/50' : 'bg-gray-50'}`}>
              <div className={`text-sm ${dm ? 'text-gray-200' : 'text-gray-800'}`}>
                <span className="font-medium">Service:</span> {selectedAppointmentToCancel.type || selectedAppointmentToCancel.service_type || 'Appointment'}
              </div>
              <div className={`text-sm ${dm ? 'text-gray-200' : 'text-gray-800'}`}>
                <span className="font-medium">Date:</span> {formatDateDisplay(selectedAppointmentToCancel.appointment_date)} at {selectedAppointmentToCancel.appointment_time}
              </div>
              <div className={`text-sm ${dm ? 'text-gray-200' : 'text-gray-800'}`}>
                <span className="font-medium">Status:</span> <span className="capitalize">{selectedAppointmentToCancel.status}</span>
              </div>
            </div>

            {/* Reason selection (optional) */}
            <div>
              <label className={`block text-sm font-medium mb-2 ${dm ? 'text-gray-300' : 'text-gray-700'}`}>
                Reason for cancellation <span className={`font-normal ${dm ? 'text-gray-500' : 'text-gray-400'}`}>(optional)</span>
              </label>
              <div className="space-y-2">
                {[
                  { value: 'schedule_conflict', label: 'Schedule conflict' },
                  { value: 'no_longer_needed', label: 'No longer needed' },
                  { value: 'found_alternative', label: 'Found an alternative' },
                  { value: 'financial_reasons', label: 'Financial reasons' },
                  { value: 'emergency', label: 'Emergency' },
                  { value: 'other', label: 'Other' },
                ].map((option) => (
                  <label
                    key={option.value}
                    className={`flex items-center gap-3 p-2.5 rounded-lg border cursor-pointer transition-colors ${
                      cancelReason === option.value
                        ? dm
                          ? 'border-amber-500/50 bg-amber-500/10'
                          : 'border-amber-500 bg-amber-50'
                        : dm
                          ? 'border-gray-700 hover:border-gray-600'
                          : 'border-gray-200 hover:border-gray-300'
                    }`}
                  >
                    <input
                      type="radio"
                      name="cancelReason"
                      value={option.value}
                      checked={cancelReason === option.value}
                      onChange={(e) => setCancelReason(e.target.value)}
                      className="accent-amber-500"
                    />
                    <span className={`text-sm ${dm ? 'text-gray-200' : 'text-gray-700'}`}>{option.label}</span>
                  </label>
                ))}
              </div>

              {/* Custom reason text area (shown when "Other" is selected) */}
              {cancelReason === 'other' && (
                <textarea
                  value={customCancelReason}
                  onChange={(e) => setCustomCancelReason(e.target.value)}
                  placeholder="Please specify your reason..."
                  rows={3}
                  className={`w-full mt-2 px-3 py-2 text-sm rounded-lg border focus:outline-none focus:ring-1 focus:ring-amber-500 resize-none ${
                    dm
                      ? 'bg-gray-800 border-gray-700 text-white placeholder-gray-500'
                      : 'bg-white border-gray-200 text-gray-900 placeholder-gray-400'
                  }`}
                />
              )}
            </div>

            <p className={`text-xs ${dm ? 'text-gray-500' : 'text-gray-400'}`}>
              A cancellation notification will be sent to your email, and the admin will be notified.
            </p>

            <div className="flex justify-end gap-3 pt-2">
              <button
                type="button"
                onClick={() => {
                  setIsCancelModalOpen(false);
                  setSelectedAppointmentToCancel(null);
                  setCancelReason('');
                  setCustomCancelReason('');
                }}
                className={`px-4 py-2 text-sm font-medium rounded-lg border ${
                  dm
                    ? 'text-gray-300 bg-gray-700 border-gray-600 hover:bg-gray-600'
                    : 'text-gray-700 bg-white border-gray-300 hover:bg-gray-50'
                }`}
              >
                Keep Appointment
              </button>
              <button
                type="button"
                onClick={handleCancelAppointment}
                disabled={loading}
                className="px-4 py-2 text-sm font-medium text-white bg-red-600 border border-red-600 rounded-lg hover:bg-red-700 disabled:opacity-50 disabled:cursor-not-allowed"
              >
                {loading ? 'Cancelling...' : 'Confirm Cancellation'}
              </button>
            </div>
          </div>
        )}
      </Modal>

      {/* View Appointment Details Modal */}
      <Modal
        isOpen={isViewModalOpen}
        onClose={() => {
          setIsViewModalOpen(false);
          setSelectedAppointmentToView(null);
        }}
        title="Appointment Details"
        size="md"
      >
        {selectedAppointmentToView && (
          <div className="space-y-4">
            <div className={`rounded-lg p-4 space-y-3 ${dm ? 'bg-gray-700/50' : 'bg-gray-50'}`}>
              <div className="flex items-center justify-between">
                <span className={`text-xs font-medium uppercase tracking-wide ${dm ? 'text-gray-400' : 'text-gray-500'}`}>Status</span>
                <span className={`px-2.5 py-0.5 rounded-full text-xs font-semibold capitalize ${getStatusColor(selectedAppointmentToView.status)}`}>
                  {selectedAppointmentToView.status}
                </span>
              </div>

              {[
                { label: 'Service', value: selectedAppointmentToView.type || selectedAppointmentToView.service_type || 'N/A' },
                { label: 'Date', value: formatDateDisplay(selectedAppointmentToView.appointment_date) },
                { label: 'Time', value: selectedAppointmentToView.appointment_time || 'N/A' },
                ...(selectedAppointmentToView.notes ? [{ label: 'Notes', value: selectedAppointmentToView.notes }] : []),
                ...(selectedAppointmentToView.staff_notes ? [{ label: 'Staff Notes', value: selectedAppointmentToView.staff_notes }] : []),
              ].map((item, idx) => (
                <div key={idx} className="flex items-start justify-between gap-4">
                  <span className={`text-xs font-medium uppercase tracking-wide shrink-0 ${dm ? 'text-gray-400' : 'text-gray-500'}`}>{item.label}</span>
                  <span className={`text-sm text-right ${dm ? 'text-gray-200' : 'text-gray-800'}`}>{item.value}</span>
                </div>
              ))}
            </div>

            {/* Payment info */}
            {selectedAppointmentToView.payment_status === 'paid' && (
              <div className={`rounded-lg p-4 space-y-2 ${dm ? 'bg-blue-500/10 border border-blue-500/20' : 'bg-blue-50 border border-blue-200'}`}>
                <p className={`text-xs font-medium uppercase tracking-wide ${dm ? 'text-blue-400' : 'text-blue-600'}`}>Payment</p>
                <p className={`text-sm ${dm ? 'text-gray-200' : 'text-gray-800'}`}>
                  ₱{parseFloat(selectedAppointmentToView.payment_amount || 0).toFixed(2)}
                  {selectedAppointmentToView.discount_amount > 0 && (
                    <span className="ml-2 opacity-70">(Discount: ₱{parseFloat(selectedAppointmentToView.discount_amount).toFixed(2)})</span>
                  )}
                </p>
              </div>
            )}

            {/* Completion info */}
            {selectedAppointmentToView.status === 'completed' && selectedAppointmentToView.completed_at && (
              <div className={`rounded-lg p-4 space-y-2 ${dm ? 'bg-green-500/10 border border-green-500/20' : 'bg-green-50 border border-green-200'}`}>
                <p className={`text-xs font-medium uppercase tracking-wide ${dm ? 'text-green-400' : 'text-green-600'}`}>Completed</p>
                <p className={`text-sm ${dm ? 'text-gray-200' : 'text-gray-800'}`}>
                  {new Date(selectedAppointmentToView.completed_at).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })} at {new Date(selectedAppointmentToView.completed_at).toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' })}
                </p>
                {selectedAppointmentToView.completion_notes && (
                  <p className={`text-sm ${dm ? 'text-gray-300' : 'text-gray-600'}`}>{selectedAppointmentToView.completion_notes}</p>
                )}
              </div>
            )}

            {/* Cancellation reason */}
            {selectedAppointmentToView.status === 'cancelled' && selectedAppointmentToView.cancellation_reason && (
              <div className={`rounded-lg p-4 space-y-2 ${dm ? 'bg-red-500/10 border border-red-500/20' : 'bg-red-50 border border-red-200'}`}>
                <p className={`text-xs font-medium uppercase tracking-wide ${dm ? 'text-red-400' : 'text-red-600'}`}>Cancellation Reason</p>
                <p className={`text-sm ${dm ? 'text-gray-200' : 'text-gray-800'}`}>{selectedAppointmentToView.cancellation_reason}</p>
              </div>
            )}

            {/* Decline reason */}
            {selectedAppointmentToView.status === 'declined' && selectedAppointmentToView.decline_reason && (
              <div className={`rounded-lg p-4 space-y-2 ${dm ? 'bg-red-500/10 border border-red-500/20' : 'bg-red-50 border border-red-200'}`}>
                <p className={`text-xs font-medium uppercase tracking-wide ${dm ? 'text-red-400' : 'text-red-600'}`}>Decline Reason</p>
                <p className={`text-sm ${dm ? 'text-gray-200' : 'text-gray-800'}`}>{selectedAppointmentToView.decline_reason}</p>
              </div>
            )}

            <div className="flex justify-end pt-2">
              <button
                type="button"
                onClick={() => {
                  setIsViewModalOpen(false);
                  setSelectedAppointmentToView(null);
                }}
                className={`px-4 py-2 text-sm font-medium rounded-lg border ${
                  dm
                    ? 'text-gray-300 bg-gray-700 border-gray-600 hover:bg-gray-600'
                    : 'text-gray-700 bg-white border-gray-300 hover:bg-gray-50'
                }`}
              >
                Close
              </button>
            </div>
          </div>
        )}
      </Modal>

      {/* Request Refund Modal - New Component */}
      <UserRefundRequest
        isOpen={showRefundRequestModal}
        onClose={() => {
          setShowRefundRequestModal(false);
          setSelectedAppointmentForRefund(null);
          setRefundFormData({ refund_amount: '', reason: 'customer_request', description: '' });
          setRefundError('');
        }}
        appointment={selectedAppointmentForRefund}
        onSuccess={() => loadAppointments()}
      />

      {/* Book Appointment Modal */}
      <Modal
        isOpen={isBookModalOpen}
        onClose={() => {
          setIsBookModalOpen(false);
          setSelectedDate('');
          setSlotDetails([]);
          setFormData({ appointment_date: '', appointment_time: '', type: 'consultation', notes: '' });
          setCalendarMonth(new Date());
        }}
        title={dailyLimitInfo.hasReachedLimit ? '📅 Booking Limit Reached' : 'Book New Appointment'}
        size="xl"
      >
        <form onSubmit={handleBookAppointment} className="space-y-5">
          {/* Step Progress Indicator */}
          <div className="flex items-center justify-center gap-0">
            {[
              { num: 1, label: 'Service', done: !!formData.type },
              { num: 2, label: 'Date', done: !!selectedDate },
              { num: 3, label: 'Time', done: !!formData.appointment_time },
              { num: 4, label: 'Confirm', done: false },
            ].map((step, idx, arr) => (
              <div key={step.num} className="flex items-center">
                <div className="flex flex-col items-center">
                  <div className={`w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold transition-all ${
                    step.done
                      ? 'bg-amber-500 text-white'
                      : 'bg-gray-200 text-gray-500'
                  }`}>
                    {step.done ? '✓' : step.num}
                  </div>
                  <span className="text-[10px] mt-1 text-gray-500 font-medium">{step.label}</span>
                </div>
                {idx < arr.length - 1 && (
                  <div className={`w-10 sm:w-16 h-0.5 mx-1 mb-4 ${step.done ? 'bg-amber-400' : 'bg-gray-200'}`} />
                )}
              </div>
            ))}
          </div>

          {dailyLimitInfo.hasReachedLimit && (
            <div className="p-4 rounded-lg bg-blue-50 border border-blue-200">
              <p className="text-blue-700 font-medium">You've reached your daily booking limit</p>
              <p className="text-sm text-blue-600 mt-2">{dailyLimitInfo.message}</p>
            </div>
          )}

          {/* Daily Appointment Limit Indicator */}
          {dailyLimitInfo.limit && (
            <div className="p-3 rounded-lg bg-amber-50 border border-amber-200">
              <div className="flex items-center justify-between mb-2">
                <span className="text-sm font-semibold text-gray-700">Daily Bookings</span>
                <span className="text-lg font-bold text-amber-600">
                  {dailyLimitInfo.used} / {dailyLimitInfo.limit}
                </span>
              </div>
              <div className="w-full bg-amber-100 rounded-full h-2">
                <div
                  className={`h-2 rounded-full transition-all duration-300 ${
                    dailyLimitInfo.hasReachedLimit ? 'bg-red-500' : 'bg-amber-500'
                  }`}
                  style={{ width: `${Math.min((dailyLimitInfo.used / dailyLimitInfo.limit) * 100, 100)}%` }}
                ></div>
              </div>
              <p className="text-xs text-gray-600 mt-2">
                {dailyLimitInfo.remaining > 0 
                  ? `You can book ${dailyLimitInfo.remaining} more appointment${dailyLimitInfo.remaining !== 1 ? 's' : ''} today`
                  : 'You have reached your daily limit for today'
                }
              </p>
            </div>
          )}

          {/* ═══════ STEP 1: Service Type ═══════ */}
          <div className="bg-white rounded-lg">
            <label className="block text-sm font-medium text-gray-700 mb-1">
              <span className="inline-flex items-center gap-1.5">
                <span className="w-5 h-5 rounded-full bg-amber-500 text-white text-xs font-bold flex items-center justify-center">1</span>
                Service Type <span className="text-red-500">*</span>
              </span>
            </label>
            <select
              value={formData.type}
              onChange={(e) => setFormData(prev => ({ ...prev, type: e.target.value }))}
              className="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm text-gray-900 focus:outline-none focus:ring-2 focus:ring-amber-500 focus:border-transparent"
              disabled={dailyLimitInfo.hasReachedLimit}
            >
              {services.length > 0 ? (
                services.map(s => (
                  <option key={s.id} value={s.name} disabled={!!s.is_unavailable}>
                    {s.name} {s.price ? `(₱${s.price})` : ''}{s.is_unavailable ? ' — Unavailable' : ''}
                  </option>
                ))
              ) : (
                <>
                  <option value="consultation">Legal Consultation</option>
                  <option value="document_review">Document Review</option>
                  <option value="contract_drafting">Contract Drafting</option>
                  <option value="court_representation">Court Representation</option>
                  <option value="notary_services">Notary Services</option>
                  <option value="legal_opinion">Legal Opinion</option>
                  <option value="case_evaluation">Case Evaluation</option>
                  <option value="document_notarization">Document Notarization</option>
                  <option value="affidavit">Affidavit</option>
                  <option value="power_of_attorney">Power of Attorney</option>
                  <option value="loan_signing">Loan Signing</option>
                  <option value="real_estate_documents">Real Estate Documents</option>
                  <option value="will_and_testament">Will and Testament</option>
                  <option value="other">Other Legal Services</option>
                </>
              )}
            </select>
          </div>

          {/* Service Requirements Presentation */}
          {(() => {
            const selectedService = services.find(s => s.name === formData.type);
            if (selectedService && selectedService.public_requirements && selectedService.public_requirements.length > 0) {
              return (
                <div className="p-3 sm:p-4 bg-amber-50 rounded-lg border border-amber-200">
                  <div className="flex items-start gap-2">
                    <ExclamationTriangleIcon className="h-5 w-5 text-amber-600 flex-shrink-0 mt-0.5" />
                    <div>
                      <h4 className="text-sm font-bold text-amber-800">What to Bring (Requirements)</h4>
                      <p className="text-xs text-amber-700 mb-2 mt-0.5">Please ensure you have the following ready to avoid rescheduling:</p>
                      <ul className="list-disc pl-4 text-xs text-amber-700 space-y-1 font-medium">
                        {selectedService.public_requirements.map((req, i) => (
                          <li key={i}>{req}</li>
                        ))}
                      </ul>
                    </div>
                  </div>
                </div>
              );
            }
            return null;
          })()}

          {/* Service Unavailability Notice */}
          {(() => {
            const selectedService = services.find(s => s.name === formData.type);
            if (selectedService && selectedService.is_unavailable) {
              const categoryLabels = {
                maintenance: 'Maintenance',
                staff_unavailable: 'Staff Unavailable',
                system_upgrade: 'System Upgrade',
                holiday: 'Holiday',
                policy_change: 'Policy Change',
                custom: 'Temporarily Unavailable',
              };
              const categoryLabel = categoryLabels[selectedService.unavailability_category] || 'Temporarily Unavailable';
              return (
                <div className="p-3 sm:p-4 bg-red-50 rounded-lg border border-red-200">
                  <div className="flex items-start gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" strokeWidth={1.5} stroke="currentColor" className="h-5 w-5 text-red-600 flex-shrink-0 mt-0.5">
                      <path strokeLinecap="round" strokeLinejoin="round" d="M18.364 18.364A9 9 0 0 0 5.636 5.636m12.728 12.728A9 9 0 0 1 5.636 5.636m12.728 12.728L5.636 5.636" />
                    </svg>
                    <div>
                      <div className="flex items-center gap-2">
                        <h4 className="text-sm font-bold text-red-800">Service Currently Unavailable</h4>
                        <span className="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-semibold bg-red-200 text-red-800">
                          {categoryLabel}
                        </span>
                      </div>
                      <p className="text-xs text-red-700 mt-1">
                        {selectedService.unavailability_reason || 'This service is temporarily unavailable for booking.'}
                      </p>
                      {selectedService.unavailable_until ? (
                        <p className="text-xs text-red-600 mt-1.5 font-medium flex items-center gap-1">
                          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" strokeWidth={1.5} stroke="currentColor" className="h-3.5 w-3.5">
                            <path strokeLinecap="round" strokeLinejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                          </svg>
                          Expected to resume: {new Date(selectedService.unavailable_until).toLocaleDateString(undefined, { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' })}
                        </p>
                      ) : (
                        <p className="text-xs text-red-500 mt-1.5 italic">
                          No estimated resumption date available.
                        </p>
                      )}
                      <p className="text-xs text-red-600 mt-1.5">
                        Please select a different service or check back later.
                      </p>
                    </div>
                  </div>
                </div>
              );
            }
            return null;
          })()}

          {/* ═══════ STEP 2: Calendar Picker ═══════ */}
          <div className="space-y-3">
            <div className="flex items-center justify-between">
              <h3 className="text-sm font-semibold text-gray-700">
                <span className="inline-flex items-center gap-1.5">
                  <span className="w-5 h-5 rounded-full bg-amber-500 text-white text-xs font-bold flex items-center justify-center">2</span>
                  Select Date
                </span>
              </h3>
              <div className="flex items-center gap-2">
                <button
                  type="button"
                  onClick={() => setCalendarMonth(new Date(calendarMonth.getFullYear(), calendarMonth.getMonth() - 1))}
                  className="p-2 border border-gray-300 rounded-lg hover:bg-gray-100 transition-colors"
                  disabled={dailyLimitInfo.hasReachedLimit}
                  aria-label="Previous month"
                >
                  <ChevronLeftIcon className="h-5 w-5" />
                </button>
                <span className="text-sm font-semibold text-gray-700 min-w-[140px] text-center select-none">
                  {calendarMonth.toLocaleDateString('en-US', { month: 'long', year: 'numeric' })}
                </span>
                <button
                  type="button"
                  onClick={() => setCalendarMonth(new Date(calendarMonth.getFullYear(), calendarMonth.getMonth() + 1))}
                  className="p-2 border border-gray-300 rounded-lg hover:bg-gray-100 transition-colors"
                  disabled={dailyLimitInfo.hasReachedLimit}
                  aria-label="Next month"
                >
                  <ChevronRightIcon className="h-5 w-5" />
                </button>
              </div>
            </div>

            {/* Calendar Grid */}
            <div className="border rounded-xl p-3 sm:p-4 bg-gray-50">
              {/* Day headers */}
              <div className="grid grid-cols-7 gap-1.5 sm:gap-2 mb-2">
                {['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'].map(day => (
                  <div key={day} className="text-center text-xs font-semibold text-gray-500 py-1 sm:py-2 select-none">
                    {day}
                  </div>
                ))}
              </div>

              {/* Days */}
              <div className="grid grid-cols-7 gap-1.5 sm:gap-2">
                {(() => {
                  const firstDay = new Date(calendarMonth.getFullYear(), calendarMonth.getMonth(), 1).getDay();
                  const daysInMonth = new Date(calendarMonth.getFullYear(), calendarMonth.getMonth() + 1, 0).getDate();
                  const days = [];

                  // Empty cells
                  for (let i = 0; i < firstDay; i++) {
                    days.push(<div key={`empty-${i}`} className="aspect-square"></div>);
                  }

                  // Days
                  for (let day = 1; day <= daysInMonth; day++) {
                    const date = new Date(calendarMonth.getFullYear(), calendarMonth.getMonth(), day);
                    const year = date.getFullYear();
                    const month = String(date.getMonth() + 1).padStart(2, '0');
                    const dayStr = String(day).padStart(2, '0');
                    const dateStr = `${year}-${month}-${dayStr}`;
                    const todayDate = new Date();
                    const todayStr = `${todayDate.getFullYear()}-${String(todayDate.getMonth() + 1).padStart(2, '0')}-${String(todayDate.getDate()).padStart(2, '0')}`;
                    const isToday = dateStr === todayStr;
                    const isPast = date < todayDate && !isToday;
                    const isUnavail = isDateUnavailable(date);
                    const isSelected = selectedDate === dateStr;

                    days.push(
                      <button
                        key={day}
                        type="button"
                        onClick={() => !isPast && !isUnavail && !dailyLimitInfo.hasReachedLimit && handleDateChange(dateStr)}
                        disabled={isPast || isUnavail || dailyLimitInfo.hasReachedLimit}
                        className={`aspect-square flex items-center justify-center text-sm sm:text-base font-medium rounded-lg transition-all duration-150 select-none ${
                          isSelected
                            ? 'bg-amber-500 text-white shadow-md shadow-amber-500/30'
                            : isPast
                            ? 'bg-gray-200 text-gray-400 cursor-not-allowed'
                            : isUnavail
                            ? 'bg-red-100 text-red-500 cursor-not-allowed'
                            : isToday
                            ? 'bg-blue-100 text-blue-700 ring-2 ring-blue-400 ring-offset-1 font-bold'
                            : 'bg-white text-gray-700 border border-gray-300 hover:border-amber-500 hover:bg-amber-50 active:bg-amber-100'
                        }`}
                        title={isPast ? 'Past date' : isUnavail ? getUnavailableReason(date) : isToday ? 'Today' : ''}
                        aria-label={`${dateStr}${isToday ? ' (today)' : ''}${isUnavail ? ' — unavailable' : ''}`}
                      >
                        {day}
                      </button>
                    );
                  }

                  return days;
                })()}
              </div>

              {/* Legend */}
              <div className="mt-4 pt-3 border-t border-gray-200 grid grid-cols-2 sm:grid-cols-4 gap-2 text-xs">
                <div className="flex items-center gap-2">
                  <div className="w-3 h-3 bg-amber-500 rounded shadow-sm"></div>
                  <span className="text-gray-600">Selected</span>
                </div>
                <div className="flex items-center gap-2">
                  <div className="w-3 h-3 bg-red-100 border border-red-400 rounded"></div>
                  <span className="text-gray-600">Unavailable</span>
                </div>
                <div className="flex items-center gap-2">
                  <div className="w-3 h-3 bg-blue-100 ring-2 ring-blue-400 ring-offset-1 rounded"></div>
                  <span className="text-gray-600">Today</span>
                </div>
                <div className="flex items-center gap-2">
                  <div className="w-3 h-3 bg-gray-200 rounded"></div>
                  <span className="text-gray-600">Past</span>
                </div>
              </div>
            </div>
          </div>

          {selectedDate && isDateUnavailable(new Date(selectedDate + 'T00:00:00')) && (
            <div className="p-4 bg-red-50 border-2 border-red-200 rounded">
              <p className="text-sm font-semibold text-red-800">
                <strong>🚫 {getUnavailableReason(new Date(selectedDate + 'T00:00:00'))}</strong>
              </p>
              <p className="text-xs text-red-600 mt-2">Please select a different date from the calendar</p>
            </div>
          )}

          {/* ═══════ STEP 3: Time Slot Selection ═══════ */}
          {selectedDate && !isDateUnavailable(new Date(selectedDate + 'T00:00:00')) && (
            <div className="space-y-4">
              <h3 className="text-sm font-semibold text-gray-700">
                <span className="inline-flex items-center gap-1.5">
                  <span className="w-5 h-5 rounded-full bg-amber-500 text-white text-xs font-bold flex items-center justify-center">3</span>
                  Select Time
                </span>
              </h3>

              {/* Loading State */}
              {slotsLoading && (
                <div className="flex items-center justify-center py-8 bg-gray-50 rounded-lg border border-gray-200">
                  <LoadingSpinner size="sm" />
                  <span className="ml-2 text-sm text-gray-500">Loading available slots...</span>
                </div>
              )}

              {/* No Slots Available */}
              {!slotsLoading && slotDetails.length === 0 && (
                <div className="p-4 bg-gray-50 border border-gray-200 rounded-lg text-center">
                  <ClockIcon className="h-8 w-8 text-gray-400 mx-auto mb-2" />
                  <p className="text-sm font-medium text-gray-700">No time slots available</p>
                  <p className="text-xs text-gray-500 mt-1">All slots for this date are fully booked or unavailable. Please try a different date.</p>
                </div>
              )}

              {/* Dynamic Time Slot Grid */}
              {!slotsLoading && slotDetails.length > 0 && (
                <div>
                  {/* Slot Summary */}
                  <div className="flex items-center gap-2 mb-3">
                    <ClockIcon className="h-4 w-4 text-gray-600" />
                    <span className="text-xs text-gray-600">
                      {slotDetails.filter(s => s.status !== 'full').length} of {slotDetails.length} slots available
                    </span>
                  </div>

                  <div className="grid grid-cols-2 sm:grid-cols-4 gap-2">
                    {slotDetails.map((slot) => {
                      const isFull = slot.status === 'full';
                      const isPartial = slot.status === 'partial';
                      const isSelected = formData.appointment_time === slot.time;
                      const isDisabled = isFull || dailyLimitInfo.hasReachedLimit;

                      return (
                        <button
                          key={slot.time}
                          type="button"
                          onClick={() => {
                            if (isDisabled) return;
                            setSelectedTime(slot.time);
                            setFormData(prev => ({ ...prev, appointment_time: slot.time }));
                            setSlotUnavailabilityReason(null);
                            setSlotAlternatives([]);
                            setShowAlternativeSlots(false);
                            setUnavailableSelectedTime(null);
                          }}
                          disabled={isDisabled}
                          className={`relative p-2.5 rounded-lg text-xs font-medium transition-all border-2 ${
                            isDisabled
                              ? 'bg-red-50 text-red-400 border-red-200 cursor-not-allowed opacity-75'
                              : isSelected
                              ? 'bg-amber-500 text-white border-amber-600 shadow-md shadow-amber-500/25'
                              : isPartial
                              ? 'bg-amber-50 text-amber-800 border-amber-300 hover:border-amber-500 hover:bg-amber-100 cursor-pointer'
                              : 'bg-white text-gray-700 border-gray-200 hover:border-amber-500 hover:bg-amber-50 cursor-pointer'
                          }`}
                          title={isFull ? 'This slot is fully booked' : `${slot.booked}/${slot.capacity} booked`}
                        >
                          <div className="flex flex-col items-center gap-1">
                            <span className="text-sm font-semibold">{formatTime12Hour(slot.time)}</span>
                            {isFull ? (
                              <span className="text-[10px] font-bold uppercase tracking-wider text-red-500">FULL</span>
                            ) : (
                              <span className={`text-[10px] ${isSelected ? 'text-amber-100' : isPartial ? 'text-amber-600' : 'text-green-600'}`}>
                                {slot.booked}/{slot.capacity} booked
                              </span>
                            )}
                          </div>
                          {isFull && (
                            <div className="absolute inset-0 rounded-lg bg-red-100/30" />
                          )}
                        </button>
                      );
                    })}
                  </div>

                  {/* Slot Legend */}
                  <div className="mt-3 flex flex-wrap gap-3 text-xs text-gray-500">
                    <div className="flex items-center gap-1.5">
                      <div className="w-3 h-3 rounded bg-white border-2 border-gray-200"></div>
                      <span>Available</span>
                    </div>
                    <div className="flex items-center gap-1.5">
                      <div className="w-3 h-3 rounded bg-amber-50 border-2 border-amber-300"></div>
                      <span>Filling up</span>
                    </div>
                    <div className="flex items-center gap-1.5">
                      <div className="w-3 h-3 rounded bg-red-50 border-2 border-red-200"></div>
                      <span>Full</span>
                    </div>
                    <div className="flex items-center gap-1.5">
                      <div className="w-3 h-3 rounded bg-amber-500"></div>
                      <span>Selected</span>
                    </div>
                  </div>

                  {/* Working Hours Info */}
                  <div className="mt-3 p-3 bg-blue-50 border border-blue-200 rounded flex items-start gap-2">
                    <InformationCircleIcon className="h-4 w-4 text-blue-600 mt-0.5 flex-shrink-0" />
                    <div className="text-xs text-blue-700">
                      <p><strong>Mon-Fri:</strong> 8 AM - 5 PM &nbsp;|&nbsp; <strong>Lunch:</strong> 12 - 1 PM (Closed) &nbsp;|&nbsp; <strong>Sat-Sun:</strong> Closed</p>
                    </div>
                  </div>
                </div>
              )}

              {/* Decision Support - Suggest Alternatives */}
              {!slotsLoading && (
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
              )}

              {/* AI Alternative Slot Suggestions - Shows when time is not available */}
              <AlternativeSlotSuggestion
                preferredDate={selectedDate}
                preferredTime={unavailableSelectedTime}
                isVisible={showAlternativeSlots && !!unavailableSelectedTime}
                isDarkMode={false}
                onSelectSlot={(date, time) => {
                  setSelectedDate(date);
                  setSelectedTime(time);
                  setFormData(prev => ({
                    ...prev,
                    appointment_date: date,
                    appointment_time: time
                  }));
                  setSlotUnavailabilityReason(null);
                  setShowAlternativeSlots(false);
                  setUnavailableSelectedTime(null);
                  setSlotAlternatives([]);
                  // Reload available slots if date changed
                  if (date !== selectedDate) {
                    loadAvailableSlots(date);
                    checkDailyLimit();
                  }
                }}
                onDismiss={() => {
                  setShowAlternativeSlots(false);
                  setUnavailableSelectedTime(null);
                }}
              />

              {/* Cancellation Risk Notice - Shows when a slot is busy */}
              {selectedDate && selectedTime && !slotUnavailabilityReason && (
                <CancellationRiskNotice 
                  appointmentDate={selectedDate}
                  appointmentTime={selectedTime}
                  onAlternativeSelected={(date, time) => {
                    setSelectedDate(date);
                    setSelectedTime(time);
                    setFormData(prev => ({ 
                      ...prev, 
                      appointment_date: date,
                      appointment_time: time 
                    }));
                  }}
                />
              )}
            </div>
          )}

          {/* ═══════ STEP 4: Notes & Confirmation ═══════ */}
          {formData.appointment_time && (
            <div className="space-y-4 pt-2 border-t border-gray-200">
              {/* Booking Summary */}
              <div className="bg-amber-50 border border-amber-200 rounded-xl overflow-hidden shadow-sm">
                <div className="bg-amber-100/50 px-4 py-2 border-b border-amber-200">
                  <h4 className="text-xs font-bold text-amber-800 uppercase tracking-wider">Booking Summary</h4>
                </div>
                <div className="p-4 space-y-4">
                  {/* Service Highlight */}
                  <div className="bg-white/60 p-3 rounded-lg border border-amber-200/50 shadow-inner">
                    <span className="text-[10px] font-bold text-amber-600 uppercase">Selected Service</span>
                    <p className="text-lg font-extrabold text-amber-900 leading-tight mt-1">
                      {(() => {
                         // Some services might be multiple, handle same as dashboard
                         if (Array.isArray(formData.type)) {
                           return formData.type.join(', ') || 'General Service';
                         }
                         return formData.type || 'General Service';
                      })()}
                    </p>
                  </div>

                  <div className="grid grid-cols-2 gap-4">
                    <div className="bg-white/40 p-2.5 rounded-lg border border-amber-200/30">
                      <span className="text-[10px] font-bold text-amber-600 uppercase">Date</span>
                      <p className="text-sm font-bold text-amber-900 mt-0.5">{formatDateDisplay(selectedDate)}</p>
                    </div>
                    <div className="bg-white/40 p-2.5 rounded-lg border border-amber-200/30">
                      <span className="text-[10px] font-bold text-amber-600 uppercase">Time</span>
                      <p className="text-sm font-bold text-amber-900 mt-0.5">{formatTime12Hour(formData.appointment_time)}</p>
                    </div>
                  </div>
                </div>
              </div>

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
                  disabled={dailyLimitInfo.hasReachedLimit}
                />
              </div>
            </div>
          )}

          <div className="flex justify-end space-x-3 pt-4 border-t">
            <button
              type="button"
              onClick={() => {
                setIsBookModalOpen(false);
                setSelectedDate('');
                setSelectedTime('');
                setSlotDetails([]);
                setSlotUnavailabilityReason(null);
                setSlotAlternatives([]);
                setShowAlternativeSlots(false);
                setUnavailableSelectedTime(null);
                setFormData({ appointment_date: '', appointment_time: '', type: 'consultation', notes: '' });
                setCalendarMonth(new Date());
              }}
              className="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded hover:bg-gray-50"
            >
              Cancel
            </button>
            <button
              type="submit"
              disabled={isSubmittingBooking || loading || !formData.appointment_time || dailyLimitInfo.hasReachedLimit}
              className="px-4 py-2 text-sm font-medium text-white bg-black rounded hover:bg-gray-900 disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2"
            >
              {isSubmittingBooking ? (
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

      {/* Refund Details Modal */}
      <RefundDetailsModal
        isOpen={showRefundDetailsModal}
        onClose={() => {
          setShowRefundDetailsModal(false);
          setSelectedAppointmentForDetails(null);
        }}
        appointment={selectedAppointmentForDetails}
        onConfirm={() => loadAppointments()}
      />
    </>
  );
};

export default ClientAppointments;
