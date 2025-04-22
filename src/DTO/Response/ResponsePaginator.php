<?php

namespace App\DTO\Response;

class ResponsePaginator
{
    public static function paginated(array $items, int $currentPage, int $totalItems, int $perPage, string $message = 'Data fetched successfully'): array
    {
        $totalPages = (int) ceil($totalItems / $perPage);

        return [
            'success' => true,
            'message' => $message,
            'data' => $items,
            'meta' => [
                'page' => $currentPage,
                'per_page' => $perPage,
                'total_items' => $totalItems,
                'total_pages' => $totalPages,
            ]
        ];
    }

    public static function error(string $message, array $errors = [], int $code = 400): array
    {
        return [
            'success' => false,
            'message' => $message,
            'errors' => $errors,
            'code' => $code
        ];
    }
}