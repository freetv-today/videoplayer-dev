<?php

/**
 * fetch_playlist.php - Internet Archive Playlist Processor
 *
 * Fetches raw data from Internet Archive API, processes and cleans it,
 * then caches the result for fast retrieval by the frontend.
 *
 * Usage: GET /api/fetch_playlist.php?identifier=captain-caveman-and-the-teen-angels
 *
 * Response: JSON with processed playlist data or error information
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

// Get and validate identifier parameter
$identifier = isset($_GET['identifier']) ? trim($_GET['identifier']) : '';
if (empty($identifier)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Missing required parameter: identifier'
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

// Determine environment and set cache directory path
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

// Check if cached version exists and is fresh (24 hours)
$cache_max_age = 24 * 60 * 60; // 24 hours in seconds
if (file_exists($cache_file)) {
    $cache_age = time() - filemtime($cache_file);
    if ($cache_age < $cache_max_age) {
        // Return cached data
        $cached_data = json_decode(file_get_contents($cache_file), true);
        if ($cached_data) {
            $cached_data['cached'] = true;
            $cached_data['cache_age_hours'] = round($cache_age / 3600, 1);
            echo json_encode($cached_data);
            exit;
        }
    }
}

// Fetch fresh data from Internet Archive
$api_url = "https://archive.org/metadata/{$identifier}/files";

$context = stream_context_create([
    'http' => [
        'timeout' => 30,
        'user_agent' => 'FreeTV/1.0 (+https://freetv.today)'
    ]
]);

$response = @file_get_contents($api_url, false, $context);
if ($response === false) {
    // Check if item is "dark" (removed) from Internet Archive
    $is_dark_url = "https://archive.org/metadata/{$identifier}/is_dark";
    $is_dark_response = @file_get_contents($is_dark_url, false, $context);

    if ($is_dark_response !== false) {
        $is_dark_data = json_decode($is_dark_response, true);
        if (isset($is_dark_data['result']) && $is_dark_data['result'] === true) {
            // Item is permanently removed from Internet Archive
            http_response_code(410); // 410 Gone
            echo json_encode([
                'success' => false,
                'error' => 'This content has been permanently removed from the Internet Archive',
                'error_type' => 'content_removed',
                'identifier' => $identifier,
                'is_dark' => true
            ]);
            exit;
        }
    }

    // If not dark or check failed, return generic network error
    http_response_code(502);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to fetch data from Internet Archive',
        'api_url' => $api_url
    ]);
    exit;
}

$data = json_decode($response, true);
if (!$data || !isset($data['result']) || !is_array($data['result'])) {
    // Check for specific "Couldn't get 'files'" error that indicates the item might be dark
    if (isset($data['error']) && str_contains($data['error'], "Couldn't get 'files'")) {
        // Check if item is "dark" (removed) from Internet Archive
        $is_dark_url = "https://archive.org/metadata/{$identifier}/is_dark";
        $is_dark_response = @file_get_contents($is_dark_url, false, $context);

        if ($is_dark_response !== false) {
            $is_dark_data = json_decode($is_dark_response, true);
            if (isset($is_dark_data['result']) && $is_dark_data['result'] === true) {
                // Item is permanently removed from Internet Archive
                // Try to disable the item in playlists to prevent future issues
                disableItemInPlaylists($identifier);

                http_response_code(410); // 410 Gone
                echo json_encode([
                    'success' => false,
                    'error' => 'This content has been permanently removed from the Internet Archive',
                    'error_type' => 'content_removed',
                    'identifier' => $identifier,
                    'is_dark' => true
                ]);
                exit;
            }
        }
    }

    // If not dark or different error, return generic API error
    http_response_code(502);
    echo json_encode([
        'success' => false,
        'error' => 'Invalid response from Internet Archive API',
        'api_url' => $api_url,
        'api_error' => $data['error'] ?? 'Unknown error'
    ]);
    exit;
}

// Process and clean the data
$processed_data = processPlaylistData($identifier, $data);

// Cache the processed data
file_put_contents($cache_file, json_encode($processed_data, JSON_PRETTY_PRINT));

// Return processed data
echo json_encode($processed_data);

/**
 * Process raw Internet Archive data into clean playlist format
 *
 * @param string $identifier Archive identifier
 * @param array $raw_data Raw API response from Internet Archive
 * @return array Processed playlist data
 */
