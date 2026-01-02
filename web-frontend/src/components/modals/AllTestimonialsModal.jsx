import React, { useState, useEffect } from 'react';
import axios from 'axios';
import { XMarkIcon, ChevronLeftIcon, ChevronRightIcon, StarIcon } from '@heroicons/react/24/solid';

const AllTestimonialsModal = ({ isOpen, onClose, isDarkMode = false }) => {
  const [testimonials, setTestimonials] = useState([]);
  const [loading, setLoading] = useState(false);
  const [currentPage, setCurrentPage] = useState(1);
  const [totalPages, setTotalPages] = useState(1);
  const [itemsPerPage] = useState(6);

  useEffect(() => {
    if (isOpen) {
      loadTestimonials(1);
    }
  }, [isOpen]);

  const loadTestimonials = async (page = 1) => {
    try {
      setLoading(true);
      const response = await axios.get('/api/testimonials/feedbacks/all', {
        params: {
          page,
          per_page: itemsPerPage
        }
      });

      const data = response.data?.data || [];
      const pagination = response.data?.pagination || {};

      setTestimonials(data);
      setCurrentPage(page);
      setTotalPages(pagination.last_page || 1);
    } catch (error) {
      console.error('Failed to load testimonials:', error);
    } finally {
      setLoading(false);
    }
  };

  const handlePrevPage = () => {
    if (currentPage > 1) {
      loadTestimonials(currentPage - 1);
    }
  };

  const handleNextPage = () => {
    if (currentPage < totalPages) {
      loadTestimonials(currentPage + 1);
    }
  };

  const renderStars = (rating) => {
    return (
      <div className="flex items-center gap-1">
        {[1, 2, 3, 4, 5].map((star) => (
          <StarIcon
            key={star}
            className={`w-4 h-4 ${star <= rating ? 'text-amber-400' : 'text-gray-300 dark:text-gray-600'}`}
          />
        ))}
      </div>
    );
  };

  if (!isOpen) return null;

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4" onClick={onClose}>
      {/* Semi-transparent backdrop with blur effect */}
      <div className={`absolute inset-0 transition-opacity duration-300 ${
        isDarkMode 
          ? 'bg-black/40 backdrop-blur-sm' 
          : 'bg-black/30 backdrop-blur-sm'
      }`} onClick={onClose} />
      
      {/* Modal Container */}
      <div 
        className={`relative rounded-2xl shadow-2xl max-w-5xl w-full max-h-[85vh] overflow-hidden transform transition-all duration-300 ${
          isDarkMode 
            ? 'bg-gradient-to-br from-gray-900 via-gray-800 to-gray-900 border border-gray-700/50' 
            : 'bg-gradient-to-br from-white via-gray-50 to-white border border-gray-200/50'
        }`}
        onClick={(e) => e.stopPropagation()}
      >
        {/* Header Section */}
        <div className={`relative px-8 py-6 border-b ${
          isDarkMode 
            ? 'bg-gradient-to-r from-gray-800/50 to-gray-900/50 border-gray-700/30' 
            : 'bg-gradient-to-r from-blue-50/50 to-indigo-50/50 border-gray-100'
        }`}>
          <div className="flex items-center justify-between">
            <div className="flex-1">
              <h2 className={`text-3xl font-bold mb-1 ${
                isDarkMode ? 'text-white' : 'text-gray-900'
              }`}>
                ✨ Client Testimonials
              </h2>
              <p className={`text-sm ${
                isDarkMode ? 'text-gray-400' : 'text-gray-600'
              }`}>
                What our satisfied clients say about us
              </p>
            </div>
            <button
              onClick={onClose}
              className={`ml-4 p-2 rounded-lg transition-all duration-200 hover:scale-110 ${
                isDarkMode
                  ? 'bg-gray-700/50 hover:bg-gray-600 text-gray-300'
                  : 'bg-gray-200/50 hover:bg-gray-300 text-gray-700'
              }`}
            >
              <XMarkIcon className="h-6 w-6" />
            </button>
          </div>
        </div>

        {/* Content Section */}
        <div className="overflow-y-auto" style={{ maxHeight: 'calc(85vh - 200px)' }}>
          <div className="p-8">
            {loading ? (
              <div className="flex justify-center items-center py-16">
                <div className="relative w-12 h-12">
                  <div className="absolute inset-0 rounded-full border-4 border-transparent border-t-amber-500 border-r-amber-500 animate-spin"></div>
                </div>
              </div>
            ) : testimonials.length === 0 ? (
              <div className={`text-center py-16 ${
                isDarkMode ? 'text-gray-400' : 'text-gray-500'
              }`}>
                <p className="text-lg">No testimonials available yet</p>
              </div>
            ) : (
              <div className="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
                {testimonials.map((item, index) => (
                  <div
                    key={item.id}
                    className={`group rounded-xl p-5 transition-all duration-300 hover:shadow-lg hover:scale-102 backdrop-blur-xs ${
                      isDarkMode
                        ? 'bg-gradient-to-br from-gray-800/50 to-gray-700/30 border border-gray-700/50 hover:border-amber-500/50'
                        : 'bg-gradient-to-br from-white/80 to-gray-50/80 border border-gray-200/50 hover:border-blue-300/50'
                    }`}
                    style={{ 
                      animation: `fadeInUp 0.4s ease-out ${index * 80}ms forwards`,
                      opacity: 0,
                      animationFillMode: 'forwards'
                    }}
                  >
                    {/* Rating Stars at Top */}
                    <div className="mb-3 flex items-center justify-between">
                      {renderStars(item.rating)}
                      <span className={`text-xs font-bold px-2 py-1 rounded-full ${
                        isDarkMode
                          ? 'bg-amber-500/20 text-amber-300'
                          : 'bg-amber-100 text-amber-700'
                      }`}>
                        {item.rating}/5
                      </span>
                    </div>

                    {/* Message */}
                    <p className={`text-sm leading-relaxed mb-4 line-clamp-4 italic ${
                      isDarkMode ? 'text-gray-300' : 'text-gray-700'
                    }`}>
                      "{item.message}"
                    </p>

                    {/* Divider */}
                    <div className={`h-px mb-4 ${
                      isDarkMode ? 'bg-gray-700/30' : 'bg-gray-200/50'
                    }`} />

                    {/* Author Info */}
                    <div className="flex items-center justify-between">
                      <div className="flex items-center gap-2 flex-1">
                        <div className={`w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold transition-transform duration-300 group-hover:scale-110 ${
                          isDarkMode
                            ? 'bg-gradient-to-br from-amber-500 to-amber-600 text-white'
                            : 'bg-gradient-to-br from-blue-400 to-blue-500 text-white'
                        }`}>
                          {item.masked_initial || item.privacy_safe_username?.charAt(0).toUpperCase() || 'U'}
                        </div>
                        <div>
                          <p className={`font-semibold text-xs ${
                            isDarkMode ? 'text-gray-200' : 'text-gray-900'
                          }`}>
                            {item.privacy_safe_username || 'Anonymous'}
                          </p>
                          {item.feedback_type && (
                            <span className={`text-xs ${
                              isDarkMode
                                ? 'text-gray-500'
                                : 'text-gray-500'
                            }`}>
                              {item.feedback_type.replace(/_/g, ' ')}
                            </span>
                          )}
                        </div>
                      </div>
                    </div>
                  </div>
                ))}
              </div>
            )}
          </div>
        </div>

        {/* Footer Section with Pagination */}
        {!loading && testimonials.length > 0 && (
          <div className={`border-t px-8 py-5 flex items-center justify-between ${
            isDarkMode 
              ? 'bg-gradient-to-r from-gray-800/50 to-gray-900/50 border-gray-700/30' 
              : 'bg-gradient-to-r from-blue-50/50 to-indigo-50/50 border-gray-100'
          }`}>
            <p className={`text-sm font-medium ${
              isDarkMode ? 'text-gray-400' : 'text-gray-600'
            }`}>
              Page <span className="font-bold text-amber-500">{currentPage}</span> of <span className="font-bold text-amber-500">{totalPages}</span>
            </p>

            <div className="flex gap-3">
              <button
                onClick={handlePrevPage}
                disabled={currentPage === 1}
                className={`p-2 rounded-lg transition-all duration-200 ${
                  currentPage === 1
                    ? isDarkMode
                      ? 'bg-gray-700/30 text-gray-600 cursor-not-allowed'
                      : 'bg-gray-200/30 text-gray-400 cursor-not-allowed'
                    : isDarkMode
                    ? 'bg-gray-700/50 hover:bg-gray-600 text-gray-300 hover:scale-110'
                    : 'bg-gray-200/50 hover:bg-gray-300 text-gray-700 hover:scale-110'
                }`}
              >
                <ChevronLeftIcon className="h-5 w-5" />
              </button>

              <button
                onClick={handleNextPage}
                disabled={currentPage === totalPages}
                className={`p-2 rounded-lg transition-all duration-200 ${
                  currentPage === totalPages
                    ? isDarkMode
                      ? 'bg-gray-700/30 text-gray-600 cursor-not-allowed'
                      : 'bg-gray-200/30 text-gray-400 cursor-not-allowed'
                    : isDarkMode
                    ? 'bg-gray-700/50 hover:bg-gray-600 text-gray-300 hover:scale-110'
                    : 'bg-gray-200/50 hover:bg-gray-300 text-gray-700 hover:scale-110'
                }`}
              >
                <ChevronRightIcon className="h-5 w-5" />
              </button>
            </div>
          </div>
        )}
      </div>

      <style>{`
        @keyframes fadeInUp {
          from {
            opacity: 0;
            transform: translateY(20px);
          }
          to {
            opacity: 1;
            transform: translateY(0);
          }
        }

        @keyframes fadeIn {
          from {
            opacity: 0;
          }
          to {
            opacity: 1;
          }
        }
      `}</style>
    </div>
  );
};

export default AllTestimonialsModal;
