import { useState, useEffect } from 'react';
import { useApi } from '../../hooks/useApi';
import axios from 'axios';
import {
  MagnifyingGlassIcon,
  ChevronLeftIcon,
  ChevronRightIcon,
  ChevronDownIcon,
  ChevronUpIcon,
  XMarkIcon,
  CheckIcon,
  StarIcon,
  TrashIcon,
  EyeIcon,
  ExclamationTriangleIcon,
  NoSymbolIcon,
  Squares2X2Icon,
  ListBulletIcon
} from '@heroicons/react/24/outline';
import LoadingSpinner from '../LoadingSpinner';
import ConfirmModal from '../ui/ConfirmModal';
import ReportFeedbackModal from '../modals/ReportFeedbackModal';

const AdminFeedback = () => {
  const { callApi } = useApi();
  const [feedback, setFeedback] = useState([]);
  const [stats, setStats] = useState({});
  const [loading, setLoading] = useState(true);
  const [currentPage, setCurrentPage] = useState(1);
  const [itemsPerPage] = useState(10);
  const [searchTerm, setSearchTerm] = useState('');
  const [sortBy, setSortBy] = useState('created_at');
  const [sortOrder, setSortOrder] = useState('desc');
  const [ratingFilter, setRatingFilter] = useState('all');
  const [testimonialFilter, setTestimonialFilter] = useState('all');
  const [total, setTotal] = useState(0);

  // View mode and card expansion
  const [viewMode, setViewMode] = useState('cards'); // 'cards' or 'table'
  const [allExpanded, setAllExpanded] = useState(true);
  const [expandedCards, setExpandedCards] = useState({});

  // Modal states
  const [selectedFeedback, setSelectedFeedback] = useState(null);
  const [showDetailModal, setShowDetailModal] = useState(false);
  const [showReportModal, setShowReportModal] = useState(false);
  const [reportTargetId, setReportTargetId] = useState(null);
  const [showConfirmBlock, setShowConfirmBlock] = useState(false);
  const [blockTargetId, setBlockTargetId] = useState(null);
  const [showConfirmDelete, setShowConfirmDelete] = useState(false);
  const [deleteTargetId, setDeleteTargetId] = useState(null);
  const [actionLoading, setActionLoading] = useState(null);

  // Load stats
  const loadStats = async () => {
    try {
      const result = await callApi(async () => {
        const response = await axios.get('/api/admin/feedback/stats', { timeout: 10000 });
        return response.data;
      });
      if (result.success || result.data) {
        const responseData = result.data || result;
        setStats(responseData.data || responseData || {});
      }
    } catch (error) {
      console.error('Failed to load stats:', error);
    }
  };

  // Load feedback
  const loadFeedback = async (page = 1) => {
    try {
      setLoading(true);
      const result = await callApi(async () => {
        // Parse sort value from "field-order" format
        let sortField = 'created_at';
        let sortOrder = 'desc';
        if (sortBy && sortBy.includes('-')) {
          const [field, order] = sortBy.split('-');
          sortField = field;
          sortOrder = order;
        }

        const params = {
          page,
          per_page: itemsPerPage,
          search: searchTerm,
          sort_by: sortField,
          sort_order: sortOrder,
        };
        if (ratingFilter !== 'all') params.rating = ratingFilter;
        if (testimonialFilter !== 'all') params.is_testimonial = testimonialFilter === 'true';

        const response = await axios.get('/api/admin/feedback', { params, timeout: 10000 });
        return response.data;
      });

      if (result.success || result.data) {
        const responseData = result.data || result;
        const feedbackItems = responseData?.data || [];
        const paginationData = responseData?.pagination || {};
        
        setFeedback(feedbackItems);
        setTotal(paginationData.total || 0);
        setCurrentPage(page);
        
        // Initialize expanded state for all cards
        const expandedState = {};
        feedbackItems.forEach(item => { expandedState[item.id] = allExpanded; });
        setExpandedCards(expandedState);
      }
      setLoading(false);
    } catch (error) {
      console.error('Failed to load feedback:', error);
      setLoading(false);
    }
  };

  useEffect(() => {
    loadStats();
    loadFeedback(1);
  }, [searchTerm, sortBy, sortOrder, ratingFilter, testimonialFilter]);

  const totalPages = Math.ceil(total / itemsPerPage);

  // Toggle individual card
  const toggleCard = (id) => {
    setExpandedCards(prev => ({ ...prev, [id]: !prev[id] }));
  };

  // Toggle all cards
  const toggleAllCards = () => {
    const newState = !allExpanded;
    setAllExpanded(newState);
    const newExpandedCards = {};
    feedback.forEach(item => { newExpandedCards[item.id] = newState; });
    setExpandedCards(newExpandedCards);
  };

  // Actions
  const handleToggleTestimonial = async (feedbackId, currentStatus) => {
    try {
      setActionLoading(feedbackId);
      await callApi(async () => {
        const response = await axios.put(`/api/admin/feedback/${feedbackId}/testimonial`, {
          is_testimonial: !currentStatus
        });
        return response.data;
      });
      loadFeedback(currentPage);
      loadStats();
    } catch (error) {
      console.error('Failed to update feedback:', error);
    } finally {
      setActionLoading(null);
    }
  };

  const openReportModal = (feedbackId) => {
    setReportTargetId(feedbackId);
    setShowReportModal(true);
  };

  const handleReportFeedback = async (payload) => {
    setShowReportModal(false);
    try {
      setActionLoading(reportTargetId);
      await callApi(async () => {
        await axios.post(`/api/admin/feedback/${reportTargetId}/report`, payload);
        return { success: true };
      });
      loadFeedback(currentPage);
    } catch (err) {
      console.error('Failed to report feedback', err);
    } finally {
      setActionLoading(null);
    }
  };

  const handleBlockUser = async () => {
    setShowConfirmBlock(false);
    try {
      setActionLoading(blockTargetId);
      await callApi(async () => {
        await axios.post(`/api/admin/feedback/${blockTargetId}/block-user`, {});
        return { success: true };
      });
      loadFeedback(currentPage);
    } catch (err) {
      console.error('Failed to block user', err);
    } finally {
      setActionLoading(null);
    }
  };

  const handleDeleteFeedback = async () => {
    setShowConfirmDelete(false);
    try {
      setActionLoading(deleteTargetId);
      await callApi(async () => {
        await axios.delete(`/api/admin/feedback/${deleteTargetId}`);
        return { success: true };
      });
      loadFeedback(currentPage);
      loadStats();
      if (selectedFeedback?.id === deleteTargetId) {
        setSelectedFeedback(null);
        setShowDetailModal(false);
      }
    } catch (error) {
      console.error('Failed to delete feedback:', error);
    } finally {
      setActionLoading(null);
    }
  };

  const renderStars = (rating, size = 14) => (
    <div className="flex">
      {[1, 2, 3, 4, 5].map((star) => (
        <svg
          key={star}
          width={size}
          height={size}
          viewBox="0 0 24 24"
          fill={star <= rating ? 'currentColor' : 'none'}
          stroke="currentColor"
          strokeWidth="2"
          className={star <= rating ? 'text-amber-400' : 'text-gray-600'}
        >
          <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z" />
        </svg>
      ))}
    </div>
  );

  if (loading && !feedback.length) {
    return (
      <div className="flex items-center justify-center h-64">
        <LoadingSpinner />
      </div>
    );
  }

  return (
    <div className="space-y-6">
      {/* Compact Stats Row */}
      <div className="grid grid-cols-2 lg:grid-cols-4 gap-3">
        <div className="bg-gray-800/50 border border-gray-700 rounded-xl p-4">
          <div className="flex items-center justify-between">
            <div>
              <p className="text-xs text-gray-400">Total</p>
              <p className="text-2xl font-bold text-amber-400">{stats.total_feedback || 0}</p>
            </div>
            <span className="text-2xl opacity-30">📊</span>
          </div>
        </div>
        <div className="bg-gray-800/50 border border-gray-700 rounded-xl p-4">
          <div className="flex items-center justify-between">
            <div>
              <p className="text-xs text-gray-400">Avg Rating</p>
              <p className="text-2xl font-bold text-blue-400">{stats.average_rating?.toFixed(1) || '0.0'}</p>
            </div>
            <span className="text-2xl opacity-30">⭐</span>
          </div>
        </div>
        <div className="bg-gray-800/50 border border-gray-700 rounded-xl p-4">
          <div className="flex items-center justify-between">
            <div>
              <p className="text-xs text-gray-400">5-Star %</p>
              <p className="text-2xl font-bold text-green-400">{stats.five_star_percentage || 0}%</p>
            </div>
            <span className="text-2xl opacity-30">✨</span>
          </div>
        </div>
        <div className="bg-gray-800/50 border border-gray-700 rounded-xl p-4">
          <div className="flex items-center justify-between">
            <div>
              <p className="text-xs text-gray-400">Testimonials</p>
              <p className="text-2xl font-bold text-purple-400">{stats.testimonials_count || 0}</p>
            </div>
            <span className="text-2xl opacity-30">🎯</span>
          </div>
        </div>
      </div>

      {/* Header with View Toggle */}
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
          <h2 className="text-xl font-bold text-amber-50">User Feedback</h2>
          <p className="text-xs text-gray-400 mt-0.5">Manage feedback and testimonials</p>
        </div>
        <div className="flex items-center gap-2">
          {/* View Mode Toggle */}
          <div className="flex bg-gray-800 rounded-lg p-1">
            <button
              onClick={() => setViewMode('cards')}
              className={`p-1.5 rounded ${viewMode === 'cards' ? 'bg-amber-600 text-white' : 'text-gray-400 hover:text-white'}`}
              title="Card View"
            >
              <Squares2X2Icon className="h-4 w-4" />
            </button>
            <button
              onClick={() => setViewMode('table')}
              className={`p-1.5 rounded ${viewMode === 'table' ? 'bg-amber-600 text-white' : 'text-gray-400 hover:text-white'}`}
              title="Table View"
            >
              <ListBulletIcon className="h-4 w-4" />
            </button>
          </div>
          {/* Expand/Collapse All (only in card view) */}
          {viewMode === 'cards' && (
            <button
              onClick={toggleAllCards}
              className="flex items-center gap-1.5 px-3 py-1.5 bg-gray-800 text-gray-300 rounded-lg text-xs hover:bg-gray-700 transition-colors"
            >
              {allExpanded ? (
                <>
                  <ChevronUpIcon className="h-3.5 w-3.5" />
                  Collapse All
                </>
              ) : (
                <>
                  <ChevronDownIcon className="h-3.5 w-3.5" />
                  Expand All
                </>
              )}
            </button>
          )}
        </div>
      </div>

      {/* Filters - Compact Row */}
      <div className="flex flex-wrap gap-3">
        <div className="relative flex-1 min-w-[200px]">
          <MagnifyingGlassIcon className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-500" />
          <input
            type="text"
            placeholder="Search email or message..."
            value={searchTerm}
            onChange={(e) => setSearchTerm(e.target.value)}
            className="w-full pl-9 pr-3 py-2 bg-gray-800 border border-gray-700 rounded-lg text-white text-sm focus:outline-none focus:border-amber-500"
          />
        </div>
        <select
          value={ratingFilter}
          onChange={(e) => setRatingFilter(e.target.value)}
          className="px-3 py-2 bg-gray-800 border border-gray-700 rounded-lg text-white text-sm focus:outline-none focus:border-amber-500"
        >
          <option value="all">All Ratings</option>
          <option value="5">5 Stars</option>
          <option value="4">4 Stars</option>
          <option value="3">3 Stars</option>
          <option value="2">2 Stars</option>
          <option value="1">1 Star</option>
        </select>
        <select
          value={testimonialFilter}
          onChange={(e) => setTestimonialFilter(e.target.value)}
          className="px-3 py-2 bg-gray-800 border border-gray-700 rounded-lg text-white text-sm focus:outline-none focus:border-amber-500"
        >
          <option value="all">All Types</option>
          <option value="true">Testimonials</option>
          <option value="false">Regular</option>
        </select>
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

      {/* Content */}
      {feedback.length > 0 ? (
        <>
          {viewMode === 'cards' ? (
            /* Card View */
            <div className="space-y-3">
              {feedback.map((item) => (
                <div
                  key={item.id}
                  className={`bg-gray-800/50 border rounded-xl overflow-hidden transition-all ${
                    item.is_testimonial ? 'border-green-500/30' : 'border-gray-700'
                  }`}
                >
                  {/* Card Header - Always Visible */}
                  <div 
                    className="flex items-center justify-between p-4 cursor-pointer hover:bg-gray-800/30"
                    onClick={() => toggleCard(item.id)}
                  >
                    <div className="flex items-center gap-3 min-w-0 flex-1">
                      {/* Avatar */}
                      <div className="w-8 h-8 rounded-full bg-amber-600/20 flex items-center justify-center text-amber-400 text-xs font-medium flex-shrink-0">
                        {item.email?.charAt(0).toUpperCase() || '?'}
                      </div>
                      <div className="min-w-0 flex-1">
                        <div className="flex items-center gap-2 flex-wrap">
                          <span className="text-sm text-white truncate">{item.email}</span>
                          {item.is_testimonial && (
                            <span className="text-xs bg-green-500/20 text-green-400 px-2 py-0.5 rounded">Featured</span>
                          )}
                        </div>
                        <div className="flex items-center gap-2 mt-0.5">
                          {renderStars(item.rating, 12)}
                          <span className="text-xs text-gray-500">
                            {new Date(item.created_at).toLocaleDateString('en-US', { month: 'short', day: 'numeric' })}
                          </span>
                        </div>
                      </div>
                    </div>
                    <div className="flex items-center gap-2">
                      {actionLoading === item.id && (
                        <div className="w-4 h-4 border-2 border-amber-400 border-t-transparent rounded-full animate-spin" />
                      )}
                      {expandedCards[item.id] ? (
                        <ChevronUpIcon className="h-4 w-4 text-gray-400" />
                      ) : (
                        <ChevronDownIcon className="h-4 w-4 text-gray-400" />
                      )}
                    </div>
                  </div>

                  {/* Card Body - Expandable */}
                  {expandedCards[item.id] && (
                    <div className="px-4 pb-4 border-t border-gray-700/50">
                      <p className="text-sm text-gray-300 mt-3 mb-4">{item.message}</p>
                      
                      {/* Actions */}
                      <div className="flex flex-wrap gap-2">
                        <button
                          onClick={(e) => { e.stopPropagation(); setSelectedFeedback(item); setShowDetailModal(true); }}
                          className="flex items-center gap-1 px-2.5 py-1.5 text-xs bg-gray-700 text-gray-300 rounded-lg hover:bg-gray-600"
                        >
                          <EyeIcon className="h-3.5 w-3.5" /> View
                        </button>
                        <button
                          onClick={(e) => { e.stopPropagation(); handleToggleTestimonial(item.id, item.is_testimonial); }}
                          className={`flex items-center gap-1 px-2.5 py-1.5 text-xs rounded-lg ${
                            item.is_testimonial
                              ? 'bg-green-500/20 text-green-400 hover:bg-green-500/30'
                              : 'bg-gray-700 text-gray-300 hover:bg-gray-600'
                          }`}
                        >
                          <CheckIcon className="h-3.5 w-3.5" /> {item.is_testimonial ? 'Featured' : 'Feature'}
                        </button>
                        <button
                          onClick={(e) => { e.stopPropagation(); openReportModal(item.id); }}
                          className="flex items-center gap-1 px-2.5 py-1.5 text-xs bg-gray-700 text-yellow-400 rounded-lg hover:bg-gray-600"
                        >
                          <ExclamationTriangleIcon className="h-3.5 w-3.5" /> Report
                        </button>
                        <button
                          onClick={(e) => { e.stopPropagation(); setBlockTargetId(item.id); setShowConfirmBlock(true); }}
                          className="flex items-center gap-1 px-2.5 py-1.5 text-xs bg-gray-700 text-orange-400 rounded-lg hover:bg-gray-600"
                        >
                          <NoSymbolIcon className="h-3.5 w-3.5" /> Block
                        </button>
                        <button
                          onClick={(e) => { e.stopPropagation(); setDeleteTargetId(item.id); setShowConfirmDelete(true); }}
                          className="flex items-center gap-1 px-2.5 py-1.5 text-xs bg-gray-700 text-red-400 rounded-lg hover:bg-gray-600"
                        >
                          <TrashIcon className="h-3.5 w-3.5" /> Delete
                        </button>
                      </div>
                    </div>
                  )}
                </div>
              ))}
            </div>
          ) : (
            /* Table View */
            <div className="bg-gray-800/50 border border-gray-700 rounded-xl overflow-hidden">
              <div className="overflow-x-auto">
                <table className="w-full text-sm">
                  <thead className="bg-gray-800 border-b border-gray-700">
                    <tr>
                      <th className="px-4 py-3 text-left text-xs font-medium text-gray-400">User</th>
                      <th className="px-4 py-3 text-left text-xs font-medium text-gray-400">Rating</th>
                      <th className="px-4 py-3 text-left text-xs font-medium text-gray-400">Message</th>
                      <th className="px-4 py-3 text-left text-xs font-medium text-gray-400">Status</th>
                      <th className="px-4 py-3 text-left text-xs font-medium text-gray-400">Date</th>
                      <th className="px-4 py-3 text-right text-xs font-medium text-gray-400">Actions</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-gray-700">
                    {feedback.map((item) => (
                      <tr key={item.id} className="hover:bg-gray-800/30">
                        <td className="px-4 py-3">
                          <span className="text-gray-300 truncate block max-w-[150px]">{item.email}</span>
                        </td>
                        <td className="px-4 py-3">{renderStars(item.rating, 12)}</td>
                        <td className="px-4 py-3">
                          <span className="text-gray-400 truncate block max-w-[200px]">{item.message}</span>
                        </td>
                        <td className="px-4 py-3">
                          {item.is_testimonial ? (
                            <span className="text-xs bg-green-500/20 text-green-400 px-2 py-0.5 rounded">Featured</span>
                          ) : (
                            <span className="text-xs bg-gray-700 text-gray-400 px-2 py-0.5 rounded">Regular</span>
                          )}
                        </td>
                        <td className="px-4 py-3 text-xs text-gray-500">
                          {new Date(item.created_at).toLocaleDateString()}
                        </td>
                        <td className="px-4 py-3">
                          <div className="flex items-center justify-end gap-1">
                            <button
                              onClick={() => { setSelectedFeedback(item); setShowDetailModal(true); }}
                              className="p-1.5 text-gray-400 hover:text-amber-400 rounded"
                              title="View"
                            >
                              <EyeIcon className="h-4 w-4" />
                            </button>
                            <button
                              onClick={() => handleToggleTestimonial(item.id, item.is_testimonial)}
                              className={`p-1.5 rounded ${item.is_testimonial ? 'text-green-400' : 'text-gray-400 hover:text-green-400'}`}
                              title="Toggle Featured"
                            >
                              <CheckIcon className="h-4 w-4" />
                            </button>
                            <button
                              onClick={() => { setDeleteTargetId(item.id); setShowConfirmDelete(true); }}
                              className="p-1.5 text-gray-400 hover:text-red-400 rounded"
                              title="Delete"
                            >
                              <TrashIcon className="h-4 w-4" />
                            </button>
                          </div>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </div>
          )}

          {/* Pagination */}
          {totalPages > 1 && (
            <div className="flex items-center justify-between">
              <span className="text-xs text-gray-500">
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
                <span className="px-3 text-sm text-gray-400">{currentPage}/{totalPages}</span>
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
        </>
      ) : (
        <div className="text-center py-12 bg-gray-800/30 rounded-xl border border-gray-700">
          <StarIcon className="h-10 w-10 text-gray-600 mx-auto mb-3" />
          <p className="text-gray-400 text-sm">No feedback found</p>
        </div>
      )}

      {/* Detail Modal */}
      {showDetailModal && selectedFeedback && (
        <div className="fixed inset-0 bg-black/70 flex items-center justify-center z-50 p-4">
          <div className="bg-gray-900 border border-gray-700 rounded-xl w-full max-w-lg max-h-[80vh] overflow-y-auto">
            <div className="flex items-center justify-between p-4 border-b border-gray-700 sticky top-0 bg-gray-900">
              <h3 className="text-sm font-semibold text-white">Feedback Details</h3>
              <button onClick={() => setShowDetailModal(false)} className="text-gray-400 hover:text-white">
                <XMarkIcon className="h-5 w-5" />
              </button>
            </div>
            <div className="p-4 space-y-4">
              <div>
                <p className="text-xs text-gray-400 mb-1">Email</p>
                <p className="text-white">{selectedFeedback.email}</p>
              </div>
              <div>
                <p className="text-xs text-gray-400 mb-1">Rating</p>
                <div className="flex items-center gap-2">
                  {renderStars(selectedFeedback.rating, 18)}
                  <span className="text-amber-400">{selectedFeedback.rating}/5</span>
                </div>
              </div>
              <div>
                <p className="text-xs text-gray-400 mb-1">Message</p>
                <p className="text-gray-300 whitespace-pre-wrap">{selectedFeedback.message}</p>
              </div>
              <div>
                <p className="text-xs text-gray-400 mb-1">Date</p>
                <p className="text-gray-400">{new Date(selectedFeedback.created_at).toLocaleString()}</p>
              </div>
              <div className="pt-3 border-t border-gray-700">
                <p className="text-xs text-gray-400 mb-2">Status</p>
                {selectedFeedback.is_testimonial ? (
                  <span className="inline-flex items-center px-3 py-1 rounded-full text-sm bg-green-500/20 text-green-400">
                    <CheckIcon className="h-4 w-4 mr-1" /> Featured Testimonial
                  </span>
                ) : (
                  <span className="inline-flex items-center px-3 py-1 rounded-full text-sm bg-gray-700 text-gray-400">
                    Regular Feedback
                  </span>
                )}
              </div>
            </div>
            <div className="flex gap-2 p-4 border-t border-gray-700 bg-gray-800/50">
              <button
                onClick={() => setShowDetailModal(false)}
                className="flex-1 px-4 py-2 border border-gray-700 text-gray-300 rounded-lg hover:bg-gray-800 text-sm"
              >
                Close
              </button>
              <button
                onClick={() => handleToggleTestimonial(selectedFeedback.id, selectedFeedback.is_testimonial)}
                className={`flex-1 px-4 py-2 rounded-lg text-sm font-medium ${
                  selectedFeedback.is_testimonial
                    ? 'bg-red-600/20 text-red-400 hover:bg-red-600/30'
                    : 'bg-green-600/20 text-green-400 hover:bg-green-600/30'
                }`}
              >
                {selectedFeedback.is_testimonial ? 'Remove Featured' : 'Mark as Featured'}
              </button>
            </div>
          </div>
        </div>
      )}

      {/* Confirm Modals */}
      <ConfirmModal
        isOpen={showConfirmBlock}
        title="Block User"
        message="Block this user from submitting feedback?"
        confirmText="Block"
        confirmColor="orange"
        onConfirm={handleBlockUser}
        onCancel={() => setShowConfirmBlock(false)}
      />

      <ConfirmModal
        isOpen={showConfirmDelete}
        title="Delete Feedback"
        message="Are you sure you want to delete this feedback? This action cannot be undone."
        confirmText="Delete"
        confirmColor="red"
        onConfirm={handleDeleteFeedback}
        onCancel={() => setShowConfirmDelete(false)}
      />

      <ReportFeedbackModal
        isOpen={showReportModal}
        onClose={() => setShowReportModal(false)}
        onSubmit={handleReportFeedback}
      />
    </div>
  );
};

export default AdminFeedback;
