import { useState, useEffect } from 'react';
import { useApi } from '../../hooks/useApi';
import axios from 'axios';
import Modal from '../Modal';
import LoadingSpinner from '../LoadingSpinner';
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
import TermsPrivacyModal from './TermsPrivacyModal';

const RegisterModal = ({ isOpen, onClose, onSwitchToLogin, isDarkMode = true }) => {
  // Light-mode palette is provided via CSS variables (see index.css :root)
  const [step, setStep] = useState(1);
  const [timeLeft, setTimeLeft] = useState(30);
  const [showPassword, setShowPassword] = useState(false);
  const [showConfirmPassword, setShowConfirmPassword] = useState(false);
  const [agreedToTerms, setAgreedToTerms] = useState(false);
  const [agreedToTermsStep3, setAgreedToTermsStep3] = useState(false);
  const [showTermsModal, setShowTermsModal] = useState(false);
  const [termsModalTab, setTermsModalTab] = useState('terms');
  const [notification, setNotification] = useState({ show: false, message: '', type: 'success' });
  const [formData, setFormData] = useState({
    username: '',
    email: '',
    password: '',
    confirmPassword: '',
    verificationCode: '',
    firstName: '',
    lastName: '',
    phone: '',
    address: '',
  });
  const { loading, error, callApi, clearError } = useApi();

  // API base path - /api is appended to axios baseURL
  const API_BASE = '/api';

  // Show notification function
  const showNotification = (message, type = 'success') => {
    setNotification({ show: true, message, type });
    setTimeout(() => {
      setNotification({ show: false, message: '', type: 'success' });
    }, 5000);
  };

  // Timer effect for step 2
  useEffect(() => {
    if (step === 2 && timeLeft > 0) {
      const timer = setTimeout(() => {
        setTimeLeft(timeLeft - 1);
      }, 60000);
      
      return () => clearTimeout(timer);
    } else if (step === 2 && timeLeft === 0) {
      showNotification('Verification code has expired. Please request a new one.', 'error');
      setStep(1);
    }
  }, [step, timeLeft]);

  // Reset timer when moving to step 2
  useEffect(() => {
    if (step === 2) {
      setTimeLeft(30);
    }
  }, [step]);

  const handleChange = (e) => {
    setFormData(prev => ({
      ...prev,
      [e.target.name]: e.target.value
    }));
    if (error) clearError();
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

  const passwordStrength = getPasswordStrength(formData.password);
  const passwordsMatch = formData.confirmPassword.length === 0 || formData.password === formData.confirmPassword;

  const validateStep1 = () => {
    if (formData.password.length < 8) {
      showNotification('Password must be at least 8 characters long', 'error');
      return false;
    }

    if (formData.password !== formData.confirmPassword) {
      showNotification('Passwords do not match', 'error');
      return false;
    }

    if (formData.username.length < 3) {
      showNotification('Username must be at least 3 characters', 'error');
      return false;
    }

    if (formData.username.length > 50) {
      showNotification('Username must be at most 50 characters', 'error');
      return false;
    }

    if (!agreedToTerms) {
      showNotification('You must agree to the Terms & Conditions and Privacy Policy', 'error');
      return false;
    }

    return true;
  };

  // We use `callApi` which ensures CSRF via `useApi.ensureCsrfToken()`

  const handleStep1 = async (e) => {
    e.preventDefault();
    
    if (!validateStep1()) return;
    
    console.log('📝 Attempting registration step 1...');
    console.log('📍 API_BASE:', API_BASE);
    console.log('📧 Form data:', { username: formData.username, email: formData.email });
    
    const result = await callApi((signal) =>
      axios.post(`${API_BASE}/register-step1`, {
        username: formData.username,
        email: formData.email,
        password: formData.password,
        password_confirmation: formData.confirmPassword,
      }, { signal })
    , { requireAuth: false }); // Public endpoint, no CSRF needed

    console.log('📦 Registration result:', result);

    if (result.success) {
      setStep(2);
      showNotification('Verification code sent to your email!', 'success');
    } else if (result.status === 422 && result.data) {
      const data = result.data;
      if (data?.errors && typeof data.errors === 'object') {
        const errorMessages = Object.entries(data.errors)
          .map(([field, messages]) => Array.isArray(messages) && messages.length > 0 ? messages[0] : messages)
          .filter(Boolean)
          .join('\n');
        showNotification(errorMessages || 'Validation failed', 'error');
      } else {
        showNotification(result.error || 'Validation failed', 'error');
      }
    } else if (result.status === 500) {
      // Handle server errors specifically
      showNotification(result.error || 'Server error. Please try again later.', 'error');
    } else {
      showNotification(result.error || 'Registration failed', 'error');
    }
  };

  const handleResendCode = async () => {
    const result = await callApi((signal) =>
      axios.post(`${API_BASE}/resend-verification`, { email: formData.email }, { signal })
    , { requireAuth: false });

    if (result.success) {
      setTimeLeft(30);
      showNotification('New verification code sent!', 'success');
    } else {
      showNotification(result.error || 'Failed to resend code. Please try again.', 'error');
    }
  };

  const handleStep2 = async (e) => {
    e.preventDefault();
    
    if (formData.verificationCode.length !== 6) {
      showNotification('Please enter a valid 6-digit verification code', 'error');
      return;
    }
    const result = await callApi((signal) =>
      axios.post(`${API_BASE}/verify-code`, { email: formData.email, code: formData.verificationCode }, { signal })
    , { requireAuth: false });

    if (result.success && result.data?.verified) {
      setStep(3);
      showNotification('Email verified successfully!', 'success');
    } else if (result.status === 422 && result.data) {
      showNotification(result.data?.message || result.error || 'Invalid or expired verification code', 'error');
    } else {
      showNotification(result.error || 'Verification failed', 'error');
    }
  };

  const handleStep3 = async (e) => {
    e.preventDefault();
    
    if (!formData.firstName.trim() || !formData.lastName.trim() || !formData.phone.trim() || !formData.address.trim()) {
      showNotification('Please fill in all required fields', 'error');
      return;
    }
    if (!agreedToTermsStep3) {
      showNotification('Please agree to the Privacy Policy and Terms & Conditions to proceed', 'error');
      return;
    }
    const result = await callApi((signal) =>
      axios.post(`${API_BASE}/complete-registration`, {
        username: formData.username,
        email: formData.email,
        password: formData.password,
        first_name: formData.firstName,
        last_name: formData.lastName,
        phone: formData.phone,
        address: formData.address,
      }, { signal })
    , { requireAuth: false });

    if (result.success) {
      showNotification('Registration submitted. Check your email and click It is me to activate your login.', 'success');
      // Reset form and close modal after success
      setTimeout(() => {
        setStep(1);
        setFormData({
          username: '',
          email: '',
          password: '',
          confirmPassword: '',
          verificationCode: '',
          firstName: '',
          lastName: '',
          phone: '',
          address: '',
        });
        onClose();
        onSwitchToLogin();
      }, 2000);
    } else if (result.status === 422 && result.data) {
      const data = result.data;
      if (data?.errors && typeof data.errors === 'object') {
        const errorMessages = Object.entries(data.errors)
          .map(([field, messages]) => Array.isArray(messages) && messages.length > 0 ? messages[0] : messages)
          .filter(Boolean)
          .join('\n');
        showNotification(errorMessages || 'Validation failed', 'error');
      } else if (data?.message?.includes('already registered')) {
        showNotification('This email is already registered. Please sign in instead.', 'error');
        setTimeout(() => {
          handleClose();
          onSwitchToLogin();
        }, 2000);
      } else if (data?.message?.includes('verification')) {
        showNotification('Email verification required. Please restart the registration process.', 'error');
        setStep(1);
      } else {
        showNotification(result.error || 'Registration failed', 'error');
      }
    } else {
      showNotification(result.error || 'Registration failed. Please try again.', 'error');
    }
  };

  const getStepTitle = () => {
    switch (step) {
      case 1: return 'Create Account';
      case 2: return 'Verify Email';
      case 3: return 'Complete Profile';
      default: return 'Create Account';
    }
  };

  const handleClose = () => {
    setStep(1);
    setFormData({
      username: '',
      email: '',
      password: '',
      confirmPassword: '',
      verificationCode: '',
      firstName: '',
      lastName: '',
      phone: '',
      address: '',
    });
    setTimeLeft(30);
    setShowPassword(false);
    setShowConfirmPassword(false);
    setAgreedToTerms(false);
    setAgreedToTermsStep3(false);
    clearError();
    setNotification({ show: false, message: '', type: 'success' });
    onClose();
  };

  const autoFormatVerificationCode = (value) => {
    // Auto-format the verification code input
    const numericValue = value.replace(/\D/g, '');
    if (numericValue.length <= 6) {
      setFormData(prev => ({ ...prev, verificationCode: numericValue }));
    }
  };

  return (
    <Modal isOpen={isOpen} onClose={handleClose} title={getStepTitle()} size={step === 2 ? 'sm' : 'md'} isDarkMode={isDarkMode}>
      {/* Notification */}
      {notification.show && (
        <div className={`mb-4 p-3 rounded-lg border flex items-center space-x-2 animate-fadeIn ${notification.type === 'success' ? (isDarkMode ? 'bg-green-500/10 border-green-500/30 text-green-300' : 'bg-green-50 border-green-100 text-green-700') : (isDarkMode ? 'bg-red-500/10 border-red-500/30 text-red-300' : 'bg-red-50 border-red-100 text-red-700')}`}>
          {notification.type === 'success' ? (
            <CheckCircleIcon className="h-4 w-4 flex-shrink-0" />
          ) : (
            <XCircleIcon className="h-4 w-4 flex-shrink-0" />
          )}
          <span className={`text-sm ${isDarkMode ? '' : 'text-gray-800'}`}>{notification.message}</span>
        </div>
      )}

      {/* Progress Indicator */}
      <div className="mb-6">
        <div className="flex justify-between mb-3">
          {[1, 2, 3].map((stepNum) => (
            <div key={stepNum} className="flex flex-col items-center">
              <div
                className={`w-8 h-8 rounded-full flex items-center justify-center text-xs font-semibold transition-all duration-300 border ${step >= stepNum ? (isDarkMode ? 'bg-gradient-to-br from-amber-500 to-amber-600 text-gray-900 shadow border-amber-500/30' : 'bg-gradient-to-br text-white shadow border border-gray-200') : (isDarkMode ? 'bg-gray-800 text-gray-400 border-amber-500/20' : 'bg-white text-gray-500 border border-gray-200')}`}
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
        <div className={`${isDarkMode ? 'relative bg-gray-800 rounded-full h-1.5 mb-2 border border-amber-500/20' : 'relative bg-gray-200 rounded-full h-1.5 mb-2 border border-gray-200'}`}>
          <div 
            className={`absolute top-0 left-0 h-full rounded-full transition-all duration-500 ease-out ${isDarkMode ? 'bg-gradient-to-r from-amber-500 to-amber-600' : ''}`}
            style={isDarkMode ? { width: `${(step / 3) * 100}%` } : { width: `${(step / 3) * 100}%`, backgroundImage: 'linear-gradient(90deg, var(--primary) 0%, var(--secondary) 100%)' }}
          ></div>
        </div>
      </div>

      <form onSubmit={step === 1 ? handleStep1 : step === 2 ? handleStep2 : handleStep3}>
        {step === 1 && (
          <div className="space-y-4">
            <div>
              <label htmlFor="username" className={`block text-xs font-medium mb-1 ${isDarkMode ? 'text-amber-50' : 'text-gray-800'}`}>
                Username *
              </label>
              <div className="relative">
                <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                  <UserIcon className={`h-4 w-4 ${isDarkMode ? 'text-amber-400/70' : 'text-gray-400'}`} />
                </div>
                <input
                  type="text"
                  id="username"
                  name="username"
                  value={formData.username}
                  onChange={handleChange}
                  className={`w-full pl-10 pr-3 py-2 rounded-lg focus:outline-none focus:border-transparent transition-all duration-200 text-sm placeholder-gray-400 ${isDarkMode ? 'bg-gray-800 border border-amber-500/20 text-white focus:ring-1 focus:ring-amber-500' : 'bg-white border border-gray-200 text-gray-900 focus:ring-1 focus:ring-blue-500'}`}
                  required
                  placeholder="Enter username"
                  maxLength={50}
                  title="Choose a username (3-50 characters)"
                />
              </div>
            </div>

            <div>
              <label htmlFor="reg-email" className={`block text-xs font-medium mb-1 ${isDarkMode ? 'text-amber-50' : 'text-gray-800'}`}>
                Email Address *
              </label>
              <div className="relative">
                <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                  <EnvelopeIcon className={`h-4 w-4 ${isDarkMode ? 'text-amber-400/70' : 'text-gray-400'}`} />
                </div>
                <input
                  type="email"
                  id="reg-email"
                  name="email"
                  value={formData.email}
                  onChange={handleChange}
                  className={`w-full pl-10 pr-3 py-2 rounded-lg focus:outline-none focus:border-transparent transition-all duration-200 text-sm placeholder-gray-400 ${isDarkMode ? 'bg-gray-800 border border-amber-500/20 text-white focus:ring-1 focus:ring-amber-500' : 'bg-white border border-gray-200 text-gray-900 focus:ring-1 focus:ring-blue-500'}`}
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
                  <LockClosedIcon className={`h-4 w-4 ${isDarkMode ? 'text-amber-400/70' : 'text-gray-400'}`} />
                </div>
                <input
                  type={showPassword ? "text" : "password"}
                  id="reg-password"
                  name="password"
                  value={formData.password}
                  onChange={handleChange}
                  className={`w-full pl-10 pr-10 py-2 rounded-lg focus:outline-none focus:border-transparent transition-all duration-200 text-sm placeholder-gray-400 ${isDarkMode ? 'bg-gray-800 border border-amber-500/20 text-white focus:ring-1 focus:ring-amber-500' : 'bg-white border border-gray-200 text-gray-900 focus:ring-1 focus:ring-blue-500'}`}
                  required
                  minLength="8"
                  placeholder="Enter password"
                />
                <button
                  type="button"
                  onClick={() => setShowPassword(!showPassword)}
                  className={`absolute inset-y-0 right-0 pr-3 flex items-center ${isDarkMode ? 'text-amber-400/70 hover:text-amber-300' : 'text-gray-500 hover:text-gray-700'} transition-colors`}
                >
                  {showPassword ? <EyeSlashIcon className="h-4 w-4" /> : <EyeIcon className="h-4 w-4" />}
                </button>
              </div>
              {/* Password Strength Indicator */}
              {formData.password.length > 0 && (
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
              {formData.password.length === 0 && (
                <p className={`text-xs mt-1 ${isDarkMode ? 'text-amber-100/50' : 'text-gray-600'}`}>Minimum 8 characters</p>
              )}
            </div>

            <div>
              <label htmlFor="reg-confirmPassword" className={`block text-xs font-medium mb-1 ${isDarkMode ? 'text-amber-50' : 'text-gray-800'}`}>
                Confirm Password *
              </label>
              <div className="relative">
                <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                  <LockClosedIcon className={`h-4 w-4 ${isDarkMode ? 'text-amber-400/70' : 'text-gray-400'}`} />
                </div>
                <input
                  type={showConfirmPassword ? "text" : "password"}
                  id="reg-confirmPassword"
                  name="confirmPassword"
                  value={formData.confirmPassword}
                  onChange={handleChange}
                  className={`w-full pl-10 pr-10 py-2 rounded-lg focus:outline-none focus:border-transparent transition-all duration-200 text-sm placeholder-gray-400 ${isDarkMode ? 'bg-gray-800 border border-amber-500/20 text-white focus:ring-1 focus:ring-amber-500' : 'bg-white border border-gray-200 text-gray-900 focus:ring-1 focus:ring-blue-500'}`}
                  required
                  placeholder="Confirm password"
                />
                <button
                  type="button"
                  onClick={() => setShowConfirmPassword(!showConfirmPassword)}
                  className={`absolute inset-y-0 right-0 pr-3 flex items-center ${isDarkMode ? 'text-amber-400/70 hover:text-amber-300' : 'text-gray-500 hover:text-gray-700'} transition-colors`}
                >
                  {showConfirmPassword ? <EyeSlashIcon className="h-4 w-4" /> : <EyeIcon className="h-4 w-4" />}
                </button>
              </div>
              {/* Real-time password mismatch message */}
              {formData.confirmPassword.length > 0 && !passwordsMatch && (
                <p className="text-xs mt-1 text-red-400 flex items-center">
                  <XCircleIcon className="h-3 w-3 mr-1 flex-shrink-0" />
                  Passwords do not match
                </p>
              )}
              {formData.confirmPassword.length > 0 && passwordsMatch && (
                <p className="text-xs mt-1 text-green-400 flex items-center">
                  <CheckCircleIcon className="h-3 w-3 mr-1 flex-shrink-0" />
                  Passwords match
                </p>
              )}
            </div>

            {/* Terms & Conditions Checkbox */}
            <div className="flex items-start gap-2 mt-1">
              <input
                type="checkbox"
                id="agreeTerms"
                checked={agreedToTerms}
                onChange={(e) => setAgreedToTerms(e.target.checked)}
                className={`mt-0.5 flex-shrink-0 ${isDarkMode ? 'w-3.5 h-3.5 text-amber-600 bg-gray-800 border-amber-500/30 rounded focus:ring-amber-500 focus:ring-1' : 'w-3.5 h-3.5 text-blue-600 bg-white border border-gray-300 rounded focus:ring-blue-500 focus:ring-1'}`}
              />
              <label htmlFor="agreeTerms" className={`text-xs leading-relaxed ${isDarkMode ? 'text-amber-100/70' : 'text-gray-600'}`}>
                I agree to the{' '}
                <button
                  type="button"
                  onClick={() => { setTermsModalTab('terms'); setShowTermsModal(true); }}
                  className={`font-medium underline transition-colors ${isDarkMode ? 'text-amber-400 hover:text-amber-300' : 'text-blue-600 hover:text-blue-500'}`}
                >
                  Terms &amp; Conditions
                </button>
                {' '}and{' '}
                <button
                  type="button"
                  onClick={() => { setTermsModalTab('privacy'); setShowTermsModal(true); }}
                  className={`font-medium underline transition-colors ${isDarkMode ? 'text-amber-400 hover:text-amber-300' : 'text-blue-600 hover:text-blue-500'}`}
                >
                  Privacy Policy
                </button>
              </label>
            </div>

            {/* Divider */}
            <div className="relative flex items-center my-4">
              <div className={`flex-grow border-t ${isDarkMode ? 'border-amber-500/20' : 'border-gray-200'}`}></div>
              <span className={`flex-shrink mx-4 text-xs ${isDarkMode ? 'text-amber-100/50' : 'text-gray-500'}`}>or</span>
              <div className={`flex-grow border-t ${isDarkMode ? 'border-amber-500/20' : 'border-gray-200'}`}></div>
            </div>

            {/* Google Sign In Button */}
            <button
              type="button"
              onClick={() => {
                window.location.href = 'http://localhost:8000/auth/google/redirect';
              }}
              className={`w-full px-4 py-2 rounded-lg transition-all duration-200 font-medium text-sm flex items-center justify-center border shadow ${isDarkMode ? 'border border-gray-600 text-white bg-gray-700 hover:bg-gray-600 focus:ring-1 focus:ring-gray-500' : 'border border-gray-300 text-gray-700 bg-white hover:bg-gray-50 focus:ring-1 focus:ring-gray-300'}`}
            >
              <svg className="w-4 h-4 mr-2" viewBox="0 0 24 24">
                <path fill="currentColor" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                <path fill="currentColor" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                <path fill="currentColor" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
                <path fill="currentColor" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
              </svg>
              Register with Google
            </button>
          </div>
        )}

        {/* Terms & Privacy Modal */}
        <TermsPrivacyModal
          isOpen={showTermsModal}
          onClose={() => setShowTermsModal(false)}
          initialTab={termsModalTab}
          isDarkMode={isDarkMode}
        />

        {step === 2 && (
          <div className="space-y-4">
            <div className={isDarkMode ? 'bg-amber-500/10 border border-amber-500/30 rounded-lg p-4' : 'bg-white border border-gray-200 rounded-lg p-4'}>
              <p className={`text-sm font-medium ${isDarkMode ? 'text-amber-200' : 'text-gray-800'}`}>
                Verification code sent to:
              </p>
              <p className={`text-sm mt-1 font-semibold ${isDarkMode ? 'text-amber-50' : 'text-gray-900'}`}>{formData.email}</p>
              <div className={`flex items-center mt-2 text-xs ${isDarkMode ? 'text-amber-200/80' : 'text-gray-600'}`}>
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
                value={formData.verificationCode}
                onChange={(e) => autoFormatVerificationCode(e.target.value)}
                className={`w-full px-4 py-3 rounded-lg focus:outline-none focus:border-transparent transition-all duration-200 text-center text-base tracking-widest font-mono font-bold placeholder-gray-400 ${isDarkMode ? 'bg-gray-800 border border-amber-500/20 text-white focus:ring-1 focus:ring-amber-500' : 'bg-white border border-gray-200 text-gray-900'}`}
                maxLength="6"
                required
                placeholder="000000"
                pattern="[0-9]{6}"
                inputMode="numeric"
              />
              <p className={`text-xs mt-1 text-center ${isDarkMode ? 'text-amber-100/50' : 'text-gray-600'}`}>Enter the 6-digit code from your email</p>
            </div>

            <div className="text-center">
              <button
                type="button"
                onClick={handleResendCode}
                className={`text-xs font-medium underline transition-colors ${isDarkMode ? 'text-amber-400 hover:text-amber-300' : 'text-[#1E3A8A] hover:opacity-90'}`}
                disabled={loading}
              >
                Didn't receive code? Resend
              </button>
            </div>
          </div>
        )}

        {step === 3 && (
          <div className="space-y-4">
            <div className="grid grid-cols-2 gap-3">
              <div>
                <label htmlFor="firstName" className={`block text-xs font-medium mb-1 ${isDarkMode ? 'text-amber-50' : 'text-gray-800'}`}>
                  First Name *
                </label>
                <input
                  type="text"
                  id="firstName"
                  name="firstName"
                  value={formData.firstName}
                  onChange={handleChange}
                  className={`w-full px-3 py-2 rounded-lg focus:outline-none focus:border-transparent transition-all duration-200 text-sm placeholder-gray-400 ${isDarkMode ? 'bg-gray-800 border border-amber-500/20 text-white focus:ring-1 focus:ring-amber-500' : 'bg-white border border-gray-200 text-gray-900 focus:ring-1 focus:ring-blue-500'}`}
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
                  value={formData.lastName}
                  onChange={handleChange}
                  className={`w-full px-3 py-2 rounded-lg focus:outline-none focus:border-transparent transition-all duration-200 text-sm placeholder-gray-400 ${isDarkMode ? 'bg-gray-800 border border-amber-500/20 text-white focus:ring-1 focus:ring-amber-500' : 'bg-white border border-gray-200 text-gray-900 focus:ring-1 focus:ring-blue-500'}`}
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
                  <PhoneIcon className={`h-4 w-4 ${isDarkMode ? 'text-amber-400/70' : 'text-gray-400'}`} />
                </div>
                <input
                  type="tel"
                  id="phone"
                  name="phone"
                  value={formData.phone}
                  onChange={handleChange}
                  className={`w-full pl-10 pr-3 py-2 rounded-lg focus:outline-none focus:border-transparent transition-all duration-200 text-sm placeholder-gray-400 ${isDarkMode ? 'bg-gray-800 border border-amber-500/20 text-white focus:ring-1 focus:ring-amber-500' : 'bg-white border border-gray-200 text-gray-900 focus:ring-1 focus:ring-blue-500'}`}
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
                  <MapPinIcon className={`h-4 w-4 ${isDarkMode ? 'text-amber-400/70' : 'text-gray-400'} mt-0.5`} />
                </div>
                <textarea
                  id="address"
                  name="address"
                  value={formData.address}
                  onChange={handleChange}
                  className={`w-full pl-10 pr-3 py-2 rounded-lg focus:outline-none focus:border-transparent transition-all duration-200 text-sm placeholder-gray-400 resize-none ${isDarkMode ? 'bg-gray-800 border border-amber-500/20 text-white focus:ring-1 focus:ring-amber-500' : 'bg-white border border-gray-200 text-gray-900 focus:ring-1 focus:ring-blue-500'}`}
                  rows="2"
                  required
                  placeholder="Complete address"
                />
              </div>
            </div>

            <div className={isDarkMode ? 'bg-amber-500/10 border border-amber-500/30 rounded-lg p-3' : 'bg-white border border-gray-200 rounded-lg p-3'}>
              <h4 className={`text-xs font-semibold mb-2 ${isDarkMode ? 'text-amber-50' : 'text-gray-800'}`}>Account Summary:</h4>
              <div className={`text-xs space-y-1 ${isDarkMode ? 'text-amber-100/70' : 'text-gray-700'}`}>
                <p><strong>Username:</strong> {formData.username}</p>
                <p><strong>Email:</strong> {formData.email}</p>
                <p><strong>Name:</strong> {formData.firstName} {formData.lastName}</p>
              </div>
            </div>

            {/* Privacy Policy & Terms Checkbox */}
            <div className="flex items-start gap-2 mt-1">
              <input
                type="checkbox"
                id="agreeTermsStep3"
                checked={agreedToTermsStep3}
                onChange={(e) => setAgreedToTermsStep3(e.target.checked)}
                className={`mt-0.5 flex-shrink-0 ${isDarkMode ? 'w-3.5 h-3.5 text-amber-600 bg-gray-800 border-amber-500/30 rounded focus:ring-amber-500 focus:ring-1' : 'w-3.5 h-3.5 text-blue-600 bg-white border border-gray-300 rounded focus:ring-blue-500 focus:ring-1'}`}
              />
              <label htmlFor="agreeTermsStep3" className={`text-xs leading-relaxed ${isDarkMode ? 'text-amber-100/70' : 'text-gray-600'}`}>
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
          </div>
        )}

        <div className={`flex ${step === 1 ? 'justify-center' : 'justify-between'} mt-6`}>
          {step > 1 && (
            <button
              type="button"
              onClick={() => setStep(step - 1)}
              className={`px-4 py-2 border ${isDarkMode ? 'border-amber-500/30 text-amber-100 hover:bg-amber-500/10' : 'border border-gray-200 text-gray-700 hover:bg-gray-50'} rounded-lg transition-all duration-200 font-medium text-sm`}
              disabled={loading}
            >
              Back
            </button>
          )}
          
          <button
            type="submit"
            disabled={loading || (step === 1 && !agreedToTerms) || (step === 3 && !agreedToTermsStep3)}
            className={`${step === 1 ? 'w-full' : ''} px-4 py-2 rounded-lg focus:outline-none transition-all duration-200 font-medium text-sm flex items-center justify-center min-w-[120px] disabled:opacity-50 disabled:cursor-not-allowed ${isDarkMode ? 'text-gray-900 border border-amber-500/30 bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-600 hover:to-amber-700 focus:ring-1 focus:ring-amber-500' : 'text-white border border-gray-200'}`}
            style={isDarkMode ? {} : { backgroundImage: 'linear-gradient(90deg, var(--primary) 0%, var(--secondary) 100%)' }}
          >
            {loading ? (
              <LoadingSpinner size="sm" />
            ) : step === 3 ? (
              'Complete Registration'
            ) : (
              'Continue'
            )}
          </button>
        </div>

        {step === 1 && (
          <div className={`text-center text-xs mt-4 ${isDarkMode ? 'text-amber-100/70' : 'text-gray-700'}`}>
            Already have an account?{' '}
            <button
              type="button"
              onClick={() => {
                handleClose();
                onSwitchToLogin();
              }}
              className={`font-medium transition-colors ${isDarkMode ? 'text-amber-400 hover:text-amber-300' : 'text-[#1E3A8A] hover:opacity-90'}`}
            >
              Sign in
            </button>
          </div>
        )}
      </form>
    </Modal>
  );
};

export default RegisterModal;