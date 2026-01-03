import { useState, useEffect } from 'react';
import axios from 'axios';
import { useNavigate } from 'react-router-dom';
import { StarIcon, XMarkIcon } from '@heroicons/react/24/outline';
import { StarIcon as StarIconSolid } from '@heroicons/react/24/solid';
import FeedbackThankYouModal from '../modals/FeedbackThankYouModal';

const UserFeedback = () => {
  const navigate = useNavigate();
  const [message, setMessage] = useState('');
  const [rating, setRating] = useState(0);
  const [submitting, setSubmitting] = useState(false);
  const [canSubmit, setCanSubmit] = useState(true);
  const [used, setUsed] = useState(0);
  const [limit, setLimit] = useState(2);
  const [nextAvailableAt, setNextAvailableAt] = useState(null);
  const [showThankYou, setShowThankYou] = useState(false);
  const [submittedFeedback, setSubmittedFeedback] = useState(null);
  const [error, setError] = useState(null);

  const loadRateLimit = async () => {
    try {
      const resp = await axios.get('/api/user/feedback/check-limit');
      const data = resp.data?.data || {};
      setCanSubmit(data.can_submit ?? true);
      setUsed(data.used ?? 0);
      setLimit(data.limit ?? 2);
      setNextAvailableAt(data.next_available_at ?? null);
    } catch (err) {
      console.warn('Failed to fetch rate limit');
    }
  };

  useEffect(() => {
    loadRateLimit();
  }, []);

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
      const nextDate = nextAvailableAt ? new Date(nextAvailableAt).toLocaleDateString() : 'in 7 days';
      setError(`Limit reached. Next available: ${nextDate}`);
      return;
    }

    try {
      setSubmitting(true);
      await axios.post('/api/user/feedback', { message, rating });
      
      setSubmittedFeedback({ message, rating });
      setMessage('');
      setRating(0);
      setShowThankYou(true);
      
      await loadRateLimit();
      
    } catch (err) {
      const resp = err.response?.data || {};
      if (resp.error === 'rate_limit_reached') {
        setError('You have reached your feedback limit.');
      } else if (resp.error === 'user_blocked') {
        setError('You are not allowed to submit feedback.');
      } else {
        setError(resp.message || 'Failed to submit. Please try again.');
      }
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <div className="w-full max-w-md mx-auto">
      {/* Header */}
      <div className="flex items-center gap-2 mb-4">
        <button
          onClick={() => navigate('/dashboard?tab=home')}
          className="p-1 hover:bg-gray-200 dark:hover:bg-gray-700 rounded"
        >
          <XMarkIcon className="w-5 h-5" />
        </button>
        <div>
          <h2 className="text-lg font-bold">Share Your Feedback</h2>
          <p className="text-xs text-gray-500">Help us improve ({used}/{limit})</p>
        </div>
      </div>

      {/* Form */}
      <form onSubmit={handleSubmit} className="space-y-4 bg-gray-50 dark:bg-gray-800 p-4 rounded-lg border border-gray-200 dark:border-gray-700">
        
        {/* Rating */}
        <div>
          <label className="block text-sm font-medium mb-2">How would you rate us?</label>
          <div className="flex gap-1">
            {[1, 2, 3, 4, 5].map((star) => (
              <button
                key={star}
                type="button"
                onClick={() => setRating(star)}
                className="transition-transform hover:scale-110 focus:outline-none"
              >
                {star <= rating ? (
                  <StarIconSolid className="w-6 h-6 text-amber-400" />
                ) : (
                  <StarIcon className="w-6 h-6 text-gray-300 dark:text-gray-600" />
                )}
              </button>
            ))}
          </div>
          {rating > 0 && <span className="text-xs text-amber-600 mt-1">{rating}/5 stars</span>}
        </div>

        {/* Message */}
        <div>
          <label className="block text-sm font-medium mb-2">Your Message</label>
          <textarea
            value={message}
            onChange={(e) => setMessage(e.target.value)}
            placeholder="Tell us what we can improve..."
            rows={4}
            className="w-full px-3 py-2 rounded-lg bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 focus:outline-none focus:border-amber-500 resize-none"
          />
          <p className="text-xs text-gray-500 mt-1">{message.length}/500 characters</p>
        </div>

        {/* Error */}
        {error && (
          <div className="text-xs text-red-600 dark:text-red-400 bg-red-50 dark:bg-red-950/30 p-2 rounded">
            {error}
          </div>
        )}

        {/* Submit Button */}
        <button
          type="submit"
          disabled={submitting || !canSubmit}
          className="w-full px-4 py-2 bg-amber-600 text-white rounded-lg font-medium hover:bg-amber-700 disabled:bg-gray-400 disabled:cursor-not-allowed transition-colors"
        >
          {submitting ? 'Sending...' : 'Send Feedback'}
        </button>
      </form>

      {/* Thank You Modal */}
      {showThankYou && submittedFeedback && (
        <FeedbackThankYouModal
          isOpen={true}
          onClose={() => setShowThankYou(false)}
          rating={submittedFeedback.rating || 0}
          message={submittedFeedback.message || ''}
        />
      )}
    </div>
  );
};

export default UserFeedback;
