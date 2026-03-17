<?php

namespace KwtSMS;

class MessageUtils
{
    /**
     * Hidden control characters explicitly listed in the PRD.
     * U+200B (Zero-width space)
     * U+200C (Zero-width non-joiner)
     * U+200D (Zero-width joiner)
     * U+2060 (Word joiner)
     * U+00AD (Soft hyphen)
     * U+FEFF (Byte order mark)
     * U+FFFC (Object replacement character)
     * @var array<string, string>
     */
    private const HIDDEN_CHARS = [
        "\xE2\x80\x8B" => '',
        "\xE2\x80\x8C" => '',
        "\xE2\x80\x8D" => '',
        "\xE2\x81\xA0" => '',
        "\xC2\xAD" => '',
        "\xEF\xBB\xBF" => '',
        "\xEF\xBF\xBC" => '',
    ];

    /**
     * Clean a message by stripping emojis, bad Unicode, and converting numerals.
     *
     * @param string $text
     * @return string
     */
    public static function clean_message(string $text): string
    {
        // 0. Strip any invalid UTF-8 byte sequences before applying /u patterns.
        //    Uses PCRE (*SKIP)(*FAIL) to preserve valid 1-4 byte sequences and
        //    strip any remaining high bytes that do not form valid UTF-8.
        //    Runs without the /u flag so it safely handles malformed input.
        $text = (string) preg_replace(
            '/[\x00-\x7F](*SKIP)(*FAIL)'
            . '|[\xC2-\xDF][\x80-\xBF](*SKIP)(*FAIL)'
            . '|[\xE0-\xEF][\x80-\xBF]{2}(*SKIP)(*FAIL)'
            . '|[\xF0-\xF7][\x80-\xBF]{3}(*SKIP)(*FAIL)'
            . '|[\x80-\xFF]/s',
            '',
            $text
        );

        // 1. Strip HTML tags
        $text = strip_tags($text);

        // 2. Convert Arabic/Hindi numerals to Latin digits (shared map from PhoneUtils)
        $text = strtr($text, PhoneUtils::DIGITS_MAP);

        // 3. Strip problematic hidden control characters exactly matching the PRD
        $text = strtr($text, self::HIDDEN_CHARS);

        // 4. Remove emojis and pictographic symbols across all emoji Unicode blocks.
        // PHP pattern modifiers:
        // /u = utf-8 execution
        //
        // Ranges covered:
        // 0x1F000–0x1FAFF  Full emoji supplementary range (extended from 0x1F300):
        //   0x1F000–0x1F02F  Mahjong tiles (e.g. 🀄)
        //   0x1F030–0x1F09F  Domino tiles
        //   0x1F0A0–0x1F0FF  Playing cards
        //   0x1F1E0–0x1F1FF  Regional Indicator Symbols — country flag pairs (e.g. 🇺🇸)
        //   0x1F300–0x1FAFF  Emoticons, symbols, pictographs (previously sole range)
        // 0x20E3            Combining Enclosing Keycap (used in 1️⃣, 2️⃣, etc.)
        // 0x2600–0x27BF     Miscellaneous symbols, dingbats
        // 0xFE00–0xFE0F     Variation selectors (emoji/text presentation)
        // 0xE0000–0xE007F   Tags block (used in subdivision flag sequences, e.g. 🏴󠁧󠁢󠁳󠁣󠁴󠁿)
        $text = preg_replace(
            '/[\x{1F000}-\x{1FAFF}\x{20E3}\x{E0000}-\x{E007F}\x{2600}-\x{27BF}\x{FE00}-\x{FE0F}]/u',
            '',
            $text
        ) ?? $text;

        // 5. Remove remaining control characters EXCEPT \n, \r, and \t
        // We do this by targeting ASCII control chars directly (0x00 to 0x1F, except 0x09, 0x0A, 0x0D)
        // and 0x7F (Delete).
        $text = preg_replace('/[\x{00}-\x{08}\x{0B}\x{0C}\x{0E}-\x{1F}\x{7F}]/u', '', $text) ?? $text;

        return $text;
    }
}
