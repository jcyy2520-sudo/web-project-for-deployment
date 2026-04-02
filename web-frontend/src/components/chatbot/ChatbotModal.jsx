import React, { useState, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import axios from 'axios';
import useChatbot from '../../hooks/useChatbot';
import ChatbotMessage from './ChatbotMessage';
import ChatbotInput from './ChatbotInput';

const ChatbotModal = ({ onClose, isDarkMode = true }) => {
  const navigate = useNavigate();
  const {
    messages,
    loading,
    error,
    sendMessage,
    clearHistory,
    messagesEndRef,
    setError,
    lastSuggestions,
    // Conversation management
    conversations,
    conversationsLoading,
    showHistory,
    loadConversations,
    startNewConversation,
    switchConversation,
    deleteConversation,
    toggleHistory,
    setShowHistory,
    conversationId,
    // Rate limiting
    rateLimitInfo,
    isRateLimited,
    rateLimitMessage,
    // Language and sentiment
    detectedLanguage,
    lastSentiment,
    // Feedback
    submitFeedback,
    // Proactive tips
    proactiveTips,
    // Retry
    lastFailedMessage,
    retryLastMessage,
    // Role context
    roleContext,
    // Error recovery
    errorRecovery,
    // Confirmation
    pendingConfirmation,
    confirmAction,
    denyAction,
  } = useChatbot();

  const [inputValue, setInputValue] = useState('');
  const [showClearConfirm, setShowClearConfirm] = useState(false);
  const [suggestedQuestions, setSuggestedQuestions] = useState([]);
  const [loadingSuggestions, setLoadingSuggestions] = useState(true);
  const [deleteConfirmId, setDeleteConfirmId] = useState(null);

  // Fallback questions for when API fails or returns empty
  const FALLBACK_QUESTIONS = [
    "What services do you offer and how much do they cost?",
    "Where is your office located?",
    "How do I book an appointment?",
    "What are your business hours?",
    "What documents do I need to bring?",
    "How do I register for an account?"
  ];

  // Format date for conversation list
  const formatConversationDate = (dateString) => {
    if (!dateString) return '';
    const date = new Date(dateString);
    const now = new Date();
    const diffMs = now - date;
    const diffMins = Math.floor(diffMs / 60000);
    const diffHours = Math.floor(diffMs / 3600000);
    const diffDays = Math.floor(diffMs / 86400000);

    if (diffMins < 1) return 'Just now';
    if (diffMins < 60) return `${diffMins}m ago`;
    if (diffHours < 24) return `${diffHours}h ago`;
    if (diffDays < 7) return `${diffDays}d ago`;
    return date.toLocaleDateString();
  };

  // Handle starting a new conversation
  const handleNewConversation = async () => {
    await startNewConversation();
    setShowHistory(false);
  };

  // Handle switching to a conversation
  const handleSwitchConversation = async (convId) => {
    await switchConversation(convId);
    setShowHistory(false);
  };

  // Handle deleting a conversation
  const handleDeleteConversation = async (convId) => {
    await deleteConversation(convId);
    setDeleteConfirmId(null);
  };

  // State for dynamic updates (system notifications for admin/cashier)
  const [dynamicUpdates, setDynamicUpdates] = useState([]);
  const [userRole, setUserRole] = useState('guest');

  // Update userRole when roleContext changes from backend responses
  useEffect(() => {
    if (roleContext?.role) {
      setUserRole(roleContext.role);
    }
  }, [roleContext?.role]);

  // Fetch suggested questions on mount
  useEffect(() => {
    fetchSuggestedQuestions();
  }, []);

  // Listen for suggestion click events from ChatbotMessage
  useEffect(() => {
    const handleSuggestionClick = (event) => {
      const suggestion = event.detail;
      if (suggestion) {
        sendMessage(suggestion);
      }
    };

    window.addEventListener('chatbot-suggestion', handleSuggestionClick);
    return () => window.removeEventListener('chatbot-suggestion', handleSuggestionClick);
  }, [sendMessage]);

  // Listen for navigation events from ChatbotMessage
  useEffect(() => {
    const handleNavigation = (event) => {
      const { route } = event.detail || {};
      if (route) {
        // Security: validate route is a safe internal path (not external URL or javascript:)
        const isSafeRoute = typeof route === 'string' && route.startsWith('/') && !route.startsWith('//');
        if (isSafeRoute) {
          onClose();
          navigate(route);
        }
      }
    };

    window.addEventListener('chatbot-navigate', handleNavigation);
    return () => window.removeEventListener('chatbot-navigate', handleNavigation);
  }, [onClose]);

  // Escape key to close modal
  useEffect(() => {
    const handleEscape = (e) => {
      if (e.key === 'Escape') {
        onClose();
      }
    };
    window.addEventListener('keydown', handleEscape);
    return () => window.removeEventListener('keydown', handleEscape);
  }, [onClose]);

  const fetchSuggestedQuestions = async () => {
    try {
      setLoadingSuggestions(true);
      const response = await axios.get('/api/chatbot/suggested-questions');
      
      // Validate response structure
      if (!response.data) {
        setSuggestedQuestions(FALLBACK_QUESTIONS);
        return;
      }

      // Check success flag and data array
      if (response.data.success === false) {
        setSuggestedQuestions(FALLBACK_QUESTIONS);
        return;
      }

      // Validate data is an array
      if (!Array.isArray(response.data.data)) {
        setSuggestedQuestions(FALLBACK_QUESTIONS);
        return;
      }

      // Use API data if available, otherwise fallback
      if (response.data.data.length > 0) {
        setSuggestedQuestions(response.data.data);
      } else {
        setSuggestedQuestions(FALLBACK_QUESTIONS);
      }

      // Set dynamic updates if available (for admin/cashier)
      if (Array.isArray(response.data.dynamic_updates)) {
        setDynamicUpdates(response.data.dynamic_updates);
      }

      // Set user role
      if (response.data.role) {
        setUserRole(response.data.role);
      }
    } catch (err) {
      console.error('Failed to fetch suggested questions:', err);
      // Use fallback questions on error
      setSuggestedQuestions(FALLBACK_QUESTIONS);
    } finally {
      setLoadingSuggestions(false);
    }
  };

  // Handle clicking on a dynamic update
  const safeNavigate = (route) => {
    if (typeof route === 'string' && route.startsWith('/') && !route.startsWith('//')) {
      onClose();
      navigate(route);
    }
  };

  const handleDynamicUpdateClick = (update) => {
    if (update.route) {
      safeNavigate(update.route);
    } else if (update.text) {
      sendMessage(update.text);
    }
  };

  useEffect(() => {
    messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' });
  }, [messages, messagesEndRef]);

  const handleSendMessage = () => {
    if (inputValue.trim()) {
      sendMessage(inputValue);
      setInputValue('');
    }
  };

  const handleSuggestedQuestion = (question) => {
    sendMessage(question);
    setInputValue('');
  };

  // Using onKeyDown instead of deprecated onKeyPress for better compatibility
  const handleKeyDown = (e) => {
    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault();
      e.stopPropagation();
      handleSendMessage();
    }
  };

  return (
    <>
      {/* Modal Backdrop */}
      <div
        className="fixed inset-0 bg-black/20 backdrop-blur-sm z-[9998] transition-all"
        onClick={onClose}
      />

      {/* Modal - Responsive: full-screen on mobile, positioned on desktop */}
      <div className={`fixed bottom-20 left-3 right-3 h-[65vh] max-h-[480px] w-auto sm:inset-auto sm:bottom-24 sm:right-6 sm:left-auto sm:h-[600px] sm:w-[400px] sm:max-w-[calc(100vw-3rem)] sm:max-h-[calc(100vh-8rem)] rounded-xl shadow-2xl flex flex-col z-[9999] overflow-hidden border ${isDarkMode ? 'bg-gray-900 border-amber-500/30' : 'bg-white border-slate-200'}`}>
        {/* Header */}
        <div className={`px-5 py-4 flex justify-between items-center flex-shrink-0 ${isDarkMode ? 'bg-gray-900 border-b border-amber-500/20 text-gray-100' : 'bg-slate-50 border-b border-slate-200 text-slate-900'}`}>
          <div className="flex items-center gap-3">
            <div className="w-10 h-10 rounded-full bg-amber-500/10 border border-amber-500/30 flex items-center justify-center">
              <svg className="w-5 h-5 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z" />
              </svg>
            </div>
            <div>
              <h2 className={`font-semibold text-base ${isDarkMode ? 'text-gray-100' : 'text-slate-900'}`}>Chat Assistant</h2>
              <p className={`text-xs ${isDarkMode ? 'text-gray-400' : 'text-slate-600'}`}>
                {roleContext?.roleDisplay
                  ? `Assisting as ${roleContext.roleDisplay}`
                  : (userRole && userRole !== 'guest')
                    ? `Logged in as ${userRole}`
                    : 'AI-powered support'
                }
              </p>
            </div>
          </div>
          <div className="flex gap-1">
            {/* New Chat Button */}
            <button
              onClick={handleNewConversation}
              className={`p-2 rounded-lg transition-all ${isDarkMode ? 'hover:bg-amber-500/10 hover:text-amber-500 text-gray-400' : 'hover:bg-slate-50 hover:text-amber-600 text-slate-600'}`}
              title="New conversation"
              aria-label="Start new conversation"
            >
              <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4v16m8-8H4" />
              </svg>
            </button>
            {/* History Button */}
            <button
              onClick={toggleHistory}
              className={`p-2 rounded-lg transition-all ${isDarkMode ? (showHistory ? 'bg-amber-500/20 text-amber-500' : 'text-gray-400 hover:bg-amber-500/10 hover:text-amber-500') : (showHistory ? 'bg-slate-200 text-amber-600' : 'text-slate-600 hover:bg-slate-50 hover:text-amber-600')}`}
              title="Chat history"
              aria-label="View chat history"
            >
              <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
            </button>
            <button
              onClick={() => setShowClearConfirm(true)}
              className={`p-2 rounded-lg transition-all ${isDarkMode ? 'hover:bg-amber-500/10 hover:text-amber-500 text-gray-400' : 'hover:bg-slate-50 hover:text-amber-600 text-slate-600'}`}
              title="Clear history"
              aria-label="Clear chat history"
            >
              <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
              </svg>
            </button>
            <button
              onClick={onClose}
              className={`p-2 rounded-lg transition-all ${isDarkMode ? 'hover:bg-amber-500/10 hover:text-amber-500 text-gray-400' : 'hover:bg-slate-50 hover:text-amber-600 text-slate-600'}`}
              title="Close chat"
              aria-label="Close chat"
            >
              <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>
        </div>

        {/* Rate Limit Warning Banner */}
        {isRateLimited && rateLimitInfo.mustStartNew && (
          <div className={`${isDarkMode ? 'px-4 py-3 bg-amber-500/20 border-b border-amber-500/30 flex-shrink-0' : 'px-4 py-3 bg-slate-50 border-b border-slate-200 flex-shrink-0'}`}>
            <div className="flex items-center justify-between">
              <div className="flex items-center gap-2">
                <svg className="w-5 h-5 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
                <div>
                  <p className={`text-sm font-medium ${isDarkMode ? 'text-amber-300' : 'text-amber-700'}`}>Message Limit Reached</p>
                  <p className={`text-xs ${isDarkMode ? 'text-amber-400/80' : 'text-amber-600'}`}>{rateLimitMessage || 'Start a new conversation to continue chatting.'}</p>
                </div>
              </div>
              <button
                onClick={handleNewConversation}
                className={`px-3 py-1.5 text-sm font-medium rounded-lg hover:opacity-95 transition-all ${isDarkMode ? 'bg-amber-500 text-gray-900 hover:bg-amber-400' : 'bg-amber-600 text-white hover:bg-slate-500'}`}
              >
                New Chat
              </button>
            </div>
          </div>
        )}

        {/* Message Counter (shows remaining messages) */}
        {!isRateLimited && rateLimitInfo.remaining <= 5 && rateLimitInfo.remaining > 0 && (
          <div className={`${isDarkMode ? 'px-4 py-2 bg-gray-800/50 border-b border-amber-500/10 flex-shrink-0' : 'px-4 py-2 bg-white/80 border-b border-slate-200 flex-shrink-0'}`}>
            <div className="flex items-center justify-between">
              <span className="text-xs text-gray-400">
                {rateLimitInfo.remaining} message{rateLimitInfo.remaining !== 1 ? 's' : ''} remaining in this conversation
              </span>
              <button
                onClick={handleNewConversation}
                className={`text-xs transition-colors ${isDarkMode ? 'text-amber-400 hover:text-amber-300' : 'text-amber-600 hover:text-slate-500'}`}
              >
                Start new
              </button>
            </div>
          </div>
        )}

        {/* Conversation History Panel (Slide-in sidebar) */}

        {/* Pending Items Banner — shows role-based action items */}
        {roleContext?.pendingItems?.length > 0 && roleContext.pendingItems.some(i => i.count > 0) && !showHistory && (
          <div className={`px-4 py-2 flex-shrink-0 border-b ${isDarkMode ? 'bg-amber-500/5 border-amber-500/10' : 'bg-amber-50 border-amber-100'}`}>
            <div className="flex items-center gap-2 overflow-x-auto scrollbar-none">
              <span className={`text-[10px] uppercase tracking-wide whitespace-nowrap ${isDarkMode ? 'text-amber-400/70' : 'text-amber-600'}`}>📋 Pending:</span>
              {roleContext.pendingItems.filter(i => i.count > 0).map((item, idx) => (
                <button
                  key={`hdr-pi-${idx}`}
                  onClick={() => {
                    if (item.route) {
                      safeNavigate(item.route);
                    } else {
                      sendMessage(`Show me ${item.label?.toLowerCase()}`);
                    }
                  }}
                  className={`text-[11px] px-2 py-0.5 rounded-full whitespace-nowrap flex items-center gap-1 transition-all ${
                    isDarkMode
                      ? 'bg-gray-800 border border-amber-500/20 text-gray-300 hover:border-amber-500/40 hover:text-amber-300'
                      : 'bg-white border border-amber-200 text-slate-600 hover:bg-amber-50'
                  }`}
                >
                  <span className={`font-bold ${item.count >= 5 ? (isDarkMode ? 'text-red-400' : 'text-red-600') : (isDarkMode ? 'text-amber-400' : 'text-amber-600')}`}>{item.count}</span>
                  {item.label}
                </button>
              ))}
            </div>
          </div>
        )}

        {showHistory && (
          <div className={`absolute inset-0 top-[73px] z-10 flex flex-col border-t ${isDarkMode ? 'bg-gray-900 border-amber-500/20' : 'bg-white border-slate-200'}`}>
            {/* History Header */}
            <div className={`px-4 py-3 border-b ${isDarkMode ? 'border-amber-500/20' : 'border-slate-200'} flex items-center justify-between`}>
              <h3 className={`font-medium ${isDarkMode ? 'text-gray-200' : 'text-slate-900'}`}>Conversation History</h3>
              <button
                onClick={() => setShowHistory(false)}
                className={`p-1 hover:bg-amber-500/10 hover:text-amber-500 ${isDarkMode ? 'text-gray-400' : 'text-slate-600'} rounded transition-all`}
              >
                <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                </svg>
              </button>
            </div>
            
            {/* New Conversation Button */}
            <div className={`px-4 py-2 border-b ${isDarkMode ? 'border-amber-500/10' : 'border-slate-200'}`}>
              <button
                onClick={handleNewConversation}
                className={`w-full flex items-center justify-center gap-2 px-4 py-2.5 rounded-lg transition-all ${isDarkMode ? 'bg-amber-500/10 border border-amber-500/30 text-amber-400 hover:bg-amber-500/20 hover:border-amber-500/50' : 'bg-slate-50 border border-slate-200 text-amber-600 hover:bg-slate-200 hover:border-slate-300'}`}
              >
                <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4v16m8-8H4" />
                </svg>
                New Conversation
              </button>
            </div>
            
            {/* Conversations List */}
            <div className="flex-1 overflow-y-auto">
              {conversationsLoading ? (
                <div className="flex items-center justify-center py-8">
                  <div className="flex space-x-1">
                    <div className={`w-2 h-2 rounded-full animate-bounce ${isDarkMode ? 'bg-amber-500' : 'bg-slate-500'}`} />
                    <div className={`w-2 h-2 rounded-full animate-bounce ${isDarkMode ? 'bg-amber-500' : 'bg-slate-500'}`} style={{ animationDelay: '0.1s' }} />
                    <div className={`w-2 h-2 rounded-full animate-bounce ${isDarkMode ? 'bg-amber-500' : 'bg-slate-500'}`} style={{ animationDelay: '0.2s' }} />
                  </div>
                </div>
              ) : conversations.length === 0 ? (
                <div className="flex flex-col items-center justify-center py-8 px-4 text-center">
                  <svg className={`w-12 h-12 mb-3 ${isDarkMode ? 'text-gray-600' : 'text-amber-500'}`} fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                  </svg>
                  <p className={`${isDarkMode ? 'text-gray-500' : 'text-slate-600'} text-sm`}>No conversation history yet</p>
                  <p className={`${isDarkMode ? 'text-gray-600' : 'text-slate-500'} text-xs mt-1`}>Start a new conversation to begin</p>
                </div>
              ) : (
                <div className={`divide-y ${isDarkMode ? 'divide-gray-800' : 'divide-gray-100'}`}>
                  {conversations.map((conv) => (
                    <div
                      key={conv.conversation_id}
                      className={`relative group ${conversationId === conv.conversation_id ? (isDarkMode ? 'bg-amber-500/10' : 'bg-slate-50') : (isDarkMode ? 'hover:bg-gray-800/50' : 'hover:bg-gray-50')}`}
                    >
                      <button
                        onClick={() => handleSwitchConversation(conv.conversation_id)}
                        className="w-full text-left px-4 py-3"
                      >
                        <div className="flex items-start justify-between gap-2">
                          <div className="flex-1 min-w-0">
                            <p className={`text-sm font-medium truncate ${conversationId === conv.conversation_id ? (isDarkMode ? 'text-amber-400' : 'text-amber-600') : (isDarkMode ? 'text-gray-300' : 'text-slate-700')}`}>
                              {conv.title}
                            </p>
                            {conv.last_message && (
                              <p className={`text-xs ${isDarkMode ? 'text-gray-500' : 'text-slate-600'} truncate mt-0.5`}>
                                {conv.last_message_role === 'assistant' ? 'AI: ' : 'You: '}{conv.last_message}
                              </p>
                            )}
                            <div className="flex items-center gap-2 mt-1">
                              <span className={`text-xs ${isDarkMode ? 'text-gray-600' : 'text-slate-500'}`}>
                                {formatConversationDate(conv.updated_at)}
                              </span>
                              <span className={`text-xs ${isDarkMode ? 'text-gray-600' : 'text-slate-500'}`}>•</span>
                              <span className={`text-xs ${isDarkMode ? 'text-gray-600' : 'text-slate-500'}`}>
                                {conv.message_count} messages
                              </span>
                              {conv.is_at_limit && (
                                <>
                                  <span className={`text-xs ${isDarkMode ? 'text-gray-600' : 'text-slate-500'}`}>•</span>
                                  <span className={`text-xs font-medium ${isDarkMode ? 'text-amber-400' : 'text-amber-600'}`}>
                                    Limit reached
                                  </span>
                                </>
                              )}
                            </div>
                          </div>
                            {conversationId === conv.conversation_id && (
                            <div className={`w-2 h-2 rounded-full flex-shrink-0 mt-1.5 ${isDarkMode ? 'bg-amber-500' : 'bg-amber-600'}`} />
                          )}
                        </div>
                      </button>
                      
                      {/* Delete button (shows on hover) */}
                      <button
                        onClick={(e) => {
                          e.stopPropagation();
                          setDeleteConfirmId(conv.conversation_id);
                        }}
                        className={`absolute right-2 top-1/2 -translate-y-1/2 p-1.5 opacity-0 group-hover:opacity-100 hover:bg-red-500/20 hover:text-red-400 ${isDarkMode ? 'text-gray-500' : 'text-slate-600'} rounded transition-all`}
                        title="Delete conversation"
                      >
                        <svg className="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                        </svg>
                      </button>
                    </div>
                  ))}
                </div>
              )}
            </div>
          </div>
        )}

        {/* Messages Container */}
        <div className={`flex-1 overflow-y-auto px-5 py-4 space-y-4 ${isDarkMode ? 'bg-gray-800/30' : 'bg-gray-50'}`}>
          {messages.length === 0 ? (
            <div className="flex flex-col items-center justify-center h-full text-center">
              <div className="w-16 h-16 rounded-full bg-amber-500/10 border border-amber-500/30 flex items-center justify-center mb-4">
                <svg className="w-8 h-8 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                </svg>
              </div>
              <h3 className={`text-base font-medium ${isDarkMode ? 'text-gray-200' : 'text-slate-900'} mb-2`}>Start a conversation</h3>
              <p className={`text-sm ${isDarkMode ? 'text-gray-400' : 'text-slate-700'} mb-6`}>Ask me about services, location, hours, or anything else!</p>

              {/* Proactive Tips Section */}
              {proactiveTips && proactiveTips.length > 0 && (
                <div className="w-full space-y-2 mb-4">
                  <p className={`text-xs font-medium uppercase tracking-wide mb-2 ${isDarkMode ? 'text-amber-400' : 'text-amber-600'}`}>💡 For You</p>
                  {proactiveTips.slice(0, 3).map((tip, idx) => (
                    <button
                      key={`tip-${idx}`}
                      onClick={() => {
                        if (tip.action?.route) {
                          safeNavigate(tip.action.route);
                        } else if (tip.action?.message) {
                          sendMessage(tip.action.message);
                        }
                      }}
                      className={`w-full text-left px-4 py-3 rounded-lg transition-all text-sm border ${
                        tip.type === 'warning'
                          ? (isDarkMode ? 'bg-amber-500/10 border-amber-500/30 hover:bg-amber-500/20' : 'bg-amber-50 border-amber-200')
                          : tip.type === 'reminder'
                          ? (isDarkMode ? 'bg-blue-500/10 border-blue-500/30 hover:bg-blue-500/20' : 'bg-blue-50 border-blue-200')
                          : (isDarkMode ? 'bg-gray-900/50 border-amber-500/20 hover:bg-gray-900' : 'bg-gray-50 border-slate-100')
                      }`}
                    >
                      <span className="flex items-center gap-2">
                        <span>{tip.icon || '💡'}</span>
                        <span className={isDarkMode ? 'text-gray-300' : 'text-slate-700'}>{tip.message}</span>
                      </span>
                      {tip.action?.label && (
                        <span className={`text-xs mt-1 block ${isDarkMode ? 'text-amber-400' : 'text-amber-600'}`}>{tip.action.label} →</span>
                      )}
                    </button>
                  ))}
                </div>
              )}

              {/* Suggested Questions (dynamic from assistant or default suggestions) */}
              {lastSuggestions && Array.isArray(lastSuggestions) && lastSuggestions.length > 0 ? (
                <div className="w-full space-y-2 mb-4">
                  {lastSuggestions.map((question, idx) => (
                    <button
                      key={`dyn-${idx}`}
                      onClick={() => handleSuggestedQuestion(question)}
                      className={`w-full text-left px-4 py-3 rounded-lg transition-all text-sm ${isDarkMode ? 'bg-gray-900/50 border border-amber-500/20 text-gray-300 hover:bg-gray-900 hover:border-amber-500/40 hover:text-amber-400' : 'bg-gray-50 border border-slate-100 text-slate-800 hover:bg-white'}`}
                    >
                      <span className="flex items-center gap-2">
                        <svg className="w-4 h-4 text-amber-500/50 group-hover:text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5l7 7-7 7" />
                        </svg>
                        {question}
                      </span>
                    </button>
                  ))}
                </div>
              ) : loadingSuggestions ? (
                  <div className="w-full">
                  <div className={`flex items-center justify-center gap-2 ${isDarkMode ? 'text-gray-400' : 'text-slate-600'}`}>
                    <div className={`w-2 h-2 rounded-full animate-bounce ${isDarkMode ? 'bg-amber-500' : 'bg-slate-500'}`} />
                    <div className={`w-2 h-2 rounded-full animate-bounce ${isDarkMode ? 'bg-amber-500' : 'bg-slate-500'}`} style={{ animationDelay: '0.1s' }} />
                    <div className={`w-2 h-2 rounded-full animate-bounce ${isDarkMode ? 'bg-amber-500' : 'bg-slate-500'}`} style={{ animationDelay: '0.2s' }} />
                  </div>
                </div>
              ) : (
                <div className="w-full space-y-4">
                  {/* Dynamic Updates Section for Admin/Cashier */}
                  {dynamicUpdates && dynamicUpdates.length > 0 && (
                    <div className="space-y-2">
                      <p className="text-xs text-amber-400 font-medium uppercase tracking-wide mb-2">📋 Updates</p>
                          {dynamicUpdates.slice(0, 3).map((update, index) => (
                        <button
                          key={`update-${index}`}
                          onClick={() => handleDynamicUpdateClick(update)}
                          className={`w-full text-left px-4 py-3 rounded-lg border transition-all text-sm group ${
                                update.priority === 'high'
                                  ? (isDarkMode ? 'bg-red-500/10 border-red-500/30 hover:bg-red-500/20 hover:border-red-500/50' : 'bg-red-50 border-red-100 hover:bg-red-100')
                                  : update.priority === 'medium'
                                  ? (isDarkMode ? 'bg-amber-500/10 border-amber-500/30 hover:bg-amber-500/20 hover:border-amber-500/50' : 'bg-slate-50 border-slate-200 hover:bg-slate-200')
                                  : (isDarkMode ? 'bg-gray-900/50 border-gray-700 hover:bg-gray-900 hover:border-gray-600' : 'bg-white border-slate-200 hover:bg-gray-50')
                              }`}
                        >
                          <span className="flex items-center justify-between gap-2">
                              <span className={`${update.priority === 'high' ? (isDarkMode ? 'text-red-300' : 'text-red-700') : update.priority === 'medium' ? (isDarkMode ? 'text-amber-300' : 'text-amber-600') : (isDarkMode ? 'text-gray-300' : 'text-slate-700')}`}>
                              {update.priority === 'high' && '⚠️ '}
                              {update.priority === 'medium' && '📌 '}
                              {update.text}
                            </span>
                            {update.route && (
                              <svg className={`w-4 h-4 ${isDarkMode ? 'text-gray-500 group-hover:text-gray-300' : 'text-slate-500 group-hover:text-slate-400'}`} fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                              </svg>
                            )}
                          </span>
                        </button>
                      ))}
                    </div>
                  )}

                  {/* Regular Suggested Questions */}
                  {Array.isArray(suggestedQuestions) && suggestedQuestions.length > 0 && (
                    <div className="space-y-2">
                      {dynamicUpdates && dynamicUpdates.length > 0 && (
                        <p className="text-xs text-gray-500 font-medium uppercase tracking-wide mb-2">💬 Questions</p>
                      )}
                      {suggestedQuestions.map((question, index) => (
                        <button
                          key={index}
                          onClick={() => handleSuggestedQuestion(question)}
                          className={`w-full text-left px-4 py-3 rounded-lg transition-all text-sm ${isDarkMode ? 'bg-gray-900/50 border border-amber-500/20 text-gray-300 hover:bg-gray-900 hover:border-amber-500/40 hover:text-amber-400' : 'bg-gray-50 border border-slate-100 text-slate-800 hover:bg-white'}`}
                        >
                          <span className="flex items-center gap-2">
                            <svg className="w-4 h-4 text-amber-500/50 group-hover:text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5l7 7-7 7" />
                            </svg>
                            {question}
                          </span>
                        </button>
                      ))}
                    </div>
                  )}

                  {(!suggestedQuestions || suggestedQuestions.length === 0) && (!dynamicUpdates || dynamicUpdates.length === 0) && (
                    <p className={`${isDarkMode ? 'text-gray-500' : 'text-slate-600'} text-sm`}>Feel free to ask me anything!</p>
                  )}
                </div>
              )}
            </div>
          ) : (
            <>
              {messages.map((message, index) => (
                <ChatbotMessage
                  key={message.id}
                  message={message}
                  isDarkMode={isDarkMode}
                  onFeedback={submitFeedback}
                  isLastMessage={index === messages.length - 1}
                />
              ))}
              {loading && (
                <div className="flex justify-start">
                  <div className={`rounded-xl px-4 py-3 flex items-center gap-2 ${isDarkMode ? 'bg-gray-900 border border-amber-500/20' : 'bg-white border border-slate-200'}`}>
                    <div className="flex space-x-1">
                      <div className={`w-2 h-2 rounded-full animate-bounce ${isDarkMode ? 'bg-amber-500' : 'bg-amber-600'}`} />
                      <div className={`w-2 h-2 rounded-full animate-bounce ${isDarkMode ? 'bg-amber-500' : 'bg-amber-600'}`} style={{ animationDelay: '0.1s' }} />
                      <div className={`w-2 h-2 rounded-full animate-bounce ${isDarkMode ? 'bg-amber-500' : 'bg-amber-600'}`} style={{ animationDelay: '0.2s' }} />
                    </div>
                    <span className={`text-xs ${isDarkMode ? 'text-gray-400' : 'text-slate-500'}`}>
                      {pendingConfirmation ? 'Processing action...' : 'Thinking...'}
                    </span>
                  </div>
                </div>
              )}
              <div ref={messagesEndRef} />
            </>
          )}
        </div>

        {/* Error Message with Recovery Guidance */}
        {error && (
          <div className={`px-5 py-3 flex-shrink-0 border-b ${isDarkMode ? 'bg-red-500/10 border-red-500/20' : 'bg-red-50 border-red-100'}`}>
            <div className="flex items-center justify-between">
              <div className="flex items-center gap-2 flex-1 min-w-0">
                <svg className={`w-4 h-4 flex-shrink-0 ${isDarkMode ? 'text-red-400' : 'text-red-500'}`} fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <p className={`text-sm truncate ${isDarkMode ? 'text-red-300' : 'text-red-700'}`}>{error}</p>
              </div>
              <div className="flex items-center gap-1 flex-shrink-0">
                {lastFailedMessage && (
                  <button
                    onClick={() => {
                      setError(null);
                      retryLastMessage();
                    }}
                    className={`text-xs px-2 py-1 rounded transition-colors ${isDarkMode ? 'bg-red-500/20 text-red-300 hover:bg-red-500/30' : 'bg-red-100 text-red-700 hover:bg-red-200'}`}
                    title="Retry last message"
                  >
                    ↻ Retry
                  </button>
                )}
                <button
                  onClick={() => setError(null)}
                  className={`transition-colors p-1 ${isDarkMode ? 'text-red-400 hover:text-red-300' : 'text-red-500 hover:text-red-700'}`}
                >
                  <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                  </svg>
                </button>
              </div>
            </div>

            {/* Recovery Steps */}
            {errorRecovery?.steps?.length > 0 && (
              <div className={`mt-2 pl-6 space-y-1 ${isDarkMode ? 'text-red-300/70' : 'text-red-600'}`}>
                {errorRecovery.steps.map((step, idx) => (
                  <p key={idx} className="text-xs flex items-start gap-1.5">
                    <span className="mt-0.5 flex-shrink-0">{idx === 0 ? '💡' : '•'}</span>
                    {step}
                  </p>
                ))}
              </div>
            )}

            {/* Recovery Suggestions */}
            {errorRecovery?.suggestions?.length > 0 && (
              <div className="mt-2 pl-6 flex flex-wrap gap-1.5">
                {errorRecovery.suggestions.map((suggestion, idx) => (
                  <button
                    key={idx}
                    onClick={() => {
                      setError(null);
                      sendMessage(suggestion);
                    }}
                    className={`text-xs px-2.5 py-1 rounded-full transition-all ${
                      isDarkMode
                        ? 'bg-gray-800 border border-red-500/20 text-gray-300 hover:border-amber-500/40 hover:text-amber-300'
                        : 'bg-white border border-red-200 text-slate-600 hover:border-amber-300 hover:text-amber-700'
                    }`}
                  >
                    {suggestion}
                  </button>
                ))}
              </div>
            )}
          </div>
        )}

        {/* Quick Actions Bar — role-based shortcut buttons */}
        {roleContext?.quickActions?.length > 0 && messages.length > 0 && !showHistory && (
          <div className={`px-4 py-2 flex-shrink-0 border-t ${isDarkMode ? 'bg-gray-900/80 border-amber-500/10' : 'bg-gray-50/80 border-slate-100'}`}>
            <div className="flex gap-1.5 overflow-x-auto scrollbar-none pb-0.5">
              {roleContext.quickActions.slice(0, 6).map((action, idx) => (
                <button
                  key={`qa-bar-${idx}`}
                  onClick={() => {
                    if (action.route) {
                      safeNavigate(action.route);
                    } else if (action.message || action.command) {
                      sendMessage(action.message || action.command);
                    }
                  }}
                  className={`text-[11px] px-2.5 py-1.5 rounded-lg whitespace-nowrap flex items-center gap-1 transition-all flex-shrink-0 ${
                    isDarkMode
                      ? 'bg-gray-800 border border-amber-500/15 text-gray-400 hover:bg-amber-500/10 hover:border-amber-500/30 hover:text-amber-400'
                      : 'bg-white border border-slate-200 text-slate-600 hover:bg-amber-50 hover:border-amber-300 hover:text-amber-700'
                  }`}
                  title={action.label}
                >
                  {action.icon && <span>{action.icon}</span>}
                  <span>{action.label}</span>
                </button>
              ))}
            </div>
          </div>
        )}

        {/* Confirmation Banner — shown when chatbot awaits user confirmation for a destructive action */}
        {pendingConfirmation && (
          <div className={`px-5 py-3 flex-shrink-0 border-t ${isDarkMode ? 'bg-amber-500/5 border-amber-500/20' : 'bg-amber-50 border-amber-100'}`}>
            <div className="flex items-center gap-2 mb-2">
              <svg className={`w-4 h-4 flex-shrink-0 ${isDarkMode ? 'text-amber-400' : 'text-amber-600'}`} fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
              </svg>
              <div>
                <p className={`text-sm font-medium ${isDarkMode ? 'text-amber-300' : 'text-amber-700'}`}>Action requires your confirmation</p>
                {pendingConfirmation.tool && (
                  <p className={`text-xs ${isDarkMode ? 'text-gray-400' : 'text-slate-500'}`}>
                    {pendingConfirmation.tool.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase())}
                  </p>
                )}
              </div>
            </div>
            <div className="flex gap-2">
              <button
                onClick={() => confirmAction()}
                disabled={loading}
                className={`flex-1 px-4 py-2 text-sm font-medium rounded-lg transition-all ${
                  loading
                    ? 'opacity-50 cursor-not-allowed'
                    : isDarkMode
                      ? 'bg-green-500/20 border border-green-500/30 text-green-300 hover:bg-green-500/30 hover:border-green-500/50'
                      : 'bg-green-50 border border-green-200 text-green-700 hover:bg-green-100'
                }`}
              >
                ✓ Confirm
              </button>
              <button
                onClick={() => denyAction()}
                disabled={loading}
                className={`flex-1 px-4 py-2 text-sm font-medium rounded-lg transition-all ${
                  loading
                    ? 'opacity-50 cursor-not-allowed'
                    : isDarkMode
                      ? 'bg-red-500/20 border border-red-500/30 text-red-300 hover:bg-red-500/30 hover:border-red-500/50'
                      : 'bg-red-50 border border-red-200 text-red-700 hover:bg-red-100'
                }`}
              >
                ✗ Cancel
              </button>
            </div>
          </div>
        )}

        {/* Input Area */}
        <ChatbotInput
          inputValue={inputValue}
          setInputValue={setInputValue}
          onSend={handleSendMessage}
          onKeyPress={handleKeyDown}
          isLoading={loading}
          isDarkMode={isDarkMode}
          disabled={isRateLimited && rateLimitInfo.mustStartNew}
          disabledMessage={rateLimitMessage || 'Message limit reached — start a new conversation'}
        />
      </div>

      {/* Clear Confirmation Modal */}
      {showClearConfirm && (
        <div className="fixed inset-0 bg-black/50 backdrop-blur-sm z-[10000] flex items-center justify-center p-4">
          <div className="bg-gray-900 border border-amber-500/30 rounded-xl p-6 max-w-sm w-full shadow-2xl">
            <div className="flex items-center gap-3 mb-4">
              <div className="w-10 h-10 rounded-full bg-red-500/10 border border-red-500/30 flex items-center justify-center flex-shrink-0">
                <svg className="w-5 h-5 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
              </div>
              <h3 className="text-lg font-semibold text-gray-100">Clear Chat History?</h3>
            </div>
            <p className="text-gray-400 mb-6 text-sm">This will permanently delete all your conversation history. This action cannot be undone.</p>
            <div className="flex gap-3">
              <button
                onClick={() => setShowClearConfirm(false)}
                className="flex-1 px-4 py-2.5 bg-gray-800 border border-amber-500/20 text-gray-100 rounded-lg hover:bg-gray-700 hover:border-amber-500/40 transition-all"
              >
                Cancel
              </button>
              <button
                onClick={() => {
                  clearHistory();
                  setShowClearConfirm(false);
                }}
                className="flex-1 px-4 py-2.5 bg-red-500 text-white rounded-lg hover:bg-red-600 transition-all shadow-lg shadow-red-500/20"
              >
                Clear All
              </button>
            </div>
          </div>
        </div>
      )}

      {/* Delete Conversation Confirmation Modal */}
      {deleteConfirmId && (
        <div className="fixed inset-0 bg-black/50 backdrop-blur-sm z-[10000] flex items-center justify-center p-4">
          <div className="bg-gray-900 border border-amber-500/30 rounded-xl p-6 max-w-sm w-full shadow-2xl">
            <div className="flex items-center gap-3 mb-4">
              <div className="w-10 h-10 rounded-full bg-red-500/10 border border-red-500/30 flex items-center justify-center flex-shrink-0">
                <svg className="w-5 h-5 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                </svg>
              </div>
              <h3 className="text-lg font-semibold text-gray-100">Delete Conversation?</h3>
            </div>
            <p className="text-gray-400 mb-6 text-sm">This will permanently delete this conversation and all its messages. This action cannot be undone.</p>
            <div className="flex gap-3">
              <button
                onClick={() => setDeleteConfirmId(null)}
                className="flex-1 px-4 py-2.5 bg-gray-800 border border-amber-500/20 text-gray-100 rounded-lg hover:bg-gray-700 hover:border-amber-500/40 transition-all"
              >
                Cancel
              </button>
              <button
                onClick={() => handleDeleteConversation(deleteConfirmId)}
                className="flex-1 px-4 py-2.5 bg-red-500 text-white rounded-lg hover:bg-red-600 transition-all shadow-lg shadow-red-500/20"
              >
                Delete
              </button>
            </div>
          </div>
        </div>
      )}
    </>
  );
};

export default ChatbotModal;
