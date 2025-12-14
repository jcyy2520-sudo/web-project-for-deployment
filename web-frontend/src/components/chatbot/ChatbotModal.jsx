import React, { useState, useEffect } from 'react';
import axios from 'axios';
import useChatbot from '../../hooks/useChatbot';
import ChatbotMessage from './ChatbotMessage';
import ChatbotInput from './ChatbotInput';

const ChatbotModal = ({ onClose }) => {
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
  } = useChatbot();

  const [inputValue, setInputValue] = useState('');
  const [showClearConfirm, setShowClearConfirm] = useState(false);
  const [suggestedQuestions, setSuggestedQuestions] = useState([]);
  const [loadingSuggestions, setLoadingSuggestions] = useState(true);
  const [deleteConfirmId, setDeleteConfirmId] = useState(null);

  // Fallback questions for when API fails or returns empty
  const FALLBACK_QUESTIONS = [
    "How do I book an appointment?",
    "What services do you offer?",
    "How do I register?",
    "What are your business hours?"
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
    } catch (err) {
      console.error('Failed to fetch suggested questions:', err);
      // Use fallback questions on error
      setSuggestedQuestions(FALLBACK_QUESTIONS);
    } finally {
      setLoadingSuggestions(false);
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

  const handleKeyPress = (e) => {
    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault();
      handleSendMessage();
    }
  };

  return (
    <>
      {/* Modal Backdrop */}
      <div
        className="fixed inset-0 bg-black/20 backdrop-blur-sm z-50 transition-all"
        onClick={onClose}
      />

      {/* Modal */}
      <div className="fixed bottom-24 right-6 w-[400px] bg-gray-900 rounded-xl shadow-2xl flex flex-col h-[600px] z-50 border border-amber-500/30 overflow-hidden">
        {/* Header */}
        <div className="bg-gray-900 border-b border-amber-500/20 px-5 py-4 flex justify-between items-center flex-shrink-0">
          <div className="flex items-center gap-3">
            <div className="w-10 h-10 rounded-full bg-amber-500/10 border border-amber-500/30 flex items-center justify-center">
              <svg className="w-5 h-5 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z" />
              </svg>
            </div>
            <div>
              <h2 className="font-semibold text-base text-gray-100">Chat Assistant</h2>
              <p className="text-xs text-gray-400">AI-powered support</p>
            </div>
          </div>
          <div className="flex gap-1">
            {/* New Chat Button */}
            <button
              onClick={handleNewConversation}
              className="p-2 hover:bg-amber-500/10 hover:text-amber-500 text-gray-400 rounded-lg transition-all"
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
              className={`p-2 hover:bg-amber-500/10 hover:text-amber-500 rounded-lg transition-all ${showHistory ? 'bg-amber-500/20 text-amber-500' : 'text-gray-400'}`}
              title="Chat history"
              aria-label="View chat history"
            >
              <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
            </button>
            <button
              onClick={() => setShowClearConfirm(true)}
              className="p-2 hover:bg-amber-500/10 hover:text-amber-500 text-gray-400 rounded-lg transition-all"
              title="Clear history"
              aria-label="Clear chat history"
            >
              <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
              </svg>
            </button>
            <button
              onClick={onClose}
              className="p-2 hover:bg-amber-500/10 hover:text-amber-500 text-gray-400 rounded-lg transition-all"
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
          <div className="px-4 py-3 bg-amber-500/20 border-b border-amber-500/30 flex-shrink-0">
            <div className="flex items-center justify-between">
              <div className="flex items-center gap-2">
                <svg className="w-5 h-5 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
                <div>
                  <p className="text-sm font-medium text-amber-300">Message Limit Reached</p>
                  <p className="text-xs text-amber-400/80">{rateLimitMessage || 'Start a new conversation to continue chatting.'}</p>
                </div>
              </div>
              <button
                onClick={handleNewConversation}
                className="px-3 py-1.5 bg-amber-500 text-gray-900 text-sm font-medium rounded-lg hover:bg-amber-400 transition-all"
              >
                New Chat
              </button>
            </div>
          </div>
        )}

        {/* Message Counter (shows remaining messages) */}
        {!isRateLimited && rateLimitInfo.remaining <= 5 && rateLimitInfo.remaining > 0 && (
          <div className="px-4 py-2 bg-gray-800/50 border-b border-amber-500/10 flex-shrink-0">
            <div className="flex items-center justify-between">
              <span className="text-xs text-gray-400">
                {rateLimitInfo.remaining} message{rateLimitInfo.remaining !== 1 ? 's' : ''} remaining in this conversation
              </span>
              <button
                onClick={handleNewConversation}
                className="text-xs text-amber-400 hover:text-amber-300 transition-colors"
              >
                Start new
              </button>
            </div>
          </div>
        )}

        {/* Conversation History Panel (Slide-in sidebar) */}
        {showHistory && (
          <div className="absolute inset-0 top-[73px] bg-gray-900 z-10 flex flex-col border-t border-amber-500/20">
            {/* History Header */}
            <div className="px-4 py-3 border-b border-amber-500/20 flex items-center justify-between">
              <h3 className="font-medium text-gray-200">Conversation History</h3>
              <button
                onClick={() => setShowHistory(false)}
                className="p-1 hover:bg-amber-500/10 hover:text-amber-500 text-gray-400 rounded transition-all"
              >
                <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                </svg>
              </button>
            </div>
            
            {/* New Conversation Button */}
            <div className="px-4 py-2 border-b border-amber-500/10">
              <button
                onClick={handleNewConversation}
                className="w-full flex items-center justify-center gap-2 px-4 py-2.5 bg-amber-500/10 border border-amber-500/30 text-amber-400 rounded-lg hover:bg-amber-500/20 hover:border-amber-500/50 transition-all"
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
                    <div className="w-2 h-2 bg-amber-500 rounded-full animate-bounce" />
                    <div className="w-2 h-2 bg-amber-500 rounded-full animate-bounce" style={{ animationDelay: '0.1s' }} />
                    <div className="w-2 h-2 bg-amber-500 rounded-full animate-bounce" style={{ animationDelay: '0.2s' }} />
                  </div>
                </div>
              ) : conversations.length === 0 ? (
                <div className="flex flex-col items-center justify-center py-8 px-4 text-center">
                  <svg className="w-12 h-12 text-gray-600 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                  </svg>
                  <p className="text-gray-500 text-sm">No conversation history yet</p>
                  <p className="text-gray-600 text-xs mt-1">Start a new conversation to begin</p>
                </div>
              ) : (
                <div className="divide-y divide-gray-800">
                  {conversations.map((conv) => (
                    <div
                      key={conv.conversation_id}
                      className={`relative group ${conversationId === conv.conversation_id ? 'bg-amber-500/10' : 'hover:bg-gray-800/50'}`}
                    >
                      <button
                        onClick={() => handleSwitchConversation(conv.conversation_id)}
                        className="w-full text-left px-4 py-3"
                      >
                        <div className="flex items-start justify-between gap-2">
                          <div className="flex-1 min-w-0">
                            <p className={`text-sm font-medium truncate ${conversationId === conv.conversation_id ? 'text-amber-400' : 'text-gray-300'}`}>
                              {conv.title}
                            </p>
                            {conv.last_message && (
                              <p className="text-xs text-gray-500 truncate mt-0.5">
                                {conv.last_message_role === 'assistant' ? 'AI: ' : 'You: '}{conv.last_message}
                              </p>
                            )}
                            <div className="flex items-center gap-2 mt-1">
                              <span className="text-xs text-gray-600">
                                {formatConversationDate(conv.updated_at)}
                              </span>
                              <span className="text-xs text-gray-600">•</span>
                              <span className="text-xs text-gray-600">
                                {conv.message_count} messages
                              </span>
                            </div>
                          </div>
                          {conversationId === conv.conversation_id && (
                            <div className="w-2 h-2 bg-amber-500 rounded-full flex-shrink-0 mt-1.5" />
                          )}
                        </div>
                      </button>
                      
                      {/* Delete button (shows on hover) */}
                      <button
                        onClick={(e) => {
                          e.stopPropagation();
                          setDeleteConfirmId(conv.conversation_id);
                        }}
                        className="absolute right-2 top-1/2 -translate-y-1/2 p-1.5 opacity-0 group-hover:opacity-100 hover:bg-red-500/20 hover:text-red-400 text-gray-500 rounded transition-all"
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
        <div className="flex-1 overflow-y-auto px-5 py-4 bg-gray-800/30 space-y-4">
          {messages.length === 0 ? (
            <div className="flex flex-col items-center justify-center h-full text-center">
              <div className="w-16 h-16 rounded-full bg-amber-500/10 border border-amber-500/30 flex items-center justify-center mb-4">
                <svg className="w-8 h-8 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                </svg>
              </div>
              <h3 className="text-base font-medium text-gray-200 mb-2">Start a conversation</h3>
              <p className="text-sm text-gray-400 mb-6">Ask me anything about your appointments</p>

              {/* Suggested Questions (dynamic from assistant or default suggestions) */}
              {lastSuggestions && Array.isArray(lastSuggestions) && lastSuggestions.length > 0 ? (
                <div className="w-full space-y-2 mb-4">
                  {lastSuggestions.map((question, idx) => (
                    <button
                      key={`dyn-${idx}`}
                      onClick={() => handleSuggestedQuestion(question)}
                      className="w-full text-left px-4 py-3 rounded-lg bg-gray-900/50 border border-amber-500/20 hover:bg-gray-900 hover:border-amber-500/40 transition-all text-sm text-gray-300 hover:text-amber-400 group"
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
                  <div className="flex items-center justify-center gap-2 text-gray-400">
                    <div className="w-2 h-2 bg-amber-500 rounded-full animate-bounce" />
                    <div className="w-2 h-2 bg-amber-500 rounded-full animate-bounce" style={{ animationDelay: '0.1s' }} />
                    <div className="w-2 h-2 bg-amber-500 rounded-full animate-bounce" style={{ animationDelay: '0.2s' }} />
                  </div>
                </div>
              ) : Array.isArray(suggestedQuestions) && suggestedQuestions.length > 0 ? (
                <div className="w-full space-y-2">
                  {suggestedQuestions.map((question, index) => (
                    <button
                      key={index}
                      onClick={() => handleSuggestedQuestion(question)}
                      className="w-full text-left px-4 py-3 rounded-lg bg-gray-900/50 border border-amber-500/20 hover:bg-gray-900 hover:border-amber-500/40 transition-all text-sm text-gray-300 hover:text-amber-400 group"
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
              ) : (
                <p className="text-sm text-gray-500">Feel free to ask me anything!</p>
              )}
            </div>
          ) : (
            <>
              {messages.map((message) => (
                <ChatbotMessage key={message.id} message={message} />
              ))}
              {loading && (
                <div className="flex justify-start">
                  <div className="bg-gray-900 border border-amber-500/20 rounded-xl px-4 py-3 flex items-center gap-2">
                    <div className="flex space-x-1">
                      <div className="w-2 h-2 bg-amber-500 rounded-full animate-bounce" />
                      <div className="w-2 h-2 bg-amber-500 rounded-full animate-bounce" style={{ animationDelay: '0.1s' }} />
                      <div className="w-2 h-2 bg-amber-500 rounded-full animate-bounce" style={{ animationDelay: '0.2s' }} />
                    </div>
                    <span className="text-xs text-gray-400">Typing...</span>
                  </div>
                </div>
              )}
              <div ref={messagesEndRef} />
            </>
          )}
        </div>

        {/* Error Message */}
        {error && (
          <div className="px-5 py-3 bg-red-500/10 border-b border-red-500/20 flex-shrink-0">
            <div className="flex items-center justify-between">
              <div className="flex items-center gap-2">
                <svg className="w-4 h-4 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <p className="text-sm text-red-300">{error}</p>
              </div>
              <button
                onClick={() => setError(null)}
                className="text-red-400 hover:text-red-300 transition-colors"
              >
                <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                </svg>
              </button>
            </div>
          </div>
        )}

        {/* Input Area */}
        <ChatbotInput
          inputValue={inputValue}
          setInputValue={setInputValue}
          onSend={handleSendMessage}
          onKeyPress={handleKeyPress}
          isLoading={loading}
        />
      </div>

      {/* Clear Confirmation Modal */}
      {showClearConfirm && (
        <div className="fixed inset-0 bg-black/50 backdrop-blur-sm z-[60] flex items-center justify-center p-4">
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
        <div className="fixed inset-0 bg-black/50 backdrop-blur-sm z-[60] flex items-center justify-center p-4">
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
