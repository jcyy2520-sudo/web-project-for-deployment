import { useState, useEffect } from 'react';
import axios from 'axios';
import {
  ClockIcon,
  CheckCircleIcon,
  ExclamationTriangleIcon,
} from '@heroicons/react/24/outline';

/**
 * TimeSlotRecommendations Component
 * Displays recommended time slots for appointments
 */
const TimeSlotRecommendations = ({ appointmentDate, isDarkMode = true, onSelectSlot }) => {
  const [recommendations, setRecommendations] = useState(null);
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
        params: {
          appointment_date: appointmentDate,
        },
      });

      setRecommendations(response.data.data);
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

  const getScoreColor = (score) => {
    if (score >= 30) return isDarkMode ? 'text-green-400' : 'text-green-600';
    if (score >= 15) return isDarkMode ? 'text-yellow-400' : 'text-yellow-600';
    return isDarkMode ? 'text-gray-400' : 'text-gray-600';
  };

  const getScoreBgColor = (score) => {
    if (score >= 30) return isDarkMode ? 'bg-green-500/20' : 'bg-green-50';
    if (score >= 15) return isDarkMode ? 'bg-yellow-500/20' : 'bg-yellow-50';
    return isDarkMode ? 'bg-gray-700' : 'bg-gray-100';
  };

  if (loading) {
    return (
      <div className={`p-4 rounded-lg border ${isDarkMode ? 'bg-gray-800 border-gray-700' : 'bg-white border-gray-200'}`}>
        <div className="flex items-center justify-center py-4">
          <div className="animate-spin rounded-full h-5 w-5 border-b-2 border-amber-500"></div>
        </div>
      </div>
    );
  }

  if (error) {
    return (
      <div className={`p-4 rounded-lg border ${isDarkMode ? 'bg-red-500/10 border-red-500/30' : 'bg-red-50 border-red-200'}`}>
        <p className={`text-sm ${isDarkMode ? 'text-red-400' : 'text-red-600'}`}>{error}</p>
      </div>
    );
  }

  if (!recommendations || recommendations.length === 0) {
    return null;
  }

  return (
    <div className={`p-4 rounded-lg border ${isDarkMode ? 'bg-gray-800 border-gray-700' : 'bg-white border-gray-200'}`}>
      {/* Header */}
      <div className="flex items-center gap-2 pb-3 border-b" style={{borderColor: isDarkMode ? '#374151' : '#e5e7eb'}}>
        <ClockIcon className="h-5 w-5 text-amber-500" />
        <h3 className={`text-sm font-semibold ${isDarkMode ? 'text-amber-50' : 'text-amber-900'}`}>
          Recommended Time Slots
        </h3>
      </div>

      {/* Time Slots Grid */}
      <div className="grid grid-cols-2 gap-2 mt-3">
        {recommendations.map((slot, index) => (
          <button
            key={slot.time}
            onClick={() => handleSelectSlot(slot.time)}
            className={`p-2 rounded-lg border transition-all ${
              selectedSlot === slot.time
                ? isDarkMode
                  ? 'bg-amber-500/20 border-amber-500'
                  : 'bg-amber-50 border-amber-500'
                : getScoreBgColor(slot.score)
            } ${!slot.available ? 'opacity-50 cursor-not-allowed' : 'cursor-pointer hover:opacity-80'}`}
            disabled={!slot.available}
          >
            <div className="flex items-center justify-between">
              <div>
                <p className={`text-sm font-bold ${isDarkMode ? 'text-gray-100' : 'text-gray-900'}`}>
                  {slot.time}
                </p>
                {index === 0 && slot.available && (
                  <p className={`text-xs ${isDarkMode ? 'text-green-400' : 'text-green-600'}`}>Best</p>
                )}
              </div>
              {slot.available ? (
                <CheckCircleIcon className={`h-4 w-4 ${getScoreColor(slot.score)}`} />
              ) : (
                <ExclamationTriangleIcon className={`h-4 w-4 ${isDarkMode ? 'text-red-400' : 'text-red-600'}`} />
              )}
            </div>
          </button>
        ))}
      </div>

      {/* Info */}
      <div className={`mt-3 text-xs ${isDarkMode ? 'text-gray-400' : 'text-gray-600'}`}>
        <p>Available staff: {recommendations[0]?.available_staff || 0} members</p>
      </div>
    </div>
  );
};

export default TimeSlotRecommendations;
