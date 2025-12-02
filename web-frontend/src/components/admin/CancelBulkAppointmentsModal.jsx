import React, { useState, useEffect } from 'react';
import { XMarkIcon, ExclamationTriangleIcon, EnvelopeIcon } from '@heroicons/react/24/outline';
import { formatServiceName } from '../../utils/format';

/**
 * CancelBulkAppointmentsModal
 * Allows admin to cancel multiple appointments due to unavailable date
 * with options to send cancellation messages individually or as group
 */
const CancelBulkAppointmentsModal = ({ 
  isOpen, 
  onClose, 
  affected = [], 
  unavailableDate = null,
  onConfirm, 
  loading 
}) => {
  const [cancellationReason, setCancellationReason] = useState('');
  const [messageOption, setMessageOption] = useState('individual'); // 'individual' or 'group'
  const [includeReason, setIncludeReason] = useState(true);
  const [selectedForCancel, setSelectedForCancel] = useState([]);

  useEffect(() => {
    if (isOpen) {
      // Pre-select all appointments for cancellation
      setSelectedForCancel(affected.map(apt => apt.id));
      setCancellationReason('');
      setMessageOption('individual');
      setIncludeReason(true);
    }
  }, [isOpen, affected]);

  if (!isOpen) return null;

  const toggleSelect = (id) => {
    setSelectedForCancel(prev => 
      prev.includes(id) ? prev.filter(x => x !== id) : [...prev, id]
    );
  };

  const toggleAll = () => {
    if (selectedForCancel.length === affected.length) {
      setSelectedForCancel([]);
    } else {
      setSelectedForCancel(affected.map(apt => apt.id));
    }
  };

  const handleConfirm = () => {
    if (selectedForCancel.length === 0) {
      alert('Please select at least one appointment to cancel');
      return;
    }

    if (!cancellationReason.trim()) {
      alert('Please provide a reason for cancellation');
      return;
    }

    onConfirm({
      appointmentIds: selectedForCancel,
      cancellationReason: cancellationReason.trim(),
      messageOption,
      includeReason,
      unavailableDate
    });
  };

  const selectedCount = selectedForCancel.length;

  return (
    <div className="fixed inset-0 bg-black bg-opacity-70 flex items-center justify-center z-50 p-4 animate-fadeIn">
      <div className="bg-gray-900 border border-red-500/30 rounded-lg shadow-xl w-full max-w-2xl max-h-[90vh] overflow-hidden flex flex-col transform animate-scaleIn">
        {/* Header */}
        <div className="flex justify-between items-center p-4 border-b border-gray-700 sticky top-0 bg-gray-900 flex-shrink-0">
          <div className="flex items-center">
            <ExclamationTriangleIcon className="h-5 w-5 text-red-400 mr-2" />
            <h3 className="text-sm font-semibold text-red-50">Cancel Appointments</h3>
          </div>
          <button 
            onClick={onClose} 
            className="text-gray-400 hover:text-red-400 transition-colors p-1 rounded"
            disabled={loading}
          >
            <XMarkIcon className="h-4 w-4" />
          </button>
        </div>

        {/* Content */}
        <div className="flex-1 overflow-y-auto p-4 space-y-4">
          {/* Warning Banner */}
          <div className="bg-red-500/10 border border-red-500/30 rounded-lg p-3">
            <p className="text-xs text-red-200">
              <strong>Warning:</strong> You are about to cancel <strong>{selectedCount}</strong> appointment{selectedCount !== 1 ? 's' : ''} due to the unavailable date on <strong>{unavailableDate ? new Date(unavailableDate.date).toLocaleDateString() : 'this date'}</strong>.
            </p>
          </div>

          {/* Appointments List with Selection */}
          <div>
            <div className="flex items-center justify-between mb-2">
              <label className="text-xs font-medium text-gray-300">
                Select Appointments to Cancel
              </label>
              <button
                onClick={toggleAll}
                className="text-xs text-amber-400 hover:text-amber-300 transition-colors"
              >
                {selectedForCancel.length === affected.length ? 'Deselect All' : 'Select All'}
              </button>
            </div>

            <div className="border border-gray-700 rounded-lg bg-gray-800/50 max-h-48 overflow-y-auto">
              {affected.length === 0 ? (
                <div className="text-center py-6 text-gray-400">No appointments to display</div>
              ) : (
                <div className="divide-y divide-gray-700">
                  {affected.map(apt => (
                    <div 
                      key={apt.id} 
                      className={`p-3 flex items-start gap-3 hover:bg-gray-700/50 transition-colors cursor-pointer ${
                        selectedForCancel.includes(apt.id) ? 'bg-gray-700/30' : ''
                      }`}
                      onClick={() => toggleSelect(apt.id)}
                    >
                      <input 
                        type="checkbox" 
                        checked={selectedForCancel.includes(apt.id)}
                        onChange={() => toggleSelect(apt.id)}
                        className="mt-0.5 cursor-pointer"
                      />
                      <div className="flex-1">
                        <div className="text-sm font-medium text-amber-50">
                          {apt.user?.first_name} {apt.user?.last_name}
                        </div>
                        <div className="text-xs text-gray-400 mt-1">
                          <span>{formatServiceName(apt)}</span>
                          <span className="mx-2">•</span>
                          <span>{new Date(apt.appointment_date).toLocaleDateString()} at {apt.appointment_time}</span>
                        </div>
                        {apt.notes && (
                          <div className="text-xs text-gray-500 mt-1">
                            Notes: {apt.notes.substring(0, 60)}{apt.notes.length > 60 ? '...' : ''}
                          </div>
                        )}
                      </div>
                    </div>
                  ))}
                </div>
              )}
            </div>
          </div>

          {/* Cancellation Reason */}
          <div>
            <label className="block text-xs font-medium text-gray-300 mb-2">
              Cancellation Reason <span className="text-red-400">*</span>
            </label>
            <textarea
              value={cancellationReason}
              onChange={(e) => setCancellationReason(e.target.value)}
              placeholder="e.g., Emergency unavailable date due to unexpected circumstances"
              className="w-full px-3 py-2 bg-gray-800 border border-gray-700 rounded-lg text-sm text-gray-100 placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-transparent resize-none"
              rows="3"
              disabled={loading}
            />
            <div className="text-xs text-gray-500 mt-1">
              {cancellationReason.length}/200 characters
            </div>
          </div>

          {/* Messaging Options */}
          <div className="space-y-3 bg-gray-800/30 border border-gray-700 rounded-lg p-3">
            <label className="text-xs font-medium text-gray-300">
              <EnvelopeIcon className="h-4 w-4 inline-block mr-1" />
              How to Send Notification
            </label>

            <div className="space-y-2">
              {/* Individual Messages Option */}
              <label className="flex items-start p-3 border border-gray-700 rounded-lg hover:bg-gray-700/20 transition-colors cursor-pointer">
                <input
                  type="radio"
                  value="individual"
                  checked={messageOption === 'individual'}
                  onChange={(e) => setMessageOption(e.target.value)}
                  className="mt-1 cursor-pointer"
                  disabled={loading}
                />
                <div className="ml-3 flex-1">
                  <div className="text-xs font-medium text-amber-50">
                    Individual Messages
                  </div>
                  <div className="text-xs text-gray-400 mt-1">
                    Each affected user receives a personalized cancellation notice with their appointment details
                  </div>
                </div>
              </label>

              {/* Group Message Option */}
              <label className="flex items-start p-3 border border-gray-700 rounded-lg hover:bg-gray-700/20 transition-colors cursor-pointer">
                <input
                  type="radio"
                  value="group"
                  checked={messageOption === 'group'}
                  onChange={(e) => setMessageOption(e.target.value)}
                  className="mt-1 cursor-pointer"
                  disabled={loading}
                />
                <div className="ml-3 flex-1">
                  <div className="text-xs font-medium text-amber-50">
                    Single Group Message
                  </div>
                  <div className="text-xs text-gray-400 mt-1">
                    One group message is sent to all affected users listing all cancelled appointments
                  </div>
                </div>
              </label>
            </div>

            {/* Include Reason Checkbox */}
            <label className="flex items-center mt-3 pt-3 border-t border-gray-700 cursor-pointer">
              <input
                type="checkbox"
                checked={includeReason}
                onChange={(e) => setIncludeReason(e.target.checked)}
                className="cursor-pointer"
                disabled={loading}
              />
              <span className="text-xs text-gray-300 ml-2">
                Include cancellation reason in notification
              </span>
            </label>
          </div>

          {/* Summary */}
          <div className="bg-amber-500/10 border border-amber-500/30 rounded-lg p-3">
            <p className="text-xs text-amber-200">
              <strong>Summary:</strong> {selectedCount} appointment{selectedCount !== 1 ? 's' : ''} will be cancelled and {messageOption === 'individual' ? `${selectedCount} individual notification${selectedCount !== 1 ? 's will' : ' will'}` : 'a single group notification will'} be sent {includeReason ? 'with the reason included' : 'without the reason'}.
            </p>
          </div>
        </div>

        {/* Footer */}
        <div className="border-t border-gray-700 bg-gray-900 p-4 flex justify-end gap-3 flex-shrink-0">
          <button
            onClick={onClose}
            disabled={loading}
            className="px-4 py-2 border border-gray-600 text-gray-300 rounded-lg hover:bg-gray-800 transition-colors text-sm font-medium"
          >
            Cancel
          </button>
          <button
            onClick={handleConfirm}
            disabled={loading || selectedCount === 0 || !cancellationReason.trim()}
            className="px-4 py-2 bg-gradient-to-r from-red-600 to-red-700 text-white rounded-lg hover:from-red-700 hover:to-red-800 transition-all duration-200 font-medium text-sm disabled:opacity-50 disabled:cursor-not-allowed flex items-center"
          >
            {loading ? (
              <>
                <div className="w-3 h-3 border-2 border-white border-t-transparent rounded-full animate-spin mr-2"></div>
                Cancelling...
              </>
            ) : (
              <>
                <ExclamationTriangleIcon className="h-4 w-4 mr-2" />
                Cancel {selectedCount} Appointment{selectedCount !== 1 ? 's' : ''}
              </>
            )}
          </button>
        </div>
      </div>
    </div>
  );
};

export default CancelBulkAppointmentsModal;
