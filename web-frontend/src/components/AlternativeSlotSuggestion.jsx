import { useState, useEffect, useCallback } from 'react';
import axios from 'axios';
import { useApi } from '../hooks/useApi';
import {
  SparklesIcon,
  ClockIcon,
  CalendarIcon,
  CheckCircleIcon,
  ExclamationTriangleIcon,
  XMarkIcon,
  ArrowRightIcon,
  LightBulbIcon,
  ChevronDownIcon,
  ChevronUpIcon,
} from '@heroicons/react/24/outline';

/**
 * AlternativeSlotSuggestion Component
 * 
 * AI-powered decision support that activates when a user selects an unavailable time slot.
 * Shows:
 *  1. AI top recommendation with one-click booking
 *  2. Same-day closest available time slots
 *  3. Nearby date alternatives
 */
const AlternativeSlotSuggestion = ({
  preferredDate,
  preferredTime,
  isVisible = false,
  isDarkMode = false,
  onSelectSlot,
  onDismiss,
}) => {
  const { callApi } = useApi();
  const [loading, setLoading] = useState(false);
  const [data, setData] = useState(null);
  const [error, setError] = useState(null);
  const [showNearbyDates, setShowNearbyDates] = useState(false);
  const [dismissed, setDismissed] = useState(false);

  const fetchSuggestions = useCallback(async () => {
    if (!preferredDate || !preferredTime) return;

    setLoading(true);
    setError(null);

    try {
      const result = await callApi(() =>
        axios.post('/api/appointments/suggest-alternative-slots', {
          preferred_date: preferredDate,
          preferred_time: preferredTime,
          days_ahead: 7,
        })
      );

      if (result.success && result.data) {
        setData(result.data);
        setDismissed(false);
      } else {
        setError('Could not load suggestions');
      }
    } catch (err) {
      console.error('Failed to fetch alternative slot suggestions:', err);
      setError('Could not load suggestions');
    } finally {
      setLoading(false);
    }
  }, [preferredDate, preferredTime, callApi]);

  useEffect(() => {
    if (isVisible && preferredDate && preferredTime) {
      fetchSuggestions();
    }
  }, [isVisible, preferredDate, preferredTime, fetchSuggestions]);

  // Reset dismissed state when inputs change
  useEffect(() => {
    setDismissed(false);
  }, [preferredDate, preferredTime]);

  if (!isVisible || dismissed) return null;

  const handleSelectSlot = (date, time) => {
    onSelectSlot?.(date, time);
    setDismissed(true);
  };

  const handleDismiss = () => {
    setDismissed(true);
    onDismiss?.();
  };

  const formatTime12h = (time24) => {
    if (!time24) return '';
    try {
      const [h, m] = time24.split(':').map(Number);
      const ampm = h >= 12 ? 'PM' : 'AM';
      const hour12 = h % 12 || 12;
      return `${hour12}:${String(m).padStart(2, '0')} ${ampm}`;
    } catch {
      return time24;
    }
  };

  const getScoreBadge = (score) => {
    if (score >= 85) return { text: 'Best Match', color: 'bg-green-100 text-green-700 border-green-200', dotColor: 'bg-green-500' };
    if (score >= 70) return { text: 'Great Option', color: 'bg-blue-100 text-blue-700 border-blue-200', dotColor: 'bg-blue-500' };
    if (score >= 50) return { text: 'Good Option', color: 'bg-amber-100 text-amber-700 border-amber-200', dotColor: 'bg-amber-500' };
    return { text: 'Available', color: 'bg-gray-100 text-gray-600 border-gray-200', dotColor: 'bg-gray-400' };
  };

  const getDistanceLabel = (minutes) => {
    if (minutes <= 30) return `${minutes}min away`;
    if (minutes <= 60) return `${minutes}min away`;
    const hrs = Math.floor(minutes / 60);
    const mins = minutes % 60;
    return mins > 0 ? `${hrs}h ${mins}m away` : `${hrs}h away`;
  };

  // Loading state
  if (loading) {
    return (
      <div className={`rounded-xl border-2 p-5 animate-pulse ${
        isDarkMode
          ? 'bg-gray-800/80 border-amber-500/30'
          : 'bg-gradient-to-br from-amber-50 to-orange-50 border-amber-300'
      }`}>
        <div className="flex items-center gap-3">
          <div className={`animate-spin rounded-full h-5 w-5 border-2 border-t-transparent ${
            isDarkMode ? 'border-amber-400' : 'border-amber-500'
          }`} />
          <span className={`text-sm font-medium ${isDarkMode ? 'text-amber-300' : 'text-amber-700'}`}>
            Finding the best available times for you...
          </span>
        </div>
      </div>
    );
  }

  // Error state
  if (error) {
    return (
      <div className={`rounded-xl border-2 p-4 ${
        isDarkMode ? 'bg-red-900/30 border-red-500/30' : 'bg-red-50 border-red-200'
      }`}>
        <div className="flex items-center gap-2">
          <ExclamationTriangleIcon className="h-5 w-5 text-red-500" />
          <span className={`text-sm ${isDarkMode ? 'text-red-300' : 'text-red-700'}`}>{error}</span>
        </div>
      </div>
    );
  }

  if (!data) return null;

  const { same_day_slots, nearby_dates, ai_recommendation } = data;
  const hasSameDayOptions = same_day_slots && same_day_slots.length > 0;
  const hasNearbyDates = nearby_dates && nearby_dates.length > 0;

  if (!hasSameDayOptions && !hasNearbyDates) {
    return (
      <div className={`rounded-xl border-2 p-5 ${
        isDarkMode ? 'bg-gray-800 border-gray-600' : 'bg-gray-50 border-gray-200'
      }`}>
        <div className="flex items-start gap-3">
          <ExclamationTriangleIcon className={`h-5 w-5 mt-0.5 ${isDarkMode ? 'text-gray-400' : 'text-gray-500'}`} />
          <div>
            <p className={`text-sm font-medium ${isDarkMode ? 'text-gray-200' : 'text-gray-800'}`}>
              No nearby available slots found
            </p>
            <p className={`text-xs mt-1 ${isDarkMode ? 'text-gray-400' : 'text-gray-600'}`}>
              Please try selecting a different date or contact our office for assistance.
            </p>
          </div>
        </div>
      </div>
    );
  }

  return (
    <div className={`rounded-xl border-2 overflow-hidden transition-all duration-300 ${
      isDarkMode
        ? 'bg-gray-800/90 border-amber-500/40 shadow-lg shadow-amber-500/5'
        : 'bg-gradient-to-br from-white via-amber-50/30 to-orange-50/40 border-amber-300 shadow-lg shadow-amber-100/50'
    }`}>
      {/* Header */}
      <div className={`px-5 py-3.5 flex items-center justify-between ${
        isDarkMode
          ? 'bg-gradient-to-r from-amber-900/40 to-orange-900/30 border-b border-amber-500/20'
          : 'bg-gradient-to-r from-amber-100/80 to-orange-100/60 border-b border-amber-200'
      }`}>
        <div className="flex items-center gap-2.5">
          <div className={`p-1.5 rounded-lg ${isDarkMode ? 'bg-amber-500/20' : 'bg-amber-200/60'}`}>
            <SparklesIcon className={`h-4.5 w-4.5 ${isDarkMode ? 'text-amber-400' : 'text-amber-600'}`} />
          </div>
          <div>
            <h3 className={`text-sm font-bold ${isDarkMode ? 'text-amber-50' : 'text-amber-900'}`}>
              AI Slot Recommendations
            </h3>
            <p className={`text-xs ${isDarkMode ? 'text-amber-300/70' : 'text-amber-700/70'}`}>
              {formatTime12h(preferredTime)} is unavailable — here are the best alternatives
            </p>
          </div>
        </div>
        <button
          onClick={handleDismiss}
          className={`p-1.5 rounded-lg transition-colors ${
            isDarkMode
              ? 'hover:bg-gray-700 text-gray-400 hover:text-gray-200'
              : 'hover:bg-amber-200/50 text-amber-600 hover:text-amber-800'
          }`}
          title="Dismiss suggestions"
        >
          <XMarkIcon className="h-4 w-4" />
        </button>
      </div>

      <div className="p-5 space-y-4">
        {/* AI Top Recommendation */}
        {ai_recommendation && (
          <div className={`rounded-lg p-4 border-2 transition-all ${
            isDarkMode
              ? 'bg-gradient-to-r from-green-900/30 to-emerald-900/20 border-green-500/40'
              : 'bg-gradient-to-r from-green-50 to-emerald-50 border-green-300'
          }`}>
            <div className="flex items-start gap-3">
              <div className={`p-2 rounded-full flex-shrink-0 ${
                isDarkMode ? 'bg-green-500/20' : 'bg-green-100'
              }`}>
                <LightBulbIcon className={`h-5 w-5 ${isDarkMode ? 'text-green-400' : 'text-green-600'}`} />
              </div>
              <div className="flex-1 min-w-0">
                <div className="flex items-center gap-2 mb-1">
                  <span className={`text-xs font-bold uppercase tracking-wide ${
                    isDarkMode ? 'text-green-400' : 'text-green-700'
                  }`}>
                    Top Recommendation
                  </span>
                  <span className="inline-flex items-center gap-1 text-xs font-semibold px-2 py-0.5 rounded-full bg-green-100 text-green-700 border border-green-200">
                    <span className="w-1.5 h-1.5 rounded-full bg-green-500 animate-pulse" />
                    Score: {ai_recommendation.score}/100
                  </span>
                </div>
                <p className={`text-sm mb-3 ${isDarkMode ? 'text-gray-200' : 'text-gray-700'}`}>
                  {ai_recommendation.message}
                </p>
                <button
                  onClick={() => handleSelectSlot(ai_recommendation.date, ai_recommendation.time)}
                  className="inline-flex items-center gap-2 px-4 py-2 text-sm font-semibold rounded-lg transition-all
                    bg-green-600 hover:bg-green-700 text-white shadow-md hover:shadow-lg active:scale-[0.98]"
                >
                  <CheckCircleIcon className="h-4 w-4" />
                  Book {formatTime12h(ai_recommendation.time)}
                  {ai_recommendation.type === 'nearby_date' && (
                    <span className="text-xs opacity-80">
                      ({new Date(ai_recommendation.date + 'T00:00:00').toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric' })})
                    </span>
                  )}
                  <ArrowRightIcon className="h-3.5 w-3.5" />
                </button>
              </div>
            </div>
          </div>
        )}

        {/* Same Day Alternatives */}
        {hasSameDayOptions && (
          <div>
            <div className="flex items-center gap-2 mb-3">
              <ClockIcon className={`h-4 w-4 ${isDarkMode ? 'text-amber-400' : 'text-amber-600'}`} />
              <h4 className={`text-sm font-semibold ${isDarkMode ? 'text-gray-200' : 'text-gray-800'}`}>
                Same Day — Closest Available
              </h4>
              <span className={`text-xs px-2 py-0.5 rounded-full ${
                isDarkMode ? 'bg-gray-700 text-gray-300' : 'bg-gray-100 text-gray-600'
              }`}>
                {same_day_slots.length} option{same_day_slots.length !== 1 ? 's' : ''}
              </span>
            </div>

            <div className="grid grid-cols-2 sm:grid-cols-3 gap-2">
              {same_day_slots.map((slot, idx) => {
                const badge = getScoreBadge(slot.score);
                const isTopPick = idx === 0 || slot.is_closest;

                return (
                  <button
                    key={slot.time}
                    onClick={() => handleSelectSlot(preferredDate, slot.time)}
                    className={`group relative p-3 rounded-lg border-2 text-left transition-all hover:scale-[1.02] active:scale-[0.98] ${
                      isTopPick
                        ? isDarkMode
                          ? 'bg-amber-900/30 border-amber-500/50 hover:border-amber-400'
                          : 'bg-amber-50 border-amber-300 hover:border-amber-500 ring-1 ring-amber-200'
                        : isDarkMode
                        ? 'bg-gray-700/50 border-gray-600 hover:border-amber-500/50'
                        : 'bg-white border-gray-200 hover:border-amber-400'
                    }`}
                  >
                    {/* Top pick indicator */}
                    {isTopPick && (
                      <div className="absolute -top-2 -right-2">
                        <span className="flex h-5 w-5 items-center justify-center rounded-full bg-amber-500 text-white text-[10px] font-bold shadow-md">
                          ★
                        </span>
                      </div>
                    )}

                    <div className="flex items-center justify-between mb-1.5">
                      <span className={`text-base font-bold ${
                        isDarkMode ? 'text-gray-100' : 'text-gray-900'
                      }`}>
                        {formatTime12h(slot.time)}
                      </span>
                    </div>

                    {/* Score badge */}
                    <div className={`inline-flex items-center gap-1 text-[10px] font-semibold px-1.5 py-0.5 rounded border ${badge.color}`}>
                      <span className={`w-1.5 h-1.5 rounded-full ${badge.dotColor}`} />
                      {badge.text}
                    </div>

                    {/* Distance info */}
                    <div className={`mt-1.5 text-xs ${isDarkMode ? 'text-gray-400' : 'text-gray-500'}`}>
                      {slot.distance_minutes === 0 ? (
                        <span className="text-green-600 font-medium">Exact match!</span>
                      ) : (
                        <span>{getDistanceLabel(slot.distance_minutes)}</span>
                      )}
                      <span className="mx-1">·</span>
                      <span>{slot.capacity_remaining} spot{slot.capacity_remaining !== 1 ? 's' : ''}</span>
                    </div>

                    {/* Hover effect arrow */}
                    <div className={`absolute right-2 top-1/2 -translate-y-1/2 opacity-0 group-hover:opacity-100 transition-opacity ${
                      isDarkMode ? 'text-amber-400' : 'text-amber-600'
                    }`}>
                      <ArrowRightIcon className="h-4 w-4" />
                    </div>
                  </button>
                );
              })}
            </div>
          </div>
        )}

        {/* Nearby Dates Expandable Section */}
        {hasNearbyDates && (
          <div>
            <button
              onClick={() => setShowNearbyDates(!showNearbyDates)}
              className={`w-full flex items-center justify-between p-3 rounded-lg border transition-colors ${
                isDarkMode
                  ? 'bg-gray-700/50 border-gray-600 hover:bg-gray-700 text-gray-200'
                  : 'bg-gray-50 border-gray-200 hover:bg-gray-100 text-gray-700'
              }`}
            >
              <div className="flex items-center gap-2">
                <CalendarIcon className={`h-4 w-4 ${isDarkMode ? 'text-blue-400' : 'text-blue-600'}`} />
                <span className="text-sm font-semibold">
                  {hasSameDayOptions ? 'Or try a different day' : 'Available on other days'}
                </span>
                <span className={`text-xs px-2 py-0.5 rounded-full ${
                  isDarkMode ? 'bg-blue-500/20 text-blue-300' : 'bg-blue-100 text-blue-700'
                }`}>
                  {nearby_dates.length} date{nearby_dates.length !== 1 ? 's' : ''}
                </span>
              </div>
              {showNearbyDates ? (
                <ChevronUpIcon className="h-4 w-4" />
              ) : (
                <ChevronDownIcon className="h-4 w-4" />
              )}
            </button>

            {showNearbyDates && (
              <div className="mt-2 space-y-2">
                {nearby_dates.map((nd, idx) => (
                  <div
                    key={nd.date}
                    className={`rounded-lg border p-3.5 ${
                      isDarkMode
                        ? 'bg-gray-700/30 border-gray-600'
                        : 'bg-white border-gray-200'
                    }`}
                  >
                    <div className="flex items-center justify-between mb-2.5">
                      <div className="flex items-center gap-2">
                        <CalendarIcon className={`h-4 w-4 ${isDarkMode ? 'text-blue-400' : 'text-blue-600'}`} />
                        <span className={`text-sm font-semibold ${isDarkMode ? 'text-gray-100' : 'text-gray-900'}`}>
                          {nd.day_name}, {new Date(nd.date + 'T00:00:00').toLocaleDateString('en-US', { month: 'short', day: 'numeric' })}
                        </span>
                      </div>
                      <span className={`text-xs font-medium px-2 py-0.5 rounded-full ${
                        nd.total_available_slots > 5
                          ? isDarkMode ? 'bg-green-500/20 text-green-300' : 'bg-green-100 text-green-700'
                          : isDarkMode ? 'bg-amber-500/20 text-amber-300' : 'bg-amber-100 text-amber-700'
                      }`}>
                        {nd.total_available_slots} slots
                      </span>
                    </div>

                    <div className="flex flex-wrap gap-1.5">
                      {nd.available_times.map((t) => (
                        <button
                          key={t.time}
                          onClick={() => handleSelectSlot(nd.date, t.time)}
                          className={`px-2.5 py-1.5 text-xs font-medium rounded-md border transition-all hover:scale-105 active:scale-95 ${
                            isDarkMode
                              ? 'bg-gray-600 border-gray-500 text-gray-200 hover:border-amber-400 hover:bg-gray-500'
                              : 'bg-white border-gray-300 text-gray-700 hover:border-amber-500 hover:bg-amber-50'
                          }`}
                        >
                          {formatTime12h(t.time)}
                        </button>
                      ))}
                      {nd.total_available_slots > nd.available_times.length && (
                        <span className={`px-2.5 py-1.5 text-xs ${isDarkMode ? 'text-gray-400' : 'text-gray-500'}`}>
                          +{nd.total_available_slots - nd.available_times.length} more
                        </span>
                      )}
                    </div>
                  </div>
                ))}
              </div>
            )}
          </div>
        )}

        {/* Footer */}
        <div className={`flex items-center gap-2 pt-2 border-t ${
          isDarkMode ? 'border-gray-700' : 'border-gray-100'
        }`}>
          <SparklesIcon className={`h-3.5 w-3.5 ${isDarkMode ? 'text-amber-400/60' : 'text-amber-500/60'}`} />
          <p className={`text-xs ${isDarkMode ? 'text-gray-500' : 'text-gray-400'}`}>
            Recommendations based on availability, proximity to your preference, and slot demand
          </p>
        </div>
      </div>
    </div>
  );
};

export default AlternativeSlotSuggestion;
