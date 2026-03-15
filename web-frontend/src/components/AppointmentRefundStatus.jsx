import { useState, useEffect } from 'react';
import axios from 'axios';
import { CheckCircleIcon, XCircleIcon, ClockIcon, ExclamationTriangleIcon } from '@heroicons/react/24/outline';

const AppointmentRefundStatus = ({ appointment, onRefundRequested }) => {
  const [refunds, setRefunds] = useState([]);
  const [loading, setLoading] = useState(false);
  const [showDetails, setShowDetails] = useState(false);

  useEffect(() => {
    if (appointment?.id && appointment?.payment_status === 'paid') {
      loadRefunds();
    }
  }, [appointment?.id, appointment?.payment_status]);

  const loadRefunds = async () => {
    setLoading(true);
    try {
      const response = await axios.get(`/api/refunds/appointment/${appointment.id}`);
      if (response.data?.success) {
        setRefunds(response.data.refunds || []);
      }
    } catch (err) {
      console.error('Failed to load refunds:', err);
    } finally {
      setLoading(false);
    }
  };

  // Only show refund status for paid appointments
  if (appointment?.payment_status !== 'paid' && appointment?.status !== 'completed') {
    return null;
  }

  const activeRefund = refunds.find(r => ['pending', 'approved'].includes(r.status));
  const completedRefund = refunds.find(r => r.status === 'completed');

  if (!activeRefund && !completedRefund) {
    return null;
  }

  const refund = activeRefund || completedRefund;

  const getStatusIcon = (status) => {
    switch (status) {
      case 'pending':
        return <ClockIcon className="h-5 w-5 text-yellow-600" />;
      case 'approved':
        return <ExclamationTriangleIcon className="h-5 w-5 text-blue-600" />;
      case 'completed':
        return <CheckCircleIcon className="h-5 w-5 text-green-600" />;
      case 'rejected':
        return <XCircleIcon className="h-5 w-5 text-red-600" />;
      default:
        return null;
    }
  };

  const getStatusLabel = (status) => {
    switch (status) {
      case 'pending':
        return 'Refund Pending Review';
      case 'approved':
        return 'Refund Approved (Processing)';
      case 'completed':
        return 'Refund Completed';
      case 'rejected':
        return 'Refund Rejected';
      default:
        return 'Refund Processing';
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

  return (
    <div className={`mt-3 p-3 rounded border ${getStatusColor(refund.status)}`}>
      <div className="flex items-start justify-between">
        <div className="flex items-start gap-3">
          {getStatusIcon(refund.status)}
          <div className="flex-1">
            <p className="font-medium text-sm">{getStatusLabel(refund.status)}</p>
            <p className="text-xs mt-1">
              Amount: <span className="font-semibold">₱{parseFloat(refund.refund_amount).toFixed(2)}</span>
              {refund.is_partial && <span className="ml-1 text-xs">(Partial)</span>}
            </p>
            {refund.reason && (
              <p className="text-xs mt-1">
                Reason: <span className="capitalize">{refund.reason.replace(/_/g, ' ')}</span>
              </p>
            )}
            {refund.status === 'completed' && refund.completed_at && (
              <p className="text-xs mt-1">
                Completed: {new Date(refund.completed_at).toLocaleDateString()}
              </p>
            )}
            {refund.status === 'rejected' && refund.rejection_reason && (
              <p className="text-xs mt-1 italic">
                Reason: {refund.rejection_reason}
              </p>
            )}
            {(refund.status === 'pending' || refund.status === 'approved') && (
              <button
                onClick={() => setShowDetails(!showDetails)}
                className="text-xs underline mt-1 hover:no-underline"
              >
                {showDetails ? 'Hide details' : 'View details'}
              </button>
            )}
          </div>
        </div>
      </div>

      {showDetails && refund.description && (
        <div className="mt-2 pt-2 border-t border-current border-opacity-20">
          <p className="text-xs">
            <strong>Details:</strong> {refund.description}
          </p>
        </div>
      )}
    </div>
  );
};

export default AppointmentRefundStatus;