function processPlaylistData($identifier, $raw_data)
{
    $files = $raw_data['result'];

    // Log available formats for debugging
    $format_counts = [];
    foreach ($files as $file) {
        if (isset($file['format'])) {
            $format = $file['format'];
            $format_counts[$format] = ($format_counts[$format] ?? 0) + 1;
        }
    }

    // Define accepted video formats
    $video_formats = [
        'h.264', 'H.264', 'MPEG4',
        'WebM', 'webm',
        'Ogg Video', 'ogg video',
        'MPEG2', 'mpeg2',
        'Matroska', 'matroska', 'MKV', 'mkv',
        'h.264 IA'
    ];

    $video_extensions = ['.mp4', '.webm', '.ogv', '.m4v', '.mpeg', '.mpg', '.mkv', '.avi'];

    // Define format preference order (lower number = higher priority)
    $format_preference = [
        'mp4' => 1,
        'm4v' => 2,
        'mkv' => 3,
        'avi' => 4,
        'webm' => 5,
        'ogv' => 6,
        'mpeg' => 7,
        'mpg' => 8
    ];

    // First pass: collect all valid video files
    $candidate_videos = [];
    foreach ($files as $file) {
        if (!isset($file['format']) || !isset($file['name'])) {
            continue;
        }

        // Check format match
        $format_match = false;
        foreach ($video_formats as $fmt) {
            if (stripos($file['format'], $fmt) !== false) {
                $format_match = true;
                break;
            }
        }

        // Check extension match as backup
        $extension_match = false;
        $filename_lower = strtolower($file['name']);
        foreach ($video_extensions as $ext) {
            if (substr($filename_lower, -strlen($ext)) === $ext) {
                $extension_match = true;
                break;
            }
        }

        // Skip if no format/extension match
        if (!$format_match && !$extension_match) {
            continue;
        }

        // Exclude thumbnail/metadata files
        if (
            stripos($filename_lower, 'thumb') !== false ||
            stripos($filename_lower, '.xml') !== false ||
            stripos($filename_lower, '.jpg') !== false ||
            stripos($filename_lower, '.png') !== false ||
            stripos($filename_lower, 'torrent') !== false
        ) {
            continue;
        }

        // Determine file extension for preference ranking
        $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $preference_score = $format_preference[$file_ext] ?? 999;

        // Determine MIME type
        $mime_type_map = [
            'mp4' => 'video/mp4',
            'm4v' => 'video/mp4',
            'webm' => 'video/webm',
            'ogv' => 'video/ogg',
            'mkv' => 'video/x-matroska',
            'avi' => 'video/x-msvideo',
            'mpeg' => 'video/mpeg',
            'mpg' => 'video/mpeg'
        ];
        $mime_type = $mime_type_map[$file_ext] ?? 'video/mp4';

        // Create normalized title (without extension) for grouping
        $normalized_title = $file['title'] ?? $file['name'];

        // Remove file extension
        $normalized_title = preg_replace('/\.[^.]+$/', '', $normalized_title);

        // Convert to lowercase
        $normalized_title = strtolower($normalized_title);

        // Remove Internet Archive processing suffixes before normalization
        $normalized_title = preg_replace('/\.ia$/', '', $normalized_title);
        $normalized_title = preg_replace('/\.(mpeg4|h264|ia|orig|original)$/', '', $normalized_title);

        // Remove or normalize common separators and special characters
        $normalized_title = str_replace(['_', '-', '.', ' '], '', $normalized_title);

        // Remove common file format suffixes that might cause duplicates
        $normalized_title = preg_replace('/\.(mp4|avi|mkv|webm|mpeg|mpg)$/', '', $normalized_title);

        // Remove any remaining special characters except alphanumeric
        $normalized_title = preg_replace('/[^a-z0-9]/', '', $normalized_title);        $candidate_videos[] = [
            'normalized_title' => $normalized_title,
            'preference_score' => $preference_score,
            'file_ext' => $file_ext,
            'sources' => [[
                'src' => "https://archive.org/download/{$identifier}/" . urlencode($file['name']),
                'type' => $mime_type
            ]],
            'title' => cleanVideoTitle($file['title'] ?? $file['name']),
            'originalTitle' => $file['title'] ?? $file['name'],
            'originalFormat' => $file['format'],
            'fileName' => $file['name'],
            'fileSize' => $file['size'] ?? null
        ];
    }

    // Second pass: deduplicate by keeping only best format per title
    $video_groups = [];
    foreach ($candidate_videos as $video) {
        $key = $video['normalized_title'];

        if (!isset($video_groups[$key]) || $video['preference_score'] < $video_groups[$key]['preference_score']) {
            $video_groups[$key] = $video;
        }
    }

    // Extract final videos and remove temporary fields
    $videos = [];
    foreach ($video_groups as $video) {
        unset($video['normalized_title']);
        unset($video['preference_score']);
        $videos[] = $video;
    }

    // Sort videos by title
    usort($videos, function ($a, $b) {
        return strcasecmp($a['title'], $b['title']);
    });

    return [
        'success' => true,
        'identifier' => $identifier,
        'totalFiles' => count($files),
        'videoFiles' => count($videos),
        'availableFormats' => $format_counts,
        'playlist' => $videos,
        'cached' => false,
        'processed_at' => date('c'),
        'api_version' => '1.0'
    ];
}

