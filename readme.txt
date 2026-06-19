=== CF7 to Mautic ===
Contributors: Ufo Agency
Original Author: Ulrich Eckardt
Tags: Mautic, Contact Form 7, CF7, CRM, Marketing Automation, OAuth2
Requires at least: 5.8
Requires PHP: 7.4
Tested up to: 6.4
Stable tag: 2.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Sends submitted data from Contact Form 7 to Mautic with OAuth2 authentication and asynchronous processing.

== Description ==

This plugin connects Contact Form 7 to Mautic CRM. When a form is submitted, the contact data is automatically sent to Mautic, creating or updating the contact and adding them to a specified segment.

**This plugin is inspired by and based on the original work of Ulrich Eckardt, modified and enhanced by Ufo Agency.**

= Key Features =

* OAuth2 authentication (secure, no passwords stored in wp-config.php)
* Asynchronous processing via WP-Cron (form submission is instant, no spinner blocking)
* Automatic contact creation or update in Mautic
* Automatic segment assignment
* Optional Mautic form submission for tracking
* Debug logging support

= Changes by Ufo Agency (v0.5) =

* **OAuth2 Authentication**: Replaced basic auth with OAuth2 Client Credentials grant for better security
* **Asynchronous Processing**: Form submissions are now processed in the background via WP-Cron, preventing the CF7 spinner from blocking
* **Admin Settings Page**: Added a dedicated settings page under Settings > CF7 to Mautic
* **Connection Test**: Added a button to test the Mautic API connection
* **Improved Logging**: Enhanced debug logging for troubleshooting
* **IP Capture**: Visitor IP is captured before async processing for accurate tracking
* **Key Case Preservation**: Fixed field name sanitization to preserve camelCase (e.g., formId)

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to Settings > CF7 to Mautic
4. Enter your Mautic URL (without https://)
5. Enter your OAuth2 Client ID and Client Secret
6. Click "Test Connection" to verify

= Getting OAuth2 Credentials from Mautic =

1. In Mautic, go to **Settings > Integrations > API Credentials**
2. Click **+ New**
3. Select **OAuth 2**
4. Give it a name (e.g., "CF7 WordPress")
5. Leave "Redirect URI" empty or enter your site URL
6. Copy the **Public Key** (Client ID) and **Secret Key** (Client Secret)

== Configuration ==

= Adding a Segment (Required) =

In each CF7 form, add a hidden field to specify the Mautic segment:

`[hidden segment "your-segment-name"]`

= Mautic Form Submission (Optional) =

To submit data to a Mautic form for tracking:

`[hidden formId "16"]`

= Field Mapping =

The `your-` prefix is automatically removed from field names. Examples:

| CF7 Field | Mautic Field |
|-----------|--------------|
| `[email* your-email]` | email |
| `[text your-firstname]` | firstname |
| `[text your-lastname]` | lastname |
| `[tel your-phone]` | phone |

= Complete Example =

`<label>Email (required)
    [email* your-email]</label>

<label>First Name
    [text your-firstname]</label>

<label>Last Name
    [text your-lastname]</label>

<label>Message
    [textarea your-message]</label>

[hidden segment "newsletter-subscribers"]
[hidden formId "2"]

[submit "Send"]`

== Debugging ==

To enable debug logs, add these lines to your `wp-config.php`:

`define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );
define( 'WP_DEBUG_DISPLAY', false );`

Logs will be available in `wp-content/debug.log`

== Frequently Asked Questions ==

= Why was the form spinner blocking before? =

The original plugin made synchronous API calls to Mautic during form submission. Each call had a 30-second timeout, and with 5-6 sequential calls, the form could hang for up to 3 minutes if Mautic was slow.

= How does asynchronous processing work? =

When a form is submitted, the data is validated and scheduled for processing via WP-Cron. The form completes immediately, and the Mautic API calls happen in the background.

= Do I need to configure a real cron job? =

WP-Cron works with regular site traffic. For low-traffic sites, you can set up a real cron job:

`* * * * * curl -s "https://your-site.com/wp-cron.php?doing_wp_cron" > /dev/null 2>&1`

And disable WP-Cron in wp-config.php:

`define('DISABLE_WP_CRON', true);`

== Changelog ==

= 0.5 =
* Added OAuth2 authentication
* Added asynchronous processing via WP-Cron
* Added admin settings page
* Added connection test feature
* Improved debug logging
* Fixed field name case preservation
* Captured visitor IP before async processing

= 0.0.4 =
* Original version by Ulrich Eckardt

== Credits ==

* Original plugin by Ulrich Eckardt
* Modified and enhanced by Ufo Agency (https://ufo-agency.com)
