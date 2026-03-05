# Example 09: Production OTP Flow

**File:** `examples/09-otp-production.php`
**Run:** `php examples/09-otp-production.php`

A complete, drop-in production OTP implementation. Covers database storage,
CAPTCHA verification, per-phone and per-IP rate limiting, brute-force protection,
and three interchangeable storage adapters (SQLite, MySQL, Redis).

---

## Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                         OtpService                              │
│                                                                 │
│   sendOtp(phone, captchaToken, clientIp)                        │
│      │                                                          │
│      ├─ PhoneUtils::validate_phone_input()  (local, instant)    │
│      ├─ CaptchaVerifier::verify()           (Turnstile/hCaptcha)│
│      ├─ OtpStorageAdapter::countRecentSends()  per phone        │
│      ├─ OtpStorageAdapter::countRecentSends()  per IP           │
│      ├─ random_int() → 6-digit OTP                              │
│      ├─ hash_hmac('sha256', phone:otp, appSecret)               │
│      ├─ KwtSMS::send()                                          │
│      ├─ OtpStorageAdapter::store(hash, msgId, balance)          │
│      └─ OtpStorageAdapter::recordSend() × 2                     │
│                                                                 │
│   verifyOtp(phone, submittedCode)                               │
│      │                                                          │
│      ├─ PhoneUtils::validate_phone_input()                      │
│      ├─ OtpStorageAdapter::fetch()                              │
│      ├─ Expiry check                                            │
│      ├─ ctype_digit() + length check on submitted code          │
│      ├─ OtpStorageAdapter::incrementAttempts()  ← BEFORE check  │
│      ├─ Attempt limit check                                     │
│      ├─ hash_hmac() + hash_equals()                             │
│      └─ OtpStorageAdapter::markUsed()                           │
└─────────────────────────────────────────────────────────────────┘
         │                          │
    OtpStorageAdapter          CaptchaVerifier
    ┌────────────┐              ┌──────────────────┐
    │ SQLite     │              │ TurnstileVerifier │
    │ MySQL      │              │ HCaptchaVerifier  │
    │ Redis      │              │ NullCaptcha (dev) │
    └────────────┘              └──────────────────┘
```

---

## Setup

### Step 1: Register a Transactional SenderID

This is the most important step. Without it, OTP messages to DND numbers fail
silently and credits are still charged.

1. Log in to kwtsms.com → Buy SenderID → Transactional
2. Cost: 15 KD one-time
3. Processing time: ~5 business days (manual telecom process)
4. Use your brand name (3–11 alphanumeric characters, no spaces)

Use `KWT-SMS` with test mode on while waiting for approval.

### Step 2: Generate APP_SECRET

```bash
php -r "echo bin2hex(random_bytes(32)); echo PHP_EOL;"
```

Add to `.env`:

```ini
APP_SECRET=a1b2c3d4e5f6...   # never commit this
APP_NAME=MyApp
```

### Step 3: Choose a storage driver

```ini
# SQLite (default — zero config, dev + small apps)
STORAGE_DRIVER=sqlite
SQLITE_PATH=/var/www/myapp/storage/otp.db

# MySQL (production relational DB)
STORAGE_DRIVER=mysql
MYSQL_DSN=mysql:host=localhost;dbname=myapp;charset=utf8mb4
MYSQL_USER=myapp_user
MYSQL_PASSWORD=secret

# Redis (high-traffic, multi-server)
STORAGE_DRIVER=redis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_PASSWORD=
REDIS_DB=0
```

### Step 4: Choose a CAPTCHA provider

**Cloudflare Turnstile** (recommended: near-zero user friction, free):

1. dash.cloudflare.com → Turnstile → Add site
2. Copy Site Key → paste into your HTML form
3. Copy Secret Key → add to `.env`:

```ini
CAPTCHA_PROVIDER=turnstile
TURNSTILE_SECRET=your_secret_key_here
```

**hCaptcha:**

```ini
CAPTCHA_PROVIDER=hcaptcha
HCAPTCHA_SECRET=your_secret_key_here
```

### Step 5: Add the HTML form

Paste the CAPTCHA frontend snippet from the bottom of `09-otp-production.php`
into your template. Replace `YOUR_CF_SITE_KEY` / `YOUR_HCAPTCHA_SITE_KEY` with
the Site Key from Step 4.

### Step 6: Wire up HTTP routes

Plain PHP:

```php
$service = buildOtpService();
$uri     = strtok($_SERVER['REQUEST_URI'], '?');

match ($uri) {
    '/otp/send'   => handleSendOtp($service),
    '/otp/verify' => handleVerifyOtp($service),
    default       => http_response_code(404),
};
```

Laravel:

```php
// routes/api.php
Route::post('/otp/send',   [OtpController::class, 'send']);
Route::post('/otp/verify', [OtpController::class, 'verify']);

// OtpController.php
class OtpController extends Controller {
    public function __construct(private OtpService $service) {}

    public function send(Request $request): JsonResponse {
        $result = $this->service->sendOtp(
            $request->input('phone'),
            $request->input('captcha_token', ''),
            $request->ip()
        );
        return response()->json($result, $result['success'] ? 200 : 422);
    }

