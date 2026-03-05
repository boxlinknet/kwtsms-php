# kwtsms/kwtsms

[![Latest Version](https://img.shields.io/packagist/v/kwtsms/kwtsms.svg?style=flat-square)](https://packagist.org/packages/kwtsms/kwtsms)
[![Total Downloads](https://img.shields.io/packagist/dt/kwtsms/kwtsms.svg?style=flat-square)](https://packagist.org/packages/kwtsms/kwtsms)
[![PHP Version](https://img.shields.io/packagist/php-v/kwtsms/kwtsms.svg?style=flat-square)](https://packagist.org/packages/kwtsms/kwtsms)
[![License](https://img.shields.io/packagist/l/kwtsms/kwtsms.svg?style=flat-square)](LICENSE)
[![CI](https://img.shields.io/github/actions/workflow/status/boxlinknet/kwtsms-php/ci.yml?branch=main&style=flat-square&label=CI)](https://github.com/boxlinknet/kwtsms-php/actions/workflows/ci.yml)

kwtSMS API Client for PHP. Official library to interface with the Kuwait SMS gateway (kwtsms.com).

---

## About kwtSMS

kwtSMS is a Kuwait-based SMS gateway trusted by businesses to deliver messages worldwide, with private Sender IDs, free API testing, non-expiring credits, and competitive flat-rate pricing. Open a free account in under a minute, no paperwork required. [Get started](https://www.kwtsms.com/signup/)

---

## Requirements

- PHP 7.4+
- `ext-curl`
- `ext-json`

---

## Installation

```bash
composer require kwtsms/kwtsms
```

If you don't have a project directory yet:

```bash
mkdir my-project && cd my-project
composer require kwtsms/kwtsms
```

---

## Quick Start

```php
require 'vendor/autoload.php';

use KwtSMS\KwtSMS;

$sms = KwtSMS::from_env();

$result = $sms->send('96598765432', 'Your OTP for MYAPP is: 123456');

if ($result['result'] === 'OK') {
    echo "Sent! Cost: {$result['points-charged']} credits\n";
} else {
    echo "Error [{$result['code']}]: {$result['description']}. {$result['action']}\n";
}
```

---

## Configuration

### Environment variables / `.env` file (recommended)

Create a `.env` file in your project root (add it to `.gitignore`):

```ini
KWTSMS_USERNAME=php_username        # API user, NOT your phone number or website login
KWTSMS_PASSWORD=php_password
KWTSMS_SENDER_ID=KWT-SMS            # Replace with a private Sender ID before going live
KWTSMS_TEST_MODE=1                  # Set to 0 when ready to deliver real messages
KWTSMS_LOG_FILE=kwtsms.log          # Path for NDJSON request log. Leave empty to disable.
```

Credentials are at: kwtsms.com → Account → API.

Load with:

```php
$sms = KwtSMS::from_env();
```

`from_env()` reads credentials in this order:
1. System environment variables (Docker, CI, server config)
2. `.env` file in the current working directory

`.env` parsing rules:
- Lines starting with `#` are skipped
- Quoted values (`"value"` or `'value'`) have quotes stripped
- Unquoted values: trailing inline comments (space + `#`) are stripped
- Embedded newlines in values are stripped (prevents env-injection)

### Constructor injection

```php
$sms = new KwtSMS(
    'php_username',
    'php_password',
    'MY-BRAND',           // sender ID
    false,                // test mode
    'storage/kwtsms.log'  // log file; empty string disables logging
);
```

Credentials have embedded newlines stripped automatically. The `log_file` path is
rejected if it contains `..` (path traversal guard).

---

## Methods

### `verify()`

Test credentials and fetch current balance.

```php
[$ok, $balance, $error] = $sms->verify();

if ($ok) {
    echo "Connected! Balance: {$balance} credits\n";
} else {
    echo "Failed: {$error}\n";
}
```

**Returns:** `[bool $ok, float $balance, string $error]`

Always call `verify()` at startup to detect wrong credentials (ERR003),
blocked account (ERR005), or IP not whitelisted (ERR024).

---

### `balance()`

Fetch current credit balance.

```php
$balance = $sms->balance(); // float|null
```

**Returns:** `float` on success, `null` on failure. Always makes an API call.

---

### `purchased()`

Return the total credits purchased on the account, cached from the last `verify()` or `balance()` call.

```php
$purchased = $sms->purchased(); // float|null. Null until verify() or balance() has been called.
```

**Returns:** `float|null`

---

### `send()`

Send SMS to one or more recipients.

```php
// Single number
$result = $sms->send('96598765432', 'Hello from kwtSMS!');

// Per-message sender ID override
$result = $sms->send('96598765432', 'Hello!', 'MY-BRAND');

// Multiple numbers: array or comma-separated string
$result = $sms->send(['96598765432', '96512345678'], 'Bulk announcement');
$result = $sms->send('96598765432,96512345678', 'Bulk announcement');
```

**What `send()` does automatically:**

- Normalizes all phone numbers (strips `+`, `00`, spaces, dashes, converts Arabic/Hindi digits)
- Deduplicates: the same normalized number is only charged and dispatched once
- Cleans the message via `MessageUtils::clean_message()` (see below)
- Returns ERR009 locally (no API call, no credits consumed) if the message is empty or becomes empty after cleaning
- Splits >200 numbers into 200-number batches with 0.5s inter-batch delay
- Retries ERR013 (queue full) automatically: 30s, 60s, 120s backoff, up to 4 attempts

**Success response (single / ≤200 numbers):**

```json
{
  "result": "OK",
  "msg-id": "f4c841adee210f31307633ceaebff2ec",
  "numbers": 1,
  "points-charged": 1,
  "balance-after": 180
}
```

**Success response (bulk / >200 numbers):**

```json
{
  "result": "OK",
  "batches": 3,
  "msg-ids": ["abc...", "def...", "ghi..."],
  "numbers": 550,
  "points-charged": 550,
  "balance-after": 450,
  "errors": []
}
```

**Error response:**

```json
{
  "result": "ERROR",
  "code": "ERR006",
  "description": "No valid phone numbers.",
  "action": "Make sure each number includes the country code (e.g. 96598765432)."
}
```

Always save `msg-id` (needed for status/DLR lookups) and `balance-after`
(avoids an extra `/balance/` call).

---

### `validate()`

Validate and normalize a list of phone numbers locally. No API call is made.

```php
$report = $sms->validate(['abcd', '+965 9876 5432', '96522334455']);

echo $report['nr'];  // total submitted: 3
echo $report['ok'];  // locally valid:   2
echo $report['er'];  // invalid:         1

foreach ($report['rejected'] as $r) {
    echo "{$r['number']}: {$r['error']}\n";
}

// Full per-number detail:
foreach ($report['raw'] as $entry) {
    // $entry['phone']      original input
    // $entry['valid']      bool
    // $entry['normalized'] normalized number (if valid)
    // $entry['error']      error message (if invalid)
}
```

For pre-campaign routing validation (checks if numbers are routable on your account),
use the kwtSMS web dashboard or call the API directly via `/API/validate/`.

---

### `senderids()`

List Sender IDs registered on the account.

```php
$result = $sms->senderids();
print_r($result['senderids']); // ['MY-APP', 'KWT-SMS']
```

---

### `coverage()`

List active country prefixes on the account.

```php
$result = $sms->coverage();
print_r($result['prefixes']);
```

---

### `status(string $msgId)`

Check the queue/dispatch status of a sent message. Use the `msg-id` returned by `send()`.

```php
$result = $sms->status($msgId);

if ($result['result'] === 'OK') {
    echo $result['status'];      // e.g. "sent"
    echo $result['description']; // e.g. "Message successfully sent to gateway"
} else {
    // ERR029: msg-id not found
    // ERR030: stuck in queue — delete at kwtsms.com → Queue to recover credits
    echo $result['action'];
}
```

---

### `dlr(string $msgId)`

Retrieve delivery reports for a sent message. Only available for international (non-Kuwait) numbers. Wait at least 5 minutes after sending before calling.

```php
$result = $sms->dlr($msgId);

if ($result['result'] === 'OK') {
    foreach ($result['report'] as $entry) {
        echo $entry['Number'] . ': ' . $entry['Status'] . "\n";
        // e.g. "96550123456: Received by recipient"
    }
}
```

> Kuwait numbers do not support DLR. For Kuwait delivery confirmation, use `status()` instead.

---

## Utility Classes

### `PhoneUtils::validate_phone_input()`

Validate and normalize a single phone number string.

```php
use KwtSMS\PhoneUtils;

[$valid, $error, $normalized] = PhoneUtils::validate_phone_input('+٩٦٥ ٩٨٧٦ ٥٤٣٢');
// $valid      true
// $error      null
// $normalized "96598765432"
```

Validation steps (in order):

1. Empty check
2. Email detection (`@` present)
3. Arabic/Hindi digit conversion
4. Non-digit stripping (`+`, spaces, dashes, parentheses, etc.)
5. Leading zero stripping
6. Length check: 7–15 digits

### `PhoneUtils::normalize_phone()`

Normalize without validating. Useful for bulk pre-processing.

```php
$normalized = PhoneUtils::normalize_phone('+965 9876-5432'); // "96598765432"
```

### `MessageUtils::clean_message()`

Strip content that causes silent delivery failures. Called automatically by `send()`.

```php
use KwtSMS\MessageUtils;

$clean = MessageUtils::clean_message($rawTemplate);
```

What it strips and why:

| Step | What | Why |
|------|------|-----|
| 1 | HTML tags | Prevents ERR027 |
| 2 | Arabic/Hindi digits converted to Latin | OTP codes render consistently on all handsets |
| 3 | Hidden Unicode (U+200B zero-width space, U+FEFF BOM, U+00AD soft hyphen, etc.) | Common in copy-pasted text from Word/PDFs; causes spam filter rejection |
| 4 | Emojis: standard, country flags (U+1F1E0–U+1F1FF), mahjong tiles (U+1F000), keycap combiner (U+20E3), tags block (U+E0000–E007F) | Messages with emojis queue indefinitely with no error returned |
| 5 | Other control characters | Prevents encoding issues |

Newlines (`\n`, `\r`), tabs (`\t`), Arabic text, and Latin punctuation are preserved.

---

## Phone Number Formats

The library normalizes all of these automatically:

| Input | Normalized |
|-------|-----------|
| `+965 9876 5432` | `96598765432` |
| `0096598765432` | `96598765432` |
| `(965) 9876-5432` | `96598765432` |
| `٩٦٥٩٨٧٦٥٤٣٢` (Arabic-Indic) | `96598765432` |
| `۹۶۵۹۸۷۶۵۴۳۲` (Extended Arabic-Indic) | `96598765432` |

Rejected inputs: email addresses, empty strings, fewer than 7 digits, more than 15 digits.

---

## Error Handling

The library adds an `action` field to every error response:

```php
$result = $sms->send($phone, $message);

if ($result['result'] !== 'OK') {
    $code   = $result['code'];        // "ERR003"
    $desc   = $result['description']; // "Wrong API username or password."
    $action = $result['action'];      // "Check KWTSMS_USERNAME and KWTSMS_PASSWORD."
}
```

Never expose raw `ERR0XX` codes to end users.

| Situation | Error code | Recommended user message |
|-----------|-----------|--------------------------|
| Invalid phone number | ERR006, ERR025 | "Please enter a valid phone number." |
| Wrong credentials | ERR003 | "SMS service is temporarily unavailable." (log and alert admin) |
| No balance | ERR010, ERR011 | "SMS service is temporarily unavailable." (alert admin to top up) |
| Country not supported | ERR026 | "SMS delivery to this country is not available." |
| Rate limited | ERR028 | "Please wait before requesting another code." |
| Message rejected | ERR031, ERR032 | "Your message could not be sent. Please try again." |
| Queue full | ERR013 | Handled automatically by bulk retry; surface only if all retries fail |
| Network error | ERR999 | "Could not connect to SMS service. Please try again." |

---

## CLI

Send SMS and verify credentials from the terminal:

```bash
# Verify credentials and check balance
vendor/bin/kwtsms verify

# Send a message
vendor/bin/kwtsms send --phone 96598765432 --message "Hello from kwtSMS"

# Override sender ID for this message
vendor/bin/kwtsms send --phone 96598765432 --message "Hello" --sender MY-BRAND

# Help
vendor/bin/kwtsms --help
```

Reads `KWTSMS_USERNAME`, `KWTSMS_PASSWORD`, `KWTSMS_SENDER_ID`, and `KWTSMS_TEST_MODE`
from `.env` or shell environment.

---

## Logging

When `KWTSMS_LOG_FILE` is set, every API request is appended to the file as a
newline-delimited JSON (NDJSON) entry:

```json
{"ts":"2026-03-05T12:00:00Z","endpoint":"send","request":{...,"password":"***"},"response":{...},"ok":true,"error":null}
```

The password is always masked as `***`. Logging never throws; failures are silently
ignored so they cannot crash the main application flow.

---

## Sender ID

- `KWT-SMS` is a shared test sender. It causes delivery delays and is blocked on
  Virgin Kuwait. Never use it in production.
- **Transactional SenderID:** required for OTP. Bypasses DND (Do Not Disturb)
  filtering. Cost: 15 KD one-time. Processing: ~5 business days.
- **Promotional SenderID:** for bulk/marketing. Silently blocked on DND numbers
  (credits still charged). Cost: 10 KD one-time.
- SenderIDs are case-sensitive and cannot be transferred between providers.

---

## Security Checklist

```
BEFORE GOING LIVE:
[ ] Private Sender ID registered (not KWT-SMS)
[ ] Transactional Sender ID for OTP (not promotional)
[ ] Test mode OFF (KWTSMS_TEST_MODE=0)
[ ] CAPTCHA on all SMS-triggering forms
[ ] Rate limit per phone number (max 3–5 per hour)
[ ] Rate limit per IP address (max 10–20 per hour)
[ ] OTP codes stored as HMAC hash, not plaintext
[ ] Admin alert on low balance
[ ] .env in .gitignore; credentials never committed
```

---

## FAQ

**My message returned OK but the recipient didn't receive it. What happened?**

Check the Sending Queue at kwtsms.com. If it is stuck there, it was accepted but
not dispatched. Common causes: emoji in the message, hidden characters from
copy-pasting, spam filter trigger, or `KWTSMS_TEST_MODE=1` still set. Delete
stuck messages from the queue to recover credits.

**What is the difference between Test mode and Live mode?**

Test mode (`KWTSMS_TEST_MODE=1`) queues the message but never delivers it. No
SMS is sent, no credits consumed. Use this during development. Set to `0` before
going live.

**Why should I not use `KWT-SMS` as my Sender ID in production?**

`KWT-SMS` is a shared promotional sender. It causes delivery delays, is blocked
on Virgin Kuwait, and cannot bypass DND filtering. Register a private
Transactional Sender ID for OTP flows.

**I'm getting ERR003. What's wrong?**

You are using the wrong credentials. The API requires your API username and
password, not your account phone number or website login. Find them at
kwtsms.com → Account → API.

**Can I send to international numbers?**

International sending is disabled by default. Contact kwtSMS support to activate
specific country prefixes. Use `coverage()` to see which are currently active.
Note: enabling international coverage increases bot/abuse exposure. Implement
rate limiting and CAPTCHA before enabling.

---

## Examples

See `examples/` for runnable code covering every use case:

| # | File | What it covers |
|---|------|----------------|
| 01 | `01-quickstart.php` | Verify credentials, send first SMS |
| 02 | `02-otp.php` | Basic OTP flow |
| 03 | `03-bulk.php` | Bulk send, auto-batching |
| 04 | `04-validation.php` | Phone number validation |
| 05 | `05-error-handling.php` | Error categories and retry logic |
| 06 | `06-message-cleaning.php` | Message cleaning internals |
| 07 | `07-laravel.php` | Laravel Service Provider and Notification channel |
| 08 | `08-wordpress.php` | WordPress plugin, WooCommerce, 2FA login |
| 09 | `09-otp-production.php` | Production OTP: DB, CAPTCHA, rate limiting, brute-force protection |

Full documentation for each example is in `examples/docs/`.

---

## Help & Support

- **[kwtSMS FAQ](https://www.kwtsms.com/faq/)**: credits, sender IDs, OTP, delivery
- **[kwtSMS Support](https://www.kwtsms.com/support.html)**: tickets and help articles
- **[API Documentation (PDF)](https://www.kwtsms.com/doc/KwtSMS.com_API_Documentation_v41.pdf)**: REST API v4.1 reference
- **[kwtSMS Dashboard](https://www.kwtsms.com/login/)**: recharge, sender IDs, logs
- **[Other Integrations](https://www.kwtsms.com/integrations.html)**: plugins for other platforms

---

## License

MIT
