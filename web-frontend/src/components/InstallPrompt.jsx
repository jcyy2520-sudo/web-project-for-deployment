import { useState, useEffect } from 'react';
import { registerSW } from 'virtual:pwa-register';

export default function InstallPrompt() {
  const [showInstall, setShowInstall] = useState(false);
  const [deferredPrompt, setDeferredPrompt] = useState(null);
  const [isInstalled, setIsInstalled] = useState(false);
  const [error, setError] = useState(null);
  const [swReady, setSwReady] = useState(false);
  const [promptAvailable, setPromptAvailable] = useState(false);

  useEffect(() => {
    try {
      // Pick up any prompt stored globally (from index.html listeners)
      if (typeof window !== 'undefined' && window.deferredPrompt && !deferredPrompt) {
        setDeferredPrompt(window.deferredPrompt);
        setShowInstall(true);
        setPromptAvailable(true);
      }

      // Register the VitePWA-generated service worker once
      let unregisterSW = null;
      if ('serviceWorker' in navigator) {
        unregisterSW = registerSW({
          immediate: true,
          onRegistered(registration) {
            console.log('✅ Service Worker registered:', registration);
            setSwReady(true);
            if (!isInstalled && !deferredPrompt) {
              setTimeout(() => setShowInstall(true), 800);
            }
          },
          onRegisterError(err) {
            console.warn('Service Worker registration failed:', err);
            setSwReady(false);
            setError('Service Worker registration failed');
          }
        });
      }

      // Listen for beforeinstallprompt event
      const handleBeforeInstallPrompt = (e) => {
        try {
          e.preventDefault();
          setDeferredPrompt(e);
          if (typeof window !== 'undefined') {
            window.deferredPrompt = e;
          }
          setShowInstall(true);
          setPromptAvailable(true);
          console.log('✅ beforeinstallprompt event triggered');
        } catch (err) {
          console.error('Error in beforeinstallprompt handler:', err);
          setError('Install feature temporarily unavailable');
        }
      };

      // Listen for successful install
      const handleAppInstalled = () => {
        try {
          setShowInstall(false);
          setDeferredPrompt(null);
          if (typeof window !== 'undefined') {
            window.deferredPrompt = null;
          }
          setIsInstalled(true);
          setPromptAvailable(false);
          console.log('✅ App installed successfully');
        } catch (err) {
          console.error('Error in appinstalled handler:', err);
        }
      };

      // Add listeners with error handling
      window.addEventListener('beforeinstallprompt', handleBeforeInstallPrompt);
      window.addEventListener('appinstalled', handleAppInstalled);

      // Check if app is already installed
      try {
        if (window.matchMedia && window.matchMedia('(display-mode: standalone)').matches) {
          setIsInstalled(true);
          setShowInstall(false);
        }
      } catch (err) {
        console.warn('Could not check app installation status:', err);
      }

      // Cleanup function - safely remove listeners and unregister sw
      return () => {
        try {
          window.removeEventListener('beforeinstallprompt', handleBeforeInstallPrompt);
          window.removeEventListener('appinstalled', handleAppInstalled);
          if (typeof unregisterSW === 'function') {
            unregisterSW();
          }
        } catch (err) {
          console.warn('Error cleaning up PWA listeners:', err);
        }
      };
    } catch (err) {
      console.error('Error setting up InstallPrompt:', err);
      setError('Error setting up install feature');
    }
  }, []);

  const handleInstallClick = async () => {
    const promptEvent = deferredPrompt || (typeof window !== 'undefined' ? window.deferredPrompt : null);

    if (promptEvent) {
      try {
        promptEvent.prompt();
        const { outcome } = await promptEvent.userChoice;

        if (outcome === 'accepted') {
          setShowInstall(false);
          setDeferredPrompt(null);
          if (typeof window !== 'undefined') {
            window.deferredPrompt = null;
          }
          setPromptAvailable(false);
          setError(null);
        }
      } catch (err) {
        console.error('Error during app installation:', err);
        setError('Installation failed. Please try again.');
      }
    } else if (swReady) {
      // For development/localhost environments, show instructions
      try {
        const platform = getPlatformInstructions();
        alert(`📱 Install Instructions for ${platform.name}:\n\n${platform.instructions}`);
        setError(null);
      } catch (err) {
        console.error('Error showing platform instructions:', err);
        setError('Please use browser menu to install this app.');
      }
    } else {
      setError('Service Worker not ready. Please refresh the page.');
    }
  };

  const getPlatformInstructions = () => {
    const ua = navigator.userAgent.toLowerCase();
    
    if (ua.includes('chrome') && !ua.includes('edg')) {
      return {
        name: 'Chrome',
        instructions: '1. Look for the install icon (⊕ or 📲) in the address bar\n2. Click it to add to home screen\n\nOr:\n3. Click the menu (⋮) in top right\n4. Select "Install app"'
      };
    } else if (ua.includes('firefox')) {
      return {
        name: 'Firefox',
        instructions: '1. Look for the install icon in the address bar\n2. Click it to install the app'
      };
    } else if (ua.includes('safari')) {
      return {
        name: 'Safari',
        instructions: '1. Tap the Share button\n2. Scroll down and tap "Add to Home Screen"\n3. Tap "Add"'
      };
    } else if (ua.includes('edg')) {
      return {
        name: 'Edge',
        instructions: '1. Look for the install icon in the address bar\n2. Click it to install the app'
      };
    } else {
      return {
        name: 'Your Browser',
        instructions: 'Look for an "Install" or "Add to Home Screen" option in your browser menu'
      };
    }
  };

  const handleDismiss = () => {
    try {
      setShowInstall(false);
      setError(null);
    } catch (err) {
      console.error('Error dismissing install prompt:', err);
    }
  };

  // Don't render if installed or hidden
  if (!showInstall || isInstalled) {
    return null;
  }

  const installCtaLabel = promptAvailable ? 'Install' : 'Install (use browser menu)';

  return (
    <div className="fixed bottom-36 sm:bottom-24 left-4 right-4 sm:left-auto sm:right-4 z-50 animate-slide-up">
      <div className="bg-white rounded-lg shadow-lg p-4 max-w-sm mx-auto sm:mx-0">
        <div className="flex items-start justify-between gap-3">
          <div className="flex-1">
            <h3 className="font-semibold text-gray-900">Install Legal Ease</h3>
            <p className="text-sm text-gray-600 mt-1">
              Install our app on your device for faster access and offline support.
            </p>
            {error && (
              <p className="text-sm text-red-600 mt-2">{error}</p>
            )}
            {!promptAvailable && !error && (
              <p className="text-xs text-gray-500 mt-2">
                If you don’t see a native prompt, use the browser menu or address bar install icon.
              </p>
            )}
          </div>
          <button
            onClick={handleDismiss}
            className="text-gray-400 hover:text-gray-600 transition-colors flex-shrink-0"
            aria-label="Dismiss"
            type="button"
          >
            ✕
          </button>
        </div>
        <div className="flex gap-2 mt-4">
          <button
            onClick={handleDismiss}
            className="flex-1 px-3 py-2 text-sm font-medium text-gray-700 bg-gray-100 rounded hover:bg-gray-200 transition-colors"
            type="button"
          >
            Not now
          </button>
          <button
            onClick={handleInstallClick}
            className={`flex-1 px-3 py-2 text-sm font-medium text-white rounded transition-colors ${promptAvailable ? 'bg-blue-600 hover:bg-blue-700' : 'bg-blue-500 hover:bg-blue-600'}`}
            type="button"
          >
            {installCtaLabel}
          </button>
        </div>
      </div>
      <style>{`
        @keyframes slide-up {
          from {
            opacity: 0;
            transform: translateY(20px);
          }
          to {
            opacity: 1;
            transform: translateY(0);
          }
        }
        .animate-slide-up {
          animation: slide-up 0.3s ease-out;
        }
      `}</style>
    </div>
  );
}
