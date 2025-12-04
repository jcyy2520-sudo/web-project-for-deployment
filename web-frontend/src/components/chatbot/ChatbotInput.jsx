import React from 'react';

const ChatbotInput = ({
  inputValue,
  setInputValue,
  onSend,
  onKeyPress,
  isLoading
}) => {
  return (
    <div className="border-t border-gray-200 p-4 bg-white rounded-b-lg">
      <div className="flex gap-2">
        <textarea
          value={inputValue}
          onChange={(e) => setInputValue(e.target.value)}
          onKeyPress={onKeyPress}
          placeholder="Type your message... (Shift+Enter for new line)"
          disabled={isLoading}
          className="flex-1 resize-none px-3 py-2 bg-gray-50 border border-gray-300 rounded-lg text-gray-900 placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-amber-500 focus:border-transparent transition-all disabled:bg-gray-100 disabled:cursor-not-allowed"
          rows={2}
        />
        <button
          onClick={onSend}
          disabled={isLoading || !inputValue.trim()}
          className="px-4 py-2 bg-amber-500 text-white rounded-lg hover:bg-amber-600 transition-colors disabled:bg-gray-300 disabled:cursor-not-allowed flex items-center justify-center"
          title="Send message"
          aria-label="Send message"
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
      <p className="text-xs text-gray-500 mt-2">Press Enter to send, Shift+Enter for new line</p>
    </div>
  );
};

export default ChatbotInput;
