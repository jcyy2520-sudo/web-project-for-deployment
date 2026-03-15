import { useState, useEffect } from 'react';
import { XMarkIcon, ChevronDownIcon, ChevronUpIcon } from '@heroicons/react/24/outline';
import axios from 'axios';
import { formatDateDisplay } from '../../utils/format';

const RefundDetailsModal = ({ isOpen, onClose, appointment, onConfirm }) => {
  const [expandedSections, setExpandedSections] = useState({
    appointmentDetails: true,
    paymentBreakdown: true,
    refundReasons: false,
    customReason: false
  });
  const [selectedReason, setSelectedReason] = useState('customer_request');
  const [customReason, setCustomReason] = useState('');
  const [showReasonForm, setShowReasonForm] = useState(false);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');
  const [countdown, setCountdown] = useState(null);
  const [canSubmit, setCanSubmit] = useState(false);

  const presetRefundReasons = [
    { value: 'customer_request', label: '👤 Customer Request', description: 'General customer-initiated refund' },
    { value: 'service_not_provided', label: '❌ Service Not Provided', description: 'The service was not delivered' },
    { value: 'duplicate_payment', label: '💳 Duplicate Payment', description: 'Payment was charged multiple times' },
    { value: 'service_cancellation', label: '🚫 Service Cancellation', description: 'Service was cancelled before completion' },
    { value: 'poor_service', label: '⭐ Poor Service Quality', description: 'Service did not meet expectations' },
    { value: 'technical_issue', label: '🔧 Technical Issue', description: 'Technical problems prevented service' },
    { value: 'appointment_cancellation', label: '📅 Appointment Cancellation', description: 'Appointment was cancelled' },
    { value: 'other', label: '✏️ Other Reason', description: 'Please specify your reason below' }
  ];

  // Countdown effect when user clicks to confirm
  useEffect(() => {
    let timer;
    if (countdown !== null && countdown > 0) {
      timer = setTimeout(() => {
        setCountdown(countdown - 1);
      }, 1000);
    } else if (countdown === 0) {
      setCanSubmit(true);
    }
    return () => clearTimeout(timer);
  }, [countdown]);

  const toggleSection = (section) => {
    setExpandedSections(prev => ({
      ...prev,
      [section]: !prev[section]
    }));
  };

  const startCountdown = () => {
    setError('');

    if (!selectedReason) {
      setError('Please select a refund reason');
      return;
    }

    if (selectedReason === 'other' && !customReason.trim()) {
      setError('Please provide a reason for "Other"');
      return;
    }

    // Validate payment status
    if (appointment.payment_status !== 'paid') {
      setError('Cannot process refund: This appointment is not marked as paid. Only paid appointments can be refunded.');
      return;
    }

    // Validate refund amount exists
    if (!appointment.payment_amount || appointment.payment_amount <= 0) {
      setError('Cannot process refund: No payment amount found for this appointment. Please contact support to update the payment information.');
      return;
    }

    // Validate calculated refundable amount
    if (refundableAmount <= 0) {
      setError('Cannot process refund: The refundable amount is zero. Please contact support for assistance.');
      return;
    }

    setCountdown(5);
    setCanSubmit(false);
  };

  const handleConfirmRefund = async () => {
    if (!canSubmit) {
      setError('Please wait for the countdown to complete');
      return;
    }

    setLoading(true);
    setError('');

    try {
      const response = await axios.post('/api/refunds/request', {
        appointment_id: appointment.id,
        refund_amount: parseFloat(appointment.payment_amount || 0),
        reason: selectedReason,
        description: customReason.trim()
      });

      if (response.data?.success) {
        if (window?.showToast) {
          window.showToast(
            '✅ Refund Request Submitted',
            'Your refund request has been submitted successfully and is under review. You will receive an email notification.',
            'success'
          );
        } else {
          window.showToast?.('Success', 'Refund request submitted successfully! You will receive an email notification.', 'success');
        }
        onConfirm?.();
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
    setSelectedReason('customer_request');
    setCustomReason('');
    setShowReasonForm(false);
    setError('');
    setCountdown(null);
    setCanSubmit(false);
    onClose();
  };

  if (!isOpen || !appointment) return null;

  // Calculate payment breakdown
  const paymentAmount = parseFloat(appointment.payment_amount || 0);
  const originalPrice = parseFloat(appointment.original_price || paymentAmount || 0);
  const discountAmount = parseFloat(appointment.discount_amount || 0);
  const netTotal = paymentAmount > 0 ? paymentAmount : (originalPrice - discountAmount);
  const refundableAmount = paymentAmount > 0 ? paymentAmount : netTotal;
  
  // Check if payment info is available
  const hasPaymentInfo = paymentAmount > 0 || originalPrice > 0;

  return (
    <div className="fixed inset-0 bg-black bg-opacity-70 flex items-center justify-center z-50 p-4 animate-fadeIn">
      <div className="w-full max-w-2xl bg-white rounded-lg shadow-2xl overflow-hidden max-h-[90vh] overflow-y-auto">
        {/* Header */}
        <div className="sticky top-0 bg-gradient-to-r from-gray-50 to-gray-100 border-b border-gray-200 p-6 flex items-center justify-between z-10">
          <div>
            <h2 className="text-2xl font-bold text-gray-900">💰 Refund Request</h2>
            <p className="text-sm text-gray-600 mt-1">Appointment #{appointment.id}</p>
          </div>
          <button
            onClick={handleClose}
            className="text-gray-500 hover:text-gray-700 p-2 rounded-full hover:bg-gray-200 transition-colors"
          >
            <XMarkIcon className="h-6 w-6" />
          </button>
        </div>

        {/* Content */}
        <div className="p-6 space-y-6">
          {/* Warning Banner for Missing Payment Info */}
          {(!appointment.payment_amount || appointment.payment_amount <= 0) && (
            <div className="p-4 bg-amber-50 border-l-4 border-amber-500 rounded-lg">
              <div className="flex items-start">
                <div className="flex-shrink-0">
                  <svg className="h-5 w-5 text-amber-500" viewBox="0 0 20 20" fill="currentColor">
                    <path fillRule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clipRule="evenodd" />
                  </svg>
                </div>
                <div className="ml-3">
                  <h3 className="text-sm font-medium text-amber-800">Payment Information Missing</h3>
                  <p className="mt-1 text-sm text-amber-700">
                    This appointment has no payment amount recorded (₱0.00). You cannot request a refund until payment information is added. Please contact support for assistance.
                  </p>
                </div>
              </div>
            </div>
          )}

          {/* Warning Banner for Unpaid Status */}
          {appointment.payment_status !== 'paid' && appointment.payment_amount > 0 && (
            <div className="p-4 bg-amber-50 border-l-4 border-amber-500 rounded-lg">
              <div className="flex items-start">
                <div className="flex-shrink-0">
                  <svg className="h-5 w-5 text-amber-500" viewBox="0 0 20 20" fill="currentColor">
                    <path fillRule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clipRule="evenodd" />
                  </svg>
                </div>
                <div className="ml-3">
                  <h3 className="text-sm font-medium text-amber-800">Appointment Not Marked as Paid</h3>
                  <p className="mt-1 text-sm text-amber-700">
                    This appointment's payment status is "{appointment.payment_status}". Only appointments marked as "paid" can be refunded.
                  </p>
                </div>
              </div>
            </div>
          )}

          {error && (
            <div className="p-4 bg-red-50 border border-red-200 rounded-lg">
              <p className="text-sm font-medium text-red-800">{error}</p>
            </div>
          )}

          {/* Appointment Details Section */}
          <div className="border border-gray-200 rounded-lg overflow-hidden">
            <button
              onClick={() => toggleSection('appointmentDetails')}
              className="w-full px-6 py-4 bg-gray-50 hover:bg-gray-100 transition-colors flex items-center justify-between"
            >
              <h3 className="text-lg font-semibold text-gray-900">📅 Appointment Details</h3>
              {expandedSections.appointmentDetails ? (
                <ChevronUpIcon className="h-5 w-5 text-gray-600" />
              ) : (
                <ChevronDownIcon className="h-5 w-5 text-gray-600" />
              )}
            </button>

            {expandedSections.appointmentDetails && (
              <div className="px-6 py-4 space-y-3 border-t">
                <div className="grid grid-cols-2 gap-4">
                  <div>
                    <p className="text-xs font-medium text-gray-500 uppercase">Date & Time</p>
                    <p className="text-sm font-semibold text-gray-900 mt-1">
                      {formatDateDisplay(appointment.appointment_date)} at {appointment.appointment_time}
                    </p>
                  </div>
                  <div>
                    <p className="text-xs font-medium text-gray-500 uppercase">Service Type</p>
                    <p className="text-sm font-semibold text-gray-900 mt-1">
                      {appointment.service?.name || appointment.type || 'N/A'}
                    </p>
                  </div>
                </div>

                {/* User Information */}
                <div className="bg-blue-50 p-4 rounded-lg border border-blue-100">
                  <h4 className="text-sm font-semibold text-blue-900 mb-3">👤 User Information</h4>
                  <div className="space-y-2 text-sm">
                    <div>
                      <p className="text-blue-700">
                        <strong>Name:</strong> {appointment.user?.first_name} {appointment.user?.last_name}
                      </p>
                    </div>
                    <div>
                      <p className="text-blue-700">
                        <strong>Email:</strong> {appointment.user?.email}
                      </p>
                    </div>
                    {appointment.user?.phone && (
                      <div>
                        <p className="text-blue-700">
                          <strong>Phone:</strong> {appointment.user?.phone}
                        </p>
                      </div>
                    )}
                  </div>
                </div>

                {appointment.staff_notes && (
                  <div>
                    <p className="text-xs font-medium text-gray-500 uppercase">Staff Notes</p>
                    <p className="text-sm text-gray-700 mt-1 p-3 bg-gray-50 rounded border border-gray-200">
                      {appointment.staff_notes}
                    </p>
                  </div>
                )}

                {appointment.completion_notes && (
                  <div>
                    <p className="text-xs font-medium text-gray-500 uppercase">Completion Notes</p>
                    <p className="text-sm text-gray-700 mt-1 p-3 bg-green-50 rounded border border-green-200">
                      {appointment.completion_notes}
                    </p>
                  </div>
                )}
              </div>
            )}
          </div>

          {/* Payment Breakdown Section */}
          <div className="border border-gray-200 rounded-lg overflow-hidden">
            <button
              onClick={() => toggleSection('paymentBreakdown')}
              className="w-full px-6 py-4 bg-gray-50 hover:bg-gray-100 transition-colors flex items-center justify-between"
            >
              <h3 className="text-lg font-semibold text-gray-900">💳 Payment Breakdown</h3>
              {expandedSections.paymentBreakdown ? (
                <ChevronUpIcon className="h-5 w-5 text-gray-600" />
              ) : (
                <ChevronDownIcon className="h-5 w-5 text-gray-600" />
              )}
            </button>

            {expandedSections.paymentBreakdown && (
              <div className="px-6 py-4 space-y-3 border-t">
                {!hasPaymentInfo ? (
                  <div className="p-4 bg-yellow-50 border border-yellow-200 rounded-lg">
                    <p className="text-sm text-yellow-800 font-semibold">⚠️ Payment Information Not Available</p>
                    <p className="text-xs text-yellow-700 mt-1">
                      This appointment may not have been processed for payment yet. Please contact support to request a refund.
                    </p>
                  </div>
                ) : (
                  <div className="space-y-2">
                    {originalPrice > 0 && originalPrice !== paymentAmount && (
                      <div className="flex justify-between text-sm">
                        <span className="text-gray-700">Original Price:</span>
                        <span className="font-semibold text-gray-900">
                          ₱{originalPrice.toFixed(2)}
                        </span>
                      </div>
                    )}

                    {discountAmount > 0 && (
                      <div className="flex justify-between text-sm">
                        <span className="text-gray-700">Discount Applied:</span>
                        <span className="font-semibold text-red-600">
                          -₱{discountAmount.toFixed(2)}
                        </span>
                      </div>
                    )}

                    {paymentAmount > 0 && (
                      <div className="flex justify-between text-sm">
                        <span className="text-gray-700 font-semibold">Price Entered by Cashier:</span>
                        <span className="font-semibold text-gray-900">
                          ₱{paymentAmount.toFixed(2)}
                        </span>
                      </div>
                    )}

                    {appointment.payment_type && (
                      <div className="flex justify-between text-sm">
                        <span className="text-gray-700">Payment Type:</span>
                        <span className="font-semibold text-gray-900 capitalize">
                          {appointment.payment_type.replace(/_/g, ' ')}
                        </span>
                      </div>
                    )}

                    {appointment.payment_date && (
                      <div className="flex justify-between text-sm">
                        <span className="text-gray-700">Payment Date:</span>
                        <span className="text-gray-900">
                          {new Date(appointment.payment_date).toLocaleDateString()}
                        </span>
                      </div>
                    )}

                    <div className="border-t pt-2 flex justify-between text-sm font-semibold">
                      <span className="text-gray-900">Net Total Paid:</span>
                      <span className="text-gray-900">₱{netTotal.toFixed(2)}</span>
                    </div>

                    <div className="bg-green-50 p-3 rounded-lg border border-green-200 flex justify-between text-sm">
                      <span className="font-semibold text-green-900">Total Refundable Amount:</span>
                      <span className="text-lg font-bold text-green-700">
                        ₱{refundableAmount.toFixed(2)}
                      </span>
                    </div>
                  </div>
                )}

                {appointment.cashier && (
                  <div className="text-xs text-gray-600 pt-2 border-t">
                    <p>
                      <strong>Processed by Cashier:</strong> {appointment.cashier.first_name} {appointment.cashier.last_name}
                    </p>
                    {appointment.payment_date && (
                      <p className="mt-1">
                        <strong>Payment Date:</strong> {new Date(appointment.payment_date).toLocaleDateString()}
                      </p>
                    )}
                  </div>
                )}
              </div>
            )}
          </div>

          {/* Refund Reason Section */}
          {!showReasonForm ? (
            !hasPaymentInfo ? (
              <div className="p-4 bg-red-50 border border-red-200 rounded-lg">
                <p className="text-sm text-red-800">
                  <strong>❌ Cannot Process Refund:</strong> This appointment does not have payment information recorded. Please contact support for assistance.
                </p>
              </div>
            ) : (
              <div className="p-4 bg-blue-50 border border-blue-200 rounded-lg">
                <p className="text-sm text-blue-800">
                  <strong>ℹ️ Next Step:</strong> Click "Continue to Refund Request" below to provide your refund reason.
                </p>
              </div>
            )
          ) : (
            <div className="border border-gray-200 rounded-lg overflow-hidden">
              <button
                onClick={() => toggleSection('refundReasons')}
                className="w-full px-6 py-4 bg-gray-50 hover:bg-gray-100 transition-colors flex items-center justify-between"
              >
                <h3 className="text-lg font-semibold text-gray-900">📝 Refund Reason</h3>
                {expandedSections.refundReasons ? (
                  <ChevronUpIcon className="h-5 w-5 text-gray-600" />
                ) : (
                  <ChevronDownIcon className="h-5 w-5 text-gray-600" />
                )}
              </button>

              {expandedSections.refundReasons && (
                <div className="px-6 py-4 space-y-4 border-t">
                  <div>
                    <label className="block text-sm font-semibold text-gray-900 mb-3">
                      Select a Reason for Refund <span className="text-red-500">*</span>
                    </label>
                    <div className="space-y-2">
                      {presetRefundReasons.map(reason => (
                        <label 
                          key={reason.value} 
                          className={`flex flex-col p-4 border-2 rounded-lg cursor-pointer transition-all ${
                            selectedReason === reason.value 
                              ? 'border-gray-900 bg-gray-50 shadow-sm' 
                              : 'border-gray-200 hover:bg-gray-50 hover:border-gray-400'
                          }`}
                        >
                          <div className="flex items-center">
                            <input
                              type="radio"
                              name="refund_reason"
                              value={reason.value}
                              checked={selectedReason === reason.value}
                              onChange={(e) => setSelectedReason(e.target.value)}
                              className="w-5 h-5 text-gray-900 cursor-pointer focus:ring-2 focus:ring-gray-500"
                            />
                            <span className="ml-3 text-sm font-semibold text-gray-900">
                              {reason.label}
                            </span>
                          </div>
                          <p className="ml-8 text-xs text-gray-600 mt-1">
                            {reason.description}
                          </p>
                        </label>
                      ))}
                    </div>
                  </div>

                  <div>
                    <label className="block text-sm font-semibold text-gray-900 mb-2">
                      Additional Details {selectedReason === 'other' && <span className="text-red-500">*</span>}
                    </label>
                      <textarea
                        value={customReason}
                        onChange={(e) => setCustomReason(e.target.value.slice(0, 500))}
                        placeholder={
                          selectedReason === 'other' 
                            ? 'Please explain your reason for requesting a refund...' 
                            : 'Optional: Provide any additional details about your refund request...'
                        }
                        rows="4"
                        className="w-full px-4 py-3 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-gray-500 focus:border-gray-500"
                      />
                    <p className="text-xs text-gray-500 mt-1">
                      {customReason.length}/500 characters
                    </p>
                  </div>
                </div>
              )}
            </div>
          )}

          {/* Countdown Timer */}
          {countdown !== null && countdown > 0 && (
            <div className="p-6 bg-gradient-to-r from-blue-50 to-indigo-50 border-2 border-blue-200 rounded-lg">
              <div className="flex items-center justify-center space-x-4">
                <div className="flex items-center justify-center w-16 h-16 bg-white rounded-full shadow-lg border-2 border-blue-300">
                  <span className="text-3xl font-bold text-blue-600">{countdown}</span>
                </div>
                <div>
                  <p className="text-lg font-bold text-gray-900">⏳ Please Review Your Request</p>
                  <p className="text-sm text-gray-700 mt-1">
                    Take a moment to double-check the details before confirming...
                  </p>
                </div>
              </div>
            </div>
          )}

          {/* Action Buttons */}
          <div className="flex gap-3 pt-6 border-t">
            {!showReasonForm ? (
              <>
                <button
                  onClick={handleClose}
                  className="flex-1 px-4 py-3 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors"
                >
                  Cancel
                </button>
                <button
                  onClick={() => {
                    setShowReasonForm(true);
                    setExpandedSections(prev => ({ ...prev, refundReasons: true }));
                  }}
                  disabled={!hasPaymentInfo}
                  className="flex-1 px-4 py-3 text-sm font-medium text-white bg-black rounded-lg hover:bg-gray-800 disabled:bg-gray-400 disabled:cursor-not-allowed transition-colors shadow-lg"
                >
                  Continue to Refund Request →
                </button>
              </>
            ) : countdown === null ? (
              <>
                <button
                  onClick={() => {
                    setShowReasonForm(false);
                    setError('');
                  }}
                  className="flex-1 px-4 py-3 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors"
                  disabled={loading}
                >
                  ← Back
                </button>
                <button
                  onClick={startCountdown}
                  disabled={loading}
                  className="flex-1 px-4 py-3 text-sm font-medium text-white bg-black rounded-lg hover:bg-gray-800 disabled:opacity-50 disabled:cursor-not-allowed transition-colors shadow-lg"
                >
                  Review & Submit Refund Request
                </button>
              </>
            ) : (
              <>
                <button
                  onClick={() => {
                    setCountdown(null);
                    setCanSubmit(false);
                  }}
                  disabled={loading}
                  className="flex-1 px-4 py-3 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors"
                >
                  Cancel
                </button>
                <button
                  onClick={handleConfirmRefund}
                  disabled={!canSubmit || loading}
                  className={`flex-1 px-4 py-3 text-sm font-medium text-white rounded-lg transition-all shadow-lg ${
                    canSubmit && !loading
                      ? 'bg-green-600 hover:bg-green-700 animate-pulse'
                      : 'bg-gray-400 cursor-not-allowed'
                  }`}
                >
                  {loading ? (
                    <span className="flex items-center justify-center">
                      <svg className="animate-spin h-5 w-5 mr-2" viewBox="0 0 24 24">
                        <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" fill="none" />
                        <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                      </svg>
                      Processing...
                    </span>
                  ) : canSubmit ? (
                    '✓ Confirm Refund Request'
                  ) : (
                    `Wait ${countdown}s...`
                  )}
                </button>
              </>
            )}
          </div>

          {/* Info Message */}
          <div className="space-y-3">
            <div className="p-4 bg-blue-50 border border-blue-200 rounded-lg">
              <p className="text-sm font-semibold text-blue-900 mb-2">📧 Email Notifications</p>
              <ul className="text-xs text-blue-800 space-y-1 list-disc list-inside">
                <li><strong>Request Submitted:</strong> Immediate confirmation email</li>
                <li><strong>Under Review:</strong> Admin team will review your request</li>
                <li><strong>Decision Made:</strong> Approval or additional information needed</li>
              </ul>
            </div>

            <div className="p-4 bg-yellow-50 border border-yellow-200 rounded-lg">
              <p className="text-xs text-yellow-800">
                <strong>⏱️ Processing Time:</strong> Refund requests are typically reviewed within 1-3 business days. 
                You'll receive email updates at each stage of the process.
              </p>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
};

export default RefundDetailsModal;
