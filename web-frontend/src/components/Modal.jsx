import { Fragment } from 'react';
import { Dialog, Transition } from '@headlessui/react';
import { XMarkIcon } from '@heroicons/react/24/outline';

const Modal = ({ isOpen, onClose, title, children, size = 'md', isDarkMode = true }) => {
  const sizeClasses = {
    sm: 'max-w-sm',
    md: 'max-w-md',
    lg: 'max-w-lg',
    xl: 'max-w-xl',
    '2xl': 'max-w-2xl',
    '3xl': 'max-w-3xl',
    full: 'max-w-full mx-4'
  };

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
          <div className={`fixed inset-0 ${isDarkMode ? 'bg-black/70' : 'bg-black/20'} backdrop-blur-sm`} />
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
              <Dialog.Panel className={`relative w-full ${sizeClasses[size]} transform overflow-hidden rounded-2xl p-6 text-left align-middle transition-all`} style={isDarkMode ? { backgroundColor: undefined, border: '1px solid rgba(245,158,11,0.18)', boxShadow: '0 20px 60px rgba(245,158,11,0.06)' } : { backgroundColor: 'var(--surface)', border: '1px solid var(--borders)', boxShadow: '0 20px 60px rgba(0,0,0,0.06)' }}>
                {title ? (
                  <div className="flex items-center justify-between mb-4">
                    <Dialog.Title as="h3" className="text-lg font-semibold" style={isDarkMode ? { color: '#FDE68A' } : { color: 'var(--text-primary)' }}>
                      {title}
                    </Dialog.Title>
                    <button
                      onClick={onClose}
                      aria-label="Close dialog"
                      className="p-1 rounded-lg transition-all duration-200"
                      style={isDarkMode ? { color: 'rgba(245,158,11,0.7)', backgroundColor: 'transparent' } : { color: 'var(--text-secondary)', backgroundColor: 'transparent' }}
                    >
                      <XMarkIcon className="h-6 w-6" />
                    </button>
                  </div>
                ) : (
                  <button
                    onClick={onClose}
                    aria-label="Close dialog"
                    className="absolute top-4 right-4 p-1 rounded-lg transition-all duration-200 z-10"
                    style={isDarkMode ? { color: 'rgba(245,158,11,0.7)', backgroundColor: 'transparent' } : { color: 'var(--text-secondary)', backgroundColor: 'transparent' }}
                  >
                    <XMarkIcon className="h-6 w-6" />
                  </button>
                )}
                {children}
              </Dialog.Panel>
            </Transition.Child>
          </div>
        </div>
      </Dialog>
    </Transition>
  );
};

export default Modal;