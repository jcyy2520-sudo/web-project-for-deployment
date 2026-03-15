import { useState, useEffect, useCallback, useRef } from 'react';
import axios from 'axios';
import {
  MegaphoneIcon,
  PlusIcon,
  PencilIcon,
  TrashIcon,
  MagnifyingGlassIcon,
  XMarkIcon,
  CheckCircleIcon,
  ExclamationTriangleIcon,
  FunnelIcon,
  ArrowPathIcon,
  UserGroupIcon,
  UsersIcon,
  ShieldCheckIcon,
  ChevronLeftIcon,
  ChevronRightIcon,
  ClockIcon,
  EyeIcon
} from '@heroicons/react/24/outline';

// Modal component extracted outside to prevent re-mounting on parent re-renders
const AnnouncementModal = ({ isOpen, onClose, title, children, icon: Icon, isDarkMode }) => {
  if (!isOpen) return null;
  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60">
      <div className={`w-full max-w-lg rounded-xl shadow-2xl border ${isDarkMode ? 'bg-gray-900 border-amber-500/20' : 'bg-white border-gray-200'} max-h-[90vh] overflow-y-auto`}>
        <div className={`flex items-center justify-between px-5 py-4 border-b ${isDarkMode ? 'border-amber-500/10' : 'border-gray-100'}`}>
          <div className="flex items-center gap-2">
            {Icon && <Icon className={`h-5 w-5 ${isDarkMode ? 'text-amber-400' : 'text-amber-600'}`} />}
            <h3 className={`text-sm font-semibold ${isDarkMode ? 'text-amber-50' : 'text-gray-900'}`}>{title}</h3>
          </div>
          <button
            onClick={onClose}
            className={`p-1 rounded-lg transition-colors ${isDarkMode ? 'text-gray-400 hover:bg-gray-800' : 'text-gray-500 hover:bg-gray-100'}`}
          >
            <XMarkIcon className="h-5 w-5" />
          </button>
        </div>
        <div className="px-5 py-4">{children}</div>
      </div>
    </div>
  );
};

