import React, { useState } from 'react';
import { XMarkIcon } from '@heroicons/react/24/outline';
import { CheckCircleIcon } from '@heroicons/react/24/solid';

const CompletionModal = ({ isOpen, onClose, appointment, onConfirm, loading, isDarkMode = true }) => {
  const [completionNotes, setCompletionNotes] = useState('');
  const [error, setError] = useState('');

  if (!isOpen || !appointment) return null;

  const handleConfirm = () => {
    if (completionNotes.length > 1000) {
      setError('Notes cannot exceed 1000 characters');
      return;
    }
    setError('');
    onConfirm(completionNotes);
    setCompletionNotes('');
  };

  const handleClose = () => {
    setCompletionNotes('');
    setError('');
    onClose();
  };

  const formattedDate = new Date(appointment.appointment_date).toLocaleDateString('en-US', {
    weekday: 'long',
    year: 'numeric',
    month: 'long',
    day: 'numeric',
  });

  const formattedTime = new Date(`${appointment.appointment_date}T${appointment.appointment_time}`).toLocaleTimeString('en-US', {
    hour: '2-digit',
    minute: '2-digit',
  });

  return (
    <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
      <div className={`${isDarkMode ? 'bg-gray-900 border-gray-700' : 'bg-white border-gray-200'} border rounded-lg shadow-2xl max-w-md w-full max-h-[90vh] overflow-hidden flex flex-col`}>
        {/* Header */}
        <div className={`${isDarkMode ? 'bg-gradient-to-r from-blue-900 to-blue-800' : 'bg-gradient-to-r from-blue-600 to-blue-500'} px-6 py-4 flex items-center justify-between`}>
          <div className="flex items-center">
            <CheckCircleIcon className={`h-5 w-5 ${isDarkMode ? 'text-blue-300' : 'text-blue-100'} mr-2`} />
            <h3 className={`text-sm font-semibold ${isDarkMode ? 'text-blue-50' : 'text-white'}`}>
              Mark as Completed
            </h3>
          </div>
          <button 
            onClick={handleClose} 
            className={`${isDarkMode ? 'text-gray-400 hover:text-blue-300' : 'text-blue-200 hover:text-white'} transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-blue-500 rounded p-1`}
            disabled={loading}
          >
            <XMarkIcon className="h-4 w-4" />
          </button>
        </div>
        
        {/* Content */}
        <div className="flex-1 min-h-0 overflow-y-auto p-4">
          {/* Appointment Info */}
          <div className={`${isDarkMode ? 'bg-blue-500/10 border-blue-500/30' : 'bg-blue-50 border-blue-200'} border rounded-lg p-3 mb-4`}>
            <p className={`text-xs ${isDarkMode ? 'text-blue-200' : 'text-blue-700'} mb-2`}>
              <strong>Client:</strong> {appointment.user?.first_name} {appointment.user?.last_name}
            </p>
            <p className={`text-xs ${isDarkMode ? 'text-blue-200' : 'text-blue-700'} mb-2`}>
              <strong>Email:</strong> {appointment.user?.email}
            </p>
            <p className={`text-xs ${isDarkMode ? 'text-blue-200' : 'text-blue-700'} mb-2`}>
              <strong>Service:</strong> {appointment.service_type || appointment.type}
            </p>
            <p className={`text-xs ${isDarkMode ? 'text-blue-200' : 'text-blue-700'} mb-2`}>
              <strong>Date:</strong> {formattedDate}
            </p>
            <p className={`text-xs ${isDarkMode ? 'text-blue-200' : 'text-blue-700'}`}>
              <strong>Time:</strong> {formattedTime}
            </p>
          </div>

          {/* Notes Section */}
          <div className="mb-4">
            <label className={`block text-xs font-semibold ${isDarkMode ? 'text-gray-300' : 'text-gray-700'} mb-2`}>
              Completion Notes <span className={`${isDarkMode ? 'text-gray-500' : 'text-gray-400'} font-normal`}>(Optional)</span>
            </label>
            <textarea
              value={completionNotes}
              onChange={(e) => {
                setCompletionNotes(e.target.value);
                setError('');
              }}
              maxLength={1000}
              placeholder="Add any notes about the appointment completion..."
              className={`w-full px-3 py-2 ${isDarkMode ? 'bg-gray-800 border-gray-700 text-gray-100 placeholder-gray-500 focus:border-blue-500' : 'bg-gray-50 border-gray-300 text-gray-900 placeholder-gray-400 focus:border-blue-500'} border rounded-lg focus:ring-1 focus:ring-blue-500 outline-none text-sm resize-none`}
              rows={4}
              disabled={loading}
            />
            <div className="flex items-center justify-between mt-1">
              <p className={`text-xs ${isDarkMode ? 'text-gray-500' : 'text-gray-400'}`}>
                {completionNotes.length}/1000 characters
              </p>
              {error && <p className="text-xs text-red-400">{error}</p>}
            </div>
          </div>

          {/* Info Message */}
          <div className="bg-blue-900/30 border border-blue-700/50 rounded-lg p-3 text-xs text-blue-200">
            <p className="font-semibold mb-1">What happens next:</p>
            <ul className="list-disc list-inside space-y-1 text-gray-300">
              <li>Email notification sent to client</li>
              <li>In-app message created</li>
              <li>Appointment status updated to "Completed"</li>
              <li>Action log entry recorded</li>
            </ul>
          </div>
        </div>

        {/* Footer */}
        <div className="bg-gray-800 px-6 py-3 flex gap-3 justify-end border-t border-gray-700">
          <button
            onClick={handleClose}
            disabled={loading}
            className="px-4 py-2 rounded-lg text-sm font-medium bg-gray-700 text-gray-100 hover:bg-gray-600 transition-colors duration-200 disabled:opacity-50 disabled:cursor-not-allowed"
          >
            Cancel
          </button>
          <button
            onClick={handleConfirm}
            disabled={loading}
            className="px-4 py-2 rounded-lg text-sm font-medium bg-blue-600 text-white hover:bg-blue-500 transition-colors duration-200 disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2"
          >
            {loading ? (
              <>
                <span className="animate-spin inline-block w-4 h-4 border-2 border-current border-t-transparent rounded-full"></span>
                Completing...
              </>
            ) : (
              <>
                <CheckCircleIcon className="h-4 w-4" />
                Mark Complete
              </>
            )}
          </button>
        </div>
      </div>
    </div>
  );
};

export default CompletionModal;
