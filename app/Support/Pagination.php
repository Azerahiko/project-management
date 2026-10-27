<?php

namespace App\Support;

use Illuminate\Http\Request;

class Pagination
{
    /**
     * Resolve a safe page size from the request.
     */
    public static function perPage(Request $request, int $default = 15, int $max = 100): int
    {
        return max(1, min((int) $request->integer('per_page', $default), $max));
    }

    /**
     * Resolve a safe page number from the request.
     */
    public static function page(Request $request): int
    {
        return max(1, (int) $request->integer('page', 1));
    }

    /**
     * Resolve a safe result limit from the request.
     */
    public static function limit(Request $request, int $default = 50, int $max = 100): int
    {
        return max(1, min((int) $request->integer('limit', $default), $max));
    }
}
