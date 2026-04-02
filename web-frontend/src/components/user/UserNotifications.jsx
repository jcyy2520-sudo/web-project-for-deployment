import { useState, useEffect, useCallback } from 'react';
import { useTheme } from '../../context/ThemeContext';
import axios from 'axios';
import {
  BellIcon,
  CheckIcon,
  TrashIcon,
  XMarkIcon,
  MegaphoneIcon,
  CalendarIcon,
  Cog6ToothIcon,
  ExclamationTriangleIcon,
  InformationCircleIcon,
  CheckCircleIcon,
  ClockIcon,
  FunnelIcon,
  ArrowPathIcon
} from '@heroicons/react/24/outline';
import { BellIcon as BellIconSolid } from '@heroicons/react/24/solid';

const UserNotifications = ({ onUnreadCountChange }) => {
  const { isDarkMode } = useTheme();
  const [notifications, setNotifications] = useState([]);
  const [loading, setLoading] = useState(true);
  const [unreadCount, setUnreadCount] = useState(0);
  const [filter, setFilter] = useState('all'); // all, unread, read
  const [typeFilter, setTypeFilter] = useState('all');
  const [pagination, setPagination] = useState({ current_page: 1, last_page: 1 });
  const [actionLoading, setActionLoading] = useState(null);
  const [selectedIds, setSelectedIds] = useState(new Set());

  const typeConfig = {
    announcement: { icon: MegaphoneIcon, color: 'amber', label: 'Announcement' },
    calendar: { icon: CalendarIcon, color: 'blue', label: 'Calendar Update' },
    settings: { icon: Cog6ToothIcon, color: 'purple', label: 'Settings Change' },
    appointment: { icon: ClockIcon, color: 'green', label: 'Appointment' },
    account: { icon: ExclamationTriangleIcon, color: 'red', label: 'Account' },
    general: { icon: InformationCircleIcon, color: 'gray', label: 'General' },
  };

  const fetchNotifications = useCallback(async (page = 1) => {
    try {
      setLoading(true);
      const params = { page, per_page: 15 };
      if (filter === 'unread') params.is_read = 0;
      if (filter === 'read') params.is_read = 1;

      const res = await axios.get('/api/notifications', { params });
      const data = res.data;

      setNotifications(data.data?.data || data.data || []);
      setUnreadCount(data.unread_count ?? 0);
      setPagination({
        current_page: data.data?.current_page || 1,
        last_page: data.data?.last_page || 1,
      });

      if (onUnreadCountChange) {
        onUnreadCountChange(data.unread_count ?? 0);
      }
    } catch (err) {
      console.error('Failed to fetch notifications:', err);
    } finally {
      setLoading(false);
    }
  }, [filter, onUnreadCountChange]);

  useEffect(() => {
    fetchNotifications();
  }, [fetchNotifications]);

  const handleMarkAsRead = async (id) => {
    setActionLoading(id);
    try {
      await axios.put(`/api/notifications/${id}/read`);
      setNotifications(prev => prev.map(n => n.id === id ? { ...n, is_read: true, read_at: new Date().toISOString() } : n));
      setUnreadCount(prev => {
        const newCount = Math.max(0, prev - 1);
        if (onUnreadCountChange) onUnreadCountChange(newCount);
        return newCount;
      });
    } catch (err) {
      console.error('Failed to mark as read:', err);
    } finally {
      setActionLoading(null);
    }
  };

  const handleMarkAllRead = async () => {
    setActionLoading('all');
    try {
      await axios.put('/api/notifications/mark-all-read');
      setNotifications(prev => prev.map(n => ({ ...n, is_read: true, read_at: new Date().toISOString() })));
      setUnreadCount(0);
      if (onUnreadCountChange) onUnreadCountChange(0);
    } catch (err) {
      console.error('Failed to mark all as read:', err);
    } finally {
      setActionLoading(null);
    }
  };

  const handleDelete = async (id) => {
    setActionLoading(id);
    try {
      await axios.delete(`/api/notifications/${id}`);
      const deleted = notifications.find(n => n.id === id);
      setNotifications(prev => prev.filter(n => n.id !== id));
      if (deleted && !deleted.is_read) {
        setUnreadCount(prev => {
          const newCount = Math.max(0, prev - 1);
          if (onUnreadCountChange) onUnreadCountChange(newCount);
          return newCount;
        });
      }
    } catch (err) {
      console.error('Failed to delete notification:', err);
    } finally {
      setActionLoading(null);
    }
  };

  const handleClearRead = async () => {
    setActionLoading('clear');
    try {
      await axios.delete('/api/notifications/clear-read');
      setNotifications(prev => prev.filter(n => !n.is_read));
    } catch (err) {
      console.error('Failed to clear read notifications:', err);
    } finally {
      setActionLoading(null);
    }
  };

  const toggleSelect = (id) => {
    setSelectedIds(prev => {
      const next = new Set(prev);
      if (next.has(id)) next.delete(id); else next.add(id);
      return next;
    });
  };

  const toggleSelectAll = () => {
    if (selectedIds.size === filteredNotifications.length) {
      setSelectedIds(new Set());
    } else {
      setSelectedIds(new Set(filteredNotifications.map(n => n.id)));
    }
  };

  const handleBulkDelete = async () => {
    if (selectedIds.size === 0) return;
    setActionLoading('bulk');
    try {
      await axios.post('/api/notifications/batch-delete', { ids: [...selectedIds] });
      const deletedUnread = notifications.filter(n => selectedIds.has(n.id) && !n.is_read).length;
      setNotifications(prev => prev.filter(n => !selectedIds.has(n.id)));
      if (deletedUnread > 0) {
        setUnreadCount(prev => {
          const newCount = Math.max(0, prev - deletedUnread);
          if (onUnreadCountChange) onUnreadCountChange(newCount);
          return newCount;
        });
      }
      setSelectedIds(new Set());
    } catch (err) {
      console.error('Failed to batch delete notifications:', err);
    } finally {
      setActionLoading(null);
    }
  };

  const formatDate = (dateStr) => {
    const date = new Date(dateStr);
    const now = new Date();
    const diffMs = now - date;
    const diffMins = Math.floor(diffMs / 60000);
    const diffHours = Math.floor(diffMs / 3600000);
    const diffDays = Math.floor(diffMs / 86400000);

    if (diffMins < 1) return 'Just now';
    if (diffMins < 60) return `${diffMins}m ago`;
    if (diffHours < 24) return `${diffHours}h ago`;
    if (diffDays < 7) return `${diffDays}d ago`;
    return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: date.getFullYear() !== now.getFullYear() ? 'numeric' : undefined });
  };

  const getTypeInfo = (type) => typeConfig[type] || typeConfig.general;

  const getPriorityBadge = (data) => {
    if (!data?.priority) return null;
    const colors = {
      urgent: 'bg-red-500 text-white',
      high: isDarkMode ? 'bg-orange-500/20 text-orange-300 border border-orange-500/30' : 'bg-orange-100 text-orange-700',
      normal: isDarkMode ? 'bg-blue-500/20 text-blue-300 border border-blue-500/30' : 'bg-blue-100 text-blue-700',
      low: isDarkMode ? 'bg-gray-500/20 text-gray-300 border border-gray-500/30' : 'bg-gray-100 text-gray-700',
    };
    return (
      <span className={`text-xs px-1.5 py-0.5 rounded ${colors[data.priority] || colors.normal}`}>
        {data.priority}
      </span>
    );
  };

  const filteredNotifications = typeFilter === 'all'
    ? notifications
    : notifications.filter(n => n.type === typeFilter);

  return (
    <div className="space-y-4">
      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div className="flex items-center gap-2">
          <BellIconSolid className={`h-5 w-5 ${isDarkMode ? 'text-amber-400' : 'text-amber-600'}`} />
          <h2 className={`text-lg font-bold ${isDarkMode ? 'text-amber-50' : 'text-gray-900'}`}>Notifications</h2>
          {unreadCount > 0 && (
            <span className={`text-xs font-medium px-2 py-0.5 rounded-full ${isDarkMode ? 'bg-amber-500/20 text-amber-300' : 'bg-amber-100 text-amber-700'}`}>
              {unreadCount} unread
            </span>
          )}
        </div>
        <div className="flex items-center gap-2">
          <button
            onClick={handleMarkAllRead}
            disabled={unreadCount === 0 || actionLoading === 'all'}
            className={`text-xs px-2.5 py-1.5 rounded border transition-colors disabled:opacity-50 ${isDarkMode ? 'border-amber-500/30 text-amber-300 hover:bg-amber-500/10' : 'border-amber-300 text-amber-700 hover:bg-amber-50'}`}
          >
            <CheckIcon className="h-3 w-3 inline mr-1" />
            Mark all read
          </button>
          <button
            onClick={handleClearRead}
            disabled={actionLoading === 'clear'}
            className={`text-xs px-2.5 py-1.5 rounded border transition-colors ${isDarkMode ? 'border-gray-600 text-gray-400 hover:bg-gray-800' : 'border-gray-300 text-gray-600 hover:bg-gray-50'}`}
          >
            <TrashIcon className="h-3 w-3 inline mr-1" />
            Clear read
          </button>

        </div>
      </div>

      {/* Bulk Selection Bar */}
      {filteredNotifications.length > 0 && (
        <div className={`flex items-center justify-between px-3 py-2 rounded-lg border ${isDarkMode ? 'bg-gray-800/50 border-gray-700' : 'bg-gray-50 border-gray-200'}`}>
          <label className="flex items-center gap-2 cursor-pointer">
            <input
              type="checkbox"
              checked={selectedIds.size > 0 && selectedIds.size === filteredNotifications.length}
              onChange={toggleSelectAll}
              className="rounded border-gray-400 text-amber-500 focus:ring-amber-500"
            />
            <span className={`text-xs ${isDarkMode ? 'text-gray-400' : 'text-gray-600'}`}>
              {selectedIds.size > 0 ? `${selectedIds.size} selected` : 'Select all'}
            </span>
          </label>
          {selectedIds.size > 0 && (
            <button
              onClick={handleBulkDelete}
              disabled={actionLoading === 'bulk'}
              className={`text-xs px-2.5 py-1.5 rounded border transition-colors ${isDarkMode ? 'border-red-500/30 text-red-400 hover:bg-red-500/10' : 'border-red-300 text-red-600 hover:bg-red-50'}`}
            >
              <TrashIcon className="h-3 w-3 inline mr-1" />
              {actionLoading === 'bulk' ? 'Deleting...' : `Delete ${selectedIds.size}`}
            </button>
          )}
        </div>
      )}

      {/* Filters - Dropdowns on mobile, buttons on desktop */}
      {/* Mobile: Compact dropdown filters */}
      <div className="flex sm:hidden items-center gap-2">
        <FunnelIcon className={`h-3.5 w-3.5 flex-shrink-0 ${isDarkMode ? 'text-amber-400/60' : 'text-gray-500'}`} />
        <select
          value={filter}
          onChange={(e) => setFilter(e.target.value)}
          className={`text-xs px-2 py-1.5 rounded-lg border appearance-none bg-no-repeat bg-[right_0.5rem_center] bg-[length:12px] cursor-pointer ${
            isDarkMode 
              ? 'bg-gray-800 border-gray-700 text-gray-200 focus:border-amber-500' 
              : 'bg-white border-gray-300 text-gray-700 focus:border-amber-500'
          } focus:outline-none focus:ring-1 focus:ring-amber-500`}
          style={{ backgroundImage: `url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 20 20' fill='%239ca3af'%3E%3Cpath fill-rule='evenodd' d='M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z'/%3E%3C/svg%3E")` }}
        >
          <option value="all">All</option>
          <option value="unread">Unread</option>
          <option value="read">Read</option>
        </select>
        <select
          value={typeFilter}
          onChange={(e) => setTypeFilter(e.target.value)}
          className={`text-xs px-2 py-1.5 rounded-lg border appearance-none bg-no-repeat bg-[right_0.5rem_center] bg-[length:12px] cursor-pointer ${
            isDarkMode 
              ? 'bg-gray-800 border-gray-700 text-gray-200 focus:border-amber-500' 
              : 'bg-white border-gray-300 text-gray-700 focus:border-amber-500'
          } focus:outline-none focus:ring-1 focus:ring-amber-500`}
          style={{ backgroundImage: `url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 20 20' fill='%239ca3af'%3E%3Cpath fill-rule='evenodd' d='M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z'/%3E%3C/svg%3E")` }}
        >
          <option value="all">All Types</option>
          {['announcement', 'appointment', 'calendar', 'settings', 'account'].map(t => (
            <option key={t} value={t}>{typeConfig[t]?.label || t}</option>
          ))}
        </select>
      </div>

      {/* Desktop: Button filters */}
      <div className="hidden sm:flex flex-wrap gap-2">
        <div className="flex items-center gap-1">
          <FunnelIcon className={`h-3.5 w-3.5 ${isDarkMode ? 'text-amber-400/60' : 'text-gray-500'}`} />
        </div>
        {['all', 'unread', 'read'].map(f => (
          <button
            key={f}
            onClick={() => setFilter(f)}
            className={`text-xs px-2.5 py-1 rounded-full border transition-colors capitalize ${
              filter === f
                ? (isDarkMode ? 'bg-amber-500/20 border-amber-500/50 text-amber-300' : 'bg-amber-100 border-amber-300 text-amber-700')
                : (isDarkMode ? 'border-gray-700 text-gray-400 hover:border-gray-600' : 'border-gray-300 text-gray-600 hover:border-gray-400')
            }`}
          >
            {f}
          </button>
        ))}
        <div className={`mx-1 w-px h-6 ${isDarkMode ? 'bg-gray-700' : 'bg-gray-300'}`} />
        {['all', 'announcement', 'appointment', 'calendar', 'settings', 'account'].map(t => (
          <button
            key={t}
            onClick={() => setTypeFilter(t)}
            className={`text-xs px-2.5 py-1 rounded-full border transition-colors capitalize ${
              typeFilter === t
                ? (isDarkMode ? 'bg-amber-500/20 border-amber-500/50 text-amber-300' : 'bg-amber-100 border-amber-300 text-amber-700')
                : (isDarkMode ? 'border-gray-700 text-gray-400 hover:border-gray-600' : 'border-gray-300 text-gray-600 hover:border-gray-400')
            }`}
          >
            {t === 'all' ? 'All Types' : (typeConfig[t]?.label || t)}
          </button>
        ))}
      </div>

      {/* Notification List */}
      {loading ? (
        <div className="flex items-center justify-center py-12">
          <div className={`w-8 h-8 border-4 rounded-full animate-spin ${isDarkMode ? 'border-amber-500 border-t-transparent' : 'border-amber-600 border-t-transparent'}`}></div>
        </div>
      ) : filteredNotifications.length === 0 ? (
        <div className={`text-center py-12 rounded-lg border ${isDarkMode ? 'bg-gray-800/50 border-amber-500/10' : 'bg-white border-gray-200'}`}>
          <BellIcon className={`h-12 w-12 mx-auto mb-3 ${isDarkMode ? 'text-gray-600' : 'text-gray-300'}`} />
          <p className={`text-sm ${isDarkMode ? 'text-gray-400' : 'text-gray-500'}`}>No notifications yet</p>
          <p className={`text-xs mt-1 ${isDarkMode ? 'text-gray-500' : 'text-gray-400'}`}>
            You'll be notified of important updates here
          </p>
        </div>
      ) : (
        <div className="space-y-2">
          {filteredNotifications.map(notification => {
            const typeInfo = getTypeInfo(notification.type);
            const TypeIcon = typeInfo.icon;
            return (
              <div
                key={notification.id}
                className={`p-3 sm:p-4 rounded-lg border transition-all duration-200 ${
                  !notification.is_read
                    ? (isDarkMode
                        ? 'bg-amber-500/5 border-amber-500/20 hover:border-amber-500/40'
                        : 'bg-amber-50/50 border-amber-200 hover:border-amber-300')
                    : (isDarkMode
                        ? 'bg-gray-800/30 border-gray-700/50 hover:border-gray-600'
                        : 'bg-gray-50 border-gray-200 hover:border-gray-300')
                }`}
              >
                <div className="flex items-start gap-3">
                  {/* Selection Checkbox */}
                  <input
                    type="checkbox"
                    checked={selectedIds.has(notification.id)}
                    onChange={() => toggleSelect(notification.id)}
                    className="mt-1 rounded border-gray-400 text-amber-500 focus:ring-amber-500 flex-shrink-0"
                  />
                  {/* Icon */}
                  <div className={`flex-shrink-0 w-8 h-8 rounded-full flex items-center justify-center ${
                    isDarkMode ? `bg-${typeInfo.color}-500/10 border border-${typeInfo.color}-500/30` : `bg-${typeInfo.color}-50 border border-${typeInfo.color}-200`
                  }`}>
                    <TypeIcon className={`h-4 w-4 ${isDarkMode ? `text-${typeInfo.color}-400` : `text-${typeInfo.color}-600`}`} />
                  </div>

                  {/* Content */}
                  <div className="flex-1 min-w-0">
                    <div className="flex items-start justify-between gap-2">
                      <div className="min-w-0">
                        <div className="flex items-center gap-2 flex-wrap">
                          <h4 className={`text-sm font-medium truncate ${
                            !notification.is_read
                              ? (isDarkMode ? 'text-amber-50' : 'text-gray-900')
                              : (isDarkMode ? 'text-gray-300' : 'text-gray-700')
                          }`}>
                            {notification.title}
                          </h4>
                          {!notification.is_read && (
                            <span className="w-2 h-2 rounded-full bg-amber-500 flex-shrink-0"></span>
                          )}
                          {getPriorityBadge(notification.data)}
                        </div>
                        <p className={`text-xs mt-0.5 line-clamp-2 ${isDarkMode ? 'text-gray-400' : 'text-gray-600'}`}>
                          {notification.message}
                        </p>
                        <div className="flex items-center gap-2 mt-1.5">
                          <span className={`text-xs ${isDarkMode ? 'text-gray-500' : 'text-gray-400'}`}>
                            <ClockIcon className="h-3 w-3 inline mr-0.5" />
                            {formatDate(notification.created_at)}
                          </span>
                          <span className={`text-xs px-1.5 py-0.5 rounded ${isDarkMode ? 'bg-gray-700/50 text-gray-400' : 'bg-gray-100 text-gray-500'}`}>
                            {typeInfo.label}
                          </span>
                        </div>
                      </div>

                      {/* Actions */}
                      <div className="flex items-center gap-1 flex-shrink-0">
                        {!notification.is_read && (
                          <button
                            onClick={() => handleMarkAsRead(notification.id)}
                            disabled={actionLoading === notification.id}
                            className={`p-1.5 rounded transition-colors ${isDarkMode ? 'text-green-400 hover:bg-green-500/10' : 'text-green-600 hover:bg-green-50'}`}
                            title="Mark as read"
                          >
                            <CheckCircleIcon className="h-4 w-4" />
                          </button>
                        )}
                        <button
                          onClick={() => handleDelete(notification.id)}
                          disabled={actionLoading === notification.id}
                          className={`p-1.5 rounded transition-colors ${isDarkMode ? 'text-red-400 hover:bg-red-500/10' : 'text-red-500 hover:bg-red-50'}`}
                          title="Delete"
                        >
                          <TrashIcon className="h-4 w-4" />
                        </button>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            );
          })}
        </div>
      )}

      {/* Pagination */}
      {pagination.last_page > 1 && (
        <div className="flex justify-center gap-2 pt-2">
          {Array.from({ length: pagination.last_page }, (_, i) => i + 1).map(page => (
            <button
              key={page}
              onClick={() => fetchNotifications(page)}
              className={`w-8 h-8 text-xs rounded border transition-colors ${
                page === pagination.current_page
                  ? (isDarkMode ? 'bg-amber-500/20 border-amber-500/50 text-amber-300' : 'bg-amber-100 border-amber-300 text-amber-700')
                  : (isDarkMode ? 'border-gray-700 text-gray-400 hover:border-gray-600' : 'border-gray-300 text-gray-600 hover:border-gray-400')
              }`}
            >
              {page}
            </button>
          ))}
        </div>
      )}
    </div>
  );
};

export default UserNotifications;
