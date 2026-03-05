<!-- examples/README.md -->
# Examples

Runnable examples showing how to use the `kwtsms/kwtsms` PHP client library.

## Prerequisites

```bash
# Install the library
composer require kwtsms/kwtsms

# Create your .env file
cp .env.example .env    # then fill in your credentials
```

Your `.env` file should contain:
```ini
KWTSMS_USERNAME=your_api_username
KWTSMS_PASSWORD=your_api_password
KWTSMS_SENDER_ID=MY-BRAND
KWTSMS_TEST_MODE=1          # Set to 0 when going live
KWTSMS_LOG_FILE=kwtsms.log
```

---

## Examples

| File | Description |
|------|-------------|
| [`01-quickstart.php`](01-quickstart.php) | Verify credentials and send your first SMS |
| [`02-otp.php`](02-otp.php) | Send OTP verification codes with safe user-facing error handling |
| [`03-bulk.php`](03-bulk.php) | Send to multiple recipients — small lists and auto-batched large lists |
| [`04-validation.php`](04-validation.php) | Validate and normalize phone numbers locally and via API |
| [`05-error-handling.php`](05-error-handling.php) | Handle every error category correctly in production |
| [`06-message-cleaning.php`](06-message-cleaning.php) | See what gets stripped from messages and why |
| [`07-laravel.php`](07-laravel.php) | Laravel Service Provider, Controller, and Notification integration |
| [`08-wordpress.php`](08-wordpress.php) | WordPress plugin settings page, WooCommerce hooks, and 2FA login |

---

## Running the Examples

```bash
# Run any example (from the repo root)
php examples/01-quickstart.php
php examples/02-otp.php
php examples/03-bulk.php
php examples/04-validation.php
php examples/05-error-handling.php
php examples/06-message-cleaning.php
```

> **Note:** Examples `07-laravel.php` and `08-wordpress.php` are reference guides with code snippets — they cannot be run directly.

---

## Test Mode

All examples respect `KWTSMS_TEST_MODE=1` in your `.env`. In test mode:
- Messages are queued by kwtSMS but **never delivered** to the handset
- **No credits are consumed**
- API responses are identical to live mode

Set `KWTSMS_TEST_MODE=0` only when you are ready to send real messages.
