<?php

// --------------------------------------------------
// Facilities Toolbox - Portal Configuration
// --------------------------------------------------
//
// Keep environment-specific values in one place so
// pages do not duplicate API host names and ports.
//
// For local development the ASP.NET Core API listens
// on port 5209. A future deployment can override this
// value with the FACILITIES_API_BASE_URL environment
// variable without changing application code.
// --------------------------------------------------

$apiBaseUrl = getenv('FACILITIES_API_BASE_URL');

if ($apiBaseUrl === false || trim($apiBaseUrl) === '') {
    $apiBaseUrl = 'http://localhost:5209';
}

// Remove a trailing slash so endpoint paths can be
// appended consistently throughout the portal.
$apiBaseUrl = rtrim($apiBaseUrl, '/');
