import { useState, useEffect, useMemo, useCallback, useRef, forwardRef, useImperativeHandle } from 'react';
import { useAuth } from '../../context/AuthContext';
import { useApi } from '../../hooks/useApi';
import axios from 'axios';
import {
  EnvelopeIcon,
  PaperAirplaneIcon,
  XMarkIcon,
  MagnifyingGlassIcon,
  ArrowPathIcon,
  TrashIcon,
  EllipsisVerticalIcon,
  ClockIcon
} from '@heroicons/react/24/outline';

// Message Details Modal
const MessageDetailsModal = ({ isOpen, onClose, message, isDarkMode }) => {
  if (!isOpen || !message) return null;

  const senderName = message.sender?.first_name && message.sender?.last_name 
    ? `${message.sender.first_name} ${message.sender.last_name}`
    : 'Unknown';

  const formatDateTime = (date) => {
    return new Date(date).toLocaleString([], { 
      year: 'numeric',
      month: 'long',
      day: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
      second: '2-digit'
    });
  };

  const formatRelativeTime = (date) => {
    const now = new Date();
    const messageDate = new Date(date);
    const diffMs = now - messageDate;
    const diffMins = Math.floor(diffMs / 60000);
    const diffHours = Math.floor(diffMs / 3600000);
    const diffDays = Math.floor(diffMs / 86400000);

    if (diffMins < 1) return 'Just now';
    if (diffMins < 60) return `${diffMins}m ago`;
    if (diffHours < 24) return `${diffHours}h ago`;
    if (diffDays < 7) return `${diffDays}d ago`;
    return messageDate.toLocaleDateString();
  };

  return (
    <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
      <div className={`${isDarkMode ? 'bg-gray-800 border-gray-700' : 'bg-white border-gray-200'} border rounded-lg shadow-xl w-full max-w-md max-h-[80vh] overflow-auto`}>
        <div className={`p-4 border-b ${isDarkMode ? 'border-gray-700 bg-gray-900' : 'border-gray-200 bg-gray-50'} sticky top-0`}>
          <h3 className={`text-sm font-semibold ${isDarkMode ? 'text-amber-50' : 'text-amber-900'}`}>Message Details</h3>
        </div>
        <div className={`p-6 space-y-4 text-sm ${isDarkMode ? 'text-gray-300' : 'text-gray-700'}`}>
          {/* From Section */}
          <div className={`p-3 rounded-lg ${isDarkMode ? 'bg-gray-700/50' : 'bg-amber-50'}`}>
            <p className={`text-xs font-semibold uppercase tracking-wide mb-2 ${isDarkMode ? 'text-gray-400' : 'text-gray-600'}`}>From:</p>
            <p className={`text-base font-semibold ${isDarkMode ? 'text-amber-200' : 'text-amber-900'}`}>{senderName}</p>
            {message.sender?.email && (
              <p className={`text-xs mt-1 ${isDarkMode ? 'text-gray-400' : 'text-gray-600'}`}>{message.sender.email}</p>
            )}
          </div>

          {/* Sent Time Section */}
          <div className={`p-3 rounded-lg ${isDarkMode ? 'bg-gray-700/50' : 'bg-blue-50'}`}>
            <p className={`text-xs font-semibold uppercase tracking-wide mb-2 ${isDarkMode ? 'text-gray-400' : 'text-gray-600'}`}>Sent At:</p>
            <p className={`text-base font-mono ${isDarkMode ? 'text-blue-200' : 'text-blue-900'}`}>{formatDateTime(message.created_at)}</p>
            <p className={`text-xs mt-1 ${isDarkMode ? 'text-gray-400' : 'text-gray-600'}`}>
              <ClockIcon className="inline h-3 w-3 mr-1" />
              {formatRelativeTime(message.created_at)}
            </p>
          </div>

          {/* Subject Section */}
          {message.subject && (
            <div className={`p-3 rounded-lg ${isDarkMode ? 'bg-gray-700/50' : 'bg-purple-50'}`}>
              <p className={`text-xs font-semibold uppercase tracking-wide mb-2 ${isDarkMode ? 'text-gray-400' : 'text-gray-600'}`}>Subject:</p>
              <p className={`text-sm ${isDarkMode ? 'text-gray-200' : 'text-gray-900'}`}>{message.subject}</p>
            </div>
          )}
        </div>
        <div className={`p-4 border-t ${isDarkMode ? 'border-gray-700' : 'border-gray-200'} flex justify-end sticky bottom-0 ${isDarkMode ? 'bg-gray-900' : 'bg-white'}`}>
          <button
            onClick={onClose}
            className={`px-4 py-2 text-sm font-medium rounded transition-colors ${
              isDarkMode
                ? 'bg-amber-600 text-white hover:bg-amber-700'
                : 'bg-amber-50 text-amber-900 hover:bg-amber-100'
            }`}
          >
            Close
          </button>
        </div>
      </div>
    </div>
  );
};

