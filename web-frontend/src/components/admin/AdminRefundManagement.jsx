import { useState, useEffect, useCallback } from 'react';
import axios from 'axios';
import { CheckCircleIcon, XCircleIcon, ClockIcon, ExclamationTriangleIcon, ChevronLeftIcon, ChevronRightIcon, XMarkIcon, ChevronDownIcon, ChevronUpIcon, Cog6ToothIcon, PlusIcon, TrashIcon, PencilIcon } from '@heroicons/react/24/outline';
import LoadingSpinner from '../LoadingSpinner';
import { formatDateDisplay } from '../../utils/format';

const AdminRefundManagement = ({ isUserLight: isUserLightProp } = {}) => {
  const [refunds, setRefunds] = useState([]);
  const [loading, setLoading] = useState(false);
  const [page, setPage] = useState(1);
  const [totalPages, setTotalPages] = useState(1);
  const [total, setTotal] = useState(0);
  const [perPage, setPerPage] = useState(20);
  const [activeTab, setActiveTab] = useState('refunds'); // 'refunds' or 'settings'
  
  const [filters, setFilters] = useState({
    status: 'all',
    reason: 'all',
    date_from: '',
    date_to: '',
    search: ''
  });

  const [selectedRefund, setSelectedRefund] = useState(null);
  const [approvalNotes, setApprovalNotes] = useState('');
  const [refundMethod, setRefundMethod] = useState('original_method');
  const [rejectionReason, setRejectionReason] = useState('');
  const [transactionId, setTransactionId] = useState('');
  const [actionInProgress, setActionInProgress] = useState(null);
  
  // Reasons state - loaded from API
  const [requestReasons, setRequestReasons] = useState([]);
  const [declineReasons, setDeclineReasons] = useState([]);
  const [allReasons, setAllReasons] = useState([]);
  const [reasonsLoading, setReasonsLoading] = useState(false);
  const [newReasonLabel, setNewReasonLabel] = useState('');
  const [newReasonType, setNewReasonType] = useState('request');
  const [editingReason, setEditingReason] = useState(null);
  const [editLabel, setEditLabel] = useState('');
  
  const [expandedSections, setExpandedSections] = useState({
    appointmentDetails: true,
    paymentBreakdown: true,
    refundDetails: true,
    actions: true
  });

  // Detect whether the admin has switched to the user-light (light) theme
  const [isUserLight, setIsUserLight] = useState(() => {
    if (typeof isUserLightProp !== 'undefined') return !!isUserLightProp;
    try {
      return typeof document !== 'undefined' && document.documentElement.classList.contains('user-light');
    } catch (e) {
      return false;
    }
  });

  useEffect(() => {
    if (typeof isUserLightProp !== 'undefined') {
      setIsUserLight(!!isUserLightProp);
      return;
    }
    if (typeof document === 'undefined') return;
    const root = document.documentElement;
    const mo = new MutationObserver(() => {
      setIsUserLight(root.classList.contains('user-light'));
    });
    mo.observe(root, { attributes: true, attributeFilter: ['class'] });
    return () => mo.disconnect();
  }, [isUserLightProp]);

  // Theme-aware helper classes
  const textPrimary = isUserLight ? 'text-gray-900' : 'text-amber-50';
  const textSecondary = isUserLight ? 'text-gray-700' : 'text-gray-400';
  const mutedText = isUserLight ? 'text-gray-600' : 'text-gray-400';
  const containerBg = isUserLight ? 'bg-white' : 'bg-gray-900';
  const panelBg = isUserLight ? 'bg-white' : 'bg-gray-800';
  const inputBg = isUserLight ? 'bg-gray-50' : 'bg-gray-800';
  const inputText = isUserLight ? 'text-gray-900' : 'text-white';
  const borderColor = isUserLight ? 'border-gray-200' : 'border-amber-500/20';
  const headBg = isUserLight ? 'bg-gray-50' : 'bg-gray-800';
  const rowHoverClass = isUserLight ? 'hover:bg-gray-100' : 'hover:bg-gray-700';

  const presetDeclineReasons = declineReasons.filter(r => r.is_active).map(r => ({
    value: r.key,
    label: r.label
  }));

  // Load reasons from API
  const loadReasons = useCallback(async () => {
    try {
      setReasonsLoading(true);
      const response = await axios.get('/api/admin/refund-reasons');
      if (response.data?.success) {
        const reasons = response.data.data || [];
        setAllReasons(reasons);
        setRequestReasons(reasons.filter(r => r.type === 'request'));
        setDeclineReasons(reasons.filter(r => r.type === 'decline'));
      }
    } catch (err) {
      console.error('Failed to load refund reasons:', err);
    } finally {
      setReasonsLoading(false);
    }
  }, []);

  useEffect(() => {
    loadReasons();
  }, []);

  const loadRefunds = useCallback(async (pageNum = 1) => {
    setLoading(true);
    try {
      const params = {
        page: pageNum,
        per_page: perPage,
        status: filters.status !== 'all' ? filters.status : undefined,
        reason: filters.reason !== 'all' ? filters.reason : undefined,
        date_from: filters.date_from || undefined,
        date_to: filters.date_to || undefined,
        search: filters.search || undefined
      };

      const response = await axios.get('/api/admin/refunds/all', { params });
      
      if (response.data) {
        setRefunds(response.data.data || []);
        setPage(response.data.current_page || pageNum);
        setTotalPages(response.data.last_page || 1);
        setTotal(response.data.total || 0);
      }
    } catch (err) {
      console.error('Failed to load refunds:', err);
      if (window?.showToast) {
        window.showToast('Error', 'Failed to load refunds', 'error');
      }
    } finally {
      setLoading(false);
    }
  }, [filters, perPage]);

  useEffect(() => {
    setPage(1);
    loadRefunds(1);
  }, [filters]);

  useEffect(() => {
    if (page !== 1) {
      loadRefunds(page);
    }
  }, [page, perPage]);

  const handleApprove = async (refund) => {
    if (!refundMethod) {
      if (window?.showToast) {
        window.showToast('Error', 'Please select a refund method', 'error');
      }
      return;
    }

    setActionInProgress('approve');
    try {
      const response = await axios.post(`/api/admin/refunds/${refund.id}/approve`, {
        approval_notes: approvalNotes,
        refund_method: refundMethod
      });

      if (response.data?.success) {
        if (window?.showToast) {
          window.showToast('Success', 'Refund approved successfully. Processing...', 'success');
        }
        
        // Reload data immediately without blocking
        setSelectedRefund(null);
        setApprovalNotes('');
        setRefundMethod('original_method');
        loadRefunds(page);
        
        if (window?.showToast) {
          window.showToast('Success', 'Refund completed. User has been notified.', 'success');
        }
      }
    } catch (err) {
      console.error('Approval error:', err);
      if (window?.showToast) {
        window.showToast('Error', err.response?.data?.message || 'Failed to approve refund', 'error');
      }
    } finally {
      setActionInProgress(null);
    }
  };

  const handleReject = async (refund) => {
    if (!rejectionReason.trim()) {
      if (window?.showToast) {
        window.showToast('Error', 'Please provide a rejection reason', 'error');
      }
      return;
    }

    setActionInProgress('reject');
    try {
      const response = await axios.post(`/api/admin/refunds/${refund.id}/reject`, {
        rejection_reason: rejectionReason
      });

      if (response.data?.success) {
        if (window?.showToast) {
          window.showToast('Success', 'Refund rejected successfully', 'success');
        }
        setSelectedRefund(null);
        setRejectionReason('');
        loadRefunds(page);
      }
    } catch (err) {
      console.error('Rejection error:', err);
      if (window?.showToast) {
        window.showToast('Error', err.response?.data?.message || 'Failed to reject refund', 'error');
      }
    } finally {
      setActionInProgress(null);
    }
  };

  const handleComplete = async (refund) => {
    setActionInProgress('complete');
    try {
      const response = await axios.post(`/api/admin/refunds/${refund.id}/complete`, {
        transaction_id: transactionId
      });

      if (response.data?.success) {
        if (window?.showToast) {
          window.showToast('Success', 'Refund completed successfully', 'success');
        }
        setSelectedRefund(null);
        setTransactionId('');
        loadRefunds(page);
      }
    } catch (err) {
      console.error('Completion error:', err);
      if (window?.showToast) {
        window.showToast('Error', err.response?.data?.message || 'Failed to complete refund', 'error');
      }
    } finally {
      setActionInProgress(null);
    }
  };

  // CRUD handlers for refund reasons
  const handleAddReason = async () => {
    if (!newReasonLabel.trim()) return;
    try {
      const response = await axios.post('/api/admin/refund-reasons', {
        type: newReasonType,
        label: newReasonLabel.trim()
      });
      if (response.data?.success) {
        setNewReasonLabel('');
        loadReasons();
        if (window?.showToast) {
          window.showToast('Success', 'Reason added successfully', 'success');
        }
      }
    } catch (err) {
      console.error('Add reason error:', err);
      if (window?.showToast) {
        window.showToast('Error', err.response?.data?.message || 'Failed to add reason', 'error');
      }
    }
  };

  const handleUpdateReason = async (id, data) => {
    try {
      const response = await axios.put(`/api/admin/refund-reasons/${id}`, data);
      if (response.data?.success) {
        loadReasons();
        setEditingReason(null);
        if (window?.showToast) {
          window.showToast('Success', 'Reason updated successfully', 'success');
        }
      }
    } catch (err) {
      console.error('Update reason error:', err);
      if (window?.showToast) {
        window.showToast('Error', err.response?.data?.message || 'Failed to update reason', 'error');
      }
    }
  };

  const handleDeleteReason = async (id) => {
    if (!confirm('Are you sure you want to delete this reason?')) return;
    try {
      const response = await axios.delete(`/api/admin/refund-reasons/${id}`);
      if (response.data?.success) {
        loadReasons();
        if (window?.showToast) {
          window.showToast('Success', 'Reason deleted successfully', 'success');
        }
      }
    } catch (err) {
      console.error('Delete reason error:', err);
      if (window?.showToast) {
        window.showToast('Error', err.response?.data?.message || 'Failed to delete reason', 'error');
      }
    }
  };

  const handleToggleActive = async (reason) => {
    await handleUpdateReason(reason.id, { is_active: !reason.is_active });
  };

  const toggleSection = (section) => {
    setExpandedSections(prev => ({
      ...prev,
      [section]: !prev[section]
    }));
  };

  const getStatusIcon = (status) => {
    switch (status) {
      case 'pending':
        return <ClockIcon className="h-5 w-5 text-amber-400" />;
      case 'approved':
        return <ExclamationTriangleIcon className="h-5 w-5 text-blue-400" />;
      case 'completed':
        return <CheckCircleIcon className="h-5 w-5 text-green-400" />;
      case 'rejected':
        return <XCircleIcon className="h-5 w-5 text-red-400" />;
      default:
        return null;
    }
  };

  const getStatusLabel = (status) => {
    switch (status) {
      case 'pending': return 'Pending Review';
      case 'approved': return 'Approved';
      case 'completed': return 'Completed';
      case 'rejected': return 'Rejected';
      default: return status;
    }
  };

  const getStatusColor = (status) => {
    if (isUserLight) {
      switch (status) {
        case 'pending': return 'bg-gray-100 border-amber-200 text-amber-600';
        case 'approved': return 'bg-gray-100 border-blue-200 text-blue-600';
        case 'completed': return 'bg-gray-100 border-green-200 text-green-600';
        case 'rejected': return 'bg-gray-100 border-red-200 text-red-600';
        default: return 'bg-gray-100 border-gray-200 text-gray-700';
      }
    }

    switch (status) {
      case 'pending': return 'bg-gray-800 border-amber-500/30 text-amber-400';
      case 'approved': return 'bg-gray-800 border-blue-500/30 text-blue-400';
      case 'completed': return 'bg-gray-800 border-green-500/30 text-green-400';
      case 'rejected': return 'bg-gray-800 border-red-500/30 text-red-400';
      default: return 'bg-gray-800 border-gray-600 text-gray-400';
    }
  };

  const getTableRowHoverColor = (status) => {
    return rowHoverClass;
  };

  return (
    <div className="space-y-4">
      {/* Header */}
      <div className={`bg-gradient-to-r ${isUserLight ? 'from-white to-white' : 'from-gray-900 to-gray-800'} ${borderColor} rounded-lg p-4`}>
        <div className="flex items-center justify-between">
          <div>
            <h2 className={`text-xl font-bold ${textPrimary} transition-colors duration-300`}>💰 Refund Management Dashboard</h2>
            <p className={`text-sm ${isUserLight ? 'text-gray-600' : 'text-amber-400/70'} mt-1 transition-colors duration-300`}>Manage and process refund requests from all users</p>
          </div>
          <div className="flex gap-2">
            <button
              onClick={() => setActiveTab('refunds')}
              className={`px-4 py-2 rounded-lg text-sm font-medium transition-colors duration-200 ${
                activeTab === 'refunds'
                  ? isUserLight ? 'bg-amber-100 text-amber-800' : 'bg-amber-500/20 text-amber-400'
                  : isUserLight ? 'text-gray-600 hover:bg-gray-100' : 'text-gray-400 hover:bg-gray-800'
              }`}
            >
              Refunds
            </button>
            <button
              onClick={() => setActiveTab('settings')}
              className={`px-4 py-2 rounded-lg text-sm font-medium transition-colors duration-200 flex items-center gap-1 ${
                activeTab === 'settings'
                  ? isUserLight ? 'bg-amber-100 text-amber-800' : 'bg-amber-500/20 text-amber-400'
                  : isUserLight ? 'text-gray-600 hover:bg-gray-100' : 'text-gray-400 hover:bg-gray-800'
              }`}
            >
              <Cog6ToothIcon className="h-4 w-4" /> Settings
            </button>
          </div>
        </div>
      </div>

      {activeTab === 'refunds' ? (
      <>
      {/* Filters */}
      <div className={`${panelBg} ${borderColor} rounded-lg shadow p-4 space-y-3 transition-colors duration-300`}>
        <h3 className="font-semibold text-amber-400 flex items-center gap-2 transition-colors duration-300">
          🔍 Filters & Search
        </h3>
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
          <input
            type="text"
            placeholder="Search by customer..."
            value={filters.search}
            onChange={(e) => setFilters(f => ({ ...f, search: e.target.value }))}
            className={`px-3 py-2 ${inputBg} border border-gray-200 ${inputText} placeholder-gray-400 rounded-lg text-sm focus:outline-none focus:ring-1 focus:ring-amber-500 transition-all duration-200`}
          />
          <select
            value={filters.status}
            onChange={(e) => setFilters(f => ({ ...f, status: e.target.value }))}
            className={`px-3 py-2 ${inputBg} border border-gray-200 ${inputText} rounded-lg text-sm focus:outline-none focus:ring-1 focus:ring-amber-500 transition-all duration-200`}
          >
            <option value="all">All Status</option>
            <option value="pending">Pending</option>
            <option value="approved">Approved</option>
            <option value="completed">Completed</option>
            <option value="rejected">Rejected</option>
          </select>
          <select
            value={filters.reason}
            onChange={(e) => setFilters(f => ({ ...f, reason: e.target.value }))}
            className={`px-3 py-2 ${inputBg} border border-gray-200 ${inputText} rounded-lg text-sm focus:outline-none focus:ring-1 focus:ring-amber-500 transition-all duration-200`}
          >
            <option value="all">All Reasons</option>
            {requestReasons.filter(r => r.is_active).map(r => (
              <option key={r.key} value={r.key}>{r.label}</option>
            ))}
          </select>
          <input
            type="date"
            value={filters.date_from}
            onChange={(e) => setFilters(f => ({ ...f, date_from: e.target.value }))}
            className={`px-3 py-2 ${inputBg} border border-gray-200 ${inputText} rounded-lg text-sm focus:outline-none focus:ring-1 focus:ring-amber-500 transition-all duration-200`}
          />
          <input
            type="date"
            value={filters.date_to}
            onChange={(e) => setFilters(f => ({ ...f, date_to: e.target.value }))}
            className={`px-3 py-2 ${inputBg} border border-gray-200 ${inputText} rounded-lg text-sm focus:outline-none focus:ring-1 focus:ring-amber-500 transition-all duration-200`}
          />
        </div>
      </div>

      {/* Refunds Table */}
      <div className={`${borderColor} rounded-lg shadow overflow-hidden ${containerBg} transition-colors duration-300`}>
        <div className="overflow-x-auto">
          <table className="w-full text-sm">
            <thead className={`${headBg} border-b ${borderColor}`}>
              <tr>
                <th className={`px-4 py-3 text-left font-semibold ${isUserLight ? 'text-gray-700' : 'text-amber-400'} transition-colors duration-300`}>Date</th>
                <th className={`px-4 py-3 text-left font-semibold ${isUserLight ? 'text-gray-700' : 'text-amber-400'} transition-colors duration-300`}>Customer</th>
                <th className={`px-4 py-3 text-left font-semibold ${isUserLight ? 'text-gray-700' : 'text-amber-400'} transition-colors duration-300`}>Amount</th>
                <th className={`px-4 py-3 text-left font-semibold ${isUserLight ? 'text-gray-700' : 'text-amber-400'} transition-colors duration-300`}>Reason</th>
                <th className={`px-4 py-3 text-left font-semibold ${isUserLight ? 'text-gray-700' : 'text-amber-400'} transition-colors duration-300`}>Status</th>
                <th className={`px-4 py-3 text-left font-semibold ${isUserLight ? 'text-gray-700' : 'text-amber-400'} transition-colors duration-300`}>Actions</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-gray-700">
              {loading ? (
                <tr>
                  <td colSpan="6" className="px-4 py-8 text-center">
                    <LoadingSpinner />
                  </td>
                </tr>
              ) : refunds.length === 0 ? (
                <tr>
                  <td colSpan="6" className="px-4 py-8 text-center text-gray-400">
                    No refunds found
                  </td>
                </tr>
              ) : (
                refunds.map(refund => (
                  <tr key={refund.id} className={`${getTableRowHoverColor(refund.status)} transition-colors duration-200 cursor-pointer`}>
                    <td className={`px-4 py-3 ${mutedText}`}>
                      {new Date(refund.created_at).toLocaleDateString()}
                    </td>
                    <td className="px-4 py-3">
                      <div className={`font-medium ${textPrimary}`}>
                        {refund.appointment?.user?.first_name} {refund.appointment?.user?.last_name}
                      </div>
                      <div className={`text-xs ${mutedText}`}>
                        {refund.appointment?.user?.email}
                      </div>
                    </td>
                    <td className={`px-4 py-3 font-semibold ${isUserLight ? 'text-gray-800' : 'text-amber-400'}`}>
                      ₱{parseFloat(refund.refund_amount).toFixed(2)}
                      {refund.is_partial && <div className={`${mutedText} text-xs`}>(Partial)</div>}
                    </td>
                    <td className={`px-4 py-3 ${isUserLight ? 'text-gray-700 text-xs' : 'text-gray-300 text-xs'}`}>
                      {refund.reason.replace(/_/g, ' ')}
                    </td>
                    <td className="px-4 py-3">
                      <div className="flex items-center gap-2">
                        {getStatusIcon(refund.status)}
                        <span className={`text-xs font-medium ${textPrimary}`}>
                          {getStatusLabel(refund.status)}
                        </span>
                      </div>
                    </td>
                    <td className="px-4 py-3">
                      <button
                        onClick={() => setSelectedRefund(refund)}
                        className={`${isUserLight ? 'text-amber-600 hover:text-amber-500' : 'text-amber-400 hover:text-amber-300'} text-xs font-medium px-2 py-1 rounded hover:bg-amber-500/10 transition-colors duration-200`}
                      >
                        View Details
                      </button>
                    </td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>

        {/* Pagination */}
        {totalPages > 1 && (
          <div className={`px-4 py-3 border-t ${isUserLight ? 'border-gray-200' : 'border-gray-700'} ${isUserLight ? 'bg-white' : 'bg-gray-800/50'} flex items-center justify-between`}>
            <div className={`text-sm ${mutedText}`}>
              Showing {((page - 1) * perPage) + 1}-{Math.min(page * perPage, total)} of {total}
            </div>
            <div className="flex items-center gap-2">
              <button
                onClick={() => setPage(Math.max(1, page - 1))}
                disabled={page === 1}
                className={`p-1 border ${isUserLight ? 'border-gray-300 text-gray-700' : 'border-gray-600 text-gray-300'} hover:border-amber-500/40 rounded ${isUserLight ? 'hover:bg-gray-100' : 'hover:bg-gray-700'} disabled:opacity-50 transition-all duration-200`}
              >
                <ChevronLeftIcon className="h-4 w-4" />
              </button>
              <span className={`text-sm ${mutedText}`}>
                Page {page} / {totalPages}
              </span>
              <button
                onClick={() => setPage(Math.min(totalPages, page + 1))}
                disabled={page >= totalPages}
                className={`p-1 border ${isUserLight ? 'border-gray-300 text-gray-700' : 'border-gray-600 text-gray-300'} hover:border-amber-500/40 rounded ${isUserLight ? 'hover:bg-gray-100' : 'hover:bg-gray-700'} disabled:opacity-50 transition-all duration-200`}
              >
                <ChevronRightIcon className="h-4 w-4" />
              </button>
            </div>
          </div>
        )}
      </div>
      </>
      ) : (
        /* Settings Tab */
        <div className="space-y-4">
          {/* Add New Reason */}
          <div className={`${panelBg} ${borderColor} rounded-lg shadow p-4 transition-colors duration-300`}>
            <h3 className={`font-semibold ${isUserLight ? 'text-gray-800' : 'text-amber-400'} mb-3`}>➕ Add New Reason</h3>
            <div className="flex flex-col sm:flex-row gap-3">
              <select
                value={newReasonType}
                onChange={(e) => setNewReasonType(e.target.value)}
                className={`px-3 py-2 ${inputBg} border border-gray-200 ${inputText} rounded-lg text-sm focus:outline-none focus:ring-1 focus:ring-amber-500 w-full sm:w-40`}
              >
                <option value="request">Request Reason</option>
                <option value="decline">Decline Reason</option>
              </select>
              <input
                type="text"
                placeholder="Enter reason label..."
                value={newReasonLabel}
                onChange={(e) => setNewReasonLabel(e.target.value)}
                onKeyDown={(e) => e.key === 'Enter' && handleAddReason()}
                className={`flex-1 px-3 py-2 ${inputBg} border border-gray-200 ${inputText} placeholder-gray-400 rounded-lg text-sm focus:outline-none focus:ring-1 focus:ring-amber-500`}
              />
              <button
                onClick={handleAddReason}
                disabled={!newReasonLabel.trim()}
                className="px-4 py-2 bg-amber-600 text-white rounded-lg text-sm font-medium hover:bg-amber-700 disabled:opacity-50 flex items-center gap-1 whitespace-nowrap transition-colors duration-200"
              >
                <PlusIcon className="h-4 w-4" /> Add
              </button>
            </div>
          </div>

          {/* Request Reasons */}
          <div className={`${panelBg} ${borderColor} rounded-lg shadow overflow-hidden transition-colors duration-300`}>
            <div className={`px-4 py-3 ${headBg} border-b ${borderColor}`}>
              <h3 className={`font-semibold ${isUserLight ? 'text-gray-800' : 'text-amber-400'}`}>📋 Refund Request Reasons</h3>
              <p className={`text-xs ${mutedText} mt-1`}>Reasons users can select when requesting a refund</p>
            </div>
            <div className="divide-y divide-gray-700">
              {reasonsLoading ? (
                <div className="p-4 text-center"><LoadingSpinner /></div>
              ) : requestReasons.length === 0 ? (
                <div className={`p-4 text-center ${mutedText} text-sm`}>No request reasons configured</div>
              ) : (
                requestReasons.map(reason => (
                  <div key={reason.id} className={`px-4 py-3 flex items-center justify-between gap-3 ${rowHoverClass} transition-colors duration-200`}>
                    <div className="flex items-center gap-3 flex-1 min-w-0">
                      <button
                        onClick={() => handleToggleActive(reason)}
                        className={`w-10 h-5 rounded-full relative transition-colors duration-200 flex-shrink-0 ${reason.is_active ? 'bg-green-500' : 'bg-gray-600'}`}
                        title={reason.is_active ? 'Active - click to deactivate' : 'Inactive - click to activate'}
                      >
                        <div className={`w-4 h-4 bg-white rounded-full absolute top-0.5 transition-all duration-200 ${reason.is_active ? 'left-5' : 'left-0.5'}`} />
                      </button>
                      {editingReason === reason.id ? (
                        <input
                          type="text"
                          value={editLabel}
                          onChange={(e) => setEditLabel(e.target.value)}
                          onBlur={() => { handleUpdateReason(reason.id, { label: editLabel }); }}
                          onKeyDown={(e) => { if (e.key === 'Enter') { handleUpdateReason(reason.id, { label: editLabel }); } if (e.key === 'Escape') setEditingReason(null); }}
                          autoFocus
                          className={`flex-1 px-2 py-1 ${inputBg} border border-amber-500 ${inputText} rounded text-sm focus:outline-none`}
                        />
                      ) : (
                        <div className="flex-1 min-w-0">
                          <span className={`text-sm ${reason.is_active ? textPrimary : mutedText} ${!reason.is_active ? 'line-through' : ''}`}>
                            {reason.label}
                          </span>
                          <span className={`text-xs ${mutedText} ml-2`}>({reason.key})</span>
                          {reason.is_default && <span className={`text-xs ml-2 px-1.5 py-0.5 rounded ${isUserLight ? 'bg-blue-100 text-blue-600' : 'bg-blue-500/20 text-blue-400'}`}>Default</span>}
                        </div>
                      )}
                    </div>
                    <div className="flex items-center gap-1 flex-shrink-0">
                      <button
                        onClick={() => { setEditingReason(reason.id); setEditLabel(reason.label); }}
                        className={`p-1.5 rounded ${isUserLight ? 'hover:bg-gray-100 text-gray-600' : 'hover:bg-gray-700 text-gray-400'} transition-colors duration-200`}
                        title="Edit label"
                      >
                        <PencilIcon className="h-3.5 w-3.5" />
                      </button>
                      {!reason.is_default && (
                        <button
                          onClick={() => handleDeleteReason(reason.id)}
                          className="p-1.5 rounded hover:bg-red-500/20 text-red-400 transition-colors duration-200"
                          title="Delete reason"
                        >
                          <TrashIcon className="h-3.5 w-3.5" />
                        </button>
                      )}
                    </div>
                  </div>
                ))
              )}
            </div>
          </div>

          {/* Decline Reasons */}
          <div className={`${panelBg} ${borderColor} rounded-lg shadow overflow-hidden transition-colors duration-300`}>
            <div className={`px-4 py-3 ${headBg} border-b ${borderColor}`}>
              <h3 className={`font-semibold ${isUserLight ? 'text-gray-800' : 'text-amber-400'}`}>❌ Refund Decline Reasons</h3>
              <p className={`text-xs ${mutedText} mt-1`}>Preset reasons when declining a refund request</p>
            </div>
            <div className="divide-y divide-gray-700">
              {reasonsLoading ? (
                <div className="p-4 text-center"><LoadingSpinner /></div>
              ) : declineReasons.length === 0 ? (
                <div className={`p-4 text-center ${mutedText} text-sm`}>No decline reasons configured</div>
              ) : (
                declineReasons.map(reason => (
                  <div key={reason.id} className={`px-4 py-3 flex items-center justify-between gap-3 ${rowHoverClass} transition-colors duration-200`}>
                    <div className="flex items-center gap-3 flex-1 min-w-0">
                      <button
                        onClick={() => handleToggleActive(reason)}
                        className={`w-10 h-5 rounded-full relative transition-colors duration-200 flex-shrink-0 ${reason.is_active ? 'bg-green-500' : 'bg-gray-600'}`}
                        title={reason.is_active ? 'Active - click to deactivate' : 'Inactive - click to activate'}
                      >
                        <div className={`w-4 h-4 bg-white rounded-full absolute top-0.5 transition-all duration-200 ${reason.is_active ? 'left-5' : 'left-0.5'}`} />
                      </button>
                      {editingReason === reason.id ? (
                        <input
                          type="text"
                          value={editLabel}
                          onChange={(e) => setEditLabel(e.target.value)}
                          onBlur={() => { handleUpdateReason(reason.id, { label: editLabel }); }}
                          onKeyDown={(e) => { if (e.key === 'Enter') { handleUpdateReason(reason.id, { label: editLabel }); } if (e.key === 'Escape') setEditingReason(null); }}
                          autoFocus
                          className={`flex-1 px-2 py-1 ${inputBg} border border-amber-500 ${inputText} rounded text-sm focus:outline-none`}
                        />
                      ) : (
                        <div className="flex-1 min-w-0">
                          <span className={`text-sm ${reason.is_active ? textPrimary : mutedText} ${!reason.is_active ? 'line-through' : ''}`}>
                            {reason.label}
                          </span>
                          <span className={`text-xs ${mutedText} ml-2`}>({reason.key})</span>
                          {reason.is_default && <span className={`text-xs ml-2 px-1.5 py-0.5 rounded ${isUserLight ? 'bg-blue-100 text-blue-600' : 'bg-blue-500/20 text-blue-400'}`}>Default</span>}
                        </div>
                      )}
                    </div>
                    <div className="flex items-center gap-1 flex-shrink-0">
                      <button
                        onClick={() => { setEditingReason(reason.id); setEditLabel(reason.label); }}
                        className={`p-1.5 rounded ${isUserLight ? 'hover:bg-gray-100 text-gray-600' : 'hover:bg-gray-700 text-gray-400'} transition-colors duration-200`}
                        title="Edit label"
                      >
                        <PencilIcon className="h-3.5 w-3.5" />
                      </button>
                      {!reason.is_default && (
                        <button
                          onClick={() => handleDeleteReason(reason.id)}
                          className="p-1.5 rounded hover:bg-red-500/20 text-red-400 transition-colors duration-200"
                          title="Delete reason"
                        >
                          <TrashIcon className="h-3.5 w-3.5" />
                        </button>
                      )}
                    </div>
                  </div>
                ))
              )}
            </div>
          </div>
        </div>
      )}

      {/* Detail Modal */}
      {selectedRefund && (
        <div className="fixed inset-0 bg-black bg-opacity-70 flex items-center justify-center z-50 p-4 animate-fadeIn">
          <div className={`${isUserLight ? 'bg-white' : 'bg-gray-900'} rounded-lg shadow-2xl max-w-2xl w-full max-h-[90vh] overflow-y-auto border ${isUserLight ? 'border-gray-200' : 'border-amber-500/20'}`}>
            {/* Header */}
            <div className={`sticky top-0 ${isUserLight ? 'bg-white border-b border-gray-200' : 'bg-gradient-to-r from-gray-900 to-gray-800 border-b border-amber-500/20'} p-6 flex items-center justify-between z-10`}>
              <div>
                <h2 className={`text-2xl font-bold ${isUserLight ? 'text-gray-900' : 'text-amber-50'} transition-colors duration-300`}>💰 Refund Request #{selectedRefund.id}</h2>
                <p className={`text-sm ${isUserLight ? 'text-gray-500' : 'text-amber-400/70'} mt-1 transition-colors duration-300`}>
                  {new Date(selectedRefund.created_at).toLocaleString()}
                </p>
              </div>
              <button
                onClick={() => setSelectedRefund(null)}
                className={`${isUserLight ? 'text-gray-600 hover:text-gray-900 hover:bg-gray-100' : 'text-amber-400 hover:text-amber-300 hover:bg-amber-500/10'} p-2 rounded transition-colors duration-200`}
              >
                <XMarkIcon className="h-6 w-6" />
              </button>
            </div>

            {/* Content */}
            <div className="p-6 space-y-4">
              {/* Status Badge */}
              <div className={`p-4 rounded-lg border ${getStatusColor(selectedRefund.status)}`}>
                <div className="flex items-center gap-3">
                  {getStatusIcon(selectedRefund.status)}
                  <div>
                    <p className="text-xs font-medium opacity-75 text-gray-400">Current Status</p>
                    <p className="text-lg font-bold">{getStatusLabel(selectedRefund.status)}</p>
                  </div>
                </div>
              </div>

              {/* Appointment Details Section */}
              <div className={`border ${isUserLight ? 'border-gray-200' : 'border-gray-700'} rounded-lg overflow-hidden`}>
                <button
                  onClick={() => toggleSection('appointmentDetails')}
                  className={`w-full px-4 py-3 ${isUserLight ? 'bg-gray-50 hover:bg-gray-100' : 'bg-gray-800 hover:bg-gray-700'} transition-colors duration-200 flex items-center justify-between`}
                >
                  <h3 className={`font-semibold ${isUserLight ? 'text-gray-900' : 'text-amber-50'} transition-colors duration-300`}>📅 Appointment Details</h3>
                  {expandedSections.appointmentDetails ? (
                    <ChevronUpIcon className="h-5 w-5 text-gray-400" />
                  ) : (
                    <ChevronDownIcon className="h-5 w-5 text-gray-400" />
                  )}
                </button>

                {expandedSections.appointmentDetails && (
                  <div className={`px-4 py-3 space-y-3 border-t ${isUserLight ? 'border-gray-200' : 'border-gray-700'}`}>
                    <div className="grid grid-cols-2 gap-4">
                      <div>
                        <p className={`text-xs font-medium ${isUserLight ? 'text-gray-500' : 'text-gray-400'} uppercase`}>Date & Time</p>
                        <p className={`text-sm font-semibold ${isUserLight ? 'text-gray-900' : 'text-amber-50'} mt-1 transition-colors duration-300`}>
                          {formatDateDisplay(selectedRefund.appointment?.appointment_date)} at {selectedRefund.appointment?.appointment_time}
                        </p>
                      </div>
                      <div>
                        <p className={`text-xs font-medium ${isUserLight ? 'text-gray-500' : 'text-gray-400'} uppercase`}>Service</p>
                        <p className={`text-sm font-semibold ${isUserLight ? 'text-gray-900' : 'text-amber-50'} mt-1 transition-colors duration-300`}>
                          {selectedRefund.appointment?.service?.name || 'N/A'}
                        </p>
                      </div>
                    </div>

                    <div className={`${isUserLight ? 'bg-blue-50 border-blue-200' : 'bg-gray-800 border-blue-500/30'} p-3 rounded border`}>
                      <h4 className="text-sm font-semibold text-blue-400 mb-2">👤 User Information</h4>
                      <div className="space-y-1 text-sm">
                        <p className="text-blue-300"><strong>Name:</strong> {selectedRefund.appointment?.user?.first_name} {selectedRefund.appointment?.user?.last_name}</p>
                        <p className="text-blue-300"><strong>Email:</strong> {selectedRefund.appointment?.user?.email}</p>
                        {selectedRefund.appointment?.user?.phone && (
                          <p className="text-blue-300"><strong>Phone:</strong> {selectedRefund.appointment?.user?.phone}</p>
                        )}
                      </div>
                    </div>
                  </div>
                )}
              </div>

              {/* Payment Breakdown Section */}
              <div className={`border ${isUserLight ? 'border-gray-200' : 'border-gray-700'} rounded-lg overflow-hidden`}>
                <button
                  onClick={() => toggleSection('paymentBreakdown')}
                  className={`w-full px-4 py-3 ${isUserLight ? 'bg-gray-50 hover:bg-gray-100' : 'bg-gray-800 hover:bg-gray-700'} transition-colors duration-200 flex items-center justify-between`}
                >
                  <h3 className={`font-semibold ${isUserLight ? 'text-gray-900' : 'text-amber-50'} transition-colors duration-300`}>💳 Payment Breakdown</h3>
                  {expandedSections.paymentBreakdown ? (
                    <ChevronUpIcon className="h-5 w-5 text-gray-400" />
                  ) : (
                    <ChevronDownIcon className="h-5 w-5 text-gray-400" />
                  )}
                </button>

                {expandedSections.paymentBreakdown && (
                  <div className="px-4 py-3 space-y-2 border-t border-gray-700">
                    <div className="flex justify-between text-sm">
                      <span className="text-gray-400">Original Price:</span>
                      <span className="font-semibold text-amber-50">₱{parseFloat(selectedRefund.original_amount).toFixed(2)}</span>
                    </div>
                    <div className="flex justify-between text-sm">
                      <span className="text-gray-400">Discount Applied:</span>
                      <span className="font-semibold text-red-400">
                        -₱{(parseFloat(selectedRefund.original_amount) - parseFloat(selectedRefund.refund_amount)).toFixed(2)}
                      </span>
                    </div>
                    {selectedRefund.appointment?.payment_type && (
                      <div className="flex justify-between text-sm">
                        <span className="text-gray-400">Payment Type:</span>
                        <span className="font-semibold text-amber-50 capitalize">
                          {selectedRefund.appointment?.payment_type.replace(/_/g, ' ')}
                        </span>
                      </div>
                    )}
                    <div className="border-t border-gray-700 pt-2 flex justify-between text-sm font-semibold">
                      <span className="text-amber-50">Net Total:</span>
                      <span className="text-amber-400">₱{parseFloat(selectedRefund.refund_amount).toFixed(2)}</span>
                    </div>
                    <div className="bg-gray-800 p-3 rounded border border-amber-500/20 flex justify-between text-sm">
                      <span className="font-semibold text-amber-400">Total Refundable Amount:</span>
                      <span className="text-lg font-bold text-amber-300">₱{parseFloat(selectedRefund.refund_amount).toFixed(2)}</span>
                    </div>
                  </div>
                )}
              </div>

              {/* Refund Details Section */}
              <div className={`border ${isUserLight ? 'border-gray-200' : 'border-gray-700'} rounded-lg overflow-hidden`}>
                <button
                  onClick={() => toggleSection('refundDetails')}
                  className={`w-full px-4 py-3 ${isUserLight ? 'bg-gray-50 hover:bg-gray-100' : 'bg-gray-800 hover:bg-gray-700'} transition-colors duration-200 flex items-center justify-between`}
                >
                  <h3 className={`font-semibold ${isUserLight ? 'text-gray-900' : 'text-amber-50'} transition-colors duration-300`}>📋 Refund Request Details</h3>
                  {expandedSections.refundDetails ? (
                    <ChevronUpIcon className="h-5 w-5 text-gray-400" />
                  ) : (
                    <ChevronDownIcon className="h-5 w-5 text-gray-400" />
                  )}
                </button>

                {expandedSections.refundDetails && (
                  <div className="px-4 py-3 space-y-3 border-t border-gray-700 text-sm">
                    <div>
                      <p className="text-gray-400"><strong>Reason:</strong></p>
                      <p className="text-amber-50 capitalize">{selectedRefund.reason.replace(/_/g, ' ')}</p>
                    </div>
                    {selectedRefund.description && (
                      <div>
                        <p className="text-gray-400"><strong>Description:</strong></p>
                        <p className="text-gray-300 p-2 bg-gray-800 rounded border border-gray-700">{selectedRefund.description}</p>
                      </div>
                    )}
                    <div>
                      <p className="text-gray-400"><strong>Requested By:</strong></p>
                      <p className="text-amber-50">{selectedRefund.requestedBy?.first_name} {selectedRefund.requestedBy?.last_name}</p>
                    </div>
                    <div>
                      <p className="text-gray-400"><strong>Request Date:</strong></p>
                      <p className="text-amber-50">{new Date(selectedRefund.created_at).toLocaleString()}</p>
                    </div>
                  </div>
                )}
              </div>

              {/* Actions Section */}
              {selectedRefund.status === 'pending' && (
                <div className={`border ${isUserLight ? 'border-gray-200' : 'border-gray-700'} rounded-lg overflow-hidden`}>
                  <button
                    onClick={() => toggleSection('actions')}
                    className={`w-full px-4 py-3 ${isUserLight ? 'bg-gray-50 hover:bg-gray-100' : 'bg-gray-800 hover:bg-gray-700'} transition-colors duration-200 flex items-center justify-between`}
                  >
                    <h3 className={`font-semibold ${isUserLight ? 'text-gray-900' : 'text-amber-50'} transition-colors duration-300`}>⚡ Actions</h3>
                    {expandedSections.actions ? (
                      <ChevronUpIcon className="h-5 w-5 text-gray-400" />
                    ) : (
                      <ChevronDownIcon className="h-5 w-5 text-gray-400" />
                    )}
                  </button>

                  {expandedSections.actions && (
                    <div className="px-4 py-4 space-y-4 border-t border-gray-700">
                      {/* Approve Section */}
                      <div className="bg-gray-800 p-4 rounded-lg border border-green-500/30">
                        <h4 className="font-semibold text-green-400 mb-3">✅ Approve Refund</h4>
                        <div className="space-y-3">
                          <div>
                            <label className="block text-sm font-medium text-gray-400 mb-1">
                              Refund Method
                            </label>
                            <select
                              value={refundMethod}
                              onChange={(e) => setRefundMethod(e.target.value)}
                              className="w-full px-3 py-2 bg-gray-700 border border-gray-600 text-white rounded-lg text-sm focus:outline-none focus:ring-1 focus:ring-green-500 transition-all duration-200"
                            >
                              <option value="original_method">Original Payment Method</option>
                              <option value="cash">Cash</option>
                              <option value="check">Check</option>
                              <option value="bank_transfer">Bank Transfer</option>
                              <option value="card">Credit Card</option>
                            </select>
                          </div>

                          <div>
                            <label className="block text-sm font-medium text-gray-400 mb-1">
                              Approval Notes (Optional)
                            </label>
                            <textarea
                              value={approvalNotes}
                              onChange={(e) => setApprovalNotes(e.target.value)}
                              rows="2"
                              className="w-full px-3 py-2 bg-gray-700 border border-gray-600 text-white rounded-lg text-sm focus:outline-none focus:ring-1 focus:ring-green-500 transition-all duration-200"
                              placeholder="Any notes about this approval..."
                            />
                          </div>

                          <button
                            onClick={() => handleApprove(selectedRefund)}
                            disabled={actionInProgress === 'approve'}
                            className="w-full px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 disabled:opacity-50 text-sm font-medium transition-colors duration-200 flex items-center justify-center gap-2"
                          >
                            {actionInProgress === 'approve' ? (
                              <>
                                <div className="animate-spin h-4 w-4 border-2 border-white border-t-transparent rounded-full"></div>
                                Processing...
                              </>
                            ) : (
                              '✅ Approve Refund'
                            )}
                          </button>
                        </div>
                      </div>

                      {/* Reject Section */}
                      <div className="bg-gray-800 p-4 rounded-lg border border-red-500/30">
                        <h4 className="font-semibold text-red-400 mb-3">❌ Decline Refund</h4>
                        <div className="space-y-3">
                          <div>
                            <label className="block text-sm font-medium text-gray-400 mb-2">
                              Decline Reason
                            </label>
                            <select
                              value=""
                              onChange={(e) => {
                                const selected = presetDeclineReasons.find(r => r.value === e.target.value);
                                setRejectionReason(selected ? selected.label : e.target.value);
                              }}
                              className="w-full px-3 py-2 bg-gray-700 border border-gray-600 text-white rounded-lg text-sm focus:outline-none focus:ring-1 focus:ring-red-500 mb-2 transition-all duration-200"
                            >
                              <option value="">Select a reason...</option>
                              {presetDeclineReasons.map(reason => (
                                <option key={reason.value} value={reason.value}>
                                  {reason.label}
                                </option>
                              ))}
                            </select>

                            {rejectionReason && (
                              <textarea
                                value={rejectionReason}
                                onChange={(e) => setRejectionReason(e.target.value)}
                                rows="2"
                                className="w-full px-3 py-2 bg-gray-700 border border-gray-600 text-white rounded-lg text-sm focus:outline-none focus:ring-1 focus:ring-red-500 transition-all duration-200"
                                placeholder="Please explain the reason for declining this refund..."
                              />
                            )}
                          </div>

                          <button
                            onClick={() => handleReject(selectedRefund)}
                            disabled={actionInProgress === 'reject' || !rejectionReason.trim()}
                            className="w-full px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 disabled:opacity-50 text-sm font-medium transition-colors duration-200 flex items-center justify-center gap-2"
                          >
                            {actionInProgress === 'reject' ? (
                              <>
                                <div className="animate-spin h-4 w-4 border-2 border-white border-t-transparent rounded-full"></div>
                                Declining...
                              </>
                            ) : (
                              '❌ Decline Refund'
                            )}
                          </button>
                        </div>
                      </div>
                    </div>
                  )}
                </div>
              )}

              {selectedRefund.status === 'approved' && (
                <div className="bg-gray-800 p-4 rounded-lg border border-yellow-500/30">
                  <p className="text-sm text-yellow-300">
                    ℹ️ This refund was approved and will be automatically completed. The user will be notified via email.
                  </p>
                </div>
              )}

              {selectedRefund.status === 'rejected' && selectedRefund.rejection_reason && (
                <div className="bg-gray-800 border border-red-500/30 rounded-lg p-4">
                  <p className="text-sm font-semibold text-red-400 mb-2">Rejection Reason:</p>
                  <p className="text-sm text-red-300">{selectedRefund.rejection_reason}</p>
                </div>
              )}

              {selectedRefund.status === 'completed' && (
                <div className="bg-gray-800 border border-green-500/30 rounded-lg p-4">
                  <p className="text-sm font-semibold text-green-400 mb-1">✅ Refund Completed</p>
                  <p className="text-sm text-green-300">
                    Completed on {new Date(selectedRefund.completed_at).toLocaleString()}
                  </p>
                  {selectedRefund.transaction_id && (
                    <p className="text-sm text-green-300 mt-1">
                      Transaction ID: {selectedRefund.transaction_id}
                    </p>
                  )}
                </div>
              )}
            </div>
          </div>
        </div>
      )}
    </div>
  );
};

export default AdminRefundManagement;