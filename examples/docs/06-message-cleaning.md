# Example 06 — Message Cleaning

**File:** `examples/06-message-cleaning.php`
**Run:** `php examples/06-message-cleaning.php`

Understand what `clean_message()` strips from your text and why. This runs
automatically inside `send()` — you never need to call it manually.

---

## What Gets Cleaned

```
Raw message text
  │
  ├─ 1. Strip HTML tags  ────────────────→  prevents ERR027
  │       "<b>Hello</b>"  →  "Hello"
  │
  ├─ 2. Convert Arabic/Hindi digits  ────→  OTP codes render consistently
  │       "رمز التحقق: ١٢٣٤٥٦"  →  "رمز التحقق: 123456"
  │
  ├─ 3. Strip hidden Unicode  ───────────→  prevents spam filter / queue stuck
  │       Zero-width space (U+200B)  →  removed
  │       Byte order mark (U+FEFF)   →  removed
  │       Soft hyphen (U+00AD)       →  removed
  │
  ├─ 4. Strip emojis  ───────────────────→  prevents silent queue stuck
  │       "Order ready 📦"  →  "Order ready "
  │       Includes: standard emojis, country flags, keycap sequences,
  │                 mahjong tiles, tags block (subdivision flags)
  │
  └─ 5. Strip other control characters  ─→  prevents encoding issues
         (newlines \n, \r, and tabs \t are preserved)
```

---

## Why Each Category Matters

**Emojis** — When an emoji is present, kwtSMS queues the message but never
dispatches it. The API returns `OK`, credits may be charged, and the message
sits in the queue indefinitely with no error returned. `clean_message()` strips
emojis before the API call entirely.

**Hidden Unicode** — Text copied from Word, PDFs, WhatsApp, or CMS editors
embeds invisible characters (zero-width spaces, BOM, soft hyphens). These
trigger spam filters or cause messages to get stuck in the queue.

**Arabic/Hindi numerals** — OTP codes and amounts written as `١٢٣٤٥٦` may
render inconsistently depending on handset locale. Converting to Latin `123456`
ensures every recipient sees the same digits.

---

## Usage

`clean_message()` is called by `send()` automatically. Only call it manually
when pre-processing message templates before storing them:

```php
use KwtSMS\MessageUtils;

$text = MessageUtils::clean_message($rawTemplate);
$sms->send($phone, $text); // clean_message() runs again inside send(), but it is idempotent
```

---

## What Is NOT Stripped

- Arabic text and letters (fully preserved)
- Newlines (`\n`), carriage returns (`\r`), tabs (`\t`)
- Latin punctuation and symbols
- Spaces

---

## Message Length Reference

| Language | Single page | Multi-page (per part) | Max pages |
|----------|-------------|----------------------|-----------|
| English / Latin (GSM-7) | 160 chars | 153 chars | 7 |
| Arabic / Unicode | 70 chars | 67 chars | 7 |

One Arabic character anywhere in the message makes the **entire message** count
as Unicode (70-char pages). Each page is billed as 1 credit.
