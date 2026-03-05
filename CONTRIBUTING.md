# Contributing to kwtsms/kwtsms

Thank you for taking the time to contribute! This document explains how to report bugs, propose changes, and submit pull requests.

---

## Table of Contents

- [Code of Conduct](#code-of-conduct)
- [Getting Started](#getting-started)
- [Reporting Bugs](#reporting-bugs)
- [Suggesting Features](#suggesting-features)
- [Development Setup](#development-setup)
- [Coding Standards](#coding-standards)
- [Running Tests](#running-tests)
- [Submitting a Pull Request](#submitting-a-pull-request)
- [Versioning](#versioning)

---

## Code of Conduct

Be respectful, constructive, and professional. We welcome contributions from everyone regardless of experience level.

---

## Getting Started

1. **Search first** — Check [existing issues](https://github.com/boxlinknet/kwtsms-php/issues) and [open PRs](https://github.com/boxlinknet/kwtsms-php/pulls) before opening a new one.
2. **Small PRs win** — Focused, single-purpose pull requests are reviewed faster and are more likely to be merged.
3. **Tests are required** — All changes to `src/` must be accompanied by tests.

---

## Reporting Bugs

Please open an issue at [github.com/boxlinknet/kwtsms-php/issues/new](https://github.com/boxlinknet/kwtsms-php/issues/new) and include:

- **PHP version** (`php -v`)
- **Library version** (`composer show kwtsms/kwtsms`)
- **kwtSMS API error code and description** (if applicable)
- **Minimal reproducible example** — the smallest code snippet that shows the problem
- **Expected behaviour** vs **actual behaviour**

> ⚠️ **Never post your API credentials in an issue.** Mask usernames and passwords with `***`.

---

## Suggesting Features

Open a [GitHub Discussion](https://github.com/boxlinknet/kwtsms-php/discussions) or an issue tagged `enhancement`. Describe:

- The problem you are trying to solve
- Your proposed solution
- Any alternatives you considered

Feature requests that add external dependencies will not be accepted — zero-dependency is a hard requirement of this library.

---

## Development Setup

```bash
# 1. Fork the repository on GitHub, then clone your fork
git clone https://github.com/YOUR-USERNAME/kwtsms-php.git
cd kwtsms-php

# 2. Install dev dependencies
composer install

# 3. Set up credentials for integration tests (Tier 3)
cp .env.example .env   # or create .env manually
# Edit .env and add your kwtSMS test credentials:
#   KWTSMS_USERNAME=your_api_username
#   KWTSMS_PASSWORD=your_api_password
#   KWTSMS_TEST_MODE=1
```

Create a `.env.example` template (do not put real credentials there):
```ini
KWTSMS_USERNAME=
KWTSMS_PASSWORD=
KWTSMS_SENDER_ID=KWT-SMS
KWTSMS_TEST_MODE=1
KWTSMS_LOG_FILE=kwtsms.log
```

---

## Coding Standards

This project follows [PSR-12](https://www.php-fig.org/psr/psr-12/) coding style, enforced by PHP-CS-Fixer.

```bash
# Check for style violations
composer cs-check

# Auto-fix style issues
composer cs-fix
```

Additional conventions:
- **No external dependencies** in `src/` — the library must work with only `ext-curl` and `ext-json`
- **PHP 7.4+ compatibility** — do not use syntax or functions introduced after PHP 7.4
- **Type hints everywhere** — all public and protected methods must have parameter and return types
- **PHPDoc on all public methods** — include `@param`, `@return`, and `@throws` where applicable
- **Array shapes documented** — use `@return array{key: type, ...}` for structured returns
- **No silent failures** — every error condition must be returned or logged, never swallowed

---

## Running Tests

```bash
# Run all tests (unit + mocked API)
composer test

# Run only unit tests (no API credentials needed)
vendor/bin/phpunit tests/PhoneUtilsTest.php tests/MessageUtilsTest.php tests/ApiErrorsTest.php

# Run integration tests (requires real credentials in .env)
vendor/bin/phpunit tests/IntegrationTest.php

# Run static analysis
composer analyse
```

### Test Tiers

| Tier | File | Needs credentials |
|------|------|-------------------|
| 1 — Unit | `PhoneUtilsTest.php`, `MessageUtilsTest.php` | No |
| 2 — Mocked API | `ApiErrorsTest.php` | No |
| 3 — Integration | `IntegrationTest.php` | Yes (`.env`) |

Integration tests use `KWTSMS_TEST_MODE=1` — no real messages are sent and no credits consumed.

---

## Submitting a Pull Request

### Step 1: Create a branch

```bash
git checkout -b fix/description-of-fix
# or
git checkout -b feat/description-of-feature
```

Branch naming conventions:
- `fix/` — bug fixes
- `feat/` — new features
- `docs/` — documentation-only changes
- `test/` — test improvements
- `refactor/` — code restructuring without behaviour change

### Step 2: Make your changes

- Keep changes focused on a single concern
- Update or add tests for any changed behaviour
- Update `CHANGELOG.md` under `[Unreleased]`
- Run the full test suite and ensure it passes

```bash
composer test
composer analyse
composer cs-fix
```

### Step 3: Commit with a clear message

Follow the [Conventional Commits](https://www.conventionalcommits.org/) format:

```
type(scope): short summary in present tense

Optional longer description explaining WHY the change was made,
not WHAT (the code shows what).

Fixes #123
```

Examples:
```
fix(message-utils): expand emoji Unicode range to include 0x1F300–0x1F5FF
feat(phone-utils): validate E.164 format more strictly
docs(readme): add Laravel Octane usage note
test(integration): add testSendWithArabicMessage case
```

### Step 4: Push and open a PR

```bash
git push origin fix/your-branch-name
```

Then open a PR at [github.com/boxlinknet/kwtsms-php/pulls](https://github.com/boxlinknet/kwtsms-php/pulls).

**PR checklist:**
- [ ] Tests pass locally (`composer test`)
- [ ] Static analysis passes (`composer analyse`)
- [ ] Code style fixed (`composer cs-fix`)
- [ ] `CHANGELOG.md` updated under `[Unreleased]`
- [ ] No new external dependencies added to `src/`
- [ ] PHP 7.4 compatible (no PHP 8.0+ syntax in `src/`)
- [ ] PR description explains the problem and the solution

---

## Versioning

This project follows [Semantic Versioning](https://semver.org/):

| Change type | Version bump | Example |
|---|---|---|
| Backwards-compatible bug fix | Patch | `1.0.0` → `1.0.1` |
| Backwards-compatible new feature | Minor | `1.0.0` → `1.1.0` |
| Breaking change | Major | `1.0.0` → `2.0.0` |

Maintainers handle version tagging and Packagist releases.

---

## Questions?

Open a [GitHub Discussion](https://github.com/boxlinknet/kwtsms-php/discussions) for general questions. For kwtSMS API questions (not library bugs), contact [kwtSMS support](https://www.kwtsms.com/support.html).
