# Example 04: Phone Validation

**File:** `examples/04-validation.php`
**Run:** `php examples/04-validation.php`

Clean and validate phone numbers locally (instant, no API call) before sending.

---

## Two Validation Modes

```
Option A: Local validation  (PhoneUtils::validate_phone_input)
--------------------------------------------------------------
Input phone string
  |
  +- Empty check
  +- Email address check (contains @)
  +- Arabic/Hindi digit conversion + strip all non-digit characters
  +- Strip leading zeros
  +- Strip local trunk digit '0' when country code is matched
  |    e.g. "966 055 123 4567" -> "966551234567"
  +- Length check: 7-15 digits
  +- Country-specific format check (PHONE_RULES, 60+ countries):
  |    - Local digit count (e.g. Kuwait: 8, Saudi: 9)
  |    - Mobile starting digit (e.g. Kuwait: 4/5/6/9, Saudi: 5)
  |    - Unknown country codes pass with length-only validation
  +- Return [valid, error, normalizedPhone]

No network call. Instant. Use on every form input before send().


Option B: Batch local validation  ($sms->validate)
---------------------------------------------------
Input: array of numbers
  |
  +- Runs PhoneUtils::validate_phone_input on each number  (no network call)
  |
  +- Returns breakdown:
       ok       count of locally valid numbers
       er       count of invalid numbers
       rejected array with number and error message per invalid entry
       raw      full per-number detail (input, valid, normalized, error)
```

---

## Step-by-Step

### Local validation: use for all user input

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

1. Instant, no network round-trip
2. Catches emails, empty strings, and short numbers before any API call
3. Validates against country-specific length and mobile prefix rules
4. Returns the normalized number ready to pass to `send()`

**Normalization rules:**

```
"+965 9876-5432"    -> "96598765432"   strip +, spaces, dashes
"0096598765432"     -> "96598765432"   strip leading 00
"966 055 123 4567"  -> "966551234567"  strip local trunk digit 0 after country code
"90765432"          -> "96598765432"   Arabic-Indic digits to Latin (example only)
"(965) 9876.5432"   -> "96598765432"   strip parentheses and dots
```

**Country-specific validation examples:**

```
"96596123456"   -> valid   (Kuwait: 8 local digits, starts with 6)
"96510000000"   -> invalid (Kuwait mobile numbers cannot start with 1)
"966512345678"  -> valid   (Saudi Arabia: 9 local digits, starts with 5)
"966412345678"  -> invalid (Saudi Arabia mobile numbers cannot start with 4)
"712345678"     -> valid   (no matching country code: generic length-only check)
```

### Batch validation with `$sms->validate()`: local, no API call

```php
$report = $sms->validate($numberList);

echo $report['nr'];  // total submitted
echo $report['ok'];  // valid count
echo $report['er'];  // invalid count

foreach ($report['rejected'] as $r) {
    echo "{$r['number']}: {$r['error']}\n";
}

// Full detail per number:
foreach ($report['raw'] as $entry) {
    // $entry['phone']      original input
    // $entry['valid']      bool
    // $entry['normalized'] cleaned number (if valid)
    // $entry['error']      error message (if invalid)
}
```

`$sms->validate()` is **local only**. No network call is made. It runs
`PhoneUtils::validate_phone_input()` on each number and returns a structured report.

For routing validation (checking if numbers are reachable on your account),
use the kwtSMS web dashboard or call `/API/validate/` directly.

---

## Decision Guide

| Scenario | Use |
|----------|-----|
| Single user input on a form | `PhoneUtils::validate_phone_input()` |
| Before every `send()` call | Built-in to `send()`, no extra call needed |
| Cleaning an imported CSV | `PhoneUtils::validate_phone_input()` in a loop |
| Pre-campaign routing check | `/API/validate/` directly or kwtSMS dashboard |

See [phone number format reference](reference.md#phone-number-format-reference) for
the full list of accepted and rejected formats.
