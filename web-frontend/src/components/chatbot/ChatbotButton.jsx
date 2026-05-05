import React, { useState, useEffect, useRef, useCallback } from 'react';
import { useLocation, useNavigate } from 'react-router-dom';
import ChatbotModal from './ChatbotModal';

const ChatbotButton = ({ className = '', isDarkMode }) => {
  const [resolvedDark, setResolvedDark] = useState(isDarkMode ?? true);
  const [showBubble, setShowBubble] = useState(true);
  const [showModal, setShowModal] = useState(false);
  const location = useLocation();
  const navigate = useNavigate();

  // Determine if we're on the landing page (guest view)
  const isLandingPage = location.pathname === '/';

  useEffect(() => {
    if (typeof isDarkMode === 'boolean') {
      setResolvedDark(isDarkMode);
    }
  }, [isDarkMode]);

  const buttonRef = useRef(null);
  const draggingRef = useRef(false);
  const movedRef = useRef(false);
  const pointerOffsetRef = useRef({ x: 0, y: 0 });
  const dragPosRef = useRef(null);
  const dragStartPosRef = useRef({ x: 0, y: 0 });
  const [pos, setPos] = useState(null);
  const [isDragging, setIsDragging] = useState(false);

  const DRAG_THRESHOLD = 8;
  const STORAGE_KEY = 'chatbot_position_v1';

  const isMobile = () => typeof window !== 'undefined' && window.innerWidth < 640;
  const getLandingPageStyle = () => ({
    position: 'fixed',
    right: isMobile() ? '16px' : '24px',
    bottom: isMobile() ? '20px' : '24px',
    zIndex: 9999,
    cursor: 'pointer',
  });

  useEffect(() => {
    try {
      const raw = localStorage.getItem(STORAGE_KEY);
      if (raw) {
        const parsed = JSON.parse(raw);
        if (parsed && typeof parsed.left === 'number' && typeof parsed.top === 'number') {
          const btnW = 80;
          const btnH = 80;
          const bottomNavHeight = isMobile() ? 80 : 0;
          const maxTop = window.innerHeight - btnH - bottomNavHeight - 16;
          const maxLeft = window.innerWidth - btnW - 8;
          const adjustedTop = Math.min(Math.max(parsed.top, 8), maxTop);
          const adjustedLeft = Math.min(Math.max(parsed.left, 8), maxLeft);
          setPos({ left: adjustedLeft, top: adjustedTop });
          return;
        }
      }
    } catch (e) { /* ignore */ }

    try {
      const btnW = 80;
      const btnH = 80;
      const right = 24;
      const bottomNavHeight = isMobile() ? 80 : 0;
      const bottom = 80 + bottomNavHeight;
      const left = Math.max(12, window.innerWidth - btnW - right);
      const top = Math.max(12, window.innerHeight - btnH - bottom);
      setPos({ left, top });
    } catch (e) {
      setPos({ left: 12, top: 12 });
    }
  }, []);

  // Re-clamp position on window resize (e.g. desktop → mobile)
  useEffect(() => {
    const handleResize = () => {
      setPos(prev => {
        if (!prev) return prev;
        const btnW = 80;
        const btnH = 80;
        const bottomNavHeight = isMobile() ? 80 : 0;
        const maxLeft = window.innerWidth - btnW - 8;
        const maxTop = window.innerHeight - btnH - bottomNavHeight - 16;
        const newLeft = Math.min(Math.max(prev.left, 8), maxLeft);
        const newTop = Math.min(Math.max(prev.top, 8), maxTop);
        if (newLeft !== prev.left || newTop !== prev.top) {
          const clamped = { left: newLeft, top: newTop };
          try { localStorage.setItem(STORAGE_KEY, JSON.stringify(clamped)); } catch (e) { /* ignore */ }
          return clamped;
        }
        return prev;
      });
    };
    window.addEventListener('resize', handleResize);
    return () => window.removeEventListener('resize', handleResize);
  }, []);

  const persistPos = useCallback((p) => {
    try { localStorage.setItem(STORAGE_KEY, JSON.stringify(p)); } catch (e) { /* ignore */ }
  }, []);

  const clamp = useCallback((value, min, max) => Math.min(Math.max(value, min), max), []);

  const handlePointerMove = useCallback((e) => {
    if (!draggingRef.current || !buttonRef.current) return;
    const point = e.touches ? e.touches[0] : e;
    const deltaX = Math.abs(point.clientX - dragStartPosRef.current.x);
    const deltaY = Math.abs(point.clientY - dragStartPosRef.current.y);
    if (deltaX < DRAG_THRESHOLD && deltaY < DRAG_THRESHOLD) return;
    movedRef.current = true;
    setIsDragging(true);
    const btn = buttonRef.current;
    const btnW = btn.offsetWidth;
    const btnH = btn.offsetHeight;
    const bottomNavHeight = isMobile() ? 80 : 0;
    const maxTop = window.innerHeight - btnH - bottomNavHeight - 8;
    const left = clamp(point.clientX - pointerOffsetRef.current.x, 8, window.innerWidth - btnW - 8);
    const top = clamp(point.clientY - pointerOffsetRef.current.y, 8, maxTop);
    dragPosRef.current = { left, top };
    btn.style.left = `${left}px`;
    btn.style.top = `${top}px`;
  }, [clamp]);

  const handlePointerUp = useCallback(() => {
    if (draggingRef.current) {
      draggingRef.current = false;
      setIsDragging(false);
      if (dragPosRef.current) {
        setPos(dragPosRef.current);
        persistPos(dragPosRef.current);
        dragPosRef.current = null;
      }
      document.removeEventListener('pointermove', handlePointerMove);
      document.removeEventListener('touchmove', handlePointerMove);
      document.removeEventListener('pointerup', handlePointerUp);
      document.removeEventListener('touchend', handlePointerUp);
    }
  }, [persistPos, handlePointerMove]);

  const handlePointerDown = useCallback((e) => {
    draggingRef.current = true;
    movedRef.current = false;
    const point = e.touches ? e.touches[0] : e;
    const rect = buttonRef.current?.getBoundingClientRect();
    dragStartPosRef.current = { x: point.clientX, y: point.clientY };
    pointerOffsetRef.current = {
      x: point.clientX - (rect?.left || 0),
      y: point.clientY - (rect?.top || 0),
    };
    document.addEventListener('pointermove', handlePointerMove, { passive: true });
    document.addEventListener('touchmove', handlePointerMove, { passive: true });
    document.addEventListener('pointerup', handlePointerUp, { passive: true });
    document.addEventListener('touchend', handlePointerUp, { passive: true });
  }, [handlePointerMove, handlePointerUp]);

  useEffect(() => {
    return () => {
      document.removeEventListener('pointermove', handlePointerMove);
      document.removeEventListener('touchmove', handlePointerMove);
      document.removeEventListener('pointerup', handlePointerUp);
      document.removeEventListener('touchend', handlePointerUp);
    };
  }, [handlePointerMove, handlePointerUp]);

  // Auto-hide bubble after 8 seconds
  useEffect(() => {
    const timer = setTimeout(() => setShowBubble(false), 8000);
    return () => clearTimeout(timer);
  }, []);

  const handleClick = (e) => {
    if (movedRef.current) {
      e.preventDefault();
      e.stopPropagation();
      movedRef.current = false;
      return;
    }

    if (isLandingPage) {
      // Guest on landing page: scroll to the inline assistant section
      const el = document.getElementById('assistant');
      if (el) {
        el.scrollIntoView({ behavior: 'smooth', block: 'center' });
        return;
      }
      navigate('/#assistant');
    } else {
      // Logged-in user on any other page: open floating chatbot modal
      setShowModal(true);
      setShowBubble(false);
    }
  };

  return (
    <>
      {/* Floating chatbot modal for logged-in users */}
      {showModal && !isLandingPage && (
        <ChatbotModal onClose={() => setShowModal(false)} isDarkMode={resolvedDark} />
      )}

      {/* Floating character button */}
      {!showModal && (
        <div
          ref={buttonRef}
          onPointerDown={isLandingPage ? undefined : handlePointerDown}
          onTouchStart={isLandingPage ? undefined : handlePointerDown}
          style={
            isLandingPage
              ? getLandingPageStyle()
              : pos
              ? {
                  position: 'fixed',
                  left: `${pos.left}px`,
                  top: `${pos.top}px`,
                  zIndex: 9999,
                  willChange: isDragging ? 'transform' : 'auto',
                  cursor: isDragging ? 'grabbing' : 'pointer',
                  transition: isDragging ? 'none' : 'all 0.15s ease-out',
                }
              : {
                  position: 'fixed',
                  right: '24px',
                  bottom: '80px',
                  zIndex: 9999,
                  cursor: 'pointer',
                }
          }
          className={`touch-none select-none ${className}`}
          title="Chat with our AI Assistant"
        >
          {/* Speech Bubble */}
          {showBubble && !isDragging && (
            <div
              className={`absolute -top-14 right-0 whitespace-nowrap px-3 py-2 rounded-xl text-xs font-medium shadow-lg animate-bounce ${
                resolvedDark
                  ? 'bg-amber-500 text-gray-900'
                  : 'bg-amber-600 text-white shadow-amber-600/20'
              }`}
              style={{ animationDuration: '2s' }}
            >
              Chat with AI Assistant! 💬
              <div className={`absolute -bottom-1.5 right-6 w-3 h-3 rotate-45 ${
                resolvedDark ? 'bg-amber-500' : 'bg-amber-600'
              }`} />
            </div>
          )}

          {/* Button - character on landing page, chat icon on other pages */}
          <button
            onClick={handleClick}
            className={`rounded-full overflow-hidden transform active:scale-95 flex items-center justify-center shadow-lg ring-2 ring-offset-2 transition-all duration-200 ${
              isLandingPage ? 'w-16 h-16 sm:w-[72px] sm:h-[72px]' : 'w-14 h-14 sm:w-16 sm:h-16'
            } ${
              isDragging
                ? 'scale-100 shadow-2xl opacity-95'
                : 'hover:scale-110'
            } ${
              resolvedDark
                ? 'ring-amber-500/50 ring-offset-gray-900 shadow-amber-500/20'
                : 'ring-amber-600/50 ring-offset-white shadow-amber-600/20'
            } ${
              !isLandingPage
                ? (resolvedDark ? 'bg-amber-500' : 'bg-gradient-to-r from-amber-500 to-amber-600')
                : ''
            }`}
            aria-label="Chat with AI Assistant"
          >
            {isLandingPage ? (
              <img
                src="/characterforchatbot.png"
                alt="AI Assistant"
                className="w-full h-full object-cover"
                draggable={false}
              />
            ) : (
              <svg className="w-7 h-7 sm:w-8 sm:h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
              </svg>
            )}
          </button>
        </div>
      )}
    </>
  );
};

export default ChatbotButton;
