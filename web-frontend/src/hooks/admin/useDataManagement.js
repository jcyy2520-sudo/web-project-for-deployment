import { useMemo } from 'react';

/**
 * Hook to filter and search data
 */
export const useDataFilter = (data, searchTerm, searchFields = [], filters = {}) => {
  const filteredData = useMemo(() => {
    let result = Array.isArray(data) ? [...data] : [];

    // Apply search
    if (searchTerm && searchFields.length > 0) {
      const searchLower = searchTerm.toLowerCase();
      result = result.filter(item =>
        searchFields.some(field => {
          const value = field.split('.').reduce((obj, f) => obj?.[f], item) || '';
          return String(value).toLowerCase().includes(searchLower);
        })
      );
    }

    // Apply filters
    Object.entries(filters).forEach(([key, value]) => {
      if (value && value !== 'all') {
        result = result.filter(item => item[key] === value);
      }
    });

    return result;
  }, [data, searchTerm, searchFields, filters]);

  return filteredData;
};

/**
 * Hook to sort data
 */
export const useDataSort = (data, sortConfig = { key: null, direction: 'asc' }) => {
  const sortedData = useMemo(() => {
    if (!sortConfig.key) return data;

    return [...data].sort((a, b) => {
      const aVal = a[sortConfig.key] || '';
      const bVal = b[sortConfig.key] || '';

      if (aVal < bVal) return sortConfig.direction === 'asc' ? -1 : 1;
      if (aVal > bVal) return sortConfig.direction === 'asc' ? 1 : -1;
      return 0;
    });
  }, [data, sortConfig]);

  return sortedData;
};

/**
 * Hook to paginate data
 */
export const usePagination = (data, itemsPerPage = 10, currentPage = 1) => {
  const paginatedData = useMemo(() => {
    const startIndex = (currentPage - 1) * itemsPerPage;
    return data.slice(startIndex, startIndex + itemsPerPage);
  }, [data, itemsPerPage, currentPage]);

  const totalPages = Math.ceil(data.length / itemsPerPage);

  return {
    paginatedData,
    totalPages,
    hasNextPage: currentPage < totalPages,
    hasPrevPage: currentPage > 1
  };
};

export default useDataFilter;