    public function verify(Request $request): JsonResponse {
        $result = $this->service->verifyOtp(
            $request->input('phone'),
            $request->input('code')
        );
        return response()->json($result, $result['success'] ? 200 : 422);
    }
}
```

### Step 7: Run the CLI demo

```bash
php examples/09-otp-production.php
```

Runs the full send → wrong code → correct code flow using SQLite and
`NullCaptchaVerifier` (CAPTCHA skipped in CLI mode).

---

## Send OTP: Detailed Flow

```
POST /otp/send  {"phone": "+965 9876 5432", "captcha_token": "xxxx"}
  │
  ├─ 1. VALIDATE & NORMALIZE PHONE (local, no API call)
  │       PhoneUtils::validate_phone_input("+965 9876 5432")
  │       → [true, null, "96598765432"]
  │       Reject if: empty, email, no digits, <7 digits, >15 digits
  │
  ├─ 2. VERIFY CAPTCHA
  │       POST to Cloudflare/hCaptcha with token + IP
  │       Fail → 422 "CAPTCHA verification failed"
  │       (Bot check runs BEFORE rate limit to avoid leaking state)
  │
  ├─ 3. PER-PHONE RATE LIMIT
  │       Count sends from "96598765432" in last 10 minutes
  │       ≥3 → 422 "Too many codes sent. Wait 10 minutes."
  │
  ├─ 4. PER-IP RATE LIMIT
  │       Count sends from client IP in last 60 minutes
  │       ≥10 → 422 "Too many requests from your location."
  │       (Spoofed/invalid IPs fall back to "0.0.0.0")
  │
  ├─ 5. GENERATE OTP
  │       random_int(0, 999999) → "042819"  (zero-padded, 6 digits)
  │
  ├─ 6. HASH OTP
  │       hash_hmac('sha256', "96598765432:042819", APP_SECRET)
  │       ONLY the hash is stored — DB dump reveals nothing
  │
  ├─ 7. SEND SMS
  │       MessageUtils::clean_message(...)  → KwtSMS::send()
  │       Error → map code → user-safe message, log masked phone
  │
  ├─ 8. PERSIST
  │       store(phone, codeHash, expiresAt, msgId, balanceAfter)
  │       Old codes replaced — one active code per phone
  │
  ├─ 9. RECORD RATE-LIMIT EVENTS
  │       recordSend("phone:96598765432", 600)
  │       recordSend("ip:x.x.x.x",       3600)
  │
  └─ 10. RESPOND
          200 {success: true, expires_in: 300, msg_id: "abc123"}
          OTP code is NEVER in the response.
```

---

## Verify OTP: Detailed Flow

```
POST /otp/verify  {"phone": "96598765432", "code": "042819"}
  │
  ├─ 1. NORMALIZE PHONE
  │       validate_phone_input → "96598765432"
  │
  ├─ 2. FETCH ACTIVE RECORD
  │       SELECT WHERE phone=... AND used=0
  │       No record → 422 "No active code found."
  │
  ├─ 3. CHECK EXPIRY
  │       time() > expires_at → 422 "Code has expired."
  │
  ├─ 4. VALIDATE CODE FORMAT
  │       ctype_digit() + strlen() === OTP_DIGITS
  │       Rejects letters, whitespace, wrong length
  │
  ├─ 5. INCREMENT ATTEMPTS  ← BEFORE comparison (critical)
  │       UPDATE SET attempts = attempts + 1
  │       Runs even on a correct guess — prevents timing oracle
  │
  ├─ 6. CHECK ATTEMPT LIMIT
  │       attempts > 5 → 422 "Too many attempts. Request a new code."
  │
  ├─ 7. HASH AND COMPARE (constant-time)
  │       hash_hmac('sha256', phone:code, APP_SECRET)
  │       hash_equals(stored, submitted)
  │       No match → 422 "Incorrect code. N attempts remaining."
  │
  ├─ 8. MARK USED
  │       UPDATE SET used=1  — prevents replay
  │
  └─ 9. RESPOND
          200 {success: true, phone: "96598765432"}
          Store normalized phone in session/DB as the verified number.
```

---

## Storage Adapter Comparison

| | SQLite | MySQL/MariaDB | Redis |
|-|--------|--------------|-------|
| Setup | Zero-config | Needs server | Needs server |
| PHP extension | pdo_sqlite | pdo_mysql | phpredis or predis |
| Cleanup | Pruned on write | Pruned on write | Native TTL |
| Multi-server | No (file-based) | Yes | Yes |
| Best for | Dev, small apps | Most production | High-traffic |

**Redis installation:**

```bash
sudo apt install php-redis && sudo systemctl restart php8.x-fpm
# OR: composer require predis/predis  (pure PHP, no extension)
```

---

## CAPTCHA Comparison

| | Cloudflare Turnstile | hCaptcha |
|-|---------------------|---------|
| User friction | Near-zero (Managed) | Occasional puzzle |
| Free tier | Yes, unlimited | Yes, limited |
| Privacy | No cookies, GDPR-OK | No cookies, GDPR-OK |
| Dashboard | dash.cloudflare.com | dashboard.hcaptcha.com |

---

## Security Properties

| Property | Implementation | Why |
|----------|---------------|-----|
| No plaintext OTP in DB | HMAC-SHA256 stored | DB dump reveals nothing |
| Timing-safe comparison | `hash_equals()` | Prevents timing oracle |
| Attempt before compare | Increment first | Prevents guess-at-expiry race |
| Replay protection | `markUsed()` after success | Code cannot be reused |
| CAPTCHA before rate check | Verify first | Avoids rate-limit state leakage |
| No code in HTTP response | Response omits OTP | Cannot be intercepted from network |
| Phone masked in logs | `***` + last 4 digits | Privacy compliance |
| IP validation | `filter_var(FILTER_VALIDATE_IP)` | Prevents spoofed rate-limit bypass |

---

## Rate Limiting Design

```
Per-phone: max 3 sends per 10-minute sliding window
  Prevents: SMS bombing — many OTPs to one victim's number

Per-IP: max 10 sends per 60-minute sliding window
  Prevents: Scanning attacks — cycling through numbers from one IP

Both stored in the same DB as OTP records.
No separate Redis instance required if using SQLite or MySQL.
```

See [Pre-Launch Checklist](reference.md#pre-launch-checklist) before going live.
