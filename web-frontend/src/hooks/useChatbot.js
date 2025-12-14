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
    remaining: 20,
    isLimited: false,
    mustStartNew: false,
    conversationLimit: 20,
  });
  const [isRateLimited, setIsRateLimited] = useState(false);
  const [rateLimitMessage, setRateLimitMessage] = useState(null);
  
  // Language and sentiment state
  const [detectedLanguage, setDetectedLanguage] = useState('en');
  const [lastSentiment, setLastSentiment] = useState('neutral');
  
  const messagesEndRef = useRef(null);
  const lastUserActionRef = useRef(Date.now());
  const sessionIdRef = useRef(null);

  // Generate or retrieve session ID for guests
  useEffect(() => {
    if (!sessionIdRef.current) {
      sessionIdRef.current = localStorage.getItem('chatbot_session_id') || 
        `session_${Date.now()}_${Math.random().toString(36).substr(2, 9)}`;
      localStorage.setItem('chatbot_session_id', sessionIdRef.current);
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
        
        const response = await axios.get(url);
        if (response.data.success) {
          setMessages(response.data.data || []);
          setLastMessageCount(response.data.data?.length || 0);
          
          // If there was a saved conversation, use it. Otherwise use the last message's conversation
          if (savedConversationId) {
            setConversationId(savedConversationId);
          } else {
            const lastMsg = response.data.data?.[response.data.data.length - 1];
            if (lastMsg) {
              setConversationId(lastMsg.conversation_id);
              localStorage.setItem('chatbot_current_conversation_id', lastMsg.conversation_id);
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
    if (!pollingEnabled) return;
    
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

        const response = await axios.get(`/api/chatbot/history?limit=50&conversation_id=${encodeURIComponent(convId)}`);
        if (response.data.success && response.data.data) {
          const serverMessages = response.data.data;
          // Only update if server has different messages to avoid unnecessary re-renders
          // and to preserve optimistic updates in progress
          if (serverMessages.length !== lastMessageCount) {
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
  }, [pollingEnabled, lastMessageCount]);

  const scrollToBottom = () => {
    setTimeout(() => {
      messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' });
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

    // Check if rate limited before sending
    if (isRateLimited && rateLimitInfo.mustStartNew) {
      setError(rateLimitMessage || 'Message limit reached. Please start a new conversation.');
      return;
    }

    setError(null);
    setRateLimitMessage(null);
    // Track user action to prevent polling from overwriting optimistic updates
    lastUserActionRef.current = Date.now();
    
    const newUserMessage = {
      id: Date.now(),
      message: userMessage,
      role: 'user',
      created_at: new Date().toISOString(),
      source: 'user'
    };

    setMessages((prev) => [...prev, newUserMessage]);
    setLastMessageCount((prev) => prev + 1);
    setLoading(true);

    try {
      const response = await axios.post('/api/chatbot/send-message', {
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

      // Normalize ai_response/message
      const aiResponseText = typeof response.data?.ai_response === 'string' && response.data.ai_response.trim().length > 0
        ? response.data.ai_response
        : (typeof response.data?.message === 'string' ? response.data.message : '');

      if (!aiResponseText) {
        console.error('Invalid ai_response format:', response.data);
        setError('Invalid response format from server');
        setMessages((prev) => prev.filter((msg) => msg.id !== newUserMessage.id));
        return;
      }

      const aiMessage = {
        id: Date.now() + 1,
        message: aiResponseText,
        role: 'assistant',
        created_at: response.data.timestamp || new Date().toISOString(),
        source: meta?.source || meta?.meta_source || 'huggingface',
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

      // Save user message to Message Center (handle auth errors gracefully)
      try {
        await axios.post('/api/chatbot/save-to-messages', {
          message: userMessage,
          role: 'user',
          conversation_id: response.data.conversation_id
        });
      } catch (saveErr) {
        // Only warn for non-401 errors; 401 is expected for guests
        if (saveErr.response?.status !== 401) {
          console.warn('Failed to save user message to Message Center:', saveErr);
        }
      }

      // Save AI response to Message Center (handle auth errors gracefully)
      try {
        await axios.post('/api/chatbot/save-to-messages', {
          message: aiResponseText,
          role: 'assistant',
          conversation_id: response.data.conversation_id
        });
      } catch (saveErr) {
        // Only warn for non-401 errors; 401 is expected for guests
        if (saveErr.response?.status !== 401) {
          console.warn('Failed to save AI response to Message Center:', saveErr);
        }
      }
    } catch (err) {
      console.error('Failed to send message:', err);
      
      // Check for rate limit error (429)
      if (err.response?.status === 429) {
        const limitInfo = err.response?.data?.rate_limit_info || {};
        setIsRateLimited(true);
        setRateLimitInfo({
          remaining: 0,
          isLimited: true,
          mustStartNew: err.response?.data?.must_start_new_conversation || true,
          conversationLimit: 20,
        });
        setRateLimitMessage(err.response?.data?.message || 'Message limit reached. Please start a new conversation.');
        setError(err.response?.data?.message || 'Rate limit exceeded. Please start a new conversation.');
        setMessages((prev) => prev.filter((msg) => msg.id !== newUserMessage.id));
        setLastMessageCount((prev) => Math.max(0, prev - 1));
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
      }
      
      setError(errorMsg);

      // Remove the user message on error
      setMessages((prev) => prev.filter((msg) => msg.id !== newUserMessage.id));
      setLastMessageCount((prev) => Math.max(0, prev - 1));
    } finally {
      setLoading(false);
      lastUserActionRef.current = Date.now() + 3000; // Extend cooldown after send attempt
      scrollToBottom();
    }
  }, [conversationId, isRateLimited, rateLimitInfo, rateLimitMessage]);

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
      const response = await axios.get('/api/chatbot/conversations');
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
        remaining: 20,
        isLimited: false,
        mustStartNew: false,
        conversationLimit: 20,
      });
      setRateLimitMessage(null);
      
      const response = await axios.post('/api/chatbot/conversations/new', {}, {
        headers: {
          'X-Session-ID': sessionIdRef.current
        }
      });
      if (response.data.success) {
        const newConvId = response.data.conversation_id;
        setConversationId(newConvId);
        setMessages([]);
        setLastMessageCount(0);
        setLastSuggestions([]);
        lastUserActionRef.current = Date.now();
        
        // IMPORTANT: Save the new conversation ID to localStorage so it persists
        localStorage.setItem('chatbot_current_conversation_id', newConvId);
        
        // Refresh conversations list
        await loadConversations();
        return newConvId;
      }
    } catch (err) {
      console.error('Failed to start new conversation:', err);
      setError('Failed to start new conversation');
      return null;
    }
  }, [loadConversations]);

  // Switch to a specific conversation
  const switchConversation = useCallback(async (targetConversationId) => {
    try {
      setError(null);
      lastUserActionRef.current = Date.now();
      
      if (!targetConversationId) {
        setMessages([]);
        setConversationId(null);
        setLastMessageCount(0);
        localStorage.removeItem('chatbot_current_conversation_id');
        return;
      }

      const response = await axios.get(`/api/chatbot/conversations/${targetConversationId}`);
      if (response.data.success) {
        setMessages(response.data.data || []);
        setConversationId(targetConversationId);
        // Save the conversation ID to localStorage
        localStorage.setItem('chatbot_current_conversation_id', targetConversationId);
        setLastMessageCount(response.data.data?.length || 0);
        setLastSuggestions([]);
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
      
      // If we deleted the current conversation, clear messages
      if (targetConversationId === conversationId) {
        setMessages([]);
        setConversationId(null);
        setLastMessageCount(0);
        setLastSuggestions([]);
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

  return {
    messages,
    loading,
    conversationId,
    lastSuggestions,
    error,
    sendMessage,
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
  };
};

export default useChatbot;
