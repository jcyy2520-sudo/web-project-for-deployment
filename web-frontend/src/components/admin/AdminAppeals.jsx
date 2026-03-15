import React, { useState, useEffect, useCallback } from 'react';
import axios from 'axios';
import {
  MagnifyingGlassIcon,
  FunnelIcon,
  CheckCircleIcon,
  XCircleIcon,
  ClockIcon,
  ExclamationTriangleIcon,
  ChevronLeftIcon,
  ChevronRightIcon,
  EyeIcon,
  ArrowPathIcon,
} from '@heroicons/react/24/outline';

const AdminAppeals = ({ isDarkMode }) => {
  const [appeals, setAppeals] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [stats, setStats] = useState({ pending: 0, total: 0 });
  const [meta, setMeta] = useState({ current_page: 1, last_page: 1, total: 0 });

  // Filters
  const [search, setSearch] = useState('');
  const [statusFilter, setStatusFilter] = useState('all');
  const [actionFilter, setActionFilter] = useState('all');
  const [page, setPage] = useState(1);
  const [perPage, setPerPage] = useState(10);

  // Review modal
  const [selectedAppeal, setSelectedAppeal] = useState(null);
  const [showReviewModal, setShowReviewModal] = useState(false);
  const [reviewStatus, setReviewStatus] = useState('approved');
  const [adminResponse, setAdminResponse] = useState('');
  const [reviewLoading, setReviewLoading] = useState(false);
  const [reviewError, setReviewError] = useState('');

  const loadAppeals = useCallback(async () => {
    try {
      setLoading(true);
      setError('');
      const params = { page, per_page: perPage };
      if (statusFilter !== 'all') params.status = statusFilter;
      if (actionFilter !== 'all') params.action_type = actionFilter;
      if (search) params.search = search;

      const res = await axios.get('/api/admin/appeals', { params });
      if (res.data.success) {
        setAppeals(res.data.data);
        setMeta(res.data.meta);
        setStats(res.data.stats);
      }
    } catch (err) {
      setError(err.response?.data?.message || 'Failed to load appeals.');
    } finally {
      setLoading(false);
    }
  }, [page, perPage, statusFilter, actionFilter, search]);

  useEffect(() => {
    loadAppeals();
  }, [loadAppeals]);

  // Debounce search
  const [debouncedSearch, setDebouncedSearch] = useState('');
  useEffect(() => {
    const timer = setTimeout(() => {
      setDebouncedSearch(search);
      setPage(1);
    }, 300);
    return () => clearTimeout(timer);
  }, [search]);

  useEffect(() => {
    if (debouncedSearch !== undefined) loadAppeals();
  }, [debouncedSearch]);

  const handleResolve = async () => {
    if (adminResponse && adminResponse.length > 0 && adminResponse.length < 10) {
      setReviewError('Response must be at least 10 characters if provided.');
      return;
    }

    try {
      setReviewLoading(true);
      setReviewError('');
      const res = await axios.put(`/api/admin/appeals/${selectedAppeal.id}/resolve`, {
        status: reviewStatus,
        admin_response: adminResponse,
      });

      if (res.data.success) {
        setShowReviewModal(false);
        setSelectedAppeal(null);
        setAdminResponse('');
        await loadAppeals();
      }
    } catch (err) {
      setReviewError(err.response?.data?.message || 'Failed to resolve appeal.');
    } finally {
      setReviewLoading(false);
    }
  };

  const actionLabels = { deleted: 'Deleted', blocked: 'Blocked', deactivated: 'Deactivated' };
  const actionColors = {
    deleted: 'bg-red-500/20 text-red-300 border-red-500/30',
    blocked: 'bg-orange-500/20 text-orange-300 border-orange-500/30',
    deactivated: 'bg-yellow-500/20 text-yellow-300 border-yellow-500/30',
  };
  const statusColors = {
    pending: 'bg-yellow-500/20 text-yellow-300 border-yellow-500/30',
    approved: 'bg-green-500/20 text-green-300 border-green-500/30',
    rejected: 'bg-red-500/20 text-red-300 border-red-500/30',
  };

  return (
    <div className="space-y-4">
      {/* Header */}
      <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
        <div>
          <h2 className="text-lg font-bold text-amber-50">Appeals Management</h2>
          <p className="text-gray-400 text-sm">Review and manage user account appeals</p>
        </div>
        <div className="flex items-center gap-3">
          {stats.pending > 0 && (
            <span className="px-3 py-1 rounded-full bg-yellow-500/20 text-yellow-300 border border-yellow-500/30 text-xs font-semibold">
              {stats.pending} Pending
            </span>
          )}
          <button
            onClick={loadAppeals}
            className="px-3 py-1.5 border border-amber-500/30 text-amber-50 rounded hover:bg-amber-500/10 transition-all text-sm flex items-center"
          >
            <ArrowPathIcon className="h-3 w-3 mr-1" />
            Refresh
          </button>
        </div>
      </div>

      {/* Filters */}
      <div className={`${isDarkMode ? 'bg-gray-900 border-amber-500/20' : 'bg-white border-amber-300/40'} border rounded-lg shadow p-3 transition-colors`}>
        <div className="grid grid-cols-1 md:grid-cols-4 gap-3">
          <div className="relative">
            <MagnifyingGlassIcon className="absolute left-2 top-1/2 -translate-y-1/2 h-3 w-3 text-amber-400" />
            <input
              type="text"
              placeholder="Search by name or email..."
              value={search}
              onChange={e => setSearch(e.target.value)}
              className="w-full pl-7 pr-3 py-1.5 bg-gray-800 border border-gray-600 text-white rounded-lg placeholder-gray-400 focus:ring-1 focus:ring-amber-500 focus:border-amber-500 text-sm"
            />
          </div>
          <select
            value={statusFilter}
            onChange={e => { setStatusFilter(e.target.value); setPage(1); }}
            className="px-3 py-1.5 bg-gray-800 border border-gray-600 text-white rounded-lg focus:ring-1 focus:ring-amber-500 text-sm"
          >
            <option value="all">All Statuses</option>
            <option value="pending">Pending</option>
            <option value="approved">Approved</option>
            <option value="rejected">Rejected</option>
          </select>
          <select
            value={actionFilter}
            onChange={e => { setActionFilter(e.target.value); setPage(1); }}
            className="px-3 py-1.5 bg-gray-800 border border-gray-600 text-white rounded-lg focus:ring-1 focus:ring-amber-500 text-sm"
          >
            <option value="all">All Actions</option>
            <option value="deleted">Deleted</option>
            <option value="blocked">Blocked</option>
            <option value="deactivated">Deactivated</option>
          </select>
          <div className="flex items-center text-xs text-gray-400">
            <FunnelIcon className="h-3 w-3 mr-1" />
            <span>{meta.total} appeal{meta.total !== 1 ? 's' : ''} found</span>
          </div>
        </div>
      </div>

      {/* Loading */}
      {loading && (
        <div className="flex justify-center py-12">
          <div className="w-8 h-8 rounded-full border-4 border-amber-200 border-t-amber-500 animate-spin"></div>
        </div>
      )}

      {/* Error */}
      {error && (
        <div className="p-3 rounded-lg bg-red-500/10 border border-red-500/30 text-red-400 text-sm">
          {error}
        </div>
      )}

      {/* Appeals Table */}
      {!loading && !error && (
        <div className={`${isDarkMode ? 'bg-gray-900 border-amber-500/20' : 'bg-white border-amber-300/40'} border rounded-lg shadow overflow-hidden`}>
          {appeals.length === 0 ? (
            <div className="text-center py-12">
              <CheckCircleIcon className="mx-auto h-12 w-12 text-gray-600" />
              <h3 className="mt-2 text-sm font-medium text-amber-50">No appeals found</h3>
              <p className="mt-1 text-xs text-gray-400">
                {statusFilter !== 'all' || actionFilter !== 'all' || search
                  ? 'Try adjusting your filters'
                  : 'No user appeals have been submitted yet'}
              </p>
            </div>
          ) : (
            <>
              <div className="overflow-x-auto">
                <table className="w-full">
                  <thead className="bg-gray-800">
                    <tr>
                      <th className="px-3 py-2 text-left text-xs font-medium text-amber-400 uppercase">User</th>
                      <th className="px-3 py-2 text-left text-xs font-medium text-amber-400 uppercase">Action</th>
                      <th className="px-3 py-2 text-left text-xs font-medium text-amber-400 uppercase">Category</th>
                      <th className="px-3 py-2 text-left text-xs font-medium text-amber-400 uppercase">Status</th>
                      <th className="px-3 py-2 text-left text-xs font-medium text-amber-400 uppercase">Submitted</th>
                      <th className="px-3 py-2 text-left text-xs font-medium text-amber-400 uppercase">Actions</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-gray-700">
                    {appeals.map(appeal => (
                      <tr key={appeal.id} className="hover:bg-gray-800 transition-colors group">
                        <td className="px-3 py-2">
                          <div className="text-xs font-medium text-amber-50">{appeal.user_name}</div>
                          <div className="text-xs text-gray-400">{appeal.user_email}</div>
                        </td>
                        <td className="px-3 py-2">
                          <span className={`inline-flex px-2 py-0.5 text-xs font-semibold rounded-full border ${actionColors[appeal.action_type] || ''}`}>
                            {actionLabels[appeal.action_type] || appeal.action_type}
                          </span>
                        </td>
                        <td className="px-3 py-2 text-xs text-gray-300 max-w-[150px] truncate">
                          {appeal.appeal_category?.replace(/_/g, ' ') || '-'}
                        </td>
                        <td className="px-3 py-2">
                          <span className={`inline-flex items-center gap-1 px-2 py-0.5 text-xs font-semibold rounded-full border ${statusColors[appeal.status] || ''}`}>
                            {appeal.status === 'pending' && <ClockIcon className="w-3 h-3" />}
                            {appeal.status === 'approved' && <CheckCircleIcon className="w-3 h-3" />}
                            {appeal.status === 'rejected' && <XCircleIcon className="w-3 h-3" />}
                            {appeal.status.charAt(0).toUpperCase() + appeal.status.slice(1)}
                          </span>
                        </td>
                        <td className="px-3 py-2 text-xs text-gray-400">
                          {appeal.appeal_submitted_at
                            ? new Date(appeal.appeal_submitted_at).toLocaleDateString()
                            : '-'}
                        </td>
                        <td className="px-3 py-2">
                          <button
                            onClick={() => {
                              setSelectedAppeal(appeal);
                              setReviewStatus('approved');
                              setAdminResponse('');
                              setReviewError('');
                              setShowReviewModal(true);
                            }}
                            className={`p-1.5 rounded border transition-colors ${
                              appeal.status === 'pending'
                                ? 'text-amber-400 hover:text-amber-300 hover:bg-amber-500/10 border-amber-500/30'
                                : 'text-blue-400 hover:text-blue-300 hover:bg-blue-500/10 border-blue-500/30'
                            }`}
                            title={appeal.status === 'pending' ? 'Review appeal' : 'View details'}
                          >
                            <EyeIcon className="h-3.5 w-3.5" />
                          </button>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>

              {/* Pagination */}
              <div className="px-3 py-3 border-t border-gray-700 bg-gray-800/50">
                <div className="flex flex-col sm:flex-row justify-between items-center gap-3">
                  {/* Left: Results info and per-page selector */}
                  <div className="flex items-center gap-3">
                    <p className="text-xs text-gray-400">
                      Page {meta.current_page} of {meta.last_page} ({meta.total} total)
                    </p>
                    <div className="flex items-center gap-1.5">
                      <label className="text-xs text-gray-500">Show:</label>
                      <select
                        value={perPage}
                        onChange={(e) => {
                          setPerPage(Number(e.target.value));
                          setPage(1);
                        }}
                        className="px-2 py-1 text-xs bg-gray-700 border border-gray-600 rounded text-gray-300 hover:border-amber-500/40 focus:outline-none focus:border-amber-500"
                      >
                        <option value={5}>5</option>
                        <option value={10}>10</option>
                        <option value={25}>25</option>
                      </select>
                    </div>
                  </div>

                  {/* Right: Page navigation */}
                  <div className="flex items-center gap-1">
                    <button
                      onClick={() => setPage(1)}
                      disabled={page === 1}
                      className="px-2 py-1 border border-gray-600 rounded text-gray-300 text-xs hover:bg-gray-700 disabled:opacity-50 disabled:cursor-not-allowed"
                      title="First page"
                    >
                      «
                    </button>
                    <button
                      onClick={() => setPage(p => Math.max(1, p - 1))}
                      disabled={page === 1}
                      className="px-2 py-1 border border-gray-600 rounded text-gray-300 text-xs hover:bg-gray-700 disabled:opacity-50 disabled:cursor-not-allowed flex items-center"
                    >
                      <ChevronLeftIcon className="h-3 w-3 mr-0.5" />Prev
                    </button>
                    {/* Page number buttons */}
                    {(() => {
                      const pages = [];
                      const maxVisible = 5;
                      let start = Math.max(1, page - Math.floor(maxVisible / 2));
                      let end = Math.min(meta.last_page, start + maxVisible - 1);
                      if (end - start + 1 < maxVisible) {
                        start = Math.max(1, end - maxVisible + 1);
                      }
                      for (let i = start; i <= end; i++) {
                        pages.push(
                          <button
                            key={i}
                            onClick={() => setPage(i)}
                            className={`px-2.5 py-1 border rounded text-xs font-medium transition-colors ${
                              i === page
                                ? 'border-amber-500 bg-amber-500/20 text-amber-300'
                                : 'border-gray-600 text-gray-300 hover:bg-gray-700'
                            }`}
                          >
                            {i}
                          </button>
                        );
                      }
                      return pages;
                    })()}
                    <button
                      onClick={() => setPage(p => p + 1)}
                      disabled={page >= meta.last_page}
                      className="px-2 py-1 border border-gray-600 rounded text-gray-300 text-xs hover:bg-gray-700 disabled:opacity-50 disabled:cursor-not-allowed flex items-center"
                    >
                      Next<ChevronRightIcon className="h-3 w-3 ml-0.5" />
                    </button>
                    <button
                      onClick={() => setPage(meta.last_page)}
                      disabled={page >= meta.last_page}
                      className="px-2 py-1 border border-gray-600 rounded text-gray-300 text-xs hover:bg-gray-700 disabled:opacity-50 disabled:cursor-not-allowed"
                      title="Last page"
                    >
                      »
                    </button>
                  </div>
                </div>
              </div>
            </>
          )}
        </div>
      )}

      {/* Review Modal */}
      {showReviewModal && selectedAppeal && (
        <div className="fixed inset-0 bg-black/60 z-50 flex items-center justify-center p-4" onClick={() => setShowReviewModal(false)}>
          <div
            className="bg-gray-900 border border-amber-500/30 rounded-xl shadow-2xl w-full max-w-lg mx-auto overflow-hidden max-h-[90vh] overflow-y-auto"
            onClick={e => e.stopPropagation()}
          >
            {/* Header */}
            <div className="px-6 py-4 border-b border-amber-500/20 bg-gray-900 sticky top-0">
              <h3 className="text-lg font-semibold text-amber-50">
                {selectedAppeal.status === 'pending' ? 'Review Appeal' : 'Appeal Details'}
              </h3>
              <p className="text-xs text-gray-400">
                {selectedAppeal.user_name} — {selectedAppeal.user_email}
              </p>
            </div>

            {/* Details */}
            <div className="px-6 py-4 space-y-4">
              {/* Action info */}
              <div className="grid grid-cols-2 gap-3">
                <div>
                  <span className="text-xs text-gray-500 font-medium">Action Taken</span>
                  <p className={`text-sm font-semibold mt-0.5 ${
                    selectedAppeal.action_type === 'deleted' ? 'text-red-400' :
                    selectedAppeal.action_type === 'blocked' ? 'text-orange-400' : 'text-yellow-400'
                  }`}>
                    {actionLabels[selectedAppeal.action_type]}
                  </p>
                </div>
                <div>
                  <span className="text-xs text-gray-500 font-medium">Acted By</span>
                  <p className="text-sm text-gray-300 mt-0.5">
                    {selectedAppeal.acted_by_admin
                      ? `${selectedAppeal.acted_by_admin.first_name} ${selectedAppeal.acted_by_admin.last_name}`
                      : 'Unknown'}
                  </p>
                </div>
              </div>

              {/* Action reason */}
              <div>
                <span className="text-xs text-gray-500 font-medium">Action Reason</span>
                <p className="text-sm text-gray-300 bg-gray-800 rounded-lg p-3 mt-1">{selectedAppeal.action_reason}</p>
              </div>

              {/* Appeal details */}
              <div>
                <span className="text-xs text-gray-500 font-medium">Appeal Category</span>
                <p className="text-sm text-amber-300 mt-0.5 capitalize">{selectedAppeal.appeal_category?.replace(/_/g, ' ') || '-'}</p>
              </div>

              <div>
                <span className="text-xs text-gray-500 font-medium">Appeal Message</span>
                <p className="text-sm text-gray-300 bg-gray-800 rounded-lg p-3 mt-1 whitespace-pre-wrap">
                  {selectedAppeal.appeal_message || '-'}
                </p>
              </div>

              {/* If already resolved, show resolution */}
              {selectedAppeal.status !== 'pending' && (
                <div className={`p-3 rounded-lg border ${
                  selectedAppeal.status === 'approved'
                    ? 'bg-green-500/10 border-green-500/30'
                    : 'bg-red-500/10 border-red-500/30'
                }`}>
                  <span className={`text-xs font-medium ${
                    selectedAppeal.status === 'approved' ? 'text-green-400' : 'text-red-400'
                  }`}>
                    {selectedAppeal.status === 'approved' ? 'Approved' : 'Rejected'} by{' '}
                    {selectedAppeal.resolved_by_admin
                      ? `${selectedAppeal.resolved_by_admin.first_name} ${selectedAppeal.resolved_by_admin.last_name}`
                      : 'Admin'}
                    {selectedAppeal.resolved_at && (
                      <> on {new Date(selectedAppeal.resolved_at).toLocaleDateString()}</>
                    )}
                  </span>
                  {selectedAppeal.admin_response && (
                    <p className="text-sm text-gray-300 mt-2">{selectedAppeal.admin_response}</p>
                  )}
                </div>
              )}

              {/* Resolution form — only for pending appeals */}
              {selectedAppeal.status === 'pending' && (
                <>
                  <hr className="border-gray-700" />

                  <div>
                    <label className="block text-sm font-medium text-amber-200 mb-2">Decision</label>
                    <div className="flex gap-3">
                      <label className={`flex-1 flex items-center gap-2 p-3 rounded-lg border cursor-pointer transition-all ${
                        reviewStatus === 'approved'
                          ? 'bg-green-500/10 border-green-500/30 ring-1 ring-green-500/30'
                          : 'border-gray-700 hover:border-gray-600'
                      }`}>
                        <input
                          type="radio"
                          name="decision"
                          value="approved"
                          checked={reviewStatus === 'approved'}
                          onChange={e => setReviewStatus(e.target.value)}
                          className="accent-green-500"
                        />
                        <div>
                          <span className="text-sm font-medium text-green-400">Approve</span>
                          <p className="text-xs text-gray-500">Restore the user's account</p>
                        </div>
                      </label>
                      <label className={`flex-1 flex items-center gap-2 p-3 rounded-lg border cursor-pointer transition-all ${
                        reviewStatus === 'rejected'
                          ? 'bg-red-500/10 border-red-500/30 ring-1 ring-red-500/30'
                          : 'border-gray-700 hover:border-gray-600'
                      }`}>
                        <input
                          type="radio"
                          name="decision"
                          value="rejected"
                          checked={reviewStatus === 'rejected'}
                          onChange={e => setReviewStatus(e.target.value)}
                          className="accent-red-500"
                        />
                        <div>
                          <span className="text-sm font-medium text-red-400">Reject</span>
                          <p className="text-xs text-gray-500">Keep the action in effect</p>
                        </div>
                      </label>
                    </div>
                  </div>

                  <div>
                    <label className="block text-sm font-medium text-amber-200 mb-1.5">
                      Your Response <span className="text-gray-500 text-xs font-normal">(optional)</span>
                    </label>
                    <textarea
                      value={adminResponse}
                      onChange={e => setAdminResponse(e.target.value)}
                      placeholder="Optionally provide a response to the user explaining your decision..."
                      rows={3}
                      className="w-full px-3 py-2 rounded-lg border border-gray-600 bg-gray-800 text-white text-sm focus:ring-2 focus:ring-amber-500 focus:border-amber-500 outline-none placeholder-gray-500 resize-none"
                    />
                    <p className="text-xs text-gray-500 mt-1">{adminResponse.length}/1000</p>
                  </div>

                  {reviewError && (
                    <div className="p-2.5 rounded-lg bg-red-500/10 border border-red-500/30">
                      <p className="text-sm text-red-400">{reviewError}</p>
                    </div>
                  )}
                </>
              )}
            </div>

            {/* Footer */}
            <div className="px-6 py-4 border-t border-gray-700 flex gap-3 sticky bottom-0 bg-gray-900">
              <button
                onClick={() => setShowReviewModal(false)}
                className="flex-1 px-4 py-2.5 rounded-lg border border-gray-600 text-gray-300 text-sm font-medium hover:bg-gray-800 transition-colors"
              >
                {selectedAppeal.status === 'pending' ? 'Cancel' : 'Close'}
              </button>
              {selectedAppeal.status === 'pending' && (
                <button
                  onClick={handleResolve}
                  disabled={reviewLoading || (adminResponse.length > 0 && adminResponse.length < 10)}
                  className={`flex-1 px-4 py-2.5 rounded-lg text-sm font-medium text-white transition-colors ${
                    !reviewLoading && (adminResponse.length === 0 || adminResponse.length >= 10)
                      ? reviewStatus === 'approved'
                        ? 'bg-green-600 hover:bg-green-700'
                        : 'bg-red-600 hover:bg-red-700'
                      : 'bg-gray-700 cursor-not-allowed opacity-50'
                  }`}
                >
                  {reviewLoading ? (
                    <span className="flex items-center justify-center gap-2">
                      <span className="w-4 h-4 border-2 border-white/30 border-t-white rounded-full animate-spin"></span>
                      Processing...
                    </span>
                  ) : (
                    `Confirm ${reviewStatus === 'approved' ? 'Approve' : 'Reject'}`
                  )}
                </button>
              )}
            </div>
          </div>
        </div>
      )}
    </div>
  );
};

export default AdminAppeals;
