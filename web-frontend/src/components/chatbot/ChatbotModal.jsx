import React, { useState, useEffect } from 'react';
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
    setError
  } = useChatbot();

  const [inputValue, setInputValue] = useState('');
  const [showClearConfirm, setShowClearConfirm] = useState(false);

  useEffect(() => {
    messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' });
  }, [messages, messagesEndRef]);

  const handleSendMessage = () => {
    if (inputValue.trim()) {
      sendMessage(inputValue);
      setInputValue('');
    }
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
        className="fixed inset-0 bg-black bg-opacity-30 z-50 transition-opacity duration-200"
        onClick={onClose}
      />

      {/* Modal */}
      <div className="fixed bottom-24 right-6 w-96 bg-white rounded-lg shadow-2xl flex flex-col max-h-[600px] z-50 border border-gray-200">
        {/* Header */}
        <div className="bg-gradient-to-r from-amber-500 to-amber-600 text-white p-4 rounded-t-lg flex justify-between items-center">
          <div>
            <h2 className="font-semibold text-lg">Chat Assistant</h2>
            <p className="text-xs text-amber-100">AI-powered support</p>
          </div>
          <div className="flex gap-2">
            <button
              onClick={() => setShowClearConfirm(true)}
              className="p-2 hover:bg-amber-700 rounded-lg transition-colors"
              title="Clear history"
              aria-label="Clear chat history"
            >
              <svg
                className="w-4 h-4"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
              >
                <path
                  strokeLinecap="round"
                  strokeLinejoin="round"
                  strokeWidth={2}
                  d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"
                />
              </svg>
            </button>
            <button
              onClick={onClose}
              className="p-2 hover:bg-amber-700 rounded-lg transition-colors"
              title="Close chat"
              aria-label="Close chat"
            >
              <svg
                className="w-4 h-4"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
              >
                <path
                  strokeLinecap="round"
                  strokeLinejoin="round"
                  strokeWidth={2}
                  d="M6 18L18 6M6 6l12 12"
                />
              </svg>
            </button>
          </div>
        </div>

        {/* Messages Container */}
        <div className="flex-1 overflow-y-auto p-4 bg-gray-50 space-y-4">
          {messages.length === 0 ? (
            <div className="flex items-center justify-center h-full text-gray-500 text-center">
              <div>
                <svg
                  className="w-12 h-12 mx-auto mb-2 text-gray-300"
                  fill="none"
                  stroke="currentColor"
                  viewBox="0 0 24 24"
                >
                  <path
                    strokeLinecap="round"
                    strokeLinejoin="round"
                    strokeWidth={2}
                    d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"
                  />
                </svg>
                <p className="text-sm">Start a conversation</p>
                <p className="text-xs text-gray-400 mt-1">Ask me anything about your appointments</p>
              </div>
            </div>
          ) : (
            <>
              {messages.map((message) => (
                <ChatbotMessage key={message.id} message={message} />
              ))}
              {loading && (
                <div className="flex items-center space-x-2">
                  <div className="flex space-x-2">
                    <div className="w-2 h-2 bg-gray-400 rounded-full animate-bounce" />
                    <div className="w-2 h-2 bg-gray-400 rounded-full animate-bounce delay-100" />
                    <div className="w-2 h-2 bg-gray-400 rounded-full animate-bounce delay-200" />
                  </div>
                </div>
              )}
              <div ref={messagesEndRef} />
            </>
          )}
        </div>

        {/* Error Message */}
        {error && (
          <div className="px-4 py-2 bg-red-50 border-b border-red-200">
            <div className="flex items-center justify-between">
              <p className="text-sm text-red-600">{error}</p>
              <button
                onClick={() => setError(null)}
                className="text-red-400 hover:text-red-600"
              >
                ×
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
        <div className="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center">
          <div className="bg-white rounded-lg p-6 max-w-sm mx-4">
            <h3 className="text-lg font-semibold text-gray-900 mb-2">Clear Chat History?</h3>
            <p className="text-gray-600 mb-6">This will delete all your current conversation history.</p>
            <div className="flex gap-3">
              <button
                onClick={() => setShowClearConfirm(false)}
                className="flex-1 px-4 py-2 bg-gray-200 text-gray-800 rounded-lg hover:bg-gray-300 transition-colors"
              >
                Cancel
              </button>
              <button
                onClick={() => {
                  clearHistory();
                  setShowClearConfirm(false);
                }}
                className="flex-1 px-4 py-2 bg-red-500 text-white rounded-lg hover:bg-red-600 transition-colors"
              >
                Clear
              </button>
            </div>
          </div>
        </div>
      )}
    </>
  );
};

export default ChatbotModal;
