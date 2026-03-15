import { useState, useEffect } from 'react';
import axios from 'axios';
import {
  ClockIcon,
  CheckCircleIcon,
  ExclamationTriangleIcon,
  SparklesIcon,
  UserGroupIcon,
  InformationCircleIcon,
} from '@heroicons/react/24/outline';

/**
 * TimeSlotRecommendations Component
 * 
 * Displays recommended time slots with:
 * - 12-hour time format
 * - Demand level badges (Low, Moderate, High, etc.)
 * - Staff availability count
 * - Score-based visual indicator
 * - Tags (Best Time, Recommended, Filling Up)
 * - Day summary info
 */
const TimeSlotRecommendations = ({ appointmentDate, isDarkMode = true, onSelectSlot }) => {
  const [recommendations, setRecommendations] = useState(null);
  const [summary, setSummary] = useState(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);
  const [selectedSlot, setSelectedSlot] = useState(null);

  useEffect(() => {
    if (appointmentDate) {
      fetchTimeSlotRecommendations();
    }
  }, [appointmentDate]);

  const fetchTimeSlotRecommendations = async () => {
    setLoading(true);
    setError(null);

    try {
      const response = await axios.get('/api/decision-support/time-slot-recommendations', {
        params: { appointment_date: appointmentDate },
      });

      // Handle both old and new response format
      const data = response.data.data;
      if (Array.isArray(data)) {
        setRecommendations(data);
      } else if (data?.slots) {
        setRecommendations(data.slots);
      }
      setSummary(response.data.summary || data?.summary || null);
    } catch (err) {
      setError('Failed to fetch time slot recommendations');
      console.error(err);
    } finally {
      setLoading(false);
    }
  };

  const handleSelectSlot = (time) => {
    setSelectedSlot(time);
    if (onSelectSlot) {
      onSelectSlot(time);
    }
  };

  const formatTime12h = (time24) => {
    if (!time24) return '';
    const [h, m] = time24.split(':').map(Number);
    const ampm = h >= 12 ? 'PM' : 'AM';
    const hour12 = h % 12 || 12;
    return `${hour12}:${String(m).padStart(2, '0')} ${ampm}`;
  };

  const getScoreColor = (score) => {
    if (score >= 35) return isDarkMode ? 'text-green-400' : 'text-green-600';
    if (score >= 20) return isDarkMode ? 'text-amber-400' : 'text-amber-600';
    return isDarkMode ? 'text-gray-400' : 'text-gray-600';
  };

  const getScoreBarColor = (score) => {
    if (score >= 35) return 'bg-green-500';
    if (score >= 20) return 'bg-amber-500';
    return 'bg-gray-400';
  };

  const getDemandBadge = (level) => {
    const configs = {
      low: { label: 'Low Demand', cls: isDarkMode ? 'bg-green-500/20 text-green-300 border-green-500/30' : 'bg-green-50 text-green-700 border-green-200' },
      moderate: { label: 'Moderate', cls: isDarkMode ? 'bg-blue-500/20 text-blue-300 border-blue-500/30' : 'bg-blue-50 text-blue-700 border-blue-200' },
      high: { label: 'High Demand', cls: isDarkMode ? 'bg-amber-500/20 text-amber-300 border-amber-500/30' : 'bg-amber-50 text-amber-700 border-amber-200' },
      very_high: { label: 'Very Busy', cls: isDarkMode ? 'bg-red-500/20 text-red-300 border-red-500/30' : 'bg-red-50 text-red-700 border-red-200' },
      full: { label: 'Full', cls: isDarkMode ? 'bg-red-500/20 text-red-400 border-red-500/30' : 'bg-red-50 text-red-600 border-red-200' },
    };
    const cfg = configs[level] || configs.low;
    return <span className={`text-xs font-medium px-2 py-0.5 rounded-full border ${cfg.cls}`}>{cfg.label}</span>;
  };

  const getTagBadge = (tag) => {
    if (!tag) return null;
    const configs = {
      'Best Time': isDarkMode ? 'bg-amber-500/20 text-amber-300 border-amber-500/40' : 'bg-amber-100 text-amber-700 border-amber-300',
      'Recommended': isDarkMode ? 'bg-green-500/20 text-green-300 border-green-500/40' : 'bg-green-100 text-green-700 border-green-300',
      'Filling Up': isDarkMode ? 'bg-red-500/20 text-red-300 border-red-500/40' : 'bg-red-100 text-red-700 border-red-300',
      'Unavailable': isDarkMode ? 'bg-gray-600/50 text-gray-400 border-gray-500/40' : 'bg-gray-100 text-gray-500 border-gray-300',
    };
    const cls = configs[tag] || (isDarkMode ? 'bg-gray-700 text-gray-300 border-gray-600' : 'bg-gray-100 text-gray-600 border-gray-200');
    return <span className={`text-xs font-bold px-2 py-0.5 rounded-full border ${cls}`}>{tag}</span>;
  };

  if (loading) {
    return (
      <div className={`p-4 rounded-xl border ${isDarkMode ? 'bg-gray-800 border-gray-700' : 'bg-white border-gray-200'}`}>
        <div className="flex items-center justify-center py-6 gap-3">
          <div className="animate-spin rounded-full h-5 w-5 border-2 border-t-transparent border-amber-500"></div>
          <p className={`text-sm ${isDarkMode ? 'text-gray-400' : 'text-gray-500'}`}>Finding best time slots...</p>
        </div>
      </div>
    );
  }

  if (error) {
    return (
      <div className={`p-4 rounded-xl border ${isDarkMode ? 'bg-red-500/10 border-red-500/30' : 'bg-red-50 border-red-200'}`}>
        <p className={`text-sm ${isDarkMode ? 'text-red-400' : 'text-red-600'}`}>{error}</p>
      </div>
    );
  }

  if (!recommendations || recommendations.length === 0) {
    return null;
  }

  return (
    <div className={`rounded-xl border overflow-hidden ${isDarkMode ? 'bg-gray-800 border-gray-700' : 'bg-white border-gray-200'}`}>
      {/* Header */}
      <div className={`px-4 py-3 border-b flex items-center justify-between ${isDarkMode ? 'border-gray-700' : 'border-gray-200'}`}>
        <div className="flex items-center gap-2">
          <ClockIcon className="h-5 w-5 text-amber-500" />
          <h3 className={`text-sm font-bold ${isDarkMode ? 'text-amber-50' : 'text-amber-900'}`}>
            Recommended Time Slots
          </h3>
        </div>
        {summary && (
          <span className={`text-xs ${isDarkMode ? 'text-gray-400' : 'text-gray-500'}`}>
            {summary.available_slots}/{summary.total_slots} available
          </span>
        )}
      </div>

      {/* Day Summary */}
      {summary && (
        <div className={`px-4 py-2.5 border-b flex items-center gap-4 text-xs ${isDarkMode ? 'bg-gray-800/50 border-gray-700 text-gray-400' : 'bg-gray-50/50 border-gray-200 text-gray-500'}`}>
          <span>Busiest: <strong className={isDarkMode ? 'text-gray-200' : 'text-gray-700'}>{summary.busiest_period}</strong></span>
          <span className={isDarkMode ? 'text-gray-600' : 'text-gray-300'}>|</span>
          <span>Quietest: <strong className={isDarkMode ? 'text-gray-200' : 'text-gray-700'}>{summary.quietest_period}</strong></span>
        </div>
      )}

      {/* Time Slots Grid */}
      <div className="p-4">
        <div className="grid grid-cols-2 gap-2.5">
          {recommendations.map((slot, index) => {
            const isSelected = selectedSlot === slot.time;
            const displayTime = slot.display || formatTime12h(slot.time);

            return (
              <button
                key={slot.time}
                onClick={() => slot.available && handleSelectSlot(slot.time)}
                disabled={!slot.available}
                className={`p-3 rounded-lg border-2 text-left transition-all ${
                  isSelected
                    ? isDarkMode
                      ? 'bg-amber-500/15 border-amber-500 ring-1 ring-amber-500/30'
                      : 'bg-amber-50 border-amber-500 ring-1 ring-amber-200'
                    : !slot.available
                    ? isDarkMode
                      ? 'bg-gray-800/50 border-gray-700 opacity-40 cursor-not-allowed'
                      : 'bg-gray-50 border-gray-200 opacity-40 cursor-not-allowed'
                    : isDarkMode
                    ? 'bg-gray-700/50 border-gray-600 hover:border-amber-500/50 cursor-pointer'
                    : 'bg-white border-gray-200 hover:border-amber-400 cursor-pointer'
                }`}
              >
                {/* Time & Tag */}
                <div className="flex items-center justify-between mb-1.5">
                  <span className={`text-sm font-bold ${isDarkMode ? 'text-gray-100' : 'text-gray-900'}`}>
                    {displayTime}
                  </span>
                  {getTagBadge(slot.tag)}
                </div>

                {/* Staff & Demand */}
                <div className="flex items-center justify-between mb-2">
                  {slot.available ? (
                    <span className={`flex items-center gap-1 text-xs ${isDarkMode ? 'text-gray-400' : 'text-gray-500'}`}>
                      <UserGroupIcon className="h-3.5 w-3.5" />
                      {slot.available_staff} staff
                    </span>
                  ) : (
                    <span className={`flex items-center gap-1 text-xs ${isDarkMode ? 'text-red-400' : 'text-red-500'}`}>
                      <ExclamationTriangleIcon className="h-3.5 w-3.5" />
                      Fully booked
                    </span>
                  )}
                  {slot.available && slot.demand_level && getDemandBadge(slot.demand_level)}
                </div>

                {/* Score Bar */}
                {slot.available && slot.score !== undefined && (
                  <div className={`w-full h-1 rounded-full overflow-hidden ${isDarkMode ? 'bg-gray-600' : 'bg-gray-200'}`}>
                    <div
                      className={`h-full rounded-full transition-all ${getScoreBarColor(slot.score)}`}
                      style={{ width: `${Math.min((slot.score / (slot.max_score || 50)) * 100, 100)}%` }}
                    />
                  </div>
                )}

                {/* Reasoning on hover/expansion for first item */}
                {index === 0 && slot.reasoning && slot.reasoning.length > 0 && slot.available && (
                  <div className="mt-2 space-y-0.5">
                    {slot.reasoning.slice(0, 2).map((r, i) => (
                      <p key={i} className={`text-xs ${isDarkMode ? 'text-gray-400' : 'text-gray-500'}`}>
                        ✓ {r}
                      </p>
                    ))}
                  </div>
                )}
              </button>
            );
          })}
        </div>
      </div>

      {/* Footer Info */}
      <div className={`px-4 py-2.5 border-t text-xs flex items-center gap-2 ${isDarkMode ? 'border-gray-700 text-gray-500' : 'border-gray-200 text-gray-400'}`}>
        <SparklesIcon className="h-3.5 w-3.5" />
        Ranked by availability, productivity, and historical data
      </div>
    </div>
  );
};

export default TimeSlotRecommendations;
