/**
 * Dashboard navigation structure
 */
export const getDashboardNavigation = (stats, counts) => [
  { 
    name: 'Dashboard', 
    icon: 'HomeIcon', 
    key: 'dashboard'
  },
  { 
    section: 'Appointments',
    items: [
      { 
        name: `All Appointments (${stats.totalAppointments || 0})`, 
        icon: 'CalendarIcon', 
        key: 'appointments'
      },
      { 
        name: `Calendar Settings (${counts.unavailableDates || 0})`, 
        icon: 'CalendarDaysIcon', 
        key: 'calendar'
      },
      { 
        name: `Services (${counts.services || 0})`, 
        icon: 'DocumentTextIcon', 
        key: 'services'
      }
    ]
  },
  { 
    section: 'User Management',
    items: [
      { 
        name: `All Users (${counts.clients || 0})`, 
        icon: 'UserGroupIcon', 
        key: 'users'
      },
      { 
        name: `Admin Accounts (${counts.admins || 0})`, 
        icon: 'ShieldCheckIcon', 
        key: 'adminProfile'
      },
      { 
        name: `Deactivated Accounts (${counts.deactivated || 0})`, 
        icon: 'UserMinusIcon', 
        key: 'deactivated'
      }
    ]
  },
  { 
    section: 'Communication',
    items: [
      { 
        name: 'Messages', 
        icon: 'ChatBubbleBottomCenterTextIcon', 
        key: 'messages'
      },
      { 
        name: 'Action Logs', 
        icon: 'ClockIcon', 
        key: 'action-logs'
      }
    ]
  },
  { 
    section: 'Reports & Analytics',
    items: [
      { 
        name: 'Reports', 
        icon: 'DocumentChartBarIcon', 
        key: 'reports'
      },
      { 
        name: 'Archive', 
        icon: 'ArchiveBoxIcon', 
        key: 'archive'
      }
    ]
  },
  { 
    name: 'Settings', 
    icon: 'CogIcon', 
    key: 'settings'
  }
];

/**
 * Status badge configuration
 */
export const STATUS_CONFIG = {
  pending: { 
    color: 'bg-amber-500/20 text-amber-300 border border-amber-500/30', 
    icon: 'ClockIcon',
    glow: 'shadow-amber-500/20'
  },
  approved: { 
    color: 'bg-blue-500/20 text-blue-300 border border-blue-500/30', 
    icon: 'CheckCircleIcon',
    glow: 'shadow-blue-500/20'
  },
  completed: { 
    color: 'bg-green-500/20 text-green-300 border border-green-500/30', 
    icon: 'CheckCircleIcon',
    glow: 'shadow-green-500/20'
  },
  cancelled: { 
    color: 'bg-red-500/20 text-red-300 border border-red-500/30', 
    icon: 'XCircleIcon',
    glow: 'shadow-red-500/20'
  },
  declined: { 
    color: 'bg-red-500/20 text-red-300 border border-red-500/30', 
    icon: 'XCircleIcon',
    glow: 'shadow-red-500/20'
  },
  active: { 
    color: 'bg-green-500/20 text-green-300 border border-green-500/30', 
    icon: 'CheckCircleIcon',
    glow: 'shadow-green-500/20'
  },
  inactive: { 
    color: 'bg-red-500/20 text-red-300 border border-red-500/30', 
    icon: 'XCircleIcon',
    glow: 'shadow-red-500/20'
  }
};

export default {
  getDashboardNavigation,
  STATUS_CONFIG
};
