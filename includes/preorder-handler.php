<?php


if (!defined('ABSPATH')) exit;

// CONFIG
$preorder_form_id = get_option('h64_preorder_form_id');
$preorder_form_name = get_option('h64_preorder_form');

/**
 * Hook into Elementor Pro Form submission
 */
add_action('elementor_pro/forms/new_record', function($record, $handler) use ($preorder_form_id, $preorder_form_name) {
    $form_name = $record->get_form_settings('form_name');
    $form_id = $record->get_form_settings('form_id');
    if (($preorder_form_name && $form_name !== $preorder_form_name) && ($preorder_form_id && $form_id != $preorder_form_id)) {  
        return;
    }

    // Extract submitted fields
    $raw_fields = $record->get('fields');

    // Debug: log raw fields to see exact keys/values
    // error_log('H64 Preorder: Raw submission fields => ' . print_r($raw_fields, true));

    $data = [];
    foreach ($raw_fields as $id => $field) {
        $data[$id] = sanitize_text_field($field['value']);
    }

    $email       = sanitize_email($data['email'] ?? '');
    $name        = sanitize_text_field($data['full_name'] ?? '');
    $role        = sanitize_text_field($data['category'] ?? '');
    $preorder_id = sanitize_text_field($data['preorder_id'] ?? '');
    $consent     = !empty($data['receive_updates']) ? 'Yes' : 'No';
    $phone       = sanitize_text_field($data['phone_number'] ?? '');
    $language    = sanitize_text_field($data['language'] ?? '');
    $units       = sanitize_text_field($data['units'] ?? '');
    $country     = sanitize_text_field($data['country'] ?? '');
    $source      = sanitize_text_field($data['source'] ?? '');
    $remarks     = sanitize_text_field($data['message'] ?? '');

    if (!$email || !is_email($email)) {
        error_log('H64 Preorder: Invalid email submitted');
        return;
    }

    // Save to Brevo contacts
    h64_preorder_add_brevo_contact($email, $name, $role, $preorder_id, $consent, $phone, $language, $units, $country, $source, $remarks);
    // Send Brevo template email
    h64_preorder_send_brevo_template($email, $name, $role, $preorder_id, $units, $language, $country, $source, $remarks);

}, 10, 2);

/**
 * REST endpoint for testing preorder submissions via Postman
 */
add_action('rest_api_init', function () {
    register_rest_route('h64/v1', '/preorder', [
        'methods'  => 'POST',
        'callback' => function (WP_REST_Request $req) {
            $data = json_decode($req->get_body(), true);

            $email       = sanitize_email($data['email'] ?? '');
            $name        = sanitize_text_field($data['full_name'] ?? '');
            $role        = sanitize_text_field($data['category'] ?? '');
            $preorder_id = sanitize_text_field($data['preorder_id'] ?? '');
            $consent     = !empty($data['receive_updates']) ? 'Yes' : 'No';
            $phone       = sanitize_text_field($data['phone_number'] ?? '');
            $language    = sanitize_text_field($data['language'] ?? '');
            $units       = sanitize_text_field($data['units'] ?? '');
            $country     = sanitize_text_field($data['country'] ?? '');
            $source      = sanitize_text_field($data['source'] ?? '');
            $remarks     = sanitize_text_field($data['message'] ?? '');

            if (!$email || !is_email($email)) {
                return new WP_REST_Response(['ok'=>false,'error'=>'Invalid email'], 400);
            }

            h64_preorder_add_brevo_contact($email, $name, $role, $preorder_id, $consent, $phone, $language, $units, $country, $source, $remarks);
            h64_preorder_send_brevo_template($email, $name, $role, $preorder_id, $units, $language, $country, $source, $remarks);
            return new WP_REST_Response(['ok'=>true,'message'=>'Contact sent to Brevo and template email sent'], 200);
        },
        'permission_callback' => '__return_true'
    ]);
});

/**
 * Send Brevo transactional email using template
 */
function h64_preorder_send_brevo_template($email, $name, $role, $preorder_id, $units, $language, $country, $source, $remarks) {
    list($firstName, $lastName) = h64_split_name($name);

    $body = [
        'to' => [
            ['email' => $email]
        ],
        'templateId' => (int) h64_get_option('h64_brevo_template_preorder'),
        'params' => [
            'FIRSTNAME'   => $firstName,
            'LASTNAME'    => $lastName,
            'PREORDER_ID' => $preorder_id,
            'UNITS'       => $units,
            'LANGUAGE'    => $language,
            'ROLE'        => $role,
            'COUNTRY'     => $country,
            'SOURCE'      => $source,
            'REMARKS'     => $remarks
        ]
    ];

    $res = h64_brevo_post('/smtp/email', $body);

    if (is_wp_error($res)) {
        error_log('H64 Preorder: Brevo template send failed - ' . $res->get_error_message());
        return;
    }

    $code = wp_remote_retrieve_response_code($res);
    if ($code < 200 || $code >= 300) {
        error_log('H64 Preorder: Brevo template API error ' . $code . ' - ' . wp_remote_retrieve_body($res));
    } else {
        error_log('H64 Preorder: Template ' . (int) h64_get_option('h64_brevo_template_preorder') . ' email sent to ' . $email);
    }
}

/**
 * Add or update contact in Brevo
 */
function h64_preorder_add_brevo_contact($email, $name, $role, $preorder_id, $consent, $phone, $language, $units, $country, $source, $remarks) {
    list($firstName, $lastName) = h64_split_name($name);

    $body = [
        'email' => $email,
        'attributes' => [
            'UNITS'       => $units,
            'FIRSTNAME'   => $firstName,
            'LASTNAME'    => $lastName,
            'ROLE'        => $role,
            'CONSENT'     => $consent,
            'PHONE_NUMBER'   => $phone,
            'LANGUAGE'    => $language,
            'PREORDER_ID' => $preorder_id,
            'COUNTRY'     => $country,
            'SOURCE'      => $source,
            'REMARKS'     => $remarks
        ],
        'listIds' => [(int) h64_get_option('h64_brevo_list_preorder')],
        'updateEnabled' => true
    ];

    $res = h64_brevo_post('/contacts', $body);

    // Debug: log API request/response for contact add
    //error_log('H64 Preorder: Brevo contact add request: ' . wp_json_encode($body));

    if (is_wp_error($res)) {
        error_log('H64 Preorder: Brevo contact add failed - ' . $res->get_error_message());
        return;
    }

    $code = wp_remote_retrieve_response_code($res);
    if ($code < 200 || $code >= 300) {
        error_log('H64 Preorder: Brevo API error ' . $code . ' - ' . wp_remote_retrieve_body($res));
    } else {
        error_log('H64 Preorder: Contact saved to Brevo - ' . $email);
    }
}