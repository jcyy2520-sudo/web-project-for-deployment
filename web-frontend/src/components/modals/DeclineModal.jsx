import React, { useState, useEffect } from 'react';
import { XMarkIcon, ExclamationTriangleIcon } from '@heroicons/react/24/outline';

const DeclineModal = ({ isOpen, onClose, appointment, onConfirm, loading, isDarkMode = true }) => {
  const [reason, setReason] = useState('');

  useEffect(() => {
    if (!isOpen) {
      setReason('');
    }
  }, [isOpen]);

  const handleSubmit = (e) => {
    e.preventDefault();
    onConfirm(reason);
  };

  if (!isOpen || !appointment) return null;

  return (
    <div className="fixed inset-0 bg-black bg-opacity-70 flex items-center justify-center z-50 p-4 animate-fadeIn">
      <div className={`${isDarkMode ? 'bg-gray-900 border-red-500/30' : 'bg-white border-red-300/40'} border rounded-lg shadow-xl w-full max-w-md max-h-[85vh] overflow-hidden flex flex-col transform animate-scaleIn`}>
        {/* Header */}
        <div className={`flex justify-between items-center p-4 border-b ${isDarkMode ? 'border-gray-700 bg-gray-900' : 'border-gray-200 bg-white'} flex-shrink-0`}>
          <div className="flex items-center">
            <ExclamationTriangleIcon className="h-5 w-5 text-red-500 mr-2" />
            <h3 className={`text-sm font-semibold ${isDarkMode ? 'text-red-50' : 'text-red-900'}`}>
              Decline Appointment
            </h3>
          </div>
          <button 
            onClick={onClose} 
            className={`${isDarkMode ? 'text-gray-400 hover:text-red-400' : 'text-gray-500 hover:text-red-500'} transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-red-500 rounded p-1`}
            disabled={loading}
          >
            <XMarkIcon className="h-4 w-4" />
          </button>
        </div>
        
        {/* Content */}
        <div className="flex-1 min-h-0 overflow-y-auto p-4">
          {/* Appointment Info */}
          <div className={`${isDarkMode ? 'bg-red-500/10 border-red-500/30' : 'bg-red-50 border-red-200'} border rounded-lg p-3 mb-4`}>
            <p className={`text-xs ${isDarkMode ? 'text-red-200' : 'text-red-700'}`}>
              <strong>Client:</strong> {appointment.user?.first_name} {appointment.user?.last_name}
            </p>
            <p className={`text-xs ${isDarkMode ? 'text-red-200' : 'text-red-700'} mt-1`}>
              <strong>Date:</strong> {new Date(appointment.appointment_date).toLocaleDateString()} at {appointment.appointment_time}
            </p>
          </div>

          <form onSubmit={handleSubmit} className="flex flex-col h-full">
            <div className="flex-1 flex flex-col">
              <label className={`block text-xs font-medium ${isDarkMode ? 'text-amber-50' : 'text-amber-900'} mb-2`}>
                Decline Reason (Optional)
              </label>
              <textarea
                value={reason}
                onChange={(e) => setReason(e.target.value)}
                placeholder="Enter the reason for declining this appointment (optional)..."
                className={`flex-1 px-2 py-2 ${isDarkMode ? 'bg-gray-800 border-gray-600 text-white placeholder-gray-400' : 'bg-gray-50 border-gray-300 text-gray-900 placeholder-gray-500'} border rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500 transition-all duration-200 text-xs resize-none`}
                disabled={loading}
              />
            </div>

            <div className={`text-xs ${isDarkMode ? 'text-gray-400 border-gray-700' : 'text-gray-500 border-gray-200'} py-2 border-t mt-3`}>
              <p>✓ The reason will be included in the decline notification email and message</p>
            </div>

            {/* Actions */}
            <div className={`flex justify-end space-x-2 pt-3 border-t ${isDarkMode ? 'border-gray-700' : 'border-gray-200'} flex-shrink-0`}>
              <button
                type="button"
                onClick={onClose}
                className={`px-3 py-1.5 border ${isDarkMode ? 'border-gray-600 text-gray-300 hover:bg-gray-800' : 'border-gray-300 text-gray-600 hover:bg-gray-100'} rounded-lg transition-all duration-200 font-medium text-xs focus:outline-none focus:ring-2 focus:ring-red-500 disabled:opacity-50`}
                disabled={loading}
              >
                Cancel
              </button>
              <button
                type="submit"
                className="px-3 py-1.5 bg-gradient-to-r from-red-600 to-red-700 text-white rounded-lg hover:from-red-700 hover:to-red-800 transition-all duration-200 font-medium text-xs focus:outline-none focus:ring-2 focus:ring-red-500 shadow disabled:opacity-50 disabled:cursor-not-allowed flex items-center"
                disabled={loading}
              >
                {loading ? (
                  <>
                    <div className="w-3 h-3 border-2 border-white border-t-transparent rounded-full animate-spin mr-2"></div>
                    Declining...
                  </>
                ) : (
                  'Decline Appointment'
                )}
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  );
};

export default DeclineModal;
