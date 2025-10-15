<?php
// Universal Elementor -> Brevo handler
if (!defined('ABSPATH')) exit;

/**
 * Minimal helpers (safe if you already have them)
 */
if (!function_exists('h64_split_name')) {
    function h64_split_name($full) {
        $full = trim(preg_replace('/\s+/',' ', (string)$full));
        if ($full === '') return ['', ''];
        $parts = explode(' ', $full, 2); // only split once
        $first = $parts[0];
        $last  = isset($parts[1]) ? trim($parts[1]) : '';
        return [$first, $last];
    }
}

/**
 * Extract best-guess email from Elementor $fields array
 */
function h64_el_find_email(array $fields) {
    $likely_keys = ['email', 'user_email', 'your_email', 'e-mail'];
    foreach ($likely_keys as $k) {
        if (!empty($fields[$k]['value'])) return trim($fields[$k]['value']);
    }
    // Fallback: scan values for something that validates as an email
    foreach ($fields as $f) {
        $v = isset($f['value']) ? trim((string)$f['value']) : '';
        if ($v && filter_var($v, FILTER_VALIDATE_EMAIL)) return $v;
    }
    return '';
}

/**
 * Extract full name from common Elementor field IDs
 */
function h64_el_find_full_name(array $fields) {
    $candidates = [
        'full_name', 'name', 'your_name', 'fullname',
        // combine first/last if present
    ];
    foreach ($candidates as $k) {
        if (!empty($fields[$k]['value'])) return trim($fields[$k]['value']);
    }
    $first = !empty($fields['first_name']['value']) ? trim($fields['first_name']['value']) : '';
    $last  = !empty($fields['last_name']['value'])  ? trim($fields['last_name']['value'])  : '';
    if ($first || $last) return trim($first . ' ' . $last);
    return '';
}

/**
 * Extract “message/remarks”
 */
function h64_el_find_message(array $fields) {
    foreach (['message','remarks','comment','your_message','notes'] as $k) {
        if (!empty($fields[$k]['value'])) return trim($fields[$k]['value']);
    }
    return '';
}

/**
 * Extract country (best effort)
 */
function h64_el_find_country(array $fields) {
    foreach (['country','country_code','location'] as $k) {
        if (!empty($fields[$k]['value'])) return trim($fields[$k]['value']);
    }
    return '';
}

/**
 * Extract acceptance/consent (Elementor sends 'on' when checked)
 */
function h64_el_find_consent(array $fields) {
    $raw = isset($fields['consent']['value']) ? strtolower(trim((string)$fields['consent']['value'])) : '';
    return in_array($raw, ['on','yes','true','1'], true) ? 'Yes' : 'No';
}

/**
 * Main hook – single handler for ALL Elementor forms
 */
