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
  ExclamationTriangleIcon
} from '@heroicons/react/24/outline';

// Message Thread Component
const MessageThread = ({ messages, currentUserId, isDarkMode }) => {
  if (!messages || messages.length === 0) {
    return (
      <div className={`flex flex-col items-center justify-center py-12 ${isDarkMode ? 'text-gray-400' : 'text-gray-600'}`}>
        <EnvelopeIcon className="h-12 w-12 mb-2 opacity-50" />
        <p>No messages yet</p>
      </div>
    );
  }

  return (
    <div className="space-y-4">
      {messages.map((msg) => {
        const isOwn = msg.sender_id === currentUserId;
        return (
          <div key={msg.id} className={`flex ${isOwn ? 'justify-end' : 'justify-start'}`}>
            <div
              className={`max-w-xs px-4 py-2 rounded-lg ${
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
              <p className={`text-xs mt-1 ${isOwn ? 'text-amber-100' : isDarkMode ? 'text-gray-400' : 'text-gray-600'}`}>
                {new Date(msg.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}
              </p>
            </div>
          </div>
        );
      })}
    </div>
  );
};

// Reply Modal Component
const ReplyModal = ({ isOpen, onClose, conversation, onSend, loading, isDarkMode }) => {
  const [replyMessage, setReplyMessage] = useState('');
  const [subject, setSubject] = useState('');

  useEffect(() => {
    if (!isOpen) {
      setReplyMessage('');
      setSubject('');
    }
  }, [isOpen]);

  const handleSubmit = (e) => {
    e.preventDefault();
    if (replyMessage.trim()) {
      onSend({
        message: replyMessage,
        subject: subject || 'Reply',
        receiver_id: conversation?.user?.id,
        type: 'reply'
      });
      setReplyMessage('');
      setSubject('');
    }
  };

  if (!isOpen || !conversation) return null;

  return (
    <div className="fixed inset-0 bg-black bg-opacity-70 flex items-center justify-center z-50 p-4">
      <div className={`${isDarkMode ? 'bg-gray-800 border-amber-500/30' : 'bg-white border-amber-300/40'} border rounded-lg shadow-xl w-full max-w-md`}>
        <div className={`flex justify-between items-center p-4 border-b ${isDarkMode ? 'border-gray-700 bg-gray-900' : 'border-gray-200 bg-gray-50'}`}>
          <h3 className={`text-sm font-semibold ${isDarkMode ? 'text-amber-50' : 'text-amber-900'}`}>
            Reply to Message
          </h3>
          <button onClick={onClose} className={`${isDarkMode ? 'text-gray-400 hover:text-amber-400' : 'text-gray-600 hover:text-amber-600'} transition-colors`} disabled={loading}>
            <XMarkIcon className="h-5 w-5" />
          </button>
        </div>

        <form onSubmit={handleSubmit} className="p-4 space-y-4">
          <input
            type="text"
            placeholder="Subject (optional)"
            value={subject}
            onChange={(e) => setSubject(e.target.value)}
            className={`w-full px-3 py-2 text-sm border rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-500 ${isDarkMode ? 'bg-gray-700 border-gray-600 text-white placeholder-gray-400' : 'bg-white border-gray-300 text-gray-900 placeholder-gray-500'}`}
          />
          <textarea
            value={replyMessage}
            onChange={(e) => setReplyMessage(e.target.value)}
            rows="6"
            placeholder="Enter your message..."
            className={`w-full px-3 py-2 text-sm border rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-500 resize-none ${isDarkMode ? 'bg-gray-700 border-gray-600 text-white placeholder-gray-400' : 'bg-white border-gray-300 text-gray-900 placeholder-gray-500'}`}
            disabled={loading}
            required
          />

          <div className="flex justify-end space-x-3 pt-2">
            <button type="button" onClick={onClose} className={`px-4 py-2 border rounded-lg text-sm font-medium transition-colors ${isDarkMode ? 'border-gray-600 text-gray-300 hover:bg-gray-700' : 'border-gray-300 text-gray-700 hover:bg-gray-100'}`} disabled={loading}>
              Cancel
            </button>
            <button
              type="submit"
              className="px-4 py-2 bg-amber-600 text-white rounded-lg text-sm font-medium hover:bg-amber-700 transition-colors disabled:opacity-50 flex items-center"
              disabled={loading || !replyMessage.trim()}
            >
              {loading ? (
                <>
                  <div className="w-3 h-3 border-2 border-white border-t-transparent rounded-full animate-spin mr-2"></div>
                  Sending...
                </>
              ) : (
                <>
                  <PaperAirplaneIcon className="h-4 w-4 mr-1" />
                  Send
                </>
              )}
            </button>
          </div>
        </form>
      </div>
    </div>
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
              className={`px-4 py-2 border rounded-lg text-sm font-medium transition-colors ${isDarkMode ? 'border-gray-600 text-gray-300 hover:bg-gray-700' : 'border-gray-300 text-gray-700 hover:bg-gray-100'}`}
              disabled={loading}
            >
              Cancel
            </button>
            <button
              onClick={onConfirm}
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
  );
};

// Main Message Center Component
const MessageCenter = ({ isDarkMode = true, compact = false }) => {
  const { user } = useAuth();
  const { callApi } = useApi();

  const [conversations, setConversations] = useState([]);
  const [selectedConversation, setSelectedConversation] = useState(null);
  const [messages, setMessages] = useState([]);
  const [searchTerm, setSearchTerm] = useState('');
  const [showReplyModal, setShowReplyModal] = useState(false);
  const [showDeleteConfirm, setShowDeleteConfirm] = useState(false);
  const [showMobileSidebar, setShowMobileSidebar] = useState(false);
  const [loading, setLoading] = useState(false);
  const messagesEndRef = useRef(null);

  useEffect(() => {
    loadConversations();
  }, []);

  useEffect(() => {
    if (selectedConversation) {
      loadMessages(selectedConversation.user.id);
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
      const result = await callApi(() =>
        axios.get('/api/messages', { timeout: 10000 })
      );

      if (result.success) {
        let convs = Array.isArray(result.data?.data) ? result.data.data : (Array.isArray(result.data) ? result.data : []);
        
        // Deduplicate conversations
        const seen = new Set();
        convs = convs.filter(conv => {
          const userId = conv.user?.id;
          if (seen.has(userId)) return false;
          seen.add(userId);
          return true;
        });
        
        setConversations(convs);
      }
    } catch (error) {
      logger.error('Error loading conversations:', error);
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
      logger.error('Error loading messages:', error);
    }
  }, [callApi]);

  const handleSendReply = useCallback(async (data) => {
    setLoading(true);
    try {
      const result = await callApi(() =>
        axios.post('/api/messages', {
          receiver_id: data.receiver_id,
          message: data.message,
          subject: data.subject,
          type: data.type
        }, { timeout: 10000 })
      );

      if (result.success) {
        setShowReplyModal(false);
        await loadMessages(data.receiver_id);
        await loadConversations();
      }
    } catch (error) {
      logger.error('Error sending reply:', error);
    } finally {
      setLoading(false);
    }
  }, [callApi, loadMessages, loadConversations]);

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
        await loadConversations();
      }
    } catch (error) {
      logger.error('Error deleting conversation:', error);
    } finally {
      setLoading(false);
    }
  }, [callApi, selectedConversation, loadConversations]);

  const filteredConversations = useMemo(() => {
    if (!searchTerm.trim()) return conversations;
    
    return conversations.filter(conv => 
      `${conv.user?.first_name} ${conv.user?.last_name}`.toLowerCase().includes(searchTerm.toLowerCase()) ||
      conv.user?.email?.toLowerCase().includes(searchTerm.toLowerCase())
    );
  }, [conversations, searchTerm]);

  return (
    <>
      {compact ? (
        // Compact mode - for use within Dashboard
        <div className={`flex flex-col gap-4 h-[500px] ${isDarkMode ? 'bg-gray-800' : 'bg-white'} border rounded-lg overflow-hidden`}>
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
          <div className="flex flex-1 min-h-0 gap-3 px-4 pb-4">
            {/* Conversations Sidebar */}
            <div className={`w-48 flex-shrink-0 overflow-y-auto border rounded-lg ${isDarkMode ? 'bg-gray-700 border-gray-600' : 'bg-gray-50 border-gray-200'}`}>
              {filteredConversations.length === 0 ? (
                <div className={`flex items-center justify-center h-full ${isDarkMode ? 'text-gray-400' : 'text-gray-600'} text-xs text-center p-4`}>
                  <p>No conversations</p>
                </div>
              ) : (
                filteredConversations.map((conv) => (
                  <button
                    key={conv.user.id}
                    onClick={() => setSelectedConversation(conv)}
                    className={`w-full text-left p-2 text-sm border-b transition-colors ${
                      selectedConversation?.user.id === conv.user.id
                        ? isDarkMode
                          ? 'bg-amber-500/20 border-amber-500/40'
                          : 'bg-amber-100 border-amber-200'
                        : isDarkMode
                        ? 'border-gray-600 hover:bg-gray-600'
                        : 'border-gray-200 hover:bg-gray-100'
                    }`}
                  >
                    <p className={`text-xs font-medium truncate ${isDarkMode ? 'text-amber-50' : 'text-amber-900'}`}>
                      {conv.user?.first_name} {conv.user?.last_name}
                    </p>
                    <p className={`text-xs truncate ${isDarkMode ? 'text-gray-400' : 'text-gray-600'}`}>{conv.user?.email}</p>
                  </button>
                ))
              )}
            </div>

            {/* Messages Area */}
            {selectedConversation ? (
              <div className="flex-1 flex flex-col min-w-0 border rounded-lg overflow-hidden" style={{backgroundColor: isDarkMode ? '#1f2937' : '#ffffff'}}>
                {/* Header */}
                <div className={`p-2 border-b ${isDarkMode ? 'border-gray-600 bg-gray-800' : 'border-gray-200 bg-gray-50'}`}>
                  <h4 className={`text-xs font-semibold ${isDarkMode ? 'text-amber-50' : 'text-amber-900'}`}>
                    {selectedConversation.user?.first_name} {selectedConversation.user?.last_name}
                  </h4>
                </div>

                {/* Messages */}
                <div className={`flex-1 flex flex-col overflow-y-auto p-3 space-y-2`}>
                  <MessageThread messages={messages} currentUserId={user?.id} isDarkMode={isDarkMode} />
                  <div ref={messagesEndRef} />
                </div>

                {/* Send Button */}
                <div className={`p-2 border-t ${isDarkMode ? 'border-gray-600 bg-gray-800' : 'border-gray-200 bg-gray-50'}`}>
                  <button
                    onClick={() => setShowReplyModal(true)}
                    className="w-full px-3 py-1.5 bg-amber-600 text-white rounded text-xs font-medium hover:bg-amber-700 transition-colors flex items-center justify-center"
                  >
                    <PaperAirplaneIcon className="h-3 w-3 mr-1" />
                    Send
                  </button>
                </div>
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
        <div className={`min-h-screen ${isDarkMode ? 'bg-gray-900' : 'bg-gray-50'}`}>
          {/* Mobile Header */}
          <div className={`md:hidden fixed top-0 left-0 right-0 z-40 ${isDarkMode ? 'bg-gray-900 border-gray-700' : 'bg-white border-gray-200'} border-b p-4 flex items-center justify-between`}>
            <button
              onClick={() => setShowMobileSidebar(!showMobileSidebar)}
              className={`${isDarkMode ? 'text-gray-400 hover:text-amber-400' : 'text-gray-600 hover:text-amber-600'} transition-colors`}
            >
              <Bars3Icon className="h-6 w-6" />
            </button>
            <h1 className={`text-lg font-bold ${isDarkMode ? 'text-amber-50' : 'text-amber-900'}`}>Messages</h1>
            <div className="w-6" />
          </div>

          {/* Desktop Header */}
          <div className={`hidden md:block ${isDarkMode ? 'bg-gray-900 border-gray-700' : 'bg-white border-gray-200'} border-b p-6`}>
            <div className="flex items-center">
              <ChatBubbleLeftRightIcon className={`h-6 w-6 mr-2 ${isDarkMode ? 'text-amber-400' : 'text-amber-600'}`} />
              <div>
                <h1 className={`text-2xl font-bold ${isDarkMode ? 'text-amber-50' : 'text-amber-900'}`}>Messages</h1>
                <p className={isDarkMode ? 'text-gray-400 text-sm mt-1' : 'text-gray-600 text-sm mt-1'}>Communicate with staff and support</p>
              </div>
            </div>
          </div>

          {/* Mobile Sidebar Overlay */}
          {showMobileSidebar && (
            <div
              className="fixed inset-0 bg-black/50 z-30 md:hidden mt-14"
              onClick={() => setShowMobileSidebar(false)}
            />
          )}

          {/* Main Content */}
          <main className={`pt-16 md:pt-0 md:p-6`}>
            <div className={`flex gap-4 md:h-[600px] mx-auto`}>
              {/* Conversations Sidebar */}
              <div className={`${
                showMobileSidebar ? 'fixed' : 'hidden md:flex'
              } left-0 top-14 md:top-auto md:relative w-64 h-auto md:h-auto flex-col ${isDarkMode ? 'bg-gray-800 border-gray-700' : 'bg-white border-gray-200'} border rounded-lg overflow-hidden z-40`}>
                <div className={`p-4 border-b ${isDarkMode ? 'border-gray-700 bg-gray-900' : 'border-gray-200 bg-gray-50'}`}>
                  <h3 className={`font-semibold mb-3 ${isDarkMode ? 'text-amber-50' : 'text-amber-900'}`}>Conversations</h3>
                  <div className="relative">
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
                        onClick={() => {
                          setSelectedConversation(conv);
                          setShowMobileSidebar(false);
                        }}
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
                              <p className={`text-xs truncate line-clamp-1 mt-1 ${isDarkMode ? 'text-gray-500' : 'text-gray-600'}`}>
                                {conv.last_message.message}
                              </p>
                            )}
                          </div>
                          {conv.unread_count > 0 && <div className="bg-red-500 text-white text-xs font-bold px-2 py-0.5 rounded-full flex-shrink-0">{conv.unread_count}</div>}
                        </div>
                      </button>
                    ))
                  )}
                </div>
              </div>

              {/* Messages Area */}
              {selectedConversation ? (
                <div className={`flex-1 flex flex-col border rounded-lg overflow-hidden ${isDarkMode ? 'bg-gray-800 border-gray-700' : 'bg-white border-gray-200'}`}>
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
                        className={`p-2 rounded-lg transition-colors ${isDarkMode ? 'text-gray-400 hover:bg-red-500/10 hover:text-red-400' : 'text-gray-600 hover:bg-red-50 hover:text-red-600'}`}
                        title="Delete conversation"
                      >
                        <TrashIcon className="h-4 w-4" />
                      </button>
                    </div>
                  </div>

                  {/* Messages - Fixed height with scroll */}
                  <div className={`max-h-96 overflow-y-auto p-4 space-y-3`}>
                    <MessageThread messages={messages} currentUserId={user?.id} isDarkMode={isDarkMode} />
                    <div ref={messagesEndRef} />
                  </div>

                  {/* Send Message Button - Fixed at bottom */}
                  <div className={`flex-shrink-0 p-2 md:p-4 border-t ${isDarkMode ? 'border-gray-700 bg-gray-900' : 'border-gray-200 bg-gray-50'}`}>
                    <button
                      onClick={() => setShowReplyModal(true)}
                      className="w-full px-3 md:px-4 py-1.5 md:py-2 bg-amber-600 text-white rounded-lg text-xs md:text-sm font-medium hover:bg-amber-700 transition-colors flex items-center justify-center"
                    >
                      <PaperAirplaneIcon className="h-3 w-3 md:h-4 md:w-4 mr-1 md:mr-2" />
                      <span className="hidden md:inline">Send Message</span>
                      <span className="md:hidden">Send</span>
                    </button>
                  </div>
                </div>
              ) : (
                <div className={`hidden md:flex flex-1 items-center justify-center ${isDarkMode ? 'bg-gray-800 border-gray-700' : 'bg-white border-gray-200'} border rounded-lg ${isDarkMode ? 'text-gray-400' : 'text-gray-600'}`}>
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

      {/* Reply Modal */}
      <ReplyModal
        isOpen={showReplyModal}
        onClose={() => setShowReplyModal(false)}
        conversation={selectedConversation}
        onSend={handleSendReply}
        loading={loading}
        isDarkMode={isDarkMode}
      />

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
