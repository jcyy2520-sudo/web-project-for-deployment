import { useEffect, useState } from 'react';
import { XMarkIcon, EnvelopeIcon, ExclamationTriangleIcon, ChatBubbleLeftRightIcon } from '@heroicons/react/24/outline';

const MessageModal = ({ isOpen, onClose, user, onSend, loading }) => {
  const [messageData, setMessageData] = useState({
    subject: '',
    message: '',
    type: 'general'
  });

  useEffect(() => {
    if (isOpen && user) {
      setMessageData({
        subject: '',
        message: '',
        type: 'general'
      });
    }
  }, [isOpen, user]);

  const handleSubmit = (e) => {
    e.preventDefault();
    if (messageData.subject && messageData.message) {
      onSend({
        ...messageData,
        userId: user.id,
        userName: `${user.first_name} ${user.last_name}`
      });
    }
  };

  if (!isOpen || !user) return null;

  return (
    <div className="fixed inset-0 bg-black bg-opacity-70 flex items-center justify-center z-50 p-4 animate-fadeIn">
      <div className="bg-gray-900 border border-amber-500/30 rounded-lg shadow-xl w-full max-w-md max-h-[85vh] overflow-hidden flex flex-col transform animate-scaleIn">
        <div className="flex justify-between items-center p-4 border-b border-gray-700 bg-gray-900 flex-shrink-0">
          <div className="flex items-center">
            <ChatBubbleLeftRightIcon className="h-5 w-5 text-amber-400 mr-2" />
            <h3 className="text-sm font-semibold text-amber-50">
              Message to {user.first_name} {user.last_name}
            </h3>
          </div>
          <button 
            onClick={onClose} 
            className="text-gray-400 hover:text-amber-400 transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-amber-500 rounded p-1"
            disabled={loading}
          >
            <XMarkIcon className="h-4 w-4" />
          </button>
        </div>
        
        <form onSubmit={handleSubmit} className="flex-1 min-h-0 overflow-y-auto p-4 space-y-3 flex flex-col">
          <div>
            <label className="block text-xs font-medium text-amber-50 mb-1">
              Subject *
            </label>
            <input
              type="text"
              value={messageData.subject}
              onChange={(e) => setMessageData(prev => ({ ...prev, subject: e.target.value }))}
              className="w-full px-2 py-1.5 bg-gray-800 border border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-500 focus:border-amber-500 transition-all duration-200 text-xs text-white placeholder-gray-400"
              disabled={loading}
              placeholder="Subject"
              required
            />
          </div>

          <div>
            <label className="block text-xs font-medium text-amber-50 mb-1">
              Type
            </label>
            <select
              value={messageData.type}
              onChange={(e) => setMessageData(prev => ({ ...prev, type: e.target.value }))}
              className="w-full px-2 py-1.5 bg-gray-800 border border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-500 focus:border-amber-500 transition-all duration-200 text-xs text-white"
              disabled={loading}
            >
              <option value="general">General</option>
              <option value="appointment">Appointment</option>
              <option value="notification">Notification</option>
              <option value="urgent">Urgent</option>
            </select>
          </div>

          <div className="flex-1 flex flex-col">
            <label className="block text-xs font-medium text-amber-50 mb-1">
              Message *
            </label>
            <textarea
              value={messageData.message}
              onChange={(e) => setMessageData(prev => ({ ...prev, message: e.target.value }))}
              rows="4"
              className="flex-1 px-2 py-1.5 bg-gray-800 border border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-500 focus:border-amber-500 transition-all duration-200 text-xs text-white placeholder-gray-400 resize-none"
              disabled={loading}
              placeholder="Message..."
              required
            />
          </div>

          <div className="bg-amber-500/10 border border-amber-500/30 rounded p-2 flex-shrink-0">
            <div className="flex items-center gap-1">
              <ExclamationTriangleIcon className="h-3.5 w-3.5 text-amber-400 flex-shrink-0" />
              <span className="text-amber-200 text-xs">
                Message will be sent to email
              </span>
            </div>
          </div>

          <div className="flex justify-end space-x-2 pt-3 border-t border-gray-700 flex-shrink-0">
            <button
              type="button"
              onClick={onClose}
              className="px-3 py-1.5 border border-gray-600 text-gray-300 rounded-lg hover:bg-gray-800 transition-all duration-200 font-medium text-xs focus:outline-none focus:ring-2 focus:ring-amber-500 disabled:opacity-50"
              disabled={loading}
            >
              Cancel
            </button>
            <button
              type="submit"
              className="px-3 py-1.5 bg-gradient-to-r from-amber-600 to-amber-700 text-white rounded-lg hover:from-amber-700 hover:to-amber-800 transition-all duration-200 font-medium text-xs focus:outline-none focus:ring-2 focus:ring-amber-500 shadow disabled:opacity-50 disabled:cursor-not-allowed flex items-center"
              disabled={loading}
            >
              {loading ? (
                <>
                  <div className="w-3 h-3 border-2 border-white border-t-transparent rounded-full animate-spin mr-1"></div>
                  Sending...
                </>
              ) : (
                <>
                  <EnvelopeIcon className="h-3 w-3 mr-1" />
                  Send Message
                </>
              )}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
};

export default MessageModal;
