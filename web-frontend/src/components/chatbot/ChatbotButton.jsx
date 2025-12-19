import React, { useState } from 'react';
import ChatbotModal from './ChatbotModal';

const ChatbotButton = ({ className = '' }) => {
  const [isOpen, setIsOpen] = useState(false);

  return (
    <>
      {/* Floating Button - Responsive positioning to avoid mobile nav collision */}
      <button
        onClick={() => setIsOpen(true)}
        className={`fixed bottom-20 sm:bottom-6 right-4 sm:right-6 bg-gray-900 border-2 border-amber-500/50 text-amber-500 rounded-full shadow-lg hover:shadow-amber-500/20 hover:border-amber-500 hover:bg-gray-800 transform hover:scale-105 transition-all duration-200 z-40 flex items-center justify-center w-12 h-12 sm:w-14 sm:h-14 group ${className}`}
        title="Chat Assistant"
        aria-label="Open Chatbot Assistant"
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

      {/* Modal */}
      {isOpen && <ChatbotModal onClose={() => setIsOpen(false)} />}
    </>
  );
};

export default ChatbotButton;
