import { useState, useEffect } from 'react';
import axios from 'axios';
import {
  CheckCircleIcon,
  XCircleIcon,
  ClockIcon,
  ExclamationTriangleIcon,
  ChevronLeftIcon,
  ChevronRightIcon
} from '@heroicons/react/24/outline';
import LoadingSpinner from './LoadingSpinner';

const UserRefundHistory = () => {
  const [refunds, setRefunds] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [page, setPage] = useState(1);
  const [totalPages, setTotalPages] = useState(1);
  const [statusFilter, setStatusFilter] = useState('all');

  const refundsPerPage = 5;

  useEffect(() => {
    loadRefunds();
  }, [statusFilter, page]);

  const loadRefunds = async () => {
    setLoading(true);
    setError('');
    try {
      const response = await axios.get('/api/user/refunds', {
        params: {
          status: statusFilter !== 'all' ? statusFilter : undefined,
          page,
          per_page: refundsPerPage
        }
      });

      if (response.data) {
        // Handle paginated response
        if (response.data.data) {
          setRefunds(response.data.data);
          setTotalPages(response.data.last_page || 1);
        } else {
          // Fallback for different response format
          setRefunds(response.data);
          setTotalPages(1);
        }
      }
    } catch (err) {
      console.error('Failed to load refunds:', err);
      setError('Failed to load refund history');
    } finally {
      setLoading(false);
    }
  };

  const getStatusIcon = (status) => {
    switch (status) {
      case 'pending':
        return <ClockIcon className="h-5 w-5 text-yellow-500" />;
      case 'approved':
        return <ExclamationTriangleIcon className="h-5 w-5 text-blue-500" />;
      case 'completed':
        return <CheckCircleIcon className="h-5 w-5 text-green-500" />;
      case 'rejected':
        return <XCircleIcon className="h-5 w-5 text-red-500" />;
      default:
        return null;
    }
  };

  const getStatusLabel = (status) => {
    switch (status) {
      case 'pending':
        return 'Awaiting Review';
      case 'approved':
        return 'Approved (Processing)';
      case 'completed':
        return 'Completed';
      case 'rejected':
        return 'Rejected';
      default:
        return status;
    }
  };

  const getStatusColor = (status) => {
    switch (status) {
      case 'pending':
        return 'bg-yellow-50 border-yellow-200 text-yellow-800';
      case 'approved':
        return 'bg-blue-50 border-blue-200 text-blue-800';
      case 'completed':
        return 'bg-green-50 border-green-200 text-green-800';
      case 'rejected':
        return 'bg-red-50 border-red-200 text-red-800';
      default:
        return 'bg-gray-50 border-gray-200 text-gray-800';
    }
  };

  if (loading) {
    return (
      <div className="flex justify-center py-12">
        <LoadingSpinner />
      </div>
    );
  }

  return (
    <div className="space-y-4">
      {/* Header */}
      <div className="flex justify-between items-center">
        <div>
          <h3 className="text-lg font-semibold text-gray-900">Refund History</h3>
          <p className="text-sm text-gray-500 mt-1">Track all your refund requests</p>
        </div>
      </div>

      {/* Filter */}
      <div className="flex gap-2">
        {['all', 'pending', 'approved', 'completed', 'rejected'].map((status) => (
          <button
            key={status}
            onClick={() => {
              setStatusFilter(status);
              setPage(1);
            }}
            className={`px-3 py-1.5 rounded-full text-xs font-medium transition-colors ${
              statusFilter === status
                ? 'bg-blue-600 text-white'
                : 'bg-gray-200 text-gray-700 hover:bg-gray-300'
            }`}
          >
            {status === 'all' ? 'All' : getStatusLabel(status)}
          </button>
        ))}
      </div>

      {/* Error Message */}
      {error && (
        <div className="bg-red-50 border border-red-200 rounded-lg p-4 text-sm text-red-700">
          {error}
        </div>
      )}

      {/* Refunds List */}
      {refunds.length === 0 ? (
        <div className="bg-gray-50 border border-gray-200 rounded-lg p-8 text-center">
          <p className="text-gray-600">
            {statusFilter === 'all'
              ? 'No refund requests yet'
              : `No ${statusFilter} refund requests`}
          </p>
        </div>
      ) : (
        <div className="space-y-3">
          {refunds.map((refund) => (
            <div
              key={refund.id}
              className={`border rounded-lg p-4 ${getStatusColor(refund.status)}`}
            >
              <div className="flex items-start justify-between gap-4">
                <div className="flex items-start gap-3 flex-1">
                  {getStatusIcon(refund.status)}
                  <div className="flex-1">
                    <div className="flex items-center gap-2">
                      <p className="font-medium">
                        {refund.appointment?.service?.name || 'Appointment'}
                      </p>
                      <span className="text-xs px-2 py-1 bg-white bg-opacity-60 rounded">
                        {getStatusLabel(refund.status)}
                      </span>
                    </div>
                    <p className="text-sm mt-1">
                      {new Date(refund.appointment?.appointment_date).toLocaleDateString()}{' '}
                      at {refund.appointment?.appointment_time}
                    </p>
                    <p className="text-xs mt-1">
                      Reason:{' '}
                      <span className="font-medium">
                        {refund.reason.replace(/_/g, ' ')}
                      </span>
                    </p>
                    {refund.description && (
                      <p className="text-xs mt-1 italic">"{refund.description}"</p>
                    )}
                  </div>
                </div>
                <div className="text-right">
                  <p className="text-lg font-semibold">
                    ₱{parseFloat(refund.refund_amount).toFixed(2)}
                  </p>
                  {refund.is_partial && (
                    <p className="text-xs text-gray-600">Partial</p>
                  )}
                  <p className="text-xs text-gray-600 mt-2">
                    {new Date(refund.created_at).toLocaleDateString()}
                  </p>
                </div>
              </div>

              {/* Rejection Reason */}
              {refund.status === 'rejected' && refund.rejection_reason && (
                <div className="mt-3 pt-3 border-t border-current border-opacity-20">
                  <p className="text-xs font-semibold">Rejection Reason:</p>
                  <p className="text-sm mt-1">{refund.rejection_reason}</p>
                </div>
              )}

              {/* Approval Notes */}
              {refund.approval_notes && (
                <div className="mt-3 pt-3 border-t border-current border-opacity-20">
                  <p className="text-xs font-semibold">Admin Notes:</p>
                  <p className="text-sm mt-1">{refund.approval_notes}</p>
                </div>
              )}

              {/* Completion Info */}
              {refund.status === 'completed' && refund.completed_at && (
                <div className="mt-3 pt-3 border-t border-current border-opacity-20">
                  <p className="text-xs font-semibold">
                    Completed on {new Date(refund.completed_at).toLocaleDateString()}
                  </p>
                  {refund.transaction_id && (
                    <p className="text-xs mt-1">
                      Transaction ID: <span className="font-mono">{refund.transaction_id}</span>
                    </p>
                  )}
                </div>
              )}
            </div>
          ))}
        </div>
      )}

      {/* Pagination */}
      {totalPages > 1 && (
        <div className="flex items-center justify-between pt-4">
          <p className="text-sm text-gray-600">
            Page {page} of {totalPages}
          </p>
          <div className="flex gap-2">
            <button
              onClick={() => setPage(Math.max(1, page - 1))}
              disabled={page === 1}
              className="p-2 border border-gray-300 rounded hover:bg-gray-100 disabled:opacity-50 disabled:cursor-not-allowed"
            >
              <ChevronLeftIcon className="h-4 w-4" />
            </button>
            <button
              onClick={() => setPage(Math.min(totalPages, page + 1))}
              disabled={page >= totalPages}
              className="p-2 border border-gray-300 rounded hover:bg-gray-100 disabled:opacity-50 disabled:cursor-not-allowed"
            >
              <ChevronRightIcon className="h-4 w-4" />
            </button>
          </div>
        </div>
      )}
    </div>
  );
};

export default UserRefundHistory;
