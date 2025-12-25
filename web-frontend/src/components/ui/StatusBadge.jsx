import React from 'react';
import { 
  ClockIcon,
  CheckCircleIcon,
  XCircleIcon
} from '@heroicons/react/24/outline';

const StatusBadge = ({ status }) => {
  const statusConfig = {
    pending: {
      color: 'bg-amber-100 text-amber-800 border border-amber-200 dark:bg-amber-500/20 dark:text-amber-300 dark:border-amber-500/30',
      icon: ClockIcon,
      glow: 'shadow-amber-100'
    },
    approved: {
      color: 'bg-blue-100 text-blue-800 border border-blue-200 dark:bg-blue-500/20 dark:text-blue-300 dark:border-blue-500/30',
      icon: CheckCircleIcon,
      glow: 'shadow-blue-100'
    },
    completed: {
      color: 'bg-green-100 text-green-800 border border-green-200 dark:bg-green-500/20 dark:text-green-300 dark:border-green-500/30',
      icon: CheckCircleIcon,
      glow: 'shadow-green-100'
    },
    cancelled: {
      color: 'bg-red-100 text-red-800 border border-red-200 dark:bg-red-500/20 dark:text-red-300 dark:border-red-500/30',
      icon: XCircleIcon,
      glow: 'shadow-red-100'
    },
    declined: {
      color: 'bg-red-100 text-red-800 border border-red-200 dark:bg-red-500/20 dark:text-red-300 dark:border-red-500/30',
      icon: XCircleIcon,
      glow: 'shadow-red-100'
    }
  };
  
  const config = statusConfig[status] || statusConfig.pending;
  const IconComponent = config.icon;
  
  return (
    <span className={`inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium ${config.color} ${config.glow} shadow hover:scale-105 transition-transform duration-200`}>
      <IconComponent className="w-3 h-3 mr-1" />
      {status.charAt(0).toUpperCase() + status.slice(1)}
    </span>
  );
};

export default StatusBadge;