import React from 'react';
import { SunIcon, MoonIcon } from '@heroicons/react/24/outline';
import { useTheme } from '../../context/ThemeContext';

const ThemeToggle = ({ className = "" }) => {
  const { isDarkMode, toggleTheme } = useTheme();

  return (
    <button
      onClick={toggleTheme}
      className={`p-2 rounded-lg transition-all duration-300 ${
        isDarkMode 
          ? 'text-amber-400 hover:bg-amber-500/10 hover:text-amber-300' 
          : 'text-amber-700 hover:bg-amber-50 hover:text-amber-600'
      } ${className}`}
      aria-label={isDarkMode ? "Switch to light mode" : "Switch to dark mode"}
      title={isDarkMode ? "Switch to light mode" : "Switch to dark mode"}
    >
      <div className="relative h-5 w-5">
        {isDarkMode ? (
          <SunIcon className="absolute inset-0 h-5 w-5 transform transition-transform duration-500 rotate-0 scale-100" />
        ) : (
          <MoonIcon className="absolute inset-0 h-5 w-5 transform transition-transform duration-500 rotate-0 scale-100" />
        )}
      </div>
    </button>
  );
};

export default ThemeToggle;
