import React, { useState, useEffect } from 'react';
import { useParams } from 'react-router-dom';
import axios from 'axios';
import {
  ExclamationTriangleIcon,
  CheckCircleIcon,
  XCircleIcon,
  ClockIcon,
  PaperAirplaneIcon,
} from '@heroicons/react/24/outline';

const AppealPage = () => {
  const { token } = useParams();
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [appealData, setAppealData] = useState(null);
  const [alreadySubmitted, setAlreadySubmitted] = useState(false);
  const [submittedStatus, setSubmittedStatus] = useState('');

  // Form state
  const [category, setCategory] = useState('');
  const [message, setMessage] = useState('');
  const [submitting, setSubmitting] = useState(false);
  const [submitError, setSubmitError] = useState('');
  const [submitSuccess, setSubmitSuccess] = useState(false);

  // Clear any stale auth on mount — user's account is actioned, they shouldn't be logged in
  useEffect(() => {
    localStorage.removeItem('token');
    localStorage.removeItem('user');
    delete axios.defaults.headers.common['Authorization'];
  }, []);

  useEffect(() => {
    verifyToken();
  }, [token]);

  const verifyToken = async () => {
    try {
      setLoading(true);
      setError('');
      const res = await axios.get(`/api/appeals/verify/${token}`);
      if (res.data.success) {
        if (res.data.data.already_submitted) {
          setAlreadySubmitted(true);
          setSubmittedStatus(res.data.data.status);
          setAppealData(res.data.data);
        } else {
          setAppealData(res.data.data);
        }
      }
    } catch (err) {
      setError(err.response?.data?.message || 'Invalid or expired appeal link.');
    } finally {
      setLoading(false);
    }
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    if (!category || message.length < 20) {
      setSubmitError('Please select a category and provide a detailed message (at least 20 characters).');
      return;
    }

    try {
      setSubmitting(true);
      setSubmitError('');
      const res = await axios.post(`/api/appeals/submit/${token}`, {
        appeal_category: category,
        appeal_message: message,
      });
      if (res.data.success) {
        setSubmitSuccess(true);
      }
    } catch (err) {
      setSubmitError(err.response?.data?.message || 'Failed to submit appeal.');
    } finally {
      setSubmitting(false);
    }
  };

  const actionLabels = {
    deleted: 'Deleted',
    blocked: 'Blocked',
    deactivated: 'Deactivated',
  };

  const actionColors = {
    deleted: 'text-red-600',
    blocked: 'text-orange-600',
    deactivated: 'text-yellow-600',
  };

  if (loading) {
    return (
      <div className="min-h-screen bg-gray-50 flex items-center justify-center">
        <div className="text-center">
          <div className="w-10 h-10 rounded-full border-4 border-amber-200 border-t-amber-600 animate-spin mx-auto mb-4"></div>
          <p className="text-gray-600">Verifying your appeal link...</p>
        </div>
      </div>
    );
  }

  if (error) {
    return (
      <div className="min-h-screen bg-gray-50 flex items-center justify-center p-4">
        <div className="bg-white rounded-xl shadow-lg max-w-md w-full p-8 text-center">
          <XCircleIcon className="w-16 h-16 text-red-400 mx-auto mb-4" />
          <h2 className="text-xl font-bold text-gray-900 mb-2">Invalid Appeal Link</h2>
          <p className="text-gray-600">{error}</p>
        </div>
      </div>
    );
  }

  if (submitSuccess) {
    return (
      <div className="min-h-screen bg-gray-50 flex items-center justify-center p-4">
        <div className="bg-white rounded-xl shadow-lg max-w-md w-full p-8 text-center">
          <CheckCircleIcon className="w-16 h-16 text-green-500 mx-auto mb-4" />
          <h2 className="text-xl font-bold text-gray-900 mb-2">Appeal Submitted</h2>
          <p className="text-gray-600">
            Your appeal has been submitted successfully. You will receive an email notification once it has been reviewed by an administrator.
          </p>
        </div>
      </div>
    );
  }

  if (alreadySubmitted) {
    return (
      <div className="min-h-screen bg-gray-50 flex items-center justify-center p-4">
        <div className="bg-white rounded-xl shadow-lg max-w-md w-full p-8 text-center">
          <ClockIcon className="w-16 h-16 text-blue-400 mx-auto mb-4" />
          <h2 className="text-xl font-bold text-gray-900 mb-2">Appeal Already Submitted</h2>
          <p className="text-gray-600 mb-4">
            You have already submitted an appeal for this account action.
          </p>
          <div className={`inline-flex items-center gap-2 px-4 py-2 rounded-full text-sm font-medium ${
            submittedStatus === 'pending' ? 'bg-yellow-100 text-yellow-700' :
            submittedStatus === 'approved' ? 'bg-green-100 text-green-700' :
            'bg-red-100 text-red-700'
          }`}>
            {submittedStatus === 'pending' && <ClockIcon className="w-4 h-4" />}
            {submittedStatus === 'approved' && <CheckCircleIcon className="w-4 h-4" />}
            {submittedStatus === 'rejected' && <XCircleIcon className="w-4 h-4" />}
            Status: {submittedStatus.charAt(0).toUpperCase() + submittedStatus.slice(1)}
          </div>
        </div>
      </div>
    );
  }

  return (
    <div className="min-h-screen bg-gray-50 py-8 px-4">
      <div className="max-w-lg mx-auto">
        {/* Header */}
        <div className="text-center mb-6">
          <h1 className="text-2xl font-bold text-gray-900">Legal Ease</h1>
          <p className="text-gray-500 text-sm mt-1">Account Appeal Form</p>
        </div>

        {/* Appeal Info Card */}
        <div className="bg-white rounded-xl shadow-lg overflow-hidden mb-6">
          <div className="bg-red-50 px-6 py-4 border-b border-red-200">
            <div className="flex items-center gap-3">
              <ExclamationTriangleIcon className="w-8 h-8 text-red-500 flex-shrink-0" />
              <div>
                <h2 className="text-lg font-semibold text-gray-900">Account Action Notice</h2>
                <p className="text-sm text-gray-600">
                  Your account has been <span className={`font-semibold ${actionColors[appealData?.action_type] || 'text-gray-900'}`}>
                    {actionLabels[appealData?.action_type]?.toLowerCase() || 'affected'}
                  </span>
                </p>
              </div>
            </div>
          </div>

          <div className="px-6 py-4 space-y-3">
            <div>
              <span className="text-xs font-medium text-gray-500 uppercase">Account</span>
              <p className="text-sm text-gray-900">{appealData?.user_name} ({appealData?.user_email})</p>
            </div>
            <div>
              <span className="text-xs font-medium text-gray-500 uppercase">Reason</span>
              <p className="text-sm text-gray-700 bg-gray-50 rounded-lg p-3 mt-1">{appealData?.action_reason}</p>
            </div>
          </div>
        </div>

        {/* Appeal Form */}
        <div className="bg-white rounded-xl shadow-lg overflow-hidden">
          <div className="px-6 py-4 border-b border-gray-200">
            <h3 className="text-lg font-semibold text-gray-900">Submit Your Appeal</h3>
            <p className="text-sm text-gray-500 mt-1">Please provide details about why you believe this action should be reconsidered.</p>
          </div>

          <form onSubmit={handleSubmit} className="px-6 py-5 space-y-5">
            {/* Category */}
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">
                Appeal Category <span className="text-red-500">*</span>
              </label>
              <div className="space-y-2">
                {appealData?.categories && Object.entries(appealData.categories).map(([key, label]) => (
                  <label
                    key={key}
                    className={`flex items-center gap-3 p-3 rounded-lg border cursor-pointer transition-all ${
                      category === key
                        ? 'border-amber-500 bg-amber-50 ring-1 ring-amber-500'
                        : 'border-gray-200 hover:border-gray-300'
                    }`}
                  >
                    <input
                      type="radio"
                      name="category"
                      value={key}
                      checked={category === key}
                      onChange={e => setCategory(e.target.value)}
                      className="accent-amber-600"
                    />
                    <span className="text-sm text-gray-700">{label}</span>
                  </label>
                ))}
              </div>
            </div>

            {/* Message */}
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1.5">
                Your Message <span className="text-red-500">*</span>
              </label>
              <textarea
                value={message}
                onChange={e => setMessage(e.target.value)}
                placeholder="Please explain in detail why you believe this action should be reconsidered... (minimum 20 characters)"
                rows={5}
                className="w-full px-3 py-2 rounded-lg border border-gray-300 bg-white text-gray-900 text-sm focus:ring-2 focus:ring-amber-500 focus:border-amber-500 outline-none resize-vertical"
              />
              <p className="text-xs text-gray-500 mt-1">{message.length}/2000 characters</p>
            </div>

            {/* Error */}
            {submitError && (
              <div className="p-3 rounded-lg bg-red-50 border border-red-200">
                <p className="text-sm text-red-600">{submitError}</p>
              </div>
            )}

            {/* Submit */}
            <button
              type="submit"
              disabled={!category || message.length < 20 || submitting}
              className={`w-full py-3 rounded-lg text-sm font-semibold text-white transition-colors flex items-center justify-center gap-2 ${
                category && message.length >= 20 && !submitting
                  ? 'bg-amber-600 hover:bg-amber-700'
                  : 'bg-gray-300 cursor-not-allowed'
              }`}
            >
              {submitting ? (
                <>
                  <span className="w-4 h-4 border-2 border-white/30 border-t-white rounded-full animate-spin"></span>
                  Submitting...
                </>
              ) : (
                <>
                  <PaperAirplaneIcon className="w-4 h-4" />
                  Submit Appeal
                </>
              )}
            </button>
          </form>
        </div>

        {/* Footer */}
        <div className="text-center mt-6 text-xs text-gray-400">
          <p>&copy; {new Date().getFullYear()} Legal Ease. All rights reserved.</p>
        </div>
      </div>
    </div>
  );
};

export default AppealPage;
