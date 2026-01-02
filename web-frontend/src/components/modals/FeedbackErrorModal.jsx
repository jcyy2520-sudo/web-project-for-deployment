import React from 'react';
import { XMarkIcon } from '@heroicons/react/24/outline';

const FeedbackErrorModal = ({ isOpen, onClose, title = 'Action Required', message = '', primaryAction }) => {
  if (!isOpen) return null;

  return (
    <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
      <div className="bg-white dark:bg-gray-900 rounded-lg shadow-xl max-w-md w-full relative">
        <button onClick={onClose} className="absolute right-3 top-3 text-gray-400 hover:text-gray-600">
          <XMarkIcon className="h-5 w-5" />
        </button>
        <div className="p-6 text-center">
          <h3 className="text-lg font-bold mb-2 text-gray-900 dark:text-white">{title}</h3>
          <p className="text-sm text-gray-600 dark:text-gray-300">{message}</p>

          {primaryAction && (
            <div className="mt-6">
              <button onClick={primaryAction.onClick} className="px-5 py-2 bg-amber-500 text-white rounded-lg">
                {primaryAction.label}
              </button>
            </div>
          )}

          <div className="mt-4">
            <button onClick={onClose} className="text-sm text-gray-500 hover:underline">Close</button>
          </div>
        </div>
      </div>
    </div>
  );
};

export default FeedbackErrorModal;
