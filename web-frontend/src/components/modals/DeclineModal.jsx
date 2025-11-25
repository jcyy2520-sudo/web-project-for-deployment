import React, { useState, useEffect } from 'react';
import { XMarkIcon, ExclamationTriangleIcon } from '@heroicons/react/24/outline';

const DeclineModal = ({ isOpen, onClose, appointment, onConfirm, loading }) => {
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
      <div className="bg-gray-900 border border-red-500/30 rounded-lg shadow-xl w-full max-w-md max-h-[85vh] overflow-hidden flex flex-col transform animate-scaleIn">
        {/* Header */}
        <div className="flex justify-between items-center p-4 border-b border-gray-700 bg-gray-900 flex-shrink-0">
          <div className="flex items-center">
            <ExclamationTriangleIcon className="h-5 w-5 text-red-500 mr-2" />
            <h3 className="text-sm font-semibold text-red-50">
              Decline Appointment
            </h3>
          </div>
          <button 
            onClick={onClose} 
            className="text-gray-400 hover:text-red-400 transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-red-500 rounded p-1"
            disabled={loading}
          >
            <XMarkIcon className="h-4 w-4" />
          </button>
        </div>
        
        {/* Content */}
        <div className="flex-1 min-h-0 overflow-y-auto p-4">
          {/* Appointment Info */}
          <div className="bg-red-500/10 border border-red-500/30 rounded-lg p-3 mb-4">
            <p className="text-xs text-red-200">
              <strong>Client:</strong> {appointment.user?.first_name} {appointment.user?.last_name}
            </p>
            <p className="text-xs text-red-200 mt-1">
              <strong>Date:</strong> {new Date(appointment.appointment_date).toLocaleDateString()} at {appointment.appointment_time}
            </p>
          </div>

          <form onSubmit={handleSubmit} className="flex flex-col h-full">
            <div className="flex-1 flex flex-col">
              <label className="block text-xs font-medium text-amber-50 mb-2">
                Decline Reason (Optional)
              </label>
              <textarea
                value={reason}
                onChange={(e) => setReason(e.target.value)}
                placeholder="Enter the reason for declining this appointment (optional)..."
                className="flex-1 px-2 py-2 bg-gray-800 border border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500 transition-all duration-200 text-xs text-white placeholder-gray-400 resize-none"
                disabled={loading}
              />
            </div>

            <div className="text-xs text-gray-400 py-2 border-t border-gray-700 mt-3">
              <p>✓ The reason will be included in the decline notification email and message</p>
            </div>

            {/* Actions */}
            <div className="flex justify-end space-x-2 pt-3 border-t border-gray-700 flex-shrink-0">
              <button
                type="button"
                onClick={onClose}
                className="px-3 py-1.5 border border-gray-600 text-gray-300 rounded-lg hover:bg-gray-800 transition-all duration-200 font-medium text-xs focus:outline-none focus:ring-2 focus:ring-red-500 disabled:opacity-50"
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
