<?php
/**
 * Plugin Name: H64 Forms Handler
 * Description: Handles all Elementor forms globally with Brevo integration
 * Version: 2.0.0
 * Author: Hashtag 64
 * Author URI: https://hashtag64.com/
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Update URI: https://github.com/Saber4Dev/h64-forms-handler
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) exit;

require_once __DIR__ . '/includes/rulebook-handler.php';
require_once __DIR__ . '/includes/update-checker.php';
require_once __DIR__ . '/includes/global_form_handler.php';

/**
 * Admin Page
 */
add_action('admin_menu', function() {
    add_options_page(
        'H64 Forms Settings',
        'H64 Forms',
        'manage_options',
        'h64-forms-settings',
        'h64_forms_settings_page'
    );
});

/**
 * Admin Settings Registration
 */
add_action('admin_init', function() {

    // Basic API + Security
    register_setting('h64_forms_settings_group', 'h64_brevo_api_key', ['sanitize_callback' => 'sanitize_text_field']);
    register_setting('h64_forms_settings_group', 'h64_webhook_secret', ['sanitize_callback' => 'sanitize_text_field']);

    // Universal forms mapping
    register_setting('h64_forms_settings_group', 'h64_forms_map', ['sanitize_callback' => function($input){
        if (!is_array($input)) return [];
        $out = [];
        foreach ($input as $k => $row) {
            $out[$k] = [
                'list_id'     => isset($row['list_id']) ? (int)$row['list_id'] : 0,
                'template_id' => isset($row['template_id']) ? (int)$row['template_id'] : 0,
            ];
        }
        return $out;
    }]);
    register_setting('h64_forms_settings_group', 'h64_brevo_list_global', ['sanitize_callback' => 'absint']);

    // Rulebook settings
    register_setting('h64_forms_settings_group', 'h64_rulebook_enabled', ['sanitize_callback' => 'absint', 'default' => 1]);
    register_setting('h64_forms_settings_group', 'h64_rulebook_file_path', ['sanitize_callback' => 'sanitize_text_field']);
    register_setting('h64_forms_settings_group', 'h64_rulebook_admin_emails', ['sanitize_callback' => 'sanitize_textarea_field']);
    register_setting('h64_forms_settings_group', 'h64_rulebook_secret_key', ['sanitize_callback' => 'sanitize_text_field']);

    add_settings_section('h64_forms_settings_section', 'H64 Forms Settings', null, 'h64-forms-settings');

    // Brevo API key
    add_settings_field('h64_brevo_api_key', 'Brevo API Key', function() {
        $value = esc_attr(get_option('h64_brevo_api_key'));
        echo '<input type="password" id="h64_brevo_api_key" name="h64_brevo_api_key" value="'.$value.'" class="regular-text" />';
        echo ' <button type="button" id="toggle_api_key" class="button">Show</button>';
    }, 'h64-forms-settings', 'h64_forms_settings_section');

    // Webhook Secret
    add_settings_field('h64_webhook_secret', 'Webhook Secret', function() {
        echo '<input type="text" name="h64_webhook_secret" value="'.esc_attr(get_option('h64_webhook_secret')).'" class="regular-text" />';
    }, 'h64-forms-settings', 'h64_forms_settings_section');

    // Default Global List
    add_settings_field('h64_brevo_list_global', 'Default Brevo List ID', function() {
        $value = (int)get_option('h64_brevo_list_global');
        echo '<input type="number" name="h64_brevo_list_global" value="'.esc_attr($value).'" class="small-text" />';
        echo '<p class="description">Default Brevo list used for all Elementor forms without a specific mapping.</p>';
    }, 'h64-forms-settings', 'h64_forms_settings_section');

    // Forms map table
    add_settings_field('h64_forms_map', 'Elementor Forms → Brevo Mapping', function() {
        global $wpdb;
        $forms = [];
        $rows = $wpdb->get_results("SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = '_elementor_data' LIMIT 3000", ARRAY_A);
        foreach ($rows as $row) {
            $data = json_decode($row['meta_value'], true);
            if (!is_array($data)) continue;
            $stack = $data;
            while ($stack) {
                $el = array_shift($stack);
                if (!is_array($el)) continue;
                if (!empty($el['elements']) && is_array($el['elements'])) {
                    foreach ($el['elements'] as $child) $stack[] = $child;
                }
                if (($el['widgetType'] ?? '') === 'form') {
                    $settings  = $el['settings'] ?? [];
                    $form_name = trim($settings['form_name'] ?? '');
                    $form_id   = trim($settings['form_id'] ?? '');
                    if ($form_name || $form_id) {
                        $key = $form_id ?: $form_name;
                        $forms[$key] = [
                            'form_id' => $form_id,
                            'form_name' => $form_name,
                        ];
                    }
                }
            }
        }
        ksort($forms);
        $map = (array)get_option('h64_forms_map', []);

        echo '<table class="widefat striped" style="max-width:900px">';
        echo '<thead><tr><th>Form Name</th><th>Form ID</th><th>Brevo List ID</th><th>Brevo Template ID</th></tr></thead><tbody>';
        if (!$forms) {
            echo '<tr><td colspan="4"><em>No Elementor forms detected.</em></td></tr>';
        } else {
            foreach ($forms as $key => $info) {
                $row = $map[$key] ?? ['list_id'=>0,'template_id'=>0];
                echo '<tr>';
                echo '<td>'.esc_html($info['form_name'] ?: '—').'</td>';
                echo '<td>'.esc_html($info['form_id'] ?: '—').'</td>';
                echo '<td><input type="number" name="h64_forms_map['.esc_attr($key).'][list_id]" value="'.esc_attr($row['list_id']).'" class="small-text" /></td>';
                echo '<td><input type="number" name="h64_forms_map['.esc_attr($key).'][template_id]" value="'.esc_attr($row['template_id']).'" class="small-text" /></td>';
                echo '</tr>';
            }
        }
        echo '</tbody></table>';
        echo '<p class="description">Map each detected Elementor form to a specific Brevo Contact List and optional Template ID.</p>';
    }, 'h64-forms-settings', 'h64_forms_settings_section');

    // Rulebook settings fields
    add_settings_field('h64_rulebook_enabled', 'Enable Rulebook Handler', function() {
        $checked = checked(get_option('h64_rulebook_enabled', 1), 1, false);
        echo '<label><input type="checkbox" name="h64_rulebook_enabled" value="1" '.$checked.' /> Enable rulebook protected download handler</label>';
    }, 'h64-forms-settings', 'h64_forms_settings_section');

    add_settings_field('h64_rulebook_file_path', 'Protected File Path', function() {
        $value = esc_attr(get_option('h64_rulebook_file_path', WP_CONTENT_DIR . '/uploads/protected/ARDEVUR_Digital_Rulebook.pdf'));
        echo '<input type="text" name="h64_rulebook_file_path" value="'.$value.'" class="regular-text" />';
        echo '<p class="description">Full absolute path to protected PDF.</p>';
    }, 'h64-forms-settings', 'h64_forms_settings_section');

    add_settings_field('h64_rulebook_admin_emails', 'Admin Emails', function() {
        $value = esc_textarea(get_option('h64_rulebook_admin_emails'));
        echo '<textarea name="h64_rulebook_admin_emails" rows="2" class="large-text">'.$value.'</textarea>';
        echo '<p class="description">Comma-separated admin emails for notifications.</p>';
    }, 'h64-forms-settings', 'h64_forms_settings_section');

    add_settings_field('h64_rulebook_secret_key', 'Secret Key', function() {
        $value = esc_attr(get_option('h64_rulebook_secret_key', ''));
        echo '<input type="password" name="h64_rulebook_secret_key" id="h64_rulebook_secret_key" value="'.$value.'" class="regular-text" />';
        echo ' <button type="button" id="toggle_rulebook_secret_key" class="button">Show</button>';
    }, 'h64-forms-settings', 'h64_forms_settings_section');
});

