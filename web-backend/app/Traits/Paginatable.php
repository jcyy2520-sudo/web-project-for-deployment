<?php

namespace App\Traits;

use Illuminate\Pagination\Paginator;

/**
 * Paginatable Trait
 * Provides standardized pagination logic for list endpoints
 * Prevents N+1 queries by enforcing pagination on large datasets
 */
trait Paginatable
{
    /**
     * Get paginated results with standard format
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int $perPage Default items per page (max 100)
     * @param int $maxPerPage Hard limit for items per page
     * @return array
     */
    protected function paginate($query, int $perPage = 15, int $maxPerPage = 100): array
    {
        // Get pagination parameters from request
        $perPage = min((int) request()->query('per_page', $perPage), $maxPerPage);
        $page = max(1, (int) request()->query('page', 1));
        
        // Ensure reasonable defaults
        $perPage = max(1, min(100, $perPage));

        // Execute query with pagination
        $paginated = $query->paginate($perPage, ['*'], 'page', $page);

        return [
            'data' => $paginated->items(),
            'pagination' => [
                'total' => $paginated->total(),
                'count' => $paginated->count(),
                'per_page' => $paginated->perPage(),
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'from' => $paginated->firstItem(),
                'to' => $paginated->lastItem(),
                'has_more' => $paginated->hasMorePages(),
            ],
        ];
    }

    /**
     * Cursor-based pagination for real-time datasets
     * Better performance than offset pagination for large datasets
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $cursorColumn Column to paginate by (typically 'id')
     * @param int $perPage Items per page
     * @return array
     */
    protected function cursorPaginate($query, string $cursorColumn = 'id', int $perPage = 15): array
    {
        $perPage = min((int) request()->query('per_page', $perPage), 100);
        $cursor = request()->query('cursor');

        $paginated = $query->cursorPaginate($perPage, ['*'], 'cursor', $cursor)
            ->appends(request()->query());

        return [
            'data' => $paginated->items(),
            'pagination' => [
                'per_page' => $paginated->perPage(),
                'path' => $paginated->path(),
                'next_cursor' => $paginated->nextCursor()?->encode(),
                'prev_cursor' => $paginated->previousCursor()?->encode(),
                'has_more' => $paginated->hasPages(),
            ],
        ];
    }

    /**
     * Get safe offset/limit for batch operations
     * Prevents memory issues on extremely large datasets
     * 
     * @param int $maxLimit Absolute maximum allowed (default 1000)
     * @return array ['offset' => int, 'limit' => int]
     */
    protected function getOffsetLimit(int $maxLimit = 1000): array
    {
        $offset = max(0, (int) request()->query('offset', 0));
        $limit = max(1, min((int) request()->query('limit', 50), $maxLimit));

        return compact('offset', 'limit');
    }
}
