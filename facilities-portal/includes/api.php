<?php

// --------------------------------------------------
// Facilities Toolbox - Shared API Client
// --------------------------------------------------
//
// All PHP portal pages communicate with the C#
// Facilities API through this shared helper.
//
// The PHP portal never connects directly to PostgreSQL.
// All database operations go through the ASP.NET Core
// API.
//
// Centralising HTTP communication here prevents portal
// pages from duplicating request, timeout, JSON parsing
// and error-handling logic.
// --------------------------------------------------


// --------------------------------------------------
// Load portal configuration
// --------------------------------------------------
//
// config.php provides $apiBaseUrl.
//
// Local development:
//   http://localhost:5209
//
// Docker:
//   FACILITIES_API_BASE_URL=http://facilities-api:8080
//
// This keeps environment-specific addresses outside
// the application logic.
// --------------------------------------------------

require_once __DIR__ . '/../config.php';


// --------------------------------------------------
// Send request to Facilities API
// --------------------------------------------------
//
// Returns a predictable result structure:
//
// [
//     "success" => bool,
//     "status"  => int,
//     "data"    => mixed,
//     "message" => ?string
// ]
// --------------------------------------------------

function facilitiesApiRequest(
    string $method,
    string $path,
    ?array $payload = null
): array {

    // Use the API base URL loaded from config.php.
    global $apiBaseUrl;


    // --------------------------------------------------
    // Build endpoint URL
    // --------------------------------------------------

    $url = $apiBaseUrl . $path;


    // --------------------------------------------------
    // Configure HTTP request
    // --------------------------------------------------

    $options = [
        "http" => [
            "method" => strtoupper($method),
            "header" => "Content-Type: application/json\r\n",
            "ignore_errors" => true,
            "timeout" => 5
        ]
    ];


    // --------------------------------------------------
    // Add JSON body when required
    // --------------------------------------------------

    if ($payload !== null) {
        $options["http"]["content"] =
            json_encode($payload);
    }


    $context =
        stream_context_create($options);


    // --------------------------------------------------
    // Send request
    // --------------------------------------------------

    $response =
        @file_get_contents(
            $url,
            false,
            $context
        );


    // --------------------------------------------------
    // Handle API connection failure
    // --------------------------------------------------

    if ($response === false) {
        return [
            "success" => false,
            "status" => 0,
            "data" => null,
            "message" => "Facilities API is unavailable."
        ];
    }


    // --------------------------------------------------
    // Determine HTTP status code
    // --------------------------------------------------

    $statusCode = 200;

    if (
        isset($http_response_header[0]) &&
        preg_match(
            "/HTTP\/\S+\s+(\d+)/",
            $http_response_header[0],
            $matches
        )
    ) {
        $statusCode = (int) $matches[1];
    }


    // --------------------------------------------------
    // Decode JSON response
    // --------------------------------------------------

    $data =
        json_decode(
            $response,
            true
        );


    // --------------------------------------------------
    // Determine request success
    // --------------------------------------------------

    $success =
        $statusCode >= 200 &&
        $statusCode < 300;


    if ($success) {
        return [
            "success" => true,
            "status" => $statusCode,
            "data" => $data,
            "message" => null
        ];
    }


    // --------------------------------------------------
    // Handle API error response
    // --------------------------------------------------
    //
    // ASP.NET Core error responses may expose an
    // "error" property. If none exists, return a safe
    // generic message for the portal.
    // --------------------------------------------------

    return [
        "success" => false,
        "status" => $statusCode,
        "data" => $data,
        "message" =>
            is_array($data)
                ? ($data["error"] ?? "The request failed.")
                : "The request failed."
    ];
}