// Message Thread Component
const MessageThread = ({ messages, currentUserId, isDarkMode }) => {
  const [selectedMessage, setSelectedMessage] = useState(null);

  if (!messages || messages.length === 0) {
    return (
      <div className={`flex flex-col items-center justify-center py-12 ${isDarkMode ? 'text-gray-400' : 'text-gray-600'}`}>
        <EnvelopeIcon className="h-12 w-12 mb-2 opacity-50" />
        <p>No messages yet</p>
      </div>
    );
  }

  const formatMessageTime = (date) => {
    const messageDate = new Date(date);
    const now = new Date();
    const isToday = messageDate.toDateString() === now.toDateString();
    
    if (isToday) {
      return messageDate.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    } else {
      return messageDate.toLocaleDateString([], { month: 'short', day: 'numeric' }) + 
             ' at ' + 
             messageDate.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    }
  };

  return (
    <>
      <div className="space-y-4">
        {messages.map((msg) => {
          const isOwn = msg.sender_id === currentUserId;
          return (
            <div key={msg.id} className={`flex ${isOwn ? 'justify-end' : 'justify-start'} group`}>
              <div className="flex items-end gap-1 max-w-full">
                <div
                  className={`max-w-xs md:max-w-md px-4 py-2 rounded-lg relative ${
                    isOwn
                      ? 'bg-amber-500 text-white rounded-br-none'
                      : isDarkMode
                      ? 'bg-gray-700 text-gray-100 rounded-bl-none'
                      : 'bg-gray-200 text-gray-900 rounded-bl-none'
                  }`}
                >
                  {msg.subject && (
                    <p className={`text-xs font-semibold mb-1 ${isOwn ? 'text-amber-100' : isDarkMode ? 'text-gray-300' : 'text-gray-700'}`}>
                      {msg.subject}
                    </p>
                  )}
                  <p className="text-sm break-words">{msg.message}</p>
                  <div className={`flex items-center justify-between mt-1 ${isOwn ? 'text-amber-100' : isDarkMode ? 'text-gray-400' : 'text-gray-600'}`}>
                    <p className={`text-xs ${isOwn ? 'text-amber-100/80' : isDarkMode ? 'text-gray-400' : 'text-gray-600'}`}>
                      {formatMessageTime(msg.created_at)}
                    </p>
                  </div>
                </div>
                <div className="flex flex-col items-center gap-1">
                  <button
                    onClick={() => setSelectedMessage(msg)}
                    className={`flex-shrink-0 p-1 rounded opacity-0 group-hover:opacity-100 transition-opacity ${
                      isOwn
                        ? 'text-amber-100 hover:bg-amber-600/30'
                        : isDarkMode
                        ? 'text-gray-400 hover:bg-gray-600/30'
                        : 'text-gray-600 hover:bg-gray-300/30'
                    }`}
                    title="View message details"
                  >
                    <EllipsisVerticalIcon className="h-4 w-4" />
                  </button>
                </div>
              </div>
            </div>
          );
        })}
      </div>
      <MessageDetailsModal 
        isOpen={!!selectedMessage} 
        onClose={() => setSelectedMessage(null)} 
        message={selectedMessage}
        isDarkMode={isDarkMode}
      />
    </>
  );
};