/**
 * Admin Page HTML
 */
function h64_forms_settings_page() {
    ?>
    <div class="wrap">
        <h1>H64 Forms Settings</h1>
        
        <form method="post" action="options.php">
            <?php
            settings_fields('h64_forms_settings_group');
            do_settings_sections('h64-forms-settings');
            submit_button();
            ?>
        </form>
    </div>
<p>
    <button type="button" class="button" id="h64_brevo_test_btn">🔄 Test Brevo API Connection</button>
    <span id="h64_brevo_test_result" style="margin-left:10px;"></span>
</p>
    <script>
    // Toggle password fields
    document.addEventListener('DOMContentLoaded', () => {
        const toggle = (btnId, inputId) => {
            const btn = document.getElementById(btnId);
            const inp = document.getElementById(inputId);
            if (!btn || !inp) return;
            btn.addEventListener('click', () => {
                if (inp.type === 'password') {
                    inp.type = 'text';
                    btn.textContent = 'Hide';
                } else {
                    inp.type = 'password';
                    btn.textContent = 'Show';
                }
            });
        };
        toggle('toggle_api_key','h64_brevo_api_key');
        toggle('toggle_rulebook_secret_key','h64_rulebook_secret_key');
    });

document.addEventListener('DOMContentLoaded', function() {
    const btn = document.getElementById('h64_brevo_test_btn');
    const out = document.getElementById('h64_brevo_test_result');
    if (!btn) return;
    btn.addEventListener('click', function() {
        out.textContent = 'Testing...';
        fetch(ajaxurl, {
            method: 'POST',
            headers: {'Content-Type':'application/x-www-form-urlencoded'},
            body: new URLSearchParams({
                action: 'h64_brevo_test',
                nonce: '<?php echo wp_create_nonce("h64_brevo_test"); ?>'
            })
        })
        .then(res => res.json())
        .then(res => {
            if (res.success) {
                out.innerHTML = '<span style="color:green;">✅ ' + res.data.message + '</span>';
            } else {
                out.innerHTML = '<span style="color:red;">❌ ' + res.data.message + '</span>';
            }
        })
        .catch(err => out.innerHTML = '<span style="color:red;">Error: ' + err + '</span>');
    });
});
</script>


    <?php
}

