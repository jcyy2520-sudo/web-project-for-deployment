import { useState, useEffect } from 'react';
import { useAuth } from '../../context/AuthContext';
import Modal from '../Modal';
import LoadingSpinner from '../LoadingSpinner';
import { useNavigate } from 'react-router-dom';
import { EyeIcon, EyeSlashIcon, EnvelopeIcon, LockClosedIcon } from '@heroicons/react/24/outline';

const AuthTabsModal = ({ isOpen, onClose, isDarkMode = true }) => {
  const [activeTab, setActiveTab] = useState('login'); // 'login' or 'register'
  
  // Login state
  const [loginFormData, setLoginFormData] = useState({
    email: '',
    password: '',
  });
  const [loginLoading, setLoginLoading] = useState(false);
  const [loginError, setLoginError] = useState('');
  const [showPassword, setShowPassword] = useState(false);
  const [rememberMe, setRememberMe] = useState(false);

  // Register state
  const [registerFormData, setRegisterFormData] = useState({
    first_name: '',
    last_name: '',
    email: '',
    password: '',
    password_confirmation: '',
  });
  const [registerLoading, setRegisterLoading] = useState(false);
  const [registerError, setRegisterError] = useState('');
  const [showRegisterPassword, setShowRegisterPassword] = useState(false);
  const [showRegisterConfirmPassword, setShowRegisterConfirmPassword] = useState(false);

  const { user, login, register } = useAuth();
  const navigate = useNavigate();

  // Load remembered email from localStorage
  useEffect(() => {
    const rememberedEmail = localStorage.getItem('rememberedEmail');
    if (rememberedEmail) {
      setLoginFormData(prev => ({ ...prev, email: rememberedEmail }));
      setRememberMe(true);
    }
  }, []);

  // Login handlers
  const handleLoginChange = (e) => {
    setLoginFormData(prev => ({
      ...prev,
      [e.target.name]: e.target.value
    }));
    if (loginError) setLoginError('');
  };

  const handleLoginSubmit = async (e) => {
    e.preventDefault();
    
    if (!loginFormData.email || !loginFormData.password) {
      setLoginError('Please fill in all fields');
      return;
    }

    setLoginLoading(true);
    setLoginError('');

    const result = await login(loginFormData.email, loginFormData.password);
    
    if (!result.success) {
      setLoginError(result.message || 'Login failed');
      setLoginLoading(false);
      return;
    }

    if (rememberMe) {
      localStorage.setItem('rememberedEmail', loginFormData.email);
    } else {
      localStorage.removeItem('rememberedEmail');
    }

    setLoginLoading(false);
    onClose();
    navigate('/dashboard');
  };

  // Register handlers
  const handleRegisterChange = (e) => {
    setRegisterFormData(prev => ({
      ...prev,
      [e.target.name]: e.target.value
    }));
    if (registerError) setRegisterError('');
  };

  const handleRegisterSubmit = async (e) => {
    e.preventDefault();

    const { first_name, last_name, email, password, password_confirmation } = registerFormData;

    if (!first_name || !last_name || !email || !password || !password_confirmation) {
      setRegisterError('Please fill in all fields');
      return;
    }

    if (password !== password_confirmation) {
      setRegisterError('Passwords do not match');
      return;
    }

    if (password.length < 8) {
      setRegisterError('Password must be at least 8 characters long');
      return;
    }

    setRegisterLoading(true);
    setRegisterError('');

    const result = await register({
      first_name,
      last_name,
      email,
      password,
      password_confirmation
    });

    if (!result.success) {
      setRegisterError(result.message || 'Registration failed. Please try again.');
      setRegisterLoading(false);
      return;
    }

    setRegisterLoading(false);
    onClose();
    navigate('/dashboard');
  };

  const handleClose = () => {
    setLoginFormData({ email: '', password: '' });
    setLoginError('');
    setShowPassword(false);
    setRegisterFormData({ first_name: '', last_name: '', email: '', password: '', password_confirmation: '' });
    setRegisterError('');
    setShowRegisterPassword(false);
    setShowRegisterConfirmPassword(false);
    onClose();
  };

  return (
    <Modal isOpen={isOpen} onClose={handleClose} size="sm" isDarkMode={isDarkMode}>
      <div className={`-mx-6 -mt-6 -mb-6 px-6 pt-4 pb-6 ${isDarkMode ? '' : 'bg-gradient-to-br from-gray-50 to-gray-100'}`}>
      {/* Tab Headers - Centered */}
      <div className={`flex gap-8 border-b justify-center ${isDarkMode ? 'border-amber-500/20' : 'border-gray-300'}`}>
        <button
          onClick={() => {
            setActiveTab('login');
            setLoginError('');
          }}
          className={`pb-4 font-medium text-sm transition-all duration-200 relative ${
            activeTab === 'login'
              ? isDarkMode ? 'text-amber-400' : 'text-amber-600'
              : isDarkMode ? 'text-gray-500 hover:text-gray-400' : 'text-gray-600 hover:text-gray-700'
          }`}
        >
          Sign In
          {activeTab === 'login' && (
            <div className={`absolute bottom-0 left-0 right-0 h-0.5 ${isDarkMode ? 'bg-amber-400' : 'bg-amber-600'}`} />
          )}
        </button>
        <button
          onClick={() => {
            setActiveTab('register');
            setRegisterError('');
          }}
          className={`pb-4 font-medium text-sm transition-all duration-200 relative ${
            activeTab === 'register'
              ? isDarkMode ? 'text-amber-400' : 'text-amber-600'
              : isDarkMode ? 'text-gray-500 hover:text-gray-400' : 'text-gray-600 hover:text-gray-700'
          }`}
        >
          Create Account
          {activeTab === 'register' && (
            <div className={`absolute bottom-0 left-0 right-0 h-0.5 ${isDarkMode ? 'bg-amber-400' : 'bg-amber-600'}`} />
          )}
        </button>
      </div>

      {/* Login Tab Content */}
      {activeTab === 'login' && (
        <form onSubmit={handleLoginSubmit} className="space-y-4 max-h-[60vh] overflow-y-auto scrollbar-hide mt-4 animate-in fade-in duration-300">
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
            <label htmlFor="login-email" className={`block text-xs font-medium mb-1 ${isDarkMode ? 'text-amber-50' : 'text-gray-800'}`}>
              Email Address
            </label>
            <div className="relative">
              <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                <EnvelopeIcon className={`h-4 w-4 ${isDarkMode ? 'text-amber-400/70' : 'text-blue-400'}`} />
              </div>
              <input
                type="email"
                id="login-email"
                name="email"
                value={loginFormData.email}
                onChange={handleLoginChange}
                className={`w-full pl-10 pr-3 py-2.5 rounded-lg focus:outline-none transition-all duration-200 text-sm ${isDarkMode ? 'bg-gray-800 border-2 border-gray-600 text-white placeholder-gray-400 focus:ring-1 focus:ring-inset focus:ring-amber-500' : 'bg-white border-2 border-gray-300 text-gray-900 placeholder-gray-400 focus:border-blue-500 focus:ring-1 focus:ring-blue-500/20 hover:border-gray-400'}`}
                required
                placeholder="your@email.com"
                autoComplete="email"
              />
            </div>
          </div>

          {/* Password Field */}
          <div>
            <div className="flex items-center justify-between mb-1">
              <label htmlFor="login-password" className={`block text-xs font-medium ${isDarkMode ? 'text-amber-50' : 'text-gray-800'}`}>
                Password
              </label>
            </div>
            <div className="relative">
              <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                <LockClosedIcon className={`h-4 w-4 ${isDarkMode ? 'text-amber-400/70' : 'text-blue-400'}`} />
              </div>
              <input
                type={showPassword ? "text" : "password"}
                id="login-password"
                name="password"
                value={loginFormData.password}
                onChange={handleLoginChange}
                className={`w-full pl-10 pr-10 py-2.5 rounded-lg focus:outline-none transition-all duration-200 text-sm ${isDarkMode ? 'bg-gray-800 border-2 border-gray-600 text-white placeholder-gray-400 focus:ring-1 focus:ring-inset focus:ring-amber-500' : 'bg-white border-2 border-gray-300 text-gray-900 placeholder-gray-400 focus:border-blue-500 focus:ring-1 focus:ring-blue-500/20 hover:border-gray-400'}`}
                required
                placeholder="Enter your password"
                autoComplete="current-password"
              />
              <button
                type="button"
                onClick={() => setShowPassword(!showPassword)}
                className={`absolute inset-y-0 right-0 pr-3 flex items-center transition-colors ${isDarkMode ? 'text-amber-400/70 hover:text-amber-300' : 'text-gray-500 hover:text-gray-700'}`}
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
              className={`${isDarkMode ? 'w-3 h-3 text-amber-600 bg-gray-800 border-amber-500/30 rounded focus:ring-amber-500 focus:ring-1 cursor-pointer' : 'w-3 h-3 text-blue-600 bg-white border-2 border-gray-300 rounded cursor-pointer focus:ring-1 focus:ring-blue-500/20'}`}
            />
            <label htmlFor="rememberMe" className={`ml-2 text-xs ${isDarkMode ? 'text-amber-100/70' : 'text-gray-700'} cursor-pointer`}>
              Remember me
            </label>
          </div>

          {/* Login Button */}
          <button
            type="submit"
            disabled={loginLoading || !loginFormData.email || !loginFormData.password}
            className={`w-full px-4 py-2.5 rounded-lg focus:outline-none transition-all duration-200 font-medium text-sm flex items-center justify-center border shadow disabled:opacity-50 disabled:cursor-not-allowed ${isDarkMode ? 'border border-amber-500/30 text-gray-900 bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-600 hover:to-amber-700 focus:ring-1 focus:ring-amber-500' : 'border border-blue-300 text-white bg-gradient-to-r from-blue-500 to-blue-600 hover:from-blue-600 hover:to-blue-700 focus:ring-2 focus:ring-blue-500/20'}`}
          >
            {loginLoading ? (
              <LoadingSpinner size="sm" />
            ) : (
              'Sign In'
            )}
          </button>

          {/* Security Notice */}
          <div className={isDarkMode ? 'bg-amber-500/5 border border-amber-500/10 rounded px-3 py-2.5' : 'bg-blue-50 border border-blue-200 rounded-lg p-3'}>
            <p className={isDarkMode ? 'text-xs text-amber-300/70 text-center leading-relaxed' : 'text-xs text-blue-700 text-center font-medium'}>
              🔒 Secure login protected
            </p>
          </div>
        </form>
      )}

      {/* Register Tab Content */}
      {activeTab === 'register' && (
        <form onSubmit={handleRegisterSubmit} className="space-y-4 max-h-[60vh] overflow-y-auto scrollbar-hide mt-4 pr-1 animate-in fade-in duration-300">
          {registerError && (
            <div className={isDarkMode ? "bg-red-500/10 border border-red-500/30 text-red-300 px-3 py-2 rounded-lg text-sm flex items-center" : "bg-red-50 border border-red-100 text-red-700 px-3 py-2 rounded-lg text-sm flex items-center"}>
              <div className="w-4 h-4 mr-2 flex-shrink-0">
                <div className={isDarkMode ? "w-2 h-2 bg-red-400 rounded-full" : "w-2 h-2 bg-red-500 rounded-full"}></div>
              </div>
              {registerError}
            </div>
          )}

          {/* First Name Field */}
          <div>
            <label htmlFor="first_name" className={`block text-xs font-medium mb-1 ${isDarkMode ? 'text-amber-50' : 'text-gray-800'}`}>
              First Name
            </label>
            <input
              type="text"
              id="first_name"
              name="first_name"
              value={registerFormData.first_name}
              onChange={handleRegisterChange}
              className={`w-full px-3 py-2.5 rounded-lg focus:outline-none transition-all duration-200 text-sm ${isDarkMode ? 'bg-gray-800 border-2 border-gray-600 text-white placeholder-gray-400 focus:ring-1 focus:ring-inset focus:ring-amber-500' : 'bg-white border-2 border-gray-300 text-gray-900 placeholder-gray-400 focus:border-blue-500 focus:ring-1 focus:ring-blue-500/20 hover:border-gray-400'}`}
              required
              placeholder="John"
              autoComplete="given-name"
            />
          </div>

          {/* Last Name Field */}
          <div>
            <label htmlFor="last_name" className={`block text-xs font-medium mb-1 ${isDarkMode ? 'text-amber-50' : 'text-gray-800'}`}>
              Last Name
            </label>
            <input
              type="text"
              id="last_name"
              name="last_name"
              value={registerFormData.last_name}
              onChange={handleRegisterChange}
              className={`w-full px-3 py-2.5 rounded-lg focus:outline-none transition-all duration-200 text-sm ${isDarkMode ? 'bg-gray-800 border-2 border-gray-600 text-white placeholder-gray-400 focus:ring-1 focus:ring-inset focus:ring-amber-500' : 'bg-white border-2 border-gray-300 text-gray-900 placeholder-gray-400 focus:border-blue-500 focus:ring-1 focus:ring-blue-500/20 hover:border-gray-400'}`}
              required
              placeholder="Doe"
              autoComplete="family-name"
            />
          </div>

          {/* Email Field */}
          <div>
            <label htmlFor="register-email" className={`block text-xs font-medium mb-1 ${isDarkMode ? 'text-amber-50' : 'text-gray-800'}`}>
              Email Address
            </label>
            <div className="relative">
              <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                <EnvelopeIcon className={`h-4 w-4 ${isDarkMode ? 'text-amber-400/70' : 'text-blue-400'}`} />
              </div>
              <input
                type="email"
                id="register-email"
                name="email"
                value={registerFormData.email}
                onChange={handleRegisterChange}
                className={`w-full pl-10 pr-3 py-2.5 rounded-lg focus:outline-none transition-all duration-200 text-sm ${isDarkMode ? 'bg-gray-800 border-2 border-gray-600 text-white placeholder-gray-400 focus:ring-1 focus:ring-inset focus:ring-amber-500' : 'bg-white border-2 border-gray-300 text-gray-900 placeholder-gray-400 focus:border-blue-500 focus:ring-1 focus:ring-blue-500/20 hover:border-gray-400'}`}
                required
                placeholder="your@email.com"
                autoComplete="email"
              />
            </div>
          </div>

          {/* Password Field */}
          <div>
            <label htmlFor="register-password" className={`block text-xs font-medium mb-1 ${isDarkMode ? 'text-amber-50' : 'text-gray-800'}`}>
              Password
            </label>
            <div className="relative">
              <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                <LockClosedIcon className={`h-4 w-4 ${isDarkMode ? 'text-amber-400/70' : 'text-blue-400'}`} />
              </div>
              <input
                type={showRegisterPassword ? "text" : "password"}
                id="register-password"
                name="password"
                value={registerFormData.password}
                onChange={handleRegisterChange}
                className={`w-full pl-10 pr-10 py-2.5 rounded-lg focus:outline-none transition-all duration-200 text-sm ${isDarkMode ? 'bg-gray-800 border-2 border-gray-600 text-white placeholder-gray-400 focus:ring-1 focus:ring-inset focus:ring-amber-500' : 'bg-white border-2 border-gray-300 text-gray-900 placeholder-gray-400 focus:border-blue-500 focus:ring-1 focus:ring-blue-500/20 hover:border-gray-400'}`}
                required
                placeholder="Enter your password"
                autoComplete="new-password"
              />
              <button
                type="button"
                onClick={() => setShowRegisterPassword(!showRegisterPassword)}
                className={`absolute inset-y-0 right-0 pr-3 flex items-center transition-colors ${isDarkMode ? 'text-amber-400/70 hover:text-amber-300' : 'text-gray-500 hover:text-gray-700'}`}
                tabIndex={-1}
              >
                {showRegisterPassword ? (
                  <EyeSlashIcon className="h-4 w-4" />
                ) : (
                  <EyeIcon className="h-4 w-4" />
                )}
              </button>
            </div>
            <p className={`text-xs mt-1 ${isDarkMode ? 'text-amber-100/60' : 'text-gray-600'}`}>
              At least 8 characters
            </p>
          </div>

          {/* Confirm Password Field */}
          <div>
            <label htmlFor="register-confirm-password" className={`block text-xs font-medium mb-1 ${isDarkMode ? 'text-amber-50' : 'text-gray-800'}`}>
              Confirm Password
            </label>
            <div className="relative">
              <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                <LockClosedIcon className={`h-4 w-4 ${isDarkMode ? 'text-amber-400/70' : 'text-blue-400'}`} />
              </div>
              <input
                type={showRegisterConfirmPassword ? "text" : "password"}
                id="register-confirm-password"
                name="password_confirmation"
                value={registerFormData.password_confirmation}
                onChange={handleRegisterChange}
                className={`w-full pl-10 pr-10 py-2.5 rounded-lg focus:outline-none transition-all duration-200 text-sm ${isDarkMode ? 'bg-gray-800 border-2 border-gray-600 text-white placeholder-gray-400 focus:ring-1 focus:ring-inset focus:ring-amber-500' : 'bg-white border-2 border-gray-300 text-gray-900 placeholder-gray-400 focus:border-blue-500 focus:ring-1 focus:ring-blue-500/20 hover:border-gray-400'}`}
                required
                placeholder="Confirm your password"
                autoComplete="new-password"
              />
              <button
                type="button"
                onClick={() => setShowRegisterConfirmPassword(!showRegisterConfirmPassword)}
                className={`absolute inset-y-0 right-0 pr-3 flex items-center transition-colors ${isDarkMode ? 'text-amber-400/70 hover:text-amber-300' : 'text-gray-500 hover:text-gray-700'}`}
                tabIndex={-1}
              >
                {showRegisterConfirmPassword ? (
                  <EyeSlashIcon className="h-4 w-4" />
                ) : (
                  <EyeIcon className="h-4 w-4" />
                )}
              </button>
            </div>
          </div>

          {/* Register Button */}
          <button
            type="submit"
            disabled={registerLoading}
            className={`w-full px-4 py-2.5 rounded-lg focus:outline-none transition-all duration-200 font-medium text-sm flex items-center justify-center border shadow disabled:opacity-50 disabled:cursor-not-allowed ${isDarkMode ? 'border border-amber-500/30 text-gray-900 bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-600 hover:to-amber-700 focus:ring-1 focus:ring-amber-500' : 'border border-blue-300 text-white bg-gradient-to-r from-blue-500 to-blue-600 hover:from-blue-600 hover:to-blue-700 focus:ring-2 focus:ring-blue-500/20'}`}
          >
            {registerLoading ? (
              <LoadingSpinner size="sm" />
            ) : (
              'Create Account'
            )}
          </button>

          {/* Security Notice */}
          <div className={isDarkMode ? 'bg-amber-500/5 border border-amber-500/10 rounded px-3 py-2.5' : 'bg-blue-50 border border-blue-200 rounded-lg p-3'}>
            <p className={isDarkMode ? 'text-xs text-amber-300/70 text-center leading-relaxed' : 'text-xs text-blue-700 text-center font-medium'}>
              🔒 Secure registration protected
            </p>
          </div>
        </form>
      )}
      </div>
    </Modal>
  );
};

export default AuthTabsModal;
