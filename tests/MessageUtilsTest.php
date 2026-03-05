<?php

namespace KwtSMS\Tests;

use KwtSMS\MessageUtils;
use PHPUnit\Framework\TestCase;

class MessageUtilsTest extends TestCase
{
    public function testCleanMessage(): void
    {
        // Html handling
        $this->assertSame('No HTML allowed.', MessageUtils::clean_message('<b>No HTML allowed.</b>'));

        // Numerals
        $this->assertSame('Amount: 1234', MessageUtils::clean_message('Amount: ١٢٣٤'));
        $this->assertSame('1234', MessageUtils::clean_message('۱۲۳۴'));

        // Emojis stripped
        $this->assertSame('Hello ', MessageUtils::clean_message('Hello 😀'));
        $this->assertSame('Cool test', MessageUtils::clean_message('Cool test🔥'));
        $this->assertSame('Unicode block', MessageUtils::clean_message('Unicode block👩‍🚀'));

        // Control characters stripped
        // \xE2\x80\x8B is the Zero-width space
        $this->assertSame('Clean', MessageUtils::clean_message("C\xE2\x80\x8Blean"));
        // \xC2\xAD is the soft hyphen
        $this->assertSame('Hyphenate', MessageUtils::clean_message("Hyphen\xC2\xADate"));

        // Newlines, CR, Tabs preserved
        $this->assertSame("A\nB", MessageUtils::clean_message("A\nB"));
        $this->assertSame("A\tB", MessageUtils::clean_message("A\tB"));
        $this->assertSame("A\r\nB", MessageUtils::clean_message("A\r\nB"));

        // Arabic text itself must NOT be stripped
        $this->assertSame('مرحبا بالعالم', MessageUtils::clean_message('مرحبا بالعالم'));
    }
}
