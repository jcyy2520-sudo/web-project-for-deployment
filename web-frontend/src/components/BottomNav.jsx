import React, { useState, useEffect } from 'react';
import { useNavigate, useLocation } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';
import axios from 'axios';
import {
  HomeIcon,
  CalendarDaysIcon,
  PlusIcon,
  ChatBubbleLeftRightIcon,
  BellIcon,
  ChevronDownIcon,
    UserIcon,
} from '@heroicons/react/24/outline';

const BottomNav = () => {
  const navigate = useNavigate();
  const location = useLocation();
  const { logout } = useAuth();
  
  // Determine active tab from current URL
  const searchParams = new URLSearchParams(location.search);
  const activeTab = searchParams.get('tab') || 'home';

  const handleNavigate = (to) => {
    navigate(to);
  };

  const navItems = [
    { key: 'home', label: 'Home', icon: HomeIcon, to: '/dashboard?tab=home' },
    { key: 'appointments', label: 'Appointments', icon: CalendarDaysIcon, to: '/dashboard?tab=appointments' },
    { key: 'book', label: 'Book', icon: PlusIcon, to: '/dashboard?tab=book' },
    { key: 'messages', label: 'Messages', icon: ChatBubbleLeftRightIcon, to: '/dashboard?tab=messages' },
    { key: 'profile', label: 'Profile', icon: UserIcon, to: '/dashboard?tab=profile' },
  ];

  return (
    <div>
      {/* Bottom nav only visible on small screens */}
      <nav aria-label="Main navigation" style={{paddingBottom: 'env(safe-area-inset-bottom)'}} className="fixed bottom-0 left-0 right-0 sm:hidden z-[60] pointer-events-auto px-4 pb-4">
        <div className="bg-gray-900/80 backdrop-blur-3xl rounded-[28px] border border-white/[0.1] shadow-[0_12px_48px_rgba(0,0,0,0.6),0_2px_16px_rgba(0,0,0,0.4)] px-1.5 py-1.5 ring-1 ring-white/5">
          <div className="flex items-center justify-between" role="tablist">
            {navItems.map(({ key, label, icon: Icon, to }) => {
              const isActive = activeTab === key;
              const isSpecial = key === 'book';
              
              if (isSpecial) {
                return (
                  <button
                    key={key}
                    type="button"
                    onClick={() => handleNavigate(to)}
                    role="tab"
                    className="flex flex-col items-center justify-center transition-all duration-300"
                  >
                    <div className={`w-12 h-12 rounded-full flex items-center justify-center shadow-lg transition-all duration-300 ${
                      isActive 
                        ? 'bg-amber-500 scale-105' 
                        : 'bg-gray-800 border border-white/10'
                    }`}>
                      <Icon className={`w-6 h-6 ${isActive ? 'text-white' : 'text-amber-500'}`} strokeWidth={isActive ? 2.5 : 2} />
                    </div>
                    <span className={`text-[10px] font-bold mt-1 transition-colors duration-200 ${
                      isActive ? 'text-amber-400' : 'text-gray-400'
                    }`}>
                      {label}
                    </span>
                  </button>
                );
              }

              return (
                <button
                  key={key}
                  type="button"
                  onClick={() => handleNavigate(to)}
                  role="tab"
                  aria-label={label}
                  aria-selected={isActive}
                  className={`flex flex-col items-center justify-center gap-1 py-1 rounded-2xl transition-all duration-300 min-w-[60px] ${
                    isActive ? 'text-amber-400' : 'text-gray-500'
                  }`}
                >
                  <Icon 
                    className={`transition-all duration-300 ${
                      isActive ? 'w-6 h-6' : 'w-5 h-5'
                    }`} 
                    strokeWidth={isActive ? 2.5 : 1.5}
                  />
                  <span className={`text-[10px] font-medium transition-opacity ${isActive ? 'opacity-100' : 'opacity-70'}`}>
                    {label}
                  </span>
                </button>
              );
            })}
          </div>
        </div>
      </nav>
    </div>
  );
};

export default BottomNav;
