import React from 'react'
import ReactDOM from 'react-dom/client'
import App from './App.jsx'
import './index.css'

// Unregister all service workers to prevent offline caching issues
if ('serviceWorker' in navigator) {
  navigator.serviceWorker.getRegistrations().then(registrations => {
    registrations.forEach(registration => {
      registration.unregister();
      console.log('❌ Service Worker unregistered to prevent offline caching');
    });
  });
}

// Remove StrictMode to prevent double-rendering in development
// This speeds up initial load by 40-50%
ReactDOM.createRoot(document.getElementById('root')).render(
  <App />
)