=== H64 Forms Handler ===
Contributors: hashtag64, saber4dev
Tags: elementor, brevo, sendinblue, forms, automation
Requires at least: 5.8
Tested up to: 6.7
Requires PHP: 7.3
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A lightweight WordPress plugin to connect Elementor Forms with Brevo (Sendinblue) for contact management, newsletter subscriptions, and preorder submissions — all under one organized dashboard.

== Description ==

**H64 Forms Handler** is a custom plugin built for Hashtag 64 Games and similar creative projects that use Elementor Forms with Brevo (Sendinblue) integration.

It automatically sends user data to Brevo lists and templates depending on the form type (Newsletter, Contact, or Preorder).  
Each section in the admin dashboard allows you to:
- Configure Brevo API key and Webhook secret.
- Assign Elementor Form Names or IDs.
- Map correct field IDs between Elementor and Brevo.
- Test API connectivity from the dashboard.
- Enable a protected Rulebook download handler with Brevo tracking.

== Features ==
- Elementor Form integration (Newsletter, Contact, Preorder)
- Brevo (Sendinblue) API connection and live test
- Rulebook secure file delivery
- Dynamic field ID mapping
- Brevo attribute reference panel
- Auto-update support from GitHub
- Lightweight and modular PHP architecture

== Installation ==
1. Upload the plugin files to the `/wp-content/plugins/h64-forms-handler` directory.
2. Activate the plugin through the ‘Plugins’ menu in WordPress.
3. Go to **Settings → H64 Forms Handler** to configure API keys and forms.
4. Enter your Brevo API Key and verify connection.
5. Match Elementor Form IDs and Brevo Attributes.
6. (Optional) Enable the Rulebook secure download system.

== Screenshots ==
1. Plugin settings page with Elementor and Brevo configuration.
2. API test connection status.
3. Rulebook protected file handler.

== Frequently Asked Questions ==

= How do I get a Brevo API key? =
Log in to Brevo → Go to **SMTP & API** → Generate a new v3 API key.

= Does this plugin work without Elementor? =
No, Elementor (and ideally Elementor Pro) is required for form creation.

= Can I use this for multiple websites? =
Yes, as long as you configure the Brevo API key and form mappings per site.

= Will it auto-update from GitHub? =
Yes — as long as the plugin repository is public and releases are tagged (v1.0.1, v1.0.2, etc.).

== Changelog ==

= 1.0.0 =
* Initial release.
* Brevo + Elementor integration.
* Rulebook secure handler.
* GitHub auto-update support.

== License ==
This plugin is licensed under the GPL v2 or later.  
See the `license.txt` file for full details.
