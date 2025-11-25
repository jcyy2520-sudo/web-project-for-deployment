import { XMarkIcon, UserGroupIcon, EnvelopeIcon, PhoneIcon, MapPinIcon } from '@heroicons/react/24/outline';
import StatusBadge from '../ui/StatusBadge';

const UserDetailModal = ({ isOpen, onClose, user, onDeactivate, loading }) => {
  if (!isOpen || !user) return null;

  return (
    <div className="fixed inset-0 bg-black bg-opacity-70 flex items-center justify-center z-50 p-4 animate-fadeIn">
      <div className="bg-gray-900 border border-amber-500/30 rounded-lg shadow-xl w-full max-w-2xl max-h-[90vh] overflow-y-auto transform animate-scaleIn">
        <div className="flex justify-between items-center p-4 border-b border-gray-700 sticky top-0 bg-gray-900">
          <div className="flex items-center">
            <UserGroupIcon className="h-5 w-5 text-amber-400 mr-2" />
            <h3 className="text-sm font-semibold text-amber-50">
              User Details
            </h3>
          </div>
          <button 
            onClick={onClose} 
            className="text-gray-400 hover:text-amber-400 transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-amber-500 rounded p-1"
          >
            <XMarkIcon className="h-4 w-4" />
          </button>
        </div>
        
        <div className="p-4 space-y-4">
          <div className="flex items-center space-x-3 p-3 bg-gray-800/50 rounded-lg border border-gray-600">
            <div className="w-12 h-12 bg-amber-500 rounded-full flex items-center justify-center text-gray-900 text-sm font-bold shadow">
              {user.first_name?.charAt(0)}{user.last_name?.charAt(0)}
            </div>
            <div>
              <h4 className="text-sm font-bold text-amber-50">
                {user.first_name} {user.last_name}
              </h4>
              <p className="text-amber-400/70 text-xs capitalize">{user.role}</p>
              <StatusBadge status={user.is_active ? 'active' : 'inactive'} />
            </div>
          </div>

          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div className="space-y-3">
              <div className="p-3 bg-gray-800/30 rounded-lg border border-gray-600">
                <label className="text-xs font-medium text-gray-400 mb-1 block">Contact Information</label>
                <div className="space-y-2">
                  <div className="flex items-center text-amber-50 text-sm">
                    <EnvelopeIcon className="h-3 w-3 mr-2 text-amber-400" />
                    <span>{user.email}</span>
                  </div>
                  {user.phone && (
                    <div className="flex items-center text-amber-50 text-sm">
                      <PhoneIcon className="h-3 w-3 mr-2 text-amber-400" />
                      <span>{user.phone}</span>
                    </div>
                  )}
                </div>
              </div>

              <div className="p-3 bg-gray-800/30 rounded-lg border border-gray-600">
                <label className="text-xs font-medium text-gray-400 mb-1 block">Account Status</label>
                <div className="space-y-1">
                  <div className="flex justify-between">
                    <span className="text-gray-300 text-sm">Role</span>
                    <span className={`px-2 py-0.5 rounded-full text-xs font-medium ${
                      user.role === 'admin' ? 'bg-purple-500/20 text-purple-300 border border-purple-500/30' :
                      user.role === 'staff' ? 'bg-blue-500/20 text-blue-300 border border-blue-500/30' :
                      'bg-green-500/20 text-green-300 border border-green-500/30'
                    }`}>
                      {user.role}
                    </span>
                  </div>
                  <div className="flex justify-between">
                    <span className="text-gray-300 text-sm">Status</span>
                    <StatusBadge status={user.is_active ? 'active' : 'inactive'} />
                  </div>
                  <div className="flex justify-between">
                    <span className="text-gray-300 text-sm">Member Since</span>
                    <span className="text-amber-50 text-sm">
                      {user.created_at ? new Date(user.created_at).toLocaleDateString() : 'N/A'}
                    </span>
                  </div>
                </div>
              </div>
            </div>

            <div className="space-y-3">
              {user.address && (
                <div className="p-3 bg-gray-800/30 rounded-lg border border-gray-600">
                  <label className="text-xs font-medium text-gray-400 mb-1 block">Address</label>
                  <div className="flex items-start text-amber-50">
                    <MapPinIcon className="h-3 w-3 mr-2 text-amber-400 mt-0.5 flex-shrink-0" />
                    <span className="text-xs">{user.address}</span>
                  </div>
                </div>
              )}

              <div className="p-3 bg-gray-800/30 rounded-lg border border-gray-600">
                <label className="text-xs font-medium text-gray-400 mb-1 block">Quick Actions</label>
                <div className="space-y-1">
                  <button className="w-full text-left p-1.5 rounded hover:bg-amber-500/10 transition-colors duration-200 text-amber-50 text-sm">
                    Send Email
                  </button>
                  <button className="w-full text-left p-1.5 rounded hover:bg-amber-500/10 transition-colors duration-200 text-amber-50 text-sm">
                    View Appointments
                  </button>
                  <button 
                    onClick={() => onDeactivate(user)}
                    className="w-full text-left p-1.5 rounded hover:bg-red-500/10 transition-colors duration-200 text-red-400 text-sm"
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
