import { useEffect, useState } from 'react';
import {
  CalendarDaysIcon,
  ClockIcon,
  CurrencyDollarIcon,
  InformationCircleIcon,
  XMarkIcon,
  CheckCircleIcon,
  ExclamationCircleIcon,
  ReceiptRefundIcon,
} from '@heroicons/react/24/outline';
import axios from 'axios';
import { useTheme } from '../context/ThemeContext';
import { formatDateDisplay, formatTime12Hour } from '../utils/format';

const UserRefundRequest = ({ isOpen, onClose, appointment, onSuccess }) => {
  const { isDarkMode } = useTheme();
  const [refundAmount, setRefundAmount] = useState(appointment?.payment_amount || '');
  const [reason, setReason] = useState('customer_request');
  const [description, setDescription] = useState('');
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');

  const refundReasons = [
    { value: 'customer_request', label: 'Customer Request' },
    { value: 'service_not_provided', label: 'Service Not Provided' },
    { value: 'duplicate_payment', label: 'Duplicate Payment' },
    { value: 'service_cancellation', label: 'Service Cancellation' },
    { value: 'poor_service', label: 'Poor Service Quality' },
    { value: 'other', label: 'Other Reason' }
  ];

  const resetFormState = () => {
    setRefundAmount(appointment?.payment_amount || '');
    setReason('customer_request');
    setDescription('');
    setError('');
  };

  useEffect(() => {
    if (isOpen && appointment) {
      resetFormState();
    }
  }, [isOpen, appointment]);

  const maxRefund = Number(appointment?.payment_amount || 0);
  const requestedRefund = Number(refundAmount);
  const hasRequestedRefund = Number.isFinite(requestedRefund) && requestedRefund > 0;
  const isPartial = hasRequestedRefund && requestedRefund < maxRefund;
  const retainedAmount = Math.max(maxRefund - (hasRequestedRefund ? requestedRefund : 0), 0);
  const isRefundEligible = appointment?.payment_status === 'paid' && maxRefund > 0;

  const formatCurrency = (value) => `PHP ${Number(value || 0).toFixed(2)}`;

  const handleSubmit = async (e) => {
    e.preventDefault();
    setError('');

    // Validate payment status
    if (appointment?.payment_status !== 'paid') {
      setError('Cannot process refund: This appointment is not marked as paid. Only paid appointments can be refunded.');
      return;
    }

    // Validate payment amount exists
    if (!appointment?.payment_amount || appointment.payment_amount <= 0) {
      setError('Cannot process refund: This appointment has no payment amount recorded. Please contact support.');
      return;
    }

    // Validation
    if (!refundAmount || parseFloat(refundAmount) <= 0) {
      setError('Please enter a valid refund amount');
      return;
    }

    if (parseFloat(refundAmount) > maxRefund) {
      setError(`Refund amount cannot exceed ₱${maxRefund.toFixed(2)}`);
      return;
    }

    if (!reason) {
      setError('Please select a reason');
      return;
    }

    setLoading(true);
    try {
      const response = await axios.post('/api/refunds/request', {
        appointment_id: appointment?.id,
        refund_amount: parseFloat(refundAmount),
        reason,
        description
      });

      if (response.data?.success) {
        if (window?.showToast) {
          window.showToast(
            'Refund Request Submitted',
            'Your refund request has been submitted successfully. Our admin team will review and process it soon.',
            'success'
          );
        } else {
          window.showToast?.('Success', 'Refund request submitted successfully!', 'success');
        }
        onSuccess?.();
        handleClose();
      } else {
        setError(response.data?.message || 'Failed to submit refund request');
      }
    } catch (err) {
      console.error('Refund error:', err);
      setError(err.response?.data?.message || 'Failed to submit refund request. Please try again.');
    } finally {
      setLoading(false);
    }
  };

  const handleClose = () => {
    resetFormState();
    onClose();
  };

  if (!isOpen || !appointment) return null;

  const dm = isDarkMode;

  return (
    <div
      className={`fixed inset-0 z-[70] flex items-end sm:items-center justify-center ${
        dm ? 'bg-black/70' : 'bg-slate-900/40'
      } backdrop-blur-sm`}
    >
      <div
        className={`w-full sm:max-w-lg mx-0 sm:mx-4 rounded-t-[24px] sm:rounded-[20px] border shadow-2xl flex flex-col overflow-hidden ${
          dm
            ? 'bg-gray-950 border-white/10 text-white'
            : 'bg-white border-slate-200 text-slate-900'
        }`}
        style={{ maxHeight: '92dvh' }}
      >

        {/* ── Header ── */}
        <div className={`flex items-center justify-between px-5 py-4 border-b ${dm ? 'border-white/10' : 'border-slate-100'}`}>
          <div className="flex items-center gap-3">
            <div className={`h-9 w-9 rounded-xl flex items-center justify-center shrink-0 ${dm ? 'bg-amber-500/15 text-amber-400' : 'bg-amber-50 text-amber-600'}`}>
              <ReceiptRefundIcon className="h-5 w-5" />
            </div>
            <div>
              <h2 className={`text-base font-semibold leading-tight ${dm ? 'text-white' : 'text-slate-900'}`}>Request a Refund</h2>
              <p className={`text-xs mt-0.5 ${dm ? 'text-gray-400' : 'text-slate-500'}`}>Reviewed by admin before processing</p>
            </div>
          </div>
          <button
            onClick={handleClose}
            disabled={loading}
            aria-label="Close"
            className={`p-2 rounded-xl transition-colors ${dm ? 'text-gray-400 hover:text-white hover:bg-white/8' : 'text-slate-400 hover:text-slate-700 hover:bg-slate-100'}`}
          >
            <XMarkIcon className="h-5 w-5" />
          </button>
        </div>

        {/* ── Scrollable Body ── */}
        <form onSubmit={handleSubmit} className="flex flex-col flex-1 min-h-0 overflow-hidden">
          <div className="flex-1 overflow-y-auto px-5 py-5 space-y-5">

            {/* Appointment Summary Strip */}
            <div className={`rounded-2xl border p-4 ${dm ? 'border-amber-500/20 bg-amber-500/5' : 'border-amber-100 bg-amber-50/60'}`}>
              <p className={`text-[10px] font-bold uppercase tracking-widest mb-2 ${dm ? 'text-amber-400/70' : 'text-amber-600/80'}`}>Appointment</p>
              <p className={`text-sm font-semibold truncate ${dm ? 'text-white' : 'text-slate-900'}`}>
                {appointment.service?.name || appointment.type || 'Appointment'}
              </p>
              <div className={`mt-2 flex flex-wrap gap-x-4 gap-y-1.5 text-xs ${dm ? 'text-gray-300' : 'text-slate-600'}`}>
                <span className="flex items-center gap-1.5">
                  <CalendarDaysIcon className="h-3.5 w-3.5 text-amber-500" />
                  {formatDateDisplay(appointment.appointment_date)}
                </span>
                <span className="flex items-center gap-1.5">
                  <ClockIcon className="h-3.5 w-3.5 text-amber-500" />
                  {formatTime12Hour(appointment.appointment_time)}
                </span>
                <span className="flex items-center gap-1.5">
                  <CurrencyDollarIcon className="h-3.5 w-3.5 text-amber-500" />
                  <span className="font-medium">Paid:</span>&nbsp;{formatCurrency(maxRefund)}
                </span>
              </div>
            </div>

            {/* Eligibility / Error notices */}
            {!isRefundEligible && (
              <div className={`flex items-start gap-3 rounded-xl border px-4 py-3 ${dm ? 'border-red-500/30 bg-red-500/10' : 'border-red-200 bg-red-50'}`}>
                <ExclamationCircleIcon className={`h-5 w-5 mt-0.5 shrink-0 ${dm ? 'text-red-400' : 'text-red-500'}`} />
                <p className={`text-sm ${dm ? 'text-red-300' : 'text-red-700'}`}>
                  This appointment is not eligible for a refund. Only paid appointments with a recorded payment can be submitted.
                </p>
              </div>
            )}

            {error && (
              <div className={`flex items-start gap-3 rounded-xl border px-4 py-3 ${dm ? 'border-red-500/30 bg-red-500/10' : 'border-red-200 bg-red-50'}`}>
                <ExclamationCircleIcon className={`h-5 w-5 mt-0.5 shrink-0 ${dm ? 'text-red-400' : 'text-red-500'}`} />
                <p className={`text-sm ${dm ? 'text-red-300' : 'text-red-700'}`}>{error}</p>
              </div>
            )}

            {/* ── Section 1: Amount ── */}
            <div className="space-y-2">
              <div className="flex items-center justify-between">
                <label className={`text-sm font-semibold ${dm ? 'text-gray-100' : 'text-slate-800'}`}>
                  How much to refund?
                </label>
                <button
                  type="button"
                  onClick={() => setRefundAmount(maxRefund.toFixed(2))}
                  disabled={loading || !isRefundEligible}
                  className={`text-xs font-medium px-3 py-1.5 rounded-full border transition-colors disabled:opacity-40 ${
                    dm
                      ? 'border-amber-500/30 text-amber-400 hover:bg-amber-500/10'
                      : 'border-amber-200 text-amber-700 hover:bg-amber-50'
                  }`}
                >
                  Full amount
                </button>
              </div>

              <div className={`flex items-center gap-3 rounded-xl border px-4 py-3 transition-colors ${
                dm
                  ? 'border-white/10 bg-white/[0.03] focus-within:border-amber-400'
                  : 'border-slate-200 bg-slate-50 focus-within:border-amber-500'
              }`}>
                <span className={`text-sm font-semibold shrink-0 ${dm ? 'text-gray-400' : 'text-slate-500'}`}>PHP</span>
                <input
                  type="number"
                  step="0.01"
                  min="0.01"
                  max={maxRefund}
                  value={refundAmount}
                  onChange={(e) => setRefundAmount(e.target.value)}
                  placeholder="0.00"
                  disabled={loading || !isRefundEligible}
                  className={`flex-1 text-xl font-semibold bg-transparent outline-none placeholder:font-normal ${
                    dm ? 'text-white placeholder:text-gray-600' : 'text-slate-900 placeholder:text-slate-400'
                  } disabled:opacity-50`}
                />
                {hasRequestedRefund && (
                  <span className={`text-xs shrink-0 ${dm ? 'text-gray-500' : 'text-slate-400'}`}>
                    of {formatCurrency(maxRefund)}
                  </span>
                )}
              </div>

              {isPartial && (
                <p className={`text-xs pl-1 ${dm ? 'text-amber-400/80' : 'text-amber-700'}`}>
                  Partial refund — {formatCurrency(retainedAmount)} will be retained.
                </p>
              )}
            </div>

            {/* ── Section 2: Reason ── */}
            <div className="space-y-2">
              <label className={`text-sm font-semibold ${dm ? 'text-gray-100' : 'text-slate-800'}`}>
                Reason for refund
              </label>
              <div className="space-y-1.5">
                {refundReasons.map((option) => {
                  const selected = reason === option.value;
                  return (
                    <label
                      key={option.value}
                      className={`flex items-center gap-3 px-4 py-3 rounded-xl border cursor-pointer transition-colors ${
                        selected
                          ? dm
                            ? 'border-amber-500/60 bg-amber-500/10'
                            : 'border-amber-400 bg-amber-50'
                          : dm
                            ? 'border-white/8 hover:border-white/15 bg-white/[0.02]'
                            : 'border-slate-200 hover:border-slate-300 bg-white'
                      } ${loading || !isRefundEligible ? 'opacity-50 cursor-not-allowed' : ''}`}
                    >
                      <input
                        type="radio"
                        name="refundReason"
                        value={option.value}
                        checked={selected}
                        onChange={(e) => setReason(e.target.value)}
                        disabled={loading || !isRefundEligible}
                        className="accent-amber-500 shrink-0"
                      />
                      <div className="min-w-0">
                        <p className={`text-sm font-medium ${dm ? 'text-gray-100' : 'text-slate-900'}`}>{option.label}</p>
                        <p className={`text-xs mt-0.5 truncate ${dm ? 'text-gray-500' : 'text-slate-500'}`}>
                          {option.value === 'customer_request' && 'Schedule or circumstances changed'}
                          {option.value === 'service_not_provided' && 'Service was not delivered as scheduled'}
                          {option.value === 'duplicate_payment' && 'Charged more than once for this appointment'}
                          {option.value === 'service_cancellation' && 'Service was cancelled before completion'}
                          {option.value === 'poor_service' && 'Delivered service did not meet expectations'}
                          {option.value === 'other' && 'Other reason not listed above'}
                        </p>
                      </div>
                    </label>
                  );
                })}
              </div>
            </div>

            {/* ── Section 3: Description ── */}
            <div className="space-y-2">
              <label className={`text-sm font-semibold ${dm ? 'text-gray-100' : 'text-slate-800'}`}>
                Additional details
                <span className={`ml-2 text-xs font-normal ${dm ? 'text-gray-500' : 'text-slate-400'}`}>(optional)</span>
              </label>
              <textarea
                rows={3}
                value={description}
                onChange={(e) => setDescription(e.target.value)}
                disabled={loading || !isRefundEligible}
                placeholder="Provide any extra context that may help the admin review your request…"
                className={`w-full rounded-xl border px-4 py-3 text-sm resize-none outline-none transition-colors placeholder:text-sm ${
                  dm
                    ? 'border-white/10 bg-white/[0.03] text-white placeholder:text-gray-600 focus:border-amber-400'
                    : 'border-slate-200 bg-slate-50 text-slate-900 placeholder:text-slate-400 focus:border-amber-500'
                } disabled:opacity-50`}
              />
            </div>

            {/* ── What happens next ── */}
            <div className={`rounded-xl border p-4 ${dm ? 'border-white/8 bg-white/[0.02]' : 'border-slate-100 bg-slate-50/60'}`}>
              <p className={`text-xs font-bold uppercase tracking-widest mb-3 ${dm ? 'text-gray-400' : 'text-slate-500'}`}>What happens next</p>
              <ol className="space-y-2">
                {[
                  'Your request is sent to the admin team for review.',
                  'You\'ll be notified once it\'s approved, rejected, or needs clarification.',
                  'Approved refunds are processed within 5–7 business days.',
                ].map((step, i) => (
                  <li key={i} className={`flex items-start gap-3 text-sm ${dm ? 'text-gray-300' : 'text-slate-600'}`}>
                    <span className={`mt-0.5 h-5 w-5 rounded-full flex items-center justify-center text-[10px] font-bold shrink-0 ${dm ? 'bg-amber-500/15 text-amber-400' : 'bg-amber-100 text-amber-700'}`}>
                      {i + 1}
                    </span>
                    {step}
                  </li>
                ))}
              </ol>
            </div>
          </div>

          {/* ── Sticky Footer ── */}
          <div className={`px-5 py-4 border-t flex flex-col-reverse sm:flex-row gap-3 sm:justify-end ${dm ? 'border-white/10 bg-gray-950' : 'border-slate-100 bg-white'}`}>
            <button
              type="button"
              onClick={handleClose}
              disabled={loading}
              className={`px-5 py-2.5 rounded-xl text-sm font-medium border transition-colors disabled:opacity-50 ${
                dm
                  ? 'border-white/10 text-gray-300 hover:bg-white/5'
                  : 'border-slate-200 text-slate-700 bg-white hover:bg-slate-50'
              }`}
            >
              Cancel
            </button>
            <button
              type="submit"
              disabled={loading || !isRefundEligible}
              className="px-5 py-2.5 rounded-xl text-sm font-semibold text-white bg-amber-500 hover:bg-amber-600 disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2 transition-colors"
            >
              {loading ? (
                <>
                  <span className="h-4 w-4 border-2 border-white border-t-transparent rounded-full animate-spin" />
                  Submitting…
                </>
              ) : (
                <>
                  <CheckCircleIcon className="h-4 w-4" />
                  Submit Refund Request
                </>
              )}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
};

export default UserRefundRequest;
