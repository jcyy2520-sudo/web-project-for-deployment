import { useState, useEffect, useCallback } from 'react';
import axios from 'axios';
import { useNavigate } from 'react-router-dom';
import {
  StarIcon,
  XMarkIcon,
  ClockIcon,
  ChatBubbleLeftEllipsisIcon,
  PaperAirplaneIcon,
  ArrowPathIcon,
  ChevronLeftIcon,
  ChevronRightIcon,
  ExclamationTriangleIcon,
  CheckCircleIcon,
  InformationCircleIcon,
  FunnelIcon,
} from '@heroicons/react/24/outline';
import { StarIcon as StarIconSolid } from '@heroicons/react/24/solid';
import FeedbackThankYouModal from '../modals/FeedbackThankYouModal';

const UserFeedback = () => {
  const navigate = useNavigate();

  // Form state
  const [message, setMessage] = useState('');
  const [rating, setRating] = useState(0);
  const [hoverRating, setHoverRating] = useState(0);
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState(null);

  // Rate limit state
  const [canSubmit, setCanSubmit] = useState(true);
  const [used, setUsed] = useState(0);
  const [limit, setLimit] = useState(2);
  const [remaining, setRemaining] = useState(2);
  const [cooldownDays, setCooldownDays] = useState(7);
  const [cooldownLabel, setCooldownLabel] = useState('week');
  const [nextAvailableAt, setNextAvailableAt] = useState(null);
  const [nextAvailableFormatted, setNextAvailableFormatted] = useState(null);

  // History state
  const [feedbackHistory, setFeedbackHistory] = useState([]);
  const [historyLoading, setHistoryLoading] = useState(false);
  const [historyPage, setHistoryPage] = useState(1);
  const [historyTotalPages, setHistoryTotalPages] = useState(1);
  const [historyTotal, setHistoryTotal] = useState(0);
  const [historyFilter, setHistoryFilter] = useState('all');
  const [historySortOrder, setHistorySortOrder] = useState('desc');

  // UI state
  const [activeTab, setActiveTab] = useState('submit'); // 'submit' | 'history'
  const [showThankYou, setShowThankYou] = useState(false);
  const [submittedFeedback, setSubmittedFeedback] = useState(null);
  const [expandedFeedback, setExpandedFeedback] = useState(null);

  // Load rate limit info
  const loadRateLimit = useCallback(async () => {
    try {
      const resp = await axios.get('/api/user/feedback/check-limit');
      const data = resp.data?.data || {};
      setCanSubmit(data.can_submit ?? true);
      setUsed(data.used ?? 0);
      setLimit(data.limit ?? 2);
      setRemaining(data.remaining ?? 2);
      setCooldownDays(data.cooldown_days ?? 7);
      setCooldownLabel(data.cooldown_label ?? 'week');
      setNextAvailableAt(data.next_available_at ?? null);
      setNextAvailableFormatted(data.next_available_formatted ?? null);
    } catch (err) {
      console.warn('Failed to fetch rate limit');
    }
  }, []);

  // Load feedback history
  const loadHistory = useCallback(async (page = 1, ratingFilter = 'all', sortOrder = 'desc') => {
    setHistoryLoading(true);
    try {
      const params = {
        page,
        per_page: 5,
        sort_by: 'created_at',
        sort_order: sortOrder,
      };
      if (ratingFilter !== 'all') {
        params.rating = parseInt(ratingFilter);
      }
      const resp = await axios.get('/api/user/feedback', { params });
      const data = resp.data || {};

      setFeedbackHistory(data.data || []);
      setHistoryTotalPages(data.pagination?.last_page || 1);
      setHistoryTotal(data.pagination?.total || 0);
      setHistoryPage(page);

      // Also update rate limit from the response
      if (data.rate_limit) {
        setCanSubmit(data.rate_limit.can_submit ?? true);
        setUsed(data.rate_limit.used ?? 0);
        setLimit(data.rate_limit.limit ?? 2);
        setRemaining(data.rate_limit.remaining ?? 2);
        setCooldownDays(data.rate_limit.cooldown_days ?? 7);
        setCooldownLabel(data.rate_limit.cooldown_label ?? 'week');
        setNextAvailableAt(data.rate_limit.next_available_at ?? null);
        setNextAvailableFormatted(data.rate_limit.next_available_formatted ?? null);
      }
    } catch (err) {
      console.warn('Failed to fetch feedback history');
    } finally {
      setHistoryLoading(false);
    }
  }, []);

  useEffect(() => {
    loadRateLimit();
    loadHistory(1, historyFilter, historySortOrder);
  }, []);

  // Poll rate limit every 60 seconds to catch admin changes
  useEffect(() => {
    const interval = setInterval(() => {
      loadRateLimit();
    }, 60000);
    return () => clearInterval(interval);
  }, [loadRateLimit]);

  const handleSubmit = async (e) => {
    e.preventDefault();
    setError(null);

    if (!message || message.trim().length < 10) {
      setError('Please write at least 10 characters.');
      return;
    }
    if (rating < 1) {
      setError('Please select a rating.');
      return;
    }
    if (!canSubmit) {
      setError(
        `You've reached your feedback limit. You can submit again on ${formatNextAvailable()}.`
      );
      return;
    }

    try {
      setSubmitting(true);
      await axios.post('/api/user/feedback', { message, rating });

      setSubmittedFeedback({ message, rating });
      setMessage('');
      setRating(0);
      setShowThankYou(true);

      // Refresh data
      await loadRateLimit();
      await loadHistory(1, historyFilter, historySortOrder);
    } catch (err) {
      const resp = err.response?.data || {};
      if (resp.error === 'rate_limit_reached') {
        const nextFormatted = resp.data?.next_available_formatted;
        setCanSubmit(false);
        setNextAvailableFormatted(nextFormatted);
        setNextAvailableAt(resp.data?.next_available_at);
        setError(
          `You've reached your feedback limit of ${resp.data?.limit || limit} per ${resp.data?.cooldown_label || cooldownLabel}. You can submit again on ${nextFormatted || 'later'}.`
        );
      } else if (resp.error === 'user_blocked') {
        setError('You are currently blocked from submitting feedback.');
      } else if (resp.error === 'profanity_detected') {
        setError('Your feedback contains language that is not allowed. Please revise your message.');
      } else if (resp.error === 'duplicate_feedback') {
        setError('You already submitted this exact feedback. Please write a different message.');
      } else {
        setError(resp.message || 'Failed to submit. Please try again.');
      }
    } finally {
      setSubmitting(false);
    }
  };

  // Format the next available time in 12-hour format
  const formatNextAvailable = () => {
    if (nextAvailableFormatted) return nextAvailableFormatted;
    if (!nextAvailableAt) return 'later';
    try {
      const date = new Date(nextAvailableAt);
      return date.toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
      }) + ' at ' + date.toLocaleTimeString('en-US', {
        hour: 'numeric',
        minute: '2-digit',
        hour12: true,
      });
    } catch {
      return 'later';
    }
  };

  // Format a date for display
  const formatDate = (dateStr) => {
    if (!dateStr) return '';
    try {
      const date = new Date(dateStr);
      return date.toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
      }) + ' at ' + date.toLocaleTimeString('en-US', {
        hour: 'numeric',
        minute: '2-digit',
        hour12: true,
      });
    } catch {
      return dateStr;
    }
  };

  // Rating labels
  const ratingLabels = ['', 'Poor', 'Fair', 'Good', 'Very Good', 'Excellent'];

  // Feedback type labels
  const typeLabels = {
    general: 'General',
    service_quality: 'Service Quality',
    speed: 'Speed',
    support: 'Support',
    system_experience: 'System Experience',
    bug_report: 'Bug Report',
    suggestion: 'Suggestion',
    other: 'Other',
  };

  const renderStars = (count, size = 'w-4 h-4') => {
    return (
      <div className="flex gap-0.5">
        {[1, 2, 3, 4, 5].map((star) => (
          <StarIconSolid
            key={star}
            className={`${size} ${star <= count ? 'text-amber-400' : 'text-gray-300 dark:text-gray-600'}`}
          />
        ))}
      </div>
    );
  };

  // Progress bar percentage
  const progressPercent = limit > 0 ? Math.min((used / limit) * 100, 100) : 0;
  const progressColor = !canSubmit
    ? 'bg-red-500'
    : remaining <= 1
    ? 'bg-amber-500'
    : 'bg-green-500';

  return (
    <div className="w-full max-w-3xl lg:max-w-full mx-auto px-2 sm:px-4">
      {/* Header */}
      <div className="flex items-center gap-3 mb-4 sm:mb-6">
        <button
          onClick={() => navigate('/dashboard?tab=home')}
          className="p-1.5 hover:bg-gray-200 dark:hover:bg-gray-700 rounded-lg transition-colors"
        >
          <XMarkIcon className="w-5 h-5" />
        </button>
        <div className="flex-1 min-w-0">
          <h2 className="text-lg sm:text-xl font-bold truncate">Feedback</h2>
          <p className="text-xs sm:text-sm text-gray-500 dark:text-gray-400">
            Share your experience & view history
          </p>
        </div>
        <button
          onClick={() => {
            loadRateLimit();
            loadHistory(historyPage, historyFilter, historySortOrder);
          }}
          className="p-2 hover:bg-gray-200 dark:hover:bg-gray-700 rounded-lg transition-colors"
          title="Refresh"
        >
          <ArrowPathIcon className="w-4 h-4 text-gray-500" />
        </button>
      </div>

      {/* Usage Bar */}
      <div className="mb-4 sm:mb-6 bg-gray-50 dark:bg-gray-800 rounded-xl p-3 sm:p-4 border border-gray-200 dark:border-gray-700">
        <div className="flex items-center justify-between mb-2">
          <div className="flex items-center gap-2">
            <ChatBubbleLeftEllipsisIcon className="w-4 h-4 text-amber-500" />
            <span className="text-xs sm:text-sm font-medium">Feedback Usage</span>
          </div>
          <span className={`text-xs font-semibold px-2 py-0.5 rounded-full ${
            !canSubmit
              ? 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400'
              : remaining <= 1
              ? 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400'
              : 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400'
          }`}>
            {used}/{limit} used
          </span>
        </div>
        <div className="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2">
          <div
            className={`h-2 rounded-full transition-all duration-500 ${progressColor}`}
            style={{ width: `${progressPercent}%` }}
          />
        </div>
        <p className="text-[10px] sm:text-xs text-gray-500 dark:text-gray-400 mt-1.5">
          {remaining > 0
            ? `${remaining} feedback${remaining !== 1 ? 's' : ''} remaining this ${cooldownLabel}`
            : `Limit reached for this ${cooldownLabel}`}
        </p>
      </div>

      {/* Limit Reached Banner */}
      {!canSubmit && (
        <div className="mb-4 sm:mb-6 bg-red-50 dark:bg-red-950/30 border border-red-200 dark:border-red-800/50 rounded-xl p-3 sm:p-4">
          <div className="flex gap-3">
            <ExclamationTriangleIcon className="w-5 h-5 text-red-500 flex-shrink-0 mt-0.5" />
            <div className="min-w-0">
              <p className="text-sm font-semibold text-red-700 dark:text-red-400">
                Feedback Limit Reached
              </p>
              <p className="text-xs sm:text-sm text-red-600 dark:text-red-300 mt-1">
                You've used all <strong>{limit}</strong> feedback{limit !== 1 ? 's' : ''} for this {cooldownLabel}. 
              </p>
              <div className="flex items-center gap-1.5 mt-2 bg-red-100 dark:bg-red-900/40 rounded-lg px-3 py-2">
                <ClockIcon className="w-4 h-4 text-red-500 flex-shrink-0" />
                <p className="text-xs sm:text-sm font-medium text-red-700 dark:text-red-300">
                  You can submit again on{' '}
                  <span className="font-bold">{formatNextAvailable()}</span>
                </p>
              </div>
            </div>
          </div>
        </div>
      )}

      {/* Tab Switcher */}
      <div className="flex bg-gray-100 dark:bg-gray-800 rounded-xl p-1 mb-4 sm:mb-6">
        <button
          onClick={() => setActiveTab('submit')}
          className={`flex-1 flex items-center justify-center gap-2 px-3 py-2 sm:py-2.5 rounded-lg text-xs sm:text-sm font-medium transition-all ${
            activeTab === 'submit'
              ? 'bg-white dark:bg-gray-700 text-amber-700 dark:text-amber-400 shadow-sm'
              : 'text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300'
          }`}
        >
          <PaperAirplaneIcon className="w-4 h-4" />
          <span>Submit</span>
        </button>
        <button
          onClick={() => {
            setActiveTab('history');
            loadHistory(1, historyFilter, historySortOrder);
          }}
          className={`flex-1 flex items-center justify-center gap-2 px-3 py-2 sm:py-2.5 rounded-lg text-xs sm:text-sm font-medium transition-all ${
            activeTab === 'history'
              ? 'bg-white dark:bg-gray-700 text-amber-700 dark:text-amber-400 shadow-sm'
              : 'text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300'
          }`}
        >
          <ClockIcon className="w-4 h-4" />
          <span>History</span>
          {historyTotal > 0 && (
            <span className={`text-[10px] px-1.5 py-0.5 rounded-full ${
              activeTab === 'history'
                ? 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400'
                : 'bg-gray-200 text-gray-500 dark:bg-gray-700 dark:text-gray-400'
            }`}>
              {historyTotal}
            </span>
          )}
        </button>
      </div>

      {/* Submit Tab */}
      {activeTab === 'submit' && (
        <form
          onSubmit={handleSubmit}
          className="bg-gray-50 dark:bg-gray-800 rounded-xl p-4 sm:p-6 border border-gray-200 dark:border-gray-700 space-y-5"
        >
          {/* Rating */}
          <div>
            <label className="block text-sm sm:text-base font-medium mb-2">
              How would you rate your experience?
            </label>
            <div className="flex items-center gap-1 sm:gap-2">
              {[1, 2, 3, 4, 5].map((star) => (
                <button
                  key={star}
                  type="button"
                  onClick={() => setRating(star)}
                  onMouseEnter={() => setHoverRating(star)}
                  onMouseLeave={() => setHoverRating(0)}
                  className="transition-transform hover:scale-110 active:scale-95 focus:outline-none p-0.5"
                  disabled={!canSubmit}
                >
                  {star <= (hoverRating || rating) ? (
                    <StarIconSolid className="w-8 h-8 sm:w-9 sm:h-9 text-amber-400 drop-shadow-sm" />
                  ) : (
                    <StarIcon className="w-8 h-8 sm:w-9 sm:h-9 text-gray-300 dark:text-gray-600" />
                  )}
                </button>
              ))}
              {(hoverRating || rating) > 0 && (
                <span className="ml-2 text-xs sm:text-sm font-medium text-amber-600 dark:text-amber-400">
                  {ratingLabels[hoverRating || rating]}
                </span>
              )}
            </div>
          </div>

          {/* Message */}
          <div>
            <label className="block text-sm sm:text-base font-medium mb-2">
              Your Message
            </label>
            <textarea
              value={message}
              onChange={(e) => setMessage(e.target.value)}
              placeholder={canSubmit ? "Tell us what you think about our service..." : "You've reached your feedback limit for this period."}
              rows={4}
              maxLength={500}
              disabled={!canSubmit}
              className="w-full px-3 py-2.5 rounded-lg bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 focus:outline-none focus:border-amber-500 focus:ring-1 focus:ring-amber-500 resize-none text-sm sm:text-base disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
            />
            <div className="flex justify-between items-center mt-1">
              <p className="text-[10px] sm:text-xs text-gray-400">
                Minimum 10 characters
              </p>
              <p className={`text-[10px] sm:text-xs ${message.length >= 480 ? 'text-red-400' : 'text-gray-400'}`}>
                {message.length}/500
              </p>
            </div>
          </div>

          {/* Error */}
          {error && (
            <div className="flex items-start gap-2 text-sm text-red-600 dark:text-red-400 bg-red-50 dark:bg-red-950/30 p-3 rounded-lg border border-red-200 dark:border-red-800/50">
              <ExclamationTriangleIcon className="w-4 h-4 flex-shrink-0 mt-0.5" />
              <p className="text-xs sm:text-sm">{error}</p>
            </div>
          )}

          {/* Submit Button */}
          <button
            type="submit"
            disabled={submitting || !canSubmit}
            className="w-full flex items-center justify-center gap-2 px-4 py-3 bg-amber-600 text-white rounded-xl font-medium hover:bg-amber-700 disabled:bg-gray-400 dark:disabled:bg-gray-600 disabled:cursor-not-allowed transition-all text-sm sm:text-base shadow-sm hover:shadow"
          >
            {submitting ? (
              <>
                <div className="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin" />
                Sending...
              </>
            ) : !canSubmit ? (
              <>
                <ExclamationTriangleIcon className="w-4 h-4" />
                Limit Reached
              </>
            ) : (
              <>
                <PaperAirplaneIcon className="w-4 h-4" />
                Send Feedback
              </>
            )}
          </button>

          {/* Info */}
          <div className="flex items-start gap-2 text-gray-400 dark:text-gray-500">
            <InformationCircleIcon className="w-4 h-4 flex-shrink-0 mt-0.5" />
            <p className="text-[10px] sm:text-xs">
              You can submit up to {limit} feedback{limit !== 1 ? 's' : ''} per {cooldownLabel}. 
              A confirmation email will be sent after submission.
            </p>
          </div>
        </form>
      )}

      {/* History Tab */}
      {activeTab === 'history' && (
        <div className="space-y-4">
          {/* History Header & Filters */}
          <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-2 sm:gap-4">
            <h3 className="text-sm sm:text-base font-semibold flex items-center gap-2">
              <ClockIcon className="w-4 h-4 text-amber-500" />
              Your Feedback History
              <span className="text-xs text-gray-400 font-normal">({historyTotal} total)</span>
            </h3>
            <div className="flex items-center gap-2">
              {/* Rating Filter */}
              <div className="relative">
                <select
                  value={historyFilter}
                  onChange={(e) => {
                    setHistoryFilter(e.target.value);
                    loadHistory(1, e.target.value, historySortOrder);
                  }}
                  className="appearance-none text-xs pl-7 pr-3 py-1.5 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 focus:outline-none focus:ring-1 focus:ring-amber-500 focus:border-amber-500"
                >
                  <option value="all">All Ratings</option>
                  <option value="5">5 Stars</option>
                  <option value="4">4 Stars</option>
                  <option value="3">3 Stars</option>
                  <option value="2">2 Stars</option>
                  <option value="1">1 Star</option>
                </select>
                <FunnelIcon className="w-3.5 h-3.5 text-gray-400 absolute left-2 top-1/2 -translate-y-1/2 pointer-events-none" />
              </div>
              {/* Sort */}
              <button
                onClick={() => {
                  const newOrder = historySortOrder === 'desc' ? 'asc' : 'desc';
                  setHistorySortOrder(newOrder);
                  loadHistory(1, historyFilter, newOrder);
                }}
                className="text-xs px-3 py-1.5 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors"
                title={historySortOrder === 'desc' ? 'Showing newest first' : 'Showing oldest first'}
              >
                {historySortOrder === 'desc' ? 'Newest' : 'Oldest'}
              </button>
            </div>
          </div>

          {/* History Content */}
          {historyLoading ? (
            <div className="flex items-center justify-center py-12">
              <div className="w-6 h-6 border-2 border-amber-500 border-t-transparent rounded-full animate-spin" />
            </div>
          ) : feedbackHistory.length === 0 ? (
            <div className="text-center py-12 bg-gray-50 dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700">
              <ChatBubbleLeftEllipsisIcon className="w-10 h-10 text-gray-300 dark:text-gray-600 mx-auto mb-3" />
              <p className="text-sm font-medium text-gray-500 dark:text-gray-400">
                No feedback yet
              </p>
              <p className="text-xs text-gray-400 dark:text-gray-500 mt-1">
                {historyFilter !== 'all'
                  ? 'No feedback matches this filter.'
                  : 'Submit your first feedback to see it here.'}
              </p>
              {historyFilter !== 'all' && (
                <button
                  onClick={() => {
                    setHistoryFilter('all');
                    loadHistory(1, 'all', historySortOrder);
                  }}
                  className="mt-3 text-xs text-amber-600 hover:text-amber-700 font-medium"
                >
                  Clear filter
                </button>
              )}
            </div>
          ) : (
            <div className="space-y-3">
              {feedbackHistory.map((fb) => (
                <div
                  key={fb.id}
                  className="bg-gray-50 dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden transition-all hover:border-gray-300 dark:hover:border-gray-600"
                >
                  {/* Feedback Card Header */}
                  <button
                    onClick={() => setExpandedFeedback(expandedFeedback === fb.id ? null : fb.id)}
                    className="w-full text-left p-3 sm:p-4"
                  >
                    <div className="flex items-start justify-between gap-3">
                      <div className="flex-1 min-w-0">
                        <div className="flex flex-wrap items-center gap-2 mb-1.5">
                          {renderStars(fb.rating, 'w-3.5 h-3.5 sm:w-4 sm:h-4')}
                          <span className="text-[10px] sm:text-xs text-gray-400">
                            {ratingLabels[fb.rating]}
                          </span>
                        </div>
                        <p className={`text-xs sm:text-sm text-gray-700 dark:text-gray-300 ${
                          expandedFeedback === fb.id ? '' : 'line-clamp-2'
                        }`}>
                          {fb.message}
                        </p>
                      </div>
                      <div className="flex flex-col items-end gap-1 flex-shrink-0">
                        {fb.is_testimonial && (
                          <span className="text-[10px] px-1.5 py-0.5 rounded-full bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400 font-medium whitespace-nowrap">
                            Testimonial
                          </span>
                        )}
                        {fb.is_reported && (
                          <span className="text-[10px] px-1.5 py-0.5 rounded-full bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400 font-medium whitespace-nowrap">
                            Reported
                          </span>
                        )}
                      </div>
                    </div>
                  </button>

                  {/* Expanded Details */}
                  {expandedFeedback === fb.id && (
                    <div className="px-3 sm:px-4 pb-3 sm:pb-4 border-t border-gray-200 dark:border-gray-700 pt-3">
                      <div className="grid grid-cols-1 sm:grid-cols-2 gap-2">
                        <div>
                          <p className="text-[10px] sm:text-xs text-gray-400 uppercase tracking-wider mb-0.5">
                            Submitted
                          </p>
                          <p className="text-xs sm:text-sm font-medium">
                            {formatDate(fb.created_at)}
                          </p>
                        </div>
                        {fb.feedback_type && (
                          <div>
                            <p className="text-[10px] sm:text-xs text-gray-400 uppercase tracking-wider mb-0.5">
                              Category
                            </p>
                            <p className="text-xs sm:text-sm font-medium">
                              {typeLabels[fb.feedback_type] || fb.feedback_type}
                            </p>
                          </div>
                        )}
                        <div>
                          <p className="text-[10px] sm:text-xs text-gray-400 uppercase tracking-wider mb-0.5">
                            Status
                          </p>
                          <div className="flex items-center gap-1">
                            {fb.is_reported ? (
                              <>
                                <ExclamationTriangleIcon className="w-3.5 h-3.5 text-red-500" />
                                <span className="text-xs text-red-600 dark:text-red-400 font-medium">Reported</span>
                              </>
                            ) : (
                              <>
                                <CheckCircleIcon className="w-3.5 h-3.5 text-green-500" />
                                <span className="text-xs text-green-600 dark:text-green-400 font-medium">Active</span>
                              </>
                            )}
                          </div>
                        </div>
                        {fb.is_testimonial && (
                          <div>
                            <p className="text-[10px] sm:text-xs text-gray-400 uppercase tracking-wider mb-0.5">
                              Featured
                            </p>
                            <p className="text-xs sm:text-sm font-medium text-amber-600 dark:text-amber-400">
                              Displayed as testimonial
                            </p>
                          </div>
                        )}
                      </div>
                      {fb.is_reported && fb.reported_reason && (
                        <div className="mt-3 p-2.5 bg-red-50 dark:bg-red-950/20 rounded-lg border border-red-200 dark:border-red-800/30">
                          <p className="text-[10px] sm:text-xs text-red-500 uppercase tracking-wider mb-0.5">Report Reason</p>
                          <p className="text-xs sm:text-sm text-red-700 dark:text-red-300 capitalize">
                            {fb.reported_reason?.replace(/_/g, ' ')}
                          </p>
                        </div>
                      )}
                    </div>
                  )}

                  {/* Footer timestamp (always visible) */}
                  <div className="px-3 sm:px-4 pb-2.5 sm:pb-3 flex items-center gap-1.5 text-[10px] sm:text-xs text-gray-400">
                    <ClockIcon className="w-3 h-3" />
                    {formatDate(fb.created_at)}
                  </div>
                </div>
              ))}
            </div>
          )}

          {/* Pagination */}
          {historyTotalPages > 1 && (
            <div className="flex items-center justify-center gap-2 pt-2">
              <button
                onClick={() => loadHistory(historyPage - 1, historyFilter, historySortOrder)}
                disabled={historyPage <= 1}
                className="p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800 disabled:opacity-30 disabled:cursor-not-allowed transition-colors"
              >
                <ChevronLeftIcon className="w-4 h-4" />
              </button>
              <div className="flex items-center gap-1">
                {Array.from({ length: Math.min(historyTotalPages, 5) }, (_, i) => {
                  let pageNum;
                  if (historyTotalPages <= 5) {
                    pageNum = i + 1;
                  } else if (historyPage <= 3) {
                    pageNum = i + 1;
                  } else if (historyPage >= historyTotalPages - 2) {
                    pageNum = historyTotalPages - 4 + i;
                  } else {
                    pageNum = historyPage - 2 + i;
                  }
                  return (
                    <button
                      key={pageNum}
                      onClick={() => loadHistory(pageNum, historyFilter, historySortOrder)}
                      className={`w-8 h-8 rounded-lg text-xs font-medium transition-colors ${
                        pageNum === historyPage
                          ? 'bg-amber-600 text-white'
                          : 'hover:bg-gray-100 dark:hover:bg-gray-800 text-gray-500'
                      }`}
                    >
                      {pageNum}
                    </button>
                  );
                })}
              </div>
              <button
                onClick={() => loadHistory(historyPage + 1, historyFilter, historySortOrder)}
                disabled={historyPage >= historyTotalPages}
                className="p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800 disabled:opacity-30 disabled:cursor-not-allowed transition-colors"
              >
                <ChevronRightIcon className="w-4 h-4" />
              </button>
            </div>
          )}
        </div>
      )}

      {/* Thank You Modal */}
      {showThankYou && submittedFeedback && (
        <FeedbackThankYouModal
          isOpen={true}
          onClose={() => {
            setShowThankYou(false);
            // Switch to history tab to show the new feedback
            setActiveTab('history');
            loadHistory(1, 'all', 'desc');
          }}
          rating={submittedFeedback.rating || 0}
          message={submittedFeedback.message || ''}
        />
      )}
    </div>
  );
};

export default UserFeedback;
