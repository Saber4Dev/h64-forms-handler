<?php
/**
 * Plugin Name: H64 Forms Handler
 * Description: Handles Elementor forms with Brevo integration
 * Version: 1.0.0
 * Author: Hashtag 64
 * Author URI:        https://hashtag64.com/
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Update URI:        https://github.com/Saber4Dev/h64-forms-handler
 * Domain Path:       /languages
 * Requires Plugins:  Elementor, Elementor-pro
 */

if (!defined('ABSPATH')) exit;

require_once __DIR__ . '/includes/rulebook-handler.php';
require_once __DIR__ . '/includes/preorder-handler.php';
require_once __DIR__ . '/includes/newsletter-handler.php';
require_once __DIR__ . '/includes/contact-handler.php';
require_once __DIR__ . '/includes/update-checker.php';

add_action('admin_menu', function() {
    add_options_page(
        'H64 Forms Settings',
        'H64 Forms',
        'manage_options',
        'h64-forms-settings',
        'h64_forms_settings_page'
    );
});

add_action('admin_init', function() {
    // Contact/Brevo settings
    register_setting('h64_forms_settings_group', 'h64_brevo_list_contact', ['sanitize_callback' => 'absint']);
    register_setting('h64_forms_settings_group', 'h64_brevo_template_contact', ['sanitize_callback' => 'absint']);
    register_setting('h64_forms_settings_group', 'h64_contact_form', ['sanitize_callback' => 'sanitize_text_field']);
    register_setting('h64_forms_settings_group', 'h64_contact_form_id', ['sanitize_callback' => 'sanitize_text_field']);
    // Contact section fields
    add_settings_field('h64_brevo_list_contact', 'Brevo Contact List ID', function() {
        $value = esc_attr(get_option('h64_brevo_list_contact'));
        echo '<input type="number" name="h64_brevo_list_contact" value="' . $value . '" class="small-text" />';
    }, 'h64-forms-settings', 'h64_forms_settings_section');

    add_settings_field('h64_brevo_template_contact', 'Brevo Contact Template ID', function() {
        $value = esc_attr(get_option('h64_brevo_template_contact'));
        echo '<input type="number" name="h64_brevo_template_contact" value="' . $value . '" class="small-text" />';
    }, 'h64-forms-settings', 'h64_forms_settings_section');

    add_settings_field('h64_contact_form_combined', 'Contact Elementor Form', function() {
        $form_name = esc_attr(get_option('h64_contact_form'));
        $form_id = esc_attr(get_option('h64_contact_form_id'));
        ?>
        <div class="h64-contact-form-row">
            <input type="text" name="h64_contact_form" value="<?php echo $form_name; ?>" class="regular-text" placeholder="Form Name" style="margin-right: 1em; min-width: 120px;" />
            <input type="text" name="h64_contact_form_id" value="<?php echo $form_id; ?>" class="regular-text" placeholder="Form ID" style="min-width: 80px;" />
        </div>
        <div class="h64-contact-form-help" style="font-size:12px; margin-top:0.5em;">
            Use either the Elementor form name or form ID. Leave the other blank.<br>
            If you set a Brevo Contact Template ID, a transactional email will be sent to the contact using that template. Available params: <code>FIRSTNAME</code>, <code>LASTNAME</code>, <code>PHONE_NUMBER</code>, <code>REMARKS</code>.
        </div>
        <style>
        .h64-contact-form-row {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5em;
        }
        .h64-contact-form-row input {
            flex: 1 1 120px;
            min-width: 0;
            box-sizing: border-box;
        }
        @media screen and (max-width: 600px) {
            .h64-contact-form-row {
                flex-direction: column;
                gap: 0.5em;
            }
        }
        </style>
        <?php
    }, 'h64-forms-settings', 'h64_forms_settings_section');
    register_setting('h64_forms_settings_group', 'h64_brevo_api_key', ['sanitize_callback' => 'sanitize_text_field']);
    register_setting('h64_forms_settings_group', 'h64_webhook_secret', ['sanitize_callback' => 'sanitize_text_field']);

    register_setting('h64_forms_settings_group', 'h64_brevo_template_newsletter', ['sanitize_callback' => 'absint']);
    register_setting('h64_forms_settings_group', 'h64_brevo_list_newsletter', ['sanitize_callback' => 'absint']);
    register_setting('h64_forms_settings_group', 'h64_elementor_form', ['sanitize_callback' => 'sanitize_text_field']);
    register_setting('h64_forms_settings_group', 'h64_newsletter_form_id', ['sanitize_callback' => 'sanitize_text_field']);

    register_setting('h64_forms_settings_group', 'h64_brevo_template_preorder', ['sanitize_callback' => 'absint']);
    register_setting('h64_forms_settings_group', 'h64_brevo_list_preorder', ['sanitize_callback' => 'absint']);
    register_setting('h64_forms_settings_group', 'h64_preorder_form', ['sanitize_callback' => 'sanitize_text_field']);
    register_setting('h64_forms_settings_group', 'h64_preorder_form_id', ['sanitize_callback' => 'sanitize_text_field']);

    // Register Rulebook settings (updated)
    register_setting('h64_forms_settings_group', 'h64_rulebook_file_path', ['sanitize_callback' => 'sanitize_text_field']);
    register_setting('h64_forms_settings_group', 'h64_rulebook_admin_emails', ['sanitize_callback' => 'sanitize_textarea_field']);
    register_setting('h64_forms_settings_group', 'h64_rulebook_secret_key', ['sanitize_callback' => 'sanitize_text_field']);
    // Register Enable Rulebook Handler setting (default enabled, sanitize as absint)
    register_setting('h64_forms_settings_group', 'h64_rulebook_enabled', [
        'sanitize_callback' => function($value) {
            // Accepts 0 or 1 (checkbox), fallback to 1 (enabled)
            return absint($value) ? 1 : 0;
        },
        'default' => 1,
    ]);

    add_settings_section('h64_forms_settings_section', 'H64 Forms Settings', null, 'h64-forms-settings');

    add_settings_field('h64_brevo_api_key', 'Brevo API Key', function() {
        $value = esc_attr(get_option('h64_brevo_api_key'));
        ?>
        <input type="password" id="h64_brevo_api_key" name="h64_brevo_api_key" value="<?php echo $value; ?>" class="regular-text" />
        <button type="button" id="toggle_api_key" class="button" style="margin-left:5px;">Show</button>
        <?php
    }, 'h64-forms-settings', 'h64_forms_settings_section');

    add_settings_field('h64_webhook_secret', 'Webhook Secret', function() {
        $value = esc_attr(get_option('h64_webhook_secret'));
        echo '<input type="text" name="h64_webhook_secret" value="' . $value . '" class="regular-text" />';
    }, 'h64-forms-settings', 'h64_forms_settings_section');

    add_settings_field('h64_brevo_template_newsletter', 'Brevo Newsletter Template ID', function() {
        $value = esc_attr(get_option('h64_brevo_template_newsletter'));
        echo '<input type="number" name="h64_brevo_template_newsletter" value="' . $value . '" class="small-text" />';
    }, 'h64-forms-settings', 'h64_forms_settings_section');

    add_settings_field('h64_brevo_list_newsletter', 'Brevo Newsletter Contact List ID', function() {
        $value = esc_attr(get_option('h64_brevo_list_newsletter'));
        echo '<input type="number" name="h64_brevo_list_newsletter" value="' . $value . '" class="small-text" />';
    }, 'h64-forms-settings', 'h64_forms_settings_section');

    // Remove the two separate fields and add a combined field for Elementor Form Target
    add_settings_field('h64_elementor_form_target', 'Elementor Form Target', function() {
        $form_name = esc_attr(get_option('h64_elementor_form'));
        $form_id = esc_attr(get_option('h64_newsletter_form_id'));
        ?>
        <div class="h64-elementor-form-row">
            <input type="text" name="h64_elementor_form" value="<?php echo $form_name; ?>" class="regular-text" placeholder="Form Name" style="margin-right: 1em; min-width: 120px;" />
            <input type="text" name="h64_newsletter_form_id" value="<?php echo $form_id; ?>" class="regular-text" placeholder="Form ID" style="min-width: 80px;" />
        </div>
        <div class="h64-elementor-form-help" style="font-size:12px; margin-top:0.5em;">
            Use either the Elementor form name or form ID. Leave the other blank.
        </div>
        <style>
        .h64-elementor-form-row {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5em;
        }
        .h64-elementor-form-row input {
            flex: 1 1 120px;
            min-width: 0;
            box-sizing: border-box;
        }
        @media screen and (max-width: 600px) {
            .h64-elementor-form-row {
                flex-direction: column;
                gap: 0.5em;
            }
        }
        </style>
        <?php
    }, 'h64-forms-settings', 'h64_forms_settings_section');

    add_settings_field('h64_brevo_template_preorder', 'Brevo Preorder Template ID', function() {
        $value = esc_attr(get_option('h64_brevo_template_preorder'));
        echo '<input type="number" name="h64_brevo_template_preorder" value="' . $value . '" class="small-text" />';
    }, 'h64-forms-settings', 'h64_forms_settings_section');

    add_settings_field('h64_brevo_list_preorder', 'Brevo Preorder Contact List ID', function() {
        $value = esc_attr(get_option('h64_brevo_list_preorder'));
        echo '<input type="number" name="h64_brevo_list_preorder" value="' . $value . '" class="small-text" />';
    }, 'h64-forms-settings', 'h64_forms_settings_section');

    // Combined Preorder Elementor Form name and ID
    add_settings_field('h64_preorder_form_combined', 'Preorder Elementor Form', function() {
        $form_name = esc_attr(get_option('h64_preorder_form'));
        $form_id = esc_attr(get_option('h64_preorder_form_id'));
        ?>
        <div class="h64-preorder-form-row">
            <input type="text" name="h64_preorder_form" value="<?php echo $form_name; ?>" class="regular-text" placeholder="Form Name" style="margin-right: 1em; min-width: 120px;" />
            <input type="text" name="h64_preorder_form_id" value="<?php echo $form_id; ?>" class="regular-text" placeholder="Form ID" style="min-width: 80px;" />
        </div>
        <div class="h64-preorder-form-help" style="font-size:12px; margin-top:0.5em;">
            Use either the Elementor form name or form ID. Leave the other blank.
        </div>
        <style>
        .h64-preorder-form-row {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5em;
        }
        .h64-preorder-form-row input {
            flex: 1 1 120px;
            min-width: 0;
            box-sizing: border-box;
        }
        @media screen and (max-width: 600px) {
            .h64-preorder-form-row {
                flex-direction: column;
                gap: 0.5em;
            }
        }
        </style>
        <?php
    }, 'h64-forms-settings', 'h64_forms_settings_section');
});

