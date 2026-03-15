import React, { useState, useEffect } from 'react';
import { useAuth } from '../context/AuthContext';
import axios from 'axios';
import { ExclamationTriangleIcon, CheckCircleIcon } from '@heroicons/react/24/outline';

const ProfileCompletionBanner = ({ isDarkMode }) => {
  const { user, updateUser } = useAuth();
  
  const [formData, setFormData] = useState({
    first_name: '',
    last_name: '',
    phone: '',
    address: ''
  });
  
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');
  const [success, setSuccess] = useState('');
  const [isVisible, setIsVisible] = useState(true);

  // Pre-fill with any existing data
  useEffect(() => {
    if (user) {
      setFormData({
        first_name: user.first_name || '',
        last_name: user.last_name || '',
        phone: user.phone || '',
        address: user.address || ''
      });
    }
  }, [user]);

  // If user profile is already completed, don't show the banner
  if (!user || user.profile_completed || !isVisible) {
    return null;
  }

  const handleChange = (e) => {
    const { name, value } = e.target;
    setFormData((prev) => ({
      ...prev,
      [name]: value
    }));
    setError('');
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    setLoading(true);
    setError('');
    
    try {
      const response = await axios.post('/api/auth/complete-profile', formData);
      
      if (response.data && response.data.success) {
        setSuccess('Profile completed successfully!');
        
        // Update user context with new data and profile_completed = true
        if (typeof updateUser === 'function') {
           updateUser({ 
             ...formData, 
             profile_completed: true 
           });
        }
        
        // Hide banner after a short delay
        setTimeout(() => {
          setIsVisible(false);
          // Optional: trigger a page reload to ensure all states update
          window.location.reload();
        }, 1500);
      }
    } catch (err) {
      console.error('Failed to complete profile:', err);
      setError(err.response?.data?.message || err.response?.data?.error || 'Failed to update profile. Please try again.');
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className={`border rounded-lg shadow-lg mb-6 overflow-hidden transition-all duration-300 ${isDarkMode ? 'bg-gray-800 border-amber-500/40' : 'bg-white border-amber-400'}`}>
      <div className={`px-4 py-3 border-b flex items-center gap-3 ${isDarkMode ? 'bg-amber-900/30 border-amber-500/30' : 'bg-amber-50 border-amber-200'}`}>
        <ExclamationTriangleIcon className={`h-6 w-6 ${isDarkMode ? 'text-amber-400' : 'text-amber-600'}`} />
        <div>
          <h3 className={`font-bold ${isDarkMode ? 'text-amber-50' : 'text-amber-900'}`}>Complete Your Profile</h3>
          <p className={`text-xs sm:text-sm ${isDarkMode ? 'text-amber-200/80' : 'text-amber-700/80'}`}>Please provide your necessary details to enable appointment booking.</p>
        </div>
      </div>
      
      <div className="p-4 sm:p-5">
        {success ? (
           <div className="flex items-center gap-2 text-green-500 bg-green-50 dark:bg-green-900/20 p-3 rounded-lg border border-green-200 dark:border-green-800">
             <CheckCircleIcon className="h-5 w-5" />
             <span className="font-medium text-sm">{success}</span>
           </div>
        ) : (
          <form onSubmit={handleSubmit} className="space-y-4">
            {error && (
              <div className="text-red-500 bg-red-50 dark:bg-red-900/20 p-2 rounded text-sm mb-4 border border-red-200 dark:border-red-800">
                {error}
              </div>
            )}
            
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <label className={`block text-xs font-medium mb-1 ${isDarkMode ? 'text-amber-50' : 'text-gray-700'}`}>First Name *</label>
                <input
                  type="text"
                  name="first_name"
                  value={formData.first_name}
                  onChange={handleChange}
                  required
                  className={`w-full px-3 py-2 text-sm border rounded focus:outline-none focus:ring-2 focus:ring-amber-500 ${isDarkMode ? 'bg-gray-700 border-gray-600 text-white' : 'bg-white border-gray-300 text-gray-900'}`}
                />
              </div>
              <div>
                <label className={`block text-xs font-medium mb-1 ${isDarkMode ? 'text-amber-50' : 'text-gray-700'}`}>Last Name *</label>
                <input
                  type="text"
                  name="last_name"
                  value={formData.last_name}
                  onChange={handleChange}
                  required
                  className={`w-full px-3 py-2 text-sm border rounded focus:outline-none focus:ring-2 focus:ring-amber-500 ${isDarkMode ? 'bg-gray-700 border-gray-600 text-white' : 'bg-white border-gray-300 text-gray-900'}`}
                />
              </div>
              <div>
                <label className={`block text-xs font-medium mb-1 ${isDarkMode ? 'text-amber-50' : 'text-gray-700'}`}>Phone Number *</label>
                <input
                  type="tel"
                  name="phone"
                  value={formData.phone}
                  onChange={handleChange}
                  required
                  className={`w-full px-3 py-2 text-sm border rounded focus:outline-none focus:ring-2 focus:ring-amber-500 ${isDarkMode ? 'bg-gray-700 border-gray-600 text-white' : 'bg-white border-gray-300 text-gray-900'}`}
                />
              </div>
              <div className="sm:col-span-2">
                <label className={`block text-xs font-medium mb-1 ${isDarkMode ? 'text-amber-50' : 'text-gray-700'}`}>Address *</label>
                <input
                  type="text"
                  name="address"
                  value={formData.address}
                  onChange={handleChange}
                  required
                  className={`w-full px-3 py-2 text-sm border rounded focus:outline-none focus:ring-2 focus:ring-amber-500 ${isDarkMode ? 'bg-gray-700 border-gray-600 text-white' : 'bg-white border-gray-300 text-gray-900'}`}
                />
              </div>
            </div>
            
            <div className="flex justify-end pt-2">
              <button
                type="submit"
                disabled={loading}
                className="px-4 py-2 bg-gradient-to-r from-amber-500 to-amber-600 text-white text-sm font-medium rounded-lg hover:from-amber-600 hover:to-amber-700 focus:outline-none focus:ring-2 focus:ring-amber-500 focus:ring-offset-2 transition-all shadow-sm disabled:opacity-70 flex items-center"
              >
                {loading ? (
                  <>
                    <div className="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin mr-2"></div>
                    Saving...
                  </>
                ) : (
                  'Complete Profile'
                )}
              </button>
            </div>
          </form>
        )}
      </div>
    </div>
  );
};

export default ProfileCompletionBanner;
