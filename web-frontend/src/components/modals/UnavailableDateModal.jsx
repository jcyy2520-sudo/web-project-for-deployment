import { useEffect, useState } from 'react';
import { XMarkIcon, CalendarDaysIcon } from '@heroicons/react/24/outline';

const UnavailableDateModal = ({ isOpen, onClose, onSave, loading, isDarkMode = true }) => {
  const [formData, setFormData] = useState({
    date: '',
    reason: '',
    all_day: true,
    start_time: '09:00',
    end_time: '17:00'
  });

  useEffect(() => {
    if (isOpen) {
      const tomorrow = new Date();
      tomorrow.setDate(tomorrow.getDate() + 1);
      setFormData({
        date: tomorrow.toISOString().split('T')[0],
        reason: '',
        all_day: true,
        start_time: '09:00',
        end_time: '17:00'
      });
    }
  }, [isOpen]);

  const handleSubmit = (e) => {
    e.preventDefault();
    onSave(formData);
  };

  if (!isOpen) return null;

  return (
    <div className="fixed inset-0 bg-black bg-opacity-70 flex items-center justify-center z-50 p-4 animate-fadeIn">
      <div className={`${isDarkMode ? 'bg-gray-900 border-amber-500/30' : 'bg-white border-amber-300/40'} border rounded-lg shadow-xl w-full max-w-md max-h-[90vh] overflow-y-auto transform animate-scaleIn`}>
        <div className={`flex justify-between items-center p-4 border-b ${isDarkMode ? 'border-gray-700 bg-gray-900' : 'border-gray-200 bg-white'} sticky top-0`}>
          <div className="flex items-center">
            <CalendarDaysIcon className="h-5 w-5 text-amber-400 mr-2" />
            <h3 className={`text-sm font-semibold ${isDarkMode ? 'text-amber-50' : 'text-amber-900'}`}>
              Add Unavailable Date
            </h3>
          </div>
          <button 
            onClick={onClose} 
            className={`${isDarkMode ? 'text-gray-400 hover:text-amber-400' : 'text-gray-500 hover:text-amber-500'} transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-amber-500 rounded p-1`}
            disabled={loading}
          >
            <XMarkIcon className="h-4 w-4" />
          </button>
        </div>
        
        <form onSubmit={handleSubmit} className="p-4 space-y-4">
          <div>
            <label className={`block text-xs font-medium ${isDarkMode ? 'text-amber-50' : 'text-amber-900'} mb-1`}>
              Date *
            </label>
            <input
              type="date"
              value={formData.date}
              onChange={(e) => setFormData(prev => ({ ...prev, date: e.target.value }))}
              className={`w-full px-3 py-2 ${isDarkMode ? 'bg-gray-800 border-gray-600 text-white' : 'bg-gray-50 border-gray-300 text-gray-900'} border rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-500 focus:border-amber-500 transition-all duration-200 text-sm`}
              disabled={loading}
              min={new Date().toISOString().split('T')[0]}
              required
            />
          </div>

          <div>
            <label className={`block text-xs font-medium ${isDarkMode ? 'text-amber-50' : 'text-amber-900'} mb-1`}>
              Reason
            </label>
            <input
              type="text"
              value={formData.reason}
              onChange={(e) => setFormData(prev => ({ ...prev, reason: e.target.value }))}
              placeholder="e.g., Public Holiday, Maintenance, Vacation"
              className={`w-full px-3 py-2 ${isDarkMode ? 'bg-gray-800 border-gray-600 text-white placeholder-gray-400' : 'bg-gray-50 border-gray-300 text-gray-900 placeholder-gray-500'} border rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-500 focus:border-amber-500 transition-all duration-200 text-sm`}
              disabled={loading}
            />
          </div>

          <div className={`flex items-center p-3 ${isDarkMode ? 'bg-gray-800/50 border-gray-600' : 'bg-gray-50 border-gray-200'} rounded-lg border`}>
            <input
              type="checkbox"
              id="all_day"
              checked={formData.all_day}
              onChange={(e) => setFormData(prev => ({ ...prev, all_day: e.target.checked }))}
              className={`w-3 h-3 text-amber-600 ${isDarkMode ? 'bg-gray-700 border-gray-600' : 'bg-white border-gray-300'} rounded focus:ring-amber-500 focus:ring-2`}
              disabled={loading}
            />
            <label htmlFor="all_day" className={`ml-2 text-xs font-medium ${isDarkMode ? 'text-amber-50' : 'text-amber-900'}`}>
              All day
            </label>
          </div>

          {!formData.all_day && (
            <div className={`grid grid-cols-2 gap-3 p-3 ${isDarkMode ? 'bg-gray-800/30 border-gray-600' : 'bg-gray-50 border-gray-200'} rounded-lg border`}>
              <div>
                <label className={`block text-xs font-medium ${isDarkMode ? 'text-amber-50' : 'text-amber-900'} mb-1`}>
                  Start Time
                </label>
                <input
                  type="time"
                  value={formData.start_time}
                  onChange={(e) => setFormData(prev => ({ ...prev, start_time: e.target.value }))}
                  className={`w-full px-2 py-1 ${isDarkMode ? 'bg-gray-800 border-gray-600 text-white' : 'bg-white border-gray-300 text-gray-900'} border rounded focus:outline-none focus:ring-2 focus:ring-amber-500 transition-colors duration-200 text-sm`}
                  disabled={loading}
                />
              </div>
              <div>
                <label className={`block text-xs font-medium ${isDarkMode ? 'text-amber-50' : 'text-amber-900'} mb-1`}>
                  End Time
                </label>
                <input
                  type="time"
                  value={formData.end_time}
                  onChange={(e) => setFormData(prev => ({ ...prev, end_time: e.target.value }))}
                  className={`w-full px-2 py-1 ${isDarkMode ? 'bg-gray-800 border-gray-600 text-white' : 'bg-white border-gray-300 text-gray-900'} border rounded focus:outline-none focus:ring-2 focus:ring-amber-500 transition-colors duration-200 text-sm`}
                  disabled={loading}
                />
              </div>
            </div>
          )}

          <div className={`flex justify-end space-x-3 pt-4 border-t ${isDarkMode ? 'border-gray-700' : 'border-gray-200'}`}>
            <button
              type="button"
              onClick={onClose}
              className={`px-4 py-2 border ${isDarkMode ? 'border-gray-600 text-gray-300 hover:bg-gray-800 focus:ring-offset-gray-900' : 'border-gray-300 text-gray-600 hover:bg-gray-100 focus:ring-offset-white'} rounded-lg transition-all duration-200 font-medium text-sm focus:outline-none focus:ring-2 focus:ring-amber-500 focus:ring-offset-2 hover:scale-105 disabled:opacity-50`}
              disabled={loading}
            >
              Cancel
            </button>
            <button
              type="submit"
              className="px-4 py-2 bg-gradient-to-r from-amber-600 to-amber-700 text-white rounded-lg hover:from-amber-700 hover:to-amber-800 transition-all duration-200 font-medium text-sm focus:outline-none focus:ring-2 focus:ring-amber-500 focus:ring-offset-2 focus:ring-offset-gray-900 shadow hover:scale-105 disabled:opacity-50 disabled:cursor-not-allowed flex items-center"
              disabled={loading}
            >
              {loading ? (
                <>
                  <div className="w-3 h-3 border-2 border-white border-t-transparent rounded-full animate-spin mr-1"></div>
                  Adding...
                </>
              ) : (
                'Add Unavailable Date'
              )}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
};

export default UnavailableDateModal;
