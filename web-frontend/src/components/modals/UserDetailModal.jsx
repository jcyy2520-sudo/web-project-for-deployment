import { XMarkIcon, UserGroupIcon, EnvelopeIcon, PhoneIcon, MapPinIcon } from '@heroicons/react/24/outline';
import StatusBadge from '../ui/StatusBadge';

const UserDetailModal = ({ isOpen, onClose, user, onDeactivate, loading, isDarkMode = true }) => {
  if (!isOpen || !user) return null;

  return (
    <div className="fixed inset-0 bg-black bg-opacity-70 flex items-center justify-center z-50 p-4 animate-fadeIn">
      <div className={`${isDarkMode ? 'bg-gray-900 border-amber-500/30' : 'bg-white border-amber-300/40'} border rounded-lg shadow-xl w-full max-w-2xl max-h-[90vh] overflow-y-auto transform animate-scaleIn`}>
        <div className={`flex justify-between items-center p-4 border-b ${isDarkMode ? 'border-gray-700 bg-gray-900' : 'border-gray-200 bg-white'} sticky top-0`}>
          <div className="flex items-center">
            <UserGroupIcon className="h-5 w-5 text-amber-400 mr-2" />
            <h3 className={`text-sm font-semibold ${isDarkMode ? 'text-amber-50' : 'text-amber-900'}`}>
              User Details
            </h3>
          </div>
          <button 
            onClick={onClose} 
            className={`${isDarkMode ? 'text-gray-400 hover:text-amber-400' : 'text-gray-500 hover:text-amber-500'} transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-amber-500 rounded p-1`}
          >
            <XMarkIcon className="h-4 w-4" />
          </button>
        </div>
        
        <div className="p-4 space-y-4">
          <div className={`flex items-center space-x-3 p-3 ${isDarkMode ? 'bg-gray-800/50 border-gray-600' : 'bg-gray-50 border-gray-200'} rounded-lg border`}>
            <div className={`w-12 h-12 bg-amber-500 rounded-full flex items-center justify-center ${isDarkMode ? 'text-gray-900' : 'text-white'} text-sm font-bold shadow`}>
              {user.first_name?.charAt(0)}{user.last_name?.charAt(0)}
            </div>
            <div>
              <h4 className={`text-sm font-bold ${isDarkMode ? 'text-amber-50' : 'text-amber-900'}`}>
                {user.first_name} {user.last_name}
              </h4>
              <p className="text-amber-400/70 text-xs capitalize">{user.role}</p>
              <StatusBadge status={user.is_active ? 'active' : 'inactive'} />
            </div>
          </div>

          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div className="space-y-3">
              <div className={`p-3 ${isDarkMode ? 'bg-gray-800/30 border-gray-600' : 'bg-gray-50 border-gray-200'} rounded-lg border`}>
                <label className={`text-xs font-medium ${isDarkMode ? 'text-gray-400' : 'text-gray-500'} mb-1 block`}>Contact Information</label>
                <div className="space-y-2">
                  <div className={`flex items-center ${isDarkMode ? 'text-amber-50' : 'text-amber-900'} text-sm`}>
                    <EnvelopeIcon className="h-3 w-3 mr-2 text-amber-400" />
                    <span>{user.email}</span>
                  </div>
                  {user.phone && (
                    <div className={`flex items-center ${isDarkMode ? 'text-amber-50' : 'text-amber-900'} text-sm`}>
                      <PhoneIcon className="h-3 w-3 mr-2 text-amber-400" />
                      <span>{user.phone}</span>
                    </div>
                  )}
                </div>
              </div>

              <div className={`p-3 ${isDarkMode ? 'bg-gray-800/30 border-gray-600' : 'bg-gray-50 border-gray-200'} rounded-lg border`}>
                <label className={`text-xs font-medium ${isDarkMode ? 'text-gray-400' : 'text-gray-500'} mb-1 block`}>Account Status</label>
                <div className="space-y-1">
                  <div className="flex justify-between">
                    <span className={`${isDarkMode ? 'text-gray-300' : 'text-gray-600'} text-sm`}>Role</span>
                    <span className={`px-2 py-0.5 rounded-full text-xs font-medium ${
                      user.role === 'admin' ? 'bg-purple-500/20 text-purple-300 border border-purple-500/30' :
                      'bg-green-500/20 text-green-300 border border-green-500/30'
                    }`}>
                      {user.role}
                    </span>
                  </div>
                  <div className="flex justify-between">
                    <span className={`${isDarkMode ? 'text-gray-300' : 'text-gray-600'} text-sm`}>Status</span>
                    <StatusBadge status={user.is_active ? 'active' : 'inactive'} />
                  </div>
                  <div className="flex justify-between">
                    <span className={`${isDarkMode ? 'text-gray-300' : 'text-gray-600'} text-sm`}>Member Since</span>
                    <span className={`${isDarkMode ? 'text-amber-50' : 'text-amber-900'} text-sm`}>
                      {user.created_at ? new Date(user.created_at).toLocaleDateString() : 'N/A'}
                    </span>
                  </div>
                </div>
              </div>
            </div>

            <div className="space-y-3">
              {user.address && (
                <div className={`p-3 ${isDarkMode ? 'bg-gray-800/30 border-gray-600' : 'bg-gray-50 border-gray-200'} rounded-lg border`}>
                  <label className={`text-xs font-medium ${isDarkMode ? 'text-gray-400' : 'text-gray-500'} mb-1 block`}>Address</label>
                  <div className={`flex items-start ${isDarkMode ? 'text-amber-50' : 'text-amber-900'}`}>
                    <MapPinIcon className="h-3 w-3 mr-2 text-amber-400 mt-0.5 flex-shrink-0" />
                    <span className="text-xs">{user.address}</span>
                  </div>
                </div>
              )}

              <div className={`p-3 ${isDarkMode ? 'bg-gray-800/30 border-gray-600' : 'bg-gray-50 border-gray-200'} rounded-lg border`}>
                <label className={`text-xs font-medium ${isDarkMode ? 'text-gray-400' : 'text-gray-500'} mb-1 block`}>Quick Actions</label>
                <div className="space-y-1">
                  <button className={`w-full text-left p-1.5 rounded ${isDarkMode ? 'hover:bg-amber-500/10 text-amber-50' : 'hover:bg-amber-50 text-amber-900'} transition-colors duration-200 text-sm`}>
                    Send Email
                  </button>
                  <button className={`w-full text-left p-1.5 rounded ${isDarkMode ? 'hover:bg-amber-500/10 text-amber-50' : 'hover:bg-amber-50 text-amber-900'} transition-colors duration-200 text-sm`}>
                    View Appointments
                  </button>
                  <button 
                    onClick={() => onDeactivate(user)}
                    className={`w-full text-left p-1.5 rounded hover:bg-red-500/10 transition-colors duration-200 text-red-400 text-sm`}
                  >
                    {user.is_active ? 'Deactivate User' : 'Activate User'}
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
};

export default UserDetailModal;
