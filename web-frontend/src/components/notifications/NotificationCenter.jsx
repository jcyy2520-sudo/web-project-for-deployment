import { useState, useEffect, useCallback } from 'react';
import { useApi } from '../hooks/useApi';
import { useNavigate } from 'react-router-dom';
import axios from 'axios';
import {
  CheckCircleIcon,
  ExclamationTriangleIcon,
  InformationCircleIcon,
  XCircleIcon,
  BellIcon,
  CheckIcon,
  TrashIcon
} from '@heroicons/react/24/outline';

const NotificationCenter = ({ isOpen, onClose, isDarkMode = true }) => {
  const navigate = useNavigate();
  const { callApi } = useApi();
  const [notifications, setNotifications] = useState([]);
  const [unreadCount, setUnreadCount] = useState(0);
  const [loading, setLoading] = useState(false);
  const [filter, setFilter] = useState('all'); // all, unread, read

  const loadNotifications = useCallback(async () => {
    setLoading(true);
    const result = await callApi((signal) =>
      axios.get('/api/notifications', { signal })
    );

    if (result.success) {
      setNotifications(result.data.data || []);
      setUnreadCount(result.data.unread_count || 0);
    }
    setLoading(false);
  }, [callApi]);

  useEffect(() => {
    if (isOpen) {
      loadNotifications();
    }
  }, [isOpen, loadNotifications]);

  const markAsRead = async (notificationId) => {
    const result = await callApi((signal) =>
      axios.put(`/api/notifications/${notificationId}/read`, {}, { signal })
    );

    if (result.success) {
      setNotifications(prev =>
        prev.map(n => n.id === notificationId ? { ...n, is_read: true, read_at: new Date() } : n)
      );
      setUnreadCount(prev => Math.max(0, prev - 1));
    }
  };

  const markAllAsRead = async () => {
    const result = await callApi((signal) =>
      axios.put('/api/notifications/mark-all-read', {}, { signal })
    );

    if (result.success) {
      setNotifications(prev => prev.map(n => ({ ...n, is_read: true })));
      setUnreadCount(0);
    }
  };

  const deleteNotification = async (notificationId) => {
    const result = await callApi((signal) =>
      axios.delete(`/api/notifications/${notificationId}`, { signal })
    );

    if (result.success) {
      setNotifications(prev => prev.filter(n => n.id !== notificationId));
    }
  };

  const handleNotificationClick = (notification) => {
    // Mark as read
    if (!notification.is_read) {
      markAsRead(notification.id);
    }

    // Navigate based on notification type and reference_id
    const type = notification.type || '';
    const refId = notification.reference_id;

    if (type.includes('appointment')) {
      navigate(`/dashboard?tab=appointments${refId ? `&id=${refId}` : ''}`);
      onClose();
    } else if (type.includes('payment') || type.includes('refund')) {
      navigate(`/dashboard?tab=payments${refId ? `&id=${refId}` : ''}`);
      onClose();
    } else if (type.includes('message')) {
      navigate('/dashboard?tab=messages');
      onClose();
    } else if (type.includes('service')) {
      navigate(`/dashboard?tab=services${refId ? `&id=${refId}` : ''}`);
      onClose();
    } else {
      // Default: navigate to dashboard
      navigate('/dashboard');
      onClose();
    }
  };

  const getIconForType = (type, color) => {
    const iconProps = { className: `h-5 w-5` };

    switch (type) {
      case 'success':
      case 'appointment_approved':
        return <CheckCircleIcon {...iconProps} className='h-5 w-5 text-green-400' />;
      case 'danger':
      case 'appointment_declined':
        return <XCircleIcon {...iconProps} className='h-5 w-5 text-red-400' />;
      case 'warning':
        return <ExclamationTriangleIcon {...iconProps} className='h-5 w-5 text-yellow-400' />;
      default:
        return <InformationCircleIcon {...iconProps} className='h-5 w-5 text-blue-400' />;
    }
  };

  const filteredNotifications = notifications.filter(n => {
    if (filter === 'unread') return !n.is_read;
    if (filter === 'read') return n.is_read;
    return true;
  });

  if (!isOpen) return null;

  return (
    <div className="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-start justify-end pt-16">
      <div className={`w-96 max-h-[calc(100vh-100px)] ${isDarkMode ? 'bg-gray-900 border-amber-500/30' : 'bg-white border-amber-300/40'} border rounded-lg shadow-xl overflow-hidden animate-slideIn`}>
        {/* Header */}
        <div className={`${isDarkMode ? 'bg-gray-950 border-amber-500/30' : 'bg-gray-50 border-amber-300/40'} border-b p-4 flex justify-between items-center`}>
          <div className="flex items-center">
            <BellIcon className="h-5 w-5 text-amber-400 mr-2" />
            <h2 className={`text-sm font-semibold ${isDarkMode ? 'text-amber-50' : 'text-amber-900'}`}>Notifications</h2>
            {unreadCount > 0 && (
              <span className="ml-2 px-2 py-1 bg-red-500/20 text-red-400 rounded-full text-xs font-medium">
                {unreadCount}
              </span>
            )}
          </div>
          <button
            onClick={onClose}
            className={`${isDarkMode ? 'text-gray-400 hover:text-gray-300' : 'text-gray-500 hover:text-gray-700'} transition-colors`}
          >
            ✕
          </button>
        </div>

        {/* Filters */}
        <div className={`${isDarkMode ? 'bg-gray-900 border-gray-700' : 'bg-gray-50 border-gray-200'} border-b p-3 flex gap-2`}>
          <button
            onClick={() => setFilter('all')}
            className={`px-3 py-1 rounded text-xs font-medium transition-all ${
              filter === 'all'
                ? 'bg-amber-500/20 text-amber-400 border border-amber-500/50'
                : `${isDarkMode ? 'bg-gray-800 text-gray-400 hover:bg-gray-700' : 'bg-gray-200 text-gray-600 hover:bg-gray-300'}`
            }`}
          >
            All
          </button>
          <button
            onClick={() => setFilter('unread')}
            className={`px-3 py-1 rounded text-xs font-medium transition-all ${
              filter === 'unread'
                ? 'bg-amber-500/20 text-amber-400 border border-amber-500/50'
                : `${isDarkMode ? 'bg-gray-800 text-gray-400 hover:bg-gray-700' : 'bg-gray-200 text-gray-600 hover:bg-gray-300'}`
            }`}
          >
            Unread
          </button>
          {unreadCount > 0 && (
            <button
              onClick={markAllAsRead}
              className="ml-auto px-3 py-1 rounded text-xs font-medium bg-amber-500/20 text-amber-400 hover:bg-amber-500/30 transition-all border border-amber-500/50"
            >
              Mark all read
            </button>
          )}
        </div>

        {/* Notifications List */}
        <div className="overflow-y-auto max-h-[calc(100vh-200px)]">
          {loading ? (
            <div className={`p-4 text-center ${isDarkMode ? 'text-gray-400' : 'text-gray-500'}`}>Loading...</div>
          ) : filteredNotifications.length === 0 ? (
            <div className="p-8 text-center">
              <BellIcon className={`h-12 w-12 ${isDarkMode ? 'text-gray-600' : 'text-gray-300'} mx-auto mb-2`} />
              <p className={`${isDarkMode ? 'text-gray-400' : 'text-gray-500'} text-sm`}>No notifications</p>
            </div>
          ) : (
            <div className={`divide-y ${isDarkMode ? 'divide-gray-700' : 'divide-gray-200'}`}>
              {filteredNotifications.map(notification => (
                <div
                  key={notification.id}
                  className={`p-4 ${isDarkMode ? 'hover:bg-gray-800/50' : 'hover:bg-gray-50'} transition-colors cursor-pointer ${
                    !notification.is_read ? `${isDarkMode ? 'bg-gray-800/30' : 'bg-amber-50/30'}` : ''
                  }`}
                  onClick={() => handleNotificationClick(notification)}
                >
                  <div className="flex items-start gap-3">
                    <div className="mt-1">
                      {getIconForType(notification.type, notification.color)}
                    </div>

                    <div className="flex-1 min-w-0">
                      <div className="flex items-start justify-between gap-2">
                        <h3 className={`text-sm font-semibold ${isDarkMode ? 'text-amber-50' : 'text-amber-900'}`}>
                          {notification.title}
                        </h3>
                        {!notification.is_read && (
                          <div className="h-2 w-2 bg-amber-400 rounded-full flex-shrink-0 mt-1.5" />
                        )}
                      </div>

                      <p className={`text-xs ${isDarkMode ? 'text-gray-400' : 'text-gray-500'} mt-1 line-clamp-2`}>
                        {notification.message}
                      </p>

                      <p className={`text-xs ${isDarkMode ? 'text-gray-500' : 'text-gray-400'} mt-2`}>
                        {new Date(notification.created_at).toLocaleDateString('en-US', {
                          month: 'short',
                          day: 'numeric',
                          hour: '2-digit',
                          minute: '2-digit'
                        })}
                      </p>
                    </div>

                    <div className="flex gap-1 flex-shrink-0">
                      {!notification.is_read && (
                        <button
                          onClick={(e) => { e.stopPropagation(); markAsRead(notification.id); }}
                          className={`p-1 ${isDarkMode ? 'text-gray-400 hover:text-amber-400 hover:bg-gray-700' : 'text-gray-500 hover:text-amber-500 hover:bg-gray-200'} rounded transition-all`}
                          title="Mark as read"
                        >
                          <CheckIcon className="h-4 w-4" />
                        </button>
                      )}
                      <button
                        onClick={(e) => { e.stopPropagation(); deleteNotification(notification.id); }}
                        className={`p-1 ${isDarkMode ? 'text-gray-400 hover:text-red-400 hover:bg-gray-700' : 'text-gray-500 hover:text-red-500 hover:bg-gray-200'} rounded transition-all`}
                        title="Delete"
                      >
                        <TrashIcon className="h-4 w-4" />
                      </button>
                    </div>
                  </div>
                </div>
              ))}
            </div>
          )}
        </div>

        {/* Footer */}
        <div className={`${isDarkMode ? 'bg-gray-950 border-gray-700' : 'bg-gray-50 border-gray-200'} border-t p-3 flex justify-center`}>
          <button
            onClick={onClose}
            className="text-xs text-amber-400 hover:text-amber-300 font-medium"
          >
            Close
          </button>
        </div>
      </div>
    </div>
  );
};

export default NotificationCenter;
