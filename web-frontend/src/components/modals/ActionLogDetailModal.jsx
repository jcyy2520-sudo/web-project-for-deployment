import React from 'react';
import {
  XMarkIcon,
  ClockIcon,
  UserIcon,
  CubeIcon,
  IdentificationIcon,
  ChatBubbleLeftIcon
} from '@heroicons/react/24/outline';

const ActionLogDetailModal = ({ isOpen, onClose, log, isDarkMode = true }) => {
  if (!isOpen || !log) return null;

  const formatDate = (dateString) => {
    return new Date(dateString).toLocaleString([], {
      year: 'numeric',
      month: 'long',
      day: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
      second: '2-digit'
    });
  };

  const getActionBadgeColor = (action) => {
    if (isDarkMode) {
      switch (action) {
        case 'create': return 'bg-green-500/20 text-green-400 border border-green-500/40';
        case 'update': return 'bg-blue-500/20 text-blue-400 border border-blue-500/40';
        case 'delete': return 'bg-red-500/20 text-red-400 border border-red-500/40';
        case 'restore': return 'bg-amber-500/20 text-amber-400 border border-amber-500/40';
        case 'approve': return 'bg-emerald-500/20 text-emerald-400 border border-emerald-500/40';
        case 'decline': return 'bg-orange-500/20 text-orange-400 border border-orange-500/40';
        case 'complete': return 'bg-cyan-500/20 text-cyan-400 border border-cyan-500/40';
        case 'toggle_status':
        case 'activate':
        case 'deactivate': return 'bg-purple-500/20 text-purple-400 border border-purple-500/40';
        default: return 'bg-gray-500/20 text-gray-400 border border-gray-500/40';
      }
    } else {
      switch (action) {
        case 'create': return 'bg-green-100 text-green-700 border border-green-300';
        case 'update': return 'bg-blue-100 text-blue-700 border border-blue-300';
        case 'delete': return 'bg-red-100 text-red-700 border border-red-300';
        case 'restore': return 'bg-amber-100 text-amber-700 border border-amber-300';
        case 'approve': return 'bg-emerald-100 text-emerald-700 border border-emerald-300';
        case 'decline': return 'bg-orange-100 text-orange-700 border border-orange-300';
        case 'complete': return 'bg-cyan-100 text-cyan-700 border border-cyan-300';
        case 'toggle_status':
        case 'activate':
        case 'deactivate': return 'bg-purple-100 text-purple-700 border border-purple-300';
        default: return 'bg-gray-100 text-gray-700 border border-gray-300';
      }
    }
  };

  const formatActionName = (action) => {
    return action
      .split('_')
      .map(word => word.charAt(0).toUpperCase() + word.slice(1))
      .join(' ');
  };

  // Extract message content from description if present
  const extractMessageContent = (description) => {
    if (!description) return null;
    
    // Pattern to extract recipient and message content
    // Format: "Sent message to {name}. Message content: {message}"
    const messagePattern = /Sent message to\s+(.+?)\.\s*Message content:\s*(.+?)$/i;
    const match = description.match(messagePattern);
    
    if (match) {
      return {
        hasMessage: true,
        recipient: match[1]?.trim() || null,
        message: match[2]?.trim() || null
      };
    }
    
    // Fallback for decline reasons
    const reasonPattern = /(?:Reason:)\s*(.+?)(?=\.|\s-|\s)|(?:Reason:\s*)(.+?)$/i;
    const reasonMatch = description.match(reasonPattern);
    
    if (reasonMatch) {
      return {
        hasReason: true,
        reason: reasonMatch[1]?.trim() || reasonMatch[2]?.trim() || null
      };
    }
    
    return null;
  };

  const messageContent = extractMessageContent(log.description);

  return (
    <div className={`fixed inset-0 bg-black ${isDarkMode ? 'bg-opacity-70' : 'bg-opacity-50'} flex items-center justify-center z-50 p-4`}>
      <div className={`${isDarkMode ? 'bg-gray-800 border-gray-700' : 'bg-white border-gray-200'} border rounded-lg shadow-2xl w-full max-w-2xl max-h-[90vh] overflow-y-auto`}>
        {/* Header */}
        <div className={`sticky top-0 flex justify-between items-center p-6 border-b ${isDarkMode ? 'border-gray-700 bg-gray-900' : 'border-gray-200 bg-gray-50'}`}>
          <h3 className={`text-lg font-bold ${isDarkMode ? 'text-amber-50' : 'text-amber-900'}`}>
            Action Details
          </h3>
          <button
            onClick={onClose}
            className={`${isDarkMode ? 'text-gray-400 hover:text-amber-400' : 'text-gray-600 hover:text-amber-600'} transition-colors`}
          >
            <XMarkIcon className="h-6 w-6" />
          </button>
        </div>

        {/* Content */}
        <div className="p-6 space-y-6">
          {/* Action Badge */}
          <div className="flex items-center gap-4">
            <div className={`px-4 py-2 rounded-lg font-semibold text-sm ${getActionBadgeColor(log.action)}`}>
              {formatActionName(log.action)}
            </div>
            <p className={`text-xs ${isDarkMode ? 'text-gray-400' : 'text-gray-600'}`}>
              {formatDate(log.created_at)}
            </p>
          </div>

          {/* User Information */}
          <div className={`${isDarkMode ? 'bg-gray-700/50 border-gray-600' : 'bg-gray-50 border-gray-200'} border rounded-lg p-4`}>
            <div className="flex items-center gap-2 mb-3">
              <UserIcon className={`h-4 w-4 ${isDarkMode ? 'text-amber-400' : 'text-amber-600'}`} />
              <h4 className={`font-semibold text-sm ${isDarkMode ? 'text-amber-50' : 'text-amber-900'}`}>
                User Information
              </h4>
            </div>
            <div className="grid grid-cols-2 gap-4 text-sm">
              <div>
                <p className={`${isDarkMode ? 'text-gray-400' : 'text-gray-600'} text-xs mb-1`}>Name</p>
                <p className={`font-medium ${isDarkMode ? 'text-gray-200' : 'text-gray-900'}`}>
                  {log.user?.first_name} {log.user?.last_name}
                </p>
              </div>
              <div>
                <p className={`${isDarkMode ? 'text-gray-400' : 'text-gray-600'} text-xs mb-1`}>Email</p>
                <p className={`font-medium ${isDarkMode ? 'text-gray-200' : 'text-gray-900'}`}>
                  {log.user?.email}
                </p>
              </div>
              <div>
                <p className={`${isDarkMode ? 'text-gray-400' : 'text-gray-600'} text-xs mb-1`}>Role</p>
                <p className={`font-medium ${isDarkMode ? 'text-gray-200' : 'text-gray-900'}`}>
                  {log.user?.role || 'N/A'}
                </p>
              </div>
            </div>
          </div>

          {/* Description */}
          <div>
            <div className="flex items-center gap-2 mb-3">
              <CubeIcon className={`h-4 w-4 ${isDarkMode ? 'text-amber-400' : 'text-amber-600'}`} />
              <h4 className={`font-semibold text-sm ${isDarkMode ? 'text-amber-50' : 'text-amber-900'}`}>
                Action Description
              </h4>
            </div>
            <div className={`${isDarkMode ? 'bg-gray-700/50 border-gray-600' : 'bg-gray-50 border-gray-200'} border rounded-lg p-4`}>
              <p className={`text-sm leading-relaxed break-words ${isDarkMode ? 'text-gray-300' : 'text-gray-700'}`}>
                {log.description}
              </p>
            </div>
          </div>

          {/* Message Content Display */}
          {(messageContent?.message || messageContent?.reason) && (
            <div>
              <div className="flex items-center gap-2 mb-3">
                <ChatBubbleLeftIcon className={`h-4 w-4 ${isDarkMode ? 'text-blue-400' : 'text-blue-600'}`} />
                <h4 className={`font-semibold text-sm ${isDarkMode ? 'text-blue-50' : 'text-blue-900'}`}>
                  {messageContent.reason ? 'Decline Reason' : 'Message Sent'}
                </h4>
              </div>
              <div className={`${isDarkMode ? 'bg-blue-900/30 border-blue-600/40' : 'bg-blue-50 border-blue-300'} border rounded-lg p-4 space-y-3`}>
                {messageContent.recipient && (
                  <div>
                    <p className={`text-xs font-semibold mb-1 ${isDarkMode ? 'text-blue-300' : 'text-blue-700'}`}>
                      Recipient:
                    </p>
                    <p className={`text-sm font-medium ${isDarkMode ? 'text-blue-200' : 'text-blue-900'}`}>
                      {messageContent.recipient}
                    </p>
                  </div>
                )}
                <div>
                  <p className={`text-xs font-semibold mb-1 ${isDarkMode ? 'text-blue-300' : 'text-blue-700'}`}>
                    {messageContent.reason ? 'Reason' : 'Message'}:
                  </p>
                  <div className={`text-sm leading-relaxed break-words whitespace-pre-wrap ${isDarkMode ? 'text-blue-200' : 'text-blue-900'}`}>
                    {messageContent.reason || messageContent.message}
                  </div>
                </div>
              </div>
            </div>
          )}

          {/* Model Information */}
          {(log.model_type || log.model_id) && (
            <div className={`${isDarkMode ? 'bg-gray-700/50 border-gray-600' : 'bg-gray-50 border-gray-200'} border rounded-lg p-4`}>
              <div className="flex items-center gap-2 mb-3">
                <IdentificationIcon className={`h-4 w-4 ${isDarkMode ? 'text-amber-400' : 'text-amber-600'}`} />
                <h4 className={`font-semibold text-sm ${isDarkMode ? 'text-amber-50' : 'text-amber-900'}`}>
                  Related Record
                </h4>
              </div>
              <div className="grid grid-cols-2 gap-4 text-sm">
                {log.model_type && (
                  <div>
                    <p className={`${isDarkMode ? 'text-gray-400' : 'text-gray-600'} text-xs mb-1`}>Type</p>
                    <p className={`font-medium ${isDarkMode ? 'text-gray-200' : 'text-gray-900'}`}>
                      {log.model_type}
                    </p>
                  </div>
                )}
                {log.model_id && (
                  <div>
                    <p className={`${isDarkMode ? 'text-gray-400' : 'text-gray-600'} text-xs mb-1`}>ID</p>
                    <p className={`font-medium ${isDarkMode ? 'text-gray-200' : 'text-gray-900'}`}>
                      {log.model_id}
                    </p>
                  </div>
                )}
              </div>
            </div>
          )}

          {/* Timestamp Information */}
          <div className={`${isDarkMode ? 'bg-gray-700/50 border-gray-600' : 'bg-gray-50 border-gray-200'} border rounded-lg p-4`}>
            <div className="flex items-center gap-2 mb-3">
              <ClockIcon className={`h-4 w-4 ${isDarkMode ? 'text-amber-400' : 'text-amber-600'}`} />
              <h4 className={`font-semibold text-sm ${isDarkMode ? 'text-amber-50' : 'text-amber-900'}`}>
                Timestamp
              </h4>
            </div>
            <div className="grid grid-cols-2 gap-4 text-sm">
              <div>
                <p className={`${isDarkMode ? 'text-gray-400' : 'text-gray-600'} text-xs mb-1`}>Created</p>
                <p className={`font-medium ${isDarkMode ? 'text-gray-200' : 'text-gray-900'}`}>
                  {formatDate(log.created_at)}
                </p>
              </div>
              {log.updated_at && (
                <div>
                  <p className={`${isDarkMode ? 'text-gray-400' : 'text-gray-600'} text-xs mb-1`}>Updated</p>
                  <p className={`font-medium ${isDarkMode ? 'text-gray-200' : 'text-gray-900'}`}>
                    {formatDate(log.updated_at)}
                  </p>
                </div>
              )}
            </div>
          </div>


        </div>

        {/* Footer */}
        <div className={`sticky bottom-0 flex justify-end p-6 border-t ${isDarkMode ? 'border-gray-700 bg-gray-900' : 'border-gray-200 bg-gray-50'}`}>
          <button
            onClick={onClose}
            className={`px-4 py-2 rounded-lg font-medium text-sm transition-colors ${
              isDarkMode
                ? 'bg-amber-600 text-white hover:bg-amber-700'
                : 'bg-amber-500 text-white hover:bg-amber-600'
            }`}
          >
            Close
          </button>
        </div>
      </div>
    </div>
  );
};

export default ActionLogDetailModal;
