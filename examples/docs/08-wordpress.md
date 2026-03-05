# Example 08: WordPress Integration

**File:** `examples/08-wordpress.php`
**Run:** Reference only. Copy snippets into your plugin file.

Integrate kwtSMS into a WordPress plugin with an admin settings page,
WooCommerce order SMS notifications, and two-factor authentication on login.

---

## Architecture

```
WordPress Plugin
  │
  ├─ get_option('kwtsms_username')   ← Credentials stored in wp_options
  │
  ├─ kwtsms_get_client()             ← Factory: reads options, builds KwtSMS instance
  │
  ├─ Admin Settings Page             ← wp-admin → Settings → kwtSMS
  │       ├─ Save credentials (sanitize_text_field)
  │       ├─ Test Connection button
  │       └─ Test Mode toggle
  │
  ├─ WooCommerce SMS Hook            ← woocommerce_order_status_changed
  │       └─ Send SMS on: processing / shipped / completed / cancelled
  │
  └─ 2FA Login Hook                  ← wp_login
          ├─ Generate OTP
          ├─ Store as transient (10 min TTL)
          └─ Redirect to OTP verification page
```

---

## Setup

### Step 1: Install via Composer inside your plugin

```bash
cd wp-content/plugins/my-plugin/
composer require kwtsms/kwtsms
```

In your main plugin file:

```php
require_once __DIR__ . '/vendor/autoload.php';
```

### Step 2: Register activation hook

```php
register_activation_hook(__FILE__, 'my_plugin_activate');

function my_plugin_activate(): void
{
    add_option('kwtsms_test_mode', 1);
    add_option('kwtsms_sender_id', 'KWT-SMS');
}
```

### Step 3: Add settings page

Copy `kwtsms_get_client()` and `kwtsms_settings_page()` from `08-wordpress.php`.
Register the menu item:

```php
add_action('admin_menu', function () {
    add_options_page('kwtSMS Settings', 'kwtSMS', 'manage_options', 'kwtsms', 'kwtsms_settings_page');
});
```

### Step 4: Security in the settings form

The settings form follows WordPress security best practices:

- `check_admin_referer('kwtsms_settings')`: CSRF protection via nonce
- `sanitize_text_field()` on all inputs: prevents XSS in saved values
- `esc_attr()` on output: prevents XSS in HTML attributes
- Password field is intentionally blank (`value=""`): never render stored passwords

### Step 5: WooCommerce hook

```php
add_action('woocommerce_order_status_changed',
    function (int $order_id, string $old_status, string $new_status) {
        $sms = kwtsms_get_client();
        if (!$sms) return; // Not configured — skip silently

        $order   = wc_get_order($order_id);
        $phone   = $order->get_billing_phone();
        $message = $templates[$new_status] ?? null;

        if ($message && $phone) {
            $result = $sms->send($phone, $message);
            if ($result['result'] !== 'OK') {
                error_log('[kwtSMS] WC order ' . $order_id . ': ' . json_encode($result));
            }
        }
    }, 10, 3
);
```

### Step 6: 2FA login flow

```
User submits login form
  │
  ├─ WordPress authenticates password (wp_login hook fires)
  │
  ├─ Plugin intercepts: generate OTP, store as transient
  │
  ├─ Send SMS to user's phone (from user_meta)
  │
  ├─ Redirect to /verify-otp page
  │
  └─ On OTP submission:
         ├─ get_transient('kwtsms_otp_' . $user_id)
         ├─ Compare submitted code
         ├─ delete_transient on success
         └─ Complete login or reject
```

---

## Storage Reference

| Data | Storage | TTL |
|------|---------|-----|
| Credentials | `wp_options` | Permanent |
| OTP code | `set_transient()` | 10 minutes |
| Rate limit counters | `set_transient()` | Per window |
| Sent message IDs | Custom table or post meta | As needed |
