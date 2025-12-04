import { useState, useCallback, useRef, useEffect } from 'react';
import axios from 'axios';

export const useChatbot = () => {
  const [messages, setMessages] = useState([]);
  const [loading, setLoading] = useState(false);
  const [conversationId, setConversationId] = useState(null);
  const [error, setError] = useState(null);
  const messagesEndRef = useRef(null);

  // Load chat history on mount
  useEffect(() => {
    loadChatHistory();
  }, []);

  const scrollToBottom = () => {
    setTimeout(() => {
      messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' });
    }, 100);
  };

  const loadChatHistory = useCallback(async () => {
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
      setError('Failed to load chat history');
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

      if (response.data.success) {
        const aiMessage = {
          id: Date.now() + 1,
          message: response.data.ai_response,
          role: 'assistant',
          created_at: response.data.timestamp,
          source: 'huggingface'
        };

        setMessages((prev) => [...prev, aiMessage]);
        setConversationId(response.data.conversation_id);
      }
    } catch (err) {
      console.error('Failed to send message:', err);
      const errorMsg = err.response?.data?.message || 'Failed to send message. Please try again.';
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
    } catch (err) {
      console.error('Failed to clear all history:', err);
      setError('Failed to clear chat history');
    }
  }, []);

  return {
    messages,
    loading,
    conversationId,
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
