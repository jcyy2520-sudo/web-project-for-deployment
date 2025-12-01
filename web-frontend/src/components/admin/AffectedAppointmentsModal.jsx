import React, { useState, useEffect } from 'react';
import { formatServiceName } from '../../utils/format';
import { XMarkIcon, CheckCircleIcon, ExclamationTriangleIcon } from '@heroicons/react/24/outline';

const AffectedAppointmentsModal = ({ isOpen, onClose, affected = [], dateData = null, onConfirm, loading }) => {
  const [selected, setSelected] = useState([]);

  useEffect(() => {
    if (isOpen) {
      // default: select none (admin can pick which to reschedule)
      setSelected([]);
    }
  }, [isOpen]);

  if (!isOpen) return null;

  const toggleSelect = (id) => {
    setSelected(prev => prev.includes(id) ? prev.filter(x => x !== id) : [...prev, id]);
  };

  return (
    <div className="fixed inset-0 bg-black bg-opacity-70 flex items-center justify-center z-50 p-4 animate-fadeIn">
      <div className="bg-gray-900 border border-amber-500/30 rounded-lg shadow-xl w-full max-w-3xl max-h-[85vh] overflow-y-auto transform animate-scaleIn">
        <div className="flex justify-between items-center p-4 border-b border-gray-700 sticky top-0 bg-gray-900">
          <div className="flex items-center">
            <ExclamationTriangleIcon className="h-5 w-5 text-amber-400 mr-2" />
            <h3 className="text-sm font-semibold text-amber-50">Affected Appointments Preview</h3>
          </div>
          <button onClick={onClose} className="text-gray-400 hover:text-amber-400 p-1 rounded">
            <XMarkIcon className="h-4 w-4" />
          </button>
        </div>

        <div className="p-4 space-y-3">
          <p className="text-gray-300 text-sm">The following appointments would be affected by adding this unavailable date. You may choose which appointments to reschedule now. If you proceed without selecting any, the blackout will still be created but appointments will remain unchanged.</p>

          <div className="space-y-2">
            {affected.length === 0 ? (
              <div className="text-center py-6 text-gray-400">No affected appointments found.</div>
            ) : (
              <div className="divide-y divide-gray-700 max-h-72 overflow-y-auto rounded border border-gray-700">
                {affected.map(apt => (
                  <div key={apt.id} className="p-3 flex items-start justify-between">
                    <div className="flex-1">
                      <div className="flex items-center justify-between">
                        <div>
                          <div className="text-sm font-medium text-amber-50">{apt.user?.first_name} {apt.user?.last_name} — {formatServiceName(apt)}</div>
                          <div className="text-xs text-gray-400">{new Date(apt.appointment_date).toLocaleDateString()} at {apt.appointment_time}</div>
                        </div>
                        <div className="text-xs text-gray-400">Status: {apt.status}</div>
                      </div>
                      {apt.notes && <div className="text-xs text-gray-500 mt-1">Notes: {apt.notes}</div>}
                    </div>

                    <div className="ml-4 flex-shrink-0">
                      <label className="inline-flex items-center cursor-pointer">
                        <input type="checkbox" checked={selected.includes(apt.id)} onChange={() => toggleSelect(apt.id)} className="mr-2" />
                        <span className="text-xs text-amber-50">Reschedule</span>
                      </label>
                    </div>
                  </div>
                ))}
              </div>
            )}
          </div>

          <div className="flex justify-end space-x-2 pt-3 border-t border-gray-700">
            <button onClick={onClose} className="px-3 py-1.5 border border-gray-600 text-gray-300 rounded hover:bg-gray-800 transition-colors text-sm">Cancel</button>
            <button
              onClick={() => onConfirm({ dateData, affectedIds: selected })}
              disabled={loading}
              className="px-3 py-1.5 bg-gradient-to-r from-amber-600 to-amber-700 text-white rounded hover:from-amber-700 hover:to-amber-800 transition-all duration-200 text-sm"
            >
              {loading ? 'Processing...' : <><CheckCircleIcon className="h-4 w-4 inline-block mr-1" /> Apply</>}
            </button>
          </div>
        </div>
      </div>
    </div>
  );
};

export default AffectedAppointmentsModal;
