<?php

/**
 * get_playlist_chunk.php - Chunked Playlist Loader
 *
 * Serves cached playlist data in chunks for lazy loading and memory efficiency.
 * This endpoint reads from cached files created by fetch_playlist.php.
 *
 * Usage: GET /api/get_playlist_chunk.php?identifier=captain-caveman&from=1&to=10
 *
 * Parameters:
 * - identifier: Archive identifier
 * - from: Starting index (1-based, default: 1)
 * - to: Ending index (1-based, default: 10)
 * - meta: If 'true', returns only metadata without playlist items
 *
 * Response: JSON with chunked playlist data or error information
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

// Only accept GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'GET only']);
    exit;
}

// Get and validate parameters
$identifier = isset($_GET['identifier']) ? trim($_GET['identifier']) : '';
$from = isset($_GET['from']) ? (int)$_GET['from'] : 1;
$to = isset($_GET['to']) ? (int)$_GET['to'] : 10;
$meta_only = isset($_GET['meta']) && $_GET['meta'] === 'true';

if (empty($identifier)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Missing required parameter: identifier'
    ]);
    exit;
}

// Validate range parameters
if ($from < 1 || $to < 1 || $from > $to) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Invalid range: from and to must be positive, and from <= to'
    ]);
    exit;
}

// Sanitize identifier for file safety
$safe_identifier = preg_replace('/[^a-zA-Z0-9._-]/', '', $identifier);
if ($safe_identifier !== $identifier) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Invalid identifier format'
    ]);
    exit;
}

// Determine cache directory path
$is_dev = (
    $_SERVER['HTTP_HOST'] === 'localhost' ||
    strpos($_SERVER['HTTP_HOST'], 'localhost:') === 0 ||
    $_SERVER['HTTP_HOST'] === '127.0.0.1' ||
    strpos($_SERVER['HTTP_HOST'], '127.0.0.1:') === 0
);

if ($is_dev) {
    // Development: public/cache/ (relative to api directory)
    $cache_dir = realpath(__DIR__ . '/../cache');
} else {
    // Production: /cache/
    $cache_dir = '/cache';
}

if (!$cache_dir || !is_dir($cache_dir)) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Cache directory not accessible',
        'debug' => $is_dev ? ['cache_dir' => $cache_dir] : null
    ]);
    exit;
}

$cache_file = $cache_dir . '/' . $safe_identifier . '.json';

// Check if cache file exists
if (!file_exists($cache_file)) {
    http_response_code(404);
    echo json_encode([
        'success' => false,
        'error' => 'Playlist not cached yet',
        'suggestion' => "Call fetch_playlist.php?identifier={$identifier} first"
    ]);
    exit;
}

// Load cached data
$cached_data = json_decode(file_get_contents($cache_file), true);
if (!$cached_data || !isset($cached_data['playlist'])) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Invalid cache file format'
    ]);
    exit;
}

// If only metadata requested, return without playlist items
if ($meta_only) {
    $response = $cached_data;
    unset($response['playlist']); // Remove playlist array to save bandwidth
    $response['cached'] = true;
    $response['cache_age_hours'] = round((time() - filemtime($cache_file)) / 3600, 1);
    echo json_encode($response);
    exit;
}

// Extract playlist and calculate chunk
$full_playlist = $cached_data['playlist'];
$total_items = count($full_playlist);

// Convert to 0-based indexing for array slicing
$start_index = $from - 1;
$length = $to - $from + 1;

// Validate range against actual playlist size
if ($start_index >= $total_items) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => "Range out of bounds: playlist has {$total_items} items, requested from {$from}"
    ]);
    exit;
}

// Extract the requested chunk
$chunk = array_slice($full_playlist, $start_index, $length);

// Build response
$response = [
    'success' => true,
    'identifier' => $cached_data['identifier'],
    'chunk' => [
        'from' => $from,
        'to' => min($to, $total_items), // Adjust 'to' if it exceeds actual items
        'requested_count' => $length,
        'actual_count' => count($chunk)
    ],
    'total' => [
        'items' => $total_items,
        'has_more' => $to < $total_items
    ],
    'playlist' => $chunk,
    'cached' => true,
    'cache_age_hours' => round((time() - filemtime($cache_file)) / 3600, 1),
    'served_at' => date('c'),
    'api_version' => '1.0'
];

echo json_encode($response);
