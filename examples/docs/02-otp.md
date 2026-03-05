# Example 02 — OTP (Basic)

**File:** `examples/02-otp.php`
**Run:** `php examples/02-otp.php`

Sends a one-time verification code. Covers the essential OTP pattern: generate
a secure code, send it, and store it for later comparison.

> For a complete production OTP flow with database storage, CAPTCHA, rate limiting,
> and brute-force protection, see [Example 09](09-otp-production.md).

---

## Flow

```
User requests OTP
  │
  ├─ 1. Generate 6-digit code  ──→  random_int(0, 999999) + zero-pad
  │
  ├─ 2. Build message  ───────────→  "Your MyApp code is: 123456\nValid for 10 min."
  │
  ├─ 3. Send SMS  ────────────────→  $sms->send(phone, message)
  │       │
  │       ├─ OK:    return {success, otp, msg_id}
  │       │           Store otp in session/cache for verification
  │       └─ ERROR: map error code → user-safe message
  │                  log real error internally
  End
```

---

## Step-by-Step

### Generating a secure OTP

```php
function generate_otp(int $digits = 6): string
{
    return str_pad((string) random_int(0, 10 ** $digits - 1), $digits, '0', STR_PAD_LEFT);
}
```

- `random_int()` uses a CSPRNG — never use `rand()` or `mt_rand()`
- `str_pad()` ensures leading zeros: `42` → `"000042"`

### Mapping errors to user-safe messages

Never expose raw API error codes to end users — they reveal your infrastructure.

```php
switch ($result['code']) {
    case 'ERR006':
    case 'ERR025': return 'Please enter a valid phone number.';
    case 'ERR026': return 'SMS delivery to this country is not available.';
    case 'ERR028': return 'Please wait before requesting another code.';
    default:       return 'Could not send verification code. Please try again.';
}
```

### Storing the OTP for verification

```php
// Session (simplest — single server, short-lived)
$_SESSION['otp']        = $otp;
$_SESSION['otp_phone']  = $phone;
$_SESSION['otp_expiry'] = time() + 600;

// Laravel cache
cache()->put('otp_' . $phone, $otp, now()->addMinutes(10));

// Database — store HMAC hash, never plaintext (see Example 09)
```

---

## Key Rules

- **Transactional SenderID required** — Promotional SenderIDs are silently blocked
  on DND numbers. Credits are still charged. See [reference](reference.md#sender-id).
- **Include your app name** in the message: `"Your APPNAME code is: 123456"` — telecom requirement
- **Never return the OTP code** in your HTTP response — it travels only via SMS
- **Never show raw API error codes** to users
