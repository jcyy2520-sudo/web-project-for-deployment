/**
 * Document State Management Hooks
 * Centralized hooks for document-related operations
 */

import { useState, useCallback } from 'react';
import axios from 'axios';

/**
 * useDocuments - Fetch and manage documents list
 * 
 * @param {Object} options Configuration options
 * @param {string} options.type Filter by document type
 * @param {number} options.page Current page
 * @returns {Object} Documents state and methods
 */
export function useDocuments(options = {}) {
  const [documents, setDocuments] = useState([]);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);
  const [pagination, setPagination] = useState(null);

  const fetchDocuments = useCallback(async (filters = {}) => {
    setLoading(true);
    setError(null);

    try {
      const response = await axios.get('/api/documents', {
        params: {
          page: options.page || 1,
          per_page: options.perPage || 15,
          type: options.type,
          ...filters,
        }
      });

      if (response.data.success) {
        setDocuments(response.data.data);
        setPagination(response.data.pagination);
      }
    } catch (err) {
      setError(err.response?.data?.message || 'Failed to fetch documents');
    } finally {
      setLoading(false);
    }
  }, [options]);

  const deleteDocument = useCallback(async (documentId) => {
    try {
      const response = await axios.delete(`/api/documents/${documentId}`);
      if (response.data.success) {
        setDocuments(prev => prev.filter(doc => doc.id !== documentId));
      }
      return response.data;
    } catch (err) {
      setError(err.response?.data?.message || 'Failed to delete document');
      throw err;
    }
  }, []);

  return {
    documents,
    loading,
    error,
    pagination,
    fetchDocuments,
    deleteDocument,
  };
}

/**
 * useDocumentUpload - Handle document upload
 * 
 * @returns {Object} Upload state and methods
 */
export function useDocumentUpload() {
  const [uploading, setUploading] = useState(false);
  const [uploadProgress, setUploadProgress] = useState(0);
  const [error, setError] = useState(null);

  const upload = useCallback(async (file, metadata = {}) => {
    setUploading(true);
    setUploadProgress(0);
    setError(null);

    const formData = new FormData();
    formData.append('file', file);
    Object.entries(metadata).forEach(([key, value]) => {
      formData.append(key, value);
    });

    try {
      const response = await axios.post('/api/documents', formData, {
        headers: { 'Content-Type': 'multipart/form-data' },
        onUploadProgress: (progressEvent) => {
          const progress = Math.round(
            (progressEvent.loaded * 100) / progressEvent.total
          );
          setUploadProgress(progress);
        },
      });

      if (response.data.success) {
        setUploadProgress(100);
        return response.data.data;
      }
    } catch (err) {
      setError(err.response?.data?.message || 'Upload failed');
      throw err;
    } finally {
      setUploading(false);
    }
  }, []);

  return {
    uploading,
    uploadProgress,
    error,
    upload,
  };
}

/**
 * useDocumentTypes - Get document type options
 * 
 * @returns {Object} Document types configuration
 */
export function useDocumentTypes() {
  const types = {
    invoice: { label: 'Invoice', category: 'financial' },
    receipt: { label: 'Receipt', category: 'financial' },
    report: { label: 'Report', category: 'business' },
    contract: { label: 'Contract', category: 'legal' },
    id: { label: 'ID Document', category: 'identity' },
    other: { label: 'Other', category: 'general' },
  };

  return {
    types,
    getType: (type) => types[type] || types.other,
    typeList: Object.entries(types).map(([key, value]) => ({
      value: key,
      ...value,
    })),
  };
}
