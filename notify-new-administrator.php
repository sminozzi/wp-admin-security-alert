```php
<?php
/**
 * Plugin Name: Notify New Administrator
 * Description: Sends a security notification when a new Administrator is created or an existing user is promoted to Administrator.
 * Version: 2.0.0
 * Author:
 * License: GPL-2.0-or-later
 */

if (!defined('ABSPATH')) {
    exit;
}

/*
 * ============================================================
 * CONFIGURATION
 * ============================================================
 *
 * By default, notifications are sent to the WordPress
 * administration email address.
 *
 * You can override the recipient using the filter:
 *
 * add_filter('nna_recipient_email', function ($email) {
 *     return 'security@example.com';
 * });
 *
 * The filter is intentionally kept for compatibility and
 * flexibility without requiring a settings page.
 */


/*
 * ============================================================
 * 1. DETECT NEW USERS
 * ============================================================
 *
 * Detects users created with Administrator privileges.
 */
add_action('user_register', 'nna_check_new_user', 10, 1);

function nna_check_new_user($user_id) {

    $user = get_userdata($user_id);

    if (!$user) {
        return;
    }

    // Only administrators are relevant.
    if (!in_array('administrator', (array) $user->roles, true)) {
        return;
    }

    nna_send_security_notification(
        $user_id,
        'created'
    );
}


/*
 * ============================================================
 * 2. DETECT ROLE CHANGES
 * ============================================================
 *
 * Detects an existing user being promoted to Administrator.
 */
add_action('set_user_role', 'nna_check_role_change', 10, 3);

function nna_check_role_change($user_id, $new_role, $old_roles) {

    // We only care about promotion to Administrator.
    if ($new_role !== 'administrator') {
        return;
    }

    // Do not notify if the user was already an Administrator.
    if (in_array('administrator', (array) $old_roles, true)) {
        return;
    }

    nna_send_security_notification(
        $user_id,
        'promoted',
        $old_roles
    );
}


/*
 * ============================================================
 * 3. SEND SECURITY NOTIFICATION
 * ============================================================
 *
 * Creates and sends the security notification.
 *
 * $event_type:
 *
 * - created  = new user created as Administrator
 * - promoted = existing user promoted to Administrator
 */
function nna_send_security_notification($user_id, $event_type, $old_roles = array()) {

    $new_admin = get_userdata($user_id);

    if (!$new_admin) {
        return;
    }

    /*
     * --------------------------------------------------------
     * Determine who performed the action.
     * --------------------------------------------------------
     *
     * wp_get_current_user() may not identify a user when the
     * change was performed by an automated process, CLI,
     * cron job, REST request, or another plugin.
     */
    $actor = wp_get_current_user();

    if ($actor && !empty($actor->ID)) {

        $actor_login = $actor->user_login;
        $actor_email = $actor->user_email;
        $actor_name  = $actor->display_name;

    } else {

        $actor_login = 'System / Automated Process';
        $actor_email = 'N/A';
        $actor_name  = 'System / Automated Process';
    }


    /*
     * --------------------------------------------------------
     * Determine the source IP address.
     * --------------------------------------------------------
     *
     * REMOTE_ADDR is intentionally used directly.
     *
     * We do not blindly trust HTTP_X_FORWARDED_FOR because
     * client-controlled headers can be spoofed unless a
     * trusted proxy configuration is known.
     */
    $ip_address = isset($_SERVER['REMOTE_ADDR'])
        ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR']))
        : 'Unknown';


    /*
     * --------------------------------------------------------
     * Date and time
     * --------------------------------------------------------
     *
     * WordPress local time is used instead of PHP server time.
     */
    $event_time = current_time('mysql');


    /*
     * --------------------------------------------------------
     * Determine the old role
     * --------------------------------------------------------
     */
    $previous_roles = !empty($old_roles)
        ? implode(', ', array_map('sanitize_text_field', (array) $old_roles))
        : 'None';


    /*
     * --------------------------------------------------------
     * Determine event description
     * --------------------------------------------------------
     */
    if ($event_type === 'created') {

        $event_title = 'A new Administrator account was created.';
        $event_description = 'New user created with Administrator privileges.';

    } else {

        $event_title = 'An existing user was promoted to Administrator.';
        $event_description = 'Existing user received Administrator privileges.';
    }


    /*
     * ========================================================
     * RECIPIENT
     * ========================================================
     *
     * Default recipient is the WordPress administration email.
     *
     * This can be overridden with:
     *
     * add_filter('nna_recipient_email', function ($email) {
     *     return 'security@example.com';
     * });
     */
    $to = get_option('admin_email');

    $to = apply_filters(
        'nna_recipient_email',
        $to,
        $user_id,
        $new_admin
    );

    $to = sanitize_email($to);

    if (!is_email($to)) {
        return;
    }


    /*
     * ========================================================
     * SUBJECT
     * ========================================================
     */
    $site_name = wp_specialchars_decode(
        get_bloginfo('name'),
        ENT_QUOTES
    );

    $subject = sprintf(
        '[%s] SECURITY ALERT: New Administrator',
        $site_name
    );


    /*
     * ========================================================
     * MESSAGE
     * ========================================================
     */
    $message  = "SECURITY ALERT\n";
    $message .= "==============================\n\n";

    $message .= $event_title . "\n\n";

    $message .= "SITE\n";
    $message .= "------------------------------\n";
    $message .= "Site: " . $site_name . "\n";
    $message .= "URL: " . home_url('/') . "\n\n";

    $message .= "ADMINISTRATOR ACCOUNT\n";
    $message .= "------------------------------\n";
    $message .= "Username: " . sanitize_text_field($new_admin->user_login) . "\n";
    $message .= "Email: " . sanitize_email($new_admin->user_email) . "\n";
    $message .= "Name: " . sanitize_text_field($new_admin->display_name) . "\n";
    $message .= "User ID: " . absint($new_admin->ID) . "\n\n";

    $message .= "EVENT\n";
    $message .= "------------------------------\n";
    $message .= "Type: " . $event_description . "\n";
    $message .= "Previous role(s): " . $previous_roles . "\n";
    $message .= "Date/Time: " . $event_time . "\n\n";

    $message .= "ACTION PERFORMED BY\n";
    $message .= "------------------------------\n";
    $message .= "Username: " . sanitize_text_field($actor_login) . "\n";
    $message .= "Email: " . sanitize_email($actor_email) . "\n";
    $message .= "Name: " . sanitize_text_field($actor_name) . "\n";
    $message .= "IP Address: " . $ip_address . "\n\n";

    $message .= "SECURITY RECOMMENDATION\n";
    $message .= "------------------------------\n";
    $message .= "If this change was not authorized, immediately review the administrator accounts, active sessions, plugins, themes, scheduled tasks, and security logs on this site.\n\n";

    $message .= "This notification was generated automatically by Notify New Administrator.";


    /*
     * ========================================================
     * SEND EMAIL
     * ========================================================
     */
    $sent = wp_mail(
        $to,
        $subject,
        $message
    );


    /*
     * ========================================================
     * HANDLE MAIL FAILURE
     * ========================================================
     *
     * wp_mail() returning false means WordPress failed to
     * hand the message to the configured mail system.
     *
     * We intentionally do not generate another notification
     * here, which could create a notification loop.
     */
    if (!$sent) {

        /*
         * Optional integration point for security plugins.
         *
         * Other plugins can monitor this event with:
         *
         * add_action('nna_email_failed', function (...) {});
         */
        do_action(
            'nna_email_failed',
            $to,
            $subject,
            $user_id,
            $event_type
        );
    }

    /*
     * Allow other security plugins to monitor successful
     * notifications as well.
     */
    if ($sent) {

        do_action(
            'nna_email_sent',
            $to,
            $subject,
            $user_id,
            $event_type
        );
    }
}
```
