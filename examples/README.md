# kwtSMS PHP — Examples

Step-by-step examples covering everything from a first send to a production OTP
system with CAPTCHA, rate limiting, and brute-force protection.

---

## Setup

### 1. Install

```bash
composer require kwtsms/kwtsms
```

Requires PHP 7.4+, `ext-curl`, `ext-json`.

### 2. Configure `.env`

```bash
cp .env.example .env
```

```ini
KWTSMS_USERNAME=php_username   # API user — NOT your phone number or website login
KWTSMS_PASSWORD=php_password
KWTSMS_SENDER_ID=KWT-SMS            # Replace with a private Sender ID before production
KWTSMS_TEST_MODE=1                  # Set to 0 when ready to deliver real messages
KWTSMS_LOG_FILE=kwtsms.log          # Leave empty to disable request logging
```

Credentials: kwtsms.com → Account → API.

### 3. Verify

```bash
php examples/01-quickstart.php
```

Expected output: `Connected! Balance: X credits`

---

## Examples

| # | File | What it covers | Docs |
|---|------|---------------|------|
| 01 | `01-quickstart.php` | Verify credentials, send your first SMS | [docs](docs/01-quickstart.md) |
| 02 | `02-otp.php` | Generate and send a one-time code (basic) | [docs](docs/02-otp.md) |
| 03 | `03-bulk.php` | Send to many numbers — auto-batching for >200 | [docs](docs/03-bulk.md) |
| 04 | `04-validation.php` | Local phone validation and API routing check | [docs](docs/04-validation.md) |
| 05 | `05-error-handling.php` | Handle every error category with the right action | [docs](docs/05-error-handling.md) |
| 06 | `06-message-cleaning.php` | What `clean_message()` strips and why | [docs](docs/06-message-cleaning.md) |
| 07 | `07-laravel.php` | Service Provider, controller injection, Notification channel | [docs](docs/07-laravel.md) |
| 08 | `08-wordpress.php` | Admin settings, WooCommerce hooks, 2FA login | [docs](docs/08-wordpress.md) |
| 09 | `09-otp-production.php` | Full production OTP: DB, CAPTCHA, rate limiting, brute-force protection | [docs](docs/09-otp-production.md) |

---

## Reference

- [Phone number formats](docs/reference.md#phone-number-format-reference) — accepted, normalized, and rejected inputs
- [Sender ID guide](docs/reference.md#sender-id) — Promotional vs Transactional, DND, registration
- [Error codes ERR001–ERR033](docs/reference.md#error-code-reference) — descriptions and recommended actions
- [Pre-launch checklist](docs/reference.md#pre-launch-checklist) — credentials, security, content, anti-abuse

---

## CLI

Send SMS and verify credentials from the terminal:

```bash
# Verify credentials and check balance
vendor/bin/kwtsms verify

# Send a message
vendor/bin/kwtsms send --phone 96598765432 --message "Hello from kwtSMS"

# Override sender ID for one message
vendor/bin/kwtsms send --phone 96598765432 --message "Hello" --sender MY-BRAND
```

Reads credentials from `.env` or environment variables.
