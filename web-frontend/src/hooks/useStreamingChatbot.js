/**
 * @deprecated This hook is NOT imported anywhere in the codebase.
 * Streaming is handled by useChatbot.js (sendMessageStreaming).
 * Safe to remove after verifying no new imports reference it.
 */
import { useState, useCallback, useRef, useEffect } from 'react';
import axios from 'axios';

/**
 * useStreamingChatbot - Enhanced chatbot hook with streaming support
 * 
 * Features:
 * - Server-Sent Events (SSE) for streaming responses
 * - Real-time token-by-token display
 * - WebSocket support for multi-device sync
 * - Smart action suggestions
 * - Personality selection
 * - Enhanced error handling
 */
export const useStreamingChatbot = () => {
  const [messages, setMessages] = useState([]);
  const [loading, setLoading] = useState(false);
  const [streaming, setStreaming] = useState(false);
  const [streamingText, setStreamingText] = useState('');
  const [conversationId, setConversationId] = useState(null);
  const [error, setError] = useState(null);
  
  // LLM Status
  const [llmStatus, setLlmStatus] = useState({
    available: false,
    provider: null,
    streamingSupported: true,
    features: {},
  });
  
  // Personality & Preferences
  const [personality, setPersonality] = useState('professional');
  const [availablePersonalities, setAvailablePersonalities] = useState([
    'professional', 'friendly', 'expert', 'concise'
  ]);
  
  // Smart Suggestions
  const [actionSuggestions, setActionSuggestions] = useState([]);
  const [quickActions, setQuickActions] = useState([]);
  const [suggestedQuestions, setSuggestedQuestions] = useState([]);
  
  // Rate limiting
  const [rateLimitInfo, setRateLimitInfo] = useState({
    remaining: 50,
    isLimited: false,
    mustStartNew: false,
  });
  const [isRateLimited, setIsRateLimited] = useState(false);
  
  // Context info
  const [lastIntent, setLastIntent] = useState(null);
  const [lastSentiment, setLastSentiment] = useState('neutral');
  
  // Conversation management
  const [conversations, setConversations] = useState([]);
  const [conversationsLoading, setConversationsLoading] = useState(false);
  const [showHistory, setShowHistory] = useState(false);
  
  const messagesEndRef = useRef(null);
  const sessionIdRef = useRef(null);
  const streamAbortRef = useRef(null);
  const echoChannelRef = useRef(null);

  // Initialize session ID
  useEffect(() => {
    if (!sessionIdRef.current) {
      sessionIdRef.current = localStorage.getItem('chatbot_session_id') || 
        `session_${Date.now()}_${Math.random().toString(36).substr(2, 9)}`;
      localStorage.setItem('chatbot_session_id', sessionIdRef.current);
    }
  }, []);

  // Fetch LLM status on mount
  useEffect(() => {
    fetchLLMStatus();
  }, []);

  // Setup WebSocket channel for real-time updates (if Echo is available)
  useEffect(() => {
    if (!conversationId || !window.Echo) return;

    try {
      const channel = window.Echo.private(`conversation.${conversationId}`);
      
      channel.listen('.message.sent', (e) => {
        // Update messages if from another device/session
        if (e.role === 'assistant' && !streaming) {
          setMessages(prev => {
            const exists = prev.some(m => m.id === e.id);
            if (!exists) {
              return [...prev, {
                id: Date.now(),
                message: e.message,
                role: e.role,
                created_at: e.timestamp,
                meta: e.meta,
              }];
            }
            return prev;
          });
        }
      });

      channel.listen('.stream.token', (e) => {
        if (e.conversation_id === conversationId) {
          setStreamingText(prev => prev + e.token);
        }
      });

      channel.listen('.chatbot.typing', (e) => {
        // Could show typing indicator
      });

      echoChannelRef.current = channel;

      return () => {
        if (echoChannelRef.current) {
          window.Echo.leave(`conversation.${conversationId}`);
        }
      };
    } catch (err) {
      console.debug('WebSocket not available:', err);
    }
  }, [conversationId, streaming]);

  // Fetch LLM status
  const fetchLLMStatus = async () => {
    try {
      const response = await axios.get('/api/chatbot/status');
      if (response.data.success) {
        const data = response.data.data;
        setLlmStatus({
          available: !!data.llm?.available_provider,
          provider: data.llm?.available_provider,
          streamingSupported: data.streaming_supported,
          features: data.features || {},
        });
        if (data.personalities) {
          setAvailablePersonalities(data.personalities);
        }
      }
    } catch (err) {
      console.warn('Failed to fetch LLM status:', err);
    }
  };

  // Load chat history
  const loadChatHistory = useCallback(async (targetConversationId = null) => {
    try {
      const convId = targetConversationId || conversationId;
      let url = '/api/chatbot/history?limit=50';
      if (convId) {
        url += `&conversation_id=${encodeURIComponent(convId)}`;
      }
      
      const response = await axios.get(url);
      if (response.data.success) {
        setMessages(response.data.data || []);
        if (convId) {
          setConversationId(convId);
        }
      }
    } catch (err) {
      if (err.response?.status !== 401) {
        console.error('Failed to load chat history:', err);
      }
    }
  }, [conversationId]);

  // Load on mount
  useEffect(() => {
    const savedConversationId = localStorage.getItem('chatbot_current_conversation_id');
    if (savedConversationId) {
      loadChatHistory(savedConversationId);
    }
  }, []);

  /**
   * Send message with streaming response
   */
  const sendMessage = useCallback(async (userMessage, useStreaming = true) => {
    if (!userMessage.trim()) return;

    if (isRateLimited && rateLimitInfo.mustStartNew) {
      setError('Message limit reached. Please start a new conversation.');
      return;
    }

    setError(null);
    setStreaming(useStreaming && llmStatus.streamingSupported);
    setStreamingText('');

    // Add user message optimistically
    const userMsgId = Date.now();
    const newUserMessage = {
      id: userMsgId,
      message: userMessage,
      role: 'user',
      created_at: new Date().toISOString(),
    };
    setMessages(prev => [...prev, newUserMessage]);
    setLoading(true);

    try {
      if (useStreaming && llmStatus.streamingSupported) {
        await sendStreamingMessage(userMessage, userMsgId);
      } else {
        await sendNonStreamingMessage(userMessage, userMsgId);
      }
    } catch (err) {
      handleError(err, userMsgId);
    } finally {
      setLoading(false);
      setStreaming(false);
      scrollToBottom();
    }
  }, [conversationId, isRateLimited, rateLimitInfo, llmStatus]);

  /**
   * Send message with SSE streaming
   */
  const sendStreamingMessage = async (userMessage, userMsgId) => {
    const convId = conversationId || `chat_${Date.now()}`;
    
    // Create abort controller
    const abortController = new AbortController();
    streamAbortRef.current = abortController;

    const response = await fetch('/api/chatbot/stream', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'text/event-stream',
        'X-Session-ID': sessionIdRef.current,
        'Authorization': `Bearer ${localStorage.getItem('token')}`,
      },
      body: JSON.stringify({
        message: userMessage,
        conversation_id: convId,
        personality: personality,
        stream: true,
      }),
      signal: abortController.signal,
    });

    if (!response.ok) {
      throw new Error(`HTTP ${response.status}`);
    }

    const reader = response.body.getReader();
    const decoder = new TextDecoder();
    let fullResponse = '';
    let buffer = '';

    try {
      while (true) {
        const { done, value } = await reader.read();
        if (done) break;

        buffer += decoder.decode(value, { stream: true });
        
        // Process complete events
        const lines = buffer.split('\n\n');
        buffer = lines.pop() || ''; // Keep incomplete line in buffer

        for (const eventBlock of lines) {
          if (!eventBlock.trim()) continue;

          const eventLines = eventBlock.split('\n');
          let eventType = '';
          let eventData = '';

          for (const line of eventLines) {
            if (line.startsWith('event: ')) {
              eventType = line.slice(7);
            } else if (line.startsWith('data: ')) {
              eventData = line.slice(6);
            }
          }

          if (eventData) {
            try {
              const data = JSON.parse(eventData);
              
              switch (eventType) {
                case 'token':
                  fullResponse += data.content || '';
                  setStreamingText(fullResponse);
                  break;

                case 'done':
                  // Finalize the message
                  setConversationId(data.conversation_id);
                  localStorage.setItem('chatbot_current_conversation_id', data.conversation_id);
                  
                  if (data.intent) setLastIntent(data.intent);
                  if (data.sentiment) setLastSentiment(data.sentiment);
                  if (data.suggestions) setActionSuggestions(data.suggestions);
                  if (data.quick_actions) setQuickActions(data.quick_actions);
                  break;

                case 'error':
                  throw new Error(data.message || 'Stream error');

                case 'status':
                  // Could show status updates
                  break;
              }
            } catch (e) {
              console.warn('Failed to parse event data:', e);
            }
          }
        }
      }
    } finally {
      reader.releaseLock();
    }

    // Add assistant message
    if (fullResponse) {
      const aiMessage = {
        id: Date.now(),
        message: fullResponse,
        role: 'assistant',
        created_at: new Date().toISOString(),
        meta: {
          source: 'llm_stream',
          intent: lastIntent,
          sentiment: lastSentiment,
        },
      };
      setMessages(prev => [...prev, aiMessage]);
      setStreamingText('');
    }
  };

  /**
   * Send non-streaming message (fallback)
   */
  const sendNonStreamingMessage = async (userMessage, userMsgId) => {
    const response = await axios.post('/api/chatbot/send-message', {
      message: userMessage,
      conversation_id: conversationId,
    }, {
      headers: {
        'X-Session-ID': sessionIdRef.current,
      },
    });

    if (response.data.rate_limited) {
      handleRateLimit(response.data);
      setMessages(prev => prev.filter(m => m.id !== userMsgId));
      return;
    }

    const aiResponse = response.data.ai_response || response.data.message;
    const meta = response.data.meta || {};

    const aiMessage = {
      id: Date.now(),
      message: aiResponse,
      role: 'assistant',
      created_at: response.data.timestamp || new Date().toISOString(),
      meta: meta,
    };

    setMessages(prev => [...prev, aiMessage]);
    
    if (response.data.conversation_id) {
      setConversationId(response.data.conversation_id);
      localStorage.setItem('chatbot_current_conversation_id', response.data.conversation_id);
    }

    if (meta.rate_limit_remaining !== undefined) {
      setRateLimitInfo(prev => ({
        ...prev,
        remaining: meta.rate_limit_remaining,
        isLimited: meta.rate_limit_remaining <= 0,
      }));
    }
  };

  /**
   * Stop streaming
   */
  const stopStreaming = useCallback(() => {
    if (streamAbortRef.current) {
      streamAbortRef.current.abort();
      streamAbortRef.current = null;
    }
    setStreaming(false);
    
    // Keep whatever we got so far
    if (streamingText) {
      const partialMessage = {
        id: Date.now(),
        message: streamingText + ' [stopped]',
        role: 'assistant',
        created_at: new Date().toISOString(),
        partial: true,
      };
      setMessages(prev => [...prev, partialMessage]);
      setStreamingText('');
    }
  }, [streamingText]);

  /**
   * Handle rate limit response
   */
  const handleRateLimit = (data) => {
    const limitInfo = data.rate_limit_info || {};
    setIsRateLimited(true);
    setRateLimitInfo({
      remaining: limitInfo.remaining || 0,
      isLimited: true,
      mustStartNew: limitInfo.must_start_new || data.must_start_new_conversation || false,
    });
    setError(data.message || 'Rate limit reached.');
  };

  /**
   * Handle errors
   */
  const handleError = (err, userMsgId) => {
    console.error('Chat error:', err);
    
    if (err.response?.status === 429) {
      handleRateLimit(err.response.data);
    } else {
      let errorMsg = 'Failed to send message. Please try again.';
      if (err.message === 'AbortError') {
        errorMsg = 'Request cancelled.';
      } else if (err.response?.data?.message) {
        errorMsg = err.response.data.message;
      }
      setError(errorMsg);
    }
    
    // Remove user message on error
    setMessages(prev => prev.filter(m => m.id !== userMsgId));
  };

  /**
   * Start a new conversation
   */
  const startNewConversation = useCallback(async () => {
    try {
      setConversationId(null);
      setMessages([]);
      setError(null);
      setStreamingText('');
      setIsRateLimited(false);
      setRateLimitInfo({ remaining: 50, isLimited: false, mustStartNew: false });
      setActionSuggestions([]);
      setQuickActions([]);
      localStorage.removeItem('chatbot_current_conversation_id');

      // Fetch fresh suggestions
      await fetchSuggestions();
    } catch (err) {
      console.error('Failed to start new conversation:', err);
    }
  }, []);

  /**
   * Fetch suggestions for current context
   */
  const fetchSuggestions = async (intent = null) => {
    try {
      const response = await axios.get('/api/chatbot/suggestions', {
        params: { intent },
      });
      if (response.data.success) {
        setActionSuggestions(response.data.data.action_suggestions || []);
        setSuggestedQuestions(response.data.data.suggested_questions || []);
      }
    } catch (err) {
      console.warn('Failed to fetch suggestions:', err);
    }
  };

  /**
   * Clear history
   */
  const clearHistory = useCallback(async () => {
    try {
      await axios.delete('/api/chatbot/clear-history', {
        data: { conversation_id: conversationId },
      });
      setMessages([]);
      setConversationId(null);
      setError(null);
      setStreamingText('');
    } catch (err) {
      console.error('Failed to clear history:', err);
      setError('Failed to clear history');
    }
  }, [conversationId]);

  /**
   * Load conversations list
   */
  const loadConversations = useCallback(async () => {
    setConversationsLoading(true);
    try {
      const response = await axios.get('/api/chatbot/conversations');
      if (response.data.success) {
        setConversations(response.data.data || []);
      }
    } catch (err) {
      console.error('Failed to load conversations:', err);
    } finally {
      setConversationsLoading(false);
    }
  }, []);

  /**
   * Switch to a different conversation
   */
  const switchConversation = useCallback(async (convId) => {
    setStreamingText('');
    setError(null);
    await loadChatHistory(convId);
  }, [loadChatHistory]);

  /**
   * Delete a conversation
   */
  const deleteConversation = useCallback(async (convId) => {
    try {
      await axios.delete(`/api/chatbot/conversations/${convId}`);
      setConversations(prev => prev.filter(c => c.conversation_id !== convId));
      
      if (convId === conversationId) {
        await startNewConversation();
      }
    } catch (err) {
      console.error('Failed to delete conversation:', err);
      setError('Failed to delete conversation');
    }
  }, [conversationId, startNewConversation]);

  /**
   * Set chatbot personality
   */
  const changePersonality = useCallback(async (newPersonality) => {
    setPersonality(newPersonality);
    try {
      await axios.post('/api/chatbot/preferences', {
        key: 'personality',
        value: newPersonality,
      });
    } catch (err) {
      // Preferences are optional
      console.debug('Failed to save personality preference:', err);
    }
  }, []);

  // Scroll to bottom helper
  const scrollToBottom = () => {
    setTimeout(() => {
      messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' });
    }, 100);
  };

  // Toggle history panel
  const toggleHistory = useCallback(() => {
    setShowHistory(prev => {
      if (!prev) loadConversations();
      return !prev;
    });
  }, [loadConversations]);

  return {
    // State
    messages,
    loading,
    streaming,
    streamingText,
    error,
    conversationId,
    
    // LLM Status
    llmStatus,
    
    // Personality
    personality,
    availablePersonalities,
    changePersonality,
    
    // Suggestions
    actionSuggestions,
    quickActions,
    suggestedQuestions,
    
    // Rate limiting
    rateLimitInfo,
    isRateLimited,
    
    // Context
    lastIntent,
    lastSentiment,
    
    // Conversation management
    conversations,
    conversationsLoading,
    showHistory,
    toggleHistory,
    setShowHistory,
    
    // Actions
    sendMessage,
    stopStreaming,
    clearHistory,
    loadChatHistory,
    startNewConversation,
    switchConversation,
    deleteConversation,
    fetchSuggestions,
    setError,
    
    // Refs
    messagesEndRef,
  };
};

export default useStreamingChatbot;
