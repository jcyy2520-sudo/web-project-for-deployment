import { useState } from 'react';
import {
  CheckCircleIcon,
  ExclamationTriangleIcon,
  InformationCircleIcon,
  XMarkIcon,
  SparklesIcon
} from '@heroicons/react/24/outline';

/**
 * Unavailability Message Component
 * Shows friendly message when selected slot is unavailable with suggestions
 */
const UnavailabilityMessage = ({
  selectedDate,
  selectedTime,
  isUnavailable,
  reason,
  alternatives,
  isDarkMode = true,
  onSelectAlternative
}) => {
  const [dismissed, setDismissed] = useState(false);

  if (!isUnavailable || dismissed) {
    return null;
  }

  const messageTypes = {
    capacity: {
      icon: ExclamationTriangleIcon,
      title: '⚠️ This slot is fully booked',
      color: isDarkMode ? 'bg-orange-950/50 border-orange-400' : 'bg-orange-50 border-orange-200'
    },
    blackout: {
      icon: ExclamationTriangleIcon,
      title: '🚫 This date is unavailable',
      color: isDarkMode ? 'bg-red-950/50 border-red-400' : 'bg-red-50 border-red-200'
    },
    weekend: {
      icon: ExclamationTriangleIcon,
      title: '📅 We\'re closed on weekends',
      color: isDarkMode ? 'bg-gray-700 border-gray-500' : 'bg-gray-100 border-gray-300'
    },
    lunch: {
      icon: ExclamationTriangleIcon,
      title: '🍽️ This is during our lunch break',
      color: isDarkMode ? 'bg-yellow-950/50 border-yellow-400' : 'bg-yellow-50 border-yellow-200'
    },
    default: {
      icon: ExclamationTriangleIcon,
      title: '⏰ This time is not available',
      color: isDarkMode ? 'bg-red-950/50 border-red-400' : 'bg-red-50 border-red-200'
    }
  };

  const messageType = messageTypes[reason?.type] || messageTypes.default;
  const Icon = messageType.icon;

  return (
    <div className={`rounded-lg border-2 p-4 ${messageType.color}`}>
      <div className="flex items-start gap-3">
        <Icon className={`h-5 w-5 flex-shrink-0 mt-0.5 ${
          isDarkMode 
            ? reason?.type === 'capacity' ? 'text-orange-400' : 'text-red-400'
            : reason?.type === 'capacity' ? 'text-orange-600' : 'text-red-600'
        }`} />
        
        <div className="flex-1">
          <h4 className={`font-semibold mb-1 ${
            isDarkMode ? 'text-gray-100' : 'text-gray-900'
          }`}>
            {messageType.title}
          </h4>

          <p className={`text-sm mb-3 ${
            isDarkMode ? 'text-gray-300' : 'text-gray-700'
          }`}>
            {reason?.message || 'Unfortunately, your preferred date and time is not available.'}
          </p>

          {/* Suggested Alternatives */}
          {alternatives && alternatives.length > 0 && (
            <div className="mt-3 space-y-2">
              <p className={`text-xs font-semibold flex items-center gap-1 mb-2 ${
                isDarkMode ? 'text-amber-300' : 'text-amber-700'
              }`}>
                <SparklesIcon className="h-4 w-4" />
                We found {alternatives.length} available slots nearby:
              </p>

              <div className="grid gap-2">
                {alternatives.map((alt, idx) => (
                  <button
                    key={idx}
                    onClick={() => {
                      onSelectAlternative?.(alt);
                      setDismissed(true);
                    }}
                    className={`p-2.5 rounded border-2 transition-all text-left text-sm font-medium ${
                      isDarkMode
                        ? 'bg-gray-800 border-amber-400/50 text-amber-50 hover:border-amber-400 hover:bg-gray-700'
                        : 'bg-white border-amber-300 text-amber-900 hover:border-amber-500 hover:bg-amber-50'
                    }`}
                  >
                    <div className="flex items-center justify-between">
                      <span>
                        {new Date(alt.date).toLocaleDateString('en-US', {
                          weekday: 'short',
                          month: 'short',
                          day: 'numeric'
                        })} {alt.time ? `at ${alt.time}` : ''}
                      </span>
                      <span className={`text-xs px-2 py-0.5 rounded font-semibold ${
                        isDarkMode
                          ? 'bg-green-500/20 text-green-300'
                          : 'bg-green-100 text-green-700'
                      }`}>
                        {alt.available_capacity ?? alt.available_slots ?? alt.availability ?? alt.capacity ?? 'N/A'} slots
                      </span>
                    </div>
                  </button>
                ))}
              </div>

              <p className={`text-xs italic mt-2 ${
                isDarkMode ? 'text-gray-400' : 'text-gray-600'
              }`}>
                Click on a time to select it for your appointment
              </p>
            </div>
          )}

          {/* No Alternatives Found */}
          {(!alternatives || alternatives.length === 0) && (
            <div className={`p-2 rounded text-sm mt-2 ${
              isDarkMode
                ? 'bg-gray-700/50 text-gray-300'
                : 'bg-gray-100 text-gray-700'
            }`}>
              <p>📞 Please call us at your earliest convenience to book an appointment.</p>
            </div>
          )}
        </div>

        <button
          onClick={() => setDismissed(true)}
          className={`flex-shrink-0 p-1 rounded hover:bg-black/10 ${
            isDarkMode ? 'text-gray-400 hover:text-gray-300' : 'text-gray-600 hover:text-gray-900'
          }`}
        >
          <XMarkIcon className="h-4 w-4" />
        </button>
      </div>
    </div>
  );
};

export default UnavailabilityMessage;
