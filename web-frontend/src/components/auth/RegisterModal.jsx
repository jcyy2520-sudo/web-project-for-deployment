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

const RegisterModal = ({ isOpen, onClose, onSwitchToLogin }) => {
  const [step, setStep] = useState(1);
  const [timeLeft, setTimeLeft] = useState(30);
  const [showPassword, setShowPassword] = useState(false);
  const [showConfirmPassword, setShowConfirmPassword] = useState(false);
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

  // API base path (relative) - keep relative so Vite proxy is used
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

  const validateStep1 = () => {
    if (formData.password !== formData.confirmPassword) {
      showNotification('Passwords do not match', 'error');
      return false;
    }
    
    // REMOVED: No special character requirement
    // REMOVED: No uppercase/lowercase requirement
    // ONLY KEEP: Minimum 8 characters
    if (formData.password.length < 8) {
      showNotification('Password must be at least 8 characters long', 'error');
      return false;
    }

    if (!formData.username.match(/^[a-zA-Z0-9_]+$/)) {
      showNotification('Username can only contain letters, numbers, and underscores', 'error');
      return false;
    }

    return true;
  };

  // We use `callApi` which ensures CSRF via `useApi.ensureCsrfToken()`

  const handleStep1 = async (e) => {
    e.preventDefault();
    
    if (!validateStep1()) return;
    const result = await callApi((signal) =>
      axios.post(`${API_BASE}/register-step1`, {
        username: formData.username,
        email: formData.email,
        password: formData.password,
        password_confirmation: formData.confirmPassword,
      }, { signal })
    );

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
    } else {
      showNotification(result.error || 'Registration failed', 'error');
    }
  };

  const handleResendCode = async () => {
    const result = await callApi((signal) =>
      axios.post(`${API_BASE}/resend-verification`, { email: formData.email }, { signal })
    );

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
    );

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
    );

    if (result.success) {
      showNotification('Registration successful! You can now sign in.', 'success');
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
    <Modal isOpen={isOpen} onClose={handleClose} title={getStepTitle()} size={step === 2 ? 'sm' : 'md'}>
      {/* Notification */}
      {notification.show && (
        <div className={`mb-4 p-3 rounded-lg border flex items-center space-x-2 animate-fadeIn ${
          notification.type === 'success' 
            ? 'bg-green-500/10 border-green-500/30 text-green-300' 
            : 'bg-red-500/10 border-red-500/30 text-red-300'
        }`}>
          {notification.type === 'success' ? (
            <CheckCircleIcon className="h-4 w-4 flex-shrink-0" />
          ) : (
            <XCircleIcon className="h-4 w-4 flex-shrink-0" />
          )}
          <span className="text-sm">{notification.message}</span>
        </div>
      )}

      {/* Progress Indicator */}
      <div className="mb-6">
        <div className="flex justify-between mb-3">
          {[1, 2, 3].map((stepNum) => (
            <div key={stepNum} className="flex flex-col items-center">
              <div
                className={`w-8 h-8 rounded-full flex items-center justify-center text-xs font-semibold transition-all duration-300 border ${
                  step >= stepNum
                    ? 'bg-gradient-to-br from-amber-500 to-amber-600 text-gray-900 shadow border-amber-500/30'
                    : 'bg-gray-800 text-gray-400 border-amber-500/20'
                }`}
              >
                {stepNum}
              </div>
              <div className="text-xs text-amber-100/70 mt-1">
                {stepNum === 1 && 'Account'}
                {stepNum === 2 && 'Verify'}
                {stepNum === 3 && 'Profile'}
              </div>
            </div>
          ))}
        </div>
        <div className="relative bg-gray-800 rounded-full h-1.5 mb-2 border border-amber-500/20">
          <div 
            className="absolute top-0 left-0 h-full bg-gradient-to-r from-amber-500 to-amber-600 rounded-full transition-all duration-500 ease-out"
            style={{ width: `${(step / 3) * 100}%` }}
          ></div>
        </div>
      </div>

      <form onSubmit={step === 1 ? handleStep1 : step === 2 ? handleStep2 : handleStep3}>
        {step === 1 && (
          <div className="space-y-4">
            <div>
              <label htmlFor="username" className="block text-xs font-medium text-amber-50 mb-1">
                Username *
              </label>
              <div className="relative">
                <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                  <UserIcon className="h-4 w-4 text-amber-400/70" />
                </div>
                <input
                  type="text"
                  id="username"
                  name="username"
                  value={formData.username}
                  onChange={handleChange}
                  className="w-full pl-10 pr-3 py-2 bg-gray-800 border border-amber-500/20 rounded-lg focus:outline-none focus:ring-1 focus:ring-amber-500 focus:border-transparent transition-all duration-200 text-sm text-white placeholder-gray-400"
                  required
                  placeholder="Enter username"
                  pattern="[a-zA-Z0-9_]+"
                  title="Letters, numbers, and underscores only"
                />
              </div>
            </div>

            <div>
              <label htmlFor="reg-email" className="block text-xs font-medium text-amber-50 mb-1">
                Email Address *
              </label>
              <div className="relative">
                <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                  <EnvelopeIcon className="h-4 w-4 text-amber-400/70" />
                </div>
                <input
                  type="email"
                  id="reg-email"
                  name="email"
                  value={formData.email}
                  onChange={handleChange}
                  className="w-full pl-10 pr-3 py-2 bg-gray-800 border border-amber-500/20 rounded-lg focus:outline-none focus:ring-1 focus:ring-amber-500 focus:border-transparent transition-all duration-200 text-sm text-white placeholder-gray-400"
                  required
                  placeholder="your@email.com"
                />
              </div>
            </div>

            <div>
              <label htmlFor="reg-password" className="block text-xs font-medium text-amber-50 mb-1">
                Password *
              </label>
              <div className="relative">
                <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                  <LockClosedIcon className="h-4 w-4 text-amber-400/70" />
                </div>
                <input
                  type={showPassword ? "text" : "password"}
                  id="reg-password"
                  name="password"
                  value={formData.password}
                  onChange={handleChange}
                  className="w-full pl-10 pr-10 py-2 bg-gray-800 border border-amber-500/20 rounded-lg focus:outline-none focus:ring-1 focus:ring-amber-500 focus:border-transparent transition-all duration-200 text-sm text-white placeholder-gray-400"
                  required
                  minLength="8"
                  placeholder="Enter password"
                />
                <button
                  type="button"
                  onClick={() => setShowPassword(!showPassword)}
                  className="absolute inset-y-0 right-0 pr-3 flex items-center text-amber-400/70 hover:text-amber-300 transition-colors"
                >
                  {showPassword ? <EyeSlashIcon className="h-4 w-4" /> : <EyeIcon className="h-4 w-4" />}
                </button>
              </div>
              {/* UPDATED: Simplified password requirements message */}
              <p className="text-xs text-amber-100/50 mt-1">Minimum 8 characters</p>
            </div>

            <div>
              <label htmlFor="reg-confirmPassword" className="block text-xs font-medium text-amber-50 mb-1">
                Confirm Password *
              </label>
              <div className="relative">
                <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                  <LockClosedIcon className="h-4 w-4 text-amber-400/70" />
                </div>
                <input
                  type={showConfirmPassword ? "text" : "password"}
                  id="reg-confirmPassword"
                  name="confirmPassword"
                  value={formData.confirmPassword}
                  onChange={handleChange}
                  className="w-full pl-10 pr-10 py-2 bg-gray-800 border border-amber-500/20 rounded-lg focus:outline-none focus:ring-1 focus:ring-amber-500 focus:border-transparent transition-all duration-200 text-sm text-white placeholder-gray-400"
                  required
                  placeholder="Confirm password"
                />
                <button
                  type="button"
                  onClick={() => setShowConfirmPassword(!showConfirmPassword)}
                  className="absolute inset-y-0 right-0 pr-3 flex items-center text-amber-400/70 hover:text-amber-300 transition-colors"
                >
                  {showConfirmPassword ? <EyeSlashIcon className="h-4 w-4" /> : <EyeIcon className="h-4 w-4" />}
                </button>
              </div>
            </div>
          </div>
        )}

        {step === 2 && (
          <div className="space-y-4">
            <div className="bg-amber-500/10 border border-amber-500/30 rounded-lg p-4">
              <p className="text-sm text-amber-200 font-medium">
                Verification code sent to:
              </p>
              <p className="text-sm text-amber-50 mt-1 font-semibold">{formData.email}</p>
              <div className="flex items-center mt-2 text-xs text-amber-200/80">
                <ClockIcon className="h-3 w-3 mr-1" />
                <span>Expires in: <span className="text-red-400 font-bold">{timeLeft} minutes</span></span>
              </div>
            </div>
              
            <div>
              <label htmlFor="verificationCode" className="block text-xs font-medium text-amber-50 mb-1">
                Verification Code *
              </label>
              <input
                type="text"
                id="verificationCode"
                name="verificationCode"
                value={formData.verificationCode}
                onChange={(e) => autoFormatVerificationCode(e.target.value)}
                className="w-full px-4 py-3 bg-gray-800 border border-amber-500/20 rounded-lg focus:outline-none focus:ring-1 focus:ring-amber-500 focus:border-transparent transition-all duration-200 text-white text-center text-base tracking-widest font-mono font-bold placeholder-gray-400"
                maxLength="6"
                required
                placeholder="000000"
                pattern="[0-9]{6}"
                inputMode="numeric"
              />
              <p className="text-xs text-amber-100/50 mt-1 text-center">Enter the 6-digit code from your email</p>
            </div>

            <div className="text-center">
              <button
                type="button"
                onClick={handleResendCode}
                className="text-xs font-medium text-amber-400 hover:text-amber-300 underline transition-colors"
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
                <label htmlFor="firstName" className="block text-xs font-medium text-amber-50 mb-1">
                  First Name *
                </label>
                <input
                  type="text"
                  id="firstName"
                  name="firstName"
                  value={formData.firstName}
                  onChange={handleChange}
                  className="w-full px-3 py-2 bg-gray-800 border border-amber-500/20 rounded-lg focus:outline-none focus:ring-1 focus:ring-amber-500 focus:border-transparent transition-all duration-200 text-sm text-white placeholder-gray-400"
                  required
                  placeholder="First name"
                />
              </div>

              <div>
                <label htmlFor="lastName" className="block text-xs font-medium text-amber-50 mb-1">
                  Last Name *
                </label>
                <input
                  type="text"
                  id="lastName"
                  name="lastName"
                  value={formData.lastName}
                  onChange={handleChange}
                  className="w-full px-3 py-2 bg-gray-800 border border-amber-500/20 rounded-lg focus:outline-none focus:ring-1 focus:ring-amber-500 focus:border-transparent transition-all duration-200 text-sm text-white placeholder-gray-400"
                  required
                  placeholder="Last name"
                />
              </div>
            </div>

            <div>
              <label htmlFor="phone" className="block text-xs font-medium text-amber-50 mb-1">
                Phone Number *
              </label>
              <div className="relative">
                <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                  <PhoneIcon className="h-4 w-4 text-amber-400/70" />
                </div>
                <input
                  type="tel"
                  id="phone"
                  name="phone"
                  value={formData.phone}
                  onChange={handleChange}
                  className="w-full pl-10 pr-3 py-2 bg-gray-800 border border-amber-500/20 rounded-lg focus:outline-none focus:ring-1 focus:ring-amber-500 focus:border-transparent transition-all duration-200 text-sm text-white placeholder-gray-400"
                  required
                  placeholder="Phone number"
                />
              </div>
            </div>

            <div>
              <label htmlFor="address" className="block text-xs font-medium text-amber-50 mb-1">
                Address *
              </label>
              <div className="relative">
                <div className="absolute top-3 left-3 flex items-start pointer-events-none">
                  <MapPinIcon className="h-4 w-4 text-amber-400/70 mt-0.5" />
                </div>
                <textarea
                  id="address"
                  name="address"
                  value={formData.address}
                  onChange={handleChange}
                  className="w-full pl-10 pr-3 py-2 bg-gray-800 border border-amber-500/20 rounded-lg focus:outline-none focus:ring-1 focus:ring-amber-500 focus:border-transparent transition-all duration-200 text-sm text-white placeholder-gray-400 resize-none"
                  rows="2"
                  required
                  placeholder="Complete address"
                />
              </div>
            </div>

            <div className="bg-amber-500/10 border border-amber-500/30 rounded-lg p-3">
              <h4 className="text-xs font-semibold text-amber-50 mb-2">Account Summary:</h4>
              <div className="text-xs text-amber-100/70 space-y-1">
                <p><strong>Username:</strong> {formData.username}</p>
                <p><strong>Email:</strong> {formData.email}</p>
                <p><strong>Name:</strong> {formData.firstName} {formData.lastName}</p>
              </div>
            </div>
          </div>
        )}

        <div className={`flex ${step === 1 ? 'justify-center' : 'justify-between'} mt-6`}>
          {step > 1 && (
            <button
              type="button"
              onClick={() => setStep(step - 1)}
              className="px-4 py-2 border border-amber-500/30 text-amber-100 rounded-lg hover:bg-amber-500/10 transition-all duration-200 font-medium text-sm"
              disabled={loading}
            >
              Back
            </button>
          )}
          
          <button
            type="submit"
            disabled={loading}
            className={`${step === 1 ? 'w-full' : ''} px-4 py-2 bg-gradient-to-r from-amber-500 to-amber-600 text-gray-900 rounded-lg hover:from-amber-600 hover:to-amber-700 focus:outline-none focus:ring-1 focus:ring-amber-500 transition-all duration-200 font-medium text-sm flex items-center justify-center min-w-[120px] border border-amber-500/30 disabled:opacity-50 disabled:cursor-not-allowed`}
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
          <div className="text-center text-xs text-amber-100/70 mt-4">
            Already have an account?{' '}
            <button
              type="button"
              onClick={() => {
                handleClose();
                onSwitchToLogin();
              }}
              className="font-medium text-amber-400 hover:text-amber-300 transition-colors"
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