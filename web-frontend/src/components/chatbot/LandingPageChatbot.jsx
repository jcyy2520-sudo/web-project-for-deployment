import React, { useState, useRef, useEffect } from 'react';
import axios from 'axios';

const LandingPageChatbot = () => {
  const [isOpen, setIsOpen] = useState(false);
  const [messages, setMessages] = useState([]);
  const [inputValue, setInputValue] = useState('');
  const [isLoading, setIsLoading] = useState(false);
  const [error, setError] = useState(null);
  const [conversationId, setConversationId] = useState(null);
  const messagesEndRef = useRef(null);
  const STORAGE_KEY = 'chatbot_guest_messages';
  const CONVERSATION_ID_KEY = 'chatbot_conversation_id';

  const quickQuestions = [
    "How do I book an appointment?",
    "What services do you offer?",
    "How do I register?",
    "What are your business hours?"
  ];

  // Load guest messages from localStorage on mount
  useEffect(() => {
    const savedMessages = localStorage.getItem(STORAGE_KEY);
    const savedConvId = localStorage.getItem(CONVERSATION_ID_KEY);
    
    if (savedMessages) {
      try {
        setMessages(JSON.parse(savedMessages));
      } catch (e) {
        console.warn('Failed to load saved messages:', e);
        localStorage.removeItem(STORAGE_KEY);
      }
    }
    
    if (!savedConvId) {
      const newConvId = `chat_${Date.now()}_${Math.random().toString(36).substr(2, 9)}`;
      localStorage.setItem(CONVERSATION_ID_KEY, newConvId);
      setConversationId(newConvId);
    } else {
      setConversationId(savedConvId);
    }
  }, []);

  // Persist messages to localStorage whenever they change
  useEffect(() => {
    if (messages.length > 0) {
      localStorage.setItem(STORAGE_KEY, JSON.stringify(messages));
    }
  }, [messages]);

  useEffect(() => {
    if (messagesEndRef.current) {
      messagesEndRef.current.scrollIntoView({ behavior: 'smooth' });
    }
  }, [messages]);

  const handleQuickQuestion = (question) => {
    sendMessage(question);
  };

  // Validate API response structure
  const validateResponse = (response) => {
    if (!response || typeof response !== 'object') {
      throw new Error('Invalid response: Not an object');
    }

    if (response.success === false) {
      throw new Error(response.message || 'API returned success: false');
    }

    const text = typeof response.ai_response === 'string' && response.ai_response.trim().length > 0
      ? response.ai_response.trim()
      : (typeof response.message === 'string' ? response.message.trim() : '');

    if (!text) {
      throw new Error('Invalid response: Missing ai_response/message');
    }

    if (!response.conversation_id || typeof response.conversation_id !== 'string') {
      console.warn('Warning: Missing conversation_id in response');
    }

    return {
      text,
      conversationId: response.conversation_id || conversationId,
      meta: (response.meta && typeof response.meta === 'object') ? response.meta : {}
    };
  };

  const sendMessage = async (messageText = inputValue) => {
    if (!messageText.trim()) return;

    const userMessage = {
      id: Date.now(),
      message: messageText,
      role: 'user',
      created_at: new Date().toISOString()
    };

    setMessages(prev => [...prev, userMessage]);
    setInputValue('');
    setIsLoading(true);
    setError(null);

    try {
      // Make API request with validation
      const apiResponse = await axios.post('/api/chatbot/send-message', {
        message: messageText,
        conversation_id: conversationId
      }, {
        timeout: 30000,
        validateStatus: () => true // Don't throw on any status
      });

      // Validate response structure
      const validatedResponse = validateResponse(apiResponse.data);
      
      // Update conversation ID if it changed
      if (validatedResponse.conversationId !== conversationId) {
        setConversationId(validatedResponse.conversationId);
        localStorage.setItem(CONVERSATION_ID_KEY, validatedResponse.conversationId);
      }

      const botMessage = {
        id: Date.now() + 1,
        message: validatedResponse.text,
        role: 'assistant',
        created_at: new Date().toISOString(),
        meta: validatedResponse.meta
      };
      
      setMessages(prev => [...prev, botMessage]);
    } catch (error) {
      console.error('Chat error:', error);
      
      // Determine user-friendly error message
      let errorMsg = 'Sorry, I encountered an error. Please try again.';
      
      if (error.response?.status === 401) {
        errorMsg = 'Authentication error. Please refresh the page.';
      } else if (error.response?.status === 422) {
        errorMsg = 'Invalid message format. Please try again.';
      } else if (error.response?.status === 500) {
        errorMsg = 'Server error. Our team has been notified. Please try again later.';
      } else if (error.response?.data?.message) {
        errorMsg = error.response.data.message;
      } else if (error.response?.data?.error) {
        errorMsg = 'API Error: ' + error.response.data.error;
      } else if (error.code === 'ECONNABORTED') {
        errorMsg = 'Request timeout. Please check your connection and try again.';
      } else if (error.message === 'Network Error') {
        errorMsg = 'Network error. Please check your internet connection.';
      } else if (error.message?.includes('Invalid response')) {
        errorMsg = 'Server returned an invalid response. Please try again.';
      }
      
      setError(errorMsg);
      
      // Remove user message on error so they can retry
      setMessages(prev => prev.filter(msg => msg.id !== userMessage.id));
    } finally {
      setIsLoading(false);
    }
  };

  const handleKeyPress = (e) => {
    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault();
      sendMessage();
    }
  };

  const clearChat = () => {
    if (confirm('Clear chat history?')) {
      setMessages([]);
      localStorage.removeItem(STORAGE_KEY);
      const newConvId = `chat_${Date.now()}_${Math.random().toString(36).substr(2, 9)}`;
      setConversationId(newConvId);
      localStorage.setItem(CONVERSATION_ID_KEY, newConvId);
    }
  };

  if (!isOpen) {
    return (
      <button
        onClick={() => setIsOpen(true)}
        className="fixed bottom-6 right-6 bg-gray-900 border-2 border-amber-500/50 text-amber-500 rounded-full shadow-lg hover:shadow-amber-500/20 hover:border-amber-500 hover:bg-gray-800 transform hover:scale-105 transition-all duration-200 z-40 flex items-center justify-center w-14 h-14 group"
        title="Chat with us"
        aria-label="Open Chat Assistant"
      >
        <svg
          className="w-6 h-6 group-hover:scale-110 transition-transform"
          fill="none"
          stroke="currentColor"
          viewBox="0 0 24 24"
        >
          <path
            strokeLinecap="round"
            strokeLinejoin="round"
            strokeWidth={2}
            d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"
          />
        </svg>
      </button>
    );
  }

  return (
    <>
      {/* Backdrop */}
      <div
        className="fixed inset-0 bg-black/20 backdrop-blur-sm z-50 transition-all"
        onClick={() => setIsOpen(false)}
      />

      {/* Chat Window */}
      <div className="fixed bottom-24 right-6 w-[400px] bg-gray-900 rounded-xl shadow-2xl flex flex-col h-[550px] z-50 border border-amber-500/30 overflow-hidden">
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
              <p className="text-xs text-gray-400">We're here to help</p>
            </div>
          </div>
          <div className="flex items-center gap-2">
            {messages.length > 0 && (
              <button
                onClick={clearChat}
                className="text-gray-400 hover:text-amber-500 hover:bg-amber-500/10 rounded-lg p-2 transition-all"
                title="Clear chat history"
              >
                <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                </svg>
              </button>
            )}
            <button
              onClick={() => setIsOpen(false)}
              className="text-gray-400 hover:text-amber-500 hover:bg-amber-500/10 rounded-lg p-2 transition-all"
            >
              <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>
        </div>

        {/* Messages Container */}
        <div className="flex-1 overflow-y-auto px-5 py-4 bg-gray-800/30 space-y-4">
          {error && (
            <div className="px-4 py-3 bg-red-500/10 border border-red-500/30 rounded-lg flex items-start gap-3">
              <svg className="w-5 h-5 text-red-400 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
              <div className="flex-1">
                <p className="text-sm text-red-300">{error}</p>
              </div>
              <button
                onClick={() => setError(null)}
                className="text-red-400 hover:text-red-300 transition-colors flex-shrink-0"
              >
                <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                </svg>
              </button>
            </div>
          )}
          {messages.length === 0 ? (
            <div className="flex flex-col items-center justify-center h-full text-center">
              <div className="w-16 h-16 rounded-full bg-amber-500/10 border border-amber-500/30 flex items-center justify-center mb-4">
                <svg className="w-8 h-8 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                </svg>
              </div>
              <h3 className="text-base font-medium text-gray-200 mb-2">Start a conversation</h3>
              <p className="text-sm text-gray-400 mb-6">Choose a question or type your own</p>
              <div className="w-full space-y-2">
                {Array.isArray(quickQuestions) && quickQuestions.length > 0 ? (
                  quickQuestions.map((question, index) => (
                    <button
                      key={index}
                      onClick={() => handleQuickQuestion(question)}
                      className="w-full text-left px-4 py-3 rounded-lg bg-gray-900/50 border border-amber-500/20 hover:bg-gray-900 hover:border-amber-500/40 transition-all text-sm text-gray-300 hover:text-amber-400 group"
                    >
                      <span className="flex items-center gap-2">
                        <svg className="w-4 h-4 text-amber-500/50 group-hover:text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5l7 7-7 7" />
                        </svg>
                        {question}
                      </span>
                    </button>
                  ))
                ) : (
                  <p className="text-sm text-gray-500 text-center py-4">No suggestions available. Feel free to type your question below.</p>
                )}
              </div>
            </div>
          ) : (
            <>

              {messages.map((message) => (
                <div
                  key={message.id}
                  className={`flex ${message.role === 'user' ? 'justify-end' : 'justify-start'}`}
                >
                  <div
                    className={`max-w-[75%] rounded-xl px-4 py-3 ${
                      message.role === 'user'
                        ? 'bg-amber-500 text-white shadow-lg shadow-amber-500/20'
                        : 'bg-gray-900 text-gray-100 border border-amber-500/20'
                    }`}
                  >
                    <p className="text-sm leading-relaxed break-words">{message.message}</p>
                  </div>
                </div>
              ))}

              {isLoading && (
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

        {/* Input Area */}
        <div className="border-t border-amber-500/20 p-4 bg-gray-900 flex-shrink-0">
          <div className="flex gap-2">
            <input
              type="text"
              value={inputValue}
              onChange={(e) => setInputValue(e.target.value)}
              onKeyPress={handleKeyPress}
              placeholder="Type your message..."
              disabled={isLoading}
              className="flex-1 px-4 py-3 border border-amber-500/20 rounded-lg bg-gray-800 text-gray-100 placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-amber-500/50 focus:border-amber-500/50 disabled:opacity-50 disabled:cursor-not-allowed text-sm transition-all"
            />
            <button
              onClick={() => sendMessage()}
              disabled={isLoading || !inputValue.trim()}
              className="px-4 py-3 bg-amber-500 text-white rounded-lg hover:bg-amber-600 disabled:opacity-50 disabled:cursor-not-allowed transition-all shadow-lg shadow-amber-500/20 hover:shadow-amber-500/30 flex items-center justify-center"
            >
              <svg
                className="w-5 h-5"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
              >
                <path
                  strokeLinecap="round"
                  strokeLinejoin="round"
                  strokeWidth={2}
                  d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"
                />
              </svg>
            </button>
          </div>
        </div>
      </div>
    </>
  );
};

export default LandingPageChatbot;
