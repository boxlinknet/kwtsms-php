<?php

namespace KwtSMS\Tests;

use KwtSMS\PhoneUtils;
use PHPUnit\Framework\TestCase;

class PhoneUtilsTest extends TestCase
{
    public function testNormalizePhone(): void
    {
        $this->assertSame('96598765432', PhoneUtils::normalize_phone('+965 9876-5432'));
        $this->assertSame('96598765432', PhoneUtils::normalize_phone('0096598765432'));
        $this->assertSame('96598765432', PhoneUtils::normalize_phone('096598765432')); // Strips leading ones

        // Arabic numbering
        $this->assertSame('96598765432', PhoneUtils::normalize_phone('٩٦٥٩٨٧٦٥٤٣٢'));
        $this->assertSame('96598765432', PhoneUtils::normalize_phone('۹۶۵۹۸۷۶۵۴۳۲'));

        // Empty string
        $this->assertSame('', PhoneUtils::normalize_phone(''));
        $this->assertSame('', PhoneUtils::normalize_phone('abc@xyz.com'));
    }

    public function testValidatePhoneInput(): void
    {
        // Missing
        [$valid, $err, $norm] = PhoneUtils::validate_phone_input('');
        $this->assertFalse($valid);
        $this->assertStringContainsString('required', $err);

        // Blank
        [$valid, $err, $norm] = PhoneUtils::validate_phone_input('   ');
        $this->assertFalse($valid);
        $this->assertStringContainsString('required', $err);

        // Email block
        [$valid, $err, $norm] = PhoneUtils::validate_phone_input('admin@test.com');
        $this->assertFalse($valid);
        $this->assertStringContainsString('email address', $err);

        // No digits block
        [$valid, $err, $norm] = PhoneUtils::validate_phone_input('call me');
        $this->assertFalse($valid);
        $this->assertStringContainsString('no digits found', $err);

        // Too short
        [$valid, $err, $norm] = PhoneUtils::validate_phone_input('123456'); // 6 chars
        $this->assertFalse($valid);
        $this->assertStringContainsString('too short', $err);

        // Min length (7)
        [$valid, $err, $norm] = PhoneUtils::validate_phone_input('1234567');
        $this->assertTrue($valid);
        $this->assertNull($err);

        // Too long
        [$valid, $err, $norm] = PhoneUtils::validate_phone_input('1234567890123456'); // 16 chars
        $this->assertFalse($valid);
        $this->assertStringContainsString('too long', $err);

        // Max length (15)
        [$valid, $err, $norm] = PhoneUtils::validate_phone_input('123456789012345');
        $this->assertTrue($valid);
        $this->assertNull($err);

        // Valid Arabic
        [$valid, $err, $norm] = PhoneUtils::validate_phone_input('+٩٦٥ ٩٨٧٦٥٤٣٢');
        $this->assertTrue($valid);
        $this->assertSame('96598765432', $norm);
    }
}
