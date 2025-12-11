import { useState } from 'react';
import { XMarkIcon } from '@heroicons/react/24/outline';
import axios from 'axios';

const UserRefundRequest = ({ isOpen, onClose, appointment, onSuccess }) => {
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

  const isPartial = parseFloat(refundAmount) < parseFloat(appointment?.payment_amount || 0);
  const maxRefund = appointment?.payment_amount || 0;

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
      const response = await axios.post('/api/cashier/refunds/request', {
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
          alert('Refund request submitted successfully!');
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
    setRefundAmount(appointment?.payment_amount || '');
    setReason('customer_request');
    setDescription('');
    setError('');
    onClose();
  };

  if (!isOpen || !appointment) return null;

  return (
    <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
      <div className="bg-white rounded-lg shadow-xl max-w-md w-full">
        {/* Header */}
        <div className="flex items-center justify-between p-6 border-b border-gray-200">
          <div>
            <h2 className="text-xl font-semibold text-gray-900">Request Refund</h2>
            <p className="text-sm text-gray-500 mt-1">Submit a refund request for review</p>
          </div>
          <button
            onClick={handleClose}
            className="text-gray-400 hover:text-gray-600 p-1 hover:bg-gray-100 rounded transition-colors"
            disabled={loading}
          >
            <XMarkIcon className="h-5 w-5" />
          </button>
        </div>

        {/* Content */}
        <form onSubmit={handleSubmit} className="p-6 space-y-4">
          {/* Appointment Info */}
          <div className="bg-blue-50 border border-blue-200 rounded-lg p-3 space-y-2">
            <div>
              <p className="text-xs text-blue-600 font-medium">SERVICE & DATE</p>
              <p className="text-sm font-medium text-gray-900 mt-1">
                {appointment.service?.name || 'Appointment'}
              </p>
              <p className="text-xs text-gray-600 mt-1">
                {new Date(appointment.appointment_date).toLocaleDateString()} at{' '}
                {appointment.appointment_time}
              </p>
            </div>
            <div>
              <p className="text-xs text-blue-600 font-medium">ORIGINAL PAYMENT</p>
              <p className="text-lg font-semibold text-gray-900 mt-1">
                ₱{parseFloat(appointment.payment_amount || 0).toFixed(2)}
              </p>
            </div>
          </div>

          {/* Error Message */}
          {error && (
            <div className="bg-red-50 border border-red-200 rounded-lg p-3">
              <p className="text-sm text-red-700">{error}</p>
            </div>
          )}

          {/* Refund Amount */}
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-2">
              Refund Amount
            </label>
            <div className="flex items-center gap-2">
              <span className="text-gray-600 font-medium">₱</span>
              <input
                type="number"
                step="0.01"
                min="0.01"
                max={maxRefund}
                value={refundAmount}
                onChange={(e) => setRefundAmount(e.target.value)}
                className="flex-1 px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                placeholder="0.00"
                disabled={loading}
              />
            </div>
            {isPartial && (
              <div className="mt-2 text-xs text-amber-600 bg-amber-50 p-2 rounded">
                ℹ️ This will be a partial refund. ₱{(maxRefund - parseFloat(refundAmount)).toFixed(2)} will be retained.
              </div>
            )}
          </div>

          {/* Reason */}
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-2">
              Reason for Refund
            </label>
            <select
              value={reason}
              onChange={(e) => setReason(e.target.value)}
              className="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
              disabled={loading}
            >
              {refundReasons.map((opt) => (
                <option key={opt.value} value={opt.value}>
                  {opt.label}
                </option>
              ))}
            </select>
          </div>

          {/* Description */}
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-2">
              Additional Details (Optional)
            </label>
            <textarea
              rows="3"
              value={description}
              onChange={(e) => setDescription(e.target.value)}
              className="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent resize-none"
              placeholder="Please explain why you're requesting this refund. This will help us process your request faster."
              disabled={loading}
            />
          </div>

          {/* Info Box */}
          <div className="bg-gray-50 rounded-lg p-3 text-xs text-gray-600 space-y-1">
            <p className="font-medium text-gray-700">📋 What happens next?</p>
            <ul className="list-disc list-inside space-y-1">
              <li>Your refund request will be reviewed by our admin team</li>
              <li>You'll be notified of approval or rejection via message</li>
              <li>Approved refunds are processed within 5-7 business days</li>
            </ul>
          </div>

          {/* Buttons */}
          <div className="flex gap-3 pt-4 border-t border-gray-200">
            <button
              type="button"
              onClick={handleClose}
              className="flex-1 px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
              disabled={loading}
            >
              Cancel
            </button>
            <button
              type="submit"
              disabled={loading}
              className="flex-1 px-4 py-2 text-sm font-medium text-white bg-blue-600 border border-blue-600 rounded-lg hover:bg-blue-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2"
            >
              {loading ? (
                <>
                  <div className="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></div>
                  Submitting...
                </>
              ) : (
                '✓ Submit Refund Request'
              )}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
};

export default UserRefundRequest;
