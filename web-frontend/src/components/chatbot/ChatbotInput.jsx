import React, { useState, useRef, useEffect } from 'react';

const MAX_MESSAGE_LENGTH = 2000;

const ChatbotInput = ({
  inputValue,
  setInputValue,
  onSend,
  onKeyPress,
  isLoading,
  isDarkMode = true,
  disabled = false,
  disabledMessage = '',
  mobileActions = null,
}) => {
  const isInputDisabled = isLoading || disabled;
  const isOverLimit = inputValue.length > MAX_MESSAGE_LENGTH;
  const [showMobileMenu, setShowMobileMenu] = useState(false);
  const menuRef = useRef(null);

  // Close mobile menu on outside click
  useEffect(() => {
    if (!showMobileMenu) return;
    const handler = (e) => {
      if (menuRef.current && !menuRef.current.contains(e.target)) {
        setShowMobileMenu(false);
      }
    };
    document.addEventListener('mousedown', handler);
    return () => document.removeEventListener('mousedown', handler);
  }, [showMobileMenu]);

  // Handle key events - use onKeyDown for better compatibility
  const handleKeyDown = (e) => {
    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault();
      e.stopPropagation();
      if (inputValue.trim() && !isInputDisabled && !isOverLimit) {
        onSend();
      }
    }
  };

  const handleChange = (e) => {
    const value = e.target.value;
    // Allow typing up to a soft limit slightly above max to show the counter warning
    if (value.length <= MAX_MESSAGE_LENGTH + 100) {
      setInputValue(value);
    }
  };

  return (
    <div className={`p-4 flex-shrink-0 ${isDarkMode ? 'bg-gray-900/40' : 'bg-white/60 backdrop-blur-sm'}`}>
      {/* Mobile menu dropdown — rendered outside the clipping container */}
      {mobileActions && showMobileMenu && (
        <div className="md:hidden relative z-40" ref={menuRef}>
          <div className={`absolute bottom-2 right-4 w-48 rounded-xl border shadow-xl overflow-hidden ${
            isDarkMode ? 'bg-gray-800 border-gray-700' : 'bg-white border-gray-200'
          }`}>
            <button
              onClick={() => { mobileActions.onNewConversation(); setShowMobileMenu(false); }}
              className={`w-full text-left px-4 py-2.5 text-sm flex items-center gap-2.5 transition-colors ${
                isDarkMode ? 'text-gray-300 hover:bg-gray-700 hover:text-amber-400' : 'text-gray-700 hover:bg-gray-50 hover:text-purple-600'
              }`}
            >
              <svg className="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4v16m8-8H4" />
              </svg>
              New Conversation
            </button>
            <button
              onClick={() => { mobileActions.onToggleHistory(); setShowMobileMenu(false); }}
              className={`w-full text-left px-4 py-2.5 text-sm flex items-center gap-2.5 transition-colors ${
                mobileActions.showHistory
                  ? (isDarkMode ? 'bg-amber-500/10 text-amber-400' : 'bg-purple-50 text-purple-600')
                  : (isDarkMode ? 'text-gray-300 hover:bg-gray-700 hover:text-amber-400' : 'text-gray-700 hover:bg-gray-50 hover:text-purple-600')
              }`}
            >
              <svg className="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
              Chat History
            </button>
            <div className={`border-t ${isDarkMode ? 'border-gray-700' : 'border-gray-100'}`} />
            <button
              onClick={() => { mobileActions.onClearHistory(); setShowMobileMenu(false); }}
              className={`w-full text-left px-4 py-2.5 text-sm flex items-center gap-2.5 transition-colors ${
                isDarkMode ? 'text-red-400 hover:bg-red-500/10' : 'text-red-500 hover:bg-red-50'
              }`}
            >
              <svg className="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
              </svg>
              Clear History
            </button>
            <div className={`px-4 py-2.5 flex items-center gap-2.5 border-t ${isDarkMode ? 'border-gray-700' : 'border-gray-100'}`}>
              <div className={`w-5 h-5 rounded-full flex items-center justify-center text-[10px] font-medium flex-shrink-0 ${
                isDarkMode ? 'bg-amber-500/20 text-amber-400 border border-amber-500/30' : 'bg-purple-100 text-purple-600 border border-purple-200'
              }`}>
                {mobileActions.userRole === 'guest' ? '?' : mobileActions.userRole.charAt(0).toUpperCase()}
              </div>
              <span className={`text-xs ${isDarkMode ? 'text-gray-400' : 'text-gray-500'}`}>
                {mobileActions.userRole === 'guest' ? 'Guest' : mobileActions.userRole.charAt(0).toUpperCase() + mobileActions.userRole.slice(1)}
              </span>
            </div>
          </div>
        </div>
      )}
      {/* Main input container — reference-inspired rounded box */}
      <div className={`rounded-2xl border overflow-hidden transition-all ${
        isDarkMode
          ? 'bg-gray-800/60 border-gray-700/40 focus-within:border-amber-500/40 focus-within:shadow-lg focus-within:shadow-amber-500/5'
          : 'bg-white/90 border-gray-200/60 focus-within:border-purple-300 focus-within:shadow-lg focus-within:shadow-purple-100/50'
      }`}>
        {/* Text area row */}
        <div className="flex items-end gap-2 px-4 pt-3 pb-2">
          <textarea
            value={inputValue}
            onChange={handleChange}
            onKeyDown={handleKeyDown}
            placeholder={disabled ? (disabledMessage || 'Message limit reached — start a new conversation') : 'Ask whatever you want....'}
            disabled={isInputDisabled}
            maxLength={MAX_MESSAGE_LENGTH + 100}
            className={`flex-1 resize-none text-sm bg-transparent border-none outline-none disabled:opacity-50 disabled:cursor-not-allowed leading-relaxed ${
              isDarkMode
                ? 'text-gray-100 placeholder-gray-500'
                : 'text-gray-800 placeholder-gray-400'
            }`}
            rows={1}
            style={{ minHeight: '24px', maxHeight: '120px' }}
            onInput={(e) => {
              e.target.style.height = 'auto';
              e.target.style.height = Math.min(e.target.scrollHeight, 120) + 'px';
            }}
          />
          {/* AI badge */}
          <div className={`flex items-center gap-1 px-2 py-1 rounded-lg text-xs whitespace-nowrap flex-shrink-0 mb-0.5 ${
            isDarkMode ? 'bg-amber-500/10 text-amber-400' : 'bg-purple-50 text-purple-500'
          }`}>
            <svg className="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 10V3L4 14h7v7l9-11h-7z" />
            </svg>
            AI
          </div>
        </div>

        {/* Bottom bar — actions + counter + send */}
        <div className={`flex items-center justify-between px-4 py-2.5 border-t ${
          isDarkMode ? 'border-gray-700/20' : 'border-gray-100'
        }`}>
          <div className="flex items-center gap-1">
            <p className={`text-[11px] ${isDarkMode ? 'text-gray-500' : 'text-gray-400'}`}>
              AI can make mistakes. Please verify important information.
            </p>
          </div>
          <div className="flex items-center gap-3">
            {/* Mobile menu trigger — visible only on mobile (md:hidden) */}
            {mobileActions && (
              <button
                type="button"
                onClick={() => setShowMobileMenu(!showMobileMenu)}
                className={`p-1.5 rounded-lg transition-colors md:hidden ${
                  showMobileMenu
                    ? (isDarkMode ? 'bg-amber-500/20 text-amber-400' : 'bg-purple-100 text-purple-600')
                    : (isDarkMode ? 'text-gray-500 hover:text-amber-400 hover:bg-gray-700' : 'text-gray-400 hover:text-purple-500 hover:bg-gray-100')
                }`}
                title="Menu"
              >
                <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 6.75a.75.75 0 110-1.5.75.75 0 010 1.5zM12 12.75a.75.75 0 110-1.5.75.75 0 010 1.5zM12 18.75a.75.75 0 110-1.5.75.75 0 010 1.5z" />
                </svg>
              </button>
            )}
            {/* Character counter */}
            <span className={`text-xs tabular-nums ${
              isOverLimit
                ? 'text-red-400 font-medium'
                : inputValue.length > MAX_MESSAGE_LENGTH * 0.8
                ? (isDarkMode ? 'text-amber-400/70' : 'text-amber-500')
                : (isDarkMode ? 'text-gray-600' : 'text-gray-300')
            }`}>
              {inputValue.length}/{MAX_MESSAGE_LENGTH}
            </span>
            {/* Send button */}
            <button
              type="button"
              onClick={(e) => {
                e.preventDefault();
                e.stopPropagation();
                if (inputValue.trim() && !isOverLimit) {
                  onSend();
                }
              }}
              disabled={isInputDisabled || !inputValue.trim() || isOverLimit}
              className={`w-8 h-8 rounded-full flex items-center justify-center transition-all disabled:opacity-30 disabled:cursor-not-allowed ${
                inputValue.trim() && !isOverLimit
                  ? (isDarkMode
                    ? 'bg-amber-500 text-gray-900 hover:bg-amber-400 shadow-lg shadow-amber-500/20'
                    : 'bg-gradient-to-r from-orange-500 via-pink-500 to-purple-500 text-white hover:shadow-lg hover:shadow-purple-200')
                  : (isDarkMode
                    ? 'bg-gray-700 text-gray-500'
                    : 'bg-gray-200 text-gray-400')
              }`}
              title="Send message"
              aria-label="Send message"
            >
              <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2.5} d="M5 12h14M12 5l7 7-7 7" />
              </svg>
            </button>
          </div>
        </div>
      </div>
    </div>
  );
};

export default ChatbotInput;
