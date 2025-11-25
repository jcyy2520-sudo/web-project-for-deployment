import { useState, useEffect, useCallback } from 'react';
import axios from 'axios';
import {
  CheckCircleIcon,
  XCircleIcon,
  ExclamationTriangleIcon,
  InformationCircleIcon
} from '@heroicons/react/24/outline';

const Toast = ({ id, title, message, type = 'info', duration = 5000, onClose }) => {
  useEffect(() => {
    const timer = setTimeout(() => onClose(id), duration);
    return () => clearTimeout(timer);
  }, [id, duration, onClose]);

  const getStyles = () => {
    switch (type) {
      case 'success':
        return {
          bg: 'bg-green-500/20',
          border: 'border-green-500/30',
          icon: CheckCircleIcon,
          iconColor: 'text-green-400'
        };
      case 'error':
        return {
          bg: 'bg-red-500/20',
          border: 'border-red-500/30',
          icon: XCircleIcon,
          iconColor: 'text-red-400'
        };
      case 'warning':
        return {
          bg: 'bg-yellow-500/20',
          border: 'border-yellow-500/30',
          icon: ExclamationTriangleIcon,
          iconColor: 'text-yellow-400'
        };
      default:
        return {
          bg: 'bg-blue-500/20',
          border: 'border-blue-500/30',
          icon: InformationCircleIcon,
          iconColor: 'text-blue-400'
        };
    }
  };

  const styles = getStyles();
  const IconComponent = styles.icon;

  return (
    <div className={`${styles.bg} border ${styles.border} rounded-lg shadow-lg p-4 mb-3 animate-slideDown max-w-sm`}>
      <div className="flex items-start gap-3">
        <IconComponent className={`h-5 w-5 ${styles.iconColor} flex-shrink-0 mt-0.5`} />
        <div className="flex-1">
          <h3 className="text-sm font-semibold text-white">{title}</h3>
          {message && <p className="text-xs text-gray-300 mt-1">{message}</p>}
        </div>
        <button
          onClick={() => onClose(id)}
          className="text-gray-400 hover:text-gray-300 flex-shrink-0"
        >
          ✕
        </button>
      </div>
    </div>
  );
};

const ToastContainer = ({ isDarkMode = true }) => {
  const [toasts, setToasts] = useState([]);
  const [nextId, setNextId] = useState(0);

  const removeToast = useCallback((id) => {
    setToasts(prev => prev.filter(t => t.id !== id));
  }, []);

  // Global toast function - attach to window for use everywhere
  useEffect(() => {
    window.showToast = (title, message, type = 'info', duration = 5000) => {
      const id = nextId;
      setNextId(prev => prev + 1);
      setToasts(prev => [...prev, { id, title, message, type, duration }]);
      return id;
    };

    return () => {
      delete window.showToast;
    };
  }, [nextId]);

  return (
    <div className={`fixed top-4 right-4 z-[9999] pointer-events-none space-y-2`}>
      <div className="pointer-events-auto">
        {toasts.map(toast => (
          <Toast
            key={toast.id}
            id={toast.id}
            title={toast.title}
            message={toast.message}
            type={toast.type}
            duration={toast.duration}
            onClose={removeToast}
          />
        ))}
      </div>
    </div>
  );
};

export { Toast, ToastContainer };
export default ToastContainer;
