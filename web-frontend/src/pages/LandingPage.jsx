import { useState } from 'react';
import LoginModal from '../components/auth/LoginModal';
import RegisterModal from '../components/auth/RegisterModal';

const LandingPage = () => {
  const [showLogin, setShowLogin] = useState(false);
  const [showRegister, setShowRegister] = useState(false);

  return (
    <div className="min-h-screen bg-gradient-to-br from-slate-900 via-slate-800 to-slate-900">
      {/* Hero Section */}
      <div className="relative overflow-hidden">
        {/* Background decoration */}
        <div className="absolute inset-0 overflow-hidden">
          <div className="absolute -top-40 -right-40 w-80 h-80 bg-amber-500/10 rounded-full blur-3xl"></div>
          <div className="absolute -bottom-40 -left-40 w-80 h-80 bg-blue-500/10 rounded-full blur-3xl"></div>
        </div>

        {/* Navigation */}
        <nav className="relative z-10 px-6 py-4">
          <div className="max-w-7xl mx-auto flex justify-between items-center">
            <div className="flex items-center space-x-2">
              <div className="w-10 h-10 bg-gradient-to-br from-amber-400 to-amber-600 rounded-lg flex items-center justify-center">
                <span className="text-slate-900 font-bold text-xl">⚖</span>
              </div>
              <span className="text-2xl font-bold text-white">LegalEase</span>
            </div>
            <div className="flex items-center space-x-4">
              <button
                onClick={() => setShowLogin(true)}
                className="px-4 py-2 text-amber-100 hover:text-amber-300 transition-colors font-medium"
              >
                Sign In
              </button>
              <button
                onClick={() => setShowRegister(true)}
                className="px-6 py-2 bg-gradient-to-r from-amber-500 to-amber-600 text-slate-900 rounded-lg font-semibold hover:from-amber-400 hover:to-amber-500 transition-all shadow-lg shadow-amber-500/25"
              >
                Get Started
              </button>
            </div>
          </div>
        </nav>

        {/* Hero Content */}
        <div className="relative z-10 max-w-7xl mx-auto px-6 py-20 lg:py-32">
          <div className="text-center">
            <h1 className="text-4xl md:text-6xl lg:text-7xl font-bold text-white mb-6">
              Legal Services
              <span className="block text-transparent bg-clip-text bg-gradient-to-r from-amber-400 to-amber-600">
                Made Simple
              </span>
            </h1>
            <p className="text-lg md:text-xl text-slate-300 max-w-2xl mx-auto mb-10">
              Schedule appointments, manage documents, and connect with legal professionals 
              all in one secure platform. Your legal journey starts here.
            </p>
            <div className="flex flex-col sm:flex-row justify-center gap-4">
              <button
                onClick={() => setShowRegister(true)}
                className="px-8 py-4 bg-gradient-to-r from-amber-500 to-amber-600 text-slate-900 rounded-xl font-bold text-lg hover:from-amber-400 hover:to-amber-500 transition-all shadow-xl shadow-amber-500/30 transform hover:scale-105"
              >
                Book an Appointment
              </button>
              <button
                onClick={() => setShowLogin(true)}
                className="px-8 py-4 bg-slate-800/50 text-white rounded-xl font-semibold text-lg hover:bg-slate-700/50 transition-all border border-slate-700"
              >
                Sign In to Dashboard
              </button>
            </div>
          </div>
        </div>

        {/* Features Section */}
        <div className="relative z-10 max-w-7xl mx-auto px-6 py-16">
          <div className="grid md:grid-cols-3 gap-8">
            <div className="bg-slate-800/50 backdrop-blur-sm rounded-2xl p-8 border border-slate-700/50">
              <div className="w-14 h-14 bg-amber-500/10 rounded-xl flex items-center justify-center mb-6">
                <span className="text-3xl">📅</span>
              </div>
              <h3 className="text-xl font-bold text-white mb-3">Easy Scheduling</h3>
              <p className="text-slate-400">
                Book appointments online 24/7. Choose your preferred date, time, and service type.
              </p>
            </div>
            <div className="bg-slate-800/50 backdrop-blur-sm rounded-2xl p-8 border border-slate-700/50">
              <div className="w-14 h-14 bg-blue-500/10 rounded-xl flex items-center justify-center mb-6">
                <span className="text-3xl">📄</span>
              </div>
              <h3 className="text-xl font-bold text-white mb-3">Document Management</h3>
              <p className="text-slate-400">
                Securely upload, store, and share legal documents with your assigned professionals.
              </p>
            </div>
            <div className="bg-slate-800/50 backdrop-blur-sm rounded-2xl p-8 border border-slate-700/50">
              <div className="w-14 h-14 bg-green-500/10 rounded-xl flex items-center justify-center mb-6">
                <span className="text-3xl">💬</span>
              </div>
              <h3 className="text-xl font-bold text-white mb-3">Instant Messaging</h3>
              <p className="text-slate-400">
                Communicate directly with our team through secure in-app messaging.
              </p>
            </div>
          </div>
        </div>

        {/* Footer */}
        <footer className="relative z-10 border-t border-slate-800 py-8">
          <div className="max-w-7xl mx-auto px-6 text-center text-slate-500">
            <p>&copy; {new Date().getFullYear()} LegalEase. All rights reserved.</p>
          </div>
        </footer>
      </div>

      {/* Modals */}
      {showLogin && (
        <LoginModal 
          onClose={() => setShowLogin(false)} 
          onSwitchToRegister={() => {
            setShowLogin(false);
            setShowRegister(true);
          }}
        />
      )}
      {showRegister && (
        <RegisterModal 
          onClose={() => setShowRegister(false)}
          onSwitchToLogin={() => {
            setShowRegister(false);
            setShowLogin(true);
          }}
        />
      )}
    </div>
  );
};

export default LandingPage;
