import React, { useState, useEffect } from 'react';
import axios from 'axios';
import {
  PlusIcon,
  PencilIcon,
  TrashIcon,
  XMarkIcon,
  ArrowPathIcon,
  ExclamationTriangleIcon,
  MagnifyingGlassIcon,
  CheckCircleIcon
} from '@heroicons/react/24/outline';

const AdminServices = ({ isDarkMode = true }) => {
  const [services, setServices] = useState([]);
  const [archivedServices, setArchivedServices] = useState([]);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');
  const [success, setSuccess] = useState('');
  const [searchTerm, setSearchTerm] = useState('');
  const [showModal, setShowModal] = useState(false);
  const [showArchive, setShowArchive] = useState(false);
  const [editingService, setEditingService] = useState(null);
  const [formData, setFormData] = useState({
    name: '',
    description: '',
    price: '',
    duration: ''
  });

  useEffect(() => {
    loadServices();
  }, []);

  const loadServices = async () => {
    try {
      setLoading(true);
      const response = await axios.get('/api/admin/services');
      
      const servicesData = response.data.data || [];
      // Filter to show only active services in the main list
      const activeServices = Array.isArray(servicesData) 
        ? servicesData.filter(s => !s.deleted_at) 
        : [];
      
      setServices(activeServices);
      
      // If no services exist, auto-sync default types
      if (activeServices.length === 0 && servicesData.length === 0) {
        await syncDefaultServices();
      } else {
        setError('');
      }
    } catch (err) {
      setError(err.response?.data?.message || 'Failed to load services');
      setServices([]);
    } finally {
      setLoading(false);
    }
  };

  const syncDefaultServices = async () => {
    try {
      setSuccess('Syncing default appointment types...');
      await axios.post('/api/admin/services/sync/defaults');
      setSuccess('Default services synced successfully');
      // Reload services after sync
      const response = await axios.get('/api/admin/services');
      const servicesData = response.data.data || [];
      const activeServices = Array.isArray(servicesData) 
        ? servicesData.filter(s => !s.deleted_at) 
        : [];
      setServices(activeServices);
      // Notify Dashboard to refresh
      window.dispatchEvent(new CustomEvent('servicesUpdated', { detail: { timestamp: new Date() } }));
    } catch (err) {
      console.error('Failed to sync default services:', err);
      setError(err.response?.data?.message || 'Failed to sync default services');
    }
  };

  const loadArchivedServices = async () => {
    try {
      const response = await axios.get('/api/admin/services');
      const allServices = response.data.data || [];
      // Filter to show only archived services
      const archived = Array.isArray(allServices)
        ? allServices.filter(s => s.deleted_at)
        : [];
      setArchivedServices(archived);
    } catch (err) {
      console.error('Failed to load archived services:', err);
    }
  };

  const handleOpenModal = (service = null) => {
    if (service) {
      setEditingService(service);
      setFormData({
        name: service.name,
        description: service.description || '',
        price: service.price || '',
        duration: service.duration || ''
      });
    } else {
      setEditingService(null);
      // Only initialize name, not description, price, duration
      setFormData({ name: '', description: '', price: '', duration: '' });
    }
    setShowModal(true);
  };

  const handleCloseModal = () => {
    setShowModal(false);
    setEditingService(null);
    setFormData({ name: '', description: '', price: '', duration: '' });
  };

  const handleInputChange = (e) => {
    const { name, value } = e.target;
    setFormData(prev => ({
      ...prev,
      [name]: value
    }));
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    setError('');
    setSuccess('');

    try {
      setLoading(true);
      
      if (editingService) {
        await axios.put(`/api/admin/services/${editingService.id}`, formData);
        setSuccess('Service updated successfully');
      } else {
        await axios.post('/api/admin/services', formData);
        setSuccess('Service created successfully');
      }
      
      handleCloseModal();
      loadServices();
      
      // Notify Dashboard to refresh appointment types by dispatching a custom event
      window.dispatchEvent(new CustomEvent('servicesUpdated', { detail: { timestamp: new Date() } }));
    } catch (err) {
      setError(err.response?.data?.message || 'Failed to save service');
    } finally {
      setLoading(false);
    }
  };

  const handleDelete = async (serviceId) => {
    if (!window.confirm('Are you sure you want to archive this service?')) return;

    try {
      setLoading(true);
      await axios.delete(`/api/admin/services/${serviceId}`);
      setSuccess('Service archived successfully');
      loadServices();
      
      // Notify Dashboard to refresh appointment types
      window.dispatchEvent(new CustomEvent('servicesUpdated', { detail: { timestamp: new Date() } }));
    } catch (err) {
      setError(err.response?.data?.message || 'Failed to archive service');
    } finally {
      setLoading(false);
    }
  };

  const handleRestore = async (serviceId) => {
    try {
      setLoading(true);
      await axios.put(`/api/admin/services/${serviceId}/restore`);
      setSuccess('Service restored successfully');
      loadServices();
      loadArchivedServices();
      
      // Notify Dashboard to refresh appointment types
      window.dispatchEvent(new CustomEvent('servicesUpdated', { detail: { timestamp: new Date() } }));
    } catch (err) {
      setError(err.response?.data?.message || 'Failed to restore service');
    } finally {
      setLoading(false);
    }
  };

  const filteredServices = services.filter(service =>
    service.name.toLowerCase().includes(searchTerm.toLowerCase()) ||
    service.description?.toLowerCase().includes(searchTerm.toLowerCase())
  );

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:justify-between sm:items-start gap-3 sm:gap-4">
        <div>
          <h2 className={`text-lg font-bold ${isDarkMode ? 'text-amber-50' : 'text-amber-900'} transition-colors duration-300`}>
            Services Management
          </h2>
          <p className={`${isDarkMode ? 'text-amber-400/70' : 'text-amber-700/70'} mt-1 text-sm transition-colors duration-300`}>
            Manage service types offered
          </p>
        </div>
        <div className="flex flex-col sm:flex-row gap-2 w-full sm:w-auto">
          <button
            onClick={syncDefaultServices}
            className={`px-3 py-1.5 border text-xs sm:text-sm ${isDarkMode ? 'border-green-500/30 text-green-50 hover:bg-green-500/10' : 'border-green-300 text-green-900 hover:bg-green-100'} rounded transition-all duration-200 font-medium flex items-center justify-center sm:justify-start`}
            disabled={loading}
            title="Sync all predefined appointment types as services"
          >
            <ArrowPathIcon className={`h-3 w-3 mr-1 ${loading ? 'animate-spin' : ''}`} />
            Sync Default Services
          </button>
          <button
            onClick={() => {
              setShowArchive(!showArchive);
              if (!showArchive) loadArchivedServices();
            }}
            className={`px-3 py-1.5 border text-xs sm:text-sm ${isDarkMode ? 'border-amber-500/30 text-amber-50 hover:bg-amber-500/10' : 'border-amber-300 text-amber-900 hover:bg-amber-100'} rounded transition-all duration-200 font-medium flex items-center justify-center sm:justify-start`}
          >
            <ArrowPathIcon className="h-3 w-3 mr-1" />
            Archive ({archivedServices.length})
          </button>
          <button
            onClick={() => handleOpenModal()}
            className={`px-4 py-1.5 text-xs sm:text-sm ${isDarkMode ? 'bg-amber-600 text-white hover:bg-amber-700' : 'bg-amber-500 text-white hover:bg-amber-600'} rounded transition-all duration-200 font-medium flex items-center justify-center`}
          >
            <PlusIcon className="h-4 w-4 mr-1" />
            Add Service
          </button>
        </div>
      </div>

      {/* Messages */}
      {error && (
        <div className={`${isDarkMode ? 'bg-red-500/10 border-red-500/30 text-red-400' : 'bg-red-100 border-red-300 text-red-700'} border rounded-lg p-3 transition-colors duration-300`}>
          <div className="flex items-center">
            <ExclamationTriangleIcon className="h-4 w-4 mr-2" />
            <p className="text-sm">{error}</p>
          </div>
        </div>
      )}
      
      {success && (
        <div className={`${isDarkMode ? 'bg-green-500/10 border-green-500/30 text-green-400' : 'bg-green-100 border-green-300 text-green-700'} border rounded-lg p-3 transition-colors duration-300`}>
          <div className="flex items-center">
            <CheckCircleIcon className="h-4 w-4 mr-2" />
            <p className="text-sm">{success}</p>
          </div>
        </div>
      )}

      {/* Archive View */}
      {showArchive && (
        <div className={`${isDarkMode ? 'bg-gray-900 border-amber-500/20' : 'bg-white border-amber-300/40'} border rounded-lg shadow p-4 transition-colors duration-300`}>
          <h3 className={`font-semibold ${isDarkMode ? 'text-amber-50' : 'text-amber-900'} mb-3`}>
            Archived Services
          </h3>
          {archivedServices.length === 0 ? (
            <p className={`text-sm ${isDarkMode ? 'text-gray-400' : 'text-gray-600'}`}>
              No archived services
            </p>
          ) : (
            <div className="space-y-2">
              {archivedServices.map(service => (
                <div 
                  key={service.id} 
                  className={`flex justify-between items-center p-3 ${isDarkMode ? 'bg-gray-800 border-gray-700' : 'bg-gray-50 border-gray-200'} border rounded transition-colors duration-300`}
                >
                  <div>
                    <p className={`font-medium ${isDarkMode ? 'text-amber-50' : 'text-amber-900'}`}>
                      {service.name}
                    </p>
                    {service.description && (
                      <p className={`text-xs ${isDarkMode ? 'text-gray-400' : 'text-gray-600'}`}>
                        {service.description}
                      </p>
                    )}
                  </div>
                  <button
                    onClick={() => handleRestore(service.id)}
                    className={`px-3 py-1.5 text-xs font-medium ${isDarkMode ? 'bg-green-500/20 text-green-400 hover:bg-green-500/30 border-green-500/30' : 'bg-green-100 text-green-700 hover:bg-green-200 border-green-300'} border rounded transition-all duration-200`}
                  >
                    Restore
                  </button>
                </div>
              ))}
            </div>
          )}
        </div>
      )}

      {/* Search Bar */}
      <div className={`${isDarkMode ? 'bg-gray-900 border-amber-500/20' : 'bg-white border-amber-300/40'} border rounded-lg shadow p-3 transition-colors duration-300`}>
        <div className="flex items-center space-x-2">
          <MagnifyingGlassIcon className={`h-4 w-4 ${isDarkMode ? 'text-amber-400' : 'text-amber-600'}`} />
          <input
            type="text"
            placeholder="Search services..."
            value={searchTerm}
            onChange={(e) => setSearchTerm(e.target.value)}
            className={`flex-1 pl-3 pr-3 py-1.5 ${isDarkMode ? 'bg-gray-800 border-gray-600 text-white placeholder-gray-400' : 'bg-gray-50 border-gray-300 text-gray-900 placeholder-gray-500'} border rounded-lg focus:outline-none focus:ring-1 focus:ring-amber-500 transition-all duration-200 text-sm`}
          />
        </div>
      </div>

      {/* Services Grid/List */}
      {loading ? (
        <div className="flex items-center justify-center py-12">
          <div className="w-6 h-6 border-2 border-amber-500 border-t-transparent rounded-full animate-spin"></div>
        </div>
      ) : filteredServices.length === 0 ? (
        <div className={`${isDarkMode ? 'bg-gray-900 border-amber-500/20' : 'bg-white border-amber-300/40'} border rounded-lg shadow text-center py-12 transition-colors duration-300`}>
          <p className={`${isDarkMode ? 'text-gray-400' : 'text-gray-600'}`}>
            No services found
          </p>
        </div>
      ) : (
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
          {filteredServices.map(service => (
            <div
              key={service.id}
              className={`${isDarkMode ? 'bg-gray-900 border-amber-500/20 hover:border-amber-500/40' : 'bg-white border-amber-300/40 hover:border-amber-400'} border rounded-lg shadow p-4 transition-all duration-300`}
            >
              <div className="flex justify-between items-start mb-3 gap-2">
                <div className="flex-1 min-w-0">
                  <h3 className={`font-semibold ${isDarkMode ? 'text-amber-50' : 'text-amber-900'}`}>
                    {service.name}
                  </h3>
                  {service.description && (
                    <p className={`text-xs ${isDarkMode ? 'text-gray-400' : 'text-gray-600'} mt-1`}>
                      {service.description}
                    </p>
                  )}
                </div>
                <div className="flex gap-1 flex-shrink-0">
                  <button
                    onClick={() => handleOpenModal(service)}
                    className={`p-1.5 ${isDarkMode ? 'text-blue-400 hover:bg-blue-500/10 border-blue-500/30' : 'text-blue-600 hover:bg-blue-100 border-blue-300'} border rounded transition-all duration-200`}
                    title="Edit"
                  >
                    <PencilIcon className="h-3 w-3" />
                  </button>
                  <button
                    onClick={() => handleDelete(service.id)}
                    className={`p-1.5 ${isDarkMode ? 'text-red-400 hover:bg-red-500/10 border-red-500/30' : 'text-red-600 hover:bg-red-100 border-red-300'} border rounded transition-all duration-200`}
                    title="Delete"
                  >
                    <TrashIcon className="h-3 w-3" />
                  </button>
                </div>
              </div>
              
              {(service.price || service.duration) && (
                <div className={`pt-2 mt-2 border-t ${isDarkMode ? 'border-gray-700' : 'border-gray-200'} text-xs space-y-1`}>
                  {service.price && (
                    <div className={`flex justify-between ${isDarkMode ? 'text-gray-400' : 'text-gray-600'}`}>
                      <span>Price:</span>
                      <span className={isDarkMode ? 'text-amber-400' : 'text-amber-600'}>
                        ${parseFloat(service.price).toFixed(2)}
                      </span>
                    </div>
                  )}
                  {service.duration && (
                    <div className={`flex justify-between ${isDarkMode ? 'text-gray-400' : 'text-gray-600'}`}>
                      <span>Duration:</span>
                      <span className={isDarkMode ? 'text-amber-400' : 'text-amber-600'}>
                        {service.duration} min
                      </span>
                    </div>
                  )}
                </div>
              )}
            </div>
          ))}
        </div>
      )}

      {/* Modal */}
      {showModal && (
        <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
          <div className={`${isDarkMode ? 'bg-gray-900 border-amber-500/20' : 'bg-white border-amber-300/40'} border rounded-lg shadow-lg p-6 max-w-md w-full mx-4 transition-colors duration-300`}>
            <div className="flex justify-between items-center mb-4">
              <h3 className={`font-bold text-lg ${isDarkMode ? 'text-amber-50' : 'text-amber-900'}`}>
                {editingService ? 'Edit Service' : 'Add New Service'}
              </h3>
              <button
                onClick={handleCloseModal}
                className={`p-1 ${isDarkMode ? 'hover:bg-gray-800' : 'hover:bg-gray-100'} rounded transition-colors duration-200`}
              >
                <XMarkIcon className="h-5 w-5" />
              </button>
            </div>

            <form onSubmit={handleSubmit} className="space-y-4">
              <div>
                <label className={`block text-xs font-medium ${isDarkMode ? 'text-amber-50' : 'text-amber-900'} mb-1`}>
                  Service Name *
                </label>
                <input
                  type="text"
                  name="name"
                  value={formData.name}
                  onChange={handleInputChange}
                  placeholder="e.g., Consultation, Document Review, etc."
                  required
                  className={`w-full px-3 py-2 ${isDarkMode ? 'bg-gray-800 border-gray-600 text-white' : 'bg-white border-gray-300 text-gray-900'} border rounded-lg focus:outline-none focus:ring-1 focus:ring-amber-500 transition-all duration-200 text-sm`}
                />
              </div>

              <div>
                <label className={`block text-xs font-medium ${isDarkMode ? 'text-amber-50' : 'text-amber-900'} mb-1`}>
                  Description
                </label>
                <textarea
                  name="description"
                  value={formData.description}
                  onChange={handleInputChange}
                  placeholder="Brief description of this service..."
                  rows="2"
                  className={`w-full px-3 py-2 ${isDarkMode ? 'bg-gray-800 border-gray-600 text-white' : 'bg-white border-gray-300 text-gray-900'} border rounded-lg focus:outline-none focus:ring-1 focus:ring-amber-500 transition-all duration-200 text-sm resize-none`}
                />
              </div>

              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className={`block text-xs font-medium ${isDarkMode ? 'text-amber-50' : 'text-amber-900'} mb-1`}>
                    Price ($)
                  </label>
                  <input
                    type="number"
                    name="price"
                    value={formData.price}
                    onChange={handleInputChange}
                    placeholder="0.00"
                    min="0"
                    step="0.01"
                    className={`w-full px-3 py-2 ${isDarkMode ? 'bg-gray-800 border-gray-600 text-white' : 'bg-white border-gray-300 text-gray-900'} border rounded-lg focus:outline-none focus:ring-1 focus:ring-amber-500 transition-all duration-200 text-sm`}
                  />
                </div>
                <div>
                  <label className={`block text-xs font-medium ${isDarkMode ? 'text-amber-50' : 'text-amber-900'} mb-1`}>
                    Duration (min)
                  </label>
                  <input
                    type="number"
                    name="duration"
                    value={formData.duration}
                    onChange={handleInputChange}
                    placeholder="30"
                    min="15"
                    step="15"
                    className={`w-full px-3 py-2 ${isDarkMode ? 'bg-gray-800 border-gray-600 text-white' : 'bg-white border-gray-300 text-gray-900'} border rounded-lg focus:outline-none focus:ring-1 focus:ring-amber-500 transition-all duration-200 text-sm`}
                  />
                </div>
              </div>

              <div className="flex space-x-3 pt-4">
                <button
                  type="button"
                  onClick={handleCloseModal}
                  className={`flex-1 px-4 py-2 ${isDarkMode ? 'bg-gray-800 text-gray-300 hover:bg-gray-700' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'} rounded-lg transition-all duration-200 font-medium text-sm`}
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  disabled={loading}
                  className={`flex-1 px-4 py-2 ${isDarkMode ? 'bg-amber-600 text-white hover:bg-amber-700' : 'bg-amber-500 text-white hover:bg-amber-600'} rounded-lg transition-all duration-200 font-medium text-sm disabled:opacity-50`}
                >
                  {loading ? 'Saving...' : 'Save Service'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
};

export default AdminServices;
