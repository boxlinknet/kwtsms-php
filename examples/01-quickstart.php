<?php

/**
 * Example 01: Quick Start
 * -----------------------
 * The fastest path to sending your first SMS with kwtSMS.
 *
 * Requirements:
 *   - composer require kwtsms/kwtsms
 *   - .env file with KWTSMS_USERNAME and KWTSMS_PASSWORD
 *
 * Run:
 *   php examples/01-quickstart.php
 */

require __DIR__ . '/../vendor/autoload.php';

use KwtSMS\KwtSMS;

// ── Step 1: Create the client ────────────────────────────────────────────────
//
// KwtSMS::from_env() loads credentials from environment variables or a .env file.
// Required: KWTSMS_USERNAME, KWTSMS_PASSWORD
// Optional: KWTSMS_SENDER_ID, KWTSMS_TEST_MODE, KWTSMS_LOG_FILE
//
// Your .env file should look like this:
//
//   KWTSMS_USERNAME=your_api_username
//   KWTSMS_PASSWORD=your_api_password
//   KWTSMS_SENDER_ID=MY-BRAND
//   KWTSMS_TEST_MODE=1           <- set to 0 when going live
//   KWTSMS_LOG_FILE=kwtsms.log

$sms = KwtSMS::from_env();

// ── Step 2: Verify your credentials ─────────────────────────────────────────
//
// Always verify before going live. Returns your current balance.

[$ok, $balance, $error] = $sms->verify();

if (!$ok) {
    echo "❌ Connection failed: {$error}\n";
    exit(1);
}

echo "✅ Connected! Balance: {$balance} credits\n\n";

// ── Step 3: Send a single SMS ────────────────────────────────────────────────
//
// Numbers are normalized automatically:
//   "+965 9876-5432" → "96598765432"
//   "0096598765432"  → "96598765432"
//   "٩٦٥٩٨٧٦٥٤٣٢"   → "96598765432" (Arabic digits)

$result = $sms->send('96598765432', 'Hello from kwtSMS! Your first message is working.');

if ($result['result'] === 'OK') {
    echo "✅ SMS sent successfully!\n";
    echo "   Message ID : {$result['msg-id']}\n";
    echo "   Credits used: {$result['points-charged']}\n";
    echo "   Balance after: {$result['balance-after']}\n";
} else {
    echo "❌ Failed to send: {$result['description']}\n";
    echo "   Action: {$result['action']}\n";
}
