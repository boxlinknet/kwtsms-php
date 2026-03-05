<?php

/**
 * Example 04: Phone Number Validation
 * ------------------------------------
 * Validate and normalize phone numbers before sending — either locally
 * (instant, no API call) or against the kwtSMS routing database (checks
 * carrier support and country activation on your account).
 *
 * Use cases:
 *   - Clean an imported list of numbers before a campaign
 *   - Validate user input on a sign-up form
 *   - Check if a country is routable before attempting to send
 *
 * Run:
 *   php examples/04-validation.php
 */

require __DIR__ . '/../vendor/autoload.php';

use KwtSMS\KwtSMS;
use KwtSMS\PhoneUtils;

// ── Local validation (no API call, instant) ──────────────────────────────────

echo "═══ LOCAL VALIDATION (no API call) ═══\n\n";

$numbers_to_test = [
    '+965 9876 5432',       // Valid Kuwait
    '0096512345678',        // Valid Kuwait (00 prefix)
    '٩٦٥٩٨٧٦٥٤٣٢',         // Arabic digits — normalized automatically
    'admin@example.com',    // Email — rejected
    '123',                  // Too short — rejected
    '1234567890123456',     // Too long (16 digits) — rejected
    'call me',              // No digits — rejected
    '+1 800 555 0199',      // Valid US
    '  96598765432  ',      // Extra whitespace — trimmed and valid
];

foreach ($numbers_to_test as $number) {
    [$valid, $error, $normalized] = PhoneUtils::validate_phone_input($number);

    $display = str_pad("'{$number}'", 30);
    if ($valid) {
        echo "  ✅ {$display} → {$normalized}\n";
    } else {
        echo "  ❌ {$display} → {$error}\n";
    }
}

// ── Bulk local validation ────────────────────────────────────────────────────

echo "\n═══ BULK LOCAL VALIDATION ═══\n\n";

$sms = KwtSMS::from_env();

$bulk_list = [
    '96598765432',
    '96512345678',
    'not-a-number',
    '+44 20 7946 0958', // UK
    '00201234567890',   // Egypt (with 00 prefix)
];

$report = $sms->validate($bulk_list);

echo "Total   : {$report['nr']}\n";
echo "Valid   : {$report['ok']}\n";
echo "Invalid : {$report['er']}\n";

if (!empty($report['rejected'])) {
    echo "\nRejected:\n";
    foreach ($report['rejected'] as $r) {
        echo "  ✗ {$r['number']}: {$r['error']}\n";
    }
}

echo "\nFull validation details:\n";
foreach ($report['raw'] as $entry) {
    $status = $entry['valid'] ? '✅' : '❌';
    $normalized = $entry['valid'] ? " → {$entry['normalized']}" : '';
    echo "  {$status} {$entry['phone']}{$normalized}\n";
}

// ── Normalization examples ────────────────────────────────────────────────────

echo "\n═══ NORMALIZATION EXAMPLES ═══\n\n";

$raw_inputs = [
    '+965 9876-5432' => '96598765432',
    '0096598765432' => '96598765432',
    '٩٦٥٩٨٧٦٥٤٣٢' => '96598765432',
    '۹۶۵۹۸۷۶۵۴۳۲' => '96598765432',
    '(965) 9876 5432' => '96598765432',
];

foreach ($raw_inputs as $raw => $expected) {
    $normalized = PhoneUtils::normalize_phone($raw);
    $match = $normalized === $expected ? '✅' : '❌';
    echo "  {$match} '{$raw}' → '{$normalized}'\n";
}