/**
 * Clean and standardize video titles
 *
 * @param string $title Original title from Internet Archive
 * @return string Cleaned title
 */
function cleanVideoTitle($title)
{
    // Remove file extension
    $title = preg_replace('/\.[^.]+$/', '', $title);

    // Remove directory paths (forward slashes) - keep only the filename part
    $title = basename($title);

    // Common episode patterns to standardize
    $patterns = [
        // "S01E01" patterns
        '/[Ss](\d+)[Ee](\d+)/' => 'S$1E$2',
        // "1x01" patterns
        '/(\d+)x(\d+)/' => 'S$1E$2',
        // "s-01-e-01" patterns
        '/[Ss][-_](\d+)[-_][Ee][-_](\d+)/' => 'S$1E$2',
        // "Season 1" -> "S01"
        '/Season\s+(\d+)/i' => 'S$1',
        // "Episode 1" -> "E01"
        '/Episode\s+(\d+)/i' => 'E$1'
    ];

    foreach ($patterns as $pattern => $replacement) {
        $title = preg_replace($pattern, $replacement, $title);
    }

    // Replace _s_ with 's (possessive apostrophe) before converting underscores to spaces
    $title = preg_replace('/_s_/i', "'s ", $title);

    // Clean up underscores and multiple spaces
    $title = str_replace('_', ' ', $title);
    $title = preg_replace('/\s+/', ' ', $title);
    $title = trim($title);

    // Capitalize first letter of each word
    $title = ucwords(strtolower($title));

    return $title;
}

/**
 * Disable an item in all playlists when it's detected as "dark" (removed from Internet Archive)
 *
 * @param string $identifier Internet Archive identifier to disable
 * @return void
 */
function disableItemInPlaylists($identifier)
{
    // Determine playlists directory based on environment
    $is_dev = (
        $_SERVER['HTTP_HOST'] === 'localhost' ||
        strpos($_SERVER['HTTP_HOST'], 'localhost:') === 0 ||
        $_SERVER['HTTP_HOST'] === '127.0.0.1' ||
        strpos($_SERVER['HTTP_HOST'], '127.0.0.1:') === 0
    );

    if ($is_dev) {
        $playlists_dir = realpath(__DIR__ . '/../playlists');
    } else {
        $playlists_dir = '/playlists';
    }

    if (!$playlists_dir || !is_dir($playlists_dir)) {
        return;
    }

    $files = glob($playlists_dir . '/*.json');
    $updated_any = false;

    foreach ($files as $file) {
        $filename = basename($file);
        if ($filename === 'index.json') {
            continue; // Skip index file
        }

        $content = file_get_contents($file);
        $playlist_data = json_decode($content, true);

        if (!$playlist_data || !isset($playlist_data['shows']) || !is_array($playlist_data['shows'])) {
            continue; // Skip invalid playlist files
        }

        $found_and_updated = false;
        foreach ($playlist_data['shows'] as &$show) {
            if (isset($show['identifier']) && $show['identifier'] === $identifier) {
                $show['status'] = 'disabled';
                $found_and_updated = true;
            }
        }
        unset($show); // Break reference

        if ($found_and_updated) {
            // Update lastupdated timestamp
            $playlist_data['lastupdated'] = gmdate('Y-m-d\TH:i:s.v\Z');

            // Save the updated playlist
            if (!file_put_contents($file, json_encode($playlist_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES))) {
                // If save fails, continue to try other files
                continue;
            }
            $updated_any = true;
        }
    }

    // If we updated any playlists, rebuild the index
    if ($updated_any) {
        require_once __DIR__ . '/playlist_utils.php';
        rebuild_index($playlists_dir);
    }
}
