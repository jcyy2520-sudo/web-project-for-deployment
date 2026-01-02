import { useState, useEffect } from 'react';
import { ChevronDownIcon } from '@heroicons/react/24/outline';

const TimePicker = ({ value, onChange, error, disabled = false, isDarkMode = false }) => {
  const [isOpen, setIsOpen] = useState(false);
  const [selectedHour, setSelectedHour] = useState('');
  const [selectedMinute, setSelectedMinute] = useState('');
  const [selectedPeriod, setSelectedPeriod] = useState('AM');

  // Initialize from value if it exists (format: "HH:mm")
  useEffect(() => {
    if (value) {
      const [hours, minutes] = value.split(':');
      let hour = parseInt(hours);
      const min = minutes;
      
      if (hour >= 12) {
        setSelectedPeriod('PM');
        if (hour > 12) hour = hour - 12;
      } else {
        setSelectedPeriod('AM');
        if (hour === 0) hour = 12;
      }
      
      setSelectedHour(String(hour).padStart(2, '0'));
      setSelectedMinute(min);
    }
  }, [value]);

  const handleSelect = () => {
    if (disabled) return;
    if (selectedHour && selectedMinute) {
      let hours = parseInt(selectedHour);
      
      if (selectedPeriod === 'PM' && hours !== 12) {
        hours += 12;
      } else if (selectedPeriod === 'AM' && hours === 12) {
        hours = 0;
      }
      
      const timeString = `${String(hours).padStart(2, '0')}:${selectedMinute}`;
      onChange(timeString);
      setIsOpen(false);
    }
  };

  // Business hours: 8:00 - 16:30 (8 AM - 5 PM excluding 12:00-12:59 lunch)
  // Display hours in 12-hour format (exclude 12)
  const hours = [8,9,10,11,1,2,3,4].map(h => String(h).padStart(2, '0'));

  // Only allow 30-minute increments
  const minutes = ['00', '30'];

  const displayValue = value 
    ? (() => {
        const [h, m] = value.split(':');
        let hour = parseInt(h);
        const period = hour >= 12 ? 'PM' : 'AM';
        if (hour > 12) hour = hour - 12;
        if (hour === 0) hour = 12;
        return `${String(hour).padStart(2, '0')}:${m} ${period}`;
      })()
    : 'Select time...';

  return (
    <div className="relative">
      <label className={`block text-xs font-medium mb-1 ${isDarkMode ? 'text-gray-300' : 'text-gray-700'}`}>
        Preferred Time *
      </label>
      
      <button
        type="button"
        disabled={disabled}
        onClick={() => !disabled && setIsOpen(!isOpen)}
        className={`w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-500 transition-all duration-200 text-sm text-left flex justify-between items-center ${
          isDarkMode 
            ? `bg-gray-800 border-gray-600 ${disabled ? 'opacity-50 cursor-not-allowed bg-gray-700' : ''} ${!value ? 'text-gray-400' : 'text-white'}`
            : `bg-white border-gray-300 ${disabled ? 'opacity-50 cursor-not-allowed bg-gray-100' : ''} ${!value ? 'text-gray-500' : 'text-black'}`
        } ${
          error ? 'border-red-500' : isDarkMode ? 'focus:border-amber-500' : 'focus:border-amber-500'
        }`}
      >
        <span>
          {displayValue}
        </span>
        <ChevronDownIcon className={`h-4 w-4 text-amber-400 transition-transform ${isOpen ? 'rotate-180' : ''}`} />
      </button>

      {isOpen && !disabled && (
        <div className={`absolute z-50 w-full mt-1 border rounded-lg shadow-lg p-4 ${
          isDarkMode 
            ? 'bg-gray-800 border-amber-500/30 shadow-amber-500/10' 
            : 'bg-white border-gray-300 shadow-gray-300/20'
        }`}>
          <div className="grid grid-cols-3 gap-2 mb-4">
            {/* Hours */}
            <div>
              <label className={`text-xs font-medium mb-1 block ${isDarkMode ? 'text-gray-300' : 'text-gray-700'}`}>Hour</label>
              <select
                value={selectedHour}
                onChange={(e) => setSelectedHour(e.target.value)}
                className={`w-full px-2 py-1 border rounded text-xs focus:outline-none focus:ring-1 focus:ring-amber-500 appearance-none cursor-pointer ${
                  isDarkMode 
                    ? 'bg-gray-700 border-gray-600 text-white' 
                    : 'bg-white border-gray-300 text-black'
                }`}
                style={{
                  backgroundImage: `url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%23f59e0b' d='M6 9L1 4h10z'/%3E%3C/svg%3E")`,
                  backgroundRepeat: 'no-repeat',
                  backgroundPosition: 'right 0.5rem center',
                  backgroundSize: '1.5em 1.5em',
                  paddingRight: '2rem',
                  colorScheme: isDarkMode ? 'dark' : 'light'
                }}
              >
                <option value="">--</option>
                {hours.map(hour => (
                  <option key={hour} value={hour}>{hour}</option>
                ))}
              </select>
            </div>

            {/* Minutes */}
            <div>
              <label className={`text-xs font-medium mb-1 block ${isDarkMode ? 'text-gray-300' : 'text-gray-700'}`}>Minute</label>
              <select
                value={selectedMinute}
                onChange={(e) => setSelectedMinute(e.target.value)}
                className={`w-full px-2 py-1 border rounded text-xs focus:outline-none focus:ring-1 focus:ring-amber-500 appearance-none cursor-pointer ${
                  isDarkMode 
                    ? 'bg-gray-700 border-gray-600 text-white' 
                    : 'bg-white border-gray-300 text-black'
                }`}
                style={{
                  backgroundImage: `url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%23f59e0b' d='M6 9L1 4h10z'/%3E%3C/svg%3E")`,
                  backgroundRepeat: 'no-repeat',
                  backgroundPosition: 'right 0.5rem center',
                  backgroundSize: '1.5em 1.5em',
                  paddingRight: '2rem',
                  colorScheme: isDarkMode ? 'dark' : 'light'
                }}
              >
                <option value="">--</option>
                {minutes.map(minute => (
                  <option key={minute} value={minute}>{minute}</option>
                ))}
              </select>
            </div>

            {/* Period */}
            <div>
              <label className={`text-xs font-medium mb-1 block ${isDarkMode ? 'text-gray-300' : 'text-gray-700'}`}>Period</label>
              <select
                value={selectedPeriod}
                onChange={(e) => setSelectedPeriod(e.target.value)}
                className={`w-full px-2 py-1 border rounded text-xs focus:outline-none focus:ring-1 focus:ring-amber-500 appearance-none cursor-pointer ${
                  isDarkMode 
                    ? 'bg-gray-700 border-gray-600 text-white' 
                    : 'bg-white border-gray-300 text-black'
                }`}
                style={{
                  backgroundImage: `url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%23f59e0b' d='M6 9L1 4h10z'/%3E%3C/svg%3E")`,
                  backgroundRepeat: 'no-repeat',
                  backgroundPosition: 'right 0.5rem center',
                  backgroundSize: '1.5em 1.5em',
                  paddingRight: '2rem',
                  colorScheme: isDarkMode ? 'dark' : 'light'
                }}
              >
                <option value="AM">AM</option>
                <option value="PM">PM</option>
              </select>
            </div>
          </div>

          <div className="flex gap-2">
            <button
              type="button"
              onClick={() => setIsOpen(false)}
              className={`flex-1 px-3 py-1 border rounded transition-colors text-xs font-medium ${
                isDarkMode 
                  ? 'border-gray-600 text-gray-300 hover:bg-gray-700' 
                  : 'border-gray-300 text-gray-700 hover:bg-gray-100'
              }`}
            >
              Cancel
            </button>
            <button
              type="button"
              onClick={handleSelect}
              className="flex-1 px-3 py-1 bg-gradient-to-r from-amber-600 to-amber-700 text-white rounded hover:from-amber-700 hover:to-amber-800 transition-all text-xs font-medium"
            >
              Confirm
            </button>
          </div>
        </div>
      )}

      {error && (
        <p className="text-red-400 text-xs mt-1 flex items-center">
          <span>⚠️</span>
          <span className="ml-1">{error}</span>
        </p>
      )}
    </div>
  );
};

export default TimePicker;
