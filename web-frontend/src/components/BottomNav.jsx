import React, { useState, useEffect } from 'react';
import { useNavigate, useLocation } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';
import axios from 'axios';
import {
  HomeIcon,
  CalendarDaysIcon,
  PlusIcon,
  ChatBubbleLeftRightIcon,
  UserIcon,
  ChevronDownIcon,
  ArrowPathIcon,
  DocumentTextIcon,
  Cog6ToothIcon,
  KeyIcon,
  CurrencyDollarIcon
} from '@heroicons/react/24/outline';

const BottomNav = () => {
  const navigate = useNavigate();
  const location = useLocation();
  const { logout } = useAuth();

  const handleNavigate = (to) => {
    navigate(to);
  };

  // Determine active tab from current URL
  const searchParams = new URLSearchParams(location.search);
  const activeTab = searchParams.get('tab') || 'home';

  const navItems = [
    { key: 'home', label: 'Home', icon: HomeIcon, to: '/dashboard?tab=home' },
    { key: 'appointments', label: 'My Appointments', icon: CalendarDaysIcon, to: '/dashboard?tab=appointments' },
    { key: 'book', label: 'Book', icon: PlusIcon, to: '/dashboard?tab=book' },
    { key: 'messages', label: 'Messages', icon: ChatBubbleLeftRightIcon, to: '/dashboard?tab=messages' },
    { key: 'profile', label: 'Profile', icon: UserIcon, to: '/dashboard?tab=profile' },
  ];

  return (
    <div>
      {/* Bottom nav only visible on small screens - Full width */}
      <nav aria-label="Main navigation" style={{paddingBottom: 'env(safe-area-inset-bottom)'}} className="fixed bottom-0 left-0 right-0 sm:hidden bg-white/95 dark:bg-gray-900/95 border-t border-slate-100 dark:border-amber-500/20 shadow-lg flex items-center justify-between px-0 py-2 z-[60] pointer-events-auto backdrop-blur-md">
        <div className="flex items-center justify-between w-full" role="tablist">
          {navItems.map(({ key, label, icon: Icon, to }) => {
            const isActive = activeTab === key;
            return (
              <button
                key={key}
                type="button"
                onClick={() => handleNavigate(to)}
                role="tab"
                aria-label={label}
                aria-selected={isActive}
                aria-current={isActive ? 'page' : undefined}
                className={`flex-1 flex flex-col items-center gap-1 px-2 py-1.5 rounded-lg transition-all duration-200 ${
                  isActive
                    ? 'text-amber-600 dark:text-amber-400 bg-amber-50 dark:bg-amber-500/10 scale-105 font-semibold'
                    : 'text-slate-500 dark:text-gray-400 hover:text-slate-700 dark:hover:text-gray-200 hover:bg-slate-50 dark:hover:bg-gray-800 active:scale-95'
                }`}
              >
                <Icon className={`w-5 h-5 transition-transform ${isActive ? 'scale-110' : ''}`} aria-hidden="true" />
                <span className="text-[11px] leading-tight">{label}</span>
              </button>
            );
          })}
        </div>
      </nav>
    </div>
  );
};

export default BottomNav;
