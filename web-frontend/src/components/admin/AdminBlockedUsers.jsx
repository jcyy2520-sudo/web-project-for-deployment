import React, { useState, useEffect, useCallback } from 'react';
import axios from 'axios';
import {
  MagnifyingGlassIcon,
  FunnelIcon,
  ShieldExclamationIcon,
  LockOpenIcon,
  EyeIcon,
  ArrowPathIcon,
  ChevronLeftIcon,
  ChevronRightIcon,
  ExclamationTriangleIcon,
  UserGroupIcon,
  XMarkIcon,
  EnvelopeIcon,
  PhoneIcon,
  MapPinIcon,
} from '@heroicons/react/24/outline';

const AdminBlockedUsers = ({ isDarkMode, onDataChange }) => {
  const [blockedUsers, setBlockedUsers] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [meta, setMeta] = useState({ current_page: 1, last_page: 1, total: 0, per_page: 10 });

  // Filters
  const [search, setSearch] = useState('');
  const [roleFilter, setRoleFilter] = useState('all');
  const [page, setPage] = useState(1);
  const [perPage, setPerPage] = useState(10);

  // Detail modal
  const [selectedUser, setSelectedUser] = useState(null);
  const [showDetailModal, setShowDetailModal] = useState(false);

  // Unblock state
  const [unblockLoading, setUnblockLoading] = useState(null);
  const [showUnblockConfirm, setShowUnblockConfirm] = useState(null);

  // Debounce search (must be declared before loadBlockedUsers which references it)
  const [debouncedSearch, setDebouncedSearch] = useState('');
  useEffect(() => {
    const timer = setTimeout(() => {
      setDebouncedSearch(search);
      setPage(1);
    }, 300);
    return () => clearTimeout(timer);
  }, [search]);

  const loadBlockedUsers = useCallback(async () => {
    try {
      setLoading(true);
      setError('');
      const params = { page, per_page: perPage };
      if (roleFilter !== 'all') params.role = roleFilter;
      if (debouncedSearch) params.search = debouncedSearch;

      const res = await axios.get('/api/admin/users/blocked', { params, timeout: 10000 });
      if (res.data.success) {
        setBlockedUsers(res.data.data || []);
        setMeta(res.data.meta || { current_page: 1, last_page: 1, total: 0, per_page: perPage });
      }
    } catch (err) {
      setError(err.response?.data?.message || 'Failed to load blocked users.');
    } finally {
      setLoading(false);
    }
  }, [page, perPage, roleFilter, debouncedSearch]);

  useEffect(() => {
    loadBlockedUsers();
  }, [loadBlockedUsers]);

  const handleUnblock = async (userId) => {
    try {
      setUnblockLoading(userId);
      const res = await axios.put(`/api/admin/users/${userId}/unblock`, {}, { timeout: 15000 });
      if (res.data.success) {
        setShowUnblockConfirm(null);
        await loadBlockedUsers();
        onDataChange?.();
        window.showToast?.('Success', 'User unblocked successfully.', 'success');
      }
    } catch (err) {
      window.showToast?.('Error', err.response?.data?.message || 'Failed to unblock user.', 'error');
    } finally {
      setUnblockLoading(null);
    }
  };

  const getRoleColor = (role) => {
    switch (role) {
      case 'admin': return 'bg-purple-500/20 text-purple-300 border-purple-500/30';
      case 'staff': return 'bg-blue-500/20 text-blue-300 border-blue-500/30';
      case 'client': return 'bg-green-500/20 text-green-300 border-green-500/30';
      default: return 'bg-gray-500/20 text-gray-300 border-gray-500/30';
    }
  };

  return (
    <div className="space-y-4">
      {/* Header */}
      <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
        <div>
          <h2 className="text-lg font-bold text-amber-50">Blocked Users</h2>
          <p className="text-gray-400 text-sm">Manage blocked user accounts. Blocked users cannot log in or access the system.</p>
        </div>
        <div className="flex items-center gap-3">
          {meta.total > 0 && (
            <span className="px-3 py-1 rounded-full bg-orange-500/20 text-orange-300 border border-orange-500/30 text-xs font-semibold">
              {meta.total} Blocked
            </span>
          )}
          <button
            onClick={loadBlockedUsers}
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
          <div className="relative md:col-span-2">
            <MagnifyingGlassIcon className="absolute left-2 top-1/2 -translate-y-1/2 h-3 w-3 text-amber-400" />
            <input
              type="text"
              placeholder="Search by name, email, or username..."
              value={search}
              onChange={e => setSearch(e.target.value)}
              className="w-full pl-7 pr-3 py-1.5 bg-gray-800 border border-gray-600 text-white rounded-lg placeholder-gray-400 focus:ring-1 focus:ring-amber-500 focus:border-amber-500 text-sm"
            />
          </div>
          <select
            value={roleFilter}
            onChange={e => { setRoleFilter(e.target.value); setPage(1); }}
            className="px-3 py-1.5 bg-gray-800 border border-gray-600 text-white rounded-lg focus:ring-1 focus:ring-amber-500 text-sm"
          >
            <option value="all">All Roles</option>
            <option value="admin">Admin</option>
            <option value="staff">Staff</option>
            <option value="client">Client</option>
          </select>
          <div className="flex items-center text-xs text-gray-400">
            <FunnelIcon className="h-3 w-3 mr-1" />
            <span>{meta.total} blocked user{meta.total !== 1 ? 's' : ''}</span>
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

      {/* Blocked Users Table */}
      {!loading && !error && (
        <div className={`${isDarkMode ? 'bg-gray-900 border-amber-500/20' : 'bg-white border-amber-300/40'} border rounded-lg shadow overflow-hidden`}>
          {blockedUsers.length === 0 ? (
            <div className="text-center py-12">
              <ShieldExclamationIcon className="mx-auto h-12 w-12 text-gray-600" />
              <h3 className="mt-2 text-sm font-medium text-amber-50">No blocked users</h3>
              <p className="mt-1 text-xs text-gray-400">
                {search || roleFilter !== 'all'
                  ? 'Try adjusting your filters'
                  : 'No user accounts are currently blocked'}
              </p>
            </div>
          ) : (
            <>
              <div className="overflow-x-auto">
                <table className="w-full">
                  <thead className="bg-gray-800">
                    <tr>
                      <th className="px-4 py-3 text-left text-xs font-medium text-amber-400 uppercase">User</th>
                      <th className="px-3 py-3 text-left text-xs font-medium text-amber-400 uppercase hidden md:table-cell">Role</th>
                      <th className="px-3 py-3 text-left text-xs font-medium text-amber-400 uppercase hidden lg:table-cell">Reason</th>
                      <th className="px-3 py-3 text-left text-xs font-medium text-amber-400 uppercase hidden sm:table-cell">Blocked Date</th>
                      <th className="px-3 py-3 text-left text-xs font-medium text-amber-400 uppercase">Actions</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-gray-700">
                    {blockedUsers.map(user => (
                      <tr key={user.id} className="hover:bg-gray-800/50 transition-colors group">
                        <td className="px-4 py-3">
                          <div className="flex items-center gap-3">
                            <div className="w-8 h-8 bg-orange-500/20 rounded-full flex items-center justify-center text-orange-300 text-xs font-bold border border-orange-500/30 flex-shrink-0">
                              {user.first_name?.charAt(0)}{user.last_name?.charAt(0)}
                            </div>
                            <div className="min-w-0">
                              <div className="text-xs font-medium text-amber-50 truncate">{user.first_name} {user.last_name}</div>
                              <div className="text-xs text-gray-400 truncate">{user.email}</div>
                            </div>
                          </div>
                        </td>
                        <td className="px-3 py-3 hidden md:table-cell">
                          <span className={`inline-flex px-2 py-0.5 text-xs font-semibold rounded-full border ${getRoleColor(user.role)}`}>
                            {user.role}
                          </span>
                        </td>
                        <td className="px-3 py-3 hidden lg:table-cell">
                          <p className="text-xs text-gray-300 max-w-[200px] truncate" title={user.account_status_reason || 'No reason provided'}>
                            {user.account_status_reason || 'No reason provided'}
                          </p>
                        </td>
                        <td className="px-3 py-3 text-xs text-gray-400 hidden sm:table-cell">
                          {user.updated_at ? new Date(user.updated_at).toLocaleDateString() : 'N/A'}
                        </td>
                        <td className="px-3 py-3">
                          <div className="flex items-center gap-1">
                            <button
                              onClick={() => {
                                setSelectedUser(user);
                                setShowDetailModal(true);
                              }}
                              className="p-1.5 rounded border border-blue-500/30 text-blue-400 hover:text-blue-300 hover:bg-blue-500/10 transition-colors"
                              title="View details"
                            >
                              <EyeIcon className="h-3.5 w-3.5" />
                            </button>
                            <button
                              onClick={() => setShowUnblockConfirm(user)}
                              disabled={unblockLoading === user.id}
                              className="px-2 py-1 bg-green-600 hover:bg-green-700 text-white rounded text-xs font-medium transition-colors disabled:opacity-50 flex items-center gap-1"
                              title="Unblock user"
                            >
                              {unblockLoading === user.id ? (
                                <span className="w-3 h-3 border-2 border-white/30 border-t-white rounded-full animate-spin"></span>
                              ) : (
                                <LockOpenIcon className="h-3 w-3" />
                              )}
                              Unblock
                            </button>
                          </div>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>

              {/* Pagination */}
              <div className="px-3 py-3 border-t border-gray-700 bg-gray-800/50">
                <div className="flex flex-col sm:flex-row justify-between items-center gap-3">
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
                        <option value={50}>50</option>
                      </select>
                    </div>
                  </div>

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

      {/* Info Banner */}
      <div className="bg-orange-500/10 border border-orange-500/30 rounded-lg p-4">
        <div className="flex gap-3">
          <ShieldExclamationIcon className="h-5 w-5 text-orange-400 flex-shrink-0 mt-0.5" />
          <div>
            <p className="text-sm font-medium text-orange-200 mb-1">Blocked Accounts</p>
            <ul className="text-xs text-orange-100/80 space-y-1">
              <li>• Blocked accounts have their sessions revoked and cannot log in</li>
              <li>• Users receive an email with the reason and a link to submit an appeal</li>
              <li>• Appeals appear in the Appeals section for admin review</li>
              <li>• Use the Unblock button to restore a user's access immediately</li>
              <li>• Unblocked users will receive an email notification</li>
            </ul>
          </div>
        </div>
      </div>

      {/* User Detail Modal */}
      {showDetailModal && selectedUser && (
        <div className="fixed inset-0 bg-black/60 z-50 flex items-center justify-center p-4" onClick={() => setShowDetailModal(false)}>
          <div
            className="bg-gray-900 border border-amber-500/30 rounded-xl shadow-2xl w-full max-w-lg mx-auto overflow-hidden max-h-[90vh] overflow-y-auto"
            onClick={e => e.stopPropagation()}
          >
            <div className="px-6 py-4 border-b border-amber-500/20 bg-gray-900 sticky top-0 z-10">
              <div className="flex items-center justify-between">
                <div className="flex items-center gap-3">
                  <div className="w-10 h-10 rounded-full bg-orange-500/20 flex items-center justify-center text-orange-300 text-sm font-bold border border-orange-500/30">
                    {selectedUser.first_name?.charAt(0)}{selectedUser.last_name?.charAt(0)}
                  </div>
                  <div>
                    <h3 className="text-lg font-semibold text-amber-50">{selectedUser.first_name} {selectedUser.last_name}</h3>
                    <p className="text-xs text-gray-400">{selectedUser.email}</p>
                  </div>
                </div>
                <button
                  onClick={() => setShowDetailModal(false)}
                  className="text-gray-400 hover:text-amber-400 transition-colors p-1 rounded"
                >
                  <XMarkIcon className="h-5 w-5" />
                </button>
              </div>
            </div>

            <div className="px-6 py-4 space-y-4">
              {/* Status Badge */}
              <div className="flex items-center gap-2">
                <span className="px-3 py-1 rounded-full bg-orange-500/20 text-orange-300 border border-orange-500/30 text-xs font-semibold">
                  Blocked
                </span>
                <span className={`px-2 py-0.5 rounded-full text-xs font-medium border ${getRoleColor(selectedUser.role)}`}>
                  {selectedUser.role}
                </span>
              </div>

              {/* Contact Info */}
              <div className="p-3 bg-gray-800/30 rounded-lg border border-gray-600 space-y-2">
                <label className="text-xs font-medium text-gray-400 block">Contact Information</label>
                <div className="flex items-center text-amber-50 text-sm">
                  <EnvelopeIcon className="h-3 w-3 mr-2 text-amber-400" />
                  <span>{selectedUser.email}</span>
                </div>
                {selectedUser.phone && (
                  <div className="flex items-center text-amber-50 text-sm">
                    <PhoneIcon className="h-3 w-3 mr-2 text-amber-400" />
                    <span>{selectedUser.phone}</span>
                  </div>
                )}
                {selectedUser.address && (
                  <div className="flex items-start text-amber-50 text-sm">
                    <MapPinIcon className="h-3 w-3 mr-2 text-amber-400 mt-0.5 flex-shrink-0" />
                    <span className="text-xs">{selectedUser.address}</span>
                  </div>
                )}
              </div>

              {/* Block Reason */}
              <div className="p-3 bg-red-500/5 rounded-lg border border-red-500/20">
                <label className="text-xs font-medium text-red-400 block mb-1">Block Reason</label>
                <p className="text-sm text-gray-300 whitespace-pre-wrap">
                  {selectedUser.account_status_reason || 'No reason provided'}
                </p>
              </div>

              {/* Dates */}
              <div className="grid grid-cols-2 gap-3">
                <div className="p-3 bg-gray-800/30 rounded-lg border border-gray-600">
                  <label className="text-xs font-medium text-gray-400 block">Member Since</label>
                  <p className="text-sm text-amber-50 mt-0.5">
                    {selectedUser.created_at ? new Date(selectedUser.created_at).toLocaleDateString() : 'N/A'}
                  </p>
                </div>
                <div className="p-3 bg-gray-800/30 rounded-lg border border-gray-600">
                  <label className="text-xs font-medium text-gray-400 block">Blocked On</label>
                  <p className="text-sm text-amber-50 mt-0.5">
                    {selectedUser.updated_at ? new Date(selectedUser.updated_at).toLocaleDateString() : 'N/A'}
                  </p>
                </div>
              </div>
            </div>

            <div className="px-6 py-4 border-t border-gray-700 flex gap-3 sticky bottom-0 bg-gray-900">
              <button
                onClick={() => setShowDetailModal(false)}
                className="flex-1 px-4 py-2.5 rounded-lg border border-gray-600 text-gray-300 text-sm font-medium hover:bg-gray-800 transition-colors"
              >
                Close
              </button>
              <button
                onClick={() => {
                  setShowDetailModal(false);
                  setShowUnblockConfirm(selectedUser);
                }}
                className="flex-1 px-4 py-2.5 rounded-lg bg-green-600 hover:bg-green-700 text-white text-sm font-medium transition-colors flex items-center justify-center gap-2"
              >
                <LockOpenIcon className="h-4 w-4" />
                Unblock User
              </button>
            </div>
          </div>
        </div>
      )}

      {/* Unblock Confirmation Modal */}
      {showUnblockConfirm && (
        <div className="fixed inset-0 bg-black/60 z-50 flex items-center justify-center p-4" onClick={() => setShowUnblockConfirm(null)}>
          <div
            className="bg-gray-900 border border-amber-500/30 rounded-xl shadow-2xl w-full max-w-sm mx-auto overflow-hidden"
            onClick={e => e.stopPropagation()}
          >
            <div className="px-6 py-4 border-b border-amber-500/20">
              <div className="flex items-center gap-3">
                <div className="w-10 h-10 rounded-full bg-green-500/20 flex items-center justify-center">
                  <LockOpenIcon className="w-5 h-5 text-green-400" />
                </div>
                <div>
                  <h3 className="text-lg font-semibold text-amber-50">Unblock User</h3>
                  <p className="text-xs text-gray-400">
                    {showUnblockConfirm.first_name} {showUnblockConfirm.last_name}
                  </p>
                </div>
              </div>
            </div>

            <div className="px-6 py-4 space-y-3">
              <p className="text-sm text-gray-300">
                Are you sure you want to unblock <span className="font-semibold text-amber-50">{showUnblockConfirm.first_name} {showUnblockConfirm.last_name}</span>?
              </p>
              <p className="text-xs text-gray-400">
                This will restore their account access. They will be notified by email.
              </p>
            </div>

            <div className="px-6 py-4 border-t border-gray-700 flex gap-3">
              <button
                onClick={() => setShowUnblockConfirm(null)}
                className="flex-1 px-4 py-2.5 rounded-lg border border-gray-600 text-gray-300 text-sm font-medium hover:bg-gray-800 transition-colors"
              >
                Cancel
              </button>
              <button
                onClick={() => handleUnblock(showUnblockConfirm.id)}
                disabled={unblockLoading === showUnblockConfirm.id}
                className="flex-1 px-4 py-2.5 rounded-lg bg-green-600 hover:bg-green-700 text-white text-sm font-medium transition-colors disabled:opacity-50 flex items-center justify-center gap-2"
              >
                {unblockLoading === showUnblockConfirm.id ? (
                  <span className="flex items-center gap-2">
                    <span className="w-4 h-4 border-2 border-white/30 border-t-white rounded-full animate-spin"></span>
                    Unblocking...
                  </span>
                ) : (
                  <>
                    <LockOpenIcon className="h-4 w-4" />
                    Unblock
                  </>
                )}
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
};

export default AdminBlockedUsers;
