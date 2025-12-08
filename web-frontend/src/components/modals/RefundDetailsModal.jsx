import { useState } from 'react';
import { XMarkIcon, ChevronDownIcon, ChevronUpIcon } from '@heroicons/react/24/outline';
import axios from 'axios';

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

  const presetRefundReasons = [
    { value: 'customer_request', label: 'Customer Request' },
    { value: 'service_not_provided', label: 'Service Not Provided' },
    { value: 'duplicate_payment', label: 'Duplicate Payment' },
    { value: 'service_cancellation', label: 'Service Cancellation' },
    { value: 'poor_service', label: 'Poor Service Quality' },
    { value: 'technical_issue', label: 'Technical Issue' },
    { value: 'appointment_cancellation', label: 'Appointment Cancellation' },
    { value: 'other', label: 'Other Reason' }
  ];

  const toggleSection = (section) => {
    setExpandedSections(prev => ({
      ...prev,
      [section]: !prev[section]
    }));
  };

  const handleConfirmRefund = async () => {
    setError('');

    if (!selectedReason) {
      setError('Please select a refund reason');
      return;
    }

    if (selectedReason === 'other' && !customReason.trim()) {
      setError('Please provide a reason for "Other"');
      return;
    }

    setLoading(true);
    try {
      const response = await axios.post('/api/cashier/refunds/request', {
        appointment_id: appointment.id,
        refund_amount: parseFloat(appointment.payment_amount || 0),
        reason: selectedReason,
        description: customReason
      });

      if (response.data?.success) {
        if (window?.showToast) {
          window.showToast(
            'Refund Request',
            'Your refund request has been submitted successfully',
            'success'
          );
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
    onClose();
  };

  if (!isOpen || !appointment) return null;

  const originalPrice = parseFloat(appointment.original_price || appointment.payment_amount || 0);
  const discountAmount = parseFloat(appointment.discount_amount || 0);
  const netTotal = originalPrice - discountAmount;
  const refundableAmount = netTotal;

  return (
    <div className="fixed inset-0 bg-black bg-opacity-70 flex items-center justify-center z-50 p-4 animate-fadeIn">
      <div className="w-full max-w-2xl bg-white rounded-lg shadow-2xl overflow-hidden max-h-[90vh] overflow-y-auto">
        {/* Header */}
        <div className="sticky top-0 bg-gradient-to-r from-amber-50 to-amber-100 border-b border-amber-200 p-6 flex items-center justify-between z-10">
          <div>
            <h2 className="text-2xl font-bold text-amber-900">💰 Refund Request</h2>
            <p className="text-sm text-amber-700 mt-1">Appointment #{appointment.id}</p>
          </div>
          <button
            onClick={handleClose}
            className="text-amber-600 hover:text-amber-800 p-2 rounded hover:bg-amber-200 transition-colors"
          >
            <XMarkIcon className="h-6 w-6" />
          </button>
        </div>

        {/* Content */}
        <div className="p-6 space-y-6">
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
                      {new Date(appointment.appointment_date).toLocaleDateString('en-US', {
                        weekday: 'long',
                        month: 'short',
                        day: 'numeric',
                        year: 'numeric'
                      })} at {appointment.appointment_time}
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
                <div className="space-y-2">
                  <div className="flex justify-between text-sm">
                    <span className="text-gray-700">Original Price:</span>
                    <span className="font-semibold text-gray-900">
                      ₱{originalPrice.toFixed(2)}
                    </span>
                  </div>

                  {discountAmount > 0 && (
                    <div className="flex justify-between text-sm">
                      <span className="text-gray-700">Discount Applied:</span>
                      <span className="font-semibold text-red-600">
                        -₱{discountAmount.toFixed(2)}
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

                  <div className="border-t pt-2 flex justify-between text-sm font-semibold">
                    <span className="text-gray-900">Net Total:</span>
                    <span className="text-amber-600">₱{netTotal.toFixed(2)}</span>
                  </div>

                  <div className="bg-amber-50 p-3 rounded-lg border border-amber-200 flex justify-between text-sm">
                    <span className="font-semibold text-amber-900">Total Refundable Amount:</span>
                    <span className="text-lg font-bold text-amber-700">
                      ₱{refundableAmount.toFixed(2)}
                    </span>
                  </div>
                </div>

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
            <div className="p-4 bg-amber-50 border border-amber-200 rounded-lg">
              <p className="text-sm text-amber-800 mb-3">
                <strong>Next Step:</strong> Click "Submit Refund Request" below to provide your refund reason.
              </p>
            </div>
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
                      Select a Reason for Refund
                    </label>
                    <div className="space-y-2">
                      {presetRefundReasons.map(reason => (
                        <label key={reason.value} className="flex items-center p-3 border border-gray-200 rounded-lg hover:bg-gray-50 cursor-pointer transition-colors">
                          <input
                            type="radio"
                            name="refund_reason"
                            value={reason.value}
                            checked={selectedReason === reason.value}
                            onChange={(e) => setSelectedReason(e.target.value)}
                            className="w-4 h-4 text-amber-600 cursor-pointer"
                          />
                          <span className="ml-3 text-sm font-medium text-gray-900">
                            {reason.label}
                          </span>
                        </label>
                      ))}
                    </div>
                  </div>

                  {(selectedReason === 'other' || customReason) && (
                    <div>
                      <label className="block text-sm font-semibold text-gray-900 mb-2">
                        Additional Details
                      </label>
                      <textarea
                        value={customReason}
                        onChange={(e) => setCustomReason(e.target.value)}
                        placeholder="Please provide more details about your refund request..."
                        rows="4"
                        className="w-full px-4 py-3 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-amber-500 focus:border-transparent"
                      />
                      <p className="text-xs text-gray-500 mt-1">
                        {customReason.length}/500 characters
                      </p>
                    </div>
                  )}
                </div>
              )}
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
                  onClick={() => setShowReasonForm(true)}
                  className="flex-1 px-4 py-3 text-sm font-medium text-white bg-amber-600 rounded-lg hover:bg-amber-700 transition-colors"
                >
                  Submit Refund Request
                </button>
              </>
            ) : (
              <>
                <button
                  onClick={() => setShowReasonForm(false)}
                  className="flex-1 px-4 py-3 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors"
                >
                  Back
                </button>
                <button
                  onClick={handleConfirmRefund}
                  disabled={loading}
                  className="flex-1 px-4 py-3 text-sm font-medium text-white bg-green-600 rounded-lg hover:bg-green-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                >
                  {loading ? 'Processing...' : 'Confirm Refund Request'}
                </button>
              </>
            )}
          </div>

          {/* Info Message */}
          <div className="p-4 bg-blue-50 border border-blue-200 rounded-lg">
            <p className="text-xs text-blue-800">
              <strong>ℹ️ Important:</strong> Once submitted, your refund request will be reviewed by our admin team. 
              You will receive an email notification with the status of your request (approved or declined).
              Processing usually takes 3-5 business days.
            </p>
          </div>
        </div>
      </div>
    </div>
  );
};

export default RefundDetailsModal;
