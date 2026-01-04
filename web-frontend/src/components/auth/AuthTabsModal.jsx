import { useState, useEffect } from 'react';
import { useAuth } from '../../context/AuthContext';
import { useApi } from '../../hooks/useApi';
import axios from 'axios';
import Modal from '../Modal';
import LoadingSpinner from '../LoadingSpinner';
import { useNavigate } from 'react-router-dom';
import { 
  EyeIcon, 
  EyeSlashIcon, 
  EnvelopeIcon, 
  LockClosedIcon,
  UserIcon,
  PhoneIcon,
  MapPinIcon,
  CheckCircleIcon,
  XCircleIcon,
  ClockIcon
} from '@heroicons/react/24/outline';

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

  // Register state - 3 step registration
  const [registerStep, setRegisterStep] = useState(1);
  const [registerFormData, setRegisterFormData] = useState({
    username: '',
    email: '',
    password: '',
    password_confirmation: '',
    verificationCode: '',
    firstName: '',
    lastName: '',
    phone: '',
    address: '',
  });
  const [registerNotification, setRegisterNotification] = useState({ show: false, message: '', type: 'success' });
  const [showRegisterPassword, setShowRegisterPassword] = useState(false);
  const [showRegisterConfirmPassword, setShowRegisterConfirmPassword] = useState(false);
  const [timeLeft, setTimeLeft] = useState(30);

  const { user, login } = useAuth();
  const { loading: registerLoading, callApi } = useApi();
  const navigate = useNavigate();

  // API base path - axios baseURL is just the domain, so we need /api prefix
  const API_BASE = '/api';

  // Load remembered email from localStorage
  useEffect(() => {
    const rememberedEmail = localStorage.getItem('rememberedEmail');
    if (rememberedEmail) {
      setLoginFormData(prev => ({ ...prev, email: rememberedEmail }));
      setRememberMe(true);
    }
  }, []);

  // Timer effect for step 2
  useEffect(() => {
    if (registerStep === 2 && timeLeft > 0) {
      const timer = setTimeout(() => {
        setTimeLeft(timeLeft - 1);
      }, 60000); // 1 minute
      return () => clearTimeout(timer);
    } else if (registerStep === 2 && timeLeft === 0) {
      showRegisterNotification('Verification code has expired. Please request a new one.', 'error');
      setRegisterStep(1);
    }
  }, [registerStep, timeLeft]);

  // Reset timer when moving to step 2
  useEffect(() => {
    if (registerStep === 2) {
      setTimeLeft(30);
    }
  }, [registerStep]);

  // Show register notification
  const showRegisterNotification = (message, type = 'success') => {
    setRegisterNotification({ show: true, message, type });
    setTimeout(() => {
      setRegisterNotification({ show: false, message: '', type: 'success' });
    }, 5000);
  };

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
    
    // Route based on user role
    if (result.user && result.user.role === 'admin') {
      navigate('/admin/dashboard');
    } else {
      navigate('/dashboard');
    }
  };

  // Register handlers
  const handleRegisterChange = (e) => {
    setRegisterFormData(prev => ({
      ...prev,
      [e.target.name]: e.target.value
    }));
  };

  const validateStep1 = () => {
    if (registerFormData.password !== registerFormData.password_confirmation) {
      showRegisterNotification('Passwords do not match', 'error');
      return false;
    }
    if (registerFormData.password.length < 8) {
      showRegisterNotification('Password must be at least 8 characters long', 'error');
      return false;
    }
    if (registerFormData.username.length < 3) {
      showRegisterNotification('Username must be at least 3 characters', 'error');
      return false;
    }
    if (!registerFormData.username.match(/^[a-zA-Z0-9_]+$/)) {
      showRegisterNotification('Username can only contain letters, numbers, and underscores', 'error');
      return false;
    }
    return true;
  };

  const handleRegisterStep1 = async (e) => {
    e.preventDefault();
    if (!validateStep1()) return;

    const result = await callApi((signal) =>
      axios.post(`${API_BASE}/register-step1`, {
        username: registerFormData.username,
        email: registerFormData.email,
        password: registerFormData.password,
        password_confirmation: registerFormData.password_confirmation,
      }, { signal })
    , { requireAuth: false });

    if (result.success) {
      setRegisterStep(2);
      showRegisterNotification('Verification code sent to your email!', 'success');
    } else if (result.status === 422 && result.data) {
      const data = result.data;
      if (data?.errors && typeof data.errors === 'object') {
        const errorMessages = Object.entries(data.errors)
          .map(([field, messages]) => Array.isArray(messages) && messages.length > 0 ? messages[0] : messages)
          .filter(Boolean)
          .join('\n');
        showRegisterNotification(errorMessages || 'Validation failed', 'error');
      } else {
        showRegisterNotification(result.error || 'Validation failed', 'error');
      }
    } else {
      showRegisterNotification(result.error || 'Registration failed', 'error');
    }
  };

  const handleResendCode = async () => {
    const result = await callApi((signal) =>
      axios.post(`${API_BASE}/resend-verification`, { email: registerFormData.email }, { signal })
    , { requireAuth: false });

    if (result.success) {
      setTimeLeft(30);
      showRegisterNotification('New verification code sent!', 'success');
    } else {
      showRegisterNotification(result.error || 'Failed to resend code. Please try again.', 'error');
    }
  };

  const autoFormatVerificationCode = (value) => {
    const numericValue = value.replace(/\D/g, '');
    if (numericValue.length <= 6) {
      setRegisterFormData(prev => ({ ...prev, verificationCode: numericValue }));
    }
  };

  const handleRegisterStep2 = async (e) => {
    e.preventDefault();
    if (registerFormData.verificationCode.length !== 6) {
      showRegisterNotification('Please enter a valid 6-digit verification code', 'error');
      return;
    }

    const result = await callApi((signal) =>
      axios.post(`${API_BASE}/verify-code`, { 
        email: registerFormData.email, 
        code: registerFormData.verificationCode 
      }, { signal })
    , { requireAuth: false });

    if (result.success && result.data?.verified) {
      setRegisterStep(3);
      showRegisterNotification('Email verified successfully!', 'success');
    } else if (result.status === 422 && result.data) {
      showRegisterNotification(result.data?.message || result.error || 'Invalid or expired verification code', 'error');
    } else {
      showRegisterNotification(result.error || 'Verification failed', 'error');
    }
  };

  const handleRegisterStep3 = async (e) => {
    e.preventDefault();
    if (!registerFormData.firstName.trim() || !registerFormData.lastName.trim() || 
        !registerFormData.phone.trim() || !registerFormData.address.trim()) {
      showRegisterNotification('Please fill in all required fields', 'error');
      return;
    }

    const result = await callApi((signal) =>
      axios.post(`${API_BASE}/complete-registration`, {
        username: registerFormData.username,
        email: registerFormData.email,
        password: registerFormData.password,
        first_name: registerFormData.firstName,
        last_name: registerFormData.lastName,
        phone: registerFormData.phone,
        address: registerFormData.address,
      }, { signal })
    , { requireAuth: false });

    if (result.success) {
      showRegisterNotification('Registration successful! You can now sign in.', 'success');
      setTimeout(() => {
        resetRegisterForm();
        setActiveTab('login');
      }, 2000);
    } else if (result.status === 422 && result.data) {
      const data = result.data;
      if (data?.errors && typeof data.errors === 'object') {
        const errorMessages = Object.entries(data.errors)
          .map(([field, messages]) => Array.isArray(messages) && messages.length > 0 ? messages[0] : messages)
          .filter(Boolean)
          .join('\n');
        showRegisterNotification(errorMessages || 'Validation failed', 'error');
      } else {
        showRegisterNotification(result.error || 'Registration failed', 'error');
      }
    } else {
      showRegisterNotification(result.error || 'Registration failed. Please try again.', 'error');
    }
  };

  const resetRegisterForm = () => {
    setRegisterStep(1);
    setRegisterFormData({
      username: '',
      email: '',
      password: '',
      password_confirmation: '',
      verificationCode: '',
      firstName: '',
      lastName: '',
      phone: '',
      address: '',
    });
    setTimeLeft(30);
    setShowRegisterPassword(false);
    setShowRegisterConfirmPassword(false);
    setRegisterNotification({ show: false, message: '', type: 'success' });
  };

  const handleClose = () => {
    setLoginFormData({ email: '', password: '' });
    setLoginError('');
    setShowPassword(false);
    resetRegisterForm();
    onClose();
  };

  const getRegisterStepTitle = () => {
    switch (registerStep) {
      case 1: return 'Create Account';
      case 2: return 'Verify Email';
      case 3: return 'Complete Profile';
      default: return 'Create Account';
    }
  };

  return (
    <Modal isOpen={isOpen} onClose={handleClose} size="sm" isDarkMode={isDarkMode}>
      <div className={`-mx-6 -mt-6 -mb-6 px-6 pt-4 pb-6 overflow-hidden ${isDarkMode ? '' : 'bg-gradient-to-br from-gray-50 to-gray-100'}`}>
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
              resetRegisterForm();
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
                <XCircleIcon className="w-4 h-4 mr-2 flex-shrink-0" />
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
                  className={`w-full pl-10 pr-3 py-2.5 rounded-lg focus:outline-none transition-all duration-200 text-sm ${isDarkMode ? 'bg-gray-800 border border-amber-500/20 text-white placeholder-gray-400 focus:ring-inset focus:ring-1 focus:ring-amber-500' : 'bg-white border border-gray-300 text-gray-900 placeholder-gray-400 focus:border-blue-500 focus:ring-inset focus:ring-1 focus:ring-blue-500/20'}`}
                  required
                  placeholder="your@email.com"
                  autoComplete="email"
                />
              </div>
            </div>

            {/* Password Field */}
            <div>
              <label htmlFor="login-password" className={`block text-xs font-medium mb-1 ${isDarkMode ? 'text-amber-50' : 'text-gray-800'}`}>
                Password
              </label>
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
                  className={`w-full pl-10 pr-10 py-2.5 rounded-lg focus:outline-none transition-all duration-200 text-sm ${isDarkMode ? 'bg-gray-800 border border-amber-500/20 text-white placeholder-gray-400 focus:ring-inset focus:ring-1 focus:ring-amber-500' : 'bg-white border border-gray-300 text-gray-900 placeholder-gray-400 focus:border-blue-500 focus:ring-inset focus:ring-1 focus:ring-blue-500/20'}`}
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
                  {showPassword ? <EyeSlashIcon className="h-4 w-4" /> : <EyeIcon className="h-4 w-4" />}
                </button>
              </div>
            </div>

            {/* Remember Me */}
            <div className="flex items-center">
              <input
                type="checkbox"
                id="rememberMe"
                checked={rememberMe}
                onChange={(e) => setRememberMe(e.target.checked)}
                className={`w-3 h-3 rounded cursor-pointer ${isDarkMode ? 'text-amber-600 bg-gray-800 border-amber-500/30 focus:ring-amber-500' : 'text-blue-600 bg-white border-gray-300 focus:ring-blue-500'}`}
              />
              <label htmlFor="rememberMe" className={`ml-2 text-xs cursor-pointer ${isDarkMode ? 'text-amber-100/70' : 'text-gray-700'}`}>
                Remember me
              </label>
            </div>

            {/* Login Button */}
            <button
              type="submit"
              disabled={loginLoading || !loginFormData.email || !loginFormData.password}
              className={`w-full px-4 py-2.5 rounded-lg focus:outline-none transition-all duration-200 font-medium text-sm flex items-center justify-center disabled:opacity-50 disabled:cursor-not-allowed ${isDarkMode ? 'text-gray-900 bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-600 hover:to-amber-700' : 'text-white bg-gradient-to-r from-blue-500 to-blue-600 hover:from-blue-600 hover:to-blue-700'}`}
            >
              {loginLoading ? <LoadingSpinner size="sm" /> : 'Sign In'}
            </button>

            {/* Security Notice */}
            <div className={isDarkMode ? 'bg-amber-500/5 border border-amber-500/10 rounded px-3 py-2.5' : 'bg-blue-50 border border-blue-200 rounded-lg p-3'}>
              <p className={isDarkMode ? 'text-xs text-amber-300/70 text-center' : 'text-xs text-blue-700 text-center font-medium'}>
                🔒 Secure login protected
              </p>
            </div>
          </form>
        )}

        {/* Register Tab Content - 3 Step Registration */}
        {activeTab === 'register' && (
          <div className="mt-4 animate-in fade-in duration-300">
            {/* Notification */}
            {registerNotification.show && (
              <div className={`mb-4 p-3 rounded-lg border flex items-center space-x-2 ${registerNotification.type === 'success' ? (isDarkMode ? 'bg-green-500/10 border-green-500/30 text-green-300' : 'bg-green-50 border-green-100 text-green-700') : (isDarkMode ? 'bg-red-500/10 border-red-500/30 text-red-300' : 'bg-red-50 border-red-100 text-red-700')}`}>
                {registerNotification.type === 'success' ? (
                  <CheckCircleIcon className="h-4 w-4 flex-shrink-0" />
                ) : (
                  <XCircleIcon className="h-4 w-4 flex-shrink-0" />
                )}
                <span className="text-sm">{registerNotification.message}</span>
              </div>
            )}

            {/* Step Progress Indicator */}
            <div className="mb-6">
              <div className="flex justify-between mb-3">
                {[1, 2, 3].map((stepNum) => (
                  <div key={stepNum} className="flex flex-col items-center">
                    <div
                      className={`w-8 h-8 rounded-full flex items-center justify-center text-xs font-semibold transition-all duration-300 border ${
                        registerStep >= stepNum 
                          ? (isDarkMode ? 'bg-gradient-to-br from-amber-500 to-amber-600 text-gray-900 border-amber-500/30' : 'bg-gradient-to-br from-blue-500 to-blue-600 text-white border-blue-300') 
                          : (isDarkMode ? 'bg-gray-800 text-gray-400 border-amber-500/20' : 'bg-white text-gray-500 border-gray-300')
                      }`}
                    >
                      {stepNum}
                    </div>
                    <div className={`text-xs mt-1 ${isDarkMode ? 'text-amber-100/70' : 'text-gray-600'}`}>
                      {stepNum === 1 && 'Account'}
                      {stepNum === 2 && 'Verify'}
                      {stepNum === 3 && 'Profile'}
                    </div>
                  </div>
                ))}
              </div>
              <div className={`relative rounded-full h-1.5 ${isDarkMode ? 'bg-gray-800 border border-amber-500/20' : 'bg-gray-200'}`}>
                <div 
                  className={`absolute top-0 left-0 h-full rounded-full transition-all duration-500 ease-out ${isDarkMode ? 'bg-gradient-to-r from-amber-500 to-amber-600' : 'bg-gradient-to-r from-blue-500 to-blue-600'}`}
                  style={{ width: `${(registerStep / 3) * 100}%` }}
                ></div>
              </div>
            </div>

            {/* Step 1: Account Info */}
            {registerStep === 1 && (
              <form onSubmit={handleRegisterStep1} className="space-y-4 max-h-[50vh] overflow-y-auto overflow-x-hidden scrollbar-hide px-1">
                <div>
                  <label htmlFor="username" className={`block text-xs font-medium mb-1 ${isDarkMode ? 'text-amber-50' : 'text-gray-800'}`}>
                    Username *
                  </label>
                  <div className="relative">
                    <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                      <UserIcon className={`h-4 w-4 ${isDarkMode ? 'text-amber-400/70' : 'text-blue-400'}`} />
                    </div>
                    <input
                      type="text"
                      id="username"
                      name="username"
                      value={registerFormData.username}
                      onChange={handleRegisterChange}
                      className={`w-full pl-10 pr-3 py-2 rounded-lg focus:outline-none transition-all duration-200 text-sm ${isDarkMode ? 'bg-gray-800 border border-amber-500/20 text-white placeholder-gray-400 focus:ring-inset focus:ring-1 focus:ring-amber-500' : 'bg-white border border-gray-300 text-gray-900 placeholder-gray-400 focus:border-blue-500 focus:ring-inset focus:ring-1 focus:ring-blue-500/20'}`}
                      required
                      placeholder="Enter username"
                    />
                  </div>
                </div>

                <div>
                  <label htmlFor="reg-email" className={`block text-xs font-medium mb-1 ${isDarkMode ? 'text-amber-50' : 'text-gray-800'}`}>
                    Email Address *
                  </label>
                  <div className="relative">
                    <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                      <EnvelopeIcon className={`h-4 w-4 ${isDarkMode ? 'text-amber-400/70' : 'text-blue-400'}`} />
                    </div>
                    <input
                      type="email"
                      id="reg-email"
                      name="email"
                      value={registerFormData.email}
                      onChange={handleRegisterChange}
                      className={`w-full pl-10 pr-3 py-2 rounded-lg focus:outline-none transition-all duration-200 text-sm ${isDarkMode ? 'bg-gray-800 border border-amber-500/20 text-white placeholder-gray-400 focus:ring-inset focus:ring-1 focus:ring-amber-500' : 'bg-white border border-gray-300 text-gray-900 placeholder-gray-400 focus:border-blue-500 focus:ring-inset focus:ring-1 focus:ring-blue-500/20'}`}
                      required
                      placeholder="your@email.com"
                    />
                  </div>
                </div>

                <div>
                  <label htmlFor="reg-password" className={`block text-xs font-medium mb-1 ${isDarkMode ? 'text-amber-50' : 'text-gray-800'}`}>
                    Password *
                  </label>
                  <div className="relative">
                    <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                      <LockClosedIcon className={`h-4 w-4 ${isDarkMode ? 'text-amber-400/70' : 'text-blue-400'}`} />
                    </div>
                    <input
                      type={showRegisterPassword ? "text" : "password"}
                      id="reg-password"
                      name="password"
                      value={registerFormData.password}
                      onChange={handleRegisterChange}
                      className={`w-full pl-10 pr-10 py-2 rounded-lg focus:outline-none transition-all duration-200 text-sm ${isDarkMode ? 'bg-gray-800 border border-amber-500/20 text-white placeholder-gray-400 focus:ring-inset focus:ring-1 focus:ring-amber-500' : 'bg-white border border-gray-300 text-gray-900 placeholder-gray-400 focus:border-blue-500 focus:ring-inset focus:ring-1 focus:ring-blue-500/20'}`}
                      required
                      minLength="8"
                      placeholder="Enter password"
                    />
                    <button
                      type="button"
                      onClick={() => setShowRegisterPassword(!showRegisterPassword)}
                      className={`absolute inset-y-0 right-0 pr-3 flex items-center ${isDarkMode ? 'text-amber-400/70 hover:text-amber-300' : 'text-gray-500 hover:text-gray-700'}`}
                    >
                      {showRegisterPassword ? <EyeSlashIcon className="h-4 w-4" /> : <EyeIcon className="h-4 w-4" />}
                    </button>
                  </div>
                  <p className={`text-xs mt-1 ${isDarkMode ? 'text-amber-100/50' : 'text-gray-500'}`}>Minimum 8 characters</p>
                </div>

                <div>
                  <label htmlFor="reg-confirm-password" className={`block text-xs font-medium mb-1 ${isDarkMode ? 'text-amber-50' : 'text-gray-800'}`}>
                    Confirm Password *
                  </label>
                  <div className="relative">
                    <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                      <LockClosedIcon className={`h-4 w-4 ${isDarkMode ? 'text-amber-400/70' : 'text-blue-400'}`} />
                    </div>
                    <input
                      type={showRegisterConfirmPassword ? "text" : "password"}
                      id="reg-confirm-password"
                      name="password_confirmation"
                      value={registerFormData.password_confirmation}
                      onChange={handleRegisterChange}
                      className={`w-full pl-10 pr-10 py-2 rounded-lg focus:outline-none transition-all duration-200 text-sm ${isDarkMode ? 'bg-gray-800 border border-amber-500/20 text-white placeholder-gray-400 focus:ring-inset focus:ring-1 focus:ring-amber-500' : 'bg-white border border-gray-300 text-gray-900 placeholder-gray-400 focus:border-blue-500 focus:ring-inset focus:ring-1 focus:ring-blue-500/20'}`}
                      required
                      placeholder="Confirm password"
                    />
                    <button
                      type="button"
                      onClick={() => setShowRegisterConfirmPassword(!showRegisterConfirmPassword)}
                      className={`absolute inset-y-0 right-0 pr-3 flex items-center ${isDarkMode ? 'text-amber-400/70 hover:text-amber-300' : 'text-gray-500 hover:text-gray-700'}`}
                    >
                      {showRegisterConfirmPassword ? <EyeSlashIcon className="h-4 w-4" /> : <EyeIcon className="h-4 w-4" />}
                    </button>
                  </div>
                </div>

                <button
                  type="submit"
                  disabled={registerLoading}
                  className={`w-full px-4 py-2.5 rounded-lg focus:outline-none transition-all duration-200 font-medium text-sm flex items-center justify-center disabled:opacity-50 disabled:cursor-not-allowed ${isDarkMode ? 'text-gray-900 bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-600 hover:to-amber-700' : 'text-white bg-gradient-to-r from-blue-500 to-blue-600 hover:from-blue-600 hover:to-blue-700'}`}
                >
                  {registerLoading ? <LoadingSpinner size="sm" /> : 'Continue'}
                </button>
              </form>
            )}

            {/* Step 2: Verify Email */}
            {registerStep === 2 && (
              <form onSubmit={handleRegisterStep2} className="space-y-4">
                <div className={`rounded-lg p-4 ${isDarkMode ? 'bg-amber-500/10 border border-amber-500/30' : 'bg-blue-50 border border-blue-200'}`}>
                  <p className={`text-sm font-medium ${isDarkMode ? 'text-amber-200' : 'text-blue-800'}`}>
                    Verification code sent to:
                  </p>
                  <p className={`text-sm mt-1 font-semibold ${isDarkMode ? 'text-amber-50' : 'text-blue-900'}`}>{registerFormData.email}</p>
                  <div className={`flex items-center mt-2 text-xs ${isDarkMode ? 'text-amber-200/80' : 'text-blue-700'}`}>
                    <ClockIcon className="h-3 w-3 mr-1" />
                    <span>Expires in: <span className={`font-bold ${isDarkMode ? 'text-red-400' : 'text-red-600'}`}>{timeLeft} minutes</span></span>
                  </div>
                </div>

                <div>
                  <label htmlFor="verificationCode" className={`block text-xs font-medium mb-1 ${isDarkMode ? 'text-amber-50' : 'text-gray-800'}`}>
                    Verification Code *
                  </label>
                  <input
                    type="text"
                    id="verificationCode"
                    name="verificationCode"
                    value={registerFormData.verificationCode}
                    onChange={(e) => autoFormatVerificationCode(e.target.value)}
                    className={`w-full px-4 py-3 rounded-lg focus:outline-none transition-all duration-200 text-center text-lg tracking-widest font-mono font-bold ${isDarkMode ? 'bg-gray-800 border border-amber-500/20 text-white placeholder-gray-400 focus:ring-inset focus:ring-1 focus:ring-amber-500' : 'bg-white border border-gray-300 text-gray-900 placeholder-gray-400 focus:border-blue-500 focus:ring-inset focus:ring-1 focus:ring-blue-500/20'}`}
                    maxLength="6"
                    required
                    placeholder="000000"
                    inputMode="numeric"
                  />
                  <p className={`text-xs mt-1 text-center ${isDarkMode ? 'text-amber-100/50' : 'text-gray-500'}`}>Enter the 6-digit code from your email</p>
                </div>

                <div className="text-center">
                  <button
                    type="button"
                    onClick={handleResendCode}
                    className={`text-xs font-medium underline transition-colors ${isDarkMode ? 'text-amber-400 hover:text-amber-300' : 'text-blue-600 hover:text-blue-700'}`}
                    disabled={registerLoading}
                  >
                    Didn't receive code? Resend
                  </button>
                </div>

                <div className="flex justify-between gap-3">
                  <button
                    type="button"
                    onClick={() => setRegisterStep(1)}
                    className={`px-4 py-2 rounded-lg transition-all duration-200 font-medium text-sm ${isDarkMode ? 'border border-amber-500/30 text-amber-100 hover:bg-amber-500/10' : 'border border-gray-300 text-gray-700 hover:bg-gray-50'}`}
                    disabled={registerLoading}
                  >
                    Back
                  </button>
                  <button
                    type="submit"
                    disabled={registerLoading}
                    className={`flex-1 px-4 py-2 rounded-lg focus:outline-none transition-all duration-200 font-medium text-sm flex items-center justify-center disabled:opacity-50 disabled:cursor-not-allowed ${isDarkMode ? 'text-gray-900 bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-600 hover:to-amber-700' : 'text-white bg-gradient-to-r from-blue-500 to-blue-600 hover:from-blue-600 hover:to-blue-700'}`}
                  >
                    {registerLoading ? <LoadingSpinner size="sm" /> : 'Verify'}
                  </button>
                </div>
              </form>
            )}

            {/* Step 3: Complete Profile */}
            {registerStep === 3 && (
              <form onSubmit={handleRegisterStep3} className="space-y-4 max-h-[50vh] overflow-y-auto overflow-x-hidden scrollbar-hide px-1">
                <div className="grid grid-cols-2 gap-3">
                  <div>
                    <label htmlFor="firstName" className={`block text-xs font-medium mb-1 ${isDarkMode ? 'text-amber-50' : 'text-gray-800'}`}>
                      First Name *
                    </label>
                    <input
                      type="text"
                      id="firstName"
                      name="firstName"
                      value={registerFormData.firstName}
                      onChange={handleRegisterChange}
                      className={`w-full px-3 py-2 rounded-lg focus:outline-none transition-all duration-200 text-sm ${isDarkMode ? 'bg-gray-800 border border-amber-500/20 text-white placeholder-gray-400 focus:ring-inset focus:ring-1 focus:ring-amber-500' : 'bg-white border border-gray-300 text-gray-900 placeholder-gray-400 focus:border-blue-500 focus:ring-inset focus:ring-1 focus:ring-blue-500/20'}`}
                      required
                      placeholder="First name"
                    />
                  </div>
                  <div>
                    <label htmlFor="lastName" className={`block text-xs font-medium mb-1 ${isDarkMode ? 'text-amber-50' : 'text-gray-800'}`}>
                      Last Name *
                    </label>
                    <input
                      type="text"
                      id="lastName"
                      name="lastName"
                      value={registerFormData.lastName}
                      onChange={handleRegisterChange}
                      className={`w-full px-3 py-2 rounded-lg focus:outline-none transition-all duration-200 text-sm ${isDarkMode ? 'bg-gray-800 border border-amber-500/20 text-white placeholder-gray-400 focus:ring-inset focus:ring-1 focus:ring-amber-500' : 'bg-white border border-gray-300 text-gray-900 placeholder-gray-400 focus:border-blue-500 focus:ring-inset focus:ring-1 focus:ring-blue-500/20'}`}
                      required
                      placeholder="Last name"
                    />
                  </div>
                </div>

                <div>
                  <label htmlFor="phone" className={`block text-xs font-medium mb-1 ${isDarkMode ? 'text-amber-50' : 'text-gray-800'}`}>
                    Phone Number *
                  </label>
                  <div className="relative">
                    <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                      <PhoneIcon className={`h-4 w-4 ${isDarkMode ? 'text-amber-400/70' : 'text-blue-400'}`} />
                    </div>
                    <input
                      type="tel"
                      id="phone"
                      name="phone"
                      value={registerFormData.phone}
                      onChange={handleRegisterChange}
                      className={`w-full pl-10 pr-3 py-2 rounded-lg focus:outline-none transition-all duration-200 text-sm ${isDarkMode ? 'bg-gray-800 border border-amber-500/20 text-white placeholder-gray-400 focus:ring-inset focus:ring-1 focus:ring-amber-500' : 'bg-white border border-gray-300 text-gray-900 placeholder-gray-400 focus:border-blue-500 focus:ring-inset focus:ring-1 focus:ring-blue-500/20'}`}
                      required
                      placeholder="Phone number"
                    />
                  </div>
                </div>

                <div>
                  <label htmlFor="address" className={`block text-xs font-medium mb-1 ${isDarkMode ? 'text-amber-50' : 'text-gray-800'}`}>
                    Address *
                  </label>
                  <div className="relative">
                    <div className="absolute top-3 left-3 flex items-start pointer-events-none">
                      <MapPinIcon className={`h-4 w-4 ${isDarkMode ? 'text-amber-400/70' : 'text-blue-400'}`} />
                    </div>
                    <textarea
                      id="address"
                      name="address"
                      value={registerFormData.address}
                      onChange={handleRegisterChange}
                      className={`w-full pl-10 pr-3 py-2 rounded-lg focus:outline-none transition-all duration-200 text-sm resize-none ${isDarkMode ? 'bg-gray-800 border border-amber-500/20 text-white placeholder-gray-400 focus:ring-inset focus:ring-1 focus:ring-amber-500' : 'bg-white border border-gray-300 text-gray-900 placeholder-gray-400 focus:border-blue-500 focus:ring-inset focus:ring-1 focus:ring-blue-500/20'}`}
                      rows="2"
                      required
                      placeholder="Complete address"
                    />
                  </div>
                </div>

                {/* Account Summary */}
                <div className={`rounded-lg p-3 ${isDarkMode ? 'bg-amber-500/10 border border-amber-500/30' : 'bg-blue-50 border border-blue-200'}`}>
                  <h4 className={`text-xs font-semibold mb-2 ${isDarkMode ? 'text-amber-50' : 'text-blue-800'}`}>Account Summary:</h4>
                  <div className={`text-xs space-y-1 ${isDarkMode ? 'text-amber-100/70' : 'text-blue-700'}`}>
                    <p><strong>Username:</strong> {registerFormData.username}</p>
                    <p><strong>Email:</strong> {registerFormData.email}</p>
                  </div>
                </div>

                <div className="flex justify-between gap-3">
                  <button
                    type="button"
                    onClick={() => setRegisterStep(2)}
                    className={`px-4 py-2 rounded-lg transition-all duration-200 font-medium text-sm ${isDarkMode ? 'border border-amber-500/30 text-amber-100 hover:bg-amber-500/10' : 'border border-gray-300 text-gray-700 hover:bg-gray-50'}`}
                    disabled={registerLoading}
                  >
                    Back
                  </button>
                  <button
                    type="submit"
                    disabled={registerLoading}
                    className={`flex-1 px-4 py-2 rounded-lg focus:outline-none transition-all duration-200 font-medium text-sm flex items-center justify-center disabled:opacity-50 disabled:cursor-not-allowed ${isDarkMode ? 'text-gray-900 bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-600 hover:to-amber-700' : 'text-white bg-gradient-to-r from-blue-500 to-blue-600 hover:from-blue-600 hover:to-blue-700'}`}
                  >
                    {registerLoading ? <LoadingSpinner size="sm" /> : 'Complete Registration'}
                  </button>
                </div>
              </form>
            )}
          </div>
        )}
      </div>
    </Modal>
  );
};

export default AuthTabsModal;
