# Example 03 — Bulk SMS

**File:** `examples/03-bulk.php`
**Run:** `php examples/03-bulk.php`

Send one message to many recipients. Demonstrates small batch (≤200),
large auto-batched (>200), and mixed valid/invalid lists.

---

## Flow

```
Input: array of phone numbers + message
  │
  ├─ $sms->send(array, message)
  │
  ├─ Library: local pre-validation
  │       ├─ Normalize each number (strip +, 00, spaces, Arabic digits)
  │       ├─ Reject invalid numbers locally → $result['invalid']
  │       └─ Deduplicate remaining numbers
  │
  ├─ Count ≤200?
  │   │
  │   ├─ YES: single API call → flat result with msg-id
  │   │
  │   └─ NO:  split into 200-number batches
  │           foreach batch:
  │             ├─ POST to /API/send/
  │             ├─ ERR013 (queue full)? → retry 30s / 60s / 120s backoff
  │             └─ 0.5s delay before next batch
  │           → merged result with msg-ids array, batches count
  │
  End
```

---

## Step-by-Step

### Small batch (≤200 numbers)

```php
$result = $sms->send($recipients, $message);

// $result['result']         → 'OK' or 'ERROR'
// $result['msg-id']         → single message ID
// $result['numbers']        → recipients accepted
// $result['points-charged'] → credits used
// $result['balance-after']  → balance remaining
// $result['invalid']        → locally rejected numbers (always check this)
```

### Large batch (>200 numbers) — auto-chunked

```php
$bulk = $sms->send($large_list, $message);

// $bulk['result']           → 'OK' / 'ERROR' / 'PARTIAL'
// $bulk['batches']          → number of API calls made
// $bulk['numbers']          → total accepted across all batches
// $bulk['points-charged']   → total credits used
// $bulk['balance-after']    → balance after all batches complete
// $bulk['msg-ids']          → array of message IDs (one per batch)
// $bulk['errors']           → batch-level errors (code + description per batch)
```

The library automatically:

- Splits the list into 200-number batches
- Waits 500ms between batches (API rate limit)
- Retries `ERR013` (queue full) with exponential backoff: 30s → 60s → 120s (max 4 attempts)

Do not manually chunk before calling `send()` — the library handles it.

### Mixed lists with invalid numbers

Valid numbers are still sent even when some inputs fail:

```php
$result = $sms->send(['96598765432', 'admin@test.com', '123'], $message);
// $result['result']  → 'OK' (valid numbers were sent)
// $result['invalid'] → [
//     ['number' => 'admin@test.com', 'error' => 'is an email address...'],
//     ['number' => '123',            'error' => 'is too short (3 digits)'],
// ]
```

Always inspect `$result['invalid']` after any bulk send.

---

## Performance Reference

| List size | API calls | Minimum time |
|-----------|-----------|--------------|
| 1–200 | 1 | ~0.5s |
| 201–400 | 2 | ~1s |
| 401–600 | 3 | ~1.5s |
| 1,000 | 5 | ~2.5s |
| 10,000 | 50 | ~25s |

For very large lists (10,000+), run the send from a background job — not from an
HTTP request handler. HTTP timeouts will kill the process mid-send.

---

## Billing

- Each accepted number consumes 1 credit per SMS page
- Rejected numbers in `invalid` are never charged
- Multi-page messages (>160 chars English / >70 chars Arabic) are billed per page

See [message length reference](06-message-cleaning.md#message-length-reference).
