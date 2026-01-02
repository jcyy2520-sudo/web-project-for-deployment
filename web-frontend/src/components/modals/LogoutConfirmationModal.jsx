import { XMarkIcon, ExclamationTriangleIcon } from '@heroicons/react/24/outline';

const LogoutConfirmationModal = ({ isOpen, onClose, onConfirm, loading, isDarkMode = true }) => {
  if (!isOpen) return null;

  return (
    <div className="fixed inset-0 bg-black bg-opacity-70 flex items-center justify-center z-50 p-4 animate-fadeIn">
      <div className={`${isDarkMode ? 'bg-gray-900 border-amber-500/30' : 'bg-white border-amber-300/40'} border rounded-lg shadow-xl w-full max-w-md transform animate-scaleIn`}>
        <div className="p-4">
          <div className="flex items-center mb-3">
            <div className={`p-2 rounded-lg ${isDarkMode ? 'bg-amber-500/20' : 'bg-amber-100'}`}>
              <ExclamationTriangleIcon className="h-5 w-5 text-amber-400" />
            </div>
            <h3 className={`text-sm font-semibold ${isDarkMode ? 'text-amber-50' : 'text-amber-900'} ml-2`}>Confirm Logout</h3>
          </div>
          <p className={`${isDarkMode ? 'text-gray-300' : 'text-gray-600'} text-sm mb-4`}>Are you sure you want to logout from the admin dashboard?</p>
          <div className="flex justify-end space-x-2">
            <button
              onClick={onClose}
              disabled={loading}
              className={`px-3 py-2 border ${isDarkMode ? 'border-gray-600 text-gray-300 hover:bg-gray-800 focus:ring-offset-gray-900' : 'border-gray-300 text-gray-600 hover:bg-gray-100 focus:ring-offset-white'} rounded-lg transition-colors duration-200 font-medium text-sm focus:outline-none focus:ring-2 focus:ring-amber-500 focus:ring-offset-2 disabled:opacity-50`}
            >
              Cancel
            </button>
            <button
              onClick={onConfirm}
              disabled={loading}
              className={`px-3 py-2 rounded-lg transition-colors duration-200 font-medium text-sm focus:outline-none focus:ring-2 focus:ring-offset-2 disabled:opacity-50 ${
                isDarkMode
                  ? 'bg-amber-600 hover:bg-amber-700 text-white focus:ring-amber-500 focus:ring-offset-gray-900'
                  : 'bg-red-600 hover:bg-red-700 text-white focus:ring-red-500 focus:ring-offset-white'
              }`
            >
              {loading ? (
                <div className="flex items-center">
                  <div className="w-3 h-3 border-2 border-white border-t-transparent rounded-full animate-spin mr-1"></div>
                  Logging out...
                </div>
              ) : (
                'Logout'
              )}
            </button>
          </div>
        </div>
      </div>
    </div>
  );
};

export default LogoutConfirmationModal;
