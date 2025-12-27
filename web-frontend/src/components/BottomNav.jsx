import React from 'react';
import { useNavigate } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';
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
  const { logout } = useAuth();

  const handleNavigate = (to) => {
    navigate(to);
  };

  return (
    <div>
      {/* Bottom nav only visible on small screens - Full width */}
      <nav style={{paddingBottom: 'env(safe-area-inset-bottom)'}} className="fixed bottom-0 left-0 right-0 sm:hidden bg-white/95 dark:bg-gray-900/95 border-t border-slate-100 dark:border-amber-500/20 shadow-lg flex items-center justify-between px-0 py-2 z-[60] pointer-events-auto">
        <div className="flex items-center justify-between w-full">
        <button type="button" onClick={() => handleNavigate('/dashboard?tab=home')} className="flex-1 flex flex-col items-center gap-1 text-slate-700 dark:text-gray-200 px-2 py-1 rounded hover:bg-slate-50 dark:hover:bg-gray-800">
          <HomeIcon className="w-5 h-5" />
          <span className="text-[11px]">Home</span>
        </button>

        <button type="button" onClick={() => handleNavigate('/dashboard?tab=appointments')} className="flex-1 flex flex-col items-center gap-1 text-slate-700 dark:text-gray-200 px-2 py-1 rounded hover:bg-slate-50 dark:hover:bg-gray-800">
          <CalendarDaysIcon className="w-5 h-5" />
          <span className="text-[11px]">My Appointments</span>
        </button>

        <button type="button" onClick={() => handleNavigate('/dashboard?tab=book')} className="flex-1 flex flex-col items-center gap-1 text-slate-700 dark:text-gray-200 px-2 py-1 rounded hover:bg-slate-50 dark:hover:bg-gray-800">
          <PlusIcon className="w-5 h-5" />
          <span className="text-[11px]">Book</span>
        </button>

        <button type="button" onClick={() => handleNavigate('/dashboard?tab=messages')} className="flex-1 flex flex-col items-center gap-1 text-slate-700 dark:text-gray-200 px-2 py-1 rounded hover:bg-slate-50 dark:hover:bg-gray-800">
          <ChatBubbleLeftRightIcon className="w-5 h-5" />
          <span className="text-[11px]">Messages</span>
        </button>

        <button type="button" onClick={() => handleNavigate('/dashboard?tab=profile')} className="flex-1 flex flex-col items-center gap-1 text-slate-700 dark:text-gray-200 px-2 py-1 rounded hover:bg-slate-50 dark:hover:bg-gray-800">
          <UserIcon className="w-5 h-5" />
          <span className="text-[11px]">Profile</span>
        </button>
        </div>
      </nav>
    </div>
  );
};

export default BottomNav;
