<?php

/**
 * Example 03: Bulk SMS
 * --------------------
 * Send a single message to hundreds or thousands of recipients efficiently.
 * The library handles all batching, delays, and retries automatically.
 *
 * How bulk sending works:
 *   - Up to 200 numbers: single API call
 *   - Over 200 numbers: automatically split into batches of 200
 *   - 0.5-second mandatory delay between batches (API rate limit)
 *   - ERR013 (queue full): automatic retry with 30s / 60s / 120s backoff
 *
 * Run:
 *   php examples/03-bulk.php
 */

require __DIR__ . '/../vendor/autoload.php';

use KwtSMS\KwtSMS;

$sms = KwtSMS::from_env();

// ── Example 1: Small bulk (≤200 numbers, single API call) ───────────────────

$recipients = [
    '96598765432',
    '96512345678',
    '+965 9000 0001',  // Normalized automatically
    '00965 9000 0002', // Normalized automatically
];

echo "Sending to " . count($recipients) . " recipients...\n\n";

$result = $sms->send($recipients, 'Dear customer, your order has been dispatched and will arrive within 2 business days.');

if ($result['result'] === 'OK') {
    echo "✅ Sent to all recipients!\n";
    echo "   Message ID  : {$result['msg-id']}\n";
    echo "   Numbers sent: {$result['numbers']}\n";
    echo "   Credits used: {$result['points-charged']}\n";
    echo "   Balance left: {$result['balance-after']}\n";
} else {
    echo "❌ Send failed: {$result['description']}\n";
}

// ── Example 2: Large bulk (>200 numbers, auto-batched) ───────────────────────
//
// The send() method detects >200 numbers and handles everything automatically.
// The result has a different shape: msg-ids (array), batches count, errors per batch.

echo "\n" . str_repeat('─', 60) . "\n\n";

// Simulate 500 phone numbers loaded from your database
$large_list = array_map(
    fn($i) => '9659' . str_pad((string) $i, 7, '0', STR_PAD_LEFT),
    range(1000000, 1000499) // 500 numbers
);

echo "Bulk sending to " . count($large_list) . " recipients (auto-batched)...\n\n";

$bulk = $sms->send($large_list, 'Special offer: 20% off all orders this weekend. Use code WEEKEND20.');

echo "Result   : {$bulk['result']}\n";
echo "Batches  : {$bulk['batches']}\n";
echo "Numbers  : {$bulk['numbers']}\n";
echo "Credits  : {$bulk['points-charged']}\n";
echo "Balance  : {$bulk['balance-after']}\n";
echo "Msg IDs  : " . implode(', ', $bulk['msg-ids']) . "\n";

if (!empty($bulk['errors'])) {
    echo "\nBatch errors:\n";
    foreach ($bulk['errors'] as $err) {
        echo "  Batch {$err['batch']}: [{$err['code']}] {$err['description']}\n";
    }
}

// ── Example 3: Mixed list (some valid, some invalid) ──────────────────────────
//
// Invalid numbers are rejected locally before any API call.
// The result includes both successful sends and the rejected list.

echo "\n" . str_repeat('─', 60) . "\n\n";

$mixed = [
    '96598765432',      // ✅ Valid Kuwait number
    'admin@test.com',   // ❌ Email address (rejected locally)
    '+1 800 555 0199',  // ✅ Valid US number
    '123',              // ❌ Too short (rejected locally)
    '96512345678',      // ✅ Valid Kuwait number
];

echo "Sending to mixed list (" . count($mixed) . " entries)...\n\n";

$mixed_result = $sms->send($mixed, 'Testing mixed number list.');

echo "Result: {$mixed_result['result']}\n";

if (!empty($mixed_result['invalid'])) {
    echo "\nRejected numbers:\n";
    foreach ($mixed_result['invalid'] as $inv) {
        echo "  ✗ {$inv['number']}: {$inv['error']}\n";
    }
}
