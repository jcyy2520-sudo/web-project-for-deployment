import { useState, useEffect } from 'react';
import { useAuth } from '../../context/AuthContext';
import Modal from '../Modal';
import LoadingSpinner from '../LoadingSpinner';
import { useNavigate } from 'react-router-dom';
import { EyeIcon, EyeSlashIcon, EnvelopeIcon, LockClosedIcon } from '@heroicons/react/24/outline';
import TermsPrivacyModal from './TermsPrivacyModal';
import { getDashboardRouteByRole, markPostAuthRedirecting } from '../../utils/authRedirect';

const LoginModal = ({ isOpen, onClose, onSwitchToRegister, onForgotPassword, isDarkMode = true }) => {
  const [formData, setFormData] = useState({
    email: '',
    password: '',
  });
  const [loginLoading, setLoginLoading] = useState(false);
  const [loginError, setLoginError] = useState('');
  const [showPassword, setShowPassword] = useState(false);
  const [rememberMe, setRememberMe] = useState(false);
  const [showTermsModal, setShowTermsModal] = useState(false);
  const [termsModalTab, setTermsModalTab] = useState('terms');
  const { user, login } = useAuth();
  const navigate = useNavigate();

  // Load remembered email from localStorage
  useEffect(() => {
    const rememberedEmail = localStorage.getItem('rememberedEmail');
    if (rememberedEmail) {
      setFormData(prev => ({ ...prev, email: rememberedEmail }));
      setRememberMe(true);
    }
  }, []);

  const handleChange = (e) => {
    setFormData(prev => ({
      ...prev,
      [e.target.name]: e.target.value
    }));
    if (loginError) setLoginError('');
  };

  // Live countdown timer for rate limit messages
  useEffect(() => {
    if (loginError && loginError.includes('try again in')) {
      const match = loginError.match(/try again in (\d+) seconds/);
      if (match && match[1]) {
        const seconds = parseInt(match[1], 10);
        if (seconds > 0) {
          const timer = setTimeout(() => {
            setLoginError(`Too many attempts! Please try again in ${seconds - 1} seconds.`);
          }, 1000);
          return () => clearTimeout(timer);
        } else {
          setLoginError('You can try logging in again now.');
        }
      }
    }
  }, [loginError]);

  const handleSubmit = async (e) => {
    e.preventDefault();
    
    if (!formData.email || !formData.password) {
      setLoginError('Please fill in all fields');
      return;
    }

    setLoginLoading(true);
    setLoginError('');

    const result = await login(formData.email, formData.password);
    
    if (!result.success) {
      setLoginError(result.message || 'Login failed');
    } else if (rememberMe) {
      // Save email to localStorage if remember me is checked
      localStorage.setItem('rememberedEmail', formData.email);
    } else {
      // Remove from localStorage if remember me is unchecked
      localStorage.removeItem('rememberedEmail');
    }
    
    setLoginLoading(false);
  };

  const togglePasswordVisibility = () => {
    setShowPassword(!showPassword);
  };

  const handleKeyDown = (e) => {
    if (e.key === 'Enter') {
      handleSubmit(e);
    }
  };

  useEffect(() => {
    if (user && isOpen) {
      onClose();
      setFormData({ email: '', password: '' });
      markPostAuthRedirecting();
      navigate(getDashboardRouteByRole(user?.role), { replace: true });
    }
  }, [user, isOpen, onClose, navigate]);

  const handleClose = () => {
    setFormData({ email: '', password: '' });
    setLoginError('');
    setShowPassword(false);
    onClose();
  };

  const handleForgotPassword = () => {
    handleClose();
    if (onForgotPassword) {
      onForgotPassword();
    }
  };

  return (
    <Modal isOpen={isOpen} onClose={handleClose} title="Sign In" size="sm" isDarkMode={isDarkMode}>
      <form onSubmit={handleSubmit} className="space-y-4">
        {loginError && (
          <div className={isDarkMode ? "bg-red-500/10 border border-red-500/30 text-red-300 px-3 py-2 rounded-lg text-sm flex items-center" : "bg-red-50 border border-red-100 text-red-700 px-3 py-2 rounded-lg text-sm flex items-center"}>
            <div className="w-4 h-4 mr-2 flex-shrink-0">
              <div className={isDarkMode ? "w-2 h-2 bg-red-400 rounded-full" : "w-2 h-2 bg-red-500 rounded-full"}></div>
            </div>
            {loginError}
          </div>
        )}
        
        {/* Email Field */}
        <div>
          <label htmlFor="email" className={`block text-xs font-medium mb-1 ${isDarkMode ? 'text-amber-50' : 'text-gray-800'}`}>
            Email Address
          </label>
          <div className="relative">
            <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
              <EnvelopeIcon className={`h-4 w-4 ${isDarkMode ? 'text-amber-400/70' : 'text-gray-400'}`} />
            </div>
            <input
              type="email"
              id="email"
              name="email"
              value={formData.email}
              onChange={handleChange}
              onKeyDown={handleKeyDown}
              className={`w-full pl-10 pr-3 py-2 rounded-lg focus:outline-none focus:border-transparent transition-all duration-200 text-sm placeholder-gray-400 ${isDarkMode ? 'bg-gray-800 border border-amber-500/20 text-white focus:ring-1 focus:ring-amber-500' : 'bg-surface-var border border-var text-primary-var focus:ring-1'}`}
              required
              placeholder="your@email.com"
              autoComplete="email"
            />
          </div>
        </div>

        {/* Password Field */}
        <div>
          <div className="flex items-center justify-between mb-1">
            <label htmlFor="password" className={`block text-xs font-medium ${isDarkMode ? 'text-amber-50' : 'text-gray-800'}`}>
              Password
            </label>
            <button
              type="button"
              onClick={handleForgotPassword}
              className={`text-xs transition-colors hover:underline ${isDarkMode ? 'text-amber-400 hover:text-amber-300' : 'text-blue-600 hover:text-blue-500'}`}
            >
              Forgot password?
            </button>
          </div>
          <div className="relative">
            <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
              <LockClosedIcon className={`h-4 w-4 ${isDarkMode ? 'text-amber-400/70' : 'text-gray-400'}`} />
            </div>
            <input
              type={showPassword ? "text" : "password"}
              id="password"
              name="password"
              value={formData.password}
              onChange={handleChange}
              onKeyDown={handleKeyDown}
              className={`w-full pl-10 pr-10 py-2 rounded-lg focus:outline-none focus:border-transparent transition-all duration-200 text-sm placeholder-gray-400 ${isDarkMode ? 'bg-gray-800 border border-amber-500/20 text-white focus:ring-1 focus:ring-amber-500' : 'bg-surface-var border border-var text-primary-var focus:ring-1'}`}
              required
              placeholder="Enter your password"
              autoComplete="current-password"
            />
            <button
              type="button"
              onClick={togglePasswordVisibility}
              className={`absolute inset-y-0 right-0 pr-3 flex items-center ${isDarkMode ? 'text-amber-400/70 hover:text-amber-300' : 'text-gray-500 hover:text-gray-700'} transition-colors`}
              tabIndex={-1}
            >
              {showPassword ? (
                <EyeSlashIcon className="h-4 w-4" />
              ) : (
                <EyeIcon className="h-4 w-4" />
              )}
            </button>
          </div>
        </div>

        {/* Remember Me Checkbox */}
        <div className="flex items-center">
          <input
            type="checkbox"
            id="rememberMe"
            checked={rememberMe}
            onChange={(e) => setRememberMe(e.target.checked)}
            className={`${isDarkMode ? 'w-3 h-3 text-amber-600 bg-gray-800 border-amber-500/30 rounded focus:ring-amber-500 focus:ring-1' : 'w-3 h-3 text-blue-600 bg-white border border-gray-300 rounded focus:ring-blue-500 focus:ring-1'}`}
          />
          <label htmlFor="rememberMe" className={`ml-2 text-xs ${isDarkMode ? 'text-amber-100/70' : 'text-gray-700'}`}>
            Remember me
          </label>
        </div>

        {/* Login Button */}
        <button
          type="submit"
          disabled={loginLoading || !formData.email || !formData.password}
          className={`w-full px-4 py-2 rounded-lg focus:outline-none transition-all duration-200 font-medium text-sm flex items-center justify-center border shadow disabled:opacity-50 disabled:cursor-not-allowed ${isDarkMode ? 'border border-amber-500/30 text-gray-900 bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-600 hover:to-amber-700 focus:ring-1 focus:ring-amber-500' : 'border border-gray-200 text-white btn-gradient-var'}`}
        >
          {loginLoading ? (
            <LoadingSpinner size="sm" />
          ) : (
            'Sign In'
          )}
        </button>

        {/* Divider */}
        <div className="relative flex items-center">
          <div className={`flex-grow border-t ${isDarkMode ? 'border-amber-500/20' : 'border-gray-200'}`}></div>
          <span className={`flex-shrink mx-4 text-xs ${isDarkMode ? 'text-amber-100/50' : 'text-gray-500'}`}>or</span>
          <div className={`flex-grow border-t ${isDarkMode ? 'border-amber-500/20' : 'border-gray-200'}`}></div>
        </div>

        {/* Google Sign In Button */}
        <button
          type="button"
          disabled={/^(?:[0-9]{1,3}\.){3}[0-9]{1,3}$/.test(window.location.hostname)}
          onClick={() => {
            const apiBase = import.meta.env.VITE_API_URL?.replace(/\/api$/, '') || 'http://localhost:8000';
            window.location.href = `${apiBase}/auth/google/redirect`;
          }}
          className={`w-full px-4 py-2 rounded-lg transition-all duration-200 font-medium text-sm flex items-center justify-center border shadow ${/^(?:[0-9]{1,3}\.){3}[0-9]{1,3}$/.test(window.location.hostname) ? 'opacity-50 cursor-not-allowed bg-gray-50 text-gray-500' : isDarkMode ? 'border border-gray-600 text-white bg-gray-700 hover:bg-gray-600 focus:ring-1 focus:ring-gray-500' : 'border border-gray-300 text-gray-700 bg-white hover:bg-gray-50 focus:ring-1 focus:ring-gray-300'}`}
          title={/^(?:[0-9]{1,3}\.){3}[0-9]{1,3}$/.test(window.location.hostname) ? "Google Auth is fundamentally blocked by Google when using a local IP Address. Please use localhost on your PC or create an account with email." : ""}
        >
          <svg className={`w-4 h-4 mr-2 ${/^(?:[0-9]{1,3}\.){3}[0-9]{1,3}$/.test(window.location.hostname) ? 'grayscale' : ''}`} viewBox="0 0 24 24">
            <path fill="currentColor" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
            <path fill="currentColor" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
            <path fill="currentColor" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
            <path fill="currentColor" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
          </svg>
          Sign In with Google
        </button>
        <div className={`text-center text-xs ${isDarkMode ? 'text-amber-100/70' : 'text-gray-700'}`}>
          Don't have an account?{' '}
          <button
            type="button"
            onClick={() => {
              handleClose();
              onSwitchToRegister();
            }}
            className={`font-medium hover:underline transition-colors ${isDarkMode ? 'text-amber-400 hover:text-amber-300' : 'text-brand hover:opacity-90'}`}
          >
            Create one
          </button>
        </div>

        {/* Security Notice */}
        <div className={isDarkMode ? 'bg-amber-500/5 border border-amber-500/10 rounded p-2' : 'bg-surface-var border border-var rounded p-2'}>
          <p className={isDarkMode ? 'text-xs text-amber-300/70 text-center' : 'text-xs text-brand text-center'}>
            🔒 Secure login protected
          </p>
        </div>

        {/* Terms & Privacy Links */}
        <div className="text-center">
          <p className={`text-xs ${isDarkMode ? 'text-amber-100/40' : 'text-gray-400'}`}>
            By signing in, you agree to our{' '}
            <button
              type="button"
              onClick={() => { setTermsModalTab('terms'); setShowTermsModal(true); }}
              className={`underline transition-colors ${isDarkMode ? 'text-amber-400/70 hover:text-amber-300' : 'text-blue-500 hover:text-blue-600'}`}
            >
              Terms &amp; Conditions
            </button>
            {' '}and{' '}
            <button
              type="button"
              onClick={() => { setTermsModalTab('privacy'); setShowTermsModal(true); }}
              className={`underline transition-colors ${isDarkMode ? 'text-amber-400/70 hover:text-amber-300' : 'text-blue-500 hover:text-blue-600'}`}
            >
              Privacy Policy
            </button>
          </p>
        </div>
      </form>

      {/* Terms & Privacy Modal */}
      <TermsPrivacyModal
        isOpen={showTermsModal}
        onClose={() => setShowTermsModal(false)}
        initialTab={termsModalTab}
        isDarkMode={isDarkMode}
      />
    </Modal>
  );
};

export default LoginModal;