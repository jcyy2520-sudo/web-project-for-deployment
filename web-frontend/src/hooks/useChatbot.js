import { useState, useCallback, useRef, useEffect } from 'react';
import axios from 'axios';

export const useChatbot = () => {
  const [messages, setMessages] = useState([]);
  const [loading, setLoading] = useState(false);
  const [conversationId, setConversationId] = useState(null);
  const [lastSuggestions, setLastSuggestions] = useState([]);
  const [error, setError] = useState(null);
  const [pollingEnabled, setPollingEnabled] = useState(true);
  const messagesEndRef = useRef(null);

  // Load chat history on mount
  useEffect(() => {
    const loadInitialHistory = async () => {
      try {
        const response = await axios.get('/api/chatbot/history?limit=50');
        if (response.data.success) {
          setMessages(response.data.data || []);
          const lastMsg = response.data.data?.[response.data.data.length - 1];
          if (lastMsg) {
            setConversationId(lastMsg.conversation_id);
          }
        }
      } catch (err) {
        console.error('Failed to load initial chat history:', err);
        // Silently fail on mount to not disrupt user experience
      }
    };
    
    loadInitialHistory();
  }, []);

  // Lightweight polling to keep the chat UI in sync with server-side changes
  useEffect(() => {
    if (!pollingEnabled) return;
    
    const pollChatHistory = async () => {
      try {
        const response = await axios.get('/api/chatbot/history?limit=50');
        if (response.data.success) {
          setMessages(response.data.data || []);
          const lastMsg = response.data.data?.[response.data.data.length - 1];
          if (lastMsg) {
            setConversationId(lastMsg.conversation_id);
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
    
    const id = setInterval(pollChatHistory, 15000);
    return () => clearInterval(id);
  }, [pollingEnabled]);

  const scrollToBottom = () => {
    setTimeout(() => {
      messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' });
    }, 100);
  };

  const loadChatHistory = useCallback(async ({ silent = false, ignoreErrors = false } = {}) => {
    try {
      const response = await axios.get('/api/chatbot/history?limit=50');
      if (response.data.success) {
        setMessages(response.data.data || []);
        // Set conversation ID from the last message if exists
        const lastMsg = response.data.data?.[response.data.data.length - 1];
        if (lastMsg) {
          setConversationId(lastMsg.conversation_id);
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
  }, []);

  const sendMessage = useCallback(async (userMessage) => {
    if (!userMessage.trim()) return;

    setError(null);
    const newUserMessage = {
      id: Date.now(),
      message: userMessage,
      role: 'user',
      created_at: new Date().toISOString(),
      source: 'user'
    };

    setMessages((prev) => [...prev, newUserMessage]);
    setLoading(true);

    try {
      const response = await axios.post('/api/chatbot/send-message', {
        message: userMessage,
        conversation_id: conversationId
      });

      // Validate response structure
      if (!response.data) {
        throw new Error('Empty response from server');
      }

      // Check for API-level success flag
      if (response.data.success === false) {
        const errorMsg = response.data?.message || 'Failed to get response from chatbot';
        setError(errorMsg);
        setMessages((prev) => prev.filter((msg) => msg.id !== newUserMessage.id));
        return;
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

      // Validate meta object
      const meta = (response.data.meta && typeof response.data.meta === 'object') ? response.data.meta : {};

      const aiMessage = {
        id: Date.now() + 1,
        message: aiResponseText,
        role: 'assistant',
        created_at: response.data.timestamp || new Date().toISOString(),
        source: meta?.source || meta?.meta_source || 'huggingface',
        suggestions: Array.isArray(meta?.suggestions) ? meta.suggestions : [],
        meta: meta || {}
      };

      // Persist suggestions from the assistant so the UI can show them
      if (Array.isArray(aiMessage.suggestions) && aiMessage.suggestions.length > 0) {
        setLastSuggestions(aiMessage.suggestions);
      }

      setMessages((prev) => [...prev, aiMessage]);
      
      // Validate conversation_id
      if (response.data.conversation_id) {
        setConversationId(response.data.conversation_id);
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
    } finally {
      setLoading(false);
      scrollToBottom();
    }
  }, [conversationId]);

  const clearHistory = useCallback(async () => {
    try {
      await axios.delete('/api/chatbot/clear-history', {
        data: { conversation_id: conversationId }
      });
      setMessages([]);
      setConversationId(null);
      setError(null);
      setLastSuggestions([]);
    } catch (err) {
      console.error('Failed to clear history:', err);
      setError('Failed to clear chat history');
    }
  }, [conversationId]);

  const clearAllHistory = useCallback(async () => {
    try {
      await axios.delete('/api/chatbot/clear-history');
      setMessages([]);
      setConversationId(null);
      setError(null);
      setLastSuggestions([]);
    } catch (err) {
      console.error('Failed to clear all history:', err);
      setError('Failed to clear chat history');
    }
  }, []);

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
    setError
  };
};

export default useChatbot;
