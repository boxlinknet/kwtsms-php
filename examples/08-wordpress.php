<?php

/**
 * Example 08: WordPress Integration
 * -----------------------------------
 * How to integrate kwtSMS into a WordPress plugin or theme using:
 *   - Admin settings page to store credentials in wp_options
 *   - A "Test Connection" button using AJAX
 *   - Sending SMS from WooCommerce order events
 *   - Sending OTP during login
 *
 * Installation (in your plugin):
 *   composer require kwtsms/kwtsms
 *   In your plugin file: require_once __DIR__ . '/vendor/autoload.php';
 *
 * NOTE: This file is a reference guide with code snippets.
 *       Do not run it directly.
 */

// ════════════════════════════════════════════════════════════════════════════
// 1. Helper: Get configured client from WordPress settings
// ════════════════════════════════════════════════════════════════════════════

/*
use KwtSMS\KwtSMS;

function kwtsms_get_client(): ?KwtSMS
{
    $username  = get_option('kwtsms_username', '');
    $password  = get_option('kwtsms_password', '');
    $sender_id = get_option('kwtsms_sender_id', 'KWT-SMS');
    $test_mode = (bool) get_option('kwtsms_test_mode', true);
    $log_file  = WP_CONTENT_DIR . '/uploads/kwtsms.log';

    if (empty($username) || empty($password)) {
        return null;
    }

    return new KwtSMS($username, $password, $sender_id, $test_mode, $log_file);
}
*/


// ════════════════════════════════════════════════════════════════════════════
// 2. Admin Settings Page
// ════════════════════════════════════════════════════════════════════════════

/*
add_action('admin_menu', function () {
    add_options_page('kwtSMS Settings', 'kwtSMS', 'manage_options', 'kwtsms', 'kwtsms_settings_page');
});

function kwtsms_settings_page(): void
{
    if (!current_user_can('manage_options')) return;

    if (isset($_POST['kwtsms_save'])) {
        check_admin_referer('kwtsms_settings');
        update_option('kwtsms_username',  sanitize_text_field($_POST['kwtsms_username']));
        update_option('kwtsms_password',  sanitize_text_field($_POST['kwtsms_password']));
        update_option('kwtsms_sender_id', sanitize_text_field($_POST['kwtsms_sender_id']));
        update_option('kwtsms_test_mode', isset($_POST['kwtsms_test_mode']) ? 1 : 0);
        echo '<div class="notice notice-success"><p>Settings saved.</p></div>';
    }

    $username  = get_option('kwtsms_username', '');
    $sender_id = get_option('kwtsms_sender_id', 'KWT-SMS');
    $test_mode = get_option('kwtsms_test_mode', 1);
    ?>
    <div class="wrap">
        <h1>kwtSMS Settings</h1>
        <form method="post">
            <?php wp_nonce_field('kwtsms_settings'); ?>
            <table class="form-table">
                <tr>
                    <th>API Username</th>
                    <td><input type="text" name="kwtsms_username" value="<?= esc_attr($username) ?>" class="regular-text" /></td>
                </tr>
                <tr>
                    <th>API Password</th>
                    <td><input type="password" name="kwtsms_password" value="" class="regular-text" placeholder="(unchanged)" /></td>
                </tr>
                <tr>
                    <th>Sender ID</th>
                    <td><input type="text" name="kwtsms_sender_id" value="<?= esc_attr($sender_id) ?>" class="regular-text" /></td>
                </tr>
                <tr>
                    <th>Test Mode</th>
                    <td>
                        <input type="checkbox" name="kwtsms_test_mode" <?= $test_mode ? 'checked' : '' ?> />
                        <span>Messages queued but NOT delivered. Disable before going live.</span>
                    </td>
                </tr>
            </table>
            <?php submit_button('Save Settings', 'primary', 'kwtsms_save'); ?>
        </form>

        <h2>Test Connection</h2>
        <?php
        $client = kwtsms_get_client();
        if ($client) {
            [$ok, $balance, $error] = $client->verify();
            if ($ok) {
                echo '<div class="notice notice-success"><p>✅ Connected! Balance: ' . esc_html($balance) . ' credits</p></div>';
            } else {
                echo '<div class="notice notice-error"><p>❌ ' . esc_html($error) . '</p></div>';
            }
        } else {
            echo '<div class="notice notice-warning"><p>Enter credentials above and save to test connection.</p></div>';
        }
        ?>
    </div>
    <?php
}
*/


// ════════════════════════════════════════════════════════════════════════════
// 3. WooCommerce: SMS on order status change
// ════════════════════════════════════════════════════════════════════════════

/*
add_action('woocommerce_order_status_changed', function (int $order_id, string $old_status, string $new_status) {
    $sms = kwtsms_get_client();
    if (!$sms) return;

    $order = wc_get_order($order_id);
    if (!$order) return;

    $phone = $order->get_billing_phone();
    $name  = $order->get_billing_first_name();
    $total = $order->get_total();

    $templates = [
        'processing' => "Dear {$name}, your order #{$order_id} has been confirmed. Total: {$total} KWD.",
        'shipped'    => "Dear {$name}, your order #{$order_id} is on its way! Track it at: " . get_site_url(),
        'completed'  => "Dear {$name}, your order #{$order_id} has been delivered. Thank you for shopping with us!",
        'cancelled'  => "Dear {$name}, your order #{$order_id} has been cancelled. Contact us for help.",
    ];

    $message = $templates[$new_status] ?? null;

    if ($message && $phone) {
        $result = $sms->send($phone, $message);
        if ($result['result'] !== 'OK') {
            error_log("[kwtSMS] WooCommerce SMS failed for order {$order_id}: " . json_encode($result));
        }
    }
}, 10, 3);
*/


// ════════════════════════════════════════════════════════════════════════════
// 4. Two-Factor Authentication on login
// ════════════════════════════════════════════════════════════════════════════

/*
// Step 1: After successful password login, send OTP
add_action('wp_login', function (string $user_login, WP_User $user) {
    $sms = kwtsms_get_client();
    if (!$sms) return;

    $phone = get_user_meta($user->ID, 'billing_phone', true);
    if (!$phone) return;

    $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

    // Store OTP as transient for 10 minutes
    set_transient('kwtsms_otp_' . $user->ID, $otp, 600);

    $sms->send($phone, "Your WordPress login code is: {$otp}\nExpires in 10 minutes.");

    // Redirect to OTP verification page
    wp_redirect(site_url('/verify-otp?user_id=' . $user->ID));
    exit;
}, 10, 2);
*/

echo "This file is a WordPress integration reference guide.\n";
echo "Copy the relevant snippets into your plugin file.\n";
