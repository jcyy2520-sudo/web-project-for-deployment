import { useState, useEffect } from 'react';
import { CheckCircleIcon, XMarkIcon } from '@heroicons/react/24/outline';

const FeedbackThankYouModal = ({ isOpen, onClose, rating = 0, message = '', category = '' }) => {
  useEffect(() => {
    if (isOpen) {
      const timer = setTimeout(() => {
        onClose();
      }, 5000); // Auto-close after 5 seconds

      return () => clearTimeout(timer);
    }
  }, [isOpen, onClose]);

  if (!isOpen) return null;

  const categoryLabel = category
    ? category.split('_').map(word => word.charAt(0).toUpperCase() + word.slice(1)).join(' ')
    : 'Feedback';

  return (
    <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4 animate-fadeIn">
      <div className="relative bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg shadow-xl max-w-md w-full transform animate-scaleIn">
        {/* Close Icon */}
        <button
          onClick={onClose}
          className="absolute top-3 right-3 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 transition-colors z-10"
        >
          <XMarkIcon className="h-5 w-5" />
        </button>

        <div className="p-6 flex flex-col items-center text-center">
          {/* Success Icon */}
          <div className="mb-4 relative">
            <div className="absolute inset-0 bg-green-500/20 rounded-full blur-xl"></div>
            <CheckCircleIcon className="h-16 w-16 text-green-500 relative" />
          </div>

          {/* Title */}
          <h2 className="text-2xl font-bold text-gray-900 dark:text-white mb-2">
            Thank You!
          </h2>

          {/* Message */}
          <p className="text-gray-600 dark:text-gray-300 mb-4 text-sm">
            Your feedback has been received and a confirmation email has been sent to your inbox.
          </p>

          {/* Feedback Summary Box */}
          <div className="w-full mb-4 p-4 rounded-lg bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700">
            {/* Category */}
            {category && (
              <div className="mb-3">
                <p className="text-xs text-gray-500 dark:text-gray-400 mb-1">Category</p>
                <p className="text-sm font-semibold text-gray-900 dark:text-white">{categoryLabel}</p>
              </div>
            )}

            {/* Rating Display */}
            {rating > 0 && (
              <div className="mb-3">
                <p className="text-xs text-gray-500 dark:text-gray-400 mb-2">Your Rating</p>
                <div className="flex items-center justify-center space-x-1">
                  {[1, 2, 3, 4, 5].map((star) => (
                    <svg
                      key={star}
                      width="18"
                      height="18"
                      viewBox="0 0 24 24"
                      fill={star <= rating ? 'currentColor' : 'none'}
                      stroke="currentColor"
                      strokeWidth="2"
                      className={star <= rating ? 'text-amber-400' : 'text-gray-300 dark:text-gray-600'}
                    >
                      <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2" />
                    </svg>
                  ))}
                </div>
                <p className="text-sm font-semibold text-gray-900 dark:text-white mt-1">{rating}.0 / 5.0</p>
              </div>
            )}

            {/* Message Preview */}
            {message && (
              <div>
                <p className="text-xs text-gray-500 dark:text-gray-400 mb-1">Your Message</p>
                <p className="text-sm text-gray-700 dark:text-gray-300 line-clamp-3 italic">
                  "{message}"
                </p>
              </div>
            )}
          </div>

          {/* Sub-message */}
          <p className="text-sm text-gray-500 dark:text-gray-400">
            A confirmation email has been sent to you.
          </p>

          {/* Close Button */}
          <button
            onClick={onClose}
            className="mt-6 px-6 py-2 bg-green-500 hover:bg-green-600 text-white rounded-lg font-medium transition-colors duration-200"
          >
            Got it
          </button>
        </div>
      </div>
    </div>
  );
};

export default FeedbackThankYouModal;
