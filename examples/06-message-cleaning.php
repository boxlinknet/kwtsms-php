<?php

/**
 * Example 06: Message Cleaning
 * -----------------------------
 * Demonstrates the automatic message sanitization that runs on every send().
 *
 * Why this matters:
 *   Messages with emojis, hidden Unicode characters, or HTML tags get stuck
 *   in the kwtSMS queue and are never delivered — but credits ARE consumed.
 *   The clean_message() function prevents this silently on every send() call.
 *
 * You don't need to call this manually — send() does it for you.
 * This example is for educational purposes and manual pre-flight checks.
 *
 * Run:
 *   php examples/06-message-cleaning.php
 */

require __DIR__ . '/../vendor/autoload.php';

use KwtSMS\MessageUtils;

$examples = [
    // Emojis (cause silent queue lock)
    'Hello! 👋 Your order is ready 📦' => 'Hello!  Your order is ready ',
    'Flash sale 🔥 50% off today only!' => 'Flash sale  50% off today only!',
    'Meeting confirmed ✅' => 'Meeting confirmed ',

    // HTML (causes ERR027 from API)
    '<b>Important:</b> Your account is <i>active</i>.' => 'Important: Your account is active.',
    '<p>Click <a href="#">here</a> to confirm.</p>' => 'Click here to confirm.',

    // Arabic/Persian digits — converted to Latin
    'رصيدك: ٢٥٠ دينار' => 'رصيدك: 250 دينار',
    'كود التحقق: ١٢٣٤٥٦' => 'كود التحقق: 123456',

    // Zero-width spaces (from copy-pasting from Word or WhatsApp)
    "Hello\u{200B}World" => 'HelloWorld',
    "Clean\u{FEFF}Text" => 'CleanText', // BOM character

    // Soft hyphens (invisible, from PDF copy-paste)
    "Hyphen\u{00AD}ate" => 'Hyphenate',

    // Arabic text preserved perfectly
    'مرحباً بكم في خدمة الرسائل النصية' => 'مرحباً بكم في خدمة الرسائل النصية',
    'عزيزي العميل، طلبك جاهز للاستلام' => 'عزيزي العميل، طلبك جاهز للاستلام',

    // Newlines and tabs preserved (SMS supports them)
    "Line 1\nLine 2\nLine 3" => "Line 1\nLine 2\nLine 3",
    "Column1\tColumn2" => "Column1\tColumn2",

    // Mixed: emoji + Arabic + hidden chars
    "طلبك 📦 رقم ١٢٣\u{200B} جاهز!" => 'طلبك  رقم 123 جاهز!',
];

echo "═══ MESSAGE CLEANING EXAMPLES ═══\n\n";

$pass = 0;
$fail = 0;

foreach ($examples as $input => $expected) {
    $output = MessageUtils::clean_message($input);
    $ok = $output === $expected;
    $ok ? $pass++ : $fail++;

    $icon = $ok ? '✅' : '❌';
    echo "{$icon} Input   : " . json_encode($input, JSON_UNESCAPED_UNICODE) . "\n";
    if (!$ok) {
        echo "   Expected: " . json_encode($expected, JSON_UNESCAPED_UNICODE) . "\n";
        echo "   Got     : " . json_encode($output, JSON_UNESCAPED_UNICODE) . "\n";
    } else {
        echo "   Output  : " . json_encode($output, JSON_UNESCAPED_UNICODE) . "\n";
    }
    echo "\n";
}

echo "─────────────────────────────────\n";
echo "Passed: {$pass} / " . count($examples) . "\n";
if ($fail > 0) {
    echo "Failed: {$fail}\n";
}
