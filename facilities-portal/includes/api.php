<?php

// --------------------------------------------------
// Facilities Toolbox - Shared API Client
// --------------------------------------------------
//
// All PHP portal pages communicate with the C# API
// through this helper. The PHP layer never connects
// directly to PostgreSQL.
//
// Keeping HTTP logic in one place prevents every page
// from reimplementing timeouts, JSON parsing and error
// handling independently.
// --------------------------------------------------


// --------------------------------------------------
// API base URL
// --------------------------------------------------
//
// Local development currently runs ASP.NET Core on
// port 5209.
//
// We use the explicit IPv4 loopback address instead of
// "localhost" because some Windows/PHP combinations can
// resolve localhost to IPv6 first while Kestrel is only
// listening on IPv4. Using 127.0.0.1 keeps portal-to-API
// communication deterministic during local development.
//
// This can later move into environment configuration when
// the product is deployed.
// --------------------------------------------------

const FACILITIES_API_BASE_URL = "http://127.0.0.1:5209";


// --------------------------------------------------
// Send request to Facilities API
// --------------------------------------------------
//
// Returns a predictable result structure:
//
// [
//     "success" => bool,
//     "status" => int,
//     "data" => mixed,
//     "message" => ?string
// ]
// --------------------------------------------------

function facilitiesApiRequest(
    string $method,
    string $path,
    ?array $payload = null
): array {

    // Build the complete endpoint URL.
    $url =
        FACILITIES_API_BASE_URL
        . $path;


    // Configure PHP's HTTP stream client.
    $options = [
        "http" => [
            "method" => strtoupper($method),
            "header" => "Content-Type: application/json\r\n",
            "ignore_errors" => true,
            "timeout" => 5
        ]
    ];


    // Only send a body when a request actually has
    // payload data.
    if ($payload !== null) {
        $options["http"]["content"] =
            json_encode($payload);
    }


    $context =
        stream_context_create($options);


    $response =
        @file_get_contents(
            $url,
            false,
            $context
        );


    // A false response means PHP could not reach the
    // ASP.NET Core API at all.
    if ($response === false) {
        return [
            "success" => false,
            "status" => 0,
            "data" => null,
            "message" => "Facilities API is unavailable."
        ];
    }


    // Default to 200 when no response status header is
    // available. Normally PHP populates this header for
    // every HTTP response.
    $statusCode = 200;

    if (
        isset($http_response_header[0]) &&
        preg_match(
            "/HTTP\/\S+\s+(\d+)/",
            $http_response_header[0],
            $matches
        )
    ) {
        $statusCode =
            (int) $matches[1];
    }


    // Decode JSON responses from ASP.NET Core.
    $data =
        json_decode(
            $response,
            true
        );


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


    // ASP.NET Core error responses currently expose an
    // "error" property. Fall back to a generic message
    // so the UI always has something useful to display.
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
