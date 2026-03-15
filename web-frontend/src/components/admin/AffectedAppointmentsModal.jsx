import React, { useState, useEffect } from 'react';
import { formatServiceName, formatDateDisplay } from '../../utils/format';
import { 
  XMarkIcon, 
  CheckCircleIcon, 
  ExclamationTriangleIcon, 
  TrashIcon,
  EnvelopeIcon,
  ChatBubbleLeftRightIcon,
  UserGroupIcon,
  UserIcon,
  PencilSquareIcon
} from '@heroicons/react/24/outline';

const DEFAULT_MESSAGE = `Dear valued customer,

We regret to inform you that we need to reschedule your appointment due to unforeseen circumstances. The date you selected has become unavailable.

We sincerely apologize for any inconvenience this may cause. Please contact us to reschedule your appointment at your earliest convenience.

Thank you for your understanding.

Best regards,
The Team`;

const AffectedAppointmentsModal = ({ isOpen, onClose, affected = [], dateData = null, onConfirm, onCancelSelected, onSendMessage, loading }) => {
  const [selected, setSelected] = useState([]);
  const [activeTab, setActiveTab] = useState('review'); // 'review', 'cancel', 'message'
  const [messageType, setMessageType] = useState('default'); // 'default', 'custom'
  const [customMessage, setCustomMessage] = useState('');
  const [sendOption, setSendOption] = useState('all'); // 'all', 'selected'
  const [cancellationReason, setCancellationReason] = useState('Date marked as unavailable');

  useEffect(() => {
    if (isOpen) {
      setSelected([]);
      setActiveTab('review');
      setMessageType('default');
      setCustomMessage('');
      setSendOption('all');
      setCancellationReason('Date marked as unavailable');
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
      window.showToast?.('Warning', 'Please select at least one appointment to cancel', 'warning');
      return;
    }
    onCancelSelected({ 
      affected, 
      selectedIds: selected, 
      dateData,
      cancellationReason 
    });
  };

  const handleCancelAll = () => {
    onCancelSelected({ 
      affected, 
      selectedIds: affected.map(apt => apt.id), 
      dateData,
      cancellationReason 
    });
  };

  const handleSendMessages = () => {
    const targetAppointments = sendOption === 'all' 
      ? affected 
      : affected.filter(apt => selected.includes(apt.id));
    
    if (targetAppointments.length === 0) {
      window.showToast?.('Warning', 'Please select at least one appointment to send message to', 'warning');
      return;
    }

    const messageContent = messageType === 'default' ? DEFAULT_MESSAGE : customMessage;
    
    if (messageType === 'custom' && !customMessage.trim()) {
      window.showToast?.('Warning', 'Please enter a custom message', 'warning');
      return;
    }

    if (onSendMessage) {
      onSendMessage({
        appointments: targetAppointments,
        message: messageContent,
        sendOption,
        dateData
      });
    }
  };

  const getTabButtonClass = (tab) => {
    const baseClass = "flex-1 px-3 py-2 text-xs font-medium rounded-lg transition-all flex items-center justify-center gap-1.5";
    if (activeTab === tab) {
      return `${baseClass} bg-amber-500/20 text-amber-300 border border-amber-500/40`;
    }
    return `${baseClass} text-gray-400 hover:text-gray-300 hover:bg-gray-800`;
  };

  return (
    <div className="fixed inset-0 bg-black bg-opacity-70 flex items-center justify-center z-50 p-4 animate-fadeIn">
      <div className="bg-gray-900 border border-amber-500/30 rounded-lg shadow-xl w-full max-w-4xl max-h-[90vh] overflow-hidden flex flex-col transform animate-scaleIn">
        {/* Header */}
        <div className="flex justify-between items-center p-4 border-b border-gray-700 sticky top-0 bg-gray-900 flex-shrink-0">
          <div className="flex items-center">
            <ExclamationTriangleIcon className="h-5 w-5 text-amber-400 mr-2" />
            <h3 className="text-sm font-semibold text-amber-50">Affected Appointments - Action Required</h3>
          </div>
          <button onClick={onClose} className="text-gray-400 hover:text-amber-400 p-1 rounded" disabled={loading}>
            <XMarkIcon className="h-4 w-4" />
          </button>
        </div>

        {/* Warning Banner */}
        <div className="p-4 border-b border-gray-700">
          <div className="bg-amber-500/10 border border-amber-500/30 rounded-lg p-3">
            <p className="text-xs text-amber-200">
              <strong>{affected.length} appointment{affected.length !== 1 ? 's' : ''}</strong> scheduled on <strong>{dateData?.date ? new Date(dateData.date).toLocaleDateString() : 'this date'}</strong> will be affected by this unavailable date.
            </p>
          </div>
        </div>

        {/* Tab Navigation */}
        <div className="px-4 pt-3 pb-2 border-b border-gray-700">
          <div className="flex gap-2">
            <button className={getTabButtonClass('review')} onClick={() => setActiveTab('review')}>
              <UserGroupIcon className="h-4 w-4" />
              Review ({affected.length})
            </button>
            <button className={getTabButtonClass('cancel')} onClick={() => setActiveTab('cancel')}>
              <TrashIcon className="h-4 w-4" />
              Cancel Options
            </button>
            <button className={getTabButtonClass('message')} onClick={() => setActiveTab('message')}>
              <EnvelopeIcon className="h-4 w-4" />
              Send Message
            </button>
          </div>
        </div>

        {/* Tab Content */}
        <div className="flex-1 overflow-y-auto p-4">
          {/* Review Tab */}
          {activeTab === 'review' && (
            <div className="space-y-4">
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
                <div className="divide-y divide-gray-700 max-h-80 overflow-y-auto rounded border border-gray-700 bg-gray-800/50">
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
                        className="mt-1 cursor-pointer"
                      />
                      <div className="flex-1">
                        <div className="flex items-center justify-between">
                          <div>
                            <div className="text-sm font-medium text-amber-50">
                              {apt.user?.first_name} {apt.user?.last_name} — {formatServiceName(apt)}
                            </div>
                            <div className="text-xs text-gray-400 mt-1">
                              {formatDateDisplay(apt.appointment_date)} at {apt.appointment_time}
                            </div>
                            {apt.user?.email && (
                              <div className="text-xs text-gray-500 mt-0.5">
                                {apt.user.email}
                              </div>
                            )}
                          </div>
                          <div className="text-xs text-gray-400">
                            Status: <span className="font-medium capitalize">{apt.status}</span>
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

              <div className="bg-gray-800/50 border border-gray-700 rounded-lg p-3 space-y-2">
                <p className="text-xs font-medium text-gray-300">Available Actions:</p>
                <ul className="text-xs text-gray-400 space-y-1 ml-2">
                  <li>✓ Review all affected appointments above</li>
                  <li>✓ Go to <strong>Cancel Options</strong> tab to cancel appointments</li>
                  <li>✓ Go to <strong>Send Message</strong> tab to notify users</li>
                  <li>✓ Or simply create the unavailable date without any changes</li>
                </ul>
              </div>
            </div>
          )}

          {/* Cancel Tab */}
          {activeTab === 'cancel' && (
            <div className="space-y-4">
              <div className="bg-red-500/10 border border-red-500/30 rounded-lg p-3">
                <p className="text-xs text-red-200">
                  <strong>Warning:</strong> Cancelling appointments will notify affected users and cannot be undone.
                </p>
              </div>

              {/* Cancellation Reason */}
              <div>
                <label className="block text-xs font-medium text-gray-300 mb-2">
                  Cancellation Reason
                </label>
                <textarea
                  value={cancellationReason}
                  onChange={(e) => setCancellationReason(e.target.value)}
                  placeholder="Enter reason for cancellation..."
                  className="w-full px-3 py-2 bg-gray-800 border border-gray-700 rounded-lg text-sm text-gray-100 placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-amber-500 focus:border-transparent resize-none"
                  rows="2"
                  disabled={loading}
                />
              </div>

              {/* Selected appointments for cancel */}
              <div className="bg-gray-800/50 border border-gray-700 rounded-lg p-3">
                <p className="text-xs font-medium text-gray-300 mb-2">
                  Currently selected: {selected.length} appointment{selected.length !== 1 ? 's' : ''}
                </p>
                <p className="text-xs text-gray-400">
                  Select appointments in the Review tab to cancel specific ones, or use "Cancel All" below.
                </p>
              </div>

              {/* Cancel Action Buttons */}
              <div className="grid grid-cols-2 gap-3">
                <button
                  onClick={handleCancelSelected}
                  disabled={loading || selected.length === 0}
                  className="px-4 py-3 bg-gradient-to-r from-red-600 to-red-700 text-white rounded-lg hover:from-red-700 hover:to-red-800 transition-all duration-200 text-sm font-medium flex flex-col items-center justify-center disabled:opacity-50 disabled:cursor-not-allowed"
                >
                  <TrashIcon className="h-5 w-5 mb-1" />
                  Cancel Selected ({selected.length})
                </button>
                <button
                  onClick={handleCancelAll}
                  disabled={loading || affected.length === 0}
                  className="px-4 py-3 bg-gradient-to-r from-red-700 to-red-800 text-white rounded-lg hover:from-red-800 hover:to-red-900 transition-all duration-200 text-sm font-medium flex flex-col items-center justify-center disabled:opacity-50 disabled:cursor-not-allowed"
                >
                  <TrashIcon className="h-5 w-5 mb-1" />
                  Cancel All ({affected.length})
                </button>
              </div>
            </div>
          )}

          {/* Message Tab */}
          {activeTab === 'message' && (
            <div className="space-y-4">
              <div className="bg-blue-500/10 border border-blue-500/30 rounded-lg p-3">
                <p className="text-xs text-blue-200">
                  Send a message to affected users without cancelling their appointments.
                </p>
              </div>

              {/* Message Type Selection */}
              <div className="space-y-3">
                <label className="text-xs font-medium text-gray-300">Message Type</label>
                <div className="space-y-2">
                  <label className="flex items-start p-3 border border-gray-700 rounded-lg hover:bg-gray-700/20 transition-colors cursor-pointer">
                    <input
                      type="radio"
                      value="default"
                      checked={messageType === 'default'}
                      onChange={(e) => setMessageType(e.target.value)}
                      className="mt-1 cursor-pointer"
                      disabled={loading}
                    />
                    <div className="ml-3 flex-1">
                      <div className="text-xs font-medium text-amber-50 flex items-center gap-2">
                        <ChatBubbleLeftRightIcon className="h-4 w-4" />
                        Default Message
                      </div>
                      <div className="text-xs text-gray-400 mt-1">
                        Use the built-in professional message template
                      </div>
                    </div>
                  </label>

                  <label className="flex items-start p-3 border border-gray-700 rounded-lg hover:bg-gray-700/20 transition-colors cursor-pointer">
                    <input
                      type="radio"
                      value="custom"
                      checked={messageType === 'custom'}
                      onChange={(e) => setMessageType(e.target.value)}
                      className="mt-1 cursor-pointer"
                      disabled={loading}
                    />
                    <div className="ml-3 flex-1">
                      <div className="text-xs font-medium text-amber-50 flex items-center gap-2">
                        <PencilSquareIcon className="h-4 w-4" />
                        Custom Message
                      </div>
                      <div className="text-xs text-gray-400 mt-1">
                        Write your own personalized message
                      </div>
                    </div>
                  </label>
                </div>
              </div>

              {/* Message Content */}
              {messageType === 'default' ? (
                <div>
                  <label className="block text-xs font-medium text-gray-300 mb-2">
                    Default Message Preview
                  </label>
                  <div className="w-full px-3 py-2 bg-gray-800/70 border border-gray-700 rounded-lg text-xs text-gray-300 whitespace-pre-wrap max-h-40 overflow-y-auto">
                    {DEFAULT_MESSAGE}
                  </div>
                </div>
              ) : (
                <div>
                  <label className="block text-xs font-medium text-gray-300 mb-2">
                    Your Custom Message <span className="text-red-400">*</span>
                  </label>
                  <textarea
                    value={customMessage}
                    onChange={(e) => setCustomMessage(e.target.value)}
                    placeholder="Type your message here..."
                    className="w-full px-3 py-2 bg-gray-800 border border-gray-700 rounded-lg text-sm text-gray-100 placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent resize-none"
                    rows="6"
                    disabled={loading}
                  />
                  <div className="text-xs text-gray-500 mt-1">
                    {customMessage.length}/500 characters
                  </div>
                </div>
              )}

              {/* Send Option Selection */}
              <div className="space-y-3">
                <label className="text-xs font-medium text-gray-300">Send To</label>
                <div className="grid grid-cols-2 gap-3">
                  <label className={`flex items-center p-3 border rounded-lg cursor-pointer transition-colors ${
                    sendOption === 'all' 
                      ? 'border-blue-500/50 bg-blue-500/10' 
                      : 'border-gray-700 hover:bg-gray-700/20'
                  }`}>
                    <input
                      type="radio"
                      value="all"
                      checked={sendOption === 'all'}
                      onChange={(e) => setSendOption(e.target.value)}
                      className="cursor-pointer"
                      disabled={loading}
                    />
                    <div className="ml-3">
                      <div className="text-xs font-medium text-amber-50 flex items-center gap-2">
                        <UserGroupIcon className="h-4 w-4" />
                        All Affected Users
                      </div>
                      <div className="text-xs text-gray-400 mt-0.5">
                        {affected.length} user{affected.length !== 1 ? 's' : ''}
                      </div>
                    </div>
                  </label>

                  <label className={`flex items-center p-3 border rounded-lg cursor-pointer transition-colors ${
                    sendOption === 'selected' 
                      ? 'border-blue-500/50 bg-blue-500/10' 
                      : 'border-gray-700 hover:bg-gray-700/20'
                  }`}>
                    <input
                      type="radio"
                      value="selected"
                      checked={sendOption === 'selected'}
                      onChange={(e) => setSendOption(e.target.value)}
                      className="cursor-pointer"
                      disabled={loading}
                    />
                    <div className="ml-3">
                      <div className="text-xs font-medium text-amber-50 flex items-center gap-2">
                        <UserIcon className="h-4 w-4" />
                        Selected Only
                      </div>
                      <div className="text-xs text-gray-400 mt-0.5">
                        {selected.length} selected
                      </div>
                    </div>
                  </label>
                </div>
                {sendOption === 'selected' && selected.length === 0 && (
                  <p className="text-xs text-amber-400">
                    ⚠️ No appointments selected. Go to Review tab to select appointments.
                  </p>
                )}
              </div>

              {/* Send Button */}
              <button
                onClick={handleSendMessages}
                disabled={loading || (sendOption === 'selected' && selected.length === 0) || (messageType === 'custom' && !customMessage.trim())}
                className="w-full px-4 py-3 bg-gradient-to-r from-blue-600 to-blue-700 text-white rounded-lg hover:from-blue-700 hover:to-blue-800 transition-all duration-200 text-sm font-medium flex items-center justify-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed"
              >
                <EnvelopeIcon className="h-5 w-5" />
                Send Message to {sendOption === 'all' ? `All ${affected.length}` : `${selected.length} Selected`} User{(sendOption === 'all' ? affected.length : selected.length) !== 1 ? 's' : ''}
              </button>
            </div>
          )}
        </div>

        {/* Footer */}
        <div className="border-t border-gray-700 bg-gray-900 p-4 flex justify-between items-center flex-shrink-0">
          <button 
            onClick={onClose} 
            disabled={loading}
            className="px-4 py-2 border border-gray-600 text-gray-300 rounded-lg hover:bg-gray-800 transition-colors text-sm font-medium"
          >
            Cancel
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
                Just Create Unavailable Date
              </>
            )}
          </button>
        </div>
      </div>
    </div>
  );
};

export default AffectedAppointmentsModal;
