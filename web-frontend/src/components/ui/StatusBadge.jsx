import React from 'react';
import { 
  ClockIcon,
  CheckCircleIcon,
  XCircleIcon
} from '@heroicons/react/24/outline';

const StatusBadge = ({ status }) => {
  const statusConfig = {
    pending: { 
      color: 'bg-amber-500/20 text-amber-300 border border-amber-500/30', 
      icon: ClockIcon,
      glow: 'shadow-amber-500/20'
    },
    approved: { 
      color: 'bg-blue-500/20 text-blue-300 border border-blue-500/30', 
      icon: CheckCircleIcon,
      glow: 'shadow-blue-500/20'
    },
    completed: { 
      color: 'bg-green-500/20 text-green-300 border border-green-500/30', 
      icon: CheckCircleIcon,
      glow: 'shadow-green-500/20'
    },
    cancelled: { 
      color: 'bg-red-500/20 text-red-300 border border-red-500/30', 
      icon: XCircleIcon,
      glow: 'shadow-red-500/20'
    },
    declined: { 
      color: 'bg-red-500/20 text-red-300 border border-red-500/30', 
      icon: XCircleIcon,
      glow: 'shadow-red-500/20'
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