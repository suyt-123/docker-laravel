<?php

namespace App\Support;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;

class ApiResponse
{
    /**
     * @param  array<string, mixed>  $meta
     * @param  array<string, mixed>  $links
     */
    public static function success(
        mixed $data = null,
        array $meta = [],
        array $links = [],
        ?string $message = null,
        int $status = 200,
    ): JsonResponse {
        return response()->json([
            'data' => $data,
            'meta' => $meta,
            'links' => $links,
            'message' => $message,
        ], $status);
    }

    /**
     * @param  array<string, mixed>  $errors
     * @param  array<string, mixed>  $meta
     */
    public static function error(
        string $message,
        string $code,
        array $errors = [],
        array $meta = [],
        int $status = 400,
    ): JsonResponse {
        return response()->json([
            'message' => $message,
            'code' => $code,
            'errors' => $errors,
            'meta' => $meta,
        ], $status);
    }

    /**
     * @return array<string, mixed>
     */
    public static function paginationMeta(LengthAwarePaginator $paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'from' => $paginator->firstItem(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'to' => $paginator->lastItem(),
            'total' => $paginator->total(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function paginationLinks(LengthAwarePaginator $paginator): array
    {
        return [
            'first' => $paginator->url(1),
            'last' => $paginator->url($paginator->lastPage()),
            'prev' => $paginator->previousPageUrl(),
            'next' => $paginator->nextPageUrl(),
        ];
    }
}