// Main Admin Messages Component
const AdminMessages = forwardRef(({ isDarkMode }, ref) => {
  const { user } = useAuth();
  const { callApi } = useApi();

  const [conversations, setConversations] = useState([]);
  const [selectedConversation, setSelectedConversation] = useState(null);
  const [messages, setMessages] = useState([]);
  const [searchTerm, setSearchTerm] = useState('');
  const [replyMessage, setReplyMessage] = useState('');
  const [showDeleteConfirm, setShowDeleteConfirm] = useState(false);
  const [loading, setLoading] = useState(false);
  const [initialLoadDone, setInitialLoadDone] = useState(false);
  const [showConversationsList, setShowConversationsList] = useState(true);
  const messagesEndRef = useRef(null);

  const loadConversations = useCallback(async () => {
    try {
      const result = await callApi(() =>
        axios.get('/api/messages', { timeout: 10000 })
      );

      if (result.success) {
        let convs = Array.isArray(result.data?.data) ? result.data.data : (Array.isArray(result.data) ? result.data : []);
        
        // Deduplicate conversations - keep only one entry per user
        const seen = new Set();
        convs = convs.filter(conv => {
          const userId = conv.user?.id;
          if (seen.has(userId)) return false;
          seen.add(userId);
          return true;
        });
        
        // Sort conversations by last message date (newest first) - most recent messages at top
        convs.sort((a, b) => {
          const dateA = new Date(a.last_message?.created_at || a.created_at || 0);
          const dateB = new Date(b.last_message?.created_at || b.created_at || 0);
          return dateB.getTime() - dateA.getTime(); // Explicitly use getTime() for clarity
        });
        
        setConversations(convs);
      }
    } catch (error) {
      console.error('Error loading conversations:', error);
    }
  }, [callApi]);

  const loadMessages = useCallback(async (userId) => {
    try {
      const result = await callApi(() =>
        axios.get(`/api/messages/conversation/user/${userId}`, { timeout: 10000 })
      );

      if (result.success) {
        const messageData = result.data?.data || result.data || {};
        let msgs = messageData.messages || [];
        // Sort messages by created_at in ascending order (oldest first)
        msgs = msgs.sort((a, b) => new Date(a.created_at) - new Date(b.created_at));
        setMessages(msgs);
      }
    } catch (error) {
      console.error('Error loading messages:', error);
    }
  }, [callApi]);

  // Initial load only
  useEffect(() => {
    if (!initialLoadDone) {
      loadConversations();
      setInitialLoadDone(true);
    }
  }, []);

  // Load messages when conversation selected
  useEffect(() => {
    if (selectedConversation?.user?.id) {
      loadMessages(selectedConversation.user.id);
    }
  }, [selectedConversation?.user?.id]);

  // Auto-scroll to new messages
  useEffect(() => {
    if (messages && messages.length > 0) {
      // Use setTimeout to ensure DOM is updated before scrolling
      const timer = setTimeout(() => {
        messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' });
      }, 0);
      return () => clearTimeout(timer);
    }
  }, [messages, selectedConversation]);

  useImperativeHandle(ref, () => ({
    refreshConversations: async () => {
      await loadConversations();
      if (selectedConversation?.user?.id) {
        await loadMessages(selectedConversation.user.id);
      }
    }
  }), [loadConversations, loadMessages, selectedConversation]);

  const filteredConversations = useMemo(() => {
    if (!searchTerm) return conversations;
    const searchLower = searchTerm.toLowerCase();
    return conversations.filter(
      (conv) =>
        (conv.user?.first_name?.toLowerCase() || '').includes(searchLower) ||
        (conv.user?.last_name?.toLowerCase() || '').includes(searchLower) ||
        (conv.user?.email?.toLowerCase() || '').includes(searchLower)
    );
  }, [conversations, searchTerm]);

  const handleSendReply = useCallback(
    async (e) => {
      e.preventDefault();
      if (!replyMessage.trim() || !selectedConversation) return;

      try {
        setLoading(true);
        const result = await callApi(() =>
          axios.post('/api/messages', {
            receiver_id: selectedConversation?.user?.id,
            message: replyMessage,
            subject: 'Message'
          }, { timeout: 15000 })
        );

        if (result.success) {
          setReplyMessage('');
          await loadMessages(selectedConversation.user.id);
          await loadConversations();
        } else {
          console.error('Error sending reply:', result);
          alert('Failed to send message. Please try again.');
        }
      } catch (error) {
        console.error('Error sending reply:', error);
        alert('Error sending message: ' + (error.response?.data?.message || error.message));
      } finally {
        setLoading(false);
      }
    },
    [callApi, selectedConversation, replyMessage, loadMessages, loadConversations]
  );

  const handleDeleteConversation = useCallback(async () => {
    try {
      setLoading(true);
      const result = await callApi(() =>
        axios.delete(`/api/messages/conversation/${selectedConversation.user.id}`, { timeout: 15000 })
      );

      if (result.success) {
        setShowDeleteConfirm(false);
        setSelectedConversation(null);
        setMessages([]);
        await loadConversations();
      }
    } catch (error) {
      console.error('Error deleting conversation:', error);
    } finally {
      setLoading(false);
    }
  }, [callApi, selectedConversation, loadConversations]);

  const unreadCount = conversations.reduce((sum, conv) => sum + (conv.unread_count || 0), 0);

  const getLastMessageTime = (conversation) => {
    if (conversation.last_message?.created_at) {
      const date = new Date(conversation.last_message.created_at);
      const now = new Date();
      const isToday = date.toDateString() === now.toDateString();
      
      if (isToday) {
        return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
      } else {
        return date.toLocaleDateString([], { month: 'short', day: 'numeric' });
      }
    }
    return '';
  };

  return (
    <div className={`h-full flex flex-col ${isDarkMode ? 'bg-gray-900' : 'bg-white'} rounded-lg border ${isDarkMode ? 'border-gray-800' : 'border-gray-200'}`}>
      {/* Header */}
      <div className={`p-4 border-b ${isDarkMode ? 'border-gray-800 bg-gray-800/50' : 'border-gray-200 bg-gray-50'}`}>
        <div className="flex items-center justify-between mb-4">
          <div>
            <h2 className={`text-lg font-bold ${isDarkMode ? 'text-amber-50' : 'text-amber-900'}`}>Messages</h2>
            {unreadCount > 0 && <p className="text-xs text-red-500">{unreadCount} unread</p>}
          </div>
          <div className="flex items-center gap-2">
            <button onClick={() => loadConversations()} className={`p-2 ${isDarkMode ? 'text-amber-400 hover:bg-amber-500/10' : 'text-amber-600 hover:bg-amber-100'} rounded transition-colors`} title="Refresh">
              <ArrowPathIcon className="h-5 w-5" />
            </button>
            {selectedConversation && (
              <button 
                onClick={() => setShowConversationsList(!showConversationsList)}
                className={`px-3 py-2 text-xs font-medium rounded transition-colors ${
                  showConversationsList 
                    ? isDarkMode ? 'bg-amber-500/20 text-amber-400' : 'bg-amber-100 text-amber-700'
                    : isDarkMode ? 'text-amber-400 hover:bg-amber-500/10' : 'text-amber-600 hover:bg-amber-100'
                }`}
              >
                {showConversationsList ? 'Hide' : 'Show'} List
              </button>
            )}
          </div>
        </div>

        {/* Search */}
        <div className="relative">
          <MagnifyingGlassIcon className={`absolute left-3 top-1/2 transform -translate-y-1/2 h-4 w-4 ${isDarkMode ? 'text-amber-400' : 'text-amber-600'}`} />
          <input
            type="text"
            placeholder="Search conversations..."
            value={searchTerm}
            onChange={(e) => setSearchTerm(e.target.value)}
            className={`w-full pl-10 pr-3 py-2 text-sm border rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-500 ${isDarkMode ? 'bg-gray-700 border-gray-600 text-white placeholder-gray-400' : 'bg-white border-gray-300 text-gray-900 placeholder-gray-500'}`}
          />
        </div>
      </div>

      {/* Main Content */}
      <div className="flex flex-col lg:flex-row flex-1 min-h-0 gap-4 p-4">
        {/* Conversations List - Collapsible on mobile/when hidden */}
        {showConversationsList && (
        <div className={`w-full lg:w-64 border rounded-lg overflow-hidden flex flex-col ${isDarkMode ? 'bg-gray-800 border-gray-700' : 'bg-white border-gray-200'}`}>
          <div className="flex-1 overflow-y-auto">
            {filteredConversations.length === 0 ? (
              <div className={`flex items-center justify-center h-full ${isDarkMode ? 'text-gray-400' : 'text-gray-600'} text-sm`}>
                <div className="text-center py-8">
                  <EnvelopeIcon className="h-10 w-10 mx-auto mb-2 opacity-50" />
                  <p>No conversations</p>
                </div>
              </div>
            ) : (
              filteredConversations.map((conv) => (
                <button
                  key={conv.user.id}
                  onClick={() => setSelectedConversation(conv)}
                  className={`w-full text-left p-3 border-b transition-colors ${
                    selectedConversation?.user.id === conv.user.id
                      ? isDarkMode
                        ? 'bg-amber-500/10 border-amber-500'
                        : 'bg-amber-50 border-amber-300'
                      : isDarkMode
                      ? 'border-gray-700 hover:bg-gray-700'
                      : 'border-gray-200 hover:bg-gray-50'
                  }`}
                >
                  <div className="flex items-start justify-between gap-2">
                    <div className="flex-1 min-w-0">
                      <p className={`text-sm font-medium truncate ${isDarkMode ? 'text-amber-50' : 'text-amber-900'}`}>
                        {conv.user?.first_name} {conv.user?.last_name}
                      </p>
                      <p className={`text-xs truncate ${isDarkMode ? 'text-gray-400' : 'text-gray-600'}`}>{conv.user?.email}</p>
                      {conv.last_message && (
                        <div className="flex items-center gap-1 mt-1">
                          <p className={`text-xs truncate line-clamp-1 ${isDarkMode ? 'text-gray-500' : 'text-gray-600'}`}>
                            {conv.last_message.message}
                          </p>
                        </div>
                      )}
                    </div>
                    <div className="flex flex-col items-end gap-1 flex-shrink-0">
                      {conv.last_message?.created_at && (
                        <span className={`text-xs ${isDarkMode ? 'text-gray-500' : 'text-gray-500'}`}>
                          {getLastMessageTime(conv)}
                        </span>
                      )}
                      {conv.unread_count > 0 && (
                        <div className="bg-red-500 text-white text-xs font-bold px-2 py-0.5 rounded-full">
                          {conv.unread_count}
                        </div>
                      )}
                    </div>
                  </div>
                </button>
              ))
            )}
          </div>
        </div>
        )}

        {/* Messages Area - Takes full width when conversation is selected */}
        {selectedConversation ? (
          <div className={`flex-1 flex flex-col border rounded-lg overflow-hidden min-w-0 ${isDarkMode ? 'bg-gray-800 border-gray-700' : 'bg-white border-gray-200'}`}>
            {/* Header with User Info */}
            <div className={`p-4 border-b flex-shrink-0 ${isDarkMode ? 'border-gray-700 bg-gray-900' : 'border-gray-200 bg-gray-50'}`}>
              <div className="flex items-center justify-between">
                <div>
                  <h3 className={`font-semibold ${isDarkMode ? 'text-amber-50' : 'text-amber-900'}`}>
                    {selectedConversation.user?.first_name} {selectedConversation.user?.last_name}
                  </h3>
                  <p className={`text-sm ${isDarkMode ? 'text-gray-400' : 'text-gray-600'}`}>{selectedConversation.user?.email}</p>
                </div>
                <button
                  onClick={() => setShowDeleteConfirm(true)}
                  className={`p-2 ${isDarkMode ? 'text-gray-400 hover:text-red-400 hover:bg-red-500/10' : 'text-gray-600 hover:text-red-600 hover:bg-red-100'} rounded transition-colors`}
                  title="Delete conversation"
                >
                  <TrashIcon className="h-5 w-5" />
                </button>
              </div>
            </div>

            {/* Messages - Fixed height with scroll */}
            <div className="max-h-96 overflow-y-auto p-4 space-y-3">
              <MessageThread messages={messages} currentUserId={user?.id} isDarkMode={isDarkMode} />
              <div ref={messagesEndRef} />
            </div>

            {/* Reply Input Form - Fixed at bottom */}
            <form onSubmit={handleSendReply} className={`flex-shrink-0 p-4 border-t ${isDarkMode ? 'border-gray-700 bg-gray-900' : 'border-gray-200 bg-gray-50'}`}>
              <div className="flex gap-2">
                <input
                  type="text"
                  value={replyMessage}
                  onChange={(e) => setReplyMessage(e.target.value)}
                  placeholder="Type your message..."
                  className={`flex-1 px-3 py-2 text-sm border rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-500 ${isDarkMode ? 'bg-gray-700 border-gray-600 text-white placeholder-gray-400' : 'bg-white border-gray-300 text-gray-900 placeholder-gray-500'}`}
                  disabled={loading}
                />
                <button
                  type="submit"
                  disabled={loading || !replyMessage.trim()}
                  className="px-4 py-2 bg-amber-600 text-white rounded-lg text-sm font-medium hover:bg-amber-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed flex items-center"
                >
                  {loading ? (
                    <div className="w-3 h-3 border-2 border-white border-t-transparent rounded-full animate-spin"></div>
                  ) : (
                    <PaperAirplaneIcon className="h-4 w-4" />
                  )}
                </button>
              </div>
            </form>
          </div>
        ) : (
          <div className={`flex-1 flex items-center justify-center ${isDarkMode ? 'bg-gray-800 border-gray-700' : 'bg-white border-gray-200'} border rounded-lg ${isDarkMode ? 'text-gray-400' : 'text-gray-600'}`}>
            <div className="text-center">
              <EnvelopeIcon className="h-12 w-12 mx-auto mb-2 opacity-50" />
              <p>Select a conversation to view messages</p>
            </div>
          </div>
        )}
      </div>

      {/* Delete Confirmation Modal */}
      {showDeleteConfirm && (
        <div className="fixed inset-0 bg-black bg-opacity-70 flex items-center justify-center z-50 p-4">
          <div className={`${isDarkMode ? 'bg-gray-800 border-gray-700' : 'bg-white border-gray-200'} border rounded-lg shadow-xl w-full max-w-sm`}>
            <div className={`p-4 border-b ${isDarkMode ? 'border-gray-700' : 'border-gray-200'}`}>
              <h3 className={`font-semibold ${isDarkMode ? 'text-amber-50' : 'text-amber-900'}`}>Delete Conversation</h3>
            </div>
            <div className="p-4">
              <p className={`text-sm ${isDarkMode ? 'text-gray-300' : 'text-gray-700'} mb-4`}>
                Are you sure you want to delete this conversation with {selectedConversation?.user?.first_name}? This cannot be undone.
              </p>
              <div className="flex gap-3 justify-end">
                <button
                  onClick={() => setShowDeleteConfirm(false)}
                  className={`px-4 py-2 border rounded-lg text-sm font-medium transition-colors ${isDarkMode ? 'border-gray-600 text-gray-300 hover:bg-gray-700' : 'border-gray-300 text-gray-700 hover:bg-gray-100'}`}
                  disabled={loading}
                >
                  Cancel
                </button>
                <button
                  onClick={handleDeleteConversation}
                  className="px-4 py-2 bg-red-600 text-white rounded-lg text-sm font-medium hover:bg-red-700 transition-colors disabled:opacity-50 flex items-center"
                  disabled={loading}
                >
                  {loading && <div className="w-3 h-3 border-2 border-white border-t-transparent rounded-full animate-spin mr-2"></div>}
                  Delete
                </button>
              </div>
            </div>
          </div>
        </div>
      )}
    </div>
  );
});

AdminMessages.displayName = 'AdminMessages';

export default AdminMessages;