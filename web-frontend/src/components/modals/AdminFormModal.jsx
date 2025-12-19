import { useEffect, useState } from 'react';
import { XMarkIcon, ShieldCheckIcon, ExclamationTriangleIcon } from '@heroicons/react/24/outline';

const AdminFormModal = ({ isOpen, onClose, admin, onSave, loading, isDarkMode = true }) => {
  const [formData, setFormData] = useState({
    first_name: '',
    last_name: '',
    email: '',
    phone: '',
    address: '',
    password: '',
    role: 'admin'
  });

  const [errors, setErrors] = useState({});

  useEffect(() => {
    if (admin && isOpen) {
      setFormData({
        first_name: admin.first_name || '',
        last_name: admin.last_name || '',
        email: admin.email || '',
        phone: admin.phone || '',
        address: admin.address || '',
        password: '',
        role: 'admin'
      });
    } else if (isOpen) {
      setFormData({
        first_name: '',
        last_name: '',
        email: '',
        phone: '',
        address: '',
        password: '',
        role: 'admin'
      });
    }
    setErrors({});
  }, [admin, isOpen]);

  const validateForm = () => {
    const newErrors = {};
    
    if (!formData.first_name.trim()) newErrors.first_name = 'First name is required';
    if (!formData.last_name.trim()) newErrors.last_name = 'Last name is required';
    if (!formData.email.trim()) newErrors.email = 'Email is required';
    else if (!/\S+@\S+\.\S+/.test(formData.email)) newErrors.email = 'Email is invalid';
    
    if (!admin && !formData.password) newErrors.password = 'Password is required';
    else if (!admin && formData.password.length < 6) newErrors.password = 'Password must be at least 6 characters';

    setErrors(newErrors);
    return Object.keys(newErrors).length === 0;
  };

  const handleSubmit = (e) => {
    e.preventDefault();
    if (validateForm()) {
      onSave(formData);
    }
  };

  if (!isOpen) return null;

  return (
    <div className="fixed inset-0 bg-black bg-opacity-70 flex items-center justify-center z-50 p-4 animate-fadeIn">
      <div className={`${isDarkMode ? 'bg-gray-900 border-amber-500/30' : 'bg-white border-amber-300/40'} border rounded-lg shadow-xl w-full max-w-2xl max-h-[90vh] overflow-y-auto transform animate-scaleIn`}>
        <div className={`flex justify-between items-center p-4 border-b ${isDarkMode ? 'border-gray-700 bg-gray-900' : 'border-gray-200 bg-white'} sticky top-0`}>
          <div className="flex items-center">
            <ShieldCheckIcon className="h-5 w-5 text-amber-400 mr-2" />
            <h3 className={`text-sm font-semibold ${isDarkMode ? 'text-amber-50' : 'text-amber-900'}`}>
              {admin ? 'Edit Admin' : 'Add New Admin'}
            </h3>
          </div>
          <button 
            onClick={onClose} 
            className={`${isDarkMode ? 'text-gray-400 hover:text-amber-400' : 'text-gray-500 hover:text-amber-500'} transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-amber-500 rounded p-1`}
            disabled={loading}
          >
            <XMarkIcon className="h-4 w-4" />
          </button>
        </div>
        
        <form onSubmit={handleSubmit} className="p-4 space-y-4">
          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label className={`block text-xs font-medium ${isDarkMode ? 'text-amber-50' : 'text-amber-900'} mb-1`}>
                First Name *
              </label>
              <input
                type="text"
                value={formData.first_name}
                onChange={(e) => setFormData(prev => ({ ...prev, first_name: e.target.value }))}
                className={`w-full px-3 py-2 ${isDarkMode ? 'bg-gray-800 text-white placeholder-gray-400' : 'bg-gray-50 text-gray-900 placeholder-gray-500'} border rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-500 transition-all duration-200 text-sm ${
                  errors.first_name ? 'border-red-500' : `${isDarkMode ? 'border-gray-600' : 'border-gray-300'} focus:border-amber-500`
                }`}
                disabled={loading}
                placeholder="Enter first name"
              />
              {errors.first_name && (
                <p className="text-red-400 text-xs mt-1 flex items-center">
                  <ExclamationTriangleIcon className="h-3 w-3 mr-1" />
                  {errors.first_name}
                </p>
              )}
            </div>
            <div>
              <label className={`block text-xs font-medium ${isDarkMode ? 'text-amber-50' : 'text-amber-900'} mb-1`}>
                Last Name *
              </label>
              <input
                type="text"
                value={formData.last_name}
                onChange={(e) => setFormData(prev => ({ ...prev, last_name: e.target.value }))}
                className={`w-full px-3 py-2 ${isDarkMode ? 'bg-gray-800 text-white placeholder-gray-400' : 'bg-gray-50 text-gray-900 placeholder-gray-500'} border rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-500 transition-all duration-200 text-sm ${
                  errors.last_name ? 'border-red-500' : `${isDarkMode ? 'border-gray-600' : 'border-gray-300'} focus:border-amber-500`
                }`}
                disabled={loading}
                placeholder="Enter last name"
              />
              {errors.last_name && (
                <p className="text-red-400 text-xs mt-1 flex items-center">
                  <ExclamationTriangleIcon className="h-3 w-3 mr-1" />
                  {errors.last_name}
                </p>
              )}
            </div>
          </div>

          <div>
            <label className={`block text-xs font-medium ${isDarkMode ? 'text-amber-50' : 'text-amber-900'} mb-1`}>
              Email Address *
            </label>
            <input
              type="email"
              value={formData.email}
              onChange={(e) => setFormData(prev => ({ ...prev, email: e.target.value }))}
              className={`w-full px-3 py-2 ${isDarkMode ? 'bg-gray-800 text-white placeholder-gray-400' : 'bg-gray-50 text-gray-900 placeholder-gray-500'} border rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-500 transition-all duration-200 text-sm ${
                errors.email ? 'border-red-500' : `${isDarkMode ? 'border-gray-600' : 'border-gray-300'} focus:border-amber-500`
              }`}
              disabled={loading}
              placeholder="Enter email address"
            />
            {errors.email && (
              <p className="text-red-400 text-xs mt-1 flex items-center">
                <ExclamationTriangleIcon className="h-3 w-3 mr-1" />
                {errors.email}
              </p>
            )}
          </div>

          <div>
            <label className={`block text-xs font-medium ${isDarkMode ? 'text-amber-50' : 'text-amber-900'} mb-1`}>
              Phone Number
            </label>
            <input
              type="tel"
              value={formData.phone}
              onChange={(e) => setFormData(prev => ({ ...prev, phone: e.target.value }))}
              className={`w-full px-3 py-2 ${isDarkMode ? 'bg-gray-800 border-gray-600 text-white placeholder-gray-400' : 'bg-gray-50 border-gray-300 text-gray-900 placeholder-gray-500'} border rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-500 focus:border-amber-500 transition-all duration-200 text-sm`}
              disabled={loading}
              placeholder="Enter phone number"
            />
          </div>

          <div>
            <label className={`block text-xs font-medium ${isDarkMode ? 'text-amber-50' : 'text-amber-900'} mb-1`}>
              Address
            </label>
            <textarea
              value={formData.address}
              onChange={(e) => setFormData(prev => ({ ...prev, address: e.target.value }))}
              rows="2"
              className={`w-full px-3 py-2 ${isDarkMode ? 'bg-gray-800 border-gray-600 text-white placeholder-gray-400' : 'bg-gray-50 border-gray-300 text-gray-900 placeholder-gray-500'} border rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-500 focus:border-amber-500 transition-all duration-200 text-sm resize-none`}
              disabled={loading}
              placeholder="Enter address (optional)"
            />
          </div>

          {!admin && (
            <div>
              <label className={`block text-xs font-medium ${isDarkMode ? 'text-amber-50' : 'text-amber-900'} mb-1`}>
                Password *
              </label>
              <input
                type="password"
                value={formData.password}
                onChange={(e) => setFormData(prev => ({ ...prev, password: e.target.value }))}
                className={`w-full px-3 py-2 ${isDarkMode ? 'bg-gray-800 text-white placeholder-gray-400' : 'bg-gray-50 text-gray-900 placeholder-gray-500'} border rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-500 transition-all duration-200 text-sm ${
                  errors.password ? 'border-red-500' : `${isDarkMode ? 'border-gray-600' : 'border-gray-300'} focus:border-amber-500`
                }`}
                disabled={loading}
                placeholder="Enter password"
              />
              {errors.password && (
                <p className="text-red-400 text-xs mt-1 flex items-center">
                  <ExclamationTriangleIcon className="h-3 w-3 mr-1" />
                  {errors.password}
                </p>
              )}
            </div>
          )}

          <div className={`flex justify-end space-x-3 pt-4 border-t ${isDarkMode ? 'border-gray-700' : 'border-gray-200'}`}>
            <button
              type="button"
              onClick={onClose}
              className={`px-4 py-2 border ${isDarkMode ? 'border-gray-600 text-gray-300 hover:bg-gray-800 focus:ring-offset-gray-900' : 'border-gray-300 text-gray-600 hover:bg-gray-100 focus:ring-offset-white'} rounded-lg transition-all duration-200 font-medium text-sm focus:outline-none focus:ring-2 focus:ring-amber-500 focus:ring-offset-2 hover:scale-105 disabled:opacity-50`}
              disabled={loading}
            >
              Cancel
            </button>
            <button
              type="submit"
              className="px-4 py-2 bg-gradient-to-r from-amber-600 to-amber-700 text-white rounded-lg hover:from-amber-700 hover:to-amber-800 transition-all duration-200 font-medium text-sm focus:outline-none focus:ring-2 focus:ring-amber-500 focus:ring-offset-2 focus:ring-offset-gray-900 shadow hover:scale-105 disabled:opacity-50 disabled:cursor-not-allowed flex items-center"
              disabled={loading}
            >
              {loading ? (
                <>
                  <div className="w-3 h-3 border-2 border-white border-t-transparent rounded-full animate-spin mr-1"></div>
                  {admin ? 'Updating...' : 'Creating...'}
                </>
              ) : (
                <>
                  {admin ? 'Update Admin' : 'Create Admin'}
                </>
              )}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
};

export default AdminFormModal;
