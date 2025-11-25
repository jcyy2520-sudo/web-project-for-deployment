import { useState, useEffect } from 'react';
import { useApi } from '../hooks/useApi';
import axios from 'axios';
import {
  DocumentArrowUpIcon,
  DocumentArrowDownIcon,
  TrashIcon,
  EyeIcon,
  ClockIcon,
  DocumentIcon
} from '@heroicons/react/24/outline';

const DocumentUpload = ({ appointmentId, onUploadSuccess }) => {
  const { callApi } = useApi();
  const [documents, setDocuments] = useState([]);
  const [loading, setLoading] = useState(false);
  const [uploading, setUploading] = useState(false);
  const [selectedFile, setSelectedFile] = useState(null);
  const [documentType, setDocumentType] = useState('other');
  const [description, setDescription] = useState('');
  const [showUploadForm, setShowUploadForm] = useState(false);

  const documentTypes = [
    'ID Proof',
    'Power of Attorney',
    'Contract',
    'Deed',
    'Affidavit',
    'Notarized Document',
    'Other'
  ];

  useEffect(() => {
    loadDocuments();
  }, [appointmentId]);

  const loadDocuments = async () => {
    setLoading(true);
    const result = await callApi((signal) =>
      axios.get(`/api/documents${appointmentId ? `?appointment_id=${appointmentId}` : ''}`, { signal })
    );

    if (result.success) {
      setDocuments(result.data.data || []);
    }
    setLoading(false);
  };

  const handleFileSelect = (e) => {
    setSelectedFile(e.target.files[0]);
  };

  const handleUpload = async () => {
    if (!selectedFile || !documentType) {
      window.showToast?.('Error', 'Please select a file and document type', 'error');
      return;
    }

    setUploading(true);
    const formData = new FormData();
    formData.append('file', selectedFile);
    formData.append('document_type', documentType);
    formData.append('description', description);
    if (appointmentId) {
      formData.append('appointment_id', appointmentId);
    }

    const result = await callApi((signal) =>
      axios.post('/api/documents', formData, {
        signal,
        headers: { 'Content-Type': 'multipart/form-data' }
      })
    );

    if (result.success) {
      window.showToast?.('Success', 'Document uploaded successfully', 'success');
      setDocuments(prev => [result.data.data, ...prev]);
      setSelectedFile(null);
      setDocumentType('other');
      setDescription('');
      setShowUploadForm(false);
      onUploadSuccess?.();
    } else {
      window.showToast?.('Error', 'Failed to upload document', 'error');
    }
    setUploading(false);
  };

  const handleDownload = async (documentId) => {
    const result = await callApi((signal) =>
      axios.get(`/api/documents/${documentId}/download`, {
        signal,
        responseType: 'blob'
      })
    );

    if (result.success) {
      const url = window.URL.createObjectURL(new Blob([result.data]));
      const link = document.createElement('a');
      link.href = url;
      link.download = result.data.filename || 'document';
      document.body.appendChild(link);
      link.click();
      link.parentChild.removeChild(link);
      window.URL.revokeObjectURL(url);
    }
  };

  const handleDelete = async (documentId) => {
    if (!window.confirm('Are you sure you want to delete this document?')) return;

    const result = await callApi((signal) =>
      axios.delete(`/api/documents/${documentId}`, { signal })
    );

    if (result.success) {
      setDocuments(prev => prev.filter(d => d.id !== documentId));
      window.showToast?.('Success', 'Document deleted successfully', 'success');
    }
  };

  const getFileIcon = (mimeType) => {
    if (mimeType.includes('pdf')) return '📄';
    if (mimeType.includes('image')) return '🖼️';
    if (mimeType.includes('word') || mimeType.includes('document')) return '📝';
    return '📎';
  };

  return (
    <div className="space-y-4">
      {/* Header */}
      <div className="flex justify-between items-center">
        <div>
          <h3 className="text-sm font-semibold text-amber-50 flex items-center">
            <DocumentIcon className="h-4 w-4 mr-2" />
            Documents
          </h3>
          <p className="text-xs text-gray-400 mt-1">Manage your notary documents</p>
        </div>
        {!showUploadForm && (
          <button
            onClick={() => setShowUploadForm(true)}
            className="px-3 py-1.5 bg-amber-500/20 text-amber-400 hover:bg-amber-500/30 border border-amber-500/50 rounded text-xs font-medium transition-all"
          >
            <DocumentArrowUpIcon className="h-3.5 w-3.5 inline mr-1" />
            Upload
          </button>
        )}
      </div>

      {/* Upload Form */}
      {showUploadForm && (
        <div className="bg-gray-800/50 border border-gray-700 rounded-lg p-4">
          <h4 className="text-sm font-semibold text-amber-50 mb-3">Upload Document</h4>

          <div className="space-y-3">
            {/* Document Type */}
            <div>
              <label className="block text-xs font-medium text-amber-50 mb-1">
                Document Type
              </label>
              <select
                value={documentType}
                onChange={(e) => setDocumentType(e.target.value)}
                className="w-full bg-gray-900 border border-gray-600 rounded px-2 py-2 text-xs text-amber-50 focus:outline-none focus:border-amber-500 transition-colors"
              >
                {documentTypes.map(type => (
                  <option key={type} value={type}>{type}</option>
                ))}
              </select>
            </div>

            {/* File Input */}
            <div>
              <label className="block text-xs font-medium text-amber-50 mb-1">
                Select File
              </label>
              <input
                type="file"
                onChange={handleFileSelect}
                accept=".pdf,.doc,.docx,.jpg,.jpeg,.png"
                className="w-full text-xs text-gray-400 file:mr-2 file:px-2 file:py-1 file:rounded file:border-0 file:bg-amber-500/20 file:text-amber-400 file:cursor-pointer"
              />
              <p className="text-xs text-gray-500 mt-1">
                {selectedFile ? `Selected: ${selectedFile.name}` : 'Max 20MB - PDF, DOC, JPG, PNG'}
              </p>
            </div>

            {/* Description */}
            <div>
              <label className="block text-xs font-medium text-amber-50 mb-1">
                Description (Optional)
              </label>
              <textarea
                value={description}
                onChange={(e) => setDescription(e.target.value)}
                placeholder="Add notes about this document..."
                rows="2"
                className="w-full bg-gray-900 border border-gray-600 rounded px-2 py-2 text-xs text-amber-50 placeholder-gray-500 focus:outline-none focus:border-amber-500 transition-colors"
              />
            </div>

            {/* Actions */}
            <div className="flex gap-2">
              <button
                onClick={handleUpload}
                disabled={uploading || !selectedFile}
                className="flex-1 px-3 py-2 bg-amber-600 hover:bg-amber-700 disabled:bg-gray-600 text-white rounded text-xs font-medium transition-colors"
              >
                {uploading ? 'Uploading...' : 'Upload'}
              </button>
              <button
                onClick={() => {
                  setShowUploadForm(false);
                  setSelectedFile(null);
                  setDescription('');
                }}
                className="flex-1 px-3 py-2 bg-gray-700 hover:bg-gray-600 text-white rounded text-xs font-medium transition-colors"
              >
                Cancel
              </button>
            </div>
          </div>
        </div>
      )}

      {/* Documents List */}
      {loading ? (
        <div className="text-center py-4 text-gray-400 text-xs">Loading...</div>
      ) : documents.length === 0 ? (
        <div className="text-center py-6">
          <DocumentIcon className="h-8 w-8 text-gray-600 mx-auto mb-2" />
          <p className="text-gray-400 text-xs">No documents uploaded yet</p>
        </div>
      ) : (
        <div className="space-y-2">
          {documents.map(doc => (
            <div
              key={doc.id}
              className="bg-gray-800/50 border border-gray-700 rounded-lg p-3 hover:border-amber-500/30 transition-all"
            >
              <div className="flex items-start justify-between gap-2">
                <div className="flex items-start gap-2 flex-1">
                  <span className="text-lg flex-shrink-0">{getFileIcon(doc.mime_type)}</span>
                  <div className="min-w-0 flex-1">
                    <h4 className="text-xs font-medium text-amber-50 truncate">
                      {doc.original_name}
                    </h4>
                    <div className="flex items-center gap-2 mt-1 flex-wrap">
                      <span className="text-xs text-gray-400">
                        {doc.document_type}
                      </span>
                      <span className="text-xs text-gray-500 flex items-center">
                        <ClockIcon className="h-3 w-3 mr-1" />
                        {new Date(doc.created_at).toLocaleDateString()}
                      </span>
                      <span className="text-xs text-gray-500">
                        {(doc.file_size / 1024 / 1024).toFixed(2)} MB
                      </span>
                    </div>
                    {doc.description && (
                      <p className="text-xs text-gray-400 mt-1 line-clamp-1">
                        {doc.description}
                      </p>
                    )}
                  </div>
                </div>

                <div className="flex gap-1 flex-shrink-0">
                  <button
                    onClick={() => handleDownload(doc.id)}
                    className="p-1.5 text-gray-400 hover:text-amber-400 hover:bg-gray-700 rounded transition-all"
                    title="Download"
                  >
                    <DocumentArrowDownIcon className="h-4 w-4" />
                  </button>
                  <button
                    onClick={() => handleDelete(doc.id)}
                    className="p-1.5 text-gray-400 hover:text-red-400 hover:bg-gray-700 rounded transition-all"
                    title="Delete"
                  >
                    <TrashIcon className="h-4 w-4" />
                  </button>
                </div>
              </div>
            </div>
          ))}
        </div>
      )}
    </div>
  );
};

export default DocumentUpload;
