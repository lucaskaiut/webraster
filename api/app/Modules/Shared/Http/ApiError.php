<?php

namespace App\Modules\Shared\Http;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ApiError
{
    /**
     * @param  array<string, mixed>  $errors
     */
    public static function response(string $message, int $status, array $errors = []): JsonResponse
    {
        return new JsonResponse([
            'success' => false,
            'message' => $message,
            'errors' => (object) $errors,
        ], $status);
    }

    public static function shouldRender(Request $request): bool
    {
        return $request->is('api/*') || $request->expectsJson();
    }
}
