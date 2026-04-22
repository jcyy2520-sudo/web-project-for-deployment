import { useState, useEffect, useRef } from 'react';
import { useAuth } from '../../context/AuthContext';
import { useApi } from '../../hooks/useApi';
import axios from 'axios';
import Modal from '../Modal';
import LoadingSpinner from '../LoadingSpinner';
import { useNavigate } from 'react-router-dom';
import { getDashboardRouteByRole, markPostAuthRedirecting } from '../../utils/authRedirect';
import TermsPrivacyModal from './TermsPrivacyModal';
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
  ClockIcon,
  ArrowLeftIcon,
  KeyIcon,
  ShieldCheckIcon
} from '@heroicons/react/24/outline';

const AuthTabsModal = ({ isOpen, onClose, isDarkMode = true }) => {
  const [activeTab, setActiveTab] = useState('login'); // 'login' or 'register'
  const loginEmailRef = useRef(null);
  const registerUsernameRef = useRef(null);

  // Auto-focus first input when modal opens or tab changes
  useEffect(() => {
    if (!isOpen) return;
    const timer = setTimeout(() => {
      if (activeTab === 'login' && loginEmailRef.current) {
        loginEmailRef.current.focus();
      } else if (activeTab === 'register' && registerUsernameRef.current) {
        registerUsernameRef.current.focus();
      }
    }, 150); // Small delay for transition
    return () => clearTimeout(timer);
  }, [isOpen, activeTab]);
  
  // Login state
  const [loginFormData, setLoginFormData] = useState({
    email: '',
    password: '',
  });
  const [loginLoading, setLoginLoading] = useState(false);
  const [loginError, setLoginError] = useState('');
  const [loginSuccess, setLoginSuccess] = useState('');
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
  const [agreedToTermsStep3, setAgreedToTermsStep3] = useState(false);
  const [showTermsModal, setShowTermsModal] = useState(false);
  const [termsModalTab, setTermsModalTab] = useState('terms');
  const [timeLeft, setTimeLeft] = useState(30);

  // Forgot Password state
  const [forgotStep, setForgotStep] = useState(1); // 1=email, 2=code, 3=new password, 4=success
  const [forgotEmail, setForgotEmail] = useState('');
  const [forgotCode, setForgotCode] = useState('');
  const [forgotPassword, setForgotPassword] = useState('');
  const [forgotPasswordConfirm, setForgotPasswordConfirm] = useState('');
  const [forgotLoading, setForgotLoading] = useState(false);
  const [forgotError, setForgotError] = useState('');
  const [forgotSuccess, setForgotSuccess] = useState('');
  const [showForgotPassword, setShowForgotPassword] = useState(false);
  const [showForgotConfirmPassword, setShowForgotConfirmPassword] = useState(false);
  const [forgotCodeTimeLeft, setForgotCodeTimeLeft] = useState(15);

  const { user, login } = useAuth();
  const { loading: registerLoading, callApi } = useApi();
  const navigate = useNavigate();

  // API base path - axios baseURL is just the domain, so we need /api prefix
  const API_BASE = '/api';
  const backendUrl = (import.meta.env.VITE_API_URL || 'http://localhost:8000').replace(/\/api\/?$/, '');
  const googleAuthUrl = `${backendUrl}/auth/google/redirect`;

  // Load remembered email from localStorage
  useEffect(() => {
    const rememberedEmail = localStorage.getItem('rememberedEmail');
    if (rememberedEmail) {
      setLoginFormData(prev => ({ ...prev, email: rememberedEmail }));
      setRememberMe(true);
    }
  }, []);

  // Surface Google OAuth errors passed from backend callback.
  useEffect(() => {
    if (!isOpen) return;
    const oauthError = sessionStorage.getItem('oauth_error_message');
    const oauthErrorTab = sessionStorage.getItem('oauth_error_tab');
    const oauthSuccess = sessionStorage.getItem('oauth_success_message');

    if (oauthSuccess) {
      setActiveTab('login');
      setLoginError('');
      setLoginSuccess(oauthSuccess);
      sessionStorage.removeItem('oauth_success_message');
    }

    if (oauthError) {
      if (oauthErrorTab === 'register' || oauthErrorTab === 'login') {
        setActiveTab(oauthErrorTab);
      } else {
        setActiveTab('login');
      }
      if (oauthErrorTab === 'register') {
        showRegisterNotification(oauthError, 'error');
      } else {
        setLoginError(oauthError);
      }
      sessionStorage.removeItem('oauth_error_message');
      sessionStorage.removeItem('oauth_error_tab');
    }
  }, [isOpen]);

  // Timer effect for registration verification code
  useEffect(() => {
    if (registerStep === 2 && timeLeft > 0) {
      const timer = setTimeout(() => {
        setTimeLeft(timeLeft - 1);
      }, 60000); // This one is minutes
      return () => clearTimeout(timer);
    } else if (registerStep === 2 && timeLeft === 0) {
      showRegisterNotification('Verification code has expired. Please request a new one.', 'error');
      setRegisterStep(1);
    }
  }, [registerStep, timeLeft]);

  // Live countdown timer for rate limit messages (Login)
  useEffect(() => {
    if (loginError && loginError.includes('try again in')) {
      const match = loginError.match(/try again in (\d+) seconds/i);
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

  // Live countdown timer for rate limit messages (Register Notifications)
  useEffect(() => {
    if (registerNotification.show && registerNotification.message?.includes('try again in')) {
      const match = registerNotification.message.match(/try again in (\d+) seconds/i);
      if (match && match[1]) {
        const seconds = parseInt(match[1], 10);
        if (seconds > 0) {
          const timer = setTimeout(() => {
            setRegisterNotification(prev => ({
              ...prev,
              message: prev.message.replace(/in \d+ seconds/i, `in ${seconds - 1} seconds`)
            }));
          }, 1000);
          return () => clearTimeout(timer);
        } else {
          setRegisterNotification(prev => ({
            ...prev,
            message: 'You can try again now.',
            type: 'success'
          }));
        }
      }
    }
  }, [registerNotification.show, registerNotification.message]);

  // Reset timer when moving to step 2
  useEffect(() => {
    if (registerStep === 2) {
      setTimeLeft(30);
    }
  }, [registerStep]);

  // Show register notification
  const showRegisterNotification = (message, type = 'success') => {
    setRegisterNotification({ show: true, message, type });
    // If it's a rate limit message, don't auto-hide it after 5 seconds
    if (!message?.includes('try again in')) {
      setTimeout(() => {
        setRegisterNotification(prev => {
          // Check if it's still the same message to avoid clearing new notifications
          if (prev.message === message) {
            return { show: false, message: '', type: 'success' };
          }
          return prev;
        });
      }, 5000);
    }
  };

  // Login handlers
  const handleLoginChange = (e) => {
    setLoginFormData(prev => ({
      ...prev,
      [e.target.name]: e.target.value
    }));
    if (loginError) setLoginError('');
    if (loginSuccess) setLoginSuccess('');
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
    markPostAuthRedirecting();
    navigate(getDashboardRouteByRole(result.user?.role), { replace: true });
    onClose();
  };

  // Forgot password timer for code expiration
  useEffect(() => {
    if (forgotStep === 2 && forgotCodeTimeLeft > 0) {
      const timer = setTimeout(() => {
        setForgotCodeTimeLeft(prev => prev - 1);
      }, 60000); // 1 minute
      return () => clearTimeout(timer);
    } else if (forgotStep === 2 && forgotCodeTimeLeft === 0) {
      setForgotError('Verification code has expired. Please request a new one.');
      setForgotStep(1);
    }
  }, [forgotStep, forgotCodeTimeLeft]);

  // Live countdown timer for rate limit messages (Forgot Password)
  useEffect(() => {
    if (forgotError && forgotError.includes('try again in')) {
      const match = forgotError.match(/try again in (\d+) seconds/i);
      if (match && match[1]) {
        const seconds = parseInt(match[1], 10);
        if (seconds > 0) {
          const timer = setTimeout(() => {
            setForgotError(prev => prev.replace(/in \d+ seconds/i, `in ${seconds - 1} seconds`));
          }, 1000);
          return () => clearTimeout(timer);
        } else {
          setForgotError('You can try again now.');
        }
      }
    }
  }, [forgotError]);

  // Forgot password handlers
  const resetForgotState = () => {
    setForgotStep(1);
    setForgotEmail('');
    setForgotCode('');
    setForgotPassword('');
    setForgotPasswordConfirm('');
    setForgotLoading(false);
    setForgotError('');
    setForgotSuccess('');
    setShowForgotPassword(false);
    setShowForgotConfirmPassword(false);
    setForgotCodeTimeLeft(15);
  };

  const handleForgotSendCode = async (e) => {
    e.preventDefault();
    if (!forgotEmail) {
      setForgotError('Please enter your email address.');
      return;
    }
    setForgotLoading(true);
    setForgotError('');
    try {
      const res = await axios.post(`${API_BASE}/forgot-password/send-code`, { email: forgotEmail });
      setForgotSuccess(res.data.message);
      setForgotCodeTimeLeft(15);
      setForgotStep(2);
    } catch (err) {
      setForgotError(err.response?.data?.message || 'Failed to send code. Please try again.');
    } finally {
      setForgotLoading(false);
    }
  };

  const handleForgotVerifyCode = async (e) => {
    e.preventDefault();
    if (!forgotCode || forgotCode.length !== 6) {
      setForgotError('Please enter a valid 6-digit code.');
      return;
    }
    setForgotLoading(true);
    setForgotError('');
    try {
      await axios.post(`${API_BASE}/forgot-password/verify-code`, { email: forgotEmail, code: forgotCode });
      setForgotSuccess('Code verified! Set your new password.');
      setForgotStep(3);
    } catch (err) {
      setForgotError(err.response?.data?.message || 'Invalid or expired code.');
    } finally {
      setForgotLoading(false);
    }
  };

  const handleForgotResetPassword = async (e) => {
    e.preventDefault();
    if (forgotPassword.length < 8) {
      setForgotError('Password must be at least 8 characters long.');
      return;
    }
    if (forgotPassword !== forgotPasswordConfirm) {
      setForgotError('Passwords do not match.');
      return;
    }
    setForgotLoading(true);
    setForgotError('');
    try {
      await axios.post(`${API_BASE}/forgot-password/reset`, {
        email: forgotEmail,
        password: forgotPassword,
        password_confirmation: forgotPasswordConfirm,
      });
      setForgotStep(4);
      setForgotSuccess('Password reset successfully! You can now sign in with your new password.');
    } catch (err) {
      setForgotError(err.response?.data?.message || 'Failed to reset password.');
    } finally {
      setForgotLoading(false);
    }
  };

  const handleForgotResendCode = async () => {
    setForgotLoading(true);
    setForgotError('');
    try {
      const res = await axios.post(`${API_BASE}/forgot-password/resend-code`, { email: forgotEmail });
      setForgotSuccess(res.data.message);
      setForgotCodeTimeLeft(15);
      setForgotCode('');
    } catch (err) {
      setForgotError(err.response?.data?.message || 'Failed to resend code.');
    } finally {
      setForgotLoading(false);
    }
  };

  // Register handlers
  const handleRegisterChange = (e) => {
    setRegisterFormData(prev => ({
      ...prev,
      [e.target.name]: e.target.value
    }));
  };

  // Password strength calculator
  const getPasswordStrength = (password) => {
    if (!password) return { level: 0, label: '', color: '' };
    let score = 0;
    if (password.length >= 8) score++;
    if (password.length >= 12) score++;
    if (/[a-z]/.test(password)) score++;
    if (/[A-Z]/.test(password)) score++;
    if (/[0-9]/.test(password)) score++;
    if (/[^a-zA-Z0-9]/.test(password)) score++;

    if (score <= 2) return { level: 1, label: 'Weak', color: 'red' };
    if (score <= 3) return { level: 2, label: 'Fair', color: 'orange' };
    if (score <= 4) return { level: 3, label: 'Good', color: 'yellow' };
    if (score <= 5) return { level: 4, label: 'Strong', color: 'green' };
    return { level: 5, label: 'Very Strong', color: 'emerald' };
  };

  const passwordStrength = getPasswordStrength(registerFormData.password);
  const passwordsMatch = registerFormData.password_confirmation.length === 0 || registerFormData.password === registerFormData.password_confirmation;

  const validateStep1 = () => {
    if (registerFormData.username.length < 3) {
      showRegisterNotification('Username must be at least 3 characters', 'error');
      return false;
    }
    if (registerFormData.username.length > 50) {
      showRegisterNotification('Username must be at most 50 characters', 'error');
      return false;
    }
    if (registerFormData.password.length < 8) {
      showRegisterNotification('Password must be at least 8 characters long', 'error');
      return false;
    }
    if (registerFormData.password !== registerFormData.password_confirmation) {
      showRegisterNotification('Passwords do not match', 'error');
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
    if (!agreedToTermsStep3) {
      showRegisterNotification('Please agree to the Privacy Policy and Terms & Conditions to proceed', 'error');
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
      showRegisterNotification('Registration submitted. Check your email and click It is me to activate your login.', 'success');
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
    setAgreedToTermsStep3(false);
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
        {/* Tab Headers - Hidden during forgot password flow */}
        {activeTab !== 'forgot' ? (
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
        ) : (
          <div className={`pb-3 border-b ${isDarkMode ? 'border-amber-500/20' : 'border-gray-300'}`}>
            <h2 className={`text-sm font-semibold text-center ${isDarkMode ? 'text-amber-50' : 'text-gray-900'}`}>Reset Password</h2>
          </div>
        )}

        {/* Login Tab Content */}
        {activeTab === 'login' && (
          <form onSubmit={handleLoginSubmit} className="space-y-4 max-h-[60vh] overflow-y-auto scrollbar-hide mt-4 animate-in fade-in duration-300">
            {loginSuccess && (
              <div className={isDarkMode ? "bg-green-500/10 border border-green-500/30 text-green-300 px-3 py-2 rounded-lg text-sm flex items-center" : "bg-green-50 border border-green-100 text-green-700 px-3 py-2 rounded-lg text-sm flex items-center"}>
                <CheckCircleIcon className="w-4 h-4 mr-2 flex-shrink-0" />
                {loginSuccess}
              </div>
            )}

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
                  ref={loginEmailRef}
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
                  aria-label={showPassword ? 'Hide password' : 'Show password'}
                >
                  {showPassword ? <EyeSlashIcon className="h-4 w-4" /> : <EyeIcon className="h-4 w-4" />}
                </button>
              </div>
            </div>

            {/* Remember Me & Forgot Password */}
            <div className="flex items-center justify-between">
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
              <button
                type="button"
                onClick={() => {
                  setActiveTab('forgot');
                  resetForgotState();
                  setLoginError('');
                }}
                className={`text-xs transition-colors hover:underline ${isDarkMode ? 'text-amber-400 hover:text-amber-300' : 'text-blue-600 hover:text-blue-500'}`}
              >
                Forgot password?
              </button>
            </div>

            {/* Login Button */}
            <button
              type="submit"
              disabled={loginLoading || !loginFormData.email || !loginFormData.password}
              className={`w-full px-4 py-2.5 rounded-lg focus:outline-none transition-all duration-200 font-medium text-sm flex items-center justify-center disabled:opacity-50 disabled:cursor-not-allowed ${isDarkMode ? 'text-gray-900 bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-600 hover:to-amber-700' : 'text-white bg-gradient-to-r from-blue-500 to-blue-600 hover:from-blue-600 hover:to-blue-700'}`}
            >
              {loginLoading ? <LoadingSpinner size="sm" /> : 'Sign In'}
            </button>

            <div className="relative flex items-center">
              <div className={`flex-grow border-t ${isDarkMode ? 'border-amber-500/20' : 'border-gray-200'}`}></div>
              <span className={`flex-shrink mx-4 text-xs ${isDarkMode ? 'text-amber-100/50' : 'text-gray-500'}`}>or</span>
              <div className={`flex-grow border-t ${isDarkMode ? 'border-amber-500/20' : 'border-gray-200'}`}></div>
            </div>

            <button
              type="button"
              onClick={() => { window.location.href = `${googleAuthUrl}?mode=login`; }}
              className={`w-full px-4 py-2.5 rounded-lg focus:outline-none transition-all duration-200 font-medium text-sm flex items-center justify-center border ${isDarkMode ? 'border-gray-600 text-white bg-gray-700 hover:bg-gray-600' : 'border-gray-300 text-gray-700 bg-white hover:bg-gray-50'}`}
            >
              <svg className="w-4 h-4 mr-2" viewBox="0 0 24 24" aria-hidden="true">
                <path fill="currentColor" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                <path fill="currentColor" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                <path fill="currentColor" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
                <path fill="currentColor" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
              </svg>
              Sign In with Google
            </button>

            {/* Security Notice */}
            <div className={isDarkMode ? 'bg-amber-500/5 border border-amber-500/10 rounded px-3 py-2.5' : 'bg-blue-50 border border-blue-200 rounded-lg p-3'}>
              <p className={isDarkMode ? 'text-xs text-amber-300/70 text-center' : 'text-xs text-blue-700 text-center font-medium'}>
                🔒 Secure login protected
              </p>
            </div>
          </form>
        )}

        {/* Forgot Password Tab Content */}
        {activeTab === 'forgot' && (
          <div className="mt-4 animate-in fade-in duration-300 max-h-[60vh] overflow-y-auto scrollbar-hide">
            {/* Back to Login */}
            <button
              onClick={() => { setActiveTab('login'); resetForgotState(); }}
              className={`flex items-center text-xs mb-4 transition-colors ${isDarkMode ? 'text-amber-400 hover:text-amber-300' : 'text-blue-600 hover:text-blue-500'}`}
            >
              <ArrowLeftIcon className="h-3 w-3 mr-1" />
              Back to Sign In
            </button>

            {/* Step Progress */}
            {forgotStep < 4 && (
              <div className="mb-5">
                <div className="flex justify-between mb-3">
                  {[1, 2, 3].map((stepNum) => (
                    <div key={stepNum} className="flex flex-col items-center">
                      <div className={`w-8 h-8 rounded-full flex items-center justify-center text-xs font-semibold transition-all duration-300 border ${
                        forgotStep >= stepNum
                          ? (isDarkMode ? 'bg-gradient-to-br from-amber-500 to-amber-600 text-gray-900 border-amber-500/30' : 'bg-gradient-to-br from-blue-500 to-blue-600 text-white border-blue-300')
                          : (isDarkMode ? 'bg-gray-800 text-gray-400 border-amber-500/20' : 'bg-white text-gray-500 border-gray-300')
                      }`}>
                        {stepNum === 1 && <EnvelopeIcon className="h-3.5 w-3.5" />}
                        {stepNum === 2 && <KeyIcon className="h-3.5 w-3.5" />}
                        {stepNum === 3 && <LockClosedIcon className="h-3.5 w-3.5" />}
                      </div>
                      <div className={`text-xs mt-1 ${isDarkMode ? 'text-amber-100/70' : 'text-gray-600'}`}>
                        {stepNum === 1 && 'Email'}
                        {stepNum === 2 && 'Verify'}
                        {stepNum === 3 && 'Reset'}
                      </div>
                    </div>
                  ))}
                </div>
                <div className={`relative rounded-full h-1.5 ${isDarkMode ? 'bg-gray-800 border border-amber-500/20' : 'bg-gray-200'}`}>
                  <div
                    className={`absolute top-0 left-0 h-full rounded-full transition-all duration-500 ease-out ${isDarkMode ? 'bg-gradient-to-r from-amber-500 to-amber-600' : 'bg-gradient-to-r from-blue-500 to-blue-600'}`}
                    style={{ width: `${(forgotStep / 3) * 100}%` }}
                  ></div>
                </div>
              </div>
            )}

            {/* Error / Success messages */}
            {forgotError && (
              <div className={`mb-3 p-3 rounded-lg border flex items-center space-x-2 ${isDarkMode ? 'bg-red-500/10 border-red-500/30 text-red-300' : 'bg-red-50 border-red-100 text-red-700'}`}>
                <XCircleIcon className="h-4 w-4 flex-shrink-0" />
                <span className="text-sm">{forgotError}</span>
              </div>
            )}
            {forgotSuccess && forgotStep !== 4 && (
              <div className={`mb-3 p-3 rounded-lg border flex items-center space-x-2 ${isDarkMode ? 'bg-green-500/10 border-green-500/30 text-green-300' : 'bg-green-50 border-green-100 text-green-700'}`}>
                <CheckCircleIcon className="h-4 w-4 flex-shrink-0" />
                <span className="text-sm">{forgotSuccess}</span>
              </div>
            )}

            {/* Step 1: Enter Email */}
            {forgotStep === 1 && (
              <form onSubmit={handleForgotSendCode} className="space-y-4">
                <div className={`text-center mb-2 ${isDarkMode ? 'text-amber-100/80' : 'text-gray-600'}`}>
                  <KeyIcon className={`h-10 w-10 mx-auto mb-2 ${isDarkMode ? 'text-amber-400' : 'text-blue-500'}`} />
                  <h3 className={`text-base font-semibold ${isDarkMode ? 'text-amber-50' : 'text-gray-900'}`}>Forgot Password?</h3>
                  <p className="text-xs mt-1">Enter your registered email and we'll send you a verification code.</p>
                </div>
                <div>
                  <label className={`block text-xs font-medium mb-1 ${isDarkMode ? 'text-amber-50' : 'text-gray-800'}`}>
                    Email Address
                  </label>
                  <div className="relative">
                    <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                      <EnvelopeIcon className={`h-4 w-4 ${isDarkMode ? 'text-amber-400/70' : 'text-blue-400'}`} />
                    </div>
                    <input
                      type="email"
                      value={forgotEmail}
                      onChange={(e) => { setForgotEmail(e.target.value); setForgotError(''); }}
                      className={`w-full pl-10 pr-3 py-2.5 rounded-lg focus:outline-none transition-all duration-200 text-sm ${isDarkMode ? 'bg-gray-800 border border-amber-500/20 text-white placeholder-gray-400 focus:ring-inset focus:ring-1 focus:ring-amber-500' : 'bg-white border border-gray-300 text-gray-900 placeholder-gray-400 focus:border-blue-500 focus:ring-inset focus:ring-1 focus:ring-blue-500/20'}`}
                      placeholder="your@email.com"
                      required
                      autoComplete="email"
                    />
                  </div>
                </div>
                <button
                  type="submit"
                  disabled={forgotLoading || !forgotEmail}
                  className={`w-full px-4 py-2.5 rounded-lg focus:outline-none transition-all duration-200 font-medium text-sm flex items-center justify-center disabled:opacity-50 disabled:cursor-not-allowed ${isDarkMode ? 'text-gray-900 bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-600 hover:to-amber-700' : 'text-white bg-gradient-to-r from-blue-500 to-blue-600 hover:from-blue-600 hover:to-blue-700'}`}
                >
                  {forgotLoading ? <LoadingSpinner size="sm" /> : 'Send Verification Code'}
                </button>
              </form>
            )}

            {/* Step 2: Enter Code */}
            {forgotStep === 2 && (
              <form onSubmit={handleForgotVerifyCode} className="space-y-4">
                <div className={`text-center mb-2 ${isDarkMode ? 'text-amber-100/80' : 'text-gray-600'}`}>
                  <ShieldCheckIcon className={`h-10 w-10 mx-auto mb-2 ${isDarkMode ? 'text-amber-400' : 'text-blue-500'}`} />
                  <h3 className={`text-base font-semibold ${isDarkMode ? 'text-amber-50' : 'text-gray-900'}`}>Enter Verification Code</h3>
                  <p className="text-xs mt-1">We sent a 6-digit code to <span className="font-medium">{forgotEmail}</span></p>
                </div>
                <div>
                  <label className={`block text-xs font-medium mb-1 ${isDarkMode ? 'text-amber-50' : 'text-gray-800'}`}>
                    Verification Code
                  </label>
                  <input
                    type="text"
                    value={forgotCode}
                    onChange={(e) => { setForgotCode(e.target.value.replace(/\D/g, '').slice(0, 6)); setForgotError(''); }}
                    className={`w-full px-4 py-3 rounded-lg focus:outline-none transition-all duration-200 text-center text-2xl font-mono tracking-[0.5em] ${isDarkMode ? 'bg-gray-800 border border-amber-500/20 text-white placeholder-gray-400 focus:ring-inset focus:ring-1 focus:ring-amber-500' : 'bg-white border border-gray-300 text-gray-900 placeholder-gray-400 focus:border-blue-500 focus:ring-inset focus:ring-1 focus:ring-blue-500/20'}`}
                    placeholder="000000"
                    maxLength={6}
                    required
                  />
                  <div className="flex justify-between items-center mt-2">
                    <span className={`text-xs ${isDarkMode ? 'text-amber-400/60' : 'text-gray-500'}`}>
                      <ClockIcon className="h-3 w-3 inline mr-1" />
                      Expires in {forgotCodeTimeLeft} min
                    </span>
                    <button
                      type="button"
                      onClick={handleForgotResendCode}
                      disabled={forgotLoading}
                      className={`text-xs transition-colors hover:underline ${isDarkMode ? 'text-amber-400 hover:text-amber-300' : 'text-blue-600 hover:text-blue-500'}`}
                    >
                      Resend Code
                    </button>
                  </div>
                </div>
                <button
                  type="submit"
                  disabled={forgotLoading || forgotCode.length !== 6}
                  className={`w-full px-4 py-2.5 rounded-lg focus:outline-none transition-all duration-200 font-medium text-sm flex items-center justify-center disabled:opacity-50 disabled:cursor-not-allowed ${isDarkMode ? 'text-gray-900 bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-600 hover:to-amber-700' : 'text-white bg-gradient-to-r from-blue-500 to-blue-600 hover:from-blue-600 hover:to-blue-700'}`}
                >
                  {forgotLoading ? <LoadingSpinner size="sm" /> : 'Verify Code'}
                </button>
              </form>
            )}

            {/* Step 3: New Password */}
            {forgotStep === 3 && (
              <form onSubmit={handleForgotResetPassword} className="space-y-4">
                <div className={`text-center mb-2 ${isDarkMode ? 'text-amber-100/80' : 'text-gray-600'}`}>
                  <LockClosedIcon className={`h-10 w-10 mx-auto mb-2 ${isDarkMode ? 'text-amber-400' : 'text-blue-500'}`} />
                  <h3 className={`text-base font-semibold ${isDarkMode ? 'text-amber-50' : 'text-gray-900'}`}>Set New Password</h3>
                  <p className="text-xs mt-1">Create a strong new password for your account.</p>
                </div>
                <div>
                  <label className={`block text-xs font-medium mb-1 ${isDarkMode ? 'text-amber-50' : 'text-gray-800'}`}>
                    New Password
                  </label>
                  <div className="relative">
                    <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                      <LockClosedIcon className={`h-4 w-4 ${isDarkMode ? 'text-amber-400/70' : 'text-blue-400'}`} />
                    </div>
                    <input
                      type={showForgotPassword ? "text" : "password"}
                      value={forgotPassword}
                      onChange={(e) => { setForgotPassword(e.target.value); setForgotError(''); }}
                      className={`w-full pl-10 pr-10 py-2.5 rounded-lg focus:outline-none transition-all duration-200 text-sm ${isDarkMode ? 'bg-gray-800 border border-amber-500/20 text-white placeholder-gray-400 focus:ring-inset focus:ring-1 focus:ring-amber-500' : 'bg-white border border-gray-300 text-gray-900 placeholder-gray-400 focus:border-blue-500 focus:ring-inset focus:ring-1 focus:ring-blue-500/20'}`}
                      placeholder="Min. 8 characters"
                      required
                      minLength={8}
                    />
                    <button
                      type="button"
                      onClick={() => setShowForgotPassword(!showForgotPassword)}
                      className={`absolute inset-y-0 right-0 pr-3 flex items-center transition-colors ${isDarkMode ? 'text-amber-400/70 hover:text-amber-300' : 'text-gray-500 hover:text-gray-700'}`}
                      tabIndex={-1}
                    >
                      {showForgotPassword ? <EyeSlashIcon className="h-4 w-4" /> : <EyeIcon className="h-4 w-4" />}
                    </button>
                  </div>
                </div>
                <div>
                  <label className={`block text-xs font-medium mb-1 ${isDarkMode ? 'text-amber-50' : 'text-gray-800'}`}>
                    Confirm New Password
                  </label>
                  <div className="relative">
                    <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                      <LockClosedIcon className={`h-4 w-4 ${isDarkMode ? 'text-amber-400/70' : 'text-blue-400'}`} />
                    </div>
                    <input
                      type={showForgotConfirmPassword ? "text" : "password"}
                      value={forgotPasswordConfirm}
                      onChange={(e) => { setForgotPasswordConfirm(e.target.value); setForgotError(''); }}
                      className={`w-full pl-10 pr-10 py-2.5 rounded-lg focus:outline-none transition-all duration-200 text-sm ${isDarkMode ? 'bg-gray-800 border border-amber-500/20 text-white placeholder-gray-400 focus:ring-inset focus:ring-1 focus:ring-amber-500' : 'bg-white border border-gray-300 text-gray-900 placeholder-gray-400 focus:border-blue-500 focus:ring-inset focus:ring-1 focus:ring-blue-500/20'}`}
                      placeholder="Confirm your new password"
                      required
                      minLength={8}
                    />
                    <button
                      type="button"
                      onClick={() => setShowForgotConfirmPassword(!showForgotConfirmPassword)}
                      className={`absolute inset-y-0 right-0 pr-3 flex items-center transition-colors ${isDarkMode ? 'text-amber-400/70 hover:text-amber-300' : 'text-gray-500 hover:text-gray-700'}`}
                      tabIndex={-1}
                    >
                      {showForgotConfirmPassword ? <EyeSlashIcon className="h-4 w-4" /> : <EyeIcon className="h-4 w-4" />}
                    </button>
                  </div>
                </div>
                {forgotPassword && forgotPasswordConfirm && forgotPassword !== forgotPasswordConfirm && (
                  <p className={`text-xs ${isDarkMode ? 'text-red-400' : 'text-red-600'}`}>Passwords do not match</p>
                )}
                <button
                  type="submit"
                  disabled={forgotLoading || !forgotPassword || !forgotPasswordConfirm || forgotPassword !== forgotPasswordConfirm}
                  className={`w-full px-4 py-2.5 rounded-lg focus:outline-none transition-all duration-200 font-medium text-sm flex items-center justify-center disabled:opacity-50 disabled:cursor-not-allowed ${isDarkMode ? 'text-gray-900 bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-600 hover:to-amber-700' : 'text-white bg-gradient-to-r from-blue-500 to-blue-600 hover:from-blue-600 hover:to-blue-700'}`}
                >
                  {forgotLoading ? <LoadingSpinner size="sm" /> : 'Reset Password'}
                </button>
              </form>
            )}

            {/* Step 4: Success */}
            {forgotStep === 4 && (
              <div className="text-center space-y-4 py-4">
                <div className={`w-16 h-16 mx-auto rounded-full flex items-center justify-center ${isDarkMode ? 'bg-green-500/10 border border-green-500/30' : 'bg-green-50 border border-green-200'}`}>
                  <CheckCircleIcon className={`h-8 w-8 ${isDarkMode ? 'text-green-400' : 'text-green-600'}`} />
                </div>
                <h3 className={`text-base font-semibold ${isDarkMode ? 'text-amber-50' : 'text-gray-900'}`}>Password Reset Successfully!</h3>
                <p className={`text-sm ${isDarkMode ? 'text-amber-100/70' : 'text-gray-600'}`}>
                  Your password has been updated. You can now sign in with your new password.
                </p>
                <button
                  onClick={() => { setActiveTab('login'); resetForgotState(); }}
                  className={`w-full px-4 py-2.5 rounded-lg focus:outline-none transition-all duration-200 font-medium text-sm flex items-center justify-center ${isDarkMode ? 'text-gray-900 bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-600 hover:to-amber-700' : 'text-white bg-gradient-to-r from-blue-500 to-blue-600 hover:from-blue-600 hover:to-blue-700'}`}
                >
                  Back to Sign In
                </button>
              </div>
            )}
          </div>
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
                      ref={registerUsernameRef}
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
                  {/* Password Strength Indicator */}
                  {registerFormData.password.length > 0 && (
                    <div className="mt-1.5 space-y-1">
                      <div className="flex gap-1">
                        {[1, 2, 3, 4, 5].map((i) => (
                          <div
                            key={i}
                            className={`h-1 flex-1 rounded-full transition-all duration-300 ${
                              i <= passwordStrength.level
                                ? passwordStrength.color === 'red' ? 'bg-red-500'
                                : passwordStrength.color === 'orange' ? 'bg-orange-500'
                                : passwordStrength.color === 'yellow' ? 'bg-yellow-500'
                                : passwordStrength.color === 'green' ? 'bg-green-500'
                                : 'bg-emerald-500'
                                : isDarkMode ? 'bg-gray-700' : 'bg-gray-200'
                            }`}
                          />
                        ))}
                      </div>
                      <div className="flex justify-between items-center">
                        <p className={`text-xs ${
                          passwordStrength.color === 'red' ? 'text-red-400'
                          : passwordStrength.color === 'orange' ? 'text-orange-400'
                          : passwordStrength.color === 'yellow' ? 'text-yellow-400'
                          : passwordStrength.color === 'green' ? 'text-green-400'
                          : 'text-emerald-400'
                        }`}>
                          {passwordStrength.label} password
                        </p>
                        <p className={`text-xs ${isDarkMode ? 'text-amber-100/40' : 'text-gray-400'}`}>Min 8 characters</p>
                      </div>
                    </div>
                  )}
                  {registerFormData.password.length === 0 && (
                    <p className={`text-xs mt-1 ${isDarkMode ? 'text-amber-100/50' : 'text-gray-500'}`}>Minimum 8 characters</p>
                  )}
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
                      className={`w-full pl-10 pr-10 py-2 rounded-lg focus:outline-none transition-all duration-200 text-sm ${
                        registerFormData.password_confirmation.length > 0 && !passwordsMatch
                          ? isDarkMode ? 'bg-gray-800 border border-red-500/60 text-white placeholder-gray-400 focus:ring-inset focus:ring-1 focus:ring-red-500' : 'bg-white border border-red-400 text-gray-900 placeholder-gray-400 focus:ring-inset focus:ring-1 focus:ring-red-400'
                          : isDarkMode ? 'bg-gray-800 border border-amber-500/20 text-white placeholder-gray-400 focus:ring-inset focus:ring-1 focus:ring-amber-500' : 'bg-white border border-gray-300 text-gray-900 placeholder-gray-400 focus:border-blue-500 focus:ring-inset focus:ring-1 focus:ring-blue-500/20'
                      }`}
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
                  {/* Real-time password mismatch message */}
                  {registerFormData.password_confirmation.length > 0 && !passwordsMatch && (
                    <p className="text-xs mt-1 text-red-400 flex items-center">
                      <XCircleIcon className="h-3 w-3 mr-1 flex-shrink-0" />
                      Passwords do not match
                    </p>
                  )}
                  {registerFormData.password_confirmation.length > 0 && passwordsMatch && (
                    <p className="text-xs mt-1 text-green-400 flex items-center">
                      <CheckCircleIcon className="h-3 w-3 mr-1 flex-shrink-0" />
                      Passwords match
                    </p>
                  )}
                </div>

                <div className="relative flex items-center">
                  <div className={`flex-grow border-t ${isDarkMode ? 'border-amber-500/20' : 'border-gray-200'}`}></div>
                  <span className={`flex-shrink mx-4 text-xs ${isDarkMode ? 'text-amber-100/50' : 'text-gray-500'}`}>or</span>
                  <div className={`flex-grow border-t ${isDarkMode ? 'border-amber-500/20' : 'border-gray-200'}`}></div>
                </div>

                <button
                  type="button"
                  onClick={() => { window.location.href = `${googleAuthUrl}?mode=register`; }}
                  className={`w-full px-4 py-2.5 rounded-lg focus:outline-none transition-all duration-200 font-medium text-sm flex items-center justify-center border ${isDarkMode ? 'border-gray-600 text-white bg-gray-700 hover:bg-gray-600' : 'border-gray-300 text-gray-700 bg-white hover:bg-gray-50'}`}
                >
                  <svg className="w-4 h-4 mr-2" viewBox="0 0 24 24" aria-hidden="true">
                    <path fill="currentColor" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                    <path fill="currentColor" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                    <path fill="currentColor" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
                    <path fill="currentColor" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
                  </svg>
                  Register with Google
                </button>

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

                {/* Privacy Policy & Terms Checkbox */}
                <div className="flex items-start gap-2 mt-1">
                  <input
                    type="checkbox"
                    id="agreeTermsStep3Auth"
                    checked={agreedToTermsStep3}
                    onChange={(e) => setAgreedToTermsStep3(e.target.checked)}
                    className={`mt-0.5 flex-shrink-0 ${isDarkMode ? 'w-3.5 h-3.5 text-amber-600 bg-gray-800 border-amber-500/30 rounded focus:ring-amber-500 focus:ring-1' : 'w-3.5 h-3.5 text-blue-600 bg-white border border-gray-300 rounded focus:ring-blue-500 focus:ring-1'}`}
                  />
                  <label htmlFor="agreeTermsStep3Auth" className={`text-xs leading-relaxed ${isDarkMode ? 'text-amber-100/70' : 'text-gray-600'}`}>
                    I have read and agree to the{' '}
                    <button
                      type="button"
                      onClick={() => { setTermsModalTab('privacy'); setShowTermsModal(true); }}
                      className={`font-medium underline transition-colors ${isDarkMode ? 'text-amber-400 hover:text-amber-300' : 'text-blue-600 hover:text-blue-500'}`}
                    >
                      Privacy Policy
                    </button>
                    {' '}and{' '}
                    <button
                      type="button"
                      onClick={() => { setTermsModalTab('terms'); setShowTermsModal(true); }}
                      className={`font-medium underline transition-colors ${isDarkMode ? 'text-amber-400 hover:text-amber-300' : 'text-blue-600 hover:text-blue-500'}`}
                    >
                      Terms &amp; Conditions
                    </button>
                  </label>
                </div>

                {/* Terms & Privacy Modal */}
                <TermsPrivacyModal
                  isOpen={showTermsModal}
                  onClose={() => setShowTermsModal(false)}
                  initialTab={termsModalTab}
                  isDarkMode={isDarkMode}
                />

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
                    disabled={registerLoading || !agreedToTermsStep3}
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
