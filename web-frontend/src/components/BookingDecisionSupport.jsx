import { useState, useEffect, useMemo } from 'react';
import axios from 'axios';
import { useApi } from '../hooks/useApi';
import {
  ExclamationTriangleIcon,
  CheckCircleIcon,
  SparklesIcon,
  ChevronRightIcon,
  CalendarIcon,
  ClockIcon
} from '@heroicons/react/24/outline';
import LoadingSpinner from './LoadingSpinner';

/**
 * Booking Decision Support Component
 * Suggests alternative dates and times when requested slot is unavailable
 * Displays available slots with capacity information
 */
const BookingDecisionSupport = ({ selectedDate, isDarkMode = true, onSuggestions }) => {
  const { callApi, loading } = useApi();
  const [suggestions, setSuggestions] = useState([]);
  const [showSuggestions, setShowSuggestions] = useState(false);

  // Business hours configuration
  const BUSINESS_HOURS = {
    start: 8,
    end: 17,
    lunchStart: 12,
    lunchEnd: 13
  };

  // Generate 30-minute time slots
  const generateTimeSlots = (date) => {
    const slots = [];
    const [hours, minutes] = [BUSINESS_HOURS.start, 0];
    
    for (let h = BUSINESS_HOURS.start; h < BUSINESS_HOURS.end; h++) {
      // Skip lunch break
      if (h >= BUSINESS_HOURS.lunchStart && h < BUSINESS_HOURS.lunchEnd) {
        continue;
      }

      slots.push({
        time: `${String(h).padStart(2, '0')}:00`,
        display: new Date(0, 0, 0, h, 0).toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true })
      });
      slots.push({
        time: `${String(h).padStart(2, '0')}:30`,
        display: new Date(0, 0, 0, h, 30).toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true })
      });
    }

    return slots;
  };

  useEffect(() => {
    if (selectedDate) {
      generateSuggestions();
    }
  }, [selectedDate]);

  const generateSuggestions = async () => {
    try {
      const result = await callApi(() =>
        axios.post('/api/appointments/suggest-alternative', {
          preferred_date: selectedDate,
          days_ahead: 14
        })
      );

      if (result.success) {
        const alts = result.data.alternatives || [];
        setSuggestions(alts);
        if (typeof onSuggestions === 'function') onSuggestions(alts);
        setShowSuggestions(true);
      }
    } catch (err) {
      // Silently fail - suggestions are optional
      console.error('Failed to load suggestions:', err);
    }
  };

  if (loading || !showSuggestions) {
    return null;
  }

  if (!suggestions || suggestions.length === 0) {
    return null;
  }

  return (
    <div className={`rounded-lg border-2 border-amber-400 p-4 ${
      isDarkMode ? 'bg-amber-950/50' : 'bg-amber-50'
    }`}>
      <div className="flex items-start gap-3">
        <SparklesIcon className={`h-5 w-5 flex-shrink-0 mt-0.5 ${
          isDarkMode ? 'text-amber-400' : 'text-amber-600'
        }`} />
        
        <div className="flex-1">
          <h4 className={`font-semibold mb-2 flex items-center gap-2 ${
            isDarkMode ? 'text-amber-50' : 'text-amber-900'
          }`}>
            <span>💡 Recommended Alternative Times</span>
          </h4>

          <div className="space-y-2">
            {suggestions.slice(0, 3).map((suggestion, idx) => (
              <div
                key={idx}
                className={`p-3 rounded border ${
                  isDarkMode
                    ? 'bg-gray-800 border-amber-500/30'
                    : 'bg-white border-amber-200'
                }`}
              >
                <div className="flex items-center justify-between mb-2">
                  <div className="flex items-center gap-2">
                    <CalendarIcon className={`h-4 w-4 ${
                      isDarkMode ? 'text-amber-400' : 'text-amber-600'
                    }`} />
                    <span className={`font-medium text-sm ${
                      isDarkMode ? 'text-amber-50' : 'text-amber-900'
                    }`}>
                      {new Date(suggestion.date).toLocaleDateString('en-US', {
                        weekday: 'short',
                        month: 'short',
                        day: 'numeric'
                      })}
                    </span>
                  </div>
                  <span className={`text-xs font-semibold px-2 py-1 rounded ${
                    suggestion.available_slots > 2
                      ? isDarkMode
                        ? 'bg-green-500/20 text-green-300'
                        : 'bg-green-100 text-green-700'
                      : isDarkMode
                      ? 'bg-yellow-500/20 text-yellow-300'
                      : 'bg-yellow-100 text-yellow-700'
                  }`}>
                    {suggestion.available_slots} slots available
                  </span>
                </div>

                <div className="flex items-center gap-2 mb-2">
                  <ClockIcon className="h-3.5 w-3.5 text-gray-500" />
                  <span className={`text-xs ${
                    isDarkMode ? 'text-gray-300' : 'text-gray-600'
                  }`}>
                    Available times: {suggestion.available_times?.join(', ') || 'Multiple times'}
                  </span>
                </div>

                <div className={`text-xs ${
                  isDarkMode ? 'text-gray-400' : 'text-gray-600'
                }`}>
                  Capacity: {suggestion.available_slots > 0 
                    ? `${suggestion.available_slots} slots (High availability)` 
                    : 'Limited availability'}
                </div>
              </div>
            ))}
          </div>

          <p className={`text-xs mt-3 ${
            isDarkMode ? 'text-amber-300' : 'text-amber-700'
          }`}>
            ✓ Select one of these alternatives for a smoother booking experience
          </p>
        </div>
      </div>
    </div>
  );
};

export default BookingDecisionSupport;
