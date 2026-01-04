import { useState, useEffect } from 'react';
import { useApi } from '../../hooks/useApi';
import axios from 'axios';
import {
  MagnifyingGlassIcon,
  ChevronLeftIcon,
  ChevronRightIcon,
  StarIcon,
  PlusIcon,
  ChatBubbleBottomCenterTextIcon,
  CheckCircleIcon,
  ExclamationTriangleIcon,
  XMarkIcon
} from '@heroicons/react/24/outline';
import LoadingSpinner from '../LoadingSpinner';

const FEEDBACK_TYPES = [
  { value: 'service_quality', label: 'Service Quality', icon: '⭐' },
  { value: 'speed', label: 'Speed', icon: '⚡' },
  { value: 'support', label: 'Support', icon: '💬' },
  { value: 'system_experience', label: 'System Experience', icon: '💻' },
  { value: 'bug_report', label: 'Bug Report', icon: '🐛' },
  { value: 'suggestion', label: 'Suggestion', icon: '💡' },
  { value: 'other', label: 'Other', icon: '📝' }
];

const UserFeedback = ({ user }) => {
  const { callApi } = useApi();
  const [feedback, setFeedback] = useState([]);
  const [loading, setLoading] = useState(true);
  const [currentPage, setCurrentPage] = useState(1);
  const [itemsPerPage] = useState(6);
  const [searchTerm, setSearchTerm] = useState('');
  const [sortBy, setSortBy] = useState('created_at');
  const [sortOrder, setSortOrder] = useState('desc');
  const [total, setTotal] = useState(0);

  // Form state
  const [showForm, setShowForm] = useState(false);
  const [formData, setFormData] = useState({
    message: '',
    rating: 0,
    feedback_type: 'other'
  });
  const [submitting, setSubmitting] = useState(false);
  const [submitError, setSubmitError] = useState('');

  // Rate limit state
  const [rateLimit, setRateLimit] = useState({
    can_submit: true,
    used: 0,
    limit: 2,
    cooldown_days: 7,
    is_blocked: false,
    next_available_at: null
  });

  const formatNextAvailableDate = (isoString) => {
    if (!isoString) return null;
    const date = new Date(isoString);
    const dateOptions = { month: 'short', day: 'numeric', year: 'numeric' };
    const timeOptions = { hour: 'numeric', minute: '2-digit', hour12: true };
    return `${date.toLocaleDateString('en-US', dateOptions)} at ${date.toLocaleTimeString('en-US', timeOptions)}`;
  };

  // Thank you message state (inline, not modal)
  const [showSuccessMessage, setShowSuccessMessage] = useState(false);
  const [successMessage, setSuccessMessage] = useState('');

  const loadFeedback = async (page = 1) => {
    try {
      setLoading(true);
      const result = await callApi(async () => {
        const params = {
          page,
          per_page: itemsPerPage,
          search: searchTerm,
          sort_by: sortBy,
          sort_order: sortOrder
        };
        const response = await axios.get('/api/user/feedback', { params, timeout: 10000 });
        return response.data;
      });

      if (result.success || result.data) {
        const data = result.data || result;
        setFeedback(data.data || []);
        setTotal(data.pagination?.total || 0);
        setCurrentPage(page);
        if (data.rate_limit) setRateLimit(data.rate_limit);
      }
      setLoading(false);
    } catch (error) {
      console.error('Failed to load feedback:', error);
      setLoading(false);
    }
  };

  useEffect(() => {
    const checkRateLimit = async () => {
      try {
        const response = await axios.get('/api/user/feedback/check-limit');
        if (response.data?.data) setRateLimit(response.data.data);
      } catch (error) {
        console.error('Failed to check rate limit:', error);
      }
    };
    checkRateLimit();
  }, []);

  useEffect(() => {
    loadFeedback(1);
  }, [searchTerm, sortBy, sortOrder]);

  const totalPages = Math.ceil(total / itemsPerPage);

  const handleSubmitFeedback = async (e) => {
    e.preventDefault();
    setSubmitError('');
    setShowSuccessMessage(false);

    if (!formData.message.trim() || formData.message.trim().length < 10) {
      setSubmitError('Please enter a message (minimum 10 characters)');
      return;
    }
    if (formData.rating === 0) {
      setSubmitError('Please select a rating');
      return;
    }

    try {
      setSubmitting(true);
      const result = await callApi(async () => {
        const response = await axios.post('/api/user/feedback', {
          message: formData.message,
          rating: formData.rating,
          feedback_type: formData.feedback_type,
          email: user?.email,
          user_id: user?.id
        });
        return response.data;
      });

      if (result.success || result.data) {
        // Show inline success message instead of modal
        setSuccessMessage(`✅ Thank you! Your ${FEEDBACK_TYPES.find(t => t.value === formData.feedback_type)?.label || 'feedback'} has been received.`);
        setShowSuccessMessage(true);
        
        setFormData({ message: '', rating: 0, feedback_type: 'other' });
        setShowForm(false);
        loadFeedback(1);
        
        // Auto-dismiss success message after 6 seconds
        setTimeout(() => {
          setShowSuccessMessage(false);
        }, 6000);
      }
    } catch (error) {
      const errorMsg = error.response?.data?.message || error.message;
      if (error.response?.data?.error === 'rate_limit_reached') {
        const nextAvailable = error.response?.data?.data?.next_available_at;
        const nextDate = formatNextAvailableDate(nextAvailable);
        setSubmitError(`⚠️ You've reached your feedback limit. You can submit feedback again on ${nextDate}.`);
        setRateLimit(prev => ({ ...prev, can_submit: false, next_available_at: nextAvailable }));
      } else if (error.response?.data?.error === 'user_blocked') {
        setSubmitError('❌ You have been blocked from submitting feedback.');
      } else {
        setSubmitError(errorMsg || 'Failed to submit feedback');
      }
    } finally {
      setSubmitting(false);
    }
  };

  const getTypeLabel = (type) => {
    const found = FEEDBACK_TYPES.find(t => t.value === type);
    return found ? found.label : type;
  };

  const getTypeIcon = (type) => {
    const found = FEEDBACK_TYPES.find(t => t.value === type);
    return found ? found.icon : '📝';
  };

  if (loading && !feedback.length) {
    return (
      <div className="flex items-center justify-center h-64">
        <LoadingSpinner />
      </div>
    );
  }

  return (
    <div className="space-y-6">
      {/* Compact Header with Status */}
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
          <h2 className="text-xl font-bold text-amber-50">My Feedback</h2>
          <div className="flex items-center gap-3 mt-1">
            <span className="text-xs text-gray-400">
              {rateLimit.used}/{rateLimit.limit} used
            </span>
            {rateLimit.is_blocked && (
              <span className="text-xs bg-red-500/20 text-red-400 px-2 py-0.5 rounded">Blocked</span>
            )}
            {!rateLimit.can_submit && !rateLimit.is_blocked && (
              <span className="text-xs bg-amber-500/20 text-amber-400 px-2 py-0.5 rounded">
                Next: {formatNextAvailableDate(rateLimit.next_available_at)}
              </span>
            )}
          </div>
        </div>
        <button
          onClick={() => setShowForm(!showForm)}
          disabled={!rateLimit.can_submit}
          className={`flex items-center px-4 py-2 rounded-lg text-sm font-medium transition-all ${
            rateLimit.can_submit
              ? 'bg-amber-600 text-white hover:bg-amber-700'
              : 'bg-gray-700 text-gray-500 cursor-not-allowed'
          }`}
        >
          <PlusIcon className="h-4 w-4 mr-2" />
          {showForm ? 'Cancel' : 'New Feedback'}
        </button>
      </div>

      {/* Compact Feedback Form */}
      {showForm && (
        <div className="bg-gray-800/50 border border-amber-500/30 rounded-xl p-5">
          <form onSubmit={handleSubmitFeedback} className="space-y-4">
            {/* Rating - Inline Stars */}
            <div>
              <label className="block text-sm font-medium text-gray-300 mb-2">How would you rate us?</label>
              <div className="flex items-center gap-1">
                {[1, 2, 3, 4, 5].map((star) => (
                  <button
                    key={star}
                    type="button"
                    onClick={() => setFormData({ ...formData, rating: star })}
                    className="p-1 transition-transform hover:scale-110 focus:outline-none"
                  >
                    <svg
                      width="28"
                      height="28"
                      viewBox="0 0 24 24"
                      fill={star <= formData.rating ? 'currentColor' : 'none'}
                      stroke="currentColor"
                      strokeWidth="2"
                      className={`transition-colors ${star <= formData.rating ? 'text-amber-400' : 'text-gray-600'}`}
                    >
                      <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z" />
                    </svg>
                  </button>
                ))}
                {formData.rating > 0 && (
                  <span className="ml-2 text-sm text-amber-400">{formData.rating}/5</span>
                )}
              </div>
            </div>

            {/* Type - Compact Pills */}
            <div>
              <label className="block text-sm font-medium text-gray-300 mb-2">Category</label>
              <div className="flex flex-wrap gap-2">
                {FEEDBACK_TYPES.map((type) => (
                  <button
                    key={type.value}
                    type="button"
                    onClick={() => setFormData({ ...formData, feedback_type: type.value })}
                    className={`px-3 py-1.5 rounded-full text-xs font-medium transition-all ${
                      formData.feedback_type === type.value
                        ? 'bg-amber-600 text-white'
                        : 'bg-gray-700 text-gray-300 hover:bg-gray-600'
                    }`}
                  >
                    {type.icon} {type.label}
                  </button>
                ))}
              </div>
            </div>

            {/* Message */}
            <div>
              <label className="block text-sm font-medium text-gray-300 mb-2">Your Message</label>
              <textarea
                value={formData.message}
                onChange={(e) => setFormData({ ...formData, message: e.target.value })}
                placeholder="Share your thoughts..."
                className="w-full px-3 py-2 bg-gray-900 border border-gray-700 rounded-lg text-white text-sm focus:outline-none focus:border-amber-500 resize-none"
                rows={3}
              />
              <p className="text-xs text-gray-500 mt-1">{formData.message.length}/2000</p>
            </div>

            {submitError && (
              <p className="text-sm text-red-400 bg-red-500/10 px-3 py-2 rounded-lg">{submitError}</p>
            )}

            <button
              type="submit"
              disabled={submitting}
              className="w-full py-2.5 bg-amber-600 text-white rounded-lg font-medium hover:bg-amber-700 transition-colors disabled:opacity-50"
            >
              {submitting ? 'Submitting...' : 'Submit Feedback'}
            </button>
          </form>
        </div>
      )}

      {/* Search & Sort - Compact */}
      <div className="flex flex-col sm:flex-row gap-3">
        <div className="relative flex-1">
          <MagnifyingGlassIcon className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-500" />
          <input
            type="text"
            placeholder="Search..."
            value={searchTerm}
            onChange={(e) => setSearchTerm(e.target.value)}
            className="w-full pl-9 pr-3 py-2 bg-gray-800 border border-gray-700 rounded-lg text-white text-sm focus:outline-none focus:border-amber-500"
          />
        </div>
        <select
          value={`${sortBy}-${sortOrder}`}
          onChange={(e) => {
            const [newSortBy, newSortOrder] = e.target.value.split('-');
            setSortBy(newSortBy);
            setSortOrder(newSortOrder);
          }}
          className="px-3 py-2 bg-gray-800 border border-gray-700 rounded-lg text-white text-sm focus:outline-none focus:border-amber-500"
        >
          <option value="created_at-desc">Newest</option>
          <option value="created_at-asc">Oldest</option>
          <option value="rating-desc">Highest Rated</option>
          <option value="rating-asc">Lowest Rated</option>
        </select>
      </div>

      {/* Feedback List - Clean Cards */}
      {feedback.length > 0 ? (
        <div className="space-y-3">
          {feedback.map((item) => (
            <div
              key={item.id}
              className="bg-gray-800/50 border border-gray-700 rounded-xl p-4 hover:border-amber-500/30 transition-colors"
            >
              <div className="flex items-start justify-between gap-4">
                <div className="flex-1 min-w-0">
                  <div className="flex items-center gap-2 mb-2">
                    {/* Stars */}
                    <div className="flex">
                      {[1, 2, 3, 4, 5].map((star) => (
                        <svg
                          key={star}
                          width="14"
                          height="14"
                          viewBox="0 0 24 24"
                          fill={star <= item.rating ? 'currentColor' : 'none'}
                          stroke="currentColor"
                          strokeWidth="2"
                          className={star <= item.rating ? 'text-amber-400' : 'text-gray-600'}
                        >
                          <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z" />
                        </svg>
                      ))}
                    </div>
                    <span className="text-xs bg-gray-700 text-gray-300 px-2 py-0.5 rounded">
                      {getTypeIcon(item.feedback_type)} {getTypeLabel(item.feedback_type)}
                    </span>
                  </div>
                  <p className="text-sm text-gray-300 line-clamp-2">{item.message}</p>
                  <p className="text-xs text-gray-500 mt-2">
                    {new Date(item.created_at).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })}
                  </p>
                </div>
              </div>
            </div>
          ))}
        </div>
      ) : (
        <div className="text-center py-12 bg-gray-800/30 rounded-xl border border-gray-700">
          <ChatBubbleBottomCenterTextIcon className="h-10 w-10 text-gray-600 mx-auto mb-3" />
          <p className="text-gray-400 text-sm">No feedback yet</p>
          <p className="text-gray-500 text-xs mt-1">Share your thoughts with us!</p>
        </div>
      )}

      {/* Pagination - Compact */}
      {totalPages > 1 && (
        <div className="flex items-center justify-between text-sm">
          <span className="text-gray-500 text-xs">
            {(currentPage - 1) * itemsPerPage + 1}-{Math.min(currentPage * itemsPerPage, total)} of {total}
          </span>
          <div className="flex items-center gap-1">
            <button
              onClick={() => loadFeedback(currentPage - 1)}
              disabled={currentPage === 1}
              className="p-1.5 rounded border border-gray-700 text-gray-400 hover:text-amber-400 disabled:opacity-50"
            >
              <ChevronLeftIcon className="h-4 w-4" />
            </button>
            <span className="px-3 text-gray-400">{currentPage}/{totalPages}</span>
            <button
              onClick={() => loadFeedback(currentPage + 1)}
              disabled={currentPage === totalPages}
              className="p-1.5 rounded border border-gray-700 text-gray-400 hover:text-amber-400 disabled:opacity-50"
            >
              <ChevronRightIcon className="h-4 w-4" />
            </button>
          </div>
        </div>
      )}

      {/* Inline Success Message */}
      {showSuccessMessage && (
        <div className="bg-green-500/10 border border-green-500/30 rounded-lg p-4 flex items-start gap-3">
          <CheckCircleIcon className="h-5 w-5 text-green-400 flex-shrink-0 mt-0.5" />
          <div className="flex-1">
            <p className="text-green-400 text-sm font-medium">{successMessage}</p>
            <p className="text-green-400/70 text-xs mt-1">A confirmation email has been sent to your inbox.</p>
          </div>
          <button
            onClick={() => setShowSuccessMessage(false)}
            className="text-green-400 hover:text-green-300 flex-shrink-0"
          >
            <XMarkIcon className="h-4 w-4" />
          </button>
        </div>
      )}
    </div>
  );
};

export default UserFeedback;