add_action('elementor_pro/forms/new_record', function($record, $handler) {
    if (!method_exists($record, 'get_form_settings')) return;

    
    // Elementor form settings
    $form_settings = $record->get('form_settings');
    $form_name = trim((string)($form_settings['form_name'] ?? ''));
    $form_id   = trim((string)($form_settings['form_id'] ?? ''));

    $fields = $record->get('fields');

    // Extract common data from fields (robust to different ID naming)
    $email     = h64_el_find_email($fields);
    $full_name = h64_el_find_full_name($fields);
    $country   = h64_el_find_country($fields);
    $message   = h64_el_find_message($fields);
    $consent   = h64_el_find_consent($fields);

    // Names
    [$firstname, $lastname] = h64_split_name($full_name);

    // Load mapping (single option) and fallback default list
    $map         = (array) get_option('h64_forms_map', []);
    $defaultList = (int) (function_exists('h64_get_option') ? h64_get_option('h64_brevo_list_global') : get_option('h64_brevo_list_global'));

    // We key by form_id if available, else by form_name
    $key = $form_id !== '' ? $form_id : $form_name;
    $entry = isset($map[$key]) ? (array)$map[$key] : [];

    // Hierarchy: form-specific → global default → hardcoded fallback (20)
    if (!empty($entry['list_id']) && (int)$entry['list_id'] > 0) {
        $list_id = (int)$entry['list_id'];
    } elseif (!empty($defaultList) && (int)$defaultList > 0) {
        $list_id = (int)$defaultList;
    } else {
        $list_id = 20; // hardcoded fallback
    }

    //error_log('[h64-global] Using Brevo list ID: ' . $list_id . ' (form=' . $form_name . ', default=' . $defaultList . ')');
    $template_id = (int) ($entry['template_id'] ?? 0);

    // Always attempt to save the contact (you said: “make sure every contact saves”)
    if (!$list_id) {
        // No list configured anywhere – nothing to do
        error_log('[h64-global] No list configured (h64_brevo_list_global and map empty). Skipping.');
        return;
    }

    // Extract additional optional fields if present
    $role         = isset($fields['role']['value']) ? trim($fields['role']['value']) : '';
    $collab_type  = isset($fields['collaboration_type']['value']) ? trim($fields['collaboration_type']['value']) : '';
    $phone        = isset($fields['phone']['value']) ? trim($fields['phone']['value']) : '';
    $language     = isset($fields['language']['value']) ? trim($fields['language']['value']) : '';
    $units        = isset($fields['units']['value']) ? trim($fields['units']['value']) : '';
    $referral     = isset($fields['source']['value']) ? trim($fields['source']['value']) : ''; // for tracking

    // Build complete Brevo attributes
    $attributes = [
        'FIRSTNAME'     => $firstname,
        'LASTNAME'      => $lastname,
        'ROLE'          => $role,
        'COLLAB_TYPE'   => $collab_type,
        'COUNTRY'       => $country,
        'PHONE_NUMBER'  => $phone,
        'LANGUAGE'      => $language,
        'UNITS'         => $units,
        'SOURCE'        => $referral ?: 'Elementor: ' . ($form_name ?: 'Unnamed Form'),
        'REMARKS'       => $message,
        'CONSENT'       => $consent,
        'FORM_NAME'     => $form_name,
        'FORM_ID'       => $form_id,
    ];

    // Build contact payload (always updateEnabled)
    $body = [
        'email'         => $email,
        'attributes'    => $attributes,
        'listIds'       => [$list_id],
        'updateEnabled' => true,
    ];

    // Post to Brevo
    if (!function_exists('h64_brevo_post')) {
        error_log('[h64-global] Missing helper h64_brevo_post(). Cannot contact Brevo.');
        return;
    }

    $res  = h64_brevo_post('/contacts', $body);
    if (is_wp_error($res)) {
        error_log('[h64-global] Brevo contact error: ' . $res->get_error_message());
        return;
    }
    $code = wp_remote_retrieve_response_code($res);
    if ($code < 200 || $code >= 300) {
        error_log('[h64-global] Brevo contact failed: HTTP ' . $code . ' ' . wp_remote_retrieve_body($res));
        return;
    }

    // Optional: transactional email per-form (if mapped)
    if ($template_id > 0 && $email) {
        $email_body = [
            'to' => [
                ['email' => $email, 'name' => $full_name]
            ],
            'templateId' => $template_id,
            'params' => [
                'FIRSTNAME' => $firstname,
                'LASTNAME'  => $lastname,
                'COUNTRY'   => $country,
                'FORM_NAME' => $form_name,
            ],
        ];
        $email_res  = h64_brevo_post('/smtp/email', $email_body);
        if (is_wp_error($email_res)) {
            error_log('[h64-global] Brevo email error: ' . $email_res->get_error_message());
        } else {
            $e_code = wp_remote_retrieve_response_code($email_res);
            if ($e_code < 200 || $e_code >= 300) {
                error_log('[h64-global] Brevo email failed: HTTP ' . $e_code . ' ' . wp_remote_retrieve_body($email_res));
            }
        }
    }
}, 10, 2);