import { CheckCircleIcon, XCircleIcon, ExclamationTriangleIcon } from '@heroicons/react/24/outline';

const ConfirmationModal = ({ isOpen, onClose, onConfirm, title, message, confirmText = "Delete", type = "danger", loading = false }) => {
  if (!isOpen) return null;

  const buttonColors = {
    danger: "bg-red-600 hover:bg-red-700 focus:ring-red-500",
    primary: "bg-amber-600 hover:bg-amber-700 focus:ring-amber-500",
    warning: "bg-yellow-600 hover:bg-yellow-700 focus:ring-yellow-500",
    success: "bg-green-600 hover:bg-green-700 focus:ring-green-500"
  };

  const icons = {
    danger: ExclamationTriangleIcon,
    warning: ExclamationTriangleIcon,
    primary: CheckCircleIcon,
    success: CheckCircleIcon
  };

  const IconComponent = icons[type];

  return (
    <div className="fixed inset-0 bg-black bg-opacity-70 flex items-center justify-center z-50 p-4 animate-fadeIn">
      <div className="bg-gray-900 border border-amber-500/30 rounded-lg shadow-xl w-full max-w-md transform animate-scaleIn">
        <div className="p-4">
          <div className="flex items-center mb-3">
            <div className={`p-2 rounded-lg ${
              type === 'danger' ? 'bg-red-500/20' : 
              type === 'warning' ? 'bg-yellow-500/20' : 
              type === 'success' ? 'bg-green-500/20' : 
              'bg-amber-500/20'
            }`}>
              <IconComponent className={`h-5 w-5 ${
                type === 'danger' ? 'text-red-400' : 
                type === 'warning' ? 'text-yellow-400' : 
                type === 'success' ? 'text-green-400' : 
                'text-amber-400'
              }`} />
            </div>
            <h3 className="text-sm font-semibold text-amber-50 ml-2">{title}</h3>
          </div>
          <p className="text-gray-300 text-sm mb-4">{message}</p>
          <div className="flex justify-end space-x-2">
            <button
              onClick={onClose}
              disabled={loading}
              className="px-3 py-2 border border-gray-600 text-gray-300 rounded-lg hover:bg-gray-800 transition-colors duration-200 font-medium text-sm focus:outline-none focus:ring-2 focus:ring-amber-500 focus:ring-offset-2 focus:ring-offset-gray-900 disabled:opacity-50"
            >
              Cancel
            </button>
            <button
              onClick={onConfirm}
              disabled={loading}
              className={`px-3 py-2 text-white rounded-lg transition-colors duration-200 font-medium text-sm focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-offset-gray-900 disabled:opacity-50 ${buttonColors[type]}`}
            >
              {loading ? (
                <div className="flex items-center">
                  <div className="w-3 h-3 border-2 border-white border-t-transparent rounded-full animate-spin mr-1"></div>
                  Processing...
                </div>
              ) : (
                confirmText
              )}
            </button>
          </div>
        </div>
      </div>
    </div>
  );
};

export default ConfirmationModal;
