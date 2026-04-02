import { useState, useCallback, useRef, useEffect } from 'react';
import axios from 'axios';

export const useChatbot = () => {
  const [messages, setMessages] = useState([]);
  const [loading, setLoading] = useState(false);
  const [conversationId, setConversationId] = useState(null);
  const [lastSuggestions, setLastSuggestions] = useState([]);
  const [error, setError] = useState(null);
  const [pollingEnabled, setPollingEnabled] = useState(true);
  const [lastMessageCount, setLastMessageCount] = useState(0);
  const [conversations, setConversations] = useState([]);
  const [conversationsLoading, setConversationsLoading] = useState(false);
  const [showHistory, setShowHistory] = useState(false);
  
  // Rate limiting state
  const [rateLimitInfo, setRateLimitInfo] = useState({
    remaining: 50, // Updated to match new backend limit
    isLimited: false,
    mustStartNew: false,
    conversationLimit: 50, // Updated to match new backend limit
  });
  const [isRateLimited, setIsRateLimited] = useState(false);
  const [rateLimitMessage, setRateLimitMessage] = useState(null);
  
  // Language and sentiment state
  const [detectedLanguage, setDetectedLanguage] = useState('en');
  const [lastSentiment, setLastSentiment] = useState('neutral');
  
  // Role context state — tracks the latest role info from backend responses
  const [roleContext, setRoleContext] = useState({
    role: null,
    roleDisplay: null,
    pendingItems: [],
    quickActions: [],
  });

  // Error recovery state — structured recovery guidance from backend
  const [errorRecovery, setErrorRecovery] = useState(null);

  // Confirmation state — tracks pending destructive actions awaiting user confirmation
  const [pendingConfirmation, setPendingConfirmation] = useState(null);

  // Auto-expire pending confirmation after 5 minutes (matches backend cache TTL)
  useEffect(() => {
    if (!pendingConfirmation) return;
    const timer = setTimeout(() => {
      setPendingConfirmation(null);
    }, 5 * 60 * 1000);
    return () => clearTimeout(timer);
  }, [pendingConfirmation]);

  // Streaming state — feature-flagged SSE streaming support
  const [streamingEnabled] = useState(() => {
    // Read from env or localStorage; defaults to false (backward-compatible)
    return (
      import.meta.env.VITE_CHATBOT_STREAMING === 'true' ||
      localStorage.getItem('chatbot_streaming_enabled') === 'true'
    );
  });
  const [isStreaming, setIsStreaming] = useState(false);
  
  const messagesEndRef = useRef(null);
  const lastUserActionRef = useRef(Date.now());
  const sessionIdRef = useRef(null);
  const streamAbortRef = useRef(null);

  // Generate or retrieve session ID for guests
  useEffect(() => {
    if (!sessionIdRef.current) {
      const stored = localStorage.getItem('chatbot_session_id');
      if (stored) {
        sessionIdRef.current = stored;
      } else {
        // Use crypto.getRandomValues for secure session ID generation
        const array = new Uint8Array(16);
        crypto.getRandomValues(array);
        const hex = Array.from(array, b => b.toString(16).padStart(2, '0')).join('');
        sessionIdRef.current = `session_${hex}`;
        localStorage.setItem('chatbot_session_id', sessionIdRef.current);
      }
    }
  }, []);

  // Load chat history on mount
  useEffect(() => {
    const loadInitialHistory = async () => {
      try {
        // First, check if there's a saved conversation ID in localStorage
        const savedConversationId = localStorage.getItem('chatbot_current_conversation_id');
        
        // Build the URL with conversation_id if we have one saved
        let url = '/api/chatbot/history?limit=50';
        if (savedConversationId) {
          url += `&conversation_id=${encodeURIComponent(savedConversationId)}`;
        }
        
        const response = await axios.get(url, {
          headers: {
            'X-Session-ID': sessionIdRef.current
          }
        });
        if (response.data.success) {
          const loadedMessages = response.data.data || [];
          setMessages(loadedMessages);
          setLastMessageCount(loadedMessages.length);
          
          // If there was a saved conversation, use it. Otherwise use the last message's conversation
          let activeConvId = null;
          if (savedConversationId) {
            activeConvId = savedConversationId;
            setConversationId(savedConversationId);
          } else {
            const lastMsg = loadedMessages[loadedMessages.length - 1];
            if (lastMsg) {
              activeConvId = lastMsg.conversation_id;
              setConversationId(lastMsg.conversation_id);
              localStorage.setItem('chatbot_current_conversation_id', lastMsg.conversation_id);
            }
          }

          // Set rate limit state based on loaded messages
          if (activeConvId && loadedMessages.length > 0) {
            const userMsgCount = loadedMessages.filter(m => m.role === 'user').length;
            const limit = 50;
            const remaining = Math.max(0, limit - userMsgCount);
            const isLimited = remaining <= 0;
            setRateLimitInfo({
              remaining,
              isLimited,
              mustStartNew: isLimited,
              conversationLimit: limit,
            });
            setIsRateLimited(isLimited);
            if (isLimited) {
              setRateLimitMessage('Message limit reached for this conversation. Please start a new conversation.');
            }
          }
        }
      } catch (err) {
        console.error('Failed to load initial chat history:', err);
        // Silently fail on mount to not disrupt user experience
      }
    };
    
    loadInitialHistory();
  }, []);

  // Smart polling: Only check for updates if no recent user activity (prevents overwriting optimistic updates)
  // Use a ref to track the current conversation ID for polling
  const currentConversationIdRef = useRef(conversationId);
  
  // Keep the ref in sync with state
  useEffect(() => {
    currentConversationIdRef.current = conversationId;
  }, [conversationId]);
  
  useEffect(() => {
    // Pause polling if the chatbot is actively waiting for an LLM response or streaming
    if (!pollingEnabled || loading || isStreaming) return;
    
    const pollChatHistory = async () => {
      try {
        const timeSinceLastAction = Date.now() - lastUserActionRef.current;
        // Only poll if more than 3 seconds have passed since last user action
        // This prevents overwriting optimistic updates that are being sent
        if (timeSinceLastAction < 3000) {
          return;
        }
        
        // Only poll for the current conversation
        const convId = currentConversationIdRef.current;
        if (!convId) {
          return; // Don't poll if no conversation is active
        }

        const response = await axios.get(`/api/chatbot/history?limit=50&conversation_id=${encodeURIComponent(convId)}`, {
          headers: {
            'X-Session-ID': sessionIdRef.current
          }
        });
        if (response.data.success && response.data.data) {
          const serverMessages = response.data.data;
          // Only update if server has MORE messages (never overwrite with fewer)
          // This prevents polling from wiping out optimistic UI updates
          // and guards against transient backend auth failures returning empty data
          if (serverMessages.length > 0 && serverMessages.length !== lastMessageCount) {
            setMessages(serverMessages);
            setLastMessageCount(serverMessages.length);
          }
        }
      } catch (err) {
        if (err?.response?.status === 401) {
          setPollingEnabled(false);
          return;
        }
        console.error('Failed to poll chat history:', err);
      }
    };
    
    const id = setInterval(pollChatHistory, 20000); // Poll every 20 seconds instead of 15
    return () => clearInterval(id);
  }, [pollingEnabled, lastMessageCount, loading, isStreaming]);

  const scrollToBottom = () => {
    setTimeout(() => {
      messagesEndRef.current?.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }, 100);
  };

  const loadChatHistory = useCallback(async ({ silent = false, ignoreErrors = false, targetConversationId = null } = {}) => {
    try {
      // Use provided conversation ID, or current one, or none
      const convId = targetConversationId || conversationId;
      let url = '/api/chatbot/history?limit=50';
      if (convId) {
        url += `&conversation_id=${encodeURIComponent(convId)}`;
      }
      
      const response = await axios.get(url);
      if (response.data.success) {
        setMessages(response.data.data || []);
        // Set conversation ID from response or last message
        if (convId) {
          setConversationId(convId);
        } else {
          const lastMsg = response.data.data?.[response.data.data.length - 1];
          if (lastMsg) {
            setConversationId(lastMsg.conversation_id);
          }
        }
      }
    } catch (err) {
      console.error('Failed to load chat history:', err);

      // If unauthenticated, stop polling to avoid noisy retries for guests
      if (err?.response?.status === 401) {
        setPollingEnabled(false);
        if (!ignoreErrors && !silent) {
          setError('You need to be logged in to load chat history.');
        }
        return;
      }

      if (!ignoreErrors && !silent) {
        setError('Failed to load chat history');
      }
    }
  }, [conversationId]);

  const sendMessage = useCallback(async (userMessage) => {
    if (!userMessage.trim()) return;

    // Prevent sending while a previous message is still being processed
    // This is the frontend counterpart to the backend concurrent request lock.
    if (loading) {
      return;
    }

    // Prevent excessively long messages that waste LLM tokens
    if (userMessage.length > 2000) {
      setError('Message is too long. Please keep it under 2000 characters.');
      return;
    }

    // Check if rate limited before sending
    if (isRateLimited && rateLimitInfo.mustStartNew) {
      setError(rateLimitMessage || 'Message limit reached. Please start a new conversation.');
      return;
    }

    setError(null);
    setRateLimitMessage(null);
    setErrorRecovery(null);
    // Track user action to prevent polling from overwriting optimistic updates
    lastUserActionRef.current = Date.now();
    
    const msgId = Date.now() * 1000 + Math.floor(Math.random() * 1000);
    const newUserMessage = {
      id: msgId,
      message: userMessage,
      role: 'user',
      created_at: new Date().toISOString(),
      source: 'user'
    };

    setMessages((prev) => [...prev, newUserMessage]);
    setLastMessageCount((prev) => prev + 1);
    setLoading(true);

    try {
      const response = await axios.post('/api/chatbot/v2/send-message', {
        message: userMessage,
        conversation_id: conversationId
      }, {
        headers: {
          'X-Session-ID': sessionIdRef.current
        }
      });

      // Check for rate limit response (429)
      if (response.status === 429 || response.data?.rate_limited) {
        const limitInfo = response.data?.rate_limit_info || {};
        setIsRateLimited(true);
        setRateLimitInfo({
          remaining: limitInfo.remaining || 0,
          isLimited: true,
          mustStartNew: limitInfo.must_start_new || response.data?.must_start_new_conversation || false,
          conversationLimit: limitInfo.conversation_limit || 20,
          blockedUntil: limitInfo.blocked_until || null,
        });
        setRateLimitMessage(response.data?.message || 'Rate limit reached. Please start a new conversation.');
        setError(response.data?.message || 'Rate limit reached.');
        setMessages((prev) => prev.filter((msg) => msg.id !== newUserMessage.id));
        setLastMessageCount((prev) => Math.max(0, prev - 1));
        return;
      }

      // Validate response structure
      if (!response.data) {
        throw new Error('Empty response from server');
      }

      // Check for API-level success flag
      if (response.data.success === false) {
        // Check if it's a rate limit error
        if (response.data.rate_limited) {
          const limitInfo = response.data.rate_limit_info || {};
          setIsRateLimited(true);
          setRateLimitInfo({
            remaining: limitInfo.remaining || 0,
            isLimited: true,
            mustStartNew: limitInfo.must_start_new || response.data.must_start_new_conversation || false,
            conversationLimit: limitInfo.conversation_limit || 20,
          });
          setRateLimitMessage(response.data.message);
        }
        
        const errorMsg = response.data?.message || 'Failed to get response from chatbot';
        setError(errorMsg);
        setMessages((prev) => prev.filter((msg) => msg.id !== newUserMessage.id));
        return;
      }

      // Update rate limit info from meta
      const meta = response.data.meta || {};
      if (meta.rate_limit_remaining !== undefined) {
        setRateLimitInfo(prev => ({
          ...prev,
          remaining: meta.rate_limit_remaining,
          isLimited: meta.rate_limit_remaining <= 0,
          mustStartNew: meta.rate_limit_remaining <= 0,
        }));
        setIsRateLimited(meta.rate_limit_remaining <= 0);
      }

      // Update detected language and sentiment
      if (meta.detected_language) {
        setDetectedLanguage(meta.detected_language);
      }
      if (meta.sentiment) {
        setLastSentiment(meta.sentiment);
      }

      // Update role context from backend response
      if (meta.role || meta.role_display) {
        setRoleContext(prev => ({
          role: meta.role || prev.role,
          roleDisplay: meta.role_display || prev.roleDisplay,
          pendingItems: Array.isArray(meta.pending_items) ? meta.pending_items : prev.pendingItems,
          quickActions: Array.isArray(meta.quick_actions) ? meta.quick_actions : prev.quickActions,
        }));
      }

      // Track confirmation state for destructive actions
      if (meta.requires_confirmation) {
        setPendingConfirmation({
          key: meta.confirmation_key,
          tool: meta.pending_tool || null,
          timestamp: Date.now(),
        });
      } else {
        // Clear confirmation state when response doesn't require confirmation
        setPendingConfirmation(null);
      }

      // Normalize ai_response/message
      const aiResponseText = typeof response.data?.ai_response === 'string' && response.data.ai_response.trim().length > 0
        ? response.data.ai_response
        : (typeof response.data?.message === 'string' && response.data.message.trim().length > 0 ? response.data.message : '');

      // For guest responses, provide a fallback message if empty
      const finalResponse = aiResponseText || (meta?.role === 'guest' 
        ? "Thanks for your question! To get personalized assistance and access all features, please register or log in."
        : "I'm experiencing a temporary issue processing your request. Please try again.");

      if (!finalResponse) {
        console.error('Invalid ai_response format:', response.data);
        setError('Invalid response format from server');
        setMessages((prev) => prev.filter((msg) => msg.id !== newUserMessage.id));
        return;
      }

      const aiMessage = {
        id: Date.now() * 1000 + Math.floor(Math.random() * 1000) + 1,
        message: finalResponse,
        role: 'assistant',
        created_at: response.data.timestamp || new Date().toISOString(),
        source: meta?.source || meta?.meta_source || 'ai_assistant',
        suggestions: Array.isArray(meta?.suggestions) ? meta.suggestions : [],
        meta: meta || {},
        isPriority: meta?.is_priority || false,
        sentiment: meta?.sentiment || 'neutral',
        detectedLanguage: meta?.detected_language || 'en',
      };

      // Persist suggestions from the assistant so the UI can show them
      if (Array.isArray(aiMessage.suggestions) && aiMessage.suggestions.length > 0) {
        setLastSuggestions(aiMessage.suggestions);
      }

      setMessages((prev) => [...prev, aiMessage]);
      setLastMessageCount((prev) => prev + 1);
      
      // Validate conversation_id
      if (response.data.conversation_id) {
        setConversationId(response.data.conversation_id);
        // Save conversation ID to localStorage for persistence
        localStorage.setItem('chatbot_current_conversation_id', response.data.conversation_id);
      }

      // Refresh conversations list so the current conversation appears in history
      // Use a slight delay to let the backend finish saving
      setTimeout(() => loadConversations(), 500);

      // Note: Chatbot messages are NOT saved to Message Center to keep them separate
    } catch (err) {
      console.error('Failed to send message:', err.message);
      console.error('Error details:', {
        status: err.response?.status,
        statusText: err.response?.statusText,
        message: err.message,
      });
      
      // Check for rate limit error (429)
      if (err.response?.status === 429) {
        const responseData = err.response?.data || {};

        // Handle concurrent request / dedup (not a real rate limit — just wait)
        if (responseData.retry_after || responseData.duplicate) {
          setError(responseData.message || 'Please wait for the current response to complete.');
          setMessages((prev) => prev.filter((msg) => msg.id !== newUserMessage.id));
          setLastMessageCount((prev) => Math.max(0, prev - 1));
          return;
        }

        const limitInfo = responseData.rate_limit_info || {};
        const mustNew = responseData.must_start_new_conversation || false;
        setIsRateLimited(true);
        setRateLimitInfo({
          remaining: 0,
          isLimited: true,
          mustStartNew: mustNew,
          conversationLimit: 50, // Updated to match new backend limit
        });
        setRateLimitMessage(err.response?.data?.message || 'Rate limit exceeded. Please wait a moment.');
        setError(err.response?.data?.message || 'Rate limit exceeded. Please wait a moment.');
        setMessages((prev) => prev.filter((msg) => msg.id !== newUserMessage.id));
        setLastMessageCount((prev) => Math.max(0, prev - 1));

        // Auto-reset for transient per-minute rate limits (not conversation limits)
        if (!mustNew) {
          setTimeout(() => {
            setIsRateLimited(false);
            setRateLimitInfo((prev) => ({ ...prev, isLimited: false, remaining: 1 }));
            setRateLimitMessage(null);
            setError(null);
          }, 60000);
        }
        return;
      }
      
      // Determine user-friendly error message
      let errorMsg = 'Failed to send message. Please try again.';
      if (err.response?.status === 401) {
        errorMsg = 'Authentication error. Please refresh the page and try again.';
      } else if (err.response?.status === 422) {
        errorMsg = 'Invalid message format. Please try again.';
      } else if (err.response?.status === 500) {
        errorMsg = 'Server error. Please try again later.';
      } else if (err.response?.data?.message) {
        errorMsg = err.response.data.message;
      } else if (err.message === 'Network Error') {
        errorMsg = 'Network error. Please check your internet connection.';
      } else if (err.code === 'ECONNABORTED') {
        errorMsg = 'Request timeout. Please try again.';
      }
      
      setError(errorMsg);

      // Extract recovery guidance from backend response
      const recovery = err.response?.data?.recovery || null;
      setErrorRecovery(recovery);

      // Store the failed message for retry capability
      setLastFailedMessage(userMessage);

      // Remove the user message on error
      setMessages((prev) => prev.filter((msg) => msg.id !== newUserMessage.id));
      setLastMessageCount((prev) => Math.max(0, prev - 1));
    } finally {
      setLoading(false);
      lastUserActionRef.current = Date.now() + 3000; // Extend cooldown after send attempt
      scrollToBottom();
    }
  }, [conversationId, loading, isRateLimited, rateLimitInfo, rateLimitMessage]);

  // =============================================
  // STREAMING SEND MESSAGE (SSE)
  // =============================================
  const sendMessageStreaming = useCallback(async (userMessage) => {
    if (!userMessage.trim()) return;

    // Prevent sending while a previous message is still being processed
    if (loading) {
      return;
    }

    if (isRateLimited && rateLimitInfo.mustStartNew) {
      setError(rateLimitMessage || 'Message limit reached. Please start a new conversation.');
      return;
    }

    setError(null);
    setRateLimitMessage(null);
    setErrorRecovery(null);
    lastUserActionRef.current = Date.now();

    const msgId = Date.now() * 1000 + Math.floor(Math.random() * 1000);
    const newUserMessage = {
      id: msgId,
      message: userMessage,
      role: 'user',
      created_at: new Date().toISOString(),
      source: 'user',
    };

    // Placeholder assistant message that will be progressively filled
    const aiMessageId = Date.now() + 1;
    const aiMessage = {
      id: aiMessageId,
      message: '',
      role: 'assistant',
      created_at: new Date().toISOString(),
      source: 'streaming',
      suggestions: [],
      meta: {},
      isStreaming: true,
    };

    setMessages((prev) => [...prev, newUserMessage, aiMessage]);
    setLastMessageCount((prev) => prev + 1);
    setLoading(true);
    setIsStreaming(true);

    const abortController = new AbortController();
    streamAbortRef.current = abortController;

    try {
      const baseURL = axios.defaults.baseURL || '';
      const url = `${baseURL}/api/chatbot/v2/stream`;

      const response = await fetch(url, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'text/event-stream',
          'X-Session-ID': sessionIdRef.current,
          // Forward auth token if present
          ...(axios.defaults.headers?.common?.Authorization
            ? { Authorization: axios.defaults.headers.common.Authorization }
            : {}),
          ...(document.querySelector('meta[name="csrf-token"]')
            ? { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }
            : {}),
        },
        body: JSON.stringify({
          message: userMessage,
          conversation_id: conversationId,
        }),
        signal: abortController.signal,
      });

      if (!response.ok) {
        if (response.status === 429) {
          setIsRateLimited(true);
          setRateLimitMessage('Rate limit reached. Please start a new conversation.');
          setMessages((prev) => prev.filter((m) => m.id !== newUserMessage.id && m.id !== aiMessageId));
          return;
        }
        throw new Error(`Stream request failed: ${response.status}`);
      }

      const reader = response.body.getReader();
      const decoder = new TextDecoder();
      let buffer = '';
      let fullText = '';
      let streamConversationId = conversationId;

      const processLine = (line) => {
        if (line.startsWith('event: ')) {
          // Store event type for the next data line
          processLine._lastEvent = line.slice(7).trim();
          return;
        }
        if (!line.startsWith('data: ')) return;

        const eventType = processLine._lastEvent || 'message';
        processLine._lastEvent = null;

        try {
          const data = JSON.parse(line.slice(6));

          switch (eventType) {
            case 'token': {
              const token = data.content || '';
              fullText += token;
              setMessages((prev) =>
                prev.map((m) =>
                  m.id === aiMessageId ? { ...m, message: fullText } : m
                )
              );
              scrollToBottom();
              break;
            }
            case 'complete': {
              // Final metadata
              const meta = data.meta || data;
              setMessages((prev) =>
                prev.map((m) =>
                  m.id === aiMessageId
                    ? {
                        ...m,
                        meta,
                        suggestions: Array.isArray(meta?.suggestions) ? meta.suggestions : m.suggestions,
                        source: meta?.source || 'streaming',
                        isStreaming: false,
                      }
                    : m
                )
              );
              if (Array.isArray(meta?.suggestions) && meta.suggestions.length > 0) {
                setLastSuggestions(meta.suggestions);
              }
              if (meta?.detected_language) setDetectedLanguage(meta.detected_language);
              if (meta?.sentiment) setLastSentiment(meta.sentiment);
              break;
            }
            case 'done': {
              if (data.conversation_id) {
                streamConversationId = data.conversation_id;
                setConversationId(data.conversation_id);
                localStorage.setItem('chatbot_current_conversation_id', data.conversation_id);
              }
              // Merge meta from done event (contextual quick actions, pending items, confirmation)
              const doneMeta = data.meta || {};
              setMessages((prev) =>
                prev.map((m) =>
                  m.id === aiMessageId
                    ? {
                        ...m,
                        isStreaming: false,
                        meta: { ...(m.meta || {}), ...doneMeta },
                      }
                    : m
                )
              );
              // Update role context from done meta
              if (doneMeta.role || doneMeta.role_display) {
                setRoleContext((prev) => ({
                  role: doneMeta.role || prev.role,
                  roleDisplay: doneMeta.role_display || prev.roleDisplay,
                  pendingItems: Array.isArray(doneMeta.pending_items) ? doneMeta.pending_items : prev.pendingItems,
                  quickActions: Array.isArray(doneMeta.quick_actions) ? doneMeta.quick_actions : prev.quickActions,
                }));
              }
              // Track confirmation state for destructive actions (streaming)
              if (doneMeta.requires_confirmation) {
                setPendingConfirmation({
                  key: doneMeta.confirmation_key,
                  tool: doneMeta.pending_tool || null,
                  timestamp: Date.now(),
                });
              } else {
                setPendingConfirmation(null);
              }
              break;
            }
            case 'error': {
              const errMsg = data.message || 'Streaming error occurred';
              setError(errMsg);
              setMessages((prev) =>
                prev.map((m) =>
                  m.id === aiMessageId
                    ? { ...m, message: fullText || errMsg, isStreaming: false }
                    : m
                )
              );
              break;
            }
            // 'status' events are informational — ignore
            default:
              break;
          }
        } catch (parseErr) {
          console.debug('SSE parse error:', parseErr, line);
        }
      };

      // Read the stream
      // eslint-disable-next-line no-constant-condition
      while (true) {
        const { done, value } = await reader.read();
        if (done) break;

        buffer += decoder.decode(value, { stream: true });
        const lines = buffer.split('\n');
        buffer = lines.pop() || '';

        for (const line of lines) {
          if (line.trim()) processLine(line);
        }
      }

      // Handle any remaining buffer
      if (buffer.trim()) processLine(buffer);

      // Ensure final state
      setMessages((prev) =>
        prev.map((m) => (m.id === aiMessageId ? { ...m, isStreaming: false } : m))
      );

      setLastMessageCount((prev) => prev + 1);
      setTimeout(() => loadConversations(), 500);
    } catch (err) {
      if (err.name === 'AbortError') {
        // User cancelled streaming
        setMessages((prev) =>
          prev.map((m) =>
            m.id === aiMessageId ? { ...m, isStreaming: false, message: m.message || '[Cancelled]' } : m
          )
        );
      } else {
        console.error('Streaming error:', err);
        setError('Streaming failed. Retrying with standard request...');
        // Remove streaming messages and fall back to non-streaming
        setMessages((prev) => prev.filter((m) => m.id !== newUserMessage.id && m.id !== aiMessageId));
        // Fallback to standard sendMessage
        await sendMessageNonStreaming(userMessage);
      }
    } finally {
      setLoading(false);
      setIsStreaming(false);
      streamAbortRef.current = null;
      lastUserActionRef.current = Date.now() + 3000;
      scrollToBottom();
    }
  }, [conversationId, loading, isRateLimited, rateLimitInfo, rateLimitMessage]);

  // Abort any in-flight stream
  const cancelStreaming = useCallback(() => {
    if (streamAbortRef.current) {
      streamAbortRef.current.abort();
      streamAbortRef.current = null;
    }
  }, []);

  // Alias: the original non-streaming logic so streaming fallback can call it
  const sendMessageNonStreaming = sendMessage;

  // Unified send: choose streaming vs standard based on feature flag
  const sendMessageUnified = useCallback(
    (userMessage) => {
      if (streamingEnabled) {
        return sendMessageStreaming(userMessage);
      }
      return sendMessage(userMessage);
    },
    [streamingEnabled, sendMessage, sendMessageStreaming]
  );

  const clearHistory = useCallback(async () => {
    try {
      await axios.delete('/api/chatbot/clear-history', {
        data: { conversation_id: conversationId }
      });
      setMessages([]);
      setLastMessageCount(0);
      setConversationId(null);
      setError(null);
      setLastSuggestions([]);
      lastUserActionRef.current = Date.now();
    } catch (err) {
      console.error('Failed to clear history:', err);
      setError('Failed to clear chat history');
    }
  }, [conversationId]);

  const clearAllHistory = useCallback(async () => {
    try {
      await axios.delete('/api/chatbot/clear-history');
      setMessages([]);
      setLastMessageCount(0);
      setConversationId(null);
      setError(null);
      setLastSuggestions([]);
      lastUserActionRef.current = Date.now();
      // Refresh conversations list after clearing
      loadConversations();
    } catch (err) {
      console.error('Failed to clear all history:', err);
      setError('Failed to clear chat history');
    }
  }, []);

  // Load all conversations for the current user
  const loadConversations = useCallback(async () => {
    try {
      setConversationsLoading(true);
      const response = await axios.get('/api/chatbot/conversations', {
        headers: {
          'X-Session-ID': sessionIdRef.current
        }
      });
      if (response.data.success) {
        setConversations(response.data.data || []);
      }
    } catch (err) {
      console.error('Failed to load conversations:', err);
      // Silently fail - not critical for user experience
      if (err?.response?.status === 401) {
        setConversations([]);
      }
    } finally {
      setConversationsLoading(false);
    }
  }, []);

  // Start a new conversation
  const startNewConversation = useCallback(async () => {
    try {
      setError(null);
      // Reset rate limit state when starting new conversation
      setIsRateLimited(false);
      setRateLimitInfo({
        remaining: 50,
        isLimited: false,
        mustStartNew: false,
        conversationLimit: 50,
      });
      setRateLimitMessage(null);
      
      // Get the current conversation ID to save it before creating new one
      const previousConversationId = conversationId || localStorage.getItem('chatbot_current_conversation_id');
      
      const response = await axios.post('/api/chatbot/conversations/new', {
        previous_conversation_id: previousConversationId
      }, {
        headers: {
          'X-Session-ID': sessionIdRef.current
        }
      });
      if (response.data.success) {
        const newConvId = response.data.conversation_id;
        
        // Clear the old conversation from state FIRST
        setMessages([]);
        setLastMessageCount(0);
        setLastSuggestions([]);
        
        // THEN set the new conversation ID
        setConversationId(newConvId);
        lastUserActionRef.current = Date.now();
        
        // IMPORTANT: Save the new conversation ID to localStorage so it persists
        localStorage.setItem('chatbot_current_conversation_id', newConvId);
        
        // Refresh conversations list to show the saved previous conversation
        // Use a small delay to let the backend finish saving
        setTimeout(() => loadConversations(), 300);
        return newConvId;
      }
    } catch (err) {
      console.error('Failed to start new conversation:', err);
      setError('Failed to start new conversation');
      return null;
    }
  }, [conversationId, loadConversations]);

  // Switch to a specific conversation
  const switchConversation = useCallback(async (targetConversationId) => {
    try {
      setError(null);
      lastUserActionRef.current = Date.now();
      
      if (!targetConversationId) {
        setMessages([]);
        setConversationId(null);
        setLastMessageCount(0);
        setIsRateLimited(false);
        setRateLimitInfo({
          remaining: 50,
          isLimited: false,
          mustStartNew: false,
          conversationLimit: 50,
        });
        setRateLimitMessage(null);
        localStorage.removeItem('chatbot_current_conversation_id');
        return;
      }

      const response = await axios.get(`/api/chatbot/conversations/${targetConversationId}`, {
        headers: {
          'X-Session-ID': sessionIdRef.current
        }
      });
      if (response.data.success) {
        setMessages(response.data.data || []);
        setConversationId(targetConversationId);
        // Save the conversation ID to localStorage
        localStorage.setItem('chatbot_current_conversation_id', targetConversationId);
        setLastMessageCount(response.data.data?.length || 0);
        setLastSuggestions([]);

        // Update rate limit state from the response
        const rateLimit = response.data.rate_limit;
        if (rateLimit) {
          const isLimited = rateLimit.is_limited || rateLimit.remaining <= 0;
          setRateLimitInfo({
            remaining: rateLimit.remaining ?? 50,
            isLimited: isLimited,
            mustStartNew: isLimited,
            conversationLimit: rateLimit.limit ?? 50,
          });
          setIsRateLimited(isLimited);
          if (isLimited) {
            setRateLimitMessage('Message limit reached for this conversation. Please start a new conversation.');
          } else {
            setRateLimitMessage(null);
          }
        } else {
          // Fallback: calculate from messages if no rate_limit in response
          const userMsgCount = (response.data.data || []).filter(m => m.role === 'user').length;
          const limit = 50;
          const remaining = Math.max(0, limit - userMsgCount);
          const isLimited = remaining <= 0;
          setRateLimitInfo({
            remaining,
            isLimited,
            mustStartNew: isLimited,
            conversationLimit: limit,
          });
          setIsRateLimited(isLimited);
          setRateLimitMessage(isLimited ? 'Message limit reached for this conversation. Please start a new conversation.' : null);
        }
      }
    } catch (err) {
      console.error('Failed to switch conversation:', err);
      setError('Failed to load conversation');
    }
  }, []);

  // Delete a specific conversation
  const deleteConversation = useCallback(async (targetConversationId) => {
    try {
      await axios.delete(`/api/chatbot/conversations/${targetConversationId}`);
      
      // If we deleted the current conversation, clear messages and localStorage
      if (targetConversationId === conversationId) {
        setMessages([]);
        setConversationId(null);
        setLastMessageCount(0);
        setLastSuggestions([]);
        localStorage.removeItem('chatbot_current_conversation_id');
      }
      
      // Refresh conversations list
      await loadConversations();
      return true;
    } catch (err) {
      console.error('Failed to delete conversation:', err);
      setError('Failed to delete conversation');
      return false;
    }
  }, [conversationId, loadConversations]);

  // Toggle history panel visibility
  const toggleHistory = useCallback(() => {
    setShowHistory(prev => !prev);
    if (!showHistory) {
      // Load conversations when opening history
      loadConversations();
    }
  }, [showHistory, loadConversations]);

  // Load conversations on mount
  useEffect(() => {
    loadConversations();
  }, [loadConversations]);

  // =============================================
  // FEEDBACK SYSTEM
  // =============================================
  const submitFeedback = useCallback(async (messageId, feedbackData) => {
    try {
      const response = await axios.post('/api/chatbot/feedback', {
        message_id: messageId,
        conversation_id: conversationId,
        ...feedbackData,
      }, {
        headers: { 'X-Session-ID': sessionIdRef.current }
      });

      if (response.data.success) {
        // Update the message in state to show feedback was given
        setMessages(prev => prev.map(msg =>
          msg.id === messageId
            ? { ...msg, feedbackGiven: true, feedbackType: feedbackData.is_helpful ? 'positive' : 'negative' }
            : msg
        ));
        return true;
      }
      return false;
    } catch (err) {
      console.error('Failed to submit feedback:', err);
      return false;
    }
  }, [conversationId]);

  // =============================================
  // PROACTIVE TIPS
  // =============================================
  const [proactiveTips, setProactiveTips] = useState([]);

  const loadProactiveTips = useCallback(async () => {
    try {
      const response = await axios.get('/api/chatbot/proactive-tips', {
        headers: { 'X-Session-ID': sessionIdRef.current }
      });
      if (response.data.success) {
        setProactiveTips(response.data.data || []);
      }
    } catch (err) {
      // Silently fail — tips are non-critical
      console.debug('Failed to load proactive tips:', err);
    }
  }, []);

  // Load tips on mount
  useEffect(() => {
    loadProactiveTips();
  }, [loadProactiveTips]);

  // =============================================
  // RETRY FAILED MESSAGE
  // =============================================
  const [lastFailedMessage, setLastFailedMessage] = useState(null);

  const retryLastMessage = useCallback(async () => {
    if (lastFailedMessage) {
      setError(null);
      const msg = lastFailedMessage;
      setLastFailedMessage(null);
      await sendMessage(msg);
    }
  }, [lastFailedMessage, sendMessage]);

  // Confirm a pending destructive action
  const confirmAction = useCallback(async () => {
    if (!pendingConfirmation) return;
    setPendingConfirmation(null);
    await sendMessage('yes');
  }, [pendingConfirmation, sendMessage]);

  // Deny/cancel a pending destructive action
  const denyAction = useCallback(async () => {
    if (!pendingConfirmation) return;
    setPendingConfirmation(null);
    await sendMessage('no');
  }, [pendingConfirmation, sendMessage]);

  return {
    messages,
    loading,
    conversationId,
    lastSuggestions,
    error,
    sendMessage: sendMessageUnified,
    clearHistory,
    clearAllHistory,
    loadChatHistory,
    messagesEndRef,
    setError,
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
    loadProactiveTips,
    // Retry
    lastFailedMessage,
    retryLastMessage,
    // Role context
    roleContext,
    // Error recovery
    errorRecovery,
    // Streaming
    isStreaming,
    streamingEnabled,
    cancelStreaming,
    // Confirmation
    pendingConfirmation,
    confirmAction,
    denyAction,
  };
};

export default useChatbot;
