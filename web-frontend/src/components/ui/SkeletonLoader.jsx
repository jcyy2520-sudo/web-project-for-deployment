import React from 'react';

/**
 * Reusable skeleton loader component for progressive enhancement
 * Shows placeholder content while data is loading
 */
export const StatCardSkeleton = ({ count = 1, isDarkMode = true }) => (
  <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
    {Array(count).fill(0).map((_, i) => (
      <div key={i} className={`${isDarkMode ? 'bg-gray-900 border-amber-500/20' : 'bg-white border-amber-300/40'} border rounded-lg shadow p-4`}>
        <div className="flex items-center justify-between">
          <div className="flex-1">
            <div className={`h-3 ${isDarkMode ? 'bg-gray-700' : 'bg-gray-200'} rounded w-20 mb-2 animate-pulse`}></div>
            <div className={`h-6 ${isDarkMode ? 'bg-gray-700' : 'bg-gray-200'} rounded w-12 animate-pulse`}></div>
            <div className={`h-2 ${isDarkMode ? 'bg-gray-700' : 'bg-gray-200'} rounded w-24 mt-2 animate-pulse`}></div>
          </div>
          <div className={`w-10 h-10 ${isDarkMode ? 'bg-gray-700' : 'bg-gray-200'} rounded-lg flex-shrink-0 animate-pulse`}></div>
        </div>
      </div>
    ))}
  </div>
);

/**
 * Table row skeleton loader
 */
export const TableRowSkeleton = ({ count = 5, columns = 4, isDarkMode = true }) => (
  <div className="space-y-2">
    {Array(count).fill(0).map((_, rowIdx) => (
      <div key={rowIdx} className={`flex gap-3 p-4 ${isDarkMode ? 'bg-gray-800/30' : 'bg-gray-100'} rounded-lg`}>
        {Array(columns).fill(0).map((_, colIdx) => (
          <div key={colIdx} className="flex-1">
            <div className={`h-4 ${isDarkMode ? 'bg-gray-700' : 'bg-gray-200'} rounded w-full animate-pulse`}></div>
          </div>
        ))}
      </div>
    ))}
  </div>
);

/**
 * Chart skeleton loader
 */
export const ChartSkeleton = ({ isDarkMode = true }) => (
  <div className={`${isDarkMode ? 'bg-gray-900 border-amber-500/20' : 'bg-white border-amber-300/40'} border rounded-lg shadow p-4`}>
    <div className={`h-4 ${isDarkMode ? 'bg-gray-700' : 'bg-gray-200'} rounded w-32 mb-4 animate-pulse`}></div>
    <div className="space-y-3">
      {Array(5).fill(0).map((_, i) => (
        <div key={i} className="flex items-center gap-2">
          <div className={`h-3 ${isDarkMode ? 'bg-gray-700' : 'bg-gray-200'} rounded w-20 animate-pulse`}></div>
          <div className={`flex-1 h-3 ${isDarkMode ? 'bg-gray-700' : 'bg-gray-200'} rounded animate-pulse`}></div>
          <div className={`h-3 ${isDarkMode ? 'bg-gray-700' : 'bg-gray-200'} rounded w-8 animate-pulse`}></div>
        </div>
      ))}
    </div>
  </div>
);

/**
 * List item skeleton loader
 */
export const ListItemSkeleton = ({ count = 3 }) => (
  <div className="space-y-2">
    {Array(count).fill(0).map((_, i) => (
      <div key={i} className="flex items-center gap-3 p-3 bg-gray-800/30 rounded-lg">
        <div className="w-10 h-10 bg-gray-700 rounded-full flex-shrink-0"></div>
        <div className="flex-1">
          <div className="h-4 bg-gray-700 rounded w-32 mb-1"></div>
          <div className="h-3 bg-gray-700 rounded w-24"></div>
        </div>
      </div>
    ))}
  </div>
);

/**
 * Generic content skeleton
 */
export const ContentSkeleton = ({ lines = 3 }) => (
  <div className="space-y-2">
    {Array(lines).fill(0).map((_, i) => (
      <div key={i} className="h-4 bg-gray-700 rounded" style={{
        width: i === lines - 1 ? '70%' : '100%'
      }}></div>
    ))}
  </div>
);

export default {
  StatCardSkeleton,
  TableRowSkeleton,
  ChartSkeleton,
  ListItemSkeleton,
  ContentSkeleton
};
