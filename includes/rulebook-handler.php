<?php

// === H64 Rulebook Handler Configuration ===
// Settings are now loaded from options.
// === End Configuration ===

if (get_option('h64_rulebook_enabled', 1)) {

/**
 * Returns the direct URL for the rules page.
 *
 * @return string
 */
function h64_rulebook_rules_url() {
    return home_url('/rules/');
}

/**
 * Generates a signed URL for downloading the rulebook that expires after a given number of minutes.
 *
 * @param int $minutes Number of minutes the URL is valid for.
 * @return string Signed URL.
 */
function h64_rulebook_signed_url($minutes = 2) {
    $expires = time() + ($minutes * 60);
    $data = 'rulebook_download=1&expires=' . $expires;
    $secret_key = get_option('h64_rulebook_secret_key', '');
    $token = hash_hmac('sha256', $data, $secret_key);
    $url = h64_rulebook_rules_url() . '?' . $data . '&token=' . $token;
    return $url;
}

add_shortcode('h64_rulebook_download_button', function() {
    $url = h64_rulebook_signed_url();
    // Output a simple link instead of a form, so it can be used directly in Elementor button widget.
    return '<a href="' . esc_url($url) . '" class="h64-rulebook-download-link">View Rulebook</a>';
});

add_action('template_redirect', function() {
    if (!is_page('rules')) {
        return;
    }

    if (!isset($_GET['rulebook_download']) || $_GET['rulebook_download'] != '1') {
        return;
    }

    // Validate expires and token parameters
    $expires = isset($_GET['expires']) ? intval($_GET['expires']) : 0;
    $token = isset($_GET['token']) ? $_GET['token'] : '';

    if ($expires === 0 || empty($token)) {
        wp_die('Invalid or missing download token.');
    }

    if ($expires < time()) {
        wp_die('The download link has expired.');
    }

    $data = 'rulebook_download=1&expires=' . $expires;
    $secret_key = get_option('h64_rulebook_secret_key', '');
    $expected_token = hash_hmac('sha256', $data, $secret_key);

    if (!hash_equals($expected_token, $token)) {
        wp_die('Invalid download token.');
    }

    // Get client IP
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
    } else {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }
    $ip = sanitize_text_field($ip);

    $date = date('Y-m-d');
    $time = date('Y-m-d H:i:s');
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    $page_url = wp_get_referer() ?: 'unknown';

    // Use transient to prevent duplicate email sending per IP per day
    $transient_key = 'h64_rulebook_email_sent_' . md5($ip . '_' . $date);
    $ip_counts = get_option('h64_rulebook_ip_counts', []);
    $ip_counts[$ip] = ($ip_counts[$ip] ?? 0) + 1;
    update_option('h64_rulebook_ip_counts', $ip_counts);

    // Prepare email data
    $admin_emails = [];
    $emails_option = get_option('h64_rulebook_admin_emails', '');
    if ($emails_option) {
        $admin_emails = array_filter(array_map('trim', explode(',', $emails_option)));
    }
    if (empty($admin_emails)) {
        $admin_email = get_option('admin_email');
        if ($admin_email) {
            $admin_emails = [$admin_email];
        }
    }

    if (!empty($admin_emails)) {
        $subject = 'H64 Rulebook Page Visit Notification';
        $message = "The rulebook page was visited with the following details:\n\n";
        $message .= "IP Address: " . $ip . "\n";
        $message .= "User Agent: " . $user_agent . "\n";
        $message .= "Date: " . $date . "\n";
        $message .= "Time: " . $time . "\n";
        $message .= "Page URL: " . $page_url . "\n";
        $message .= "Download Count for this IP: " . $ip_counts[$ip] . "\n";

        // Schedule email sending asynchronously
        wp_schedule_single_event(time() + 5, 'h64_rulebook_send_email', [ $admin_emails, $subject, $message ]);
    }

    // Serve the PDF file for download (using full file path option)
    $file_path = get_option('h64_rulebook_file_path', WP_CONTENT_DIR . '/uploads/protected/ARDEVUR_Digital_Rulebook.pdf');
    if (file_exists($file_path)) {
        // Clear output buffer
        if (ob_get_length()) {
            ob_end_clean();
        }
        // Set headers
        header('Content-Description: File Transfer');
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . basename($file_path) . '"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($file_path));
        // Read the file
        readfile($file_path);
        exit;
    } else {
        wp_die('The requested file is not available.');
    }
});

add_action('h64_rulebook_send_email', function($admin_emails, $subject, $message) {
    wp_mail($admin_emails, $subject, $message);
}, 10, 3);

} // end if enabled