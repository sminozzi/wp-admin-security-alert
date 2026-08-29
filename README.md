# wp-admin-security-alert
R

**Contributors:** Bill Minozzi   
**Tags:** security, admin, notification, alerts, audit, email  
**Requires at least:** 5.0  
**Tested up to:** 7.1 
**Stable tag:** 2.0.0  
**License:** GPL-2.0-or-later  
**License URI:** https://www.gnu.org/licenses/gpl-2.0.html

Sends a comprehensive, real-time security notification whenever a new Administrator account is created or an existing user is promoted to Administrator.

## Description

The **Notify New Administrator** plugin is a lightweight yet powerful security tool for WordPress. It acts as an early warning system, ensuring you are immediately alerted to any changes in your site's administrative privileges.

Whenever a user is granted Administrator capabilities—whether through a new registration or a role change—this plugin sends a detailed email to the site's primary admin email address. The notification includes critical forensic data to help you assess the legitimacy of the change, such as:

- The identity of the user who performed the action (or an indication of a system/CLI process).
- The IP address of the actor.
- The exact date and time of the event (based on your WordPress timezone).
- The previous role(s) of the promoted user.
- Full details of the new administrator account (Username, Email, Display Name, User ID).

This plugin is built with security best practices in mind, featuring strict data sanitization, validation, and dedicated action hooks for seamless integration with other monitoring systems.

## Features

- **Dual-event detection:** Monitors both new user registrations (`user_register`) and user role promotions (`set_user_role`).
- **Smart filtering:** Automatically ignores actions where the user was already an Administrator, preventing redundant checks.
- **Detailed forensic logging:** Every notification contains the actor, IP, timestamp, and previous roles for complete auditability.
- **Secure default recipient:** Uses the native WordPress admin email (`admin_email`) as the default recipient, ensuring reliability.
- **Configurable delivery:** Change the recipient easily using the standard WordPress filter `nna_recipient_email`.
- **Extensible architecture:** Provides `nna_email_sent` and `nna_email_failed` action hooks for third-party plugins to monitor email delivery status.
- **Automated process detection:** Correctly identifies actions performed by cron jobs, REST API, or system processes, attributing them to "System / Automated Process".
- **Localized timestamps:** Respects your WordPress timezone settings for accurate event logging.
- **Security hardened:** All inputs and outputs are properly sanitized and escaped (`sanitize_text_field`, `sanitize_email`, `absint`).

## Installation

1. Upload the `notify-new-administrator` folder to the `/wp-content/plugins/` directory, or install the plugin directly through the WordPress Plugins screen.
2. Activate the plugin through the **Plugins** screen in WordPress.
3. The plugin works immediately upon activation. No configuration page is required.
4. (Optional) Use the `nna_recipient_email` filter in your theme's `functions.php` or a custom plugin to change the notification recipient.

## Configuration (Filters & Actions)

The plugin is designed to be highly flexible for developers without needing a settings page.

### Change the Recipient Email

By default, notifications are sent to the email address defined in **Settings > General > Administration Email Address**. To override this, use the following filter:

```php
/**
 * Change the recipient of the new administrator notification.
 *
 * @param string $email      The default recipient email (admin_email).
 * @param int    $user_id    The ID of the new administrator.
 * @param WP_User $new_admin The WP_User object of the new administrator.
 */
add_filter('nna_recipient_email', function ($email, $user_id, $new_admin) {
    // Send notifications to a dedicated security email address.
    return 'security@example.com';
}, 10, 3);


---

```markdown
### Monitor Email Delivery Status

Developers can hook into the following actions to log or react to the success or failure of the notification email:

```php
// Triggered when the email is sent successfully.
add_action('nna_email_sent', function ($to, $subject, $user_id, $event_type) {
    // Log successful delivery or trigger additional workflows.
    error_log("Admin alert sent to {$to} for user ID {$user_id}.");
}, 10, 4);

// Triggered when the email fails to send.
add_action('nna_email_failed', function ($to, $subject, $user_id, $event_type) {
    // Log failure or escalate to a backup notification system.
    error_log("Security alert failed to send to {$to}.");
}, 10, 4);
```

---

## FAQ

**Does this plugin have a settings page?**  
No. This plugin is intentionally kept lean. It works out-of-the-box with the WordPress core admin email. However, we provide powerful filters (see above) for developers who need custom configurations.

**How do I prevent the original admin from being notified when they change their own role?**  
The plugin automatically handles this. If the current user already possesses the "Administrator" role and the action is merely a role re-assignment, the notification is skipped to prevent self-notification clutter.

**Where does the IP address come from?**  
The plugin uses the server's `REMOTE_ADDR` variable. This is the most reliable and secure way to identify the client IP, as it cannot be easily spoofed by the end-user (unrestricted use of forwarded headers is avoided for security reasons).

**What happens if wp_mail() fails?**  
If the email fails to send, the plugin does not attempt to resend it (to prevent infinite loops). However, it triggers the `nna_email_failed` action hook, allowing external security plugins or logging systems to capture and handle the failure.

---

## Changelog

### 2.0.0
* **Major overhaul:** Completely refactored the detection logic.
* **Added:** Detection for new users created with Administrator privileges (`user_register`).
* **Added:** Detection for existing users promoted to Administrator (`set_user_role`).
* **Added:** Forensic data to the email body, including actor details, IP address, timestamp, and previous roles.
* **Improved:** Security hardening with strict sanitization and validation of all data.
* **Added:** Filter `nna_recipient_email` to customize the recipient.
* **Added:** Actions `nna_email_sent` and `nna_email_failed` for third-party integrations.
* **Changed:** Default recipient is now the native WordPress `admin_email` for increased reliability.
* **Added:** Fallback identification for automated processes (CLI, Cron, REST API).

### 1.0.0
* Initial release. Basic notification on role change.

---

## Upgrade Notice

### 2.0.0
This version is a complete rewrite. It provides significantly more detailed security alerts, better detection accuracy, and developer hooks. You can safely replace the old version with this one.
```

---


