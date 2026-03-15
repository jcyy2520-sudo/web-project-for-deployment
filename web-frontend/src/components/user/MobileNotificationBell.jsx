import { useState, useEffect, useCallback } from 'react';
import axios from 'axios';
import {
  BellIcon,
  XMarkIcon,
  CheckIcon,
  MegaphoneIcon,
  CalendarIcon,
  Cog6ToothIcon,
  ClockIcon,
  ExclamationTriangleIcon,
  InformationCircleIcon,
  CheckCircleIcon
} from '@heroicons/react/24/outline';
import { BellIcon as BellSolid } from '@heroicons/react/24/solid';

const MobileNotificationBell = ({ onViewAll, isOpen, onToggle, onClose, isDarkMode }) => {
  const [notifications, setNotifications] = useState([]);
  const [unreadCount, setUnreadCount] = useState(0);
  const [loading, setLoading] = useState(false);

  const typeIcons = {
    announcement: MegaphoneIcon,
    calendar: CalendarIcon,
    settings: Cog6ToothIcon,
    appointment: ClockIcon,
    account: ExclamationTriangleIcon,
    general: InformationCircleIcon,
  };

  // Fetch unread count periodically
  useEffect(() => {
    const fetchCount = async () => {
      try {
        const res = await axios.get('/api/notifications/unread-count');
        setUnreadCount(res.data.unread_count ?? 0);
      } catch (err) {
        // silent
      }
    };
    fetchCount();
    const interval = setInterval(fetchCount, 30000);
    return () => clearInterval(interval);
  }, []);

  // Fetch recent when modal opens
  useEffect(() => {
    if (isOpen) {
      const fetchRecent = async () => {
        setLoading(true);
        try {
          const res = await axios.get('/api/notifications/unread');
          setNotifications((res.data.data || []).slice(0, 10));
        } catch (err) {
          // silent
        } finally {
          setLoading(false);
        }
      };
      fetchRecent();
    }
  }, [isOpen]);

  const handleMarkAsRead = async (id) => {
    try {
      await axios.put(`/api/notifications/${id}/read`);
      setNotifications(prev => prev.filter(n => n.id !== id));
      setUnreadCount(prev => Math.max(0, prev - 1));
    } catch (err) {
      // silent
    }
  };

  const handleMarkAllRead = async () => {
    try {
      await axios.put('/api/notifications/mark-all-read');
      setNotifications([]);
      setUnreadCount(0);
    } catch (err) {
      // silent
    }
  };

  const formatDate = (dateStr) => {
    const date = new Date(dateStr);
    const now = new Date();
    const diffMs = now - date;
    const diffMins = Math.floor(diffMs / 60000);
    const diffHours = Math.floor(diffMs / 3600000);
    if (diffMins < 1) return 'Now';
    if (diffMins < 60) return `${diffMins}m ago`;
    if (diffHours < 24) return `${diffHours}h ago`;
    return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
  };

  return (
    <>
      {/* Bell Button */}
      <button
        onClick={onToggle}
        className={`relative p-2 rounded-lg transition-colors ${isDarkMode ? 'text-amber-400 hover:bg-amber-500/10' : 'text-amber-700 hover:bg-amber-50'}`}
      >
        {unreadCount > 0 ? (
          <BellSolid className="h-5 w-5" />
        ) : (
          <BellIcon className="h-5 w-5" />
        )}
        {unreadCount > 0 && (
          <span className="absolute -top-0.5 -right-0.5 min-w-[18px] h-[18px] flex items-center justify-center rounded-full bg-red-500 text-white text-[10px] font-bold px-1 animate-pulse">
            {unreadCount > 99 ? '99+' : unreadCount}
          </span>
        )}
      </button>

      {/* Modal Overlay */}
      {isOpen && (
        <div className="fixed inset-0 z-[70] flex items-start justify-center" onClick={onClose}>
          {/* Backdrop */}
          <div className="absolute inset-0 bg-black/50" />
          
          {/* Modal Content */}
          <div
            className={`relative w-full max-w-md mx-4 mt-14 rounded-xl shadow-2xl border overflow-hidden animate-fadeIn ${
              isDarkMode ? 'bg-gray-900 border-amber-500/20' : 'bg-white border-gray-200'
            }`}
            onClick={(e) => e.stopPropagation()}
          >
            {/* Header */}
            <div className={`flex items-center justify-between px-4 py-3 border-b ${isDarkMode ? 'border-amber-500/10 bg-gray-800/50' : 'border-gray-100 bg-gray-50'}`}>
              <h3 className={`text-sm font-semibold ${isDarkMode ? 'text-amber-50' : 'text-gray-900'}`}>
                Notifications
                {unreadCount > 0 && (
                  <span className={`ml-2 text-xs font-medium px-1.5 py-0.5 rounded-full ${isDarkMode ? 'bg-amber-500/20 text-amber-300' : 'bg-amber-100 text-amber-700'}`}>
                    {unreadCount}
                  </span>
                )}
              </h3>
              <div className="flex items-center gap-1">
                {unreadCount > 0 && (
                  <button
                    onClick={handleMarkAllRead}
                    className={`text-xs px-2 py-1 rounded transition-colors ${isDarkMode ? 'text-amber-400 hover:bg-amber-500/10' : 'text-amber-600 hover:bg-amber-50'}`}
                  >
                    <CheckIcon className="h-3 w-3 inline mr-0.5" />
                    Read all
                  </button>
                )}
                <button
                  onClick={onClose}
                  className={`p-1.5 rounded-lg transition-colors ${isDarkMode ? 'text-gray-400 hover:bg-gray-800' : 'text-gray-500 hover:bg-gray-100'}`}
                >
                  <XMarkIcon className="h-4 w-4" />
                </button>
              </div>
            </div>

            {/* Content */}
            <div className="max-h-[60vh] overflow-y-auto">
              {loading ? (
                <div className="flex justify-center py-10">
                  <div className={`w-6 h-6 border-2 rounded-full animate-spin ${isDarkMode ? 'border-amber-500 border-t-transparent' : 'border-amber-600 border-t-transparent'}`}></div>
                </div>
              ) : notifications.length === 0 ? (
                <div className="text-center py-10">
                  <BellIcon className={`h-10 w-10 mx-auto mb-2 ${isDarkMode ? 'text-gray-600' : 'text-gray-300'}`} />
                  <p className={`text-sm ${isDarkMode ? 'text-gray-500' : 'text-gray-400'}`}>No new notifications</p>
                </div>
              ) : (
                notifications.map(notification => {
                  const Icon = typeIcons[notification.type] || typeIcons.general;
                  return (
                    <div
                      key={notification.id}
                      className={`px-4 py-3 border-b last:border-b-0 flex items-start gap-3 transition-colors ${isDarkMode ? 'border-gray-800 hover:bg-gray-800/50' : 'border-gray-50 hover:bg-gray-50'}`}
                    >
                      <div className={`flex-shrink-0 w-8 h-8 rounded-full flex items-center justify-center ${isDarkMode ? 'bg-amber-500/10' : 'bg-amber-50'}`}>
                        <Icon className={`h-4 w-4 ${isDarkMode ? 'text-amber-400' : 'text-amber-600'}`} />
                      </div>
                      <div className="flex-1 min-w-0">
                        <p className={`text-sm font-medium ${isDarkMode ? 'text-amber-50' : 'text-gray-900'}`}>{notification.title}</p>
                        <p className={`text-xs mt-0.5 line-clamp-2 ${isDarkMode ? 'text-gray-400' : 'text-gray-600'}`}>{notification.message}</p>
                        <span className={`text-[10px] ${isDarkMode ? 'text-gray-500' : 'text-gray-400'}`}>{formatDate(notification.created_at)}</span>
                      </div>
                      <button
                        onClick={() => handleMarkAsRead(notification.id)}
                        className={`flex-shrink-0 p-1.5 rounded-lg transition-colors ${isDarkMode ? 'text-green-400 hover:bg-green-500/10' : 'text-green-600 hover:bg-green-50'}`}
                        title="Mark as read"
                      >
                        <CheckCircleIcon className="h-4 w-4" />
                      </button>
                    </div>
                  );
                })
              )}
            </div>

            {/* Footer */}
            {onViewAll && (
              <div className={`px-4 py-3 border-t ${isDarkMode ? 'border-amber-500/10 bg-gray-800/30' : 'border-gray-100 bg-gray-50/50'}`}>
                <button
                  onClick={() => {
                    onClose();
                    onViewAll();
                  }}
                  className={`w-full text-center text-xs font-medium py-2 rounded-lg transition-colors ${isDarkMode ? 'text-amber-400 hover:bg-amber-500/10 border border-amber-500/20' : 'text-amber-600 hover:bg-amber-50 border border-amber-200'}`}
                >
                  View all notifications
                </button>
              </div>
            )}
          </div>
        </div>
      )}
    </>
  );
};

export default MobileNotificationBell;
