import { useState, useEffect } from 'react';
import axios from 'axios';
import { useNavigate } from 'react-router-dom';
import { 
  StarIcon, 
  PaperAirplaneIcon, 
  ChatBubbleBottomCenterTextIcon,
  ClockIcon,
  CheckCircleIcon,
  XMarkIcon,
  ChevronDownIcon,
  ChevronUpIcon,
  ArrowLeftIcon
} from '@heroicons/react/24/outline';
import { StarIcon as StarIconSolid } from '@heroicons/react/24/solid';
import StarRating from '../ui/StarRating';
import FeedbackThankYouModal from '../modals/FeedbackThankYouModal';

const categoryOptions = [
  { value: 'service_quality', label: 'Service Quality', icon: '⭐', color: 'amber' },
  { value: 'speed', label: 'Speed', icon: '⚡', color: 'blue' },
  { value: 'support', label: 'Support', icon: '💬', color: 'green' },
  { value: 'system_experience', label: 'System Experience', icon: '🖥️', color: 'purple' },
  { value: 'bug_report', label: 'Bug Report', icon: '🐛', color: 'red' },
  { value: 'suggestion', label: 'Suggestion', icon: '💡', color: 'cyan' },
  { value: 'other', label: 'Other', icon: '📝', color: 'gray' }
];

