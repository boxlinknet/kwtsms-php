# Example 04 — Phone Validation

**File:** `examples/04-validation.php`
**Run:** `php examples/04-validation.php`

Clean and validate phone numbers — locally (instant, no API call) or against
the kwtSMS routing database (checks carrier support).

---

## Two Validation Modes

```
Option A: Local validation  (PhoneUtils::validate_phone_input)
──────────────────────────────────────────────────────────────
Input phone string
  │
  ├─ Empty check
  ├─ Email address check (contains @)
  ├─ Arabic/Hindi digit conversion
  ├─ Strip all non-digit characters
  ├─ Strip leading zeros
  ├─ Length check: 7–15 digits
  └─ Return [valid, error, normalizedPhone]

No network call. Instant. Use on every form input before send().


Option B: API validation  ($sms->validate)
──────────────────────────────────────────
Input: array of numbers
  │
  ├─ Local pre-validation on each number
  ├─ POST to /API/validate/  (network call)
  │
  └─ Returns breakdown:
       OK  → valid and routable on your account
       ER  → format error
       NR  → no route (country not activated)
```

---

## Step-by-Step

### Local validation — use for all user input

```php
use KwtSMS\PhoneUtils;

[$valid, $error, $normalized] = PhoneUtils::validate_phone_input($rawInput);

if (!$valid) {
    echo $error;       // "'+965 abc' is not a valid phone number, no digits found"
} else {
    echo $normalized;  // "96598765432"
}
```

Use local validation first on every form submission:

1. Instant — no network round-trip
2. Catches emails, empty strings, and short numbers before any API call
3. Returns the normalized number ready to pass to `send()`

**Normalization rules:**

```
"+965 9876-5432"  → "96598765432"   strip +, spaces, dashes
"0096598765432"   → "96598765432"   strip leading 00
"٩٦٥٩٨٧٦٥٤٣٢"   → "96598765432"   Arabic-Indic digits → Latin
"۹۶۵۹۸۷۶۵۴۳۲"   → "96598765432"   Extended Arabic-Indic → Latin
"(965) 9876.5432" → "96598765432"   strip parentheses and dots
```

### API validation — use before bulk campaigns

```php
$report = $sms->validate($numberList);

echo $report['nr'];  // total submitted
echo $report['ok'];  // locally valid count
echo $report['er'];  // invalid count

foreach ($report['rejected'] as $r) {
    echo "{$r['number']}: {$r['error']}\n";
}

// Full detail per number:
foreach ($report['raw'] as $entry) {
    // $entry['phone']      → original input
    // $entry['valid']      → bool
    // $entry['normalized'] → cleaned number (if valid)
    // $entry['error']      → error message (if invalid)
}
```

`/API/validate/` checks routability on your specific account — catches countries
you haven't activated. For single OTP sends, local validation is sufficient.

---

## Decision Guide

| Scenario | Use |
|----------|-----|
| Single user input on a form | `PhoneUtils::validate_phone_input()` |
| Before every `send()` call | Built-in to `send()` — no extra call needed |
| Cleaning an imported CSV | `PhoneUtils::validate_phone_input()` in a loop |
| Pre-campaign routing check | `$sms->validate($list)` — API call |

See [phone number format reference](reference.md#phone-number-format-reference) for
the full list of accepted and rejected formats.