function h64_forms_settings_page() {
    $nonce = wp_create_nonce('h64_brevo_test');
    ?>
    <div class="wrap">
        <h1>H64 Forms Settings</h1>
        <div style="display:flex; gap:20px; align-items:flex-start;">
            <div style="flex:2;">
                <form method="post" action="options.php" id="h64-forms-settings-form">
                    <?php
                    settings_fields('h64_forms_settings_group');
                    ?>

                    <h2>Brevo API Settings</h2>
                    <table class="form-table">
                        <tbody>
                            <tr>
                                <th><label for="h64_brevo_api_key">Brevo API Key</label></th>
                                <td>
                                    <input type="password" id="h64_brevo_api_key" name="h64_brevo_api_key" value="<?php echo esc_attr(get_option('h64_brevo_api_key')); ?>" class="regular-text" />
                                    <button type="button" id="toggle_api_key" class="button" style="margin-left:5px;">Show</button>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="h64_webhook_secret">Webhook Secret</label></th>
                                <td><input type="text" name="h64_webhook_secret" id="h64_webhook_secret" value="<?php echo esc_attr(get_option('h64_webhook_secret')); ?>" class="regular-text" /></td>
                            </tr>
                        <tr>
                            <th>
                            <p>API STATUS</p>
                            </th>
                            <td>
                            <p style="font-size: 12px;">Please save the settings before testing the connection of API. The test will confirm if your Brevo API key is valid and connected.</p>
                            <button type="button" id="test_connection" class="button button-secondary" data-nonce="<?php echo esc_attr($nonce); ?>">Test Connection</button>
                    <div id="test_connection_result" style="margin-top:8px;"></div>
                            </td>
                        </tr>
                        </tbody>

                    </table>

                    <h2>Newsletter Settings</h2>
                    <table class="form-table">
                        <tbody>
                            <tr>
                                <th><label for="h64_brevo_template_newsletter">Brevo Newsletter Template ID</label></th>
                                <td><input type="number" name="h64_brevo_template_newsletter" id="h64_brevo_template_newsletter" value="<?php echo esc_attr(get_option('h64_brevo_template_newsletter')); ?>" class="small-text" /></td>
                            </tr>
                            <tr>
                                <th><label for="h64_brevo_list_newsletter">Brevo Newsletter Contact List ID</label></th>
                                <td><input type="number" name="h64_brevo_list_newsletter" id="h64_brevo_list_newsletter" value="<?php echo esc_attr(get_option('h64_brevo_list_newsletter')); ?>" class="small-text" /></td>
                            </tr>
                            <tr>
                                <th><label for="h64_elementor_form">Elementor Form</label></th>
                                <td>
                                    <p style="font-size:12px; color:#555; margin:0 0 8px 0;">
                                      ⚠️ In your Elementor form, make sure each field has the correct <strong>Field ID</strong> assigned in the form editor. These IDs must match exactly what you configure here, otherwise the integration will not work.
                                    </p>
                                    <div class="h64-elementor-form-row">
                                        <input type="text" name="h64_elementor_form" id="h64_elementor_form" value="<?php echo esc_attr(get_option('h64_elementor_form')); ?>" class="regular-text" placeholder="Form Name" style="margin-right: 1em; min-width: 120px;" />
                                        <input type="text" name="h64_newsletter_form_id" id="h64_newsletter_form_id" value="<?php echo esc_attr(get_option('h64_newsletter_form_id')); ?>" class="regular-text" placeholder="Form ID" style="min-width: 80px;" />
                                    </div>
                                    <div class="h64-elementor-form-help" style="font-size:12px; margin-top:0.5em;">
                                        Use either the Elementor form name or form ID. Leave the other blank.
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <h2>Contact Settings</h2>
                    <table class="form-table">
                        <tbody>
                            
                            <tr>
                                <th><label for="h64_brevo_template_contact">Brevo Contact Template ID</label></th>
                                <td><input type="number" name="h64_brevo_template_contact" id="h64_brevo_template_contact" value="<?php echo esc_attr(get_option('h64_brevo_template_contact')); ?>" class="small-text" /></td>
                            </tr>
                            <tr>
                                <th><label for="h64_brevo_list_contact">Brevo Contact List ID</label></th>
                                <td><input type="number" name="h64_brevo_list_contact" id="h64_brevo_list_contact" value="<?php echo esc_attr(get_option('h64_brevo_list_contact')); ?>" class="small-text" /></td>
                            </tr>
                            <tr>
                                <th><label for="h64_contact_form">Contact Elementor Form</label></th>
                                <td>
                                    <p style="font-size:12px; color:#555; margin:0 0 8px 0;">
                                      ⚠️ In your Elementor form, make sure each field has the correct <strong>Field ID</strong> assigned in the form editor. These IDs must match exactly what you configure here, otherwise the integration will not work.
                                    </p>
                                    <div class="h64-contact-form-row">
                                        <input type="text" name="h64_contact_form" id="h64_contact_form" value="<?php echo esc_attr(get_option('h64_contact_form')); ?>" class="regular-text" placeholder="Form Name" style="margin-right: 1em; min-width: 120px;" />
                                        <input type="text" name="h64_contact_form_id" id="h64_contact_form_id" value="<?php echo esc_attr(get_option('h64_contact_form_id')); ?>" class="regular-text" placeholder="Form ID" style="min-width: 80px;" />
                                    </div>
                                    <div class="h64-contact-form-help" style="font-size:12px; margin-top:0.5em;">
                                        Use either the Elementor form name or form ID. Leave the other blank.
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <h2>Preorder Settings</h2>
                    <table class="form-table">
                        <tbody>
                            <tr>
                                <th><label for="h64_brevo_template_preorder">Brevo Preorder Template ID</label></th>
                                <td><input type="number" name="h64_brevo_template_preorder" id="h64_brevo_template_preorder" value="<?php echo esc_attr(get_option('h64_brevo_template_preorder')); ?>" class="small-text" /></td>
                            </tr>
                            <tr>
                                <th><label for="h64_brevo_list_preorder">Brevo Preorder Contact List ID</label></th>
                                <td><input type="number" name="h64_brevo_list_preorder" id="h64_brevo_list_preorder" value="<?php echo esc_attr(get_option('h64_brevo_list_preorder')); ?>" class="small-text" /></td>
                            </tr>
                            <tr>
                                <th><label for="h64_preorder_form">Preorder Elementor Form</label></th>
                                <td>
                                    <p style="font-size:12px; color:#555; margin:0 0 8px 0;">
                                      ⚠️ In your Elementor form, make sure each field has the correct <strong>Field ID</strong> assigned in the form editor. These IDs must match exactly what you configure here, otherwise the integration will not work.
                                    </p>
                                   
                                    <div class="h64-preorder-form-row">
                                        <input type="text" name="h64_preorder_form" id="h64_preorder_form" value="<?php echo esc_attr(get_option('h64_preorder_form')); ?>" class="regular-text" placeholder="Form Name" style="margin-right: 1em; min-width: 120px;" />
                                        <input type="text" name="h64_preorder_form_id" id="h64_preorder_form_id" value="<?php echo esc_attr(get_option('h64_preorder_form_id')); ?>" class="regular-text" placeholder="Form ID" style="min-width: 80px;" />
                                    </div>
                                    <div class="h64-preorder-form-help" style="font-size:12px; margin-top:0.5em;">
                                        Use either the Elementor form name or form ID. Leave the other blank.
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <h2>Rulebook Settings</h2>
                    <table class="form-table">
                        <tbody>
                            <tr>
                                <th scope="row">
                                    <label for="h64_rulebook_enabled">Enable Rulebook Handler</label>
                                </th>
                                <td>
                                    <input type="checkbox" name="h64_rulebook_enabled" id="h64_rulebook_enabled" value="1" <?php checked(get_option('h64_rulebook_enabled', 1), 1); ?> />
                                    <span style="font-size:12px;">Enable the protected rulebook download handler and shortcode.</span>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="h64_rulebook_file_path">Protected File Path</label></th>
                                <td>
                                    <input type="text" name="h64_rulebook_file_path" id="h64_rulebook_file_path" value="<?php echo esc_attr(get_option('h64_rulebook_file_path', WP_CONTENT_DIR . '/uploads/protected/ARDEVUR_Digital_Rulebook.pdf')); ?>" class="regular-text" />
                                    <div style="font-size:12px; margin-top:0.5em;">
                                        Enter the full absolute file path to the protected PDF file. Example: <code>/home/youruser/public_html/wp-content/uploads/protected/file.pdf</code>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="h64_rulebook_admin_emails">Admin Emails</label></th>
                                <td>
                                    <textarea name="h64_rulebook_admin_emails" id="h64_rulebook_admin_emails" rows="2" class="large-text"><?php echo esc_textarea(get_option('h64_rulebook_admin_emails')); ?></textarea>
                                    <div style="font-size:12px; margin-top:0.5em;">
                                      Multiple admin emails can be comma-separated.
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="h64_rulebook_secret_key">Secret Key</label></th>
                                <td>
                                    <input type="password" name="h64_rulebook_secret_key" id="h64_rulebook_secret_key" value="<?php echo esc_attr(get_option('h64_rulebook_secret_key', '')); ?>" class="regular-text" />
                                    <button type="button" id="toggle_rulebook_secret_key" class="button" style="margin-left:5px;">Show</button>
                                    <div style="font-size:12px; margin-top:0.5em;">
                                        Use a strong secret key for secure download links.
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <?php submit_button(); ?>
                </form>
            </div>
            <div style="flex:1; max-width:320px;">
                <div style="font-size:12px;">
                    <strong>Available Brevo Attributes:</strong>
                    <table style="border-collapse:collapse; margin-top:4px; width:100%;">
                        <tr><th style="border:1px solid #ccc; padding:2px 6px;">Attribute Name</th><th style="border:1px solid #ccc; padding:2px 6px;">Attribute Type</th></tr>
                        <tr><td style="border:1px solid #ccc; padding:2px 6px;">EMAIL</td><td style="border:1px solid #ccc; padding:2px 6px;">Text</td></tr>
                        <tr><td style="border:1px solid #ccc; padding:2px 6px;">LASTNAME</td><td style="border:1px solid #ccc; padding:2px 6px;">Text</td></tr>
                        <tr><td style="border:1px solid #ccc; padding:2px 6px;">FIRSTNAME</td><td style="border:1px solid #ccc; padding:2px 6px;">Text</td></tr>
                        <tr><td style="border:1px solid #ccc; padding:2px 6px;">SMS</td><td style="border:1px solid #ccc; padding:2px 6px;">Text</td></tr>
                        <tr><td style="border:1px solid #ccc; padding:2px 6px;">EXT_ID</td><td style="border:1px solid #ccc; padding:2px 6px;">Text</td></tr>
                        <tr><td style="border:1px solid #ccc; padding:2px 6px;">LANDLINE_NUMBER</td><td style="border:1px solid #ccc; padding:2px 6px;">Text</td></tr>
                        <tr><td style="border:1px solid #ccc; padding:2px 6px;">CONTACT_TIMEZONE</td><td style="border:1px solid #ccc; padding:2px 6px;">Text</td></tr>
                        <tr><td style="border:1px solid #ccc; padding:2px 6px;">JOB_TITLE</td><td style="border:1px solid #ccc; padding:2px 6px;">Text</td></tr>
                        <tr><td style="border:1px solid #ccc; padding:2px 6px;">LINKEDIN</td><td style="border:1px solid #ccc; padding:2px 6px;">Text</td></tr>
                        <tr><td style="border:1px solid #ccc; padding:2px 6px;">ROLE</td><td style="border:1px solid #ccc; padding:2px 6px;">Text</td></tr>
                        <tr><td style="border:1px solid #ccc; padding:2px 6px;">CONSENT</td><td style="border:1px solid #ccc; padding:2px 6px;">Text</td></tr>
                        <tr><td style="border:1px solid #ccc; padding:2px 6px;">PREORDER_ID</td><td style="border:1px solid #ccc; padding:2px 6px;">Text</td></tr>
                        <tr><td style="border:1px solid #ccc; padding:2px 6px;">UNITS</td><td style="border:1px solid #ccc; padding:2px 6px;">Number</td></tr>
                        <tr><td style="border:1px solid #ccc; padding:2px 6px;">LANGUAGE</td><td style="border:1px solid #ccc; padding:2px 6px;">Text</td></tr>
                        <tr><td style="border:1px solid #ccc; padding:2px 6px;">SOURCE</td><td style="border:1px solid #ccc; padding:2px 6px;">Text</td></tr>
                        <tr><td style="border:1px solid #ccc; padding:2px 6px;">PHONE_NUMBER</td><td style="border:1px solid #ccc; padding:2px 6px;">Text</td></tr>
                        <tr><td style="border:1px solid #ccc; padding:2px 6px;">REMARKS</td><td style="border:1px solid #ccc; padding:2px 6px;">Text</td></tr>
                        <tr><td style="border:1px solid #ccc; padding:2px 6px;">COUNTRY</td><td style="border:1px solid #ccc; padding:2px 6px;">Text</td></tr>
                    </table>
                </div>
            </div>
        </div>

    <style>
        @media screen and (max-width: 600px) {
            .form-table, .form-table tbody, .form-table tr, .form-table th, .form-table td {
                display: block;
                width: 100%;
            }
            .form-table tr {
                margin-bottom: 1.5em;
            }
            .form-table th {
                width: 320px;
                font-weight: bold;
                margin-bottom: 0.5em;
            }
            .form-table td {
                margin-bottom: 0.5em;
            }
        }
        /* Responsive styling for elementor/contact/preorder form rows */
        .h64-elementor-form-row,
        .h64-contact-form-row,
        .h64-preorder-form-row {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5em;
        }
        .h64-elementor-form-row input,
        .h64-contact-form-row input,
        .h64-preorder-form-row input {
            flex: 1 1 200px;
            min-width: 0;
            max-width: 250px;
            box-sizing: border-box;
        }
        @media screen and (max-width: 600px) {
            .h64-elementor-form-row,
            .h64-contact-form-row,
            .h64-preorder-form-row {
                flex-direction: column;
                gap: 0.5em;
            }
        }
    </style>

    <script>
    (function(){
        // Brevo API Key toggle
        const toggleBtn = document.getElementById('toggle_api_key');
        const apiKeyInput = document.getElementById('h64_brevo_api_key');
        if (toggleBtn && apiKeyInput) {
            toggleBtn.addEventListener('click', function() {
                if (apiKeyInput.type === 'password') {
                    apiKeyInput.type = 'text';
                    toggleBtn.textContent = 'Hide';
                } else {
                    apiKeyInput.type = 'password';
                    toggleBtn.textContent = 'Show';
                }
            });
        }

        // Rulebook Secret Key toggle
        const rulebookSecretBtn = document.getElementById('toggle_rulebook_secret_key');
        const rulebookSecretInput = document.getElementById('h64_rulebook_secret_key');
        if (rulebookSecretBtn && rulebookSecretInput) {
            rulebookSecretBtn.addEventListener('click', function() {
                if (rulebookSecretInput.type === 'password') {
                    rulebookSecretInput.type = 'text';
                    rulebookSecretBtn.textContent = 'Hide';
                } else {
                    rulebookSecretInput.type = 'password';
                    rulebookSecretBtn.textContent = 'Show';
                }
            });
        }

        // Brevo Test Connection
        const testBtn = document.getElementById('test_connection');
        const resultDiv = document.getElementById('test_connection_result');
        if (testBtn && resultDiv) {
            testBtn.addEventListener('click', function() {
                resultDiv.textContent = 'Testing...';
                fetch(ajaxurl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
                    body: new URLSearchParams({
                        action: 'h64_brevo_test',
                        nonce: testBtn.getAttribute('data-nonce')
                    })
                }).then(response => response.json())
                .then(data => {
                    if(data.success) {
                        resultDiv.style.color = 'green';
                        resultDiv.textContent = data.data.message + ' (HTTP ' + data.data.code + ')';
                    } else {
                        resultDiv.style.color = 'red';
                        resultDiv.textContent = data.data.message;
                    }
                }).catch(err => {
                    resultDiv.style.color = 'red';
                    resultDiv.textContent = 'Error: ' + err.message;
                });
            });
        }
    })();
    </script>
    <?php
}

