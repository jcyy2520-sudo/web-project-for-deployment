import { useState, useEffect, useMemo, useCallback, useRef } from 'react';
import { useAuth } from '../context/AuthContext';
import { useApi } from '../hooks/useApi';
import axios from 'axios';
import logger from '../utils/logger';
import {
  EnvelopeIcon,
  PaperAirplaneIcon,
  XMarkIcon,
  MagnifyingGlassIcon,
  ArrowPathIcon,
  TrashIcon,
  Bars3Icon,
  ChatBubbleLeftRightIcon,
  ExclamationTriangleIcon,
  EllipsisVerticalIcon,
  ClockIcon,
  CalendarIcon,
  ArrowLeftIcon,
  InformationCircleIcon
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
          <h3 className={`text-sm font-semibold ${isDarkMode ? 'text-amber-50' : 'theme-text-primary'}`}>Message Details</h3>
        </div>
        <div className={`p-6 space-y-4 text-sm ${isDarkMode ? 'text-gray-300' : 'text-gray-700'}`}>
          {/* From Section */}
          <div className={`p-3 rounded-lg ${isDarkMode ? 'bg-gray-700/50' : ''}`} style={!isDarkMode ? { backgroundColor: 'var(--secondary-10)' } : {}}>
            <p className={`text-xs font-semibold uppercase tracking-wide mb-2 ${isDarkMode ? 'text-gray-400' : 'text-gray-600'}`}>From:</p>
            <p className={`text-base font-semibold ${isDarkMode ? 'text-amber-200' : 'theme-text-primary'}`}>{senderName}</p>
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
            className={`px-4 py-2 text-sm font-medium rounded ${isDarkMode ? 'bg-amber-600 text-white hover:bg-amber-700' : ''}`}
            style={!isDarkMode ? { backgroundColor: 'var(--secondary)', color: '#fff' } : {}}
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
                      ? 'bg-amber-500 text-black rounded-br-none'
                      : isDarkMode
                      ? 'bg-gray-700 text-gray-100 rounded-bl-none'
                      : 'bg-gray-200 text-gray-900 rounded-bl-none'
                  }`}
                >
                  {msg.subject && (
                    <p className={`text-xs font-semibold mb-1 ${isOwn ? 'text-black' : isDarkMode ? 'text-gray-300' : 'text-gray-700'}`}>
                      {msg.subject}
                    </p>
                  )}
                  <p className="text-sm break-words">{msg.message}</p>
                  <div className={`flex items-center justify-between mt-1 ${isOwn ? 'text-black' : isDarkMode ? 'text-gray-400' : 'text-gray-600'}`}>
                    <p className={`text-xs ${isOwn ? 'text-black/80' : isDarkMode ? 'text-gray-400' : 'text-gray-600'}`}>
                      {formatMessageTime(msg.created_at)}
                    </p>
                  </div>
                </div>
                <div className="flex flex-col items-center gap-1">
                  <button
                    onClick={() => setSelectedMessage(msg)}
                    className={`flex-shrink-0 p-1 rounded opacity-0 group-hover:opacity-100 ${
                      isOwn
                        ? 'text-black hover:bg-amber-200/30'
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

// Delete Confirmation Modal
const DeleteConfirmationModal = ({ isOpen, onClose, onConfirm, loading, isDarkMode, userName }) => {
  if (!isOpen) return null;

  return (
    <div className="fixed inset-0 bg-black bg-opacity-70 flex items-center justify-center z-50 p-4">
      <div className={`${isDarkMode ? 'bg-gray-900 border-amber-500/30' : 'bg-white border-gray-200'} border rounded-lg shadow-xl w-full max-w-md`}>
        <div className={`p-4 border-b ${isDarkMode ? 'border-gray-700' : 'border-gray-200'}`}>
          <div className="flex items-center">
            <div className="p-2 rounded-lg bg-red-500/20">
              <ExclamationTriangleIcon className="h-5 w-5 text-red-400" />
            </div>
            <h3 className={`text-sm font-semibold ml-2 ${isDarkMode ? 'text-amber-50' : 'text-amber-900'}`}>Delete Conversation</h3>
          </div>
        </div>
        <div className="p-4">
          <p className={`text-sm ${isDarkMode ? 'text-gray-300' : 'text-gray-700'} mb-4`}>
            Are you sure you want to delete this conversation with {userName}? This cannot be undone.
          </p>
          <div className="flex gap-3 justify-end">
            <button
              onClick={onClose}
              className={`px-4 py-2 border rounded-lg text-sm font-medium ${isDarkMode ? 'border-gray-600 text-gray-300 hover:bg-gray-700' : 'border-gray-300 text-gray-700 hover:bg-gray-100'}`}
              disabled={loading}
            >
              Cancel
            </button>
            <button
              onClick={onConfirm}
              className="px-4 py-2 bg-red-600 text-white rounded-lg text-sm font-medium hover:bg-red-700 disabled:opacity-50 flex items-center"
              disabled={loading}
            >
              {loading && <div className="w-3 h-3 border-2 border-white border-t-transparent rounded-full mr-2"></div>}
              Delete
            </button>
          </div>
        </div>
      </div>
    </div>
  );
};

// Main Message Center Component
const MessageCenter = ({ isDarkMode = true, compact = false, hideMobileHeader = false }) => {
  const { user } = useAuth();
  const { callApi } = useApi();

  const [conversations, setConversations] = useState([]);
  const [selectedConversation, setSelectedConversation] = useState(null);
  const [messages, setMessages] = useState([]);
  const [searchTerm, setSearchTerm] = useState('');
  const [replyMessage, setReplyMessage] = useState('');
  const [showDeleteConfirm, setShowDeleteConfirm] = useState(false);
  const [showMobileSidebar, setShowMobileSidebar] = useState(false);
  const [loading, setLoading] = useState(false);
  const [loadingConversations, setLoadingConversations] = useState(true);
  const [loadingMessages, setLoadingMessages] = useState(false);
  const [error, setError] = useState(null);
  const [mobileViewingConversation, setMobileViewingConversation] = useState(false);
  const [messageLimit, setMessageLimit] = useState(null); // { remaining_messages, message_limit, has_limit }
  const [sendError, setSendError] = useState(null);
  const messagesEndRef = useRef(null);

  useEffect(() => {
    loadConversations();
  }, []);

  useEffect(() => {
    if (selectedConversation) {
      loadMessages(selectedConversation.user.id);
      checkMessageLimit(selectedConversation.user.id);
    } else {
      setMessageLimit(null);
      setSendError(null);
    }
  }, [selectedConversation]);

  useEffect(() => {
    if (messages && messages.length > 0) {
      // Use setTimeout to ensure DOM is updated before scrolling
      const timer = setTimeout(() => {
        messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' });
      }, 0);
      return () => clearTimeout(timer);
    }
  }, [messages, selectedConversation]);

  const loadConversations = useCallback(async () => {
    try {
      setLoadingConversations(true);
      setError(null);
      
      // Load both existing conversations and admin contacts in parallel
      const [convsResult, adminContactsResult] = await Promise.all([
        callApi(() => axios.get('/api/messages', { timeout: 10000 }), { skipCache: true, abortPrevious: false }),
        callApi(() => axios.get('/api/messages/admin-contacts', { timeout: 10000 }), { skipCache: true, abortPrevious: false })
      ]);

      let convs = [];
      if (convsResult.success) {
        convs = Array.isArray(convsResult.data?.data) ? convsResult.data.data : (Array.isArray(convsResult.data) ? convsResult.data : []);
        
        // Deduplicate conversations and filter to show only admin/staff conversations
        const seen = new Set();
        convs = convs.filter(conv => {
          const userId = conv.user?.id;
          if (seen.has(userId)) return false;
          seen.add(userId);
          // Filter out chatbot-type messages from last_message
          if (conv.last_message?.type === 'chatbot') {
            conv.last_message = null;
          }
          // Only show conversations with admin or staff users
          const userRole = conv.user?.role?.toLowerCase();
          return userRole === 'admin' || userRole === 'staff';
        });

        // For clients: only keep ONE admin conversation (the one with the most recent message)
        if (convs.length > 1) {
          // Sort by last message date descending so the most active conversation is first
          convs.sort((a, b) => {
            const dateA = a.last_message ? new Date(a.last_message.created_at) : new Date(0);
            const dateB = b.last_message ? new Date(b.last_message.created_at) : new Date(0);
            return dateB - dateA;
          });
          // Keep only the first (most recent) admin conversation
          convs = [convs[0]];
        }
      }

      // Add admin/staff contacts that don't have existing conversations
      // But only if user doesn't already have an admin conversation
      if (adminContactsResult.success && convs.length === 0) {
        const adminContacts = Array.isArray(adminContactsResult.data?.data) 
          ? adminContactsResult.data.data 
          : (Array.isArray(adminContactsResult.data) ? adminContactsResult.data : []);
        
        // Only add the first admin contact (user should see one admin entry)
        if (adminContacts.length > 0) {
          convs.push({
            user: adminContacts[0],
            last_message: null,
            unread_count: 0,
            is_new_contact: true // Flag to identify admin contacts without prior conversation
          });
        }
      }
      
      // Sort conversations by last message date (newest first), new contacts at bottom
      convs.sort((a, b) => {
        // Contacts with messages first, then new contacts
        if (a.last_message && !b.last_message) return -1;
        if (!a.last_message && b.last_message) return 1;
        if (!a.last_message && !b.last_message) return 0;
        const dateA = new Date(a.last_message?.created_at || 0);
        const dateB = new Date(b.last_message?.created_at || 0);
        return dateB.getTime() - dateA.getTime();
      });
      
      setConversations(convs);
    } catch (error) {
      logger.error('Error loading conversations:', error);
      setError('Failed to load conversations. Please try again.');
    } finally {
      setLoadingConversations(false);
    }
  }, [callApi]);

  const loadMessages = useCallback(async (userId) => {
    try {
      setLoadingMessages(true);
      const result = await callApi(() =>
        axios.get(`/api/messages/conversation/user/${userId}`, { timeout: 10000 }),
        { skipCache: true, abortPrevious: false }
      );

      if (result.success) {
        const messageData = result.data?.data || result.data || {};
        let msgs = messageData.messages || [];
        // Sort messages by created_at in ascending order (oldest first)
        msgs = msgs.sort((a, b) => new Date(a.created_at) - new Date(b.created_at));
        setMessages(msgs);
      }
    } catch (error) {
      logger.error('Error loading messages:', error);
    } finally {
      setLoadingMessages(false);
    }
  }, [callApi]);

  const handleSendReply = useCallback(async (e) => {
    e.preventDefault();
    if (!replyMessage.trim() || !selectedConversation) return;

    // Check if message limit is reached
    if (messageLimit?.has_limit && messageLimit.remaining_messages <= 0) {
      const limit = messageLimit.message_limit || 2;
      setSendError(`You can only send up to ${limit} messages at a time. Please wait for the admin to reply.`);
      return;
    }

    setLoading(true);
    setSendError(null);
    try {
      const result = await callApi(() =>
        axios.post('/api/messages', {
          receiver_id: selectedConversation.user.id,
          message: replyMessage,
          subject: 'Message',
          type: 'reply'
        }, { timeout: 10000 })
      );

      if (result.success) {
        setReplyMessage('');
        await loadMessages(selectedConversation.user.id);
        await loadConversations();
        // Refresh message limit after sending
        await checkMessageLimit(selectedConversation.user.id);
      } else {
        const errorMsg = result.data?.message || result.error?.response?.data?.message || 'Failed to send message.';
        const errorCode = result.data?.error_code || result.error?.response?.data?.error_code;
        if (errorCode === 'MESSAGE_LIMIT_EXCEEDED') {
          const limit = result.data?.message_limit || result.error?.response?.data?.message_limit || messageLimit?.message_limit || 2;
          setSendError(`You can only send up to ${limit} messages at a time. Please wait for the admin to reply.`);
          setMessageLimit(prev => prev ? { ...prev, remaining_messages: 0 } : { has_limit: true, remaining_messages: 0, message_limit: limit });
        } else {
          setSendError(errorMsg);
        }
        logger.error('Error sending message:', result);
      }
    } catch (error) {
      logger.error('Error sending reply:', error);
      const errorCode = error.response?.data?.error_code;
      if (errorCode === 'MESSAGE_LIMIT_EXCEEDED') {
        const limit = error.response?.data?.message_limit || messageLimit?.message_limit || 2;
        setSendError(`You can only send up to ${limit} messages at a time. Please wait for the admin to reply.`);
        setMessageLimit(prev => prev ? { ...prev, remaining_messages: 0 } : { has_limit: true, remaining_messages: 0, message_limit: limit });
      } else {
        setSendError(error.response?.data?.message || error.message || 'Error sending message.');
      }
    } finally {
      setLoading(false);
    }
  }, [callApi, loadMessages, loadConversations, selectedConversation, replyMessage, messageLimit]);

  const handleDeleteConversation = useCallback(async () => {
    if (!selectedConversation) return;
    
    setLoading(true);
    try {
      const result = await callApi(() =>
        axios.delete(`/api/messages/conversation/${selectedConversation.user.id}`, { timeout: 10000 })
      );

      if (result.success) {
        setShowDeleteConfirm(false);
        setSelectedConversation(null);
        setMobileViewingConversation(false);
        setMessageLimit(null);
        setSendError(null);
        await loadConversations();
      }
    } catch (error) {
      logger.error('Error deleting conversation:', error);
    } finally {
      setLoading(false);
    }
  }, [callApi, selectedConversation, loadConversations]);

  // Check message limit for the current conversation
  const checkMessageLimit = useCallback(async (userId) => {
    try {
      const result = await callApi(() =>
        axios.get(`/api/messages/message-limit/${userId}`, { timeout: 10000 }),
        { skipCache: true }
      );

      if (result.success) {
        const data = result.data?.data || result.data || {};
        setMessageLimit(data);
        if (data.remaining_messages > 0) {
          setSendError(null);
        }
      }
    } catch (error) {
      logger.error('Error checking message limit:', error);
    }
  }, [callApi]);

  const filteredConversations = useMemo(() => {
    if (!searchTerm.trim()) return conversations;
    
    return conversations.filter(conv => 
      `${conv.user?.first_name} ${conv.user?.last_name}`.toLowerCase().includes(searchTerm.toLowerCase()) ||
      conv.user?.email?.toLowerCase().includes(searchTerm.toLowerCase())
    );
  }, [conversations, searchTerm]);

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
    <>
      {compact ? (
        // Compact mode - for use within Dashboard
        <div className={`flex flex-col gap-4 h-auto min-h-[400px] sm:h-[500px] ${isDarkMode ? 'bg-gray-800' : 'bg-white'} border rounded-lg overflow-hidden`}>
          {/* Header */}
          <div className={`p-4 border-b ${isDarkMode ? 'border-gray-700 bg-gray-900' : 'border-gray-200 bg-gray-50'}`}>
            <h3 className={`font-semibold ${isDarkMode ? 'text-amber-50' : 'text-amber-900'}`}>Conversations</h3>
            <div className="relative mt-3">
              <MagnifyingGlassIcon className={`absolute left-3 top-2.5 h-4 w-4 ${isDarkMode ? 'text-gray-500' : 'text-gray-400'}`} />
              <input
                type="text"
                placeholder="Search..."
                value={searchTerm}
                onChange={(e) => setSearchTerm(e.target.value)}
                className={`w-full pl-9 pr-3 py-2 text-sm border rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-500 ${isDarkMode ? 'bg-gray-700 border-gray-600 text-white placeholder-gray-400' : 'bg-white border-gray-300 text-gray-900 placeholder-gray-500'}`}
              />
            </div>
          </div>

          {/* Main Content Area */}
          <div className="flex flex-1 min-h-0 gap-3 px-4 pb-4 flex-col sm:flex-row">
            {/* Conversations Sidebar */}
            <div className={`w-full sm:w-64 md:w-72 sm:flex-shrink-0 overflow-y-auto border rounded-lg ${isDarkMode ? 'bg-gray-700 border-gray-600' : 'bg-gray-50 border-gray-200'}`}>
              {filteredConversations.length === 0 ? (
                <div className={`flex items-center justify-center h-full ${isDarkMode ? 'text-gray-400' : 'text-gray-600'} text-xs text-center p-4`}>
                  <p>No conversations</p>
                </div>
              ) : (
                filteredConversations.map((conv) => (
                  <button
                    key={conv.user.id}
                    onClick={() => setSelectedConversation(conv)}
                    className={`w-full text-left p-2 text-sm border-b ${
                      selectedConversation?.user.id === conv.user.id
                        ? isDarkMode
                          ? 'bg-amber-500/20 border-amber-500/40'
                          : 'bg-amber-100 border-amber-200'
                        : isDarkMode
                        ? 'border-gray-600 hover:bg-gray-600'
                        : 'border-gray-200 hover:bg-gray-100'
                    }`}
                  >
                    <div className="flex items-center justify-between">
                      <div className="flex-1 min-w-0">
                        <div className="flex items-center gap-1">
                          <p className={`text-xs font-medium truncate ${isDarkMode ? 'text-amber-50' : 'text-amber-900'}`}>
                            {conv.user?.first_name} {conv.user?.last_name}
                          </p>
                          {conv.user?.role && (
                            <span className={`text-[9px] px-1 py-0.5 rounded-full font-medium flex-shrink-0 ${
                              conv.user.role.toLowerCase() === 'admin' 
                                ? isDarkMode ? 'bg-amber-500/20 text-amber-300' : 'bg-amber-100 text-amber-700'
                                : isDarkMode ? 'bg-blue-500/20 text-blue-300' : 'bg-blue-100 text-blue-700'
                            }`}>
                              {conv.user.role.charAt(0).toUpperCase() + conv.user.role.slice(1)}
                            </span>
                          )}
                        </div>
                        <p className={`text-xs truncate ${isDarkMode ? 'text-gray-400' : 'text-gray-600'}`}>{conv.user?.email}</p>
                        {conv.is_new_contact && !conv.last_message && (
                          <p className={`text-[10px] mt-0.5 italic ${isDarkMode ? 'text-gray-500' : 'text-gray-400'}`}>New contact</p>
                        )}
                      </div>
                      {conv.last_message?.created_at && (
                        <span className={`text-xs flex-shrink-0 ml-1 ${isDarkMode ? 'text-gray-500' : 'text-gray-500'}`}>
                          {getLastMessageTime(conv)}
                        </span>
                      )}
                    </div>
                  </button>
                ))
              )}
            </div>

            {/* Messages Area */}
            {selectedConversation ? (
              <div className="flex-1 flex flex-col min-w-0 border rounded-lg overflow-hidden bg-surface-var border-var">
                {/* Header */}
                <div className={`p-2 border-b ${isDarkMode ? 'border-gray-600 bg-gray-800' : 'border-gray-200 bg-gray-50'}`}>
                  <div className="flex items-center justify-between">
                    <h4 className={`text-xs font-semibold ${isDarkMode ? 'text-amber-50' : 'text-amber-900'}`}>
                      {selectedConversation.user?.first_name} {selectedConversation.user?.last_name}
                    </h4>
                    <button
                      onClick={() => setShowDeleteConfirm(true)}
                      className={`p-1 ${isDarkMode ? 'text-gray-400 hover:text-red-400 hover:bg-red-500/10' : 'text-gray-600 hover:text-red-600 hover:bg-red-100'} rounded`}
                      title="Delete conversation"
                    >
                      <TrashIcon className="h-3 w-3" />
                    </button>
                  </div>
                </div>

                {/* Messages */}
                <div className={`flex-1 flex flex-col overflow-y-auto p-3 space-y-2`}>
                  <MessageThread messages={messages} currentUserId={user?.id} isDarkMode={isDarkMode} />
                  <div ref={messagesEndRef} />
                </div>

                {/* Message Limit Notice */}
                {messageLimit?.has_limit && (
                  <div className={`px-3 py-2 border-t ${messageLimit.remaining_messages <= 0 
                    ? isDarkMode ? 'bg-red-900/30 border-red-800/50' : 'bg-red-50 border-red-200'
                    : isDarkMode ? 'bg-blue-900/30 border-blue-800/50' : 'bg-blue-50 border-blue-200'
                  }`}>
                    <div className="flex items-center gap-1.5">
                      <InformationCircleIcon className={`h-3.5 w-3.5 flex-shrink-0 ${messageLimit.remaining_messages <= 0
                        ? isDarkMode ? 'text-red-400' : 'text-red-500'
                        : isDarkMode ? 'text-blue-400' : 'text-blue-500'
                      }`} />
                      <p className={`text-xs ${messageLimit.remaining_messages <= 0
                        ? isDarkMode ? 'text-red-300' : 'text-red-700'
                        : isDarkMode ? 'text-blue-300' : 'text-blue-700'
                      }`}>
                        {messageLimit.remaining_messages <= 0
                          ? 'Message limit reached. Please wait for admin to reply.'
                          : `You can send ${messageLimit.remaining_messages} more message${messageLimit.remaining_messages === 1 ? '' : 's'} before the admin replies.`
                        }
                      </p>
                    </div>
                  </div>
                )}

                {/* Send Error */}
                {sendError && (
                  <div className={`px-3 py-1.5 ${isDarkMode ? 'bg-red-900/20' : 'bg-red-50'}`}>
                    <p className={`text-xs ${isDarkMode ? 'text-red-400' : 'text-red-600'}`}>{sendError}</p>
                  </div>
                )}

                {/* Send Message Form */}
                <form onSubmit={handleSendReply} className={`p-3 border-t ${isDarkMode ? 'border-gray-600 bg-gray-800' : 'border-gray-200 bg-gray-50'}`}>
                  <div className="flex gap-2">
                    <input
                      type="text"
                      value={replyMessage}
                      onChange={(e) => setReplyMessage(e.target.value)}
                      placeholder={messageLimit?.has_limit && messageLimit.remaining_messages <= 0 ? "Waiting for admin to reply..." : "Type your message..."}
                      className={`flex-1 px-3 py-2 text-xs border rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-500 ${isDarkMode ? 'bg-gray-700 border-gray-600 text-white placeholder-gray-400' : 'bg-white border-gray-300 text-gray-900 placeholder-gray-500'} ${messageLimit?.has_limit && messageLimit.remaining_messages <= 0 ? 'opacity-50' : ''}`}
                      disabled={loading || (messageLimit?.has_limit && messageLimit.remaining_messages <= 0)}
                    />
                    <button
                      type="submit"
                      disabled={loading || !replyMessage.trim() || (messageLimit?.has_limit && messageLimit.remaining_messages <= 0)}
                      className="px-3 py-2 bg-amber-600 text-white rounded-lg text-xs font-medium hover:bg-amber-700 disabled:opacity-50 disabled:cursor-not-allowed flex items-center"
                    >
                      {loading ? (
                        <div className="w-3 h-3 border-2 border-white border-t-transparent rounded-full"></div>
                      ) : (
                        <PaperAirplaneIcon className="h-3 w-3" />
                      )}
                    </button>
                  </div>
                </form>
              </div>
            ) : (
              <div className={`flex-1 flex items-center justify-center ${isDarkMode ? 'bg-gray-700 text-gray-400' : 'bg-gray-100 text-gray-600'} border rounded-lg`}>
                <p className="text-xs">Select a conversation</p>
              </div>
            )}
          </div>
        </div>
      ) : (
        // Full screen mode
        <div className={`flex flex-col h-full min-h-0 ${isDarkMode ? 'bg-gray-900' : 'bg-gray-50'}`} style={{ minHeight: 0 }}>
          {/* Mobile Header - Always show if viewing a conversation to provide the Back button */}
          <div className={`md:hidden ${(hideMobileHeader && !mobileViewingConversation) ? 'hidden' : 'fixed top-0 left-0 right-0 z-50'} ${isDarkMode ? 'bg-gray-900 border-gray-700' : 'bg-white border-gray-200'} border-b p-3 flex items-center justify-between h-14`}>
            {mobileViewingConversation && selectedConversation && (
              <button
                onClick={() => {
                  setMobileViewingConversation(false);
                  setSelectedConversation(null);
                  setReplyMessage('');
                }}
                className={`${isDarkMode ? 'text-gray-400 hover:text-amber-400' : 'text-gray-600 hover:text-amber-600'}`}
              >
                <ArrowLeftIcon className="h-5 w-5" />
              </button>
            )}
            <h1 className={`text-base font-bold truncate ${isDarkMode ? 'text-amber-50' : 'text-amber-900'}`}>
              {mobileViewingConversation && selectedConversation 
                ? `${selectedConversation.user?.first_name} ${selectedConversation.user?.last_name}`
                : 'Messages'}
            </h1>
            <div className="w-5" />
          </div>

          {/* Desktop Header */}
          <div className={`hidden md:flex items-center flex-shrink-0 ${isDarkMode ? 'bg-gray-900/50 border-gray-700/50' : 'bg-white/80 border-gray-200'} border-b px-5 py-3`}>
            <ChatBubbleLeftRightIcon className={`h-5 w-5 mr-2 flex-shrink-0 ${isDarkMode ? 'text-amber-400' : 'text-amber-600'}`} />
            <div>
              <h1 className={`text-lg font-bold ${isDarkMode ? 'text-amber-50' : 'text-amber-900'}`}>Messages</h1>
              <p className={`text-xs ${isDarkMode ? 'text-gray-400' : 'text-gray-600'}`}>Communicate with support</p>
            </div>

          </div>

          {/* Main Content */}
          <main className={`flex-1 overflow-hidden flex flex-col ${hideMobileHeader ? 'pt-0' : 'pt-14'} md:pt-0 md:p-4 min-h-0`}>
            <div className="flex flex-col sm:flex-row gap-0 md:gap-4 flex-1 min-h-0">
              {/* Conversations List - Hide on mobile when viewing conversation */}
              {!mobileViewingConversation && (
                <div className={`w-full sm:w-72 md:w-80 sm:flex-shrink-0 flex flex-col h-[calc(100vh-3.5rem)] md:h-full md:min-h-0 ${isDarkMode ? 'bg-gray-800 border-gray-700' : 'bg-white border-gray-200'} border-0 md:border md:rounded-lg overflow-hidden z-40`}>
                  <div className={`p-2 sm:p-3 border-b ${isDarkMode ? 'border-gray-700 bg-gray-900' : 'border-gray-200 bg-gray-50'}`}>
                    <h3 className={`font-semibold text-xs sm:text-sm mb-2 ${isDarkMode ? 'text-amber-50' : 'text-amber-900'}`}>Conversations</h3>
                    <div className="relative">
                      <MagnifyingGlassIcon className={`absolute left-2 top-2 h-3.5 w-3.5 ${isDarkMode ? 'text-gray-500' : 'text-gray-400'}`} />
                      <input
                        type="text"
                        placeholder="Search..."
                        value={searchTerm}
                        onChange={(e) => setSearchTerm(e.target.value)}
                        className={`w-full pl-8 pr-3 py-1.5 text-xs border rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-500 ${isDarkMode ? 'bg-gray-700 border-gray-600 text-white placeholder-gray-400' : 'bg-white border-gray-300 text-gray-900 placeholder-gray-500'}`}
                      />
                    </div>
                  </div>

                  <div className="flex-1 overflow-y-auto min-h-0">
                    {loadingConversations ? (
                      <div className={`flex items-center justify-center h-full ${isDarkMode ? 'text-gray-400' : 'text-gray-600'} text-sm`}>
                        <div className="text-center py-8">
                          <div className="w-6 h-6 border-2 border-amber-500 border-t-transparent rounded-full animate-spin mx-auto mb-2"></div>
                          <p>Loading conversations...</p>
                        </div>
                      </div>
                    ) : error ? (
                      <div className={`flex items-center justify-center h-full ${isDarkMode ? 'text-red-400' : 'text-red-600'} text-sm`}>
                        <div className="text-center py-8 px-4">
                          <ExclamationTriangleIcon className="h-8 w-8 mx-auto mb-2 opacity-70" />
                          <p className="text-xs">{error}</p>
                          <button onClick={loadConversations} className="mt-2 text-xs text-amber-500 hover:text-amber-400 underline">Retry</button>
                        </div>
                      </div>
                    ) : filteredConversations.length === 0 ? (
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
                          onClick={() => {
                            setSelectedConversation(conv);
                            setMobileViewingConversation(true);
                          }}
                          className={`w-full text-left p-2 border-b flex flex-col ${
                            selectedConversation?.user.id === conv.user.id
                              ? isDarkMode
                                ? 'bg-amber-500/10 border-amber-500'
                                : 'bg-amber-50 border-amber-300'
                              : isDarkMode
                              ? 'border-gray-700 hover:bg-gray-700'
                              : 'border-gray-200 hover:bg-gray-50'
                          }`}
                        >
                          <div className="flex items-start justify-between gap-2 min-w-0 w-full">
                            <div className="flex-1 min-w-0 text-left">
                              <div className="flex items-center gap-1.5">
                                <p className={`text-xs sm:text-sm font-medium truncate text-left ${isDarkMode ? 'text-amber-50' : 'text-amber-900'}`}>
                                  {conv.user?.first_name} {conv.user?.last_name}
                                </p>
                                {conv.user?.role && (
                                  <span className={`text-[10px] px-1.5 py-0.5 rounded-full font-medium flex-shrink-0 ${
                                    conv.user.role.toLowerCase() === 'admin' 
                                      ? isDarkMode ? 'bg-amber-500/20 text-amber-300' : 'bg-amber-100 text-amber-700'
                                      : isDarkMode ? 'bg-blue-500/20 text-blue-300' : 'bg-blue-100 text-blue-700'
                                  }`}>
                                    {conv.user.role.charAt(0).toUpperCase() + conv.user.role.slice(1)}
                                  </span>
                                )}
                              </div>
                              <p className={`text-xs truncate text-left ${isDarkMode ? 'text-gray-400' : 'text-gray-600'}`}>{conv.user?.email}</p>
                              {conv.last_message ? (
                                <div className="flex flex-col gap-1 mt-1 min-w-0 text-left">
                                  <p className={`text-xs break-words text-left ${isDarkMode ? 'text-gray-500' : 'text-gray-600'}`}>
                                    {conv.last_message.message.substring(0, 60)}{conv.last_message.message.length > 60 ? '...' : ''}
                                  </p>
                                </div>
                              ) : conv.is_new_contact && (
                                <p className={`text-xs mt-1 italic ${isDarkMode ? 'text-gray-500' : 'text-gray-400'}`}>
                                  No messages yet — tap to start a conversation
                                </p>
                              )}
                            </div>
                            <div className="flex flex-col items-end gap-1 flex-shrink-0 whitespace-nowrap">
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

              {/* Messages Area - Show on mobile when conversation selected, always on desktop */}
              {selectedConversation ? (
                <div className={`flex-1 flex flex-col min-h-0 min-w-0 w-full sm:w-auto h-[calc(100vh-3.5rem)] md:h-full border-0 md:border md:rounded-lg overflow-hidden ${isDarkMode ? 'bg-gray-800 border-gray-700' : 'bg-white border-gray-200'}`}>
                  {/* Header with User Info */}
                  <div className={`p-4 border-b flex-shrink-0 ${isDarkMode ? 'border-gray-700 bg-gray-900' : 'border-gray-200 bg-gray-50'}`}>
                    <div className="flex items-center justify-between">
                      <div>
                        <h3 className={`font-semibold ${isDarkMode ? 'text-amber-50' : 'text-amber-900'}`}>
                          {selectedConversation.user?.first_name} {selectedConversation.user?.last_name}
                        </h3>
                        <p className={`text-xs ${isDarkMode ? 'text-gray-400' : 'text-gray-600'}`}>{selectedConversation.user?.email}</p>
                      </div>
                      <button
                        onClick={() => setShowDeleteConfirm(true)}
                        className={`p-2 rounded-lg ${isDarkMode ? 'text-gray-400 hover:bg-red-500/10 hover:text-red-400' : 'text-gray-600 hover:bg-red-50 hover:text-red-600'}`}
                        title="Delete conversation"
                      >
                        <TrashIcon className="h-4 w-4" />
                      </button>
                    </div>
                  </div>

                  {/* Messages - Fixed height with scroll */}
                  <div className={`flex-1 overflow-y-auto p-3 sm:p-4 space-y-3 min-h-0`}>
                    {loadingMessages ? (
                      <div className="flex items-center justify-center py-12">
                        <div className="w-6 h-6 border-2 border-amber-500 border-t-transparent rounded-full animate-spin"></div>
                      </div>
                    ) : (
                      <MessageThread messages={messages} currentUserId={user?.id} isDarkMode={isDarkMode} />
                    )}
                    <div ref={messagesEndRef} />
                  </div>

                  {/* Message Limit Notice */}
                  {messageLimit?.has_limit && (
                    <div className={`flex-shrink-0 px-3 sm:px-4 py-2.5 border-t ${messageLimit.remaining_messages <= 0 
                      ? isDarkMode ? 'bg-red-900/30 border-red-800/50' : 'bg-red-50 border-red-200'
                      : isDarkMode ? 'bg-blue-900/30 border-blue-800/50' : 'bg-blue-50 border-blue-200'
                    }`}>
                      <div className="flex items-center gap-2">
                        <InformationCircleIcon className={`h-4 w-4 flex-shrink-0 ${messageLimit.remaining_messages <= 0
                          ? isDarkMode ? 'text-red-400' : 'text-red-500'
                          : isDarkMode ? 'text-blue-400' : 'text-blue-500'
                        }`} />
                        <p className={`text-xs sm:text-sm ${messageLimit.remaining_messages <= 0
                          ? isDarkMode ? 'text-red-300' : 'text-red-700'
                          : isDarkMode ? 'text-blue-300' : 'text-blue-700'
                        }`}>
                          {messageLimit.remaining_messages <= 0
                            ? `You have reached the ${messageLimit.message_limit || 2}-message limit. Please wait for the admin to reply before sending more.`
                            : `You can send ${messageLimit.remaining_messages} more message${messageLimit.remaining_messages === 1 ? '' : 's'} before the admin replies. (Limit: ${messageLimit.message_limit} messages)`
                          }
                        </p>
                      </div>
                    </div>
                  )}

                  {/* Send Error */}
                  {sendError && (
                    <div className={`flex-shrink-0 px-3 sm:px-4 py-2 ${isDarkMode ? 'bg-red-900/20' : 'bg-red-50'}`}>
                      <p className={`text-xs ${isDarkMode ? 'text-red-400' : 'text-red-600'}`}>{sendError}</p>
                    </div>
                  )}

                  {/* Send Message Form - Fixed at bottom */}
                  <form onSubmit={handleSendReply} className={`flex-shrink-0 p-2 sm:p-3 md:p-4 border-t ${isDarkMode ? 'border-gray-700 bg-gray-900' : 'border-gray-200 bg-gray-50'}`}>
                    <div className="flex gap-2">
                      <input
                        type="text"
                        value={replyMessage}
                        onChange={(e) => setReplyMessage(e.target.value)}
                        placeholder={messageLimit?.has_limit && messageLimit.remaining_messages <= 0 ? "Waiting for admin to reply..." : "Type your message..."}
                        className={`flex-1 px-3 py-2 text-sm border rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-500 ${isDarkMode ? 'bg-gray-700 border-gray-600 text-white placeholder-gray-400' : 'bg-white border-gray-300 text-gray-900 placeholder-gray-500'} ${messageLimit?.has_limit && messageLimit.remaining_messages <= 0 ? 'opacity-50' : ''}`}
                        disabled={loading || (messageLimit?.has_limit && messageLimit.remaining_messages <= 0)}
                      />
                      <button
                        type="submit"
                        disabled={loading || !replyMessage.trim() || (messageLimit?.has_limit && messageLimit.remaining_messages <= 0)}
                        className="px-3 py-1.5 bg-amber-600 text-white rounded-lg text-xs font-medium hover:bg-amber-700 disabled:opacity-50 disabled:cursor-not-allowed flex items-center"
                      >
                        {loading ? (
                          <div className="w-3 h-3 border-2 border-white border-t-transparent rounded-full"></div>
                        ) : (
                          <PaperAirplaneIcon className="h-4 w-4" />
                        )}
                      </button>
                    </div>
                  </form>
                </div>
              ) : (
                <div className={`hidden md:flex flex-1 items-center justify-center min-h-0 ${isDarkMode ? 'bg-gray-800 border-gray-700' : 'bg-white border-gray-200'} border rounded-lg ${isDarkMode ? 'text-gray-400' : 'text-gray-600'}`}>
                  <div className="text-center">
                    <EnvelopeIcon className="h-12 w-12 mx-auto mb-2 opacity-50" />
                    <p>Select a conversation to view messages</p>
                  </div>
                </div>
              )}
            </div>
          </main>
        </div>
      )}

      {/* Delete Confirmation Modal */}
      <DeleteConfirmationModal
        isOpen={showDeleteConfirm}
        onClose={() => setShowDeleteConfirm(false)}
        onConfirm={handleDeleteConversation}
        loading={loading}
        isDarkMode={isDarkMode}
        userName={selectedConversation ? `${selectedConversation.user?.first_name} ${selectedConversation.user?.last_name}` : ''}
      />
    </>
  );
};

export default MessageCenter;