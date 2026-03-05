# Changelog

All notable changes to `kwtsms/kwtsms` are documented in this file.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).
This project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

---

## [Unreleased]

---

## [1.2.0] 2026-03-05

### Added
- `KwtSMS::status(string $msgId)`: check the queue/dispatch status of a sent message.
  Returns `status` and `description` on OK. Handles ERR029 (not found) and ERR030
  (stuck in queue with credits recoverable from dashboard).
- `KwtSMS::dlr(string $msgId)`: retrieve delivery reports for international sends.
  Returns a `report` array of `{Number, Status}` entries. Only available for
  non-Kuwait numbers; wait at least 5 minutes after send before calling.
- GitHub Actions CI: automated test matrix across PHP 7.4, 8.1, 8.2, and 8.3
  on every push and pull request.
- `SECURITY.md`: private vulnerability reporting policy via GitHub Security Advisories.
- README badges: Packagist version, total downloads, PHP version, license, CI status.

### Changed
- Test count: 63 → 71 tests, 220 → 246 assertions.

---

## [1.1.0] 2026-03-05

### Added
- `bin/kwtsms` CLI tool: send SMS and verify credentials from the terminal.
  Supports `send` (with `--phone`, `--message`, `--sender`) and `verify` commands.
  Reads credentials from `.env` or shell environment.
- `examples/09-otp-production.php`: complete drop-in production OTP implementation
  with three storage adapters (SQLite, MySQL, Redis), two CAPTCHA providers
  (Cloudflare Turnstile, hCaptcha), per-phone and per-IP rate limiting,
  HMAC-SHA256 code hashing, brute-force protection (attempt increment before
  comparison), replay protection via `markUsed()`, and IP spoofing guard.
- `examples/docs/`: per-example documentation files linked from a restructured
  `examples/README.md` index; shared reference doc covering phone formats, Sender
  ID types, error code table, and pre-launch checklist.

### Changed
- `MessageUtils::clean_message()`: extended emoji stripping to cover country flags
  (Regional Indicator Symbols U+1F1E0–U+1F1FF), mahjong tiles (U+1F000–U+1F02F),
  domino tiles, playing cards, keycap combiner (U+20E3, used in 1️⃣ 2️⃣ etc.),
  and the tags block (U+E0000–U+E007F, used in subdivision flags such as 🏴󠁧󠁢󠁳󠁣󠁴󠁿).
- `KwtSMS::send()`: ERR009 is now returned locally before any API call when the
  message is empty or becomes empty after cleaning (emoji-only or HTML-only input).
  The error description distinguishes the two cases with an actionable message.
  No credits are consumed and no network request is made.
- `KwtSMS::__construct()`: embedded newlines (`\r`, `\n`) are stripped from
  `$username` and `$password` to prevent env-injection if credentials are ever
  written back to a `.env` file or a log entry.
- `KwtSMS::from_env()`: embedded newlines are also stripped from all values
  parsed from the `.env` file (belt-and-suspenders for file-based parsing).
- `composer.lock` removed from version control. Library convention: consumers
  use their own lock files and Composer ignores the library lock file on install.
- `.gitignore`: added OS files (`Thumbs.db`, `Desktop.ini`), editor swap files
  (`*.swp`, `*.swo`), `.claude/`, and `/.github/`.

### Fixed
- `--sender` flag in `bin/kwtsms` correctly errors if no value is provided,
  preventing the next flag from being silently consumed as the sender ID.

---

## [1.0.0] 2026-03-05

### Added
- `KwtSMS` main client class: zero external dependencies, `ext-curl` and `ext-json` only.
- `KwtSMS::from_env()`: load credentials from environment variables or `.env` file;
  supports quoted values, inline comments, and precedence over system env vars.
- `KwtSMS::verify()`: test credentials and retrieve current balance.
- `KwtSMS::balance()`: fetch current credit balance.
- `KwtSMS::purchased()`: return total credits purchased, cached from last balance call.
- `KwtSMS::send()`: send SMS to one or more recipients with automatic:
  - Phone number normalization (strips `+`, `00`, spaces, dashes; converts Arabic/Hindi digits)
  - Deduplication (same normalized number charged and dispatched only once)
  - Message cleaning via `MessageUtils::clean_message()`
  - Batch splitting for >200 numbers (200/batch, 0.5s inter-batch delay)
  - ERR013 (queue full) retry with exponential backoff: 30s, 60s, 120s, max 4 attempts
- `KwtSMS::validate()`: local batch phone validation using `PhoneUtils`; no API call.
- `KwtSMS::senderids()`: list Sender IDs registered on the account.
- `KwtSMS::coverage()`: list active country coverage prefixes.
- `PhoneUtils::normalize_phone()`: normalize a phone number string.
- `PhoneUtils::validate_phone_input()`: validate a phone number with detailed error messages.
- `PhoneUtils::DIGITS_MAP`: public constant mapping Arabic-Indic and Extended Arabic-Indic
  digits to Latin; shared with `MessageUtils`.
- `MessageUtils::clean_message()`: strip HTML, emojis, hidden Unicode control characters,
  and convert Arabic/Hindi digits to Latin.
- `ApiErrors::ERRORS`: all 33 kwtSMS error codes mapped to developer descriptions and actions.
- `ApiErrors::enrichError()`: enriches any API response with the `action` field.
- NDJSON request logging with automatic password masking; path traversal guard on log path;
  logging never throws (failures silently ignored).
- PHP 7.4+ compatibility.
- PSR-4 autoloading under the `KwtSMS\` namespace.
- PHPUnit test suite: 63 tests, 220 assertions across four test files.
  - `PhoneUtilsTest.php`: unit tests for phone normalization and validation.
  - `MessageUtilsTest.php`: unit tests for message cleaning (including emoji ranges).
  - `ApiErrorsTest.php`: mocked API tests for all error code mappings.
  - `KwtSMSClientTest.php`: mocked API tests for send, validate, bulk, verify flows.
- `IntegrationTest.php`: live API tests (auto-skipped without credentials).
- `phpstan/phpstan`: static analysis.
- `friendsofphp/php-cs-fixer`: PSR-12 code style enforcement.
- MIT License.
- `README.md`, `CHANGELOG.md`, `CONTRIBUTING.md`, `.env.example`.
- 9 examples covering: quickstart, OTP, bulk SMS, validation, error handling,
  message cleaning, Laravel integration, WordPress integration, production OTP flow.

---

## Version History

| Version | Date | PHP | Notable Change |
|---------|------|-----|----------------|
| 1.2.0 | 2026-03-05 | 7.4+ | status(), dlr(), CI, badges, SECURITY.md |
| 1.1.0 | 2026-03-05 | 7.4+ | CLI tool, production OTP example, extended emoji ranges |
| 1.0.0 | 2026-03-05 | 7.4+ | Initial release |

---

[Unreleased]: https://github.com/boxlinknet/kwtsms-php/compare/v1.2.0...HEAD
[1.2.0]: https://github.com/boxlinknet/kwtsms-php/compare/v1.1.0...v1.2.0
[1.1.0]: https://github.com/boxlinknet/kwtsms-php/compare/v1.0.0...v1.1.0
[1.0.0]: https://github.com/boxlinknet/kwtsms-php/releases/tag/v1.0.0
