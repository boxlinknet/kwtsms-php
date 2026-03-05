# Reference

Shared reference tables for all examples.

---

## Phone Number Format Reference

```
International format (correct):
  96598765432      ✅

Auto-normalized by the library:
  +96598765432     ✅  strip +
  0096598765432    ✅  strip leading 00
  965 9876 5432    ✅  strip spaces
  965-9876-5432    ✅  strip dashes
  (965) 9876.5432  ✅  strip parentheses and dots
  ٩٦٥٩٨٧٦٥٤٣٢     ✅  Arabic-Indic digits → Latin
  ۹۶۵۹۸۷۶۵۴۳۲     ✅  Extended Arabic-Indic → Latin

Rejected:
  admin@example.com  ❌  email address
  1234               ❌  too short (under 7 digits)
  12345678901234567  ❌  too long (over 15 digits)
  (empty string)     ❌  empty
```

---

## Sender ID

`KWT-SMS` is a shared promotional sender for **testing only**.

- May cause delivery delays
- Blocked on Virgin Kuwait numbers
- Must **never** be used in production for OTP

**Two types of private Sender ID:**

| | Promotional | Transactional |
|-|-------------|--------------|
| Use for | Bulk SMS, marketing | OTP, alerts, reminders |
| DND numbers | Silently blocked | Bypasses DND |
| Cost | 10 KD one-time | 15 KD one-time |
| Register at | kwtsms.com → Buy SenderID | kwtsms.com → Buy SenderID → Transactional |
| Processing | ~5 business days | ~5 business days |

**For OTP you must use a Transactional SenderID.** Using a Promotional SenderID
for OTP means messages to DND numbers are silently blocked and credits are
still deducted.

---

## Error Code Reference

| Code | Context | Description | Action |
|------|---------|-------------|--------|
| ERR001 | All | API disabled on account | Contact kwtSMS support |
| ERR002 | All | Missing required parameter | Check your request body |
| ERR003 | All | Wrong username or password | Verify API credentials (not website login) |
| ERR004 | All | Account has no API access | Contact kwtSMS to enable API |
| ERR005 | All | Account blocked | Contact kwtSMS support |
| ERR006 | send | No valid numbers submitted | Check number format |
| ERR007 | send | More than 200 numbers in one call | Library handles splitting automatically |
| ERR008 | send | SenderID is banned | Check or re-register SenderID |
| ERR009 | send | Empty message | Add message content |
| ERR010 | send | Zero balance | Recharge at kwtsms.com |
| ERR011 | send | Insufficient balance | Recharge at kwtsms.com |
| ERR012 | send | Message too long (>7 pages) | Shorten the message |
| ERR013 | send | Send queue full (1000 msgs) | Retry after 30s — library handles in bulk |
| ERR019–023 | dlr | Delivery report issues | See API docs |
| ERR024 | All | IP not in whitelist | Add server IP at kwtsms.com → API → IP Lockdown |
| ERR025 | send | Invalid number format | Strip non-digits, remove + or 00 prefix |
| ERR026 | send | No route for country | Contact kwtSMS to activate country |
| ERR027 | send | HTML tags in message | Strip HTML before sending |
| ERR028 | send | 15-second gap required between sends to same number | Wait 15s, retry once |
| ERR029 | status | Message ID not found | Check msg-id value |
| ERR030 | status | Message stuck in queue | Delete from queue to recover credits |
| ERR031 | send | Bad language detected | Review message content |
| ERR032 | send | Spam detected | Review message content |
| ERR033 | coverage | No active coverage | Contact kwtSMS support |

---

## Pre-Launch Checklist

### Credentials & Configuration

- [ ] Using API username/password — not your website login
- [ ] `KWTSMS_TEST_MODE=0` set in production `.env`
- [ ] Registered a private Transactional SenderID (required for OTP)
- [ ] `APP_SECRET` set to a 32+ character random value, not committed to git
- [ ] `.env` is in `.gitignore`
- [ ] CAPTCHA configured (Turnstile or hCaptcha secret key set)

### Phone Number Handling

- [ ] All user inputs validated with `PhoneUtils::validate_phone_input()` before send
- [ ] Numbers with `+` prefix handled
- [ ] Numbers with `00` prefix handled
- [ ] Arabic/Hindi digit input handled

### Security

- [ ] OTP codes stored as HMAC hash, not plaintext
- [ ] Comparison uses `hash_equals()` — not `==` or `===`
- [ ] Brute-force protection: max 5 attempts per code
- [ ] Rate limiting: per phone and per IP
- [ ] CAPTCHA enforced on OTP send endpoint
- [ ] Raw API error codes never shown to end users
- [ ] Phone numbers masked in logs (last 4 digits only)

### SMS Content

- [ ] App name included in OTP message: `"Your APPNAME code is: 123456"`
- [ ] No emojis in message templates
- [ ] No HTML in message templates
- [ ] Expiry stated in message: `"Valid for 5 minutes"`

### Operations

- [ ] `msg-id` saved from every successful send response
- [ ] `balance-after` saved from every successful send response
- [ ] Low-balance alert configured (notify admin when balance drops below threshold)
- [ ] Error logging in place — never log OTP codes or full phone numbers
- [ ] End-to-end tested in test mode before switching to live

### Anti-Abuse

- [ ] CAPTCHA on OTP request form
- [ ] Rate limit: max 3 OTP requests per phone per 10 minutes
- [ ] Rate limit: max 10 OTP requests per IP per hour
- [ ] OTP codes expire after 5 minutes
- [ ] Old code invalidated when new code is requested
