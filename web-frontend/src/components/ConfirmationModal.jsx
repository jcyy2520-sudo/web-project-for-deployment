import { Fragment } from 'react';
import { Dialog, Transition } from '@headlessui/react';
import { ExclamationTriangleIcon } from '@heroicons/react/24/outline';

const ConfirmationModal = ({ 
  isOpen, 
  onClose, 
  onConfirm, 
  title = "Confirm Action", 
  message = "Are you sure you want to proceed?",
  confirmText = "Confirm",
  cancelText = "Cancel",
  type = "warning", // 'warning', 'danger', 'info'
  isLoading = false
}) => {
  const typeConfig = {
    warning: {
      icon: "text-amber-400",
      iconBg: "bg-amber-500/20",
      button: "bg-amber-600 hover:bg-amber-700 focus:ring-amber-500 border border-amber-500/30",
    },
    danger: {
      icon: "text-red-400",
      iconBg: "bg-red-500/20", 
      button: "bg-red-600 hover:bg-red-700 focus:ring-red-500 border border-red-500/30",
    },
    info: {
      icon: "text-blue-400",
      iconBg: "bg-blue-500/20",
      button: "bg-blue-600 hover:bg-blue-700 focus:ring-blue-500 border border-blue-500/30",
    }
  };

  const config = typeConfig[type];

  return (
    <Transition appear show={isOpen} as={Fragment}>
      <Dialog as="div" className="relative z-50" onClose={onClose}>
        <Transition.Child
          as={Fragment}
          enter="ease-out duration-300"
          enterFrom="opacity-0"
          enterTo="opacity-100"
          leave="ease-in duration-200"
          leaveFrom="opacity-100"
          leaveTo="opacity-0"
        >
          <div className="fixed inset-0 bg-black/70 backdrop-blur-sm" />
        </Transition.Child>

        <div className="fixed inset-0 overflow-y-auto">
          <div className="flex min-h-full items-center justify-center p-4 text-center">
            <Transition.Child
              as={Fragment}
              enter="ease-out duration-300"
              enterFrom="opacity-0 scale-95"
              enterTo="opacity-100 scale-100"
              leave="ease-in duration-200"
              leaveFrom="opacity-100 scale-100"
              leaveTo="opacity-0 scale-95"
            >
              <Dialog.Panel className="w-full max-w-md transform overflow-hidden rounded-2xl bg-gray-900 border border-amber-500/30 p-6 text-left align-middle shadow-2xl shadow-amber-500/10 transition-all">
                <div className="sm:flex sm:items-start">
                  <div className={`mx-auto flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-full ${config.iconBg} border border-amber-500/20 sm:mx-0 sm:h-10 sm:w-10`}>
                    <ExclamationTriangleIcon className={`h-6 w-6 ${config.icon}`} aria-hidden="true" />
                  </div>
                  <div className="mt-3 text-center sm:ml-4 sm:mt-0 sm:text-left">
                    <Dialog.Title as="h3" className="text-lg font-semibold leading-6 text-amber-50">
                      {title}
                    </Dialog.Title>
                    <div className="mt-2">
                      <p className="text-sm text-amber-100/70">
                        {message}
                      </p>
                    </div>
                  </div>
                </div>
                <div className="mt-5 sm:mt-4 sm:flex sm:flex-row-reverse">
                  <button
                    type="button"
                    disabled={isLoading}
                    className={`inline-flex w-full justify-center rounded-xl px-4 py-2.5 text-sm font-semibold text-white shadow-sm ${config.button} focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-500 sm:ml-3 sm:w-auto disabled:opacity-50 disabled:cursor-not-allowed transition-all duration-200`}
                    onClick={onConfirm}
                  >
                    {isLoading ? (
                      <div className="flex items-center">
                        <svg className="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                          <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle>
                          <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        Processing...
                      </div>
                    ) : (
                      confirmText
                    )}
                  </button>
                  <button
                    type="button"
                    disabled={isLoading}
                    className="mt-3 inline-flex w-full justify-center rounded-xl bg-gray-800 px-4 py-2.5 text-sm font-semibold text-amber-100 shadow-sm border border-amber-500/30 hover:bg-amber-500/10 hover:text-amber-50 transition-all duration-200 sm:mt-0 sm:w-auto disabled:opacity-50 disabled:cursor-not-allowed"
                    onClick={onClose}
                  >
                    {cancelText}
                  </button>
                </div>
              </Dialog.Panel>
            </Transition.Child>
          </div>
        </div>
      </Dialog>
    </Transition>
  );
};

export default ConfirmationModal;