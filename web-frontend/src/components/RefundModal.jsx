import { useState } from 'react';
import { XMarkIcon } from '@heroicons/react/24/outline';
import axios from 'axios';

const RefundModal = ({ isOpen, onClose, transaction, onSuccess }) => {
  const [refundAmount, setRefundAmount] = useState(transaction?.payment_amount || '');
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
    { value: 'other', label: 'Other' }
  ];

  const isPartial = parseFloat(refundAmount) < parseFloat(transaction?.payment_amount || 0);
  const maxRefund = transaction?.payment_amount || 0;

  const handleSubmit = async (e) => {
    e.preventDefault();
    setError('');

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
        appointment_id: transaction?.id,
        refund_amount: parseFloat(refundAmount),
        reason,
        description
      });

      if (response.data?.success) {
        if (window?.showToast) {
          window.showToast('Refund', 'Refund request submitted successfully', 'success');
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
    setRefundAmount(transaction?.payment_amount || '');
    setReason('customer_request');
    setDescription('');
    setError('');
    onClose();
  };

  if (!isOpen || !transaction) return null;

  return (
    <div className="fixed inset-0 bg-black bg-opacity-70 flex items-center justify-center z-50 p-4 animate-fadeIn">
      <div className="w-full max-w-md bg-gray-900 border border-amber-500/20 rounded-lg shadow-2xl overflow-hidden">
        {/* Header */}
        <div className="p-4 border-b border-amber-500/20 flex items-center justify-between bg-gradient-to-r from-gray-800 to-gray-900">
          <div>
            <div className="text-sm font-semibold text-amber-400">💰 REFUND REQUEST</div>
            <div className="text-xs text-gray-400 mt-0.5">Reference #{transaction.id}</div>
          </div>
          <button onClick={handleClose} className="text-gray-400 hover:text-amber-400 p-2 rounded hover:bg-gray-800 transition-colors">
            <XMarkIcon className="h-5 w-5" />
          </button>
        </div>

        {/* Content */}
        <form onSubmit={handleSubmit} className="p-6 space-y-4">
          {/* Transaction Info */}
          <div className="bg-gray-800 border border-gray-700 rounded-lg p-3">
            <div className="space-y-2 text-sm">
              <div className="flex justify-between">
                <span className="text-gray-400">Client</span>
                <span className="text-amber-50 font-medium">
                  {transaction.user?.first_name} {transaction.user?.last_name}
                </span>
              </div>
              <div className="flex justify-between">
                <span className="text-gray-400">Service</span>
                <span className="text-amber-50 font-medium">{transaction.service?.name || 'N/A'}</span>
              </div>
              <div className="flex justify-between">
                <span className="text-gray-400">Original Amount</span>
                <span className="text-amber-400 font-semibold">₱{parseFloat(transaction.payment_amount || 0).toFixed(2)}</span>
              </div>
            </div>
          </div>

          {/* Error Message */}
          {error && (
            <div className="bg-red-500/10 border border-red-500/30 rounded p-3">
              <p className="text-red-400 text-xs">{error}</p>
            </div>
          )}

          {/* Refund Amount */}
          <div>
            <label className="block text-xs text-gray-400 mb-2 font-medium">
              Refund Amount
            </label>
            <div className="flex items-center gap-2">
              <span className="text-gray-400">₱</span>
              <input
                type="number"
                step="0.01"
                min="0.01"
                max={maxRefund}
                value={refundAmount}
                onChange={(e) => setRefundAmount(e.target.value)}
                className="flex-1 px-3 py-2 bg-gray-800 border border-gray-700 rounded text-sm text-white focus:outline-none focus:border-amber-500"
                placeholder="0.00"
                disabled={loading}
              />
            </div>
            {isPartial && (
              <div className="mt-2 text-xs text-amber-400">
                ℹ️ This will be a partial refund
              </div>
            )}
          </div>

          {/* Reason */}
          <div>
            <label className="block text-xs text-gray-400 mb-2 font-medium">
              Reason for Refund
            </label>
            <select
              value={reason}
              onChange={(e) => setReason(e.target.value)}
              className="w-full px-3 py-2 bg-gray-800 border border-gray-700 rounded text-sm text-white focus:outline-none focus:border-amber-500"
              disabled={loading}
            >
              {refundReasons.map(r => (
                <option key={r.value} value={r.value}>{r.label}</option>
              ))}
            </select>
          </div>

          {/* Description */}
          <div>
            <label className="block text-xs text-gray-400 mb-2 font-medium">
              Additional Details (Optional)
            </label>
            <textarea
              rows="3"
              value={description}
              onChange={(e) => setDescription(e.target.value)}
              className="w-full px-3 py-2 bg-gray-800 border border-gray-700 rounded text-sm text-white focus:outline-none focus:border-amber-500"
              placeholder="Provide more context about this refund..."
              disabled={loading}
            />
          </div>

          {/* Buttons */}
          <div className="flex gap-2 pt-2">
            <button
              type="button"
              onClick={handleClose}
              disabled={loading}
              className="flex-1 px-3 py-2 bg-gray-800 hover:bg-gray-700 disabled:bg-gray-800 text-gray-200 rounded text-sm font-medium transition-colors"
            >
              Cancel
            </button>
            <button
              type="submit"
              disabled={loading}
              className="flex-1 px-3 py-2 bg-amber-600 hover:bg-amber-700 disabled:bg-amber-700/50 text-white rounded text-sm font-medium transition-colors flex items-center justify-center gap-2"
            >
              {loading ? (
                <>
                  <div className="w-3 h-3 border-2 border-white border-t-transparent rounded-full animate-spin"></div>
                  Submitting...
                </>
              ) : (
                'Submit Refund Request'
              )}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
};

export default RefundModal;
