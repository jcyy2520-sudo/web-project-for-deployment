  import React, { useState, useEffect, useRef } from 'react';
import { useNavigate } from 'react-router-dom';
import axios from 'axios';
import useChatbot from '../../hooks/useChatbot';
import { useAuth } from '../../context/AuthContext';
import ChatbotMessage from './ChatbotMessage';
import ChatbotInput from './ChatbotInput';

const FALLBACK_QUESTIONS = [
  "What services do you offer and how much do they cost?",
  "Where is your office located?",
  "How do I book an appointment?",
  "What are your business hours?",
  "What documents do I need to bring?",
  "How do I register for an account?"
];

// Icons for prompt cards mapped by index
const PROMPT_ICONS = [
  // Calendar/booking icon
  <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>,
  // Location icon
  <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" /><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" /></svg>,
  // Document/info icon
  <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>,
  // Clock icon
  <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>,
  // Clipboard icon
  <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" /></svg>,
  // User icon
  <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" /></svg>,
];

const InlineChatbot = ({ isDarkMode }) => {
  const navigate = useNavigate();
  const { isAuthenticated } = useAuth();
  const isGuest = !isAuthenticated;
  const {
    messages,
    loading,
    error,
    sendMessage,
    clearHistory,
    messagesEndRef,
    setError,
    lastSuggestions,
    conversations,
    conversationsLoading,
    showHistory,
    startNewConversation,
    switchConversation,
    deleteConversation,
    toggleHistory,
    setShowHistory,
    conversationId,
    rateLimitInfo,
    isRateLimited,
    rateLimitMessage,
    roleContext,
    submitFeedback,
    proactiveTips,
    lastFailedMessage,
    retryLastMessage,
    errorRecovery,
    guestLimitInfo,
  } = useChatbot();

  const [inputValue, setInputValue] = useState('');
  const [suggestedQuestions, setSuggestedQuestions] = useState([]);
  const [loadingSuggestions, setLoadingSuggestions] = useState(true);
  const [dynamicUpdates, setDynamicUpdates] = useState([]);
  const [userRole, setUserRole] = useState('guest');
  const [showClearConfirm, setShowClearConfirm] = useState(false);
  const [deleteConfirmId, setDeleteConfirmId] = useState(null);
  const chatContainerRef = useRef(null);

  // Update userRole when roleContext changes from backend responses
  useEffect(() => {
    if (roleContext?.role) {
      setUserRole(roleContext.role);
    }
  }, [roleContext?.role]);

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

  // Safe navigation helper
  const safeNavigate = (route) => {
    if (typeof route === 'string' && route.startsWith('/') && !route.startsWith('//')) {
      navigate(route);
    }
  };

  // Fetch suggested questions on mount
  useEffect(() => {
    const fetchSuggestions = async () => {
      try {
        setLoadingSuggestions(true);
        const response = await axios.get('/api/chatbot/suggested-questions');

        if (!response.data || response.data.success === false || !Array.isArray(response.data.data)) {
          setSuggestedQuestions(FALLBACK_QUESTIONS);
          return;
        }

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
      } catch {
        setSuggestedQuestions(FALLBACK_QUESTIONS);
      } finally {
        setLoadingSuggestions(false);
      }
    };
    fetchSuggestions();
  }, []);

  // Listen for chatbot-suggestion events
  useEffect(() => {
    const handler = (e) => {
      if (e.detail) sendMessage(e.detail);
    };
    window.addEventListener('chatbot-suggestion', handler);
    return () => window.removeEventListener('chatbot-suggestion', handler);
  }, [sendMessage]);

  // Listen for navigation events from ChatbotMessage
  useEffect(() => {
    const handler = (e) => {
      const { route } = e.detail || {};
      if (typeof route === 'string' && route.startsWith('/') && !route.startsWith('//')) {
        navigate(route);
      }
    };
    window.addEventListener('chatbot-navigate', handler);
    return () => window.removeEventListener('chatbot-navigate', handler);
  }, [navigate]);

  // Auto-scroll within the chat container only (not the page)
  useEffect(() => {
    if (chatContainerRef.current) {
      chatContainerRef.current.scrollTop = chatContainerRef.current.scrollHeight;
    }
  }, [messages]);

  const handleSendMessage = () => {
    if (inputValue.trim()) {
      sendMessage(inputValue);
      setInputValue('');
    }
  };

  const formatCooldownTime = (isoString) => {
    if (!isoString) return '';

    try {
      const date = new Date(isoString);
      const datePart = date.toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
      });
      const timePart = date.toLocaleTimeString('en-US', {
        hour: 'numeric',
        minute: '2-digit',
        hour12: true,
      });
      return `${datePart} at ${timePart}`;
    } catch {
      return '';
    }
  };

  const handleSuggestedQuestion = (question) => {
    sendMessage(question);
    setInputValue('');
  };

  const handleKeyDown = (e) => {
    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault();
      e.stopPropagation();
      handleSendMessage();
    }
  };

  const handleNewConversation = () => {
    startNewConversation();
    setShowHistory(false);
  };

  const handleSwitchConversation = (convId) => {
    switchConversation(convId);
    setShowHistory(false);
  };

  const handleDeleteConversation = (convId) => {
    deleteConversation(convId);
    setDeleteConfirmId(null);
  };

  const handleDynamicUpdateClick = (update) => {
    if (update.route) {
      safeNavigate(update.route);
    } else if (update.text) {
      sendMessage(update.text);
    }
  };

  // Refresh suggestions
  const refreshSuggestions = async () => {
    try {
      setLoadingSuggestions(true);
      const response = await axios.get('/api/chatbot/suggested-questions');
      if (response.data?.data && Array.isArray(response.data.data) && response.data.data.length > 0) {
        setSuggestedQuestions(response.data.data);
      } else {
        // Shuffle fallback questions for variety
        setSuggestedQuestions([...FALLBACK_QUESTIONS].sort(() => Math.random() - 0.5));
      }
      if (Array.isArray(response.data?.dynamic_updates)) {
        setDynamicUpdates(response.data.dynamic_updates);
      }
    } catch {
      setSuggestedQuestions([...FALLBACK_QUESTIONS].sort(() => Math.random() - 0.5));
    } finally {
      setLoadingSuggestions(false);
    }
  };

  return (
    <div className={`rounded-3xl overflow-hidden backdrop-blur-xl border transition-all duration-500 flex flex-col relative h-[500px] max-h-[70vh] md:h-[600px] md:max-h-[80vh] ${
      isDarkMode
        ? 'bg-gray-900/60 border-gray-700/40 shadow-2xl shadow-black/20'
        : 'bg-white/80 border-gray-200/60 shadow-2xl shadow-gray-300/30'
    }`}>

      {/* Sidebar Icons - hidden on mobile */}
      <div className={`absolute left-0 top-0 bottom-0 w-12 hidden md:flex flex-col items-center py-5 gap-3 z-20 border-r ${
        isDarkMode ? 'bg-gray-900/80 border-gray-700/30' : 'bg-gray-50/90 border-gray-200/40'
      }`}>
        {/* Logo / Bot icon */}
        <div className="w-12 h-12 flex items-center justify-center mb-2 flex-shrink-0">
          <img 
            src={isDarkMode ? '/logo-dark-v2.png' : '/logo-light-v2.png'} 
            alt="Logo" 
            className="h-full w-full object-contain pointer-events-none drop-shadow-sm transition-opacity duration-300"
            onError={(e) => {
              e.target.onerror = null;
              e.target.src = '/logo.png';
            }}
          />
        </div>

        {/* New conversation — authenticated users only */}
        {!isGuest && (
        <button
          onClick={handleNewConversation}
          className={`w-8 h-8 rounded-xl flex items-center justify-center transition-all ${
            isDarkMode ? 'text-gray-500 hover:text-amber-400 hover:bg-amber-500/10' : 'text-gray-400 hover:text-blue-500 hover:bg-blue-50'
          }`}
          title="New conversation"
        >
          <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4v16m8-8H4" />
          </svg>
        </button>
        )}

        {/* Search / History — authenticated users only */}
        {!isGuest && (
        <button
          onClick={toggleHistory}
          className={`w-8 h-8 rounded-xl flex items-center justify-center transition-all ${
            showHistory
              ? (isDarkMode ? 'bg-amber-500/20 text-amber-400' : 'bg-blue-100 text-blue-600')
              : (isDarkMode ? 'text-gray-500 hover:text-amber-400 hover:bg-amber-500/10' : 'text-gray-400 hover:text-blue-500 hover:bg-blue-50')
          }`}
          title="Chat history"
        >
          <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
          </svg>
        </button>
        )}

        {/* History clock — authenticated users only */}
        {!isGuest && (
        <button
          onClick={toggleHistory}
          className={`w-8 h-8 rounded-xl flex items-center justify-center transition-all ${
            isDarkMode ? 'text-gray-500 hover:text-amber-400 hover:bg-amber-500/10' : 'text-gray-400 hover:text-blue-500 hover:bg-blue-50'
          }`}
          title="Recent conversations"
        >
          <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
        </button>
        )}

        {/* Spacer */}
        <div className="flex-1" />

        {/* Settings / Clear */}
        <button
          onClick={() => setShowClearConfirm(true)}
          className={`w-8 h-8 rounded-xl flex items-center justify-center transition-all ${
            isDarkMode ? 'text-gray-500 hover:text-red-400 hover:bg-red-500/10' : 'text-gray-400 hover:text-red-500 hover:bg-red-50'
          }`}
          title="Clear history"
        >
          <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
          </svg>
        </button>

        {/* User avatar */}
        <div className={`w-7 h-7 rounded-full flex items-center justify-center text-xs font-medium ${
          isDarkMode ? 'bg-amber-500/20 text-amber-400 border border-amber-500/30' : 'bg-blue-100 text-blue-600 border border-blue-200'
        }`}>
          {userRole === 'guest' ? '?' : userRole.charAt(0).toUpperCase()}
        </div>
      </div>

      {/* Main Content Area (offset by sidebar on md+) */}
      <div className="ml-0 md:ml-12 flex flex-col flex-1 min-h-0">

        {/* Rate Limit Warning */}
        {isRateLimited && rateLimitInfo.mustStartNew && (
          <div className={`px-6 py-3 border-b flex-shrink-0 ${isDarkMode ? 'bg-amber-500/10 border-amber-500/20' : 'bg-amber-50 border-amber-200'}`}>
            <div className="flex items-center justify-between">
              <div className="flex items-center gap-2">
                <svg className="w-5 h-5 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
                <div>
                  <p className={`text-sm font-medium ${isDarkMode ? 'text-amber-300' : 'text-amber-700'}`}>Message Limit Reached</p>
                  <p className={`text-xs ${isDarkMode ? 'text-amber-400/80' : 'text-amber-600'}`}>
                    {isGuest
                      ? 'Please log in or register to continue chatting.'
                      : (rateLimitMessage || 'Start a new conversation to continue.')}
                  </p>
                </div>
              </div>
              {!isGuest && (
                <button onClick={handleNewConversation} className={`px-3 py-1.5 text-sm font-medium rounded-lg ${isDarkMode ? 'bg-amber-500 text-gray-900' : 'bg-amber-600 text-white'}`}>
                  New Chat
                </button>
              )}
            </div>
          </div>
        )}

        {isGuest && guestLimitInfo.isLimited && (
          <div className={`px-6 py-3 border-b flex-shrink-0 ${isDarkMode ? 'bg-red-500/10 border-red-500/20' : 'bg-red-50 border-red-200'}`}>
            <div className="flex items-start justify-between gap-3">
              <div className="flex items-start gap-2.5 min-w-0">
                <svg className={`w-5 h-5 flex-shrink-0 mt-0.5 ${isDarkMode ? 'text-red-400' : 'text-red-500'}`} fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
                </svg>
                <div className="min-w-0">
                  <p className={`text-sm font-semibold ${isDarkMode ? 'text-red-300' : 'text-red-700'}`}>Guest message limit reached</p>
                  <p className={`text-xs mt-0.5 ${isDarkMode ? 'text-red-400/80' : 'text-red-600'}`}>
                    {guestLimitInfo.cooldownUntil
                      ? `You've reached the message limit for guest users. You can send messages again on ${formatCooldownTime(guestLimitInfo.cooldownUntil)}. Register now to continue chatting without limits.`
                      : 'You\'ve reached the message limit for guest users. Register now to continue chatting without limits.'}
                  </p>
                </div>
              </div>
              <button
                onClick={() => navigate('/register')}
                className={`flex-shrink-0 inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold rounded-lg transition-all ${isDarkMode ? 'bg-amber-500 text-gray-900 hover:bg-amber-400' : 'bg-amber-600 text-white hover:bg-amber-500'}`}
              >
                Register
              </button>
            </div>
          </div>
        )}

        {isGuest && !guestLimitInfo.isLimited && guestLimitInfo.remaining <= 2 && guestLimitInfo.remaining > 0 && (
          <div className={`px-6 py-2.5 border-b flex-shrink-0 ${isDarkMode ? 'bg-amber-500/10 border-amber-500/20' : 'bg-amber-50 border-amber-200'}`}>
            <div className="flex items-center justify-between gap-3">
              <div className="flex items-center gap-2 min-w-0">
                <svg className={`w-4 h-4 flex-shrink-0 ${isDarkMode ? 'text-amber-400' : 'text-amber-600'}`} fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
                <p className={`text-xs ${isDarkMode ? 'text-amber-300' : 'text-amber-700'}`}>
                  You have <span className="font-bold">{guestLimitInfo.remaining}</span> message{guestLimitInfo.remaining !== 1 ? 's' : ''} remaining. Register an account to enjoy unlimited access.
                </p>
              </div>
              <button
                onClick={() => navigate('/register')}
                className={`flex-shrink-0 text-xs font-semibold px-2.5 py-1 rounded-lg transition-all ${isDarkMode ? 'bg-amber-500/20 border border-amber-500/40 text-amber-300 hover:bg-amber-500/30' : 'bg-amber-100 border border-amber-300 text-amber-700 hover:bg-amber-200'}`}
              >
                Register
              </button>
            </div>
          </div>
        )}

        {/* Remaining messages counter */}
        {!isRateLimited && !isGuest && rateLimitInfo.remaining <= 5 && rateLimitInfo.remaining > 0 && (
          <div className={`px-6 py-2 border-b flex-shrink-0 ${isDarkMode ? 'bg-gray-800/30 border-gray-700/20' : 'bg-white/60 border-gray-200/30'}`}>
            <div className="flex items-center justify-between">
              <span className={`text-xs ${isDarkMode ? 'text-gray-400' : 'text-slate-500'}`}>
                {rateLimitInfo.remaining} message{rateLimitInfo.remaining !== 1 ? 's' : ''} remaining
              </span>
              <button onClick={handleNewConversation} className={`text-xs ${isDarkMode ? 'text-amber-400 hover:text-amber-300' : 'text-blue-600 hover:text-blue-500'}`}>
                Start new
              </button>
            </div>
          </div>
        )}

        {/* Conversation History Panel */}
        {showHistory && (
          <div className={`absolute inset-0 left-0 md:left-12 z-10 flex flex-col rounded-r-3xl ${isDarkMode ? 'bg-gray-900/98' : 'bg-white/98'} backdrop-blur-xl`}>
            <div className={`px-6 py-4 border-b flex items-center justify-between ${isDarkMode ? 'border-gray-700/30' : 'border-gray-200/40'}`}>
              <h3 className={`font-semibold ${isDarkMode ? 'text-gray-200' : 'text-slate-900'}`}>Conversations</h3>
              <button onClick={() => setShowHistory(false)} className={`p-1.5 rounded-lg transition-all ${isDarkMode ? 'text-gray-400 hover:text-amber-400 hover:bg-amber-500/10' : 'text-slate-500 hover:text-blue-600 hover:bg-blue-50'}`}>
                <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                </svg>
              </button>
            </div>
            <div className={`px-4 py-3 border-b ${isDarkMode ? 'border-gray-700/20' : 'border-gray-200/30'}`}>
              <button onClick={handleNewConversation} className={`w-full flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl transition-all font-medium text-sm ${isDarkMode ? 'bg-amber-500/10 border border-amber-500/30 text-amber-400 hover:bg-amber-500/20' : 'bg-blue-50 border border-blue-200 text-blue-600 hover:bg-blue-100'}`}>
                <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4v16m8-8H4" />
                </svg>
                New Conversation
              </button>
            </div>
            <div className="flex-1 overflow-y-auto">
              {conversationsLoading ? (
                <div className="flex items-center justify-center py-8">
                  <div className="flex space-x-1.5">
                    {[0, 0.1, 0.2].map((d, i) => (
                      <div key={i} className={`w-2 h-2 rounded-full animate-bounce ${isDarkMode ? 'bg-amber-500' : 'bg-blue-500'}`} style={{ animationDelay: `${d}s` }} />
                    ))}
                  </div>
                </div>
              ) : conversations.length === 0 ? (
                <div className="flex flex-col items-center justify-center py-12 px-6 text-center">
                  <div className={`w-12 h-12 rounded-2xl flex items-center justify-center mb-3 ${isDarkMode ? 'bg-gray-800' : 'bg-gray-100'}`}>
                    <svg className={`w-6 h-6 ${isDarkMode ? 'text-gray-600' : 'text-gray-400'}`} fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                    </svg>
                  </div>
                  <p className={`text-sm ${isDarkMode ? 'text-gray-500' : 'text-slate-500'}`}>No conversation history yet</p>
                </div>
              ) : (
                <div className={`divide-y ${isDarkMode ? 'divide-gray-800/50' : 'divide-gray-100'}`}>
                  {conversations.map((conv) => (
                    <div key={conv.conversation_id} className={`relative group ${conversationId === conv.conversation_id ? (isDarkMode ? 'bg-amber-500/5' : 'bg-blue-50/50') : (isDarkMode ? 'hover:bg-gray-800/30' : 'hover:bg-gray-50')}`}>
                      <button onClick={() => handleSwitchConversation(conv.conversation_id)} className="w-full text-left px-5 py-3.5">
                        <div className="flex items-start justify-between gap-2">
                          <div className="flex-1 min-w-0">
                            <p className={`text-sm font-medium truncate ${conversationId === conv.conversation_id ? (isDarkMode ? 'text-amber-400' : 'text-blue-600') : (isDarkMode ? 'text-gray-300' : 'text-slate-700')}`}>
                              {conv.title}
                            </p>
                            {conv.last_message && (
                              <p className={`text-xs truncate mt-0.5 ${isDarkMode ? 'text-gray-500' : 'text-slate-500'}`}>
                                {conv.last_message_role === 'assistant' ? 'AI: ' : 'You: '}{conv.last_message}
                              </p>
                            )}
                            <div className="flex items-center gap-2 mt-1">
                              <span className={`text-xs ${isDarkMode ? 'text-gray-600' : 'text-slate-400'}`}>
                                {formatConversationDate(conv.updated_at)}
                              </span>
                              <span className={`text-xs ${isDarkMode ? 'text-gray-600' : 'text-slate-400'}`}>•</span>
                              <span className={`text-xs ${isDarkMode ? 'text-gray-600' : 'text-slate-400'}`}>
                                {conv.message_count} messages
                              </span>
                              {conv.is_at_limit && (
                                <>
                                  <span className={`text-xs ${isDarkMode ? 'text-gray-600' : 'text-slate-400'}`}>•</span>
                                  <span className={`text-xs font-medium ${isDarkMode ? 'text-amber-400' : 'text-amber-600'}`}>Limit reached</span>
                                </>
                              )}
                            </div>
                          </div>
                          {conversationId === conv.conversation_id && (
                            <div className={`w-2 h-2 rounded-full flex-shrink-0 mt-1.5 ${isDarkMode ? 'bg-amber-500' : 'bg-blue-500'}`} />
                          )}
                        </div>
                      </button>
                      <button
                        onClick={(e) => { e.stopPropagation(); setDeleteConfirmId(conv.conversation_id); }}
                        className={`absolute right-3 top-1/2 -translate-y-1/2 p-1.5 opacity-0 group-hover:opacity-100 hover:bg-red-500/20 hover:text-red-400 ${isDarkMode ? 'text-gray-500' : 'text-slate-400'} rounded-lg transition-all`}
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

        {/* Pending Items Banner — shows role-based action items */}
        {roleContext?.pendingItems?.length > 0 && roleContext.pendingItems.some(i => i.count > 0) && !showHistory && (
          <div className={`px-6 py-2.5 flex-shrink-0 border-b ${isDarkMode ? 'bg-amber-500/5 border-gray-700/20' : 'bg-blue-50/30 border-gray-200/30'}`}>
            <div className="flex items-center gap-2 overflow-x-auto scrollbar-none">
              <span className={`text-[10px] uppercase tracking-wide whitespace-nowrap font-medium ${isDarkMode ? 'text-amber-400/70' : 'text-blue-600'}`}>📋 Pending:</span>
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
                  className={`text-[11px] px-2.5 py-1 rounded-full whitespace-nowrap flex items-center gap-1 transition-all ${
                    isDarkMode
                      ? 'bg-gray-800/80 border border-amber-500/15 text-gray-300 hover:border-amber-500/40 hover:text-amber-300'
                      : 'bg-white border border-blue-200 text-slate-600 hover:bg-blue-50'
                  }`}
                >
                  <span className={`font-bold ${item.count >= 5 ? (isDarkMode ? 'text-red-400' : 'text-red-600') : (isDarkMode ? 'text-amber-400' : 'text-blue-600')}`}>{item.count}</span>
                  {item.label}
                </button>
              ))}
            </div>
          </div>
        )}

        {/* Messages Area */}
        <div ref={chatContainerRef} className={`flex-1 overflow-y-auto px-6 py-6 space-y-4 ${isDarkMode ? '' : ''}`} style={{
          scrollbarWidth: 'thin',
          scrollbarColor: isDarkMode ? '#374151 transparent' : '#E2E8F0 transparent',
        }}>
          {messages.length === 0 ? (
            /* ==================== EMPTY STATE — Reference-inspired greeting ==================== */
            <div className="flex flex-col h-full">
              {/* Greeting Section */}
              <div className="flex-1 flex flex-col justify-center items-center text-center px-4">
                <div className="mb-8">
                  <h2 className={`text-3xl md:text-4xl font-bold leading-tight ${isDarkMode ? 'text-gray-100' : 'text-slate-900'}`}>
                    Hi there{roleContext?.roleDisplay ? `, ${roleContext.roleDisplay}` : (userRole && userRole !== 'guest' ? `, ${userRole.charAt(0).toUpperCase() + userRole.slice(1)}` : '')}
                  </h2>
                  <h2 className={`text-3xl md:text-4xl font-bold leading-tight mt-1 ${isDarkMode ? 'text-amber-400' : 'text-blue-500'}`}>
                    What would you like to know?
                  </h2>
                  <p className={`mt-3 text-sm max-w-md mx-auto ${isDarkMode ? 'text-gray-400' : 'text-slate-500'}`}>
                    {roleContext?.roleDisplay
                      ? `Assisting as ${roleContext.roleDisplay} — ask about services, appointments, or anything else`
                      : 'Use one of the common prompts below or type your own to begin'
                    }
                  </p>
                </div>

                {/* Proactive Tips */}
                {proactiveTips && proactiveTips.length > 0 && (
                  <div className="w-full max-w-2xl mb-6">
                    <p className={`text-xs font-semibold uppercase tracking-wider mb-3 ${isDarkMode ? 'text-amber-400/80' : 'text-blue-500'}`}>💡 For You</p>
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-2">
                      {proactiveTips.slice(0, 4).map((tip, idx) => (
                        <button
                          key={`tip-${idx}`}
                          onClick={() => {
                            if (tip.action?.route) {
                              safeNavigate(tip.action.route);
                            } else if (tip.action?.message) {
                              sendMessage(tip.action.message);
                            }
                          }}
                          className={`text-left p-3.5 rounded-2xl transition-all text-sm border group ${
                            tip.type === 'warning'
                              ? (isDarkMode ? 'bg-amber-500/5 border-amber-500/20 hover:bg-amber-500/10 hover:border-amber-500/40' : 'bg-amber-50 border-amber-100 hover:bg-amber-100')
                              : tip.type === 'reminder'
                              ? (isDarkMode ? 'bg-blue-500/5 border-blue-500/20 hover:bg-blue-500/10 hover:border-blue-500/40' : 'bg-blue-50 border-blue-100 hover:bg-blue-100')
                              : (isDarkMode ? 'bg-gray-800/40 border-gray-700/30 hover:bg-gray-800/60 hover:border-gray-600/50' : 'bg-white/60 border-gray-200/60 hover:bg-white hover:border-gray-300 hover:shadow-sm')
                          }`}
                        >
                          <span className="flex items-center gap-2">
                            <span className="text-base">{tip.icon || '💡'}</span>
                            <span className={isDarkMode ? 'text-gray-300' : 'text-slate-700'}>{tip.message}</span>
                          </span>
                          {tip.action?.label && (
                            <span className={`text-xs mt-1.5 block ${isDarkMode ? 'text-amber-400/80' : 'text-blue-500'}`}>{tip.action.label} →</span>
                          )}
                        </button>
                      ))}
                    </div>
                  </div>
                )}

                {/* Dynamic Updates for Admin/Cashier */}
                {dynamicUpdates && dynamicUpdates.length > 0 && (
                  <div className="w-full max-w-2xl mb-6">
                    <p className={`text-xs font-semibold uppercase tracking-wider mb-3 ${isDarkMode ? 'text-amber-400/80' : 'text-blue-500'}`}>📋 Updates</p>
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-2">
                      {dynamicUpdates.slice(0, 4).map((update, index) => (
                        <button
                          key={`update-${index}`}
                          onClick={() => handleDynamicUpdateClick(update)}
                          className={`text-left p-3.5 rounded-2xl border transition-all text-sm group ${
                            update.priority === 'high'
                              ? (isDarkMode ? 'bg-red-500/5 border-red-500/20 hover:bg-red-500/10 hover:border-red-500/40' : 'bg-red-50 border-red-100 hover:bg-red-100')
                              : update.priority === 'medium'
                              ? (isDarkMode ? 'bg-amber-500/5 border-amber-500/20 hover:bg-amber-500/10 hover:border-amber-500/40' : 'bg-blue-50 border-blue-200 hover:bg-blue-100')
                              : (isDarkMode ? 'bg-gray-800/40 border-gray-700/30 hover:bg-gray-800/60' : 'bg-white/60 border-gray-200/60 hover:bg-white hover:shadow-sm')
                          }`}
                        >
                          <span className="flex items-center justify-between gap-2">
                            <span className={`${update.priority === 'high' ? (isDarkMode ? 'text-red-300' : 'text-red-700') : update.priority === 'medium' ? (isDarkMode ? 'text-amber-300' : 'text-amber-600') : (isDarkMode ? 'text-gray-300' : 'text-slate-700')}`}>
                              {update.priority === 'high' && '⚠️ '}
                              {update.priority === 'medium' && '📌 '}
                              {update.text}
                            </span>
                            {update.route && (
                              <svg className={`w-4 h-4 flex-shrink-0 ${isDarkMode ? 'text-gray-500 group-hover:text-gray-300' : 'text-slate-400 group-hover:text-slate-600'}`} fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                              </svg>
                            )}
                          </span>
                        </button>
                      ))}
                    </div>
                  </div>
                )}

                {/* Prompt Cards — Reference-inspired grid */}
                {lastSuggestions && Array.isArray(lastSuggestions) && lastSuggestions.length > 0 ? (
                  <div className="w-full max-w-2xl">
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                      {lastSuggestions.map((question, idx) => (
                        <button
                          key={`dyn-${idx}`}
                          onClick={() => handleSuggestedQuestion(question)}
                          className={`text-left p-4 rounded-2xl transition-all group border ${
                            isDarkMode
                              ? 'bg-gray-800/40 border-gray-700/30 hover:bg-gray-800/70 hover:border-amber-500/30 hover:shadow-lg hover:shadow-amber-500/5'
                              : 'bg-white/70 border-gray-200/60 hover:bg-white hover:border-gray-300 hover:shadow-md hover:shadow-gray-200/50'
                          }`}
                        >
                          <p className={`text-sm leading-relaxed mb-3 ${isDarkMode ? 'text-gray-300 group-hover:text-gray-100' : 'text-slate-700 group-hover:text-slate-900'}`}>
                            {question}
                          </p>
                          <div className={`${isDarkMode ? 'text-amber-500/50 group-hover:text-amber-400' : 'text-blue-400/50 group-hover:text-blue-500'} transition-colors`}>
                            {PROMPT_ICONS[idx % PROMPT_ICONS.length]}
                          </div>
                        </button>
                      ))}
                    </div>
                  </div>
                ) : loadingSuggestions ? (
                  <div className="flex items-center justify-center gap-2 py-4">
                    {[0, 0.1, 0.2].map((d, i) => (
                      <div key={i} className={`w-2.5 h-2.5 rounded-full animate-bounce ${isDarkMode ? 'bg-amber-500' : 'bg-blue-500'}`} style={{ animationDelay: `${d}s` }} />
                    ))}
                  </div>
                ) : (
                  <div className="w-full max-w-2xl">
                    {Array.isArray(suggestedQuestions) && suggestedQuestions.length > 0 ? (
                      <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        {suggestedQuestions.slice(0, 4).map((question, index) => (
                          <button
                            key={index}
                            onClick={() => handleSuggestedQuestion(question)}
                            className={`text-left p-4 rounded-2xl transition-all group border ${
                              isDarkMode
                                ? 'bg-gray-800/40 border-gray-700/30 hover:bg-gray-800/70 hover:border-amber-500/30 hover:shadow-lg hover:shadow-amber-500/5'
                                : 'bg-white/70 border-gray-200/60 hover:bg-white hover:border-gray-300 hover:shadow-md hover:shadow-gray-200/50'
                            }`}
                          >
                            <p className={`text-sm leading-relaxed mb-3 ${isDarkMode ? 'text-gray-300 group-hover:text-gray-100' : 'text-slate-700 group-hover:text-slate-900'}`}>
                              {question}
                            </p>
                            <div className={`${isDarkMode ? 'text-amber-500/50 group-hover:text-amber-400' : 'text-blue-400/50 group-hover:text-blue-500'} transition-colors`}>
                              {PROMPT_ICONS[index % PROMPT_ICONS.length]}
                            </div>
                          </button>
                        ))}
                      </div>
                    ) : (
                      <p className={`${isDarkMode ? 'text-gray-500' : 'text-slate-500'} text-sm text-center`}>Feel free to ask me anything!</p>
                    )}
                  </div>
                )}

                {/* Refresh Prompts Button */}
                {messages.length === 0 && !loadingSuggestions && (
                  <button
                    onClick={refreshSuggestions}
                    className={`mt-5 flex items-center gap-1.5 text-xs transition-all ${
                      isDarkMode ? 'text-gray-500 hover:text-amber-400' : 'text-slate-400 hover:text-blue-500'
                    }`}
                  >
                    <svg className="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                    </svg>
                    Refresh Prompts
                  </button>
                )}
              </div>
            </div>
          ) : (
            <>
              {messages.map((msg, i) => (
                <ChatbotMessage
                  key={msg.id || `msg-${i}`}
                  message={msg}
                  isDarkMode={isDarkMode}
                  onFeedback={submitFeedback}
                  isLastMessage={i === messages.length - 1}
                />
              ))}

              {/* Retry failed message */}
              {lastFailedMessage && (
                <div className="flex justify-center">
                  <button
                    onClick={retryLastMessage}
                    className={`text-xs px-3 py-1.5 rounded-full border transition-all ${
                      isDarkMode ? 'border-red-500/30 text-red-400 hover:bg-red-500/10' : 'border-red-200 text-red-600 hover:bg-red-50'
                    }`}
                  >
                    ↻ Retry last message
                  </button>
                </div>
              )}

              {/* Loading indicator */}
              {loading && (
                <div className="flex justify-start">
                  <div className={`rounded-2xl px-5 py-3.5 ${isDarkMode ? 'bg-gray-800/50 border border-gray-700/40' : 'bg-white/80 border border-gray-200/60'}`}>
                    <div className="flex gap-1.5">
                      {[0, 150, 300].map((d, i) => (
                        <div key={i} className={`w-2 h-2 rounded-full animate-bounce ${isDarkMode ? 'bg-amber-400' : 'bg-blue-400'}`} style={{ animationDelay: `${d}ms` }} />
                      ))}
                    </div>
                  </div>
                </div>
              )}

              {/* Suggestions after messages */}
              {!loading && lastSuggestions && lastSuggestions.length > 0 && (
                <div className="flex flex-wrap gap-2 pt-2">
                  {lastSuggestions.map((s, idx) => (
                    <button
                      key={`sug-${idx}`}
                      onClick={() => handleSuggestedQuestion(s)}
                      className={`text-xs px-3.5 py-1.5 rounded-full border transition-all hover:scale-105 ${
                        isDarkMode ? 'border-amber-500/20 text-amber-300 hover:bg-amber-500/10 hover:border-amber-500/40' : 'border-blue-200 text-blue-600 hover:bg-blue-50 hover:border-blue-300'
                      }`}
                    >
                      {s}
                    </button>
                  ))}
                </div>
              )}
            </>
          )}
          <div ref={messagesEndRef} />
        </div>

        {/* Error Message with Recovery Guidance */}
        {error && (
          <div className={`px-6 py-3 flex-shrink-0 border-t ${isDarkMode ? 'bg-red-500/5 border-red-500/15' : 'bg-red-50 border-red-100'}`}>
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
                    onClick={() => { setError(null); retryLastMessage(); }}
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
                    onClick={() => { setError(null); sendMessage(suggestion); }}
                    className={`text-xs px-2.5 py-1 rounded-full transition-all ${
                      isDarkMode
                        ? 'bg-gray-800 border border-red-500/20 text-gray-300 hover:border-amber-500/40 hover:text-amber-300'
                        : 'bg-white border border-red-200 text-slate-600 hover:border-blue-300 hover:text-blue-700'
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
          <div className={`px-6 py-2.5 flex-shrink-0 border-t ${isDarkMode ? 'bg-gray-900/60 border-gray-700/20' : 'bg-gray-50/60 border-gray-200/30'}`}>
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
                      ? 'bg-gray-800/60 border border-gray-700/30 text-gray-400 hover:bg-amber-500/10 hover:border-amber-500/30 hover:text-amber-400'
                      : 'bg-white border border-gray-200 text-slate-600 hover:bg-blue-50 hover:border-blue-300 hover:text-blue-700'
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

        {/* Input Area */}
        <div className={`flex-shrink-0 border-t ${isDarkMode ? 'border-gray-700/30' : 'border-gray-200/40'}`}>
          <ChatbotInput
            inputValue={inputValue}
            setInputValue={setInputValue}
            onSend={handleSendMessage}
            onKeyPress={handleKeyDown}
            isLoading={loading}
            isDarkMode={isDarkMode}
            disabled={(isRateLimited && rateLimitInfo.mustStartNew) || (isGuest && guestLimitInfo.isLimited)}
            disabledMessage={
              isGuest && guestLimitInfo.isLimited
                ? (guestLimitInfo.cooldownUntil
                    ? `Available again on ${formatCooldownTime(guestLimitInfo.cooldownUntil)}`
                    : 'Guest limit reached — register to continue chatting')
                : (isRateLimited ? 'Message limit reached — start a new conversation' : '')
            }
            mobileActions={{
              onNewConversation: handleNewConversation,
              onToggleHistory: toggleHistory,
              onClearHistory: () => setShowClearConfirm(true),
              showHistory,
              userRole: isGuest ? 'guest' : (userRole && userRole !== 'guest' ? userRole : 'user'),
            }}
          />
        </div>
      </div>

      {/* Clear Confirmation Modal */}
      {showClearConfirm && (
        <div className="fixed inset-0 bg-black/50 backdrop-blur-sm z-[10000] flex items-center justify-center p-4">
          <div className={`border rounded-2xl p-6 max-w-sm w-full shadow-2xl ${isDarkMode ? 'bg-gray-900 border-gray-700/50' : 'bg-white border-gray-200'}`}>
            <div className="flex items-center gap-3 mb-4">
              <div className="w-10 h-10 rounded-full bg-red-500/10 border border-red-500/30 flex items-center justify-center flex-shrink-0">
                <svg className="w-5 h-5 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
              </div>
              <h3 className={`text-lg font-semibold ${isDarkMode ? 'text-gray-100' : 'text-slate-900'}`}>Clear Chat History?</h3>
            </div>
            <p className={`mb-6 text-sm ${isDarkMode ? 'text-gray-400' : 'text-slate-500'}`}>This will permanently delete all your conversation history. This action cannot be undone.</p>
            <div className="flex gap-3">
              <button
                onClick={() => setShowClearConfirm(false)}
                className={`flex-1 px-4 py-2.5 border rounded-xl transition-all ${isDarkMode ? 'bg-gray-800 border-gray-700/50 text-gray-100 hover:bg-gray-700' : 'bg-gray-50 border-gray-200 text-slate-700 hover:bg-gray-100'}`}
              >
                Cancel
              </button>
              <button
                onClick={() => { clearHistory(); setShowClearConfirm(false); }}
                className="flex-1 px-4 py-2.5 bg-red-500 text-white rounded-xl hover:bg-red-600 transition-all shadow-lg shadow-red-500/20"
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
          <div className={`border rounded-2xl p-6 max-w-sm w-full shadow-2xl ${isDarkMode ? 'bg-gray-900 border-gray-700/50' : 'bg-white border-gray-200'}`}>
            <div className="flex items-center gap-3 mb-4">
              <div className="w-10 h-10 rounded-full bg-red-500/10 border border-red-500/30 flex items-center justify-center flex-shrink-0">
                <svg className="w-5 h-5 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                </svg>
              </div>
              <h3 className={`text-lg font-semibold ${isDarkMode ? 'text-gray-100' : 'text-slate-900'}`}>Delete Conversation?</h3>
            </div>
            <p className={`mb-6 text-sm ${isDarkMode ? 'text-gray-400' : 'text-slate-500'}`}>This will permanently delete this conversation and all its messages. This action cannot be undone.</p>
            <div className="flex gap-3">
              <button
                onClick={() => setDeleteConfirmId(null)}
                className={`flex-1 px-4 py-2.5 border rounded-xl transition-all ${isDarkMode ? 'bg-gray-800 border-gray-700/50 text-gray-100 hover:bg-gray-700' : 'bg-gray-50 border-gray-200 text-slate-700 hover:bg-gray-100'}`}
              >
                Cancel
              </button>
              <button
                onClick={() => handleDeleteConversation(deleteConfirmId)}
                className="flex-1 px-4 py-2.5 bg-red-500 text-white rounded-xl hover:bg-red-600 transition-all shadow-lg shadow-red-500/20"
              >
                Delete
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
};

export default InlineChatbot;