/**
 * Helper + API
 */
function h64_get_option($key, $default = '') {
    $value = get_option($key, $default);
    return $value === false ? $default : $value;
}

function h64_brevo_api_key() { return trim(get_option('h64_brevo_api_key', '')); }

function h64_brevo_headers() {
    return ['Content-Type'=>'application/json','api-key'=>h64_brevo_api_key()];
}

function h64_brevo_request($method, $path, $body=null) {
    $url='https://api.brevo.com/v3'.$path;
    $args=['method'=>strtoupper($method),'headers'=>h64_brevo_headers(),'timeout'=>15];
    if ($body!==null) $args['body']=wp_json_encode($body);
    return ($method==='get') ? wp_remote_get($url,$args) : wp_remote_post($url,$args);
}

function h64_brevo_post($path,$body){ return h64_brevo_request('post',$path,$body); }
function h64_brevo_get($path){ return h64_brevo_request('get',$path); }

/**
 * Brevo Connection Test
 */
add_action('wp_ajax_h64_brevo_test', function(){
    check_ajax_referer('h64_brevo_test','nonce');
    $res = h64_brevo_get('/account');
    if (is_wp_error($res)) wp_send_json_error(['message'=>$res->get_error_message()]);
    $code = wp_remote_retrieve_response_code($res);
    $body = wp_remote_retrieve_body($res);
    if ($code >= 200 && $code < 300) wp_send_json_success(['message'=>'Connected to Brevo','code'=>$code]);
    wp_send_json_error(['message'=>'HTTP '.$code.' '.$body]);
});

