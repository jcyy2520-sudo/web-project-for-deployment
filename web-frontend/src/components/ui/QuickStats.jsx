import React from 'react';
import { 
  UsersIcon,
  CalendarDaysIcon,
  ClockIcon,
  BuildingLibraryIcon
} from '@heroicons/react/24/outline';

const QuickStats = ({ stats, onStatClick }) => {
  const statCards = [
    {
      name: 'Total Users',
      value: stats.totalUsers?.toString() || '0',
      icon: UsersIcon,
      color: 'bg-purple-500',
      change: '+12%',
      trend: 'up',
      key: 'users'
    },
    {
      name: 'Total Appointments',
      value: stats.totalAppointments?.toString() || '0',
      icon: CalendarDaysIcon,
      color: 'bg-blue-500',
      change: '+8%',
      trend: 'up',
      key: 'appointments'
    },
    {
      name: 'Pending Actions',
      value: stats.pendingAppointments?.toString() || '0',
      icon: ClockIcon,
      color: 'bg-amber-500',
      change: '+5%',
      trend: 'up',
      key: 'appointments'
    },
    {
      name: 'Revenue',
      value: `$${stats.revenue?.toLocaleString() || '0'}`,
      icon: BuildingLibraryIcon,
      color: 'bg-green-500',
      change: '+15%',
      trend: 'up',
      key: 'revenue'
    }
  ];

  return (
    <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
      {statCards.map((card, index) => (
        <div
          key={index}
          onClick={() => onStatClick(card.key)}
          className="bg-gray-900 border border-amber-500/20 rounded-lg shadow p-4 hover:border-amber-500/40 hover:shadow-amber-500/10 transition-all duration-300 cursor-pointer group transform hover:-translate-y-1"
        >
          <div className="flex items-center justify-between">
            <div>
              <p className="text-xs font-medium text-gray-400 group-hover:text-amber-300 transition-colors">
                {card.name}
              </p>
              <p className="text-lg font-bold text-amber-50 mt-0.5 group-hover:scale-105 transition-transform">
                {card.value}
              </p>
              <div className={`flex items-center mt-1 text-xs ${
                card.trend === 'up' ? 'text-green-400' : 'text-red-400'
              }`}>
                <span>{card.change}</span>
                <span className="ml-1">from last month</span>
              </div>
            </div>
            <div className={`${card.color} p-2 rounded-lg shadow group-hover:scale-110 transition-transform`}>
              <card.icon className="h-5 w-5 text-white" />
            </div>
          </div>
        </div>
      ))}
    </div>
  );
};

export default QuickStats;