function h64_get_option($key, $default = '') {
    $value = get_option($key, $default);
    if ($value === false) {
        return $default;
    }
    return $value;
}

add_filter('plugin_action_links_' . plugin_basename(__FILE__), function($links) {
    $settings_link = '<a href="options-general.php?page=h64-forms-settings">Settings</a>';
    array_unshift($links, $settings_link);
    return $links;
});

if (!function_exists('h64_brevo_api_key')) {
    function h64_brevo_api_key() {
        return trim(get_option('h64_brevo_api_key', ''));
    }
}

if (!function_exists('h64_brevo_headers')) {
    function h64_brevo_headers() {
        return [
            'Content-Type' => 'application/json',
            'api-key' => h64_brevo_api_key(),
        ];
    }
}

if (!function_exists('h64_brevo_request')) {
    function h64_brevo_request($method, $path, $body = null) {
        $url = 'https://api.brevo.com/v3' . $path;
        $args = [
            'method' => strtoupper($method),
            'headers' => h64_brevo_headers(),
            'timeout' => 15,
        ];
        if ($body !== null) {
            $args['body'] = wp_json_encode($body);
        }
        if ($method === 'get') {
            return wp_remote_get($url, $args);
        } else {
            return wp_remote_post($url, $args);
        }
    }
}

if (!function_exists('h64_brevo_post')) {
    function h64_brevo_post($path, $body) {
        return h64_brevo_request('post', $path, $body);
    }
}

if (!function_exists('h64_brevo_get')) {
    function h64_brevo_get($path) {
        return h64_brevo_request('get', $path);
    }
}

if (!function_exists('h64_split_name')) {
    function h64_split_name($name) {
        $parts = preg_split('/\s+/', trim($name));
        $firstName = $parts[0] ?? '';
        $lastName = count($parts) > 1 ? implode(' ', array_slice($parts, 1)) : '';
        return [$firstName, $lastName];
    }
}

add_action('wp_ajax_h64_brevo_test', function(){
    check_ajax_referer('h64_brevo_test','nonce');
    $res = h64_brevo_get('/account');
    if (is_wp_error($res)) {
        wp_send_json_error(['message' => $res->get_error_message()]);
    }
    $code = wp_remote_retrieve_response_code($res);
    $body = wp_remote_retrieve_body($res);
    if ($code >= 200 && $code < 300) {
        wp_send_json_success(['message' => 'Connected to Brevo', 'code' => $code]);
    } else {
        wp_send_json_error(['message' => 'HTTP ' . $code . ' ' . $body]);
    }
});