const UserFeedback = () => {
  const navigate = useNavigate();
  const [feedbackList, setFeedbackList] = useState([]);
  const [message, setMessage] = useState('');
  const [rating, setRating] = useState(0);
  const [hoveredRating, setHoveredRating] = useState(0);
  const [category, setCategory] = useState('service_quality');
  const [loading, setLoading] = useState(false);
  const [submitting, setSubmitting] = useState(false);
  const [canSubmit, setCanSubmit] = useState(true);
  const [used, setUsed] = useState(0);
  const [limit, setLimit] = useState(2);
  const [cooldownDays, setCooldownDays] = useState(7);
  const [nextAvailableAt, setNextAvailableAt] = useState(null);
  const [showThankYou, setShowThankYou] = useState(false);
  const [submittedFeedback, setSubmittedFeedback] = useState(null);
  const [showHistory, setShowHistory] = useState(false);
  const [error, setError] = useState(null);

  const loadFeedback = async () => {
    try {
      setLoading(true);
      const resp = await axios.get('/api/user/feedback', { params: { per_page: 10 } });
      setFeedbackList(resp.data?.data || []);
    } catch (err) {
      console.error('Failed to load feedback', err);
    } finally {
      setLoading(false);
    }
  };

  const loadRateLimit = async () => {
    try {
      const resp = await axios.get('/api/user/feedback/check-limit');
      const data = resp.data?.data || {};
      setCanSubmit(data.can_submit ?? true);
      setUsed(data.used ?? 0);
      setLimit(data.limit ?? 2);
      setCooldownDays(data.cooldown_days ?? 7);
      setNextAvailableAt(data.next_available_at ?? null);
    } catch (err) {
      console.warn('Failed to fetch rate limit');
    }
  };

  // Format date/time for display
  const formatNextAvailableDate = (isoString) => {
    if (!isoString) return null;
    const date = new Date(isoString);
    const dateOptions = { month: 'short', day: 'numeric', year: 'numeric' };
    const timeOptions = { hour: 'numeric', minute: '2-digit', hour12: true };
    return `${date.toLocaleDateString('en-US', dateOptions)} at ${date.toLocaleTimeString('en-US', timeOptions)}`;
  };

  useEffect(() => {
    loadFeedback();
    loadRateLimit();
  }, []);

  const handleSubmit = async (e) => {
    e.preventDefault();
    setError(null);
    
    if (!message || message.trim().length < 10) {
      setError('Please write at least 10 characters for your feedback.');
      return;
    }
    if (rating < 1) {
      setError('Please select a rating.');
      return;
    }
    if (!canSubmit) {
      setError(`You have reached your feedback limit of ${limit}. Please try again later.`);
      return;
    }

    try {
      setSubmitting(true);
      await axios.post('/api/user/feedback', { 
        message, 
        rating, 
        feedback_type: category 
      });
      
      setSubmittedFeedback({
        message,
        rating,
        category: categoryOptions.find(c => c.value === category)?.label || category
      });
      
      setMessage('');
      setRating(0);
      setCategory('service_quality');
      setShowThankYou(true);
      
      await loadFeedback();
      await loadRateLimit();
      
    } catch (err) {
      console.error('Submit failed', err);
      const resp = err.response?.data || {};
      if (resp.error === 'email_not_registered') {
        setError('Email not registered. Please log in or create an account.');
      } else if (resp.error === 'rate_limit_reached') {
        setError(resp.message || 'Rate limit reached.');
      } else if (resp.error === 'profanity_detected') {
        setError('Please remove inappropriate language from your feedback.');
      } else if (resp.error === 'duplicate_feedback') {
        setError(resp.message || 'Duplicate feedback detected.');
      } else {
        setError('Failed to submit feedback. Please try again.');
      }
    } finally {
      setSubmitting(false);
    }
  };

  const getCategoryInfo = (value) => categoryOptions.find(c => c.value === value) || categoryOptions[6];
  const formatDate = (dateString) => new Date(dateString).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });

  return (
    <div className="space-y-6">
      {/* Mobile Back Button Header */}
      <div className="flex items-center gap-3 lg:hidden">
        <button
          onClick={() => navigate('/dashboard?tab=home')}
          className="p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors"
        >
          <ArrowLeftIcon className="w-5 h-5 text-gray-600 dark:text-gray-300" />
        </button>
        <div>
          <h2 className="text-lg font-bold">My Feedback</h2>
          <p className="text-sm text-gray-500">Submit feedback and view your history</p>
        </div>
      </div>
      
      {/* Desktop Header */}
      <div className="hidden lg:block">
        <h2 className="text-lg font-bold">My Feedback</h2>
        <p className="text-sm text-gray-500">Submit feedback and view your history</p>
      </div>

      <form onSubmit={handleSubmit} className="space-y-3 bg-white dark:bg-gray-800 p-4 rounded-lg border">
        <div>
          <label className="text-xs font-medium">Category</label>
          <select value={category} onChange={e => setCategory(e.target.value)} className="w-full p-2 rounded-md mt-1 bg-gray-50 dark:bg-gray-700">
            {categoryOptions.map(opt => (
              <option key={opt.value} value={opt.value}>{opt.label}</option>
            ))}
          </select>
        </div>

        <div>
          <label className="text-xs font-medium">Rating *</label>
          <div className="mt-1">
            <StarRating rating={rating} onChange={setRating} />
          </div>
        </div>

        <div>
          <label className="text-xs font-medium">Message *</label>
          <textarea value={message} onChange={e => setMessage(e.target.value)} rows={4} className="w-full p-2 mt-1 rounded-md bg-gray-50 dark:bg-gray-700" placeholder="Share your thoughts (minimum 10 characters)..." />
        </div>

        {/* Error Message */}
        {error && (
          <div className="p-3 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-md">
            <p className="text-sm text-red-600 dark:text-red-400">{error}</p>
          </div>
        )}

        {/* Rate Limit Warning */}
        {!canSubmit && (
          <div className="p-3 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-md">
            <p className="text-sm text-amber-600 dark:text-amber-400">
              You have reached your feedback limit of {limit}.
              {nextAvailableAt ? (
                <> You can submit again on <strong>{formatNextAvailableDate(nextAvailableAt)}</strong>.</>
              ) : (
                <> Please try again after {cooldownDays} days.</>
              )}
            </p>
          </div>
        )}

        <div className="flex items-center justify-between">
          <div className="text-sm text-gray-500">Used: {used} / {limit} (cooldown {cooldownDays} days)</div>
          <div>
            <button 
              type="submit"
              disabled={!canSubmit || submitting} 
              className="px-4 py-2 bg-amber-600 hover:bg-amber-700 text-white rounded-md disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
            >
              {submitting ? 'Sending...' : 'Send Feedback'}
            </button>
          </div>
        </div>
      </form>

      <div className="bg-white dark:bg-gray-800 p-4 rounded-lg border">
        <h3 className="font-semibold mb-3">Feedback History</h3>
        {feedbackList.length === 0 ? (
          <div className="text-sm text-gray-500">No feedback yet.</div>
        ) : (
          <div className="space-y-3">
            {feedbackList.map(item => (
              <div key={item.id} className="p-3 border rounded-md bg-gray-50 dark:bg-gray-900">
                <div className="flex items-center justify-between mb-2">
                  <div className="text-sm font-medium">{item.feedback_type || 'General'}</div>
                  <div className="text-xs text-gray-500">{new Date(item.created_at).toLocaleString()}</div>
                </div>
                <div className="flex items-center justify-between">
                  <div className="text-sm text-gray-700 dark:text-gray-200">{item.message}</div>
                  <div className="text-sm text-amber-500 font-semibold">{item.rating}/5</div>
                </div>
              </div>
            ))}
          </div>
        )}
      </div>

      {/* Thank You Modal */}
      {showThankYou && submittedFeedback && (
        <FeedbackThankYouModal
          isOpen={true}
          onClose={() => setShowThankYou(false)}
          rating={submittedFeedback.rating || 0}
          message={submittedFeedback.message || ''}
          category={submittedFeedback.category || ''}
        />
      )}
    </div>
  );
};

export default UserFeedback;
