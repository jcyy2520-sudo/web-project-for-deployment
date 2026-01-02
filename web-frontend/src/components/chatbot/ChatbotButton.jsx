import React, { useState, useEffect, useRef, useCallback } from 'react';
import ChatbotModal from './ChatbotModal';

const ChatbotButton = ({ className = '', isDarkMode }) => {
  const [isOpen, setIsOpen] = useState(false);
  // Always use the prop if provided, otherwise detect from DOM
  const [resolvedDark, setResolvedDark] = useState(isDarkMode ?? true);

  // Keep resolvedDark in sync with the prop
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
  const [pos, setPos] = useState(null);
  const [isDragging, setIsDragging] = useState(false);

  const STORAGE_KEY = 'chatbot_position_v1';

  // Check if we're on mobile
  const isMobile = () => {
    return typeof window !== 'undefined' && window.innerWidth < 640;
  };

  // Load saved position after mount
  useEffect(() => {
    try {
      const raw = localStorage.getItem(STORAGE_KEY);
      if (raw) {
        const parsed = JSON.parse(raw);
        if (parsed && typeof parsed.left === 'number' && typeof parsed.top === 'number') {
          // On mobile, ensure the button is not too close to the bottom (where BottomNav is)
          const btnH = 56;
          const bottomNavHeight = isMobile() ? 80 : 0; // Account for BottomNav on mobile
          const maxTop = window.innerHeight - btnH - bottomNavHeight - 16;
          const adjustedTop = Math.min(parsed.top, maxTop);
          setPos({ left: parsed.left, top: adjustedTop });
          return;
        }
      }
    } catch (e) {
      // ignore
    }

    // default: bottom-right offset, above BottomNav on mobile
    try {
      const btnW = 56;
      const btnH = 56;
      const right = 24;
      const bottomNavHeight = isMobile() ? 80 : 0; // Account for BottomNav on mobile
      const bottom = 80 + bottomNavHeight;
      const left = Math.max(12, window.innerWidth - btnW - right);
      const top = Math.max(12, window.innerHeight - btnH - bottom);
      setPos({ left, top });
    } catch (e) {
      setPos({ left: 12, top: 12 });
    }
  }, []);

  // Save position to localStorage
  const persistPos = useCallback((p) => {
    try {
      localStorage.setItem(STORAGE_KEY, JSON.stringify(p));
    } catch (e) {
      console.warn('Failed to save chatbot position:', e);
    }
  }, []);

  const clamp = useCallback((value, min, max) => Math.min(Math.max(value, min), max), []);

  const handlePointerMove = useCallback((e) => {
    if (!draggingRef.current || !buttonRef.current) return;

    const point = e.touches ? e.touches[0] : e;
    const btn = buttonRef.current;
    const btnW = btn.offsetWidth;
    const btnH = btn.offsetHeight;
    
    // On mobile, leave space for BottomNav (approximately 80px)
    const bottomNavHeight = isMobile() ? 80 : 0;
    const maxTop = window.innerHeight - btnH - bottomNavHeight - 8;
    
    const left = clamp(point.clientX - pointerOffsetRef.current.x, 8, window.innerWidth - btnW - 8);
    const top = clamp(point.clientY - pointerOffsetRef.current.y, 8, maxTop);

    // Store position in ref and apply visual feedback with transform for smooth dragging
    dragPosRef.current = { left, top };
    btn.style.left = `${left}px`;
    btn.style.top = `${top}px`;
    movedRef.current = true;
  }, [clamp]);

  const handlePointerUp = useCallback((e) => {
    if (draggingRef.current) {
      draggingRef.current = false;
      setIsDragging(false);
      
      // Commit the dragged position to state
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
    if (isOpen) return; // Don't start drag if modal is open
    
    draggingRef.current = true;
    movedRef.current = false;
    setIsDragging(true);
    
    const point = e.touches ? e.touches[0] : e;
    const rect = buttonRef.current?.getBoundingClientRect();
    
    pointerOffsetRef.current = {
      x: point.clientX - (rect?.left || 0),
      y: point.clientY - (rect?.top || 0),
    };

    // Use document instead of window for better performance
    document.addEventListener('pointermove', handlePointerMove, { passive: true });
    document.addEventListener('touchmove', handlePointerMove, { passive: true });
    document.addEventListener('pointerup', handlePointerUp, { passive: true });
    document.addEventListener('touchend', handlePointerUp, { passive: true });
  }, [handlePointerMove, handlePointerUp, isOpen]);

  // Cleanup event listeners
  useEffect(() => {
    return () => {
      document.removeEventListener('pointermove', handlePointerMove);
      document.removeEventListener('touchmove', handlePointerMove);
      document.removeEventListener('pointerup', handlePointerUp);
      document.removeEventListener('touchend', handlePointerUp);
    };
  }, [handlePointerMove, handlePointerUp]);

  return (
    <>
      {/* Floating button - ONLY show when modal is closed */}
      {!isOpen && (
        <button
          ref={buttonRef}
          onClick={(e) => {
            if (movedRef.current) {
              e.preventDefault();
              e.stopPropagation();
              movedRef.current = false;
              return;
            }
            setIsOpen(true);
          }}
          onPointerDown={handlePointerDown}
          onTouchStart={handlePointerDown}
          style={
            pos
              ? {
                  position: 'fixed',
                  left: `${pos.left}px`,
                  top: `${pos.top}px`,
                  zIndex: 40,
                  willChange: isDragging ? 'transform' : 'auto',
                  cursor: isDragging ? 'grabbing' : 'grab',
                  transition: isDragging ? 'none' : 'all 0.15s ease-out',
                }
              : {
                  position: 'fixed',
                  right: '24px',
                  bottom: '80px',
                  zIndex: 40,
                  cursor: 'grab',
                }
          }
          className={`rounded-full shadow-lg transform active:scale-95 flex items-center justify-center w-14 h-14 touch-none select-none ${className} ${
            isDragging
              ? 'scale-100 shadow-2xl opacity-95'
              : 'hover:scale-110 transition-all duration-200'
          } ${
            resolvedDark
              ? 'bg-gradient-to-br from-amber-500 to-amber-600 border-2 border-amber-400/50 text-white hover:shadow-amber-500/30'
              : 'bg-gradient-to-br from-amber-500 to-amber-600 border-2 border-amber-400/50 text-white hover:shadow-amber-500/30'
          }`}
          title="Chat Assistant (Drag to move)"
          aria-label="Open Chatbot Assistant"
        >
          <svg
            className={`w-6 h-6 transition-transform ${isDragging ? 'scale-90' : ''}`}
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
          {isDragging && (
            <div className="absolute inset-0 rounded-full border-2 border-white/40 animate-pulse" />
          )}
        </button>
      )}

      {/* Modal */}
      {isOpen && <ChatbotModal onClose={() => setIsOpen(false)} isDarkMode={resolvedDark} />}
    </>
  );
};

export default ChatbotButton;
