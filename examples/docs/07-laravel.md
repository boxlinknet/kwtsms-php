# Example 07 — Laravel Integration

**File:** `examples/07-laravel.php`
**Run:** Reference only — copy snippets into your Laravel app

Integrate kwtSMS as a first-class Laravel service with a Service Provider,
configuration file, controller injection, and an optional Notification channel.

---

## Architecture

```
Laravel App
  │
  ├─ config/kwtsms.php          ← Credentials from .env
  │
  ├─ KwtSmsServiceProvider      ← Registers KwtSMS as singleton
  │       └─ $app->singleton(KwtSMS::class, ...)
  │
  ├─ Controller (injection)     ← public function __construct(private KwtSMS $sms)
  │
  └─ Notification Channel       ← $user->notify(new SmsNotification('...'))
```

---

## Setup

### Step 1 — Install

```bash
composer require kwtsms/kwtsms
```

### Step 2 — Create config file

`config/kwtsms.php`:

```php
return [
    'username'  => env('KWTSMS_USERNAME'),
    'password'  => env('KWTSMS_PASSWORD'),
    'sender_id' => env('KWTSMS_SENDER_ID', 'KWT-SMS'),
    'test_mode' => env('KWTSMS_TEST_MODE', false),
    'log_file'  => env('KWTSMS_LOG_FILE', storage_path('logs/kwtsms.log')),
];
```

### Step 3 — Add to `.env`

```ini
KWTSMS_USERNAME=php_username
KWTSMS_PASSWORD=php_password
KWTSMS_SENDER_ID=MY-BRAND
KWTSMS_TEST_MODE=false
KWTSMS_LOG_FILE=
```

### Step 4 — Create the Service Provider

Create `app/Providers/KwtSmsServiceProvider.php` (see `07-laravel.php` for full
code). Register in `config/app.php` → `providers`:

```php
App\Providers\KwtSmsServiceProvider::class,
```

### Step 5 — Use in a controller

```php
class AuthController extends Controller
{
    public function __construct(private KwtSMS $sms) {}

    public function sendOtp(Request $request): JsonResponse
    {
        $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        cache()->put('otp_' . $request->phone, $otp, now()->addMinutes(10));

        $result = $this->sms->send(
            $request->phone,
            "Your MyApp code is: {$otp}\nExpires in 10 minutes."
        );

        if ($result['result'] !== 'OK') {
            logger()->error('kwtSMS OTP failed', $result);
            return response()->json(['message' => 'Could not send code.'], 503);
        }

        return response()->json(['message' => 'Code sent.']);
    }
}
```

### Step 6 — (Optional) Notification Channel

```php
// In a Notifiable model (e.g. User):
public function routeNotificationForSms(): string
{
    return $this->phone;
}

// Trigger from anywhere:
$user->notify(new SmsNotification('Your appointment is confirmed for tomorrow.'));
```

---

## OTP Flow in Laravel

```
POST /auth/otp/send
  │
  ├─ Validate request (phone required)
  ├─ Generate OTP with random_int()
  ├─ Store in cache: key=otp_{phone}, TTL=10min
  ├─ Send SMS via $this->sms->send()
  └─ Return 200 OK or 503

POST /auth/otp/verify
  │
  ├─ Validate request (phone + otp required)
  ├─ Fetch from cache: otp_{phone}
  ├─ Compare with hash_equals() (timing-safe)
  ├─ cache()->forget('otp_' . $phone) — invalidate after use
  └─ Return 200 OK or 422
```

For production Laravel OTP with rate limiting and queue-based sending, see
[Example 09](09-otp-production.md) — swap PDO storage for Eloquent/Redis.
