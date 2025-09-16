<?php

declare(strict_types=1);

namespace App\Http;

/**
 * Static builder for unified error JSON responses.
 */
final class ErrorResponse
{
    /**
     * Creates a standardized error response array.
     *
     * @param string $code    Error code identifier
     * @param string $message Human-readable error message
     * @param array<string, mixed>  $details Optional additional error details
     *
     * @return array<string, array<string, mixed>> The formatted error response
     */
    public static function from(string $code, string $message, array $details = []): array
    {
        return [
            'error' => [
                'code' => $code,
                'message' => $message,
                'details' => $details,
            ],
        ];
    }
}
