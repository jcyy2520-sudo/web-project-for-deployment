import { useState, useEffect } from 'react';
import { ChevronDownIcon } from '@heroicons/react/24/outline';

const TimePicker = ({ value, onChange, error }) => {
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

  const hours = Array.from({ length: 12 }, (_, i) => {
    const hour = i + 1;
    return String(hour).padStart(2, '0');
  });

  const minutes = Array.from({ length: 60 }, (_, i) => String(i).padStart(2, '0'));

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
      <label className="block text-xs font-medium text-amber-50 mb-1">
        Preferred Time *
      </label>
      
      <button
        type="button"
        onClick={() => setIsOpen(!isOpen)}
        className={`w-full px-3 py-2 bg-gray-800 border rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-500 transition-all duration-200 text-sm text-white text-left flex justify-between items-center ${
          error ? 'border-red-500' : 'border-gray-600 focus:border-amber-500'
        }`}
      >
        <span className={!value ? 'text-gray-400' : 'text-white'}>
          {displayValue}
        </span>
        <ChevronDownIcon className={`h-4 w-4 text-amber-400 transition-transform ${isOpen ? 'rotate-180' : ''}`} />
      </button>

      {isOpen && (
        <div className="absolute z-50 w-full mt-1 bg-gray-800 border border-amber-500/30 rounded-lg shadow-lg shadow-amber-500/10 p-4">
          <div className="grid grid-cols-3 gap-2 mb-4">
            {/* Hours */}
            <div>
              <label className="text-xs font-medium text-amber-50 mb-1 block">Hour</label>
              <select
                value={selectedHour}
                onChange={(e) => setSelectedHour(e.target.value)}
                className="w-full px-2 py-1 bg-gray-700 border border-gray-600 rounded text-white text-xs focus:outline-none focus:ring-1 focus:ring-amber-500"
              >
                <option value="">--</option>
                {hours.map(hour => (
                  <option key={hour} value={hour}>{hour}</option>
                ))}
              </select>
            </div>

            {/* Minutes */}
            <div>
              <label className="text-xs font-medium text-amber-50 mb-1 block">Minute</label>
              <select
                value={selectedMinute}
                onChange={(e) => setSelectedMinute(e.target.value)}
                className="w-full px-2 py-1 bg-gray-700 border border-gray-600 rounded text-white text-xs focus:outline-none focus:ring-1 focus:ring-amber-500"
              >
                <option value="">--</option>
                {minutes.map(minute => (
                  <option key={minute} value={minute}>{minute}</option>
                ))}
              </select>
            </div>

            {/* Period */}
            <div>
              <label className="text-xs font-medium text-amber-50 mb-1 block">Period</label>
              <select
                value={selectedPeriod}
                onChange={(e) => setSelectedPeriod(e.target.value)}
                className="w-full px-2 py-1 bg-gray-700 border border-gray-600 rounded text-white text-xs focus:outline-none focus:ring-1 focus:ring-amber-500"
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
              className="flex-1 px-3 py-1 border border-gray-600 text-gray-300 rounded hover:bg-gray-700 transition-colors text-xs font-medium"
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
