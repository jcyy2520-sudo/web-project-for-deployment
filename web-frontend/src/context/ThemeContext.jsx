import React, { createContext, useContext, useEffect, useState } from 'react';

const ThemeContext = createContext();

export const ThemeProvider = ({ children }) => {
  // Initialize theme from localStorage (or system preference) before any render
  const [isDarkMode, setIsDarkMode] = useState(() => {
    try {
      // Check localStorage first
      const saved = localStorage.getItem('isDarkMode');
      if (saved !== null) {
        return saved === 'true';
      }
      
      // Fallback to system preference
      if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
        return true;
      }
      
      // Default to dark mode
      return true;
    } catch (e) {
      return true;
    }
  });

  // Apply theme to DOM immediately when isDarkMode changes
  useEffect(() => {
    // Apply to document root
    const htmlElement = document.documentElement;
    
    if (isDarkMode) {
      htmlElement.classList.add('dark');
      htmlElement.setAttribute('data-theme', 'dark');
    } else {
      htmlElement.classList.remove('dark');
      htmlElement.setAttribute('data-theme', 'light');
    }
    
    // Persist to localStorage
    try {
      localStorage.setItem('isDarkMode', isDarkMode.toString());
    } catch (e) {
      console.error('Failed to persist theme preference:', e);
    }
  }, [isDarkMode]);

  const toggleTheme = () => {
    setIsDarkMode(prev => !prev);
  };

  return (
    <ThemeContext.Provider value={{ isDarkMode, setIsDarkMode, toggleTheme }}>
      {children}
    </ThemeContext.Provider>
  );
};

export const useTheme = () => {
  const context = useContext(ThemeContext);
  if (!context) {
    throw new Error('useTheme must be used within a ThemeProvider');
  }
  return context;
};
