# kwtsms/kwtsms

kwtSMS API Client for PHP. Official library to interface with the Kuwait SMS gateway (kwtsms.com)

## About kwtSMS

kwtSMS is a Kuwaiti SMS gateway trusted by top businesses to deliver messages anywhere in the world, with private Sender ID, free API testing, non-expiring credits, and competitive flat-rate pricing. Secure, simple to integrate, built to last. Open a free account in under 1 minute, no paperwork or payment required. [Click here to get started](https://www.kwtsms.com/signup/) 👍

---

## Prerequisites

You need **PHP** (7.4 or newer) and **Composer** (PHP's package manager) installed.

### Step 1: Check if PHP is installed

```bash
php -v
```

If you see a version number (e.g., `PHP 8.2.x`), PHP is installed. If not, install it:

- **macOS:** `brew install php`
- **Ubuntu/Debian:** `sudo apt update && sudo apt install php php-curl`
- **Windows:** Download from https://windows.php.net/download/ or install via https://laragon.org/

### Step 2: Check if Composer is installed

```bash
composer --version
```

If you see a version number, Composer is installed. If not, install it:

- **macOS / Linux:**
  ```bash
  curl -sS https://getcomposer.org/installer | php
  sudo mv composer.phar /usr/local/bin/composer
  ```
- **Windows:** Download and run the installer from https://getcomposer.org/download/

### Step 3: Install kwtsms

```bash
composer require kwtsms/kwtsms
```

This creates a `vendor/` folder and a `composer.json` file in your project. If you don't have a project yet, create a folder first:

```bash
mkdir my-project && cd my-project
composer require kwtsms/kwtsms
```

---

## Quick Start
Send your first SMS in under 10 lines of code.

```php
require 'vendor/autoload.php';

use KwtSMS\KwtSMS;

// Load API credentials automatically from your environment or .env file
$sms = KwtSMS::from_env();

// Send an SMS
$result = $sms->send('96598765432', 'Your OTP for MYAPP is: 123456');

if ($result['result'] === 'OK') {
    echo "Message sent successfully! Cost: {$result['points-charged']} credits.\n";
} else {
    echo "Error: {$result['description']} -> {$result['action']}\n";
}
```

---

## Setup / Configuration

### 1. Environment Variables / .env file (server-side default)
The recommended and safest method to load credentials. The client library handles this automatically via the `KwtSMS::from_env()` factory method.

Create a `.env` file in the root directory of your project (and ensure it is added to `.gitignore`), and populate it with your kwtSMS credentials:

```ini
KWTSMS_USERNAME=php_username
KWTSMS_PASSWORD=php_password
KWTSMS_SENDER_ID=YOUR-SENDERID
KWTSMS_TEST_MODE=1
KWTSMS_LOG_FILE=kwtsms.log
```

If the `.env` file is missing, the client natively falls back to global environment variables.

---

## Credential Management

**This applies to ALL applications**: web backends, CMS plugins, serverless functions, etc. Credentials must NEVER be hardcoded and must be changeable without code changes.

1. **Environment variables / .env file**: Load credentials from env vars or a `.env` file natively via the client library. 
2. **Admin settings UI**: For CMS systems like WordPress, provide a settings page where an admin can enter/update credentials into the database. Include a "Test Connection" button that calls `verify()` and shows the result.
3. **Remote config / secrets manager**: Load credentials from AWS Secrets Manager, Google Secret Manager, HashiCorp Vault.
4. **Constructor injection**: The client constructor accepts all credentials directly for developers employing deep Dependency Injection.

```php
// Injecting specifically without .env mappings:
$sms = new \KwtSMS\KwtSMS(
    'php_username', 
    'php_password', 
    'YOUR-SENDERID', 
    false, // test mode
    'storage/logs/kwtsms.log' // log file
);
```

---

## Methods Reference

### Verify
Test credentials and fetch current balances.

```php
[$ok, $balance, $error] = $sms->verify();
if ($ok) {
    echo "Connected successfully! Balance: {$balance}\n";
} else {
    echo "Connection failed: {$error}\n";
}
```
**Returns:** `array{0: bool, 1: float, 2: string}`.

### Balance
Refresh and get the current credit balance alone.

```php
$balance = $sms->balance();
```
**Returns:** `float|null`. (Automatically tracks cached balance after sending messages to prevent wasting generic calls).

### Send
Send single or bulk SMS messages natively handling normalization, message cleaning, bulk-queue chunking, and retries.

```php
$result = $sms->send('96598765432', 'Hello from kwtsms!', 'OPTIONAL-SENDER');
// Array of inputs:
$bulkResult = $sms->send(['96598765432', '96512345678'], 'Bulk announcement');
```

**Returns:** `array`.
`OK` Returns structure:
```json
{"result":"OK", "msg-id":"...", "numbers":1, "points-charged":1, "balance-after":180}
```
`ERROR` Returns structure:
```json
{"result":"ERROR", "code":"ERR006", "description":"...", "action": "..."}
```

### Validate
Test formatting locally, then ping the API for supported carrier routing validations before actually issuing any SMS payloads.

```php
$report = $sms->validate(['abcd', '+965 9876 5431']);
// Returns exactly how many are valid, how many failed format, and rejects.
```

### Sender IDs
List available registered Sender IDs for your specific account.

```php
$result = $sms->senderids();
print_r($result['senderids']); // ['MY-APP', 'KWT-SMS']
```

### Coverage
List dynamically updated active country prefixes allowed on your account.
```php
$result = $sms->coverage();
```

---

## Utility Functions & Input Sanitization

The best way to prevent silent delivery failures and wasted credits is to sanitize inputs. Our core utility class operates automatically within the `send()` loop, but it is available publicly for internal verifications.

### Phone Number Validation `PhoneUtils::validate_phone_input()`
```php
use KwtSMS\PhoneUtils;

[$valid, $error, $normalized] = PhoneUtils::validate_phone_input('+٩٦٥ ٩٨٧٦ ٥٤٣٢');
// $valid -> true
// $normalized -> 96598765432
```

### Message Sanitization `MessageUtils::clean_message()`
**Called automatically on all `send()` requests.**

1. Converts Arabic-Indic/Persian digits to Latin (prevents render breaks on older devices).
2. Removes Emojis and pictographic symbols (the #1 cause of API queue-locks and wasting credits).
3. Strips hidden zero-width layout characters introduced by Microsoft Word copying.
4. Safely preserves all core Arabic alphabetic structures and translations.

---

## Error Handling

API errors map out to user-facing or system-level responses. Never expose raw `ERR006` errors back to end customers.

| Situation | Raw API error | User-facing message |
|-----------|--------------|---------------------|
| Invalid phone number | ERR006, ERR025 | "Please enter a valid phone number in international format (e.g., +965 9876 5432)." |
| Wrong credentials | ERR003 | "SMS service is temporarily unavailable. Please try again later." (do NOT tell users about auth errors. Log it and alert the admin) |
| No balance | ERR010, ERR011 | "SMS service is temporarily unavailable. Please try again later." (alert the admin to top up) |
| Country not supported | ERR026 | "SMS delivery to this country is not available. Please contact support." |
| Rate limited | ERR028 | "Please wait a moment before requesting another code." |
| Message rejected | ERR031, ERR032 | "Your message could not be sent. Please try again with different content." |
| Network error | connection timeout | "Could not connect to SMS service. Please check your internet connection and try again." |
| Queue full | ERR013 | "SMS service is busy. Please try again in a few minutes." (library retries automatically) |

---

## Phone Number Formats
Normalizations handle messy configurations and foreign inputs efficiently out of the box.

| Raw Input | Cleaned Normalized |
|:--- |:---|
| `+965 9876 5432` | `96598765432` |
| `0096598765432` | `96598765432` |
| `(965) 9876-5432` | `96598765432` |
| `٩٦٥٩٨٧٦٥٤٣٢` (Arabic Digits) | `96598765432` |

---

## What's Handled Automatically?
- **Bulk Split Rules**: Over 200 numbers passed into `send()` are split into batches of 200, injected with a hardware delay (0.5s), dispatched smoothly, and aggregate arrays merge failures.
- **Queue Protection**: If the dispatcher detects an `ERR013` (queue full) warning, the package intelligently backoffs (30s -> 60s -> 120s) before alerting failures.
- **Data Deduplication**: All leading `00` or explicit `+` values in formatting parameters are clipped into standard configurations.

---

## Security Checklist
```text
BEFORE GOING LIVE:
[ ] CAPTCHA enabled on all SMS-triggering forms
[ ] Rate limit per phone number (max 3-5/hour)
[ ] Rate limit per IP address (max 10-20/hour)
[ ] Rate limit per user/session if authenticated
[ ] Monitoring/alerting on abuse patterns
[ ] Admin notification on low balance
[ ] Test mode OFF (KWTSMS_TEST_MODE=0)
[ ] Private Sender ID registered (not KWT-SMS)
[ ] Transactional Sender ID for OTP (not promotional)
```

---

## Sender ID
- A **Sender ID** represents the company text tag (e.g., `MY-BRAND`), bypassing random strings.
- `KWT-SMS` is an explicit test parameter. It causes delays and blocks explicitly on some carriers like Virgin Kuwait. **Never use it in real production APIs.**
- **Transactional** ID configurations are inherently immune to DND (Do-Not-Disturb) systems and ensure OTP authentications succeed without silent failures.

---

## Best Practices
- **Country Code Validation**: Validate `coverage()` locally via cache. Don't waste an API call if a country block disables SMS dispatches.
- **Local Validation Rules**: Ensure your application enforces limits tracking requests-per-hour and requests-per-ip to combat bots draining credits through recursive automated sign-ups.

---

## FAQ

**1. My message was sent successfully (result: OK) but the recipient didn't receive it. What happened?**

Check the **Sending Queue** at [kwtsms.com](https://www.kwtsms.com/login/). If your message is stuck there, it was accepted by the API but not dispatched. Common causes are emoji in the message, hidden characters from copy-pasting, or spam filter triggers. Delete it from the queue to recover your credits. Also verify that `test` mode is off (`KWTSMS_TEST_MODE=0`). Test messages are queued but never delivered.

**2. What is the difference between Test mode and Live mode?**

**Test mode** (`KWTSMS_TEST_MODE=1`) sends your message to the kwtSMS queue but does NOT deliver it to the handset. No SMS credits are consumed. Use this during development. **Live mode** (`KWTSMS_TEST_MODE=0`) delivers the message for real and deducts credits. Always develop in test mode and switch to live only when ready for production.

**3. What is a Sender ID and why should I not use "KWT-SMS" in production?**

A **Sender ID** is the name that appears as the sender on the recipient's phone (e.g., "MY-APP" instead of a random number). `KWT-SMS` is a shared test sender. It causes delivery delays, is blocked on Virgin Kuwait, and should never be used in production. Register your own private Sender ID through your kwtSMS account. For OTP/authentication messages, you need a **Transactional** Sender ID to bypass DND (Do Not Disturb) filtering.

**4. I'm getting ERR003 "Authentication error". What's wrong?**

You are using the wrong credentials. The API requires your **API username and API password**, NOT your account mobile number. Log in to [kwtsms.com](https://www.kwtsms.com/login/), go to Account → API settings, and check your API credentials. Also make sure you are using POST (not GET) and `Content-Type: application/json`.

**5. Can I send to international numbers (outside Kuwait)?**

International sending is **disabled by default** on kwtSMS accounts. Contact kwtSMS support to request activation for specific country prefixes. Use `coverage()` to check which countries are currently active on your account. Be aware that activating international coverage increases exposure to automated abuse. Implement rate limiting and CAPTCHA before enabling.

---

## Help & Support

- **[kwtSMS FAQ](https://www.kwtsms.com/faq/)**: Answers to common questions about credits, sender IDs, OTP, and delivery
- **[kwtSMS Support](https://www.kwtsms.com/support.html)**: Open a support ticket or browse help articles
- **[Contact kwtSMS](https://www.kwtsms.com/#contact)**: Reach the kwtSMS team directly for Sender ID registration and account issues
- **[API Documentation (PDF)](https://www.kwtsms.com/doc/KwtSMS.com_API_Documentation_v41.pdf)**: kwtSMS REST API v4.1 full reference
- **[kwtSMS Dashboard](https://www.kwtsms.com/login/)**: Recharge credits, buy Sender IDs, view message logs, manage coverage
- **[Other Integrations](https://www.kwtsms.com/integrations.html)**: Plugins and integrations for other platforms and languages

## License
MIT
