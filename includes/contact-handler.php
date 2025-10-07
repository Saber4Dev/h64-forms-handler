

<?php
// Contact form handler for Brevo integration
if (!defined('ABSPATH')) exit;

add_action('elementor_pro/forms/new_record', function($record, $handler) {
    // Only handle Elementor Pro forms
    if (!method_exists($record, 'get_form_settings')) return;

    // Retrieve Contact form name and ID from options (correct usage)
    $target_form_name = trim(get_option('h64_contact_form', ''));
    $target_form_id = trim(get_option('h64_contact_form_id', ''));

    // Get submitted form name and id using Elementor API
    $submitted_form_name = trim($record->get_form_settings('form_name') ?? '');
    $submitted_form_id   = trim($record->get_form_settings('form_id') ?? '');

    // Prioritize ID if set, fallback to name, otherwise do nothing
    if ($target_form_id !== '') {
        if ($submitted_form_id !== $target_form_id) return;
    } elseif ($target_form_name !== '') {
        if ($submitted_form_name !== $target_form_name) return;
    } else {
        // No match criteria set
        return;
    }

    // Get submitted fields
    $fields = $record->get('fields');
    $full_name = isset($fields['full_name']['value']) ? trim($fields['full_name']['value']) : '';
    $email = isset($fields['email']['value']) ? trim($fields['email']['value']) : '';
    $phone = isset($fields['phone']['value']) ? trim($fields['phone']['value']) : '';
    $message = isset($fields['message']['value']) ? trim($fields['message']['value']) : '';

    // Use h64_split_name for full_name, fallback to email as firstname if empty
    if (!empty($full_name)) {
        list($firstname, $lastname) = function_exists('h64_split_name') ? h64_split_name($full_name) : [$full_name, ''];
    } elseif (!empty($email)) {
        $firstname = $email;
        $lastname = '';
    } else {
        $firstname = '';
        $lastname = '';
    }
    // Build a clean contact full name (no trailing spaces)
    $parts = array_filter([$firstname, $lastname]);
    $fullName = implode(' ', $parts);

    // Get Brevo Contact List ID
    $list_id = absint(get_option('h64_brevo_list_contact'));
    if (!$list_id) return;

    // Ensure email is not empty before sending to Brevo
    if (empty($email)) {
        return;
    }

    // Only include non-empty attributes, use PHONE_NUMBER and REMARKS as required by new mapping
    $attributes = array_filter([
        'FIRSTNAME'    => $firstname,
        'LASTNAME'     => $lastname,
        'PHONE_NUMBER' => $phone,
        'REMARKS'      => $message,
    ]);

    $body = [
        'email' => $email,
        'attributes' => $attributes,
        'listIds' => [$list_id],
        'updateEnabled' => true,
    ];

    $res = function_exists('h64_brevo_post') ? h64_brevo_post('/contacts', $body) : null;

    if (is_wp_error($res)) {
        error_log('[h64-contact] Brevo contact add error: ' . $res->get_error_message());
    } else {
        $code = wp_remote_retrieve_response_code($res);
        if ($code < 200 || $code >= 300) {
            $body_str = wp_remote_retrieve_body($res);
            error_log('[h64-contact] Brevo contact add failed. HTTP ' . $code . ' Response: ' . $body_str);
        } else {
            // After successful contact add, optionally send transactional template email
            $template_id = absint(get_option('h64_brevo_template_contact'));
            if ($template_id > 0) {
                // build and send email body
                $email_body = [
                    'to' => [
                        [
                            'email' => $email,
                            'name' => $fullName,
                        ]
                    ],
                    'templateId' => $template_id,
                    'params' => [
                        'FIRSTNAME'    => $firstname,
                        'LASTNAME'     => $lastname,
                        'PHONE_NUMBER' => $phone,
                        'REMARKS'      => $message,
                    ],
                ];
                $email_res = function_exists('h64_brevo_post') ? h64_brevo_post('/smtp/email', $email_body) : null;
                if (is_wp_error($email_res)) {
                    error_log('[h64-contact] Brevo transactional email error: ' . $email_res->get_error_message());
                } else {
                    $email_code = wp_remote_retrieve_response_code($email_res);
                    if ($email_code < 200 || $email_code >= 300) {
                        $email_body_str = wp_remote_retrieve_body($email_res);
                        error_log('[h64-contact] Brevo transactional email failed. HTTP ' . $email_code . ' Response: ' . $email_body_str);
                    }
                }
            } else {
                error_log('[h64-contact] No valid template ID configured, skipping transactional email.');
            }
        }
    }
}, 10, 2);