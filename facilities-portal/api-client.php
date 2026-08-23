<?php

// --------------------------------------------------
// Facilities Toolbox - API Client
// --------------------------------------------------
//
// All PHP-to-C# communication passes through this
// helper. The portal never talks directly to
// PostgreSQL; ASP.NET Core remains the system of
// record for business rules and persistence.
// --------------------------------------------------

require_once __DIR__ . '/config.php';

/**
 * Send an HTTP request to the Facilities API.
 *
 * @param string     $method  HTTP method such as GET, POST or PATCH.
 * @param string     $path    API path beginning with a forward slash.
 * @param array|null $payload Optional JSON request body.
 *
 * @return array{
 *     success: bool,
 *     status: int,
 *     data: mixed,
 *     message: ?string
 * }
 */
function facilitiesApiRequest(
    string $method,
    string $path,
    ?array $payload = null
): array {
    global $apiBaseUrl;

    // --------------------------------------------------
    // Build request URL and headers
    // --------------------------------------------------

    $url = $apiBaseUrl . '/' . ltrim($path, '/');

    $headers = [
        'Accept: application/json',
        'Content-Type: application/json'
    ];

    $options = [
        'http' => [
            'method' => strtoupper($method),
            'header' => implode("\r\n", $headers),
            'ignore_errors' => true,
            'timeout' => 5
        ]
    ];

    if ($payload !== null) {
        $encodedPayload = json_encode($payload);

        if ($encodedPayload === false) {
            return [
                'success' => false,
                'status' => 0,
                'data' => null,
                'message' => 'The request payload could not be encoded.'
            ];
        }

        $options['http']['content'] = $encodedPayload;
    }

    // --------------------------------------------------
    // Send request
    // --------------------------------------------------

    $context = stream_context_create($options);

    // Reset this variable so a previous request cannot
    // leak headers into the current response handling.
    $http_response_header = null;

    $response = @file_get_contents(
        $url,
        false,
        $context
    );

    if ($response === false) {
        return [
            'success' => false,
            'status' => 0,
            'data' => null,
            'message' => 'Facilities API is unavailable.'
        ];
    }

    // --------------------------------------------------
    // Read HTTP status code
    // --------------------------------------------------

    $statusCode = 200;

    if (
        isset($http_response_header[0]) &&
        preg_match(
            '/HTTP\/\S+\s+(\d+)/',
            $http_response_header[0],
            $matches
        )
    ) {
        $statusCode = (int) $matches[1];
    }

    // --------------------------------------------------
    // Decode JSON safely
    // --------------------------------------------------

    $data = null;

    if ($response !== '') {
        $data = json_decode($response, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return [
                'success' => false,
                'status' => $statusCode,
                'data' => null,
                'message' => 'Facilities API returned an invalid JSON response.'
            ];
        }
    }

    if ($statusCode >= 200 && $statusCode < 300) {
        return [
            'success' => true,
            'status' => $statusCode,
            'data' => $data,
            'message' => null
        ];
    }

    // Prefer a useful API-provided error message when
    // one exists, while keeping a safe generic fallback.
    $message = null;

    if (is_array($data)) {
        $message = $data['error']
            ?? $data['message']
            ?? $data['title']
            ?? null;
    }

    return [
        'success' => false,
        'status' => $statusCode,
        'data' => $data,
        'message' => $message ?? 'The Facilities API request failed.'
    ];
}

/**
 * Escape dynamic text before rendering it into HTML.
 */
function e(mixed $value): string
{
    return htmlspecialchars(
        (string) ($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}