const AdminAnnouncements = ({ isDarkMode }) => {
  const [announcements, setAnnouncements] = useState([]);
  const [loading, setLoading] = useState(true);
  const [page, setPage] = useState(1);
  const [pagination, setPagination] = useState({});
  const [search, setSearch] = useState('');
  const [priorityFilter, setPriorityFilter] = useState('');
  const [showCreateModal, setShowCreateModal] = useState(false);
  const [showEditModal, setShowEditModal] = useState(false);
  const [showDeleteModal, setShowDeleteModal] = useState(false);
  const [showDetailModal, setShowDetailModal] = useState(false);
  const [selectedAnnouncement, setSelectedAnnouncement] = useState(null);
  const [formData, setFormData] = useState({
    title: '',
    message: '',
    priority: 'normal',
    target_audience: 'all_users'
  });
  const [formErrors, setFormErrors] = useState({});
  const [submitting, setSubmitting] = useState(false);
  const [successMessage, setSuccessMessage] = useState('');
  const [errorMessage, setErrorMessage] = useState('');
  const [debouncedSearch, setDebouncedSearch] = useState('');
  const searchTimerRef = useRef(null);

  // Debounce search input so typing doesn't trigger immediate refetch
  const handleSearchChange = useCallback((value) => {
    setSearch(value);
    if (searchTimerRef.current) clearTimeout(searchTimerRef.current);
    searchTimerRef.current = setTimeout(() => {
      setDebouncedSearch(value);
      setPage(1);
    }, 400);
  }, []);

  const priorities = [
    { value: 'low', label: 'Low', color: 'text-gray-400', bg: 'bg-gray-500/20', border: 'border-gray-500/30' },
    { value: 'normal', label: 'Normal', color: 'text-blue-400', bg: 'bg-blue-500/20', border: 'border-blue-500/30' },
    { value: 'high', label: 'High', color: 'text-amber-400', bg: 'bg-amber-500/20', border: 'border-amber-500/30' },
    { value: 'urgent', label: 'Urgent', color: 'text-red-400', bg: 'bg-red-500/20', border: 'border-red-500/30' }
  ];

  const audiences = [
    { value: 'all_users', label: 'All Users', icon: UserGroupIcon },
    { value: 'clients', label: 'Clients Only', icon: UsersIcon },
    { value: 'staff', label: 'Staff Only', icon: ShieldCheckIcon }
  ];

  const fetchAnnouncements = useCallback(async (searchOverride) => {
    setLoading(true);
    try {
      const params = { page };
      const searchVal = searchOverride !== undefined ? searchOverride : debouncedSearch;
      if (searchVal) params.search = searchVal;
      if (priorityFilter) params.priority = priorityFilter;
      const res = await axios.get('/api/admin/announcements', { params });
      // Backend returns { announcements: { data: [...], current_page, last_page, total, ... }, success }
      const paginatedData = res.data.announcements || res.data;
      setAnnouncements(paginatedData.data || []);
      setPagination(paginatedData);
    } catch (err) {
      setErrorMessage('Failed to load announcements');
    } finally {
      setLoading(false);
    }
  }, [page, debouncedSearch, priorityFilter]);

  useEffect(() => {
    fetchAnnouncements();
  }, [fetchAnnouncements]);

  useEffect(() => {
    if (successMessage || errorMessage) {
      const timer = setTimeout(() => {
        setSuccessMessage('');
        setErrorMessage('');
      }, 4000);
      return () => clearTimeout(timer);
    }
  }, [successMessage, errorMessage]);

  const validateForm = () => {
    const errors = {};
    if (!formData.title.trim()) errors.title = 'Title is required';
    else if (formData.title.length > 255) errors.title = 'Title must be under 255 characters';
    if (!formData.message.trim()) errors.message = 'Message is required';
    else if (formData.message.length > 5000) errors.message = 'Message must be under 5000 characters';
    setFormErrors(errors);
    return Object.keys(errors).length === 0;
  };

  const handleCreate = async (e) => {
    e.preventDefault();
    if (!validateForm()) return;
    setSubmitting(true);
    try {
      const res = await axios.post('/api/admin/announcements', formData);
      setSuccessMessage(`Announcement sent to ${res.data.recipients_count || 0} user(s)!`);
      setShowCreateModal(false);
      resetForm();
      fetchAnnouncements();
    } catch (err) {
      setErrorMessage(err.response?.data?.message || 'Failed to create announcement');
    } finally {
      setSubmitting(false);
    }
  };

  const handleUpdate = async (e) => {
    e.preventDefault();
    if (!validateForm()) return;
    setSubmitting(true);
    try {
      await axios.put(`/api/admin/announcements/${selectedAnnouncement.id}`, formData);
      setSuccessMessage('Announcement updated successfully');
      setShowEditModal(false);
      resetForm();
      fetchAnnouncements();
    } catch (err) {
      setErrorMessage(err.response?.data?.message || 'Failed to update announcement');
    } finally {
      setSubmitting(false);
    }
  };

  const handleDelete = async () => {
    setSubmitting(true);
    try {
      await axios.delete(`/api/admin/announcements/${selectedAnnouncement.id}`);
      setSuccessMessage('Announcement deleted successfully');
      setShowDeleteModal(false);
      setSelectedAnnouncement(null);
      fetchAnnouncements();
    } catch (err) {
      setErrorMessage(err.response?.data?.message || 'Failed to delete announcement');
    } finally {
      setSubmitting(false);
    }
  };

  const resetForm = () => {
    setFormData({ title: '', message: '', priority: 'normal', target_audience: 'all_users' });
    setFormErrors({});
    setSelectedAnnouncement(null);
  };

  const openEdit = (announcement) => {
    setSelectedAnnouncement(announcement);
    setFormData({
      title: announcement.title,
      message: announcement.message,
      priority: announcement.priority,
      target_audience: announcement.target_audience
    });
    setShowEditModal(true);
  };

  const getPriorityConfig = (priority) => priorities.find(p => p.value === priority) || priorities[1];
  const getAudienceLabel = (audience) => audiences.find(a => a.value === audience)?.label || audience;

  const formatDate = (dateStr) => {
    const date = new Date(dateStr);
    return date.toLocaleDateString('en-US', {
      month: 'short', day: 'numeric', year: 'numeric',
      hour: '2-digit', minute: '2-digit'
    });
  };

  // Common modal form content
  const renderForm = (onSubmit, isEdit = false) => (
    <form onSubmit={onSubmit} className="space-y-4">
      {/* Title */}
      <div>
        <label className={`block text-xs font-medium mb-1 ${isDarkMode ? 'text-amber-50' : 'text-gray-700'}`}>
          Title <span className="text-red-400">*</span>
        </label>
        <input
          type="text"
          value={formData.title}
          onChange={(e) => setFormData(prev => ({ ...prev, title: e.target.value }))}
          className={`w-full px-3 py-2 rounded-lg border text-sm focus:outline-none focus:ring-2 focus:ring-amber-500 ${
            isDarkMode ? 'bg-gray-800 border-gray-600 text-white placeholder-gray-500' : 'bg-white border-gray-300 text-gray-900 placeholder-gray-400'
          } ${formErrors.title ? 'border-red-500' : ''}`}
          placeholder="Announcement title..."
          maxLength={255}
        />
        {formErrors.title && <p className="text-red-400 text-xs mt-1">{formErrors.title}</p>}
        <p className={`text-xs mt-1 ${isDarkMode ? 'text-gray-500' : 'text-gray-400'}`}>{formData.title.length}/255</p>
      </div>

      {/* Message */}
      <div>
        <label className={`block text-xs font-medium mb-1 ${isDarkMode ? 'text-amber-50' : 'text-gray-700'}`}>
          Message <span className="text-red-400">*</span>
        </label>
        <textarea
          value={formData.message}
          onChange={(e) => setFormData(prev => ({ ...prev, message: e.target.value }))}
          rows={4}
          className={`w-full px-3 py-2 rounded-lg border text-sm focus:outline-none focus:ring-2 focus:ring-amber-500 resize-none ${
            isDarkMode ? 'bg-gray-800 border-gray-600 text-white placeholder-gray-500' : 'bg-white border-gray-300 text-gray-900 placeholder-gray-400'
          } ${formErrors.message ? 'border-red-500' : ''}`}
          placeholder="Write your announcement message..."
          maxLength={5000}
        />
        {formErrors.message && <p className="text-red-400 text-xs mt-1">{formErrors.message}</p>}
        <p className={`text-xs mt-1 ${isDarkMode ? 'text-gray-500' : 'text-gray-400'}`}>{formData.message.length}/5000</p>
      </div>

      {/* Priority */}
      <div>
        <label className={`block text-xs font-medium mb-1.5 ${isDarkMode ? 'text-amber-50' : 'text-gray-700'}`}>
          Priority
        </label>
        <div className="flex flex-wrap gap-2">
          {priorities.map(p => (
            <button
              key={p.value}
              type="button"
              onClick={() => setFormData(prev => ({ ...prev, priority: p.value }))}
              className={`px-3 py-1.5 text-xs font-medium rounded-lg border transition-all ${
                formData.priority === p.value
                  ? `${p.bg} ${p.border} ${p.color} ring-1 ring-offset-1 ${isDarkMode ? 'ring-offset-gray-900' : 'ring-offset-white'} ring-amber-500/50`
                  : `${isDarkMode ? 'border-gray-700 text-gray-400 hover:border-gray-600' : 'border-gray-200 text-gray-500 hover:border-gray-300'}`
              }`}
            >
              {p.label}
            </button>
          ))}
        </div>
      </div>

      {/* Target Audience */}
      <div>
        <label className={`block text-xs font-medium mb-1.5 ${isDarkMode ? 'text-amber-50' : 'text-gray-700'}`}>
          Target Audience
        </label>
        <div className="flex flex-wrap gap-2">
          {audiences.map(a => (
            <button
              key={a.value}
              type="button"
              onClick={() => setFormData(prev => ({ ...prev, target_audience: a.value }))}
              className={`flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-lg border transition-all ${
                formData.target_audience === a.value
                  ? `${isDarkMode ? 'bg-amber-500/20 border-amber-500/30 text-amber-400' : 'bg-amber-50 border-amber-300 text-amber-700'} ring-1 ring-offset-1 ${isDarkMode ? 'ring-offset-gray-900' : 'ring-offset-white'} ring-amber-500/50`
                  : `${isDarkMode ? 'border-gray-700 text-gray-400 hover:border-gray-600' : 'border-gray-200 text-gray-500 hover:border-gray-300'}`
              }`}
            >
              <a.icon className="h-3.5 w-3.5" />
              {a.label}
            </button>
          ))}
        </div>
      </div>

      {/* Actions */}
      <div className={`flex justify-end gap-2 pt-3 border-t ${isDarkMode ? 'border-gray-700' : 'border-gray-200'}`}>
        <button
          type="button"
          onClick={() => { isEdit ? setShowEditModal(false) : setShowCreateModal(false); resetForm(); }}
          className={`px-4 py-2 text-xs font-medium rounded-lg border transition-colors ${
            isDarkMode ? 'border-gray-600 text-gray-300 hover:bg-gray-800' : 'border-gray-300 text-gray-700 hover:bg-gray-100'
          }`}
        >
          Cancel
        </button>
        <button
          type="submit"
          disabled={submitting}
          className="px-4 py-2 text-xs font-medium rounded-lg bg-gradient-to-r from-amber-600 to-amber-700 text-white hover:from-amber-700 hover:to-amber-800 transition-all disabled:opacity-50 shadow border border-amber-500/30"
        >
          {submitting ? (
            <span className="flex items-center gap-1.5">
              <div className="w-3 h-3 border-2 border-white border-t-transparent rounded-full animate-spin"></div>
              {isEdit ? 'Updating...' : 'Sending...'}
            </span>
          ) : (
            isEdit ? 'Update Announcement' : 'Send Announcement'
          )}
        </button>
      </div>
    </form>
  );

  return (
    <div className="space-y-4 sm:space-y-6">
      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div>
          <h2 className={`text-lg font-bold ${isDarkMode ? 'text-amber-50' : 'text-amber-900'}`}>
            Announcements
          </h2>
          <p className={`text-xs mt-0.5 ${isDarkMode ? 'text-amber-400/70' : 'text-amber-700/70'}`}>
            Send announcements to users as notifications
          </p>
        </div>
        <div className="flex items-center gap-2">
          <button
            onClick={fetchAnnouncements}
            className={`p-2 rounded-lg border transition-colors ${isDarkMode ? 'border-amber-500/30 text-amber-400 hover:bg-amber-500/10' : 'border-amber-300 text-amber-700 hover:bg-amber-50'}`}
            title="Refresh"
          >
            <ArrowPathIcon className={`h-4 w-4 ${loading ? 'animate-spin' : ''}`} />
          </button>
          <button
            onClick={() => { resetForm(); setShowCreateModal(true); }}
            className="flex items-center gap-1.5 px-3 py-2 text-xs font-medium rounded-lg bg-gradient-to-r from-amber-600 to-amber-700 text-white hover:from-amber-700 hover:to-amber-800 transition-all shadow border border-amber-500/30"
          >
            <PlusIcon className="h-4 w-4" />
            <span className="hidden sm:inline">New Announcement</span>
            <span className="sm:hidden">New</span>
          </button>
        </div>
      </div>

      {/* Success/Error Messages */}
      {successMessage && (
        <div className="flex items-center gap-2 px-4 py-3 rounded-lg bg-green-500/10 border border-green-500/30 animate-fadeIn">
          <CheckCircleIcon className="h-4 w-4 text-green-400 flex-shrink-0" />
          <p className="text-green-400 text-xs">{successMessage}</p>
        </div>
      )}
      {errorMessage && (
        <div className="flex items-center gap-2 px-4 py-3 rounded-lg bg-red-500/10 border border-red-500/30 animate-fadeIn">
          <ExclamationTriangleIcon className="h-4 w-4 text-red-400 flex-shrink-0" />
          <p className="text-red-400 text-xs">{errorMessage}</p>
        </div>
      )}

      {/* Filters */}
      <div className="flex flex-col sm:flex-row gap-2">
        <div className="relative flex-1">
          <MagnifyingGlassIcon className={`absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 ${isDarkMode ? 'text-gray-500' : 'text-gray-400'}`} />
          <input
            type="text"
            value={search}
            onChange={(e) => { handleSearchChange(e.target.value); }}
            className={`w-full pl-9 pr-3 py-2 text-xs rounded-lg border focus:outline-none focus:ring-2 focus:ring-amber-500 ${
              isDarkMode ? 'bg-gray-800 border-gray-700 text-white placeholder-gray-500' : 'bg-white border-gray-300 text-gray-900 placeholder-gray-400'
            }`}
            placeholder="Search announcements..."
          />
        </div>
        <div className="flex items-center gap-2">
          <FunnelIcon className={`h-4 w-4 flex-shrink-0 ${isDarkMode ? 'text-gray-500' : 'text-gray-400'}`} />
          <select
            value={priorityFilter}
            onChange={(e) => { setPriorityFilter(e.target.value); setPage(1); }}
            className={`px-3 py-2 text-xs rounded-lg border focus:outline-none focus:ring-2 focus:ring-amber-500 ${
              isDarkMode ? 'bg-gray-800 border-gray-700 text-white' : 'bg-white border-gray-300 text-gray-900'
            }`}
          >
            <option value="">All Priorities</option>
            {priorities.map(p => (
              <option key={p.value} value={p.value}>{p.label}</option>
            ))}
          </select>
        </div>
      </div>

      {/* Announcements List */}
      {loading ? (
        <div className="flex justify-center py-12">
          <div className={`w-8 h-8 border-3 rounded-full animate-spin ${isDarkMode ? 'border-amber-500 border-t-transparent' : 'border-amber-600 border-t-transparent'}`}></div>
        </div>
      ) : announcements.length === 0 ? (
        <div className={`text-center py-12 rounded-lg border ${isDarkMode ? 'border-gray-800 bg-gray-900/50' : 'border-gray-200 bg-gray-50'}`}>
          <MegaphoneIcon className={`h-12 w-12 mx-auto mb-3 ${isDarkMode ? 'text-gray-600' : 'text-gray-300'}`} />
          <h3 className={`text-sm font-medium ${isDarkMode ? 'text-gray-400' : 'text-gray-600'}`}>No announcements yet</h3>
          <p className={`text-xs mt-1 ${isDarkMode ? 'text-gray-500' : 'text-gray-400'}`}>
            {search || priorityFilter ? 'Try adjusting your filters' : 'Create your first announcement to notify users'}
          </p>
        </div>
      ) : (
        <div className="space-y-3">
          {announcements.map(announcement => {
            const pConfig = getPriorityConfig(announcement.priority);
            return (
              <div
                key={announcement.id}
                className={`rounded-lg border p-4 transition-all hover:shadow-md ${
                  isDarkMode ? 'bg-gray-900 border-amber-500/10 hover:border-amber-500/30' : 'bg-white border-gray-200 hover:border-amber-300'
                }`}
              >
                <div className="flex items-start justify-between gap-3">
                  <div className="flex-1 min-w-0">
                    <div className="flex items-center gap-2 flex-wrap">
                      <h3 className={`text-sm font-semibold truncate ${isDarkMode ? 'text-amber-50' : 'text-gray-900'}`}>
                        {announcement.title}
                      </h3>
                      <span className={`px-2 py-0.5 text-[10px] font-medium rounded-full ${pConfig.bg} ${pConfig.color} ${pConfig.border} border`}>
                        {pConfig.label}
                      </span>
                    </div>
                    <p className={`text-xs mt-1 line-clamp-2 ${isDarkMode ? 'text-gray-400' : 'text-gray-600'}`}>
                      {announcement.message}
                    </p>
                    <div className={`flex items-center gap-3 mt-2 text-[10px] ${isDarkMode ? 'text-gray-500' : 'text-gray-400'}`}>
                      <span className="flex items-center gap-1">
                        <ClockIcon className="h-3 w-3" />
                        {formatDate(announcement.created_at)}
                      </span>
                      <span className="flex items-center gap-1">
                        <UserGroupIcon className="h-3 w-3" />
                        {getAudienceLabel(announcement.target_audience)}
                      </span>
                      {announcement.creator && (
                        <span>By: {announcement.creator.first_name} {announcement.creator.last_name}</span>
                      )}
                    </div>
                  </div>
                  <div className="flex items-center gap-1 flex-shrink-0">
                    <button
                      onClick={() => { setSelectedAnnouncement(announcement); setShowDetailModal(true); }}
                      className={`p-1.5 rounded-lg transition-colors ${isDarkMode ? 'text-gray-400 hover:text-amber-400 hover:bg-amber-500/10' : 'text-gray-500 hover:text-amber-600 hover:bg-amber-50'}`}
                      title="View"
                    >
                      <EyeIcon className="h-4 w-4" />
                    </button>
                    <button
                      onClick={() => openEdit(announcement)}
                      className={`p-1.5 rounded-lg transition-colors ${isDarkMode ? 'text-gray-400 hover:text-blue-400 hover:bg-blue-500/10' : 'text-gray-500 hover:text-blue-600 hover:bg-blue-50'}`}
                      title="Edit"
                    >
                      <PencilIcon className="h-4 w-4" />
                    </button>
                    <button
                      onClick={() => { setSelectedAnnouncement(announcement); setShowDeleteModal(true); }}
                      className={`p-1.5 rounded-lg transition-colors ${isDarkMode ? 'text-gray-400 hover:text-red-400 hover:bg-red-500/10' : 'text-gray-500 hover:text-red-600 hover:bg-red-50'}`}
                      title="Delete"
                    >
                      <TrashIcon className="h-4 w-4" />
                    </button>
                  </div>
                </div>
              </div>
            );
          })}
        </div>
      )}

      {/* Pagination */}
      {pagination.last_page > 1 && (
        <div className="flex items-center justify-between">
          <p className={`text-xs ${isDarkMode ? 'text-gray-500' : 'text-gray-400'}`}>
            Page {pagination.current_page} of {pagination.last_page} ({pagination.total} total)
          </p>
          <div className="flex items-center gap-1">
            <button
              onClick={() => setPage(p => Math.max(1, p - 1))}
              disabled={page <= 1}
              className={`p-1.5 rounded-lg border transition-colors disabled:opacity-30 ${isDarkMode ? 'border-gray-700 text-gray-400 hover:bg-gray-800' : 'border-gray-200 text-gray-600 hover:bg-gray-100'}`}
            >
              <ChevronLeftIcon className="h-4 w-4" />
            </button>
            <button
              onClick={() => setPage(p => Math.min(pagination.last_page, p + 1))}
              disabled={page >= pagination.last_page}
              className={`p-1.5 rounded-lg border transition-colors disabled:opacity-30 ${isDarkMode ? 'border-gray-700 text-gray-400 hover:bg-gray-800' : 'border-gray-200 text-gray-600 hover:bg-gray-100'}`}
            >
              <ChevronRightIcon className="h-4 w-4" />
            </button>
          </div>
        </div>
      )}

      {/* Create Modal */}
      <AnnouncementModal isOpen={showCreateModal} onClose={() => { setShowCreateModal(false); resetForm(); }} title="New Announcement" icon={MegaphoneIcon} isDarkMode={isDarkMode}>
        {renderForm(handleCreate, false)}
      </AnnouncementModal>

      {/* Edit Modal */}
      <AnnouncementModal isOpen={showEditModal} onClose={() => { setShowEditModal(false); resetForm(); }} title="Edit Announcement" icon={PencilIcon} isDarkMode={isDarkMode}>
        {renderForm(handleUpdate, true)}
      </AnnouncementModal>

      {/* Detail Modal */}
      <AnnouncementModal isOpen={showDetailModal} onClose={() => { setShowDetailModal(false); setSelectedAnnouncement(null); }} title="Announcement Details" icon={MegaphoneIcon} isDarkMode={isDarkMode}>
        {selectedAnnouncement && (
          <div className="space-y-4">
            <div>
              <h4 className={`text-sm font-semibold ${isDarkMode ? 'text-amber-50' : 'text-gray-900'}`}>{selectedAnnouncement.title}</h4>
              <div className="flex items-center gap-2 mt-1">
                {(() => {
                  const pc = getPriorityConfig(selectedAnnouncement.priority);
                  return <span className={`px-2 py-0.5 text-[10px] font-medium rounded-full ${pc.bg} ${pc.color} ${pc.border} border`}>{pc.label}</span>;
                })()}
                <span className={`text-xs ${isDarkMode ? 'text-gray-500' : 'text-gray-400'}`}>
                  {getAudienceLabel(selectedAnnouncement.target_audience)}
                </span>
              </div>
            </div>
            <div className={`p-3 rounded-lg ${isDarkMode ? 'bg-gray-800' : 'bg-gray-50'}`}>
              <p className={`text-xs whitespace-pre-wrap ${isDarkMode ? 'text-gray-300' : 'text-gray-700'}`}>{selectedAnnouncement.message}</p>
            </div>
            <div className={`text-xs space-y-1 ${isDarkMode ? 'text-gray-500' : 'text-gray-400'}`}>
              <p>Created: {formatDate(selectedAnnouncement.created_at)}</p>
              {selectedAnnouncement.creator && (
                <p>By: {selectedAnnouncement.creator.first_name} {selectedAnnouncement.creator.last_name}</p>
              )}
            </div>
          </div>
        )}
      </AnnouncementModal>

      {/* Delete Confirmation Modal */}
      <AnnouncementModal isOpen={showDeleteModal} onClose={() => { setShowDeleteModal(false); setSelectedAnnouncement(null); }} title="Delete Announcement" icon={TrashIcon} isDarkMode={isDarkMode}>
        <div className="space-y-4">
          <div className="flex items-start gap-3">
            <div className="w-10 h-10 rounded-full bg-red-500/20 flex items-center justify-center flex-shrink-0">
              <ExclamationTriangleIcon className="h-5 w-5 text-red-400" />
            </div>
            <div>
              <p className={`text-sm ${isDarkMode ? 'text-amber-50' : 'text-gray-900'}`}>
                Are you sure you want to delete this announcement?
              </p>
              {selectedAnnouncement && (
                <p className={`text-xs mt-1 ${isDarkMode ? 'text-gray-400' : 'text-gray-500'}`}>
                  "{selectedAnnouncement.title}" – This action cannot be undone.
                </p>
              )}
            </div>
          </div>
          <div className={`flex justify-end gap-2 pt-3 border-t ${isDarkMode ? 'border-gray-700' : 'border-gray-200'}`}>
            <button
              onClick={() => { setShowDeleteModal(false); setSelectedAnnouncement(null); }}
              className={`px-4 py-2 text-xs font-medium rounded-lg border transition-colors ${
                isDarkMode ? 'border-gray-600 text-gray-300 hover:bg-gray-800' : 'border-gray-300 text-gray-700 hover:bg-gray-100'
              }`}
            >
              Cancel
            </button>
            <button
              onClick={handleDelete}
              disabled={submitting}
              className="px-4 py-2 text-xs font-medium rounded-lg bg-red-600 text-white hover:bg-red-700 transition-colors disabled:opacity-50 shadow"
            >
              {submitting ? 'Deleting...' : 'Delete'}
            </button>
          </div>
        </div>
      </AnnouncementModal>
    </div>
  );
};

export default AdminAnnouncements;
