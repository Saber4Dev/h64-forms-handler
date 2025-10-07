<?php
if (!defined('ABSPATH')) exit;

/**
 * Auto-update integration for GitHub
 * Enhanced version with caching and dynamic version detection
 */

$plugin_slug = 'h64-forms-handler';
$github_user = 'Saber4Dev';
$github_repo = 'h64-forms-handler';

// Get current version from main plugin header
$plugin_main_file = dirname(__DIR__) . '/h64-forms.php';
$plugin_data = get_file_data($plugin_main_file, ['Version' => 'Version']);
$current_version = $plugin_data['Version'] ?? '1.0.0';

add_filter('pre_set_site_transient_update_plugins', function($transient) use ($plugin_slug, $github_user, $github_repo, $current_version, $plugin_main_file) {
    if (empty($transient->checked)) return $transient;

    // Caching GitHub response
    $cache_key = 'h64_forms_latest_release';
    $release = get_transient($cache_key);

    if (!$release) {
        $response = wp_remote_get("https://api.github.com/repos/$github_user/$github_repo/releases/latest", [
            'headers' => [
                'Accept' => 'application/vnd.github.v3+json',
                'User-Agent' => 'WordPress/' . get_bloginfo('version') . '; ' . home_url(),
            ]
        ]);
        if (is_wp_error($response)) return $transient;
        $release = json_decode(wp_remote_retrieve_body($response));
        if (!$release) return $transient;
        set_transient($cache_key, $release, 6 * HOUR_IN_SECONDS);
    }

    if (empty($release->tag_name)) return $transient;

    $new_version = ltrim($release->tag_name, 'v');
    if (version_compare($current_version, $new_version, '>=')) return $transient;

    $plugin_file = plugin_basename($plugin_main_file);
    $plugin_data = [
        'slug'        => $plugin_slug,
        'new_version' => $new_version,
        'url'         => "https://github.com/$github_user/$github_repo",
        'package'     => $release->zipball_url,
    ];

    $transient->response[$plugin_file] = (object)$plugin_data;
    return $transient;
});