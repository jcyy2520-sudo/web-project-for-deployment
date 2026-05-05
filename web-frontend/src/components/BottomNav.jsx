import React from 'react';
import { useNavigate, useLocation } from 'react-router-dom';
import { useTheme } from '../context/ThemeContext';
import {
  HomeIcon,
  CalendarDaysIcon,
  PlusIcon,
  ChatBubbleLeftRightIcon,
  UserCircleIcon,
} from '@heroicons/react/24/outline';

const BottomNav = () => {
  const navigate = useNavigate();
  const location = useLocation();
  const { isDarkMode } = useTheme();
  
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
    { key: 'profile', label: 'Profile', icon: UserCircleIcon, to: '/dashboard?tab=profile' },
  ];

  return (
    <div>
      {/* Bottom nav only visible on small screens */}
      <nav aria-label="Main navigation" className="fixed bottom-3 left-3 right-3 sm:hidden z-[60] pointer-events-auto">
        <div className={`backdrop-blur-2xl rounded-[28px] shadow-[0_8px_32px_rgba(0,0,0,0.12)] transition-all duration-300 px-1 py-1 ${
          isDarkMode 
            ? 'bg-gray-950/55 ring-1 ring-white/5 shadow-black/40' 
            : 'bg-white/55 ring-1 ring-black/5 shadow-black/8'
        }`}>
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
                    <div className={`w-11 h-11 rounded-full flex items-center justify-center transition-all duration-300 ${
                      isActive 
                        ? 'bg-amber-500 shadow-[0_0_15px_rgba(245,158,11,0.5)] scale-105' 
                        : isDarkMode 
                          ? 'bg-gray-800 border border-white/10 shadow-lg shadow-black/20 hover:shadow-amber-500/20' 
                          : 'bg-amber-50/50 border border-amber-200/70 shadow-sm shadow-amber-500/5 hover:border-amber-300'
                    }`}>
                      <Icon className={`w-6 h-6 ${isActive ? 'text-white' : 'text-amber-600 filter drop-shadow-[0_0_3px_rgba(245,158,11,0.3)]'}`} strokeWidth={isActive ? 2.5 : 2} />
                    </div>
                    <span className={`text-[10px] font-bold mt-1 transition-colors duration-200 ${
                      isActive ? (isDarkMode ? 'text-amber-400' : 'text-amber-700') : (isDarkMode ? 'text-gray-400' : 'text-slate-400')
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
                  className={`flex flex-col items-center justify-center gap-0.5 py-1 rounded-2xl transition-all duration-300 min-w-[55px] ${
                    isActive 
                      ? (isDarkMode ? 'text-amber-400' : 'text-amber-600') 
                      : (isDarkMode ? 'text-gray-500' : 'text-slate-400')
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
