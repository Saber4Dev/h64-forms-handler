<?php
if (!defined('ABSPATH')) exit;

// CONFIG
// Removed define('H64_NEWSLETTER_FORM_ID', 64);

// Retrieve form ID or name from options
$newsletter_form_id = get_option('h64_newsletter_form_id');
$newsletter_form_name = get_option('h64_elementor_form');

/**
 * Hook into Elementor form submission
 */
add_action('elementor_pro/forms/new_record', function($record, $handler) use ($newsletter_form_id, $newsletter_form_name) {
    $form_name = $record->get_form_settings('form_name');
    $form_id = $record->get_form_settings('form_id');

    // Updated: Prioritize form ID, fallback to form name, otherwise bail
    if ($newsletter_form_id) {
        if ($form_id != $newsletter_form_id) return;
    } elseif ($newsletter_form_name) {
        if ($form_name !== $newsletter_form_name) return;
    } else {
        return; // no target configured
    }

    // Extract submitted fields
    $raw_fields = $record->get('fields');
    $data = [];
    foreach ($raw_fields as $id => $field) {
        $data[$id] = sanitize_text_field($field['value']);
    }

    $email = sanitize_email($data['email'] ?? '');
    $name  = sanitize_text_field($data['full_name'] ?? '');

    if (!$email || !is_email($email)) {
        error_log('H64 Newsletter: Invalid email submitted');
        return;
    }

    // 1. Send via Brevo template
    $resp = h64_send_newsletter_brevo($email, $name);

    if (is_wp_error($resp)) {
        error_log('H64 Newsletter: Brevo send failed - ' . $resp->get_error_message());
    } else {
        error_log('H64 Newsletter: Email sent via Brevo for ' . $email);
    }

    // 2. Save to Brevo contacts list
    h64_add_newsletter_contact($email, $name);

}, 10, 2);


/**
 * Send Newsletter confirmation using Brevo Template
 */
function h64_send_newsletter_brevo($to, $name) {
    list($firstName, $lastName) = h64_split_name($name);
    // Fallback: if no first name, use email as first name, last name empty
    if (empty($firstName)) {
        $firstName = $to;
        $lastName = '';
    }
    // Build full name without trailing spaces using only non-empty parts
    $parts = array_filter([$firstName, $lastName]);
    $fullName = implode(' ', $parts);
    // Ensure params always have valid strings
    $body = [
        'to' => [[
            'email' => $to,
            'name'  => $fullName
        ]],
        'templateId' => (int) h64_get_option('h64_brevo_template_newsletter'),
        'params' => [
            'FIRSTNAME' => (string) $firstName,
            'LASTNAME' => (string) $lastName
        ]
    ];

    $res = h64_brevo_post('/smtp/email', $body);

    if (is_wp_error($res)) return $res;
    $code = wp_remote_retrieve_response_code($res);
    if ($code < 200 || $code >= 300) {
        return new WP_Error('brevo', 'HTTP '.$code.' '.wp_remote_retrieve_body($res));
    }
    return true;
}


/**
 * Save subscriber to Brevo contacts list
 */
function h64_add_newsletter_contact($email, $name) {
    list($firstName, $lastName) = h64_split_name($name);
    // Build full name without trailing spaces using only non-empty parts
    $parts = array_filter([$firstName, $lastName]);
    $fullName = implode(' ', $parts);

    $body = [
        'email' => $email,
        'attributes' => [
            'FIRSTNAME' => $firstName,
            'LASTNAME'  => $lastName
        ],
        'listIds' => [(int) h64_get_option('h64_brevo_list_newsletter')],
        'updateEnabled' => true
    ];

    $res = h64_brevo_post('/contacts', $body);

    if (is_wp_error($res)) {
        error_log('H64 Newsletter: Brevo contact add failed - ' . $res->get_error_message());
        return;
    }

    $code = wp_remote_retrieve_response_code($res);
    if ($code < 200 || $code >= 300) {
        error_log('H64 Newsletter: Brevo contact API error ' . $code . ' - ' . wp_remote_retrieve_body($res));
    } else {
        error_log('H64 Newsletter: Contact saved to Brevo - ' . $email);
    }
}

// Register REST API endpoint for newsletter signup
add_action('rest_api_init', function () {
    register_rest_route('h64/v1', '/newsletter', [
        'methods' => 'POST',
        'callback' => function (WP_REST_Request $request) {
            $params = $request->get_json_params();
            $email = isset($params['email']) ? sanitize_email($params['email']) : '';
            $name = isset($params['full_name']) ? sanitize_text_field($params['full_name']) : '';

            if (!$email || !is_email($email)) {
                return new WP_REST_Response([
                    'error' => 'Invalid email address'
                ], 400);
            }

            $send_result = h64_send_newsletter_brevo($email, $name);
            if (is_wp_error($send_result)) {
                return new WP_REST_Response([
                    'error' => $send_result->get_error_message()
                ], 500);
            }

            h64_add_newsletter_contact($email, $name);

            return new WP_REST_Response([
                'ok' => true,
                'email' => $email,
                'name' => $name
            ], 200);
        },
        'permission_callback' => '__return_true'
    ]);
});
