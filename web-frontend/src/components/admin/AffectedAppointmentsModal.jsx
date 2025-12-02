import React, { useState, useEffect } from 'react';
import { formatServiceName } from '../../utils/format';
import { XMarkIcon, CheckCircleIcon, ExclamationTriangleIcon, TrashIcon } from '@heroicons/react/24/outline';

const AffectedAppointmentsModal = ({ isOpen, onClose, affected = [], dateData = null, onConfirm, onCancelSelected, loading }) => {
  const [selected, setSelected] = useState([]);
  const [actionMode, setActionMode] = useState('proceed'); // 'proceed', 'cancel', or 'partial'

  useEffect(() => {
    if (isOpen) {
      // default: select none (admin can pick which to reschedule)
      setSelected([]);
      setActionMode('proceed');
    }
  }, [isOpen]);

  if (!isOpen) return null;

  const toggleSelect = (id) => {
    setSelected(prev => prev.includes(id) ? prev.filter(x => x !== id) : [...prev, id]);
  };

  const toggleAll = () => {
    if (selected.length === affected.length) {
      setSelected([]);
    } else {
      setSelected(affected.map(apt => apt.id));
    }
  };

  const handleProceed = () => {
    // Just add the unavailable date, no cancellations
    onConfirm({ dateData, affectedIds: [] });
  };

  const handleCancelSelected = () => {
    if (selected.length === 0) {
      alert('Please select at least one appointment to cancel');
      return;
    }
    // Switch to cancel mode and pass selected IDs
    onCancelSelected({ affected, selectedIds: selected, dateData });
  };

  const handleCancelAll = () => {
    // Cancel all affected appointments
    onCancelSelected({ affected, selectedIds: affected.map(apt => apt.id), dateData });
  };

  return (
    <div className="fixed inset-0 bg-black bg-opacity-70 flex items-center justify-center z-50 p-4 animate-fadeIn">
      <div className="bg-gray-900 border border-amber-500/30 rounded-lg shadow-xl w-full max-w-3xl max-h-[90vh] overflow-y-auto transform animate-scaleIn flex flex-col">
        <div className="flex justify-between items-center p-4 border-b border-gray-700 sticky top-0 bg-gray-900 flex-shrink-0">
          <div className="flex items-center">
            <ExclamationTriangleIcon className="h-5 w-5 text-amber-400 mr-2" />
            <h3 className="text-sm font-semibold text-amber-50">Affected Appointments - Action Required</h3>
          </div>
          <button onClick={onClose} className="text-gray-400 hover:text-amber-400 p-1 rounded" disabled={loading}>
            <XMarkIcon className="h-4 w-4" />
          </button>
        </div>

        <div className="flex-1 overflow-y-auto p-4 space-y-4">
          {/* Info Banner */}
          <div className="bg-amber-500/10 border border-amber-500/30 rounded-lg p-3">
            <p className="text-xs text-amber-200">
              <strong>{affected.length} appointment{affected.length !== 1 ? 's' : ''}</strong> scheduled on <strong>{new Date(dateData?.date).toLocaleDateString()}</strong> will be affected by this unavailable date.
            </p>
          </div>

          {/* Appointments List */}
          <div>
            <div className="flex items-center justify-between mb-2">
              <label className="text-xs font-medium text-gray-300">
                Affected Appointments
              </label>
              <button
                onClick={toggleAll}
                className="text-xs text-amber-400 hover:text-amber-300 transition-colors"
              >
                {selected.length === affected.length ? 'Deselect All' : 'Select All'}
              </button>
            </div>

            {affected.length === 0 ? (
              <div className="text-center py-6 text-gray-400">No affected appointments found.</div>
            ) : (
              <div className="divide-y divide-gray-700 max-h-64 overflow-y-auto rounded border border-gray-700 bg-gray-800/50">
                {affected.map(apt => (
                  <div 
                    key={apt.id} 
                    className={`p-3 flex items-start gap-3 hover:bg-gray-700/50 transition-colors cursor-pointer ${
                      selected.includes(apt.id) ? 'bg-gray-700/30' : ''
                    }`}
                    onClick={() => toggleSelect(apt.id)}
                  >
                    <input 
                      type="checkbox" 
                      checked={selected.includes(apt.id)}
                      onChange={() => toggleSelect(apt.id)}
                      className="mt-1"
                    />
                    <div className="flex-1">
                      <div className="flex items-center justify-between">
                        <div>
                          <div className="text-sm font-medium text-amber-50">
                            {apt.user?.first_name} {apt.user?.last_name} — {formatServiceName(apt)}
                          </div>
                          <div className="text-xs text-gray-400 mt-1">
                            {new Date(apt.appointment_date).toLocaleDateString()} at {apt.appointment_time}
                          </div>
                        </div>
                        <div className="text-xs text-gray-400">
                          Status: <span className="font-medium">{apt.status}</span>
                        </div>
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

          {/* Action Info */}
          <div className="bg-gray-800/50 border border-gray-700 rounded-lg p-3 space-y-2">
            <p className="text-xs font-medium text-gray-300">What would you like to do?</p>
            <ul className="text-xs text-gray-400 space-y-1 ml-2">
              <li>✓ Create the unavailable date without changing these appointments</li>
              <li>✓ Cancel selected appointments and notify affected users</li>
              <li>✓ Cancel all affected appointments with custom messaging</li>
            </ul>
          </div>
        </div>

        {/* Footer with Action Buttons */}
        <div className="border-t border-gray-700 bg-gray-900 p-4 flex justify-end gap-3 flex-shrink-0">
          <button 
            onClick={onClose} 
            disabled={loading}
            className="px-4 py-2 border border-gray-600 text-gray-300 rounded-lg hover:bg-gray-800 transition-colors text-sm font-medium"
          >
            Cancel
          </button>

          {selected.length > 0 && (
            <button
              onClick={handleCancelSelected}
              disabled={loading}
              className="px-4 py-2 bg-gradient-to-r from-red-600 to-red-700 text-white rounded-lg hover:from-red-700 hover:to-red-800 transition-all duration-200 text-sm font-medium flex items-center"
            >
              <TrashIcon className="h-4 w-4 mr-2" />
              Cancel Selected ({selected.length})
            </button>
          )}

          <button
            onClick={handleCancelAll}
            disabled={loading || affected.length === 0}
            className="px-4 py-2 bg-gradient-to-r from-red-600 to-red-700 text-white rounded-lg hover:from-red-700 hover:to-red-800 transition-all duration-200 text-sm font-medium flex items-center"
            title="Cancel all affected appointments with decision support"
          >
            <TrashIcon className="h-4 w-4 mr-2" />
            Cancel All & Decide
          </button>

          <button
            onClick={handleProceed}
            disabled={loading}
            className="px-4 py-2 bg-gradient-to-r from-amber-600 to-amber-700 text-white rounded-lg hover:from-amber-700 hover:to-amber-800 transition-all duration-200 text-sm font-medium flex items-center"
          >
            {loading ? (
              <>
                <div className="w-3 h-3 border-2 border-white border-t-transparent rounded-full animate-spin mr-2"></div>
                Processing...
              </>
            ) : (
              <>
                <CheckCircleIcon className="h-4 w-4 mr-2" />
                Just Create Date
              </>
            )}
          </button>
        </div>
      </div>
    </div>
  );
};

export default AffectedAppointmentsModal;
