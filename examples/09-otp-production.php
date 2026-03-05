<?php

/**
 * Example 09: Production OTP Flow — Complete Reference Implementation
 * ─────────────────────────────────────────────────────────────────────
 *
 * Drop-in OTP implementation for PHP applications.
 * Covers every production requirement out of the box:
 *   - Phone format validation (local, before any API call)
 *   - CAPTCHA verification (Cloudflare Turnstile or hCaptcha)
 *   - Rate limiting per phone AND per IP
 *   - Secure OTP storage (HMAC-SHA256, never plaintext)
 *   - OTP expiry (5 minutes, configurable)
 *   - Brute-force protection (5 max attempts)
 *   - User-safe error messages (no raw API codes exposed)
 *   - msg-id + balance-after saved from every send response
 *   - Three DB adapters: SQLite, MySQL/MariaDB, Redis
 *
 * ╔══════════════════════════════════════════════════════════════════════╗
 * ║                     QUICK-START CHECKLIST                           ║
 * ╠══════════════════════════════════════════════════════════════════════╣
 * ║  1. Register a TRANSACTIONAL SenderID on kwtsms.com (not KWT-SMS)  ║
 * ║     OTP to DND numbers is silently blocked on Promotional IDs.     ║
 * ║     kwtsms.com → Buy SenderID → Transactional (15 KD, ~5 days)    ║
 * ║  2. Add env vars: KWTSMS_USERNAME, KWTSMS_PASSWORD, KWTSMS_SENDER  ║
 * ║  3. Generate APP_SECRET: php -r "echo bin2hex(random_bytes(32));"  ║
 * ║  4. Pick storage: STORAGE_DRIVER=sqlite|mysql|redis                ║
 * ║  5. Pick captcha: CAPTCHA_PROVIDER=turnstile|hcaptcha              ║
 * ║  6. Add captcha frontend snippet (see end of file)                 ║
 * ║  7. Set KWTSMS_TEST_MODE=0 before going live                       ║
 * ╚══════════════════════════════════════════════════════════════════════╝
 *
 * SENDER ID — CRITICAL FOR OTP
 * ─────────────────────────────
 * You MUST use a Transactional (not Promotional) SenderID for OTP.
 *   - Promotional IDs (including KWT-SMS) are filtered on DND numbers.
 *     Credits are still deducted — message just never arrives.
 *   - Transactional IDs bypass DND (whitelisted like banks/telecoms).
 *   - KWT-SMS may also cause delivery delays and is blocked on Virgin Kuwait.
 *
 * DATABASE OPTIONS
 * ─────────────────
 * Option A — SQLite:  Zero config, file-based. Good for dev + small apps.
 *   Requires pdo_sqlite (standard on most PHP installs).
 *
 * Option B — MySQL/MariaDB:  Standard production relational DB.
 *   Requires pdo_mysql. Use raw PDO or your framework ORM (Eloquent, Doctrine).
 *   Note: Drizzle ORM is TypeScript/JavaScript only — not usable in PHP.
 *
 * Option C — Redis:  Fastest, native TTL, ideal for multi-server deployments.
 *   Requires phpredis extension: sudo apt install php-redis
 *   OR Predis package: composer require predis/predis
 *
 * CAPTCHA OPTIONS
 * ────────────────
 * Option 1 — Cloudflare Turnstile (recommended):
 *   Privacy-first, zero-friction. Free for all volumes.
 *   Setup: dash.cloudflare.com → Turnstile → Add site
 *   Add TURNSTILE_SECRET to .env
 *
 * Option 2 — hCaptcha:
 *   GDPR-compliant reCAPTCHA alternative. Free tier available.
 *   Setup: dashboard.hcaptcha.com → New Site
 *   Add HCAPTCHA_SECRET to .env
 *
 * OTP SECURITY DESIGN
 * ────────────────────
 * - Codes stored as HMAC-SHA256(phone:code, app_secret). DB dump reveals nothing.
 * - Comparison uses hash_equals() — constant-time, timing-attack safe.
 * - Attempts incremented BEFORE comparison — prevents timing oracle.
 * - After MAX_ATTEMPTS: code invalidated even if never guessed correctly.
 * - On success: code immediately marked used (cannot replay).
 *
 * RATE LIMITING
 * ──────────────
 * Per phone: max 3 sends per 10 minutes  (prevents SMS bombing one number)
 * Per IP:    max 10 sends per hour        (prevents scanning / bulk abuse)
 *
 * Run (CLI demo):
 *   php examples/09-otp-production.php
 *
 * HTTP integration:
 *   POST /otp/send   — body: {"phone":"96598765432","captcha_token":"..."}
 *   POST /otp/verify — body: {"phone":"96598765432","code":"123456"}
 */

require __DIR__ . '/../vendor/autoload.php';

use KwtSMS\KwtSMS;
use KwtSMS\PhoneUtils;
use KwtSMS\MessageUtils;

// ┌─────────────────────────────────────────────────────────────────────────┐
// │ SECTION 1: CONFIGURATION                                                │
// └─────────────────────────────────────────────────────────────────────────┘

const OTP_TTL_SECONDS     = 300;  // 5 minutes — standard OTP expiry
const OTP_DIGITS          = 6;    // 6-digit code
const OTP_MAX_ATTEMPTS    = 5;    // lock out after 5 wrong guesses
const RATE_PHONE_MAX      = 3;    // max sends per phone per RATE_PHONE_WINDOW
const RATE_PHONE_WINDOW   = 600;  // 10-minute sliding window for per-phone limit
const RATE_IP_MAX         = 10;   // max sends per IP per RATE_IP_WINDOW
const RATE_IP_WINDOW      = 3600; // 1-hour sliding window for per-IP limit

// ┌─────────────────────────────────────────────────────────────────────────┐
// │ SECTION 2: INTERFACES                                                   │
// └─────────────────────────────────────────────────────────────────────────┘

/**
 * Contract all storage backends must implement.
 * Create one instance per request; open the DB connection in the constructor.
 */
interface OtpStorageAdapter
{
    /**
     * Persist a newly generated OTP.
     *
     * @param string     $phone        Normalized phone (digits only, no leading zeros)
     * @param string     $codeHash     HMAC-SHA256 of the code — never store plaintext
     * @param int        $expiresAt    Unix timestamp when the code expires
     * @param string     $msgId        kwtSMS msg-id for audit trail
     * @param float|null $balanceAfter kwtSMS balance after charge
     */
    public function store(
        string $phone,
        string $codeHash,
        int $expiresAt,
        string $msgId,
        ?float $balanceAfter
    ): void;

    /**
     * Fetch the active OTP record for this phone.
     * Returns null if no valid (unused, unexpired) record exists.
     *
     * @return array{code_hash: string, expires_at: int, attempts: int}|null
     */
    public function fetch(string $phone): ?array;

    /**
     * Atomically increment the attempt counter. Returns the new count.
     * Always call this BEFORE comparing the submitted code.
     */
    public function incrementAttempts(string $phone): int;

    /** Mark the OTP as consumed so it cannot be reused (replay protection). */
    public function markUsed(string $phone): void;

    /**
     * Count send events within the rate-limit window.
     *
     * @param string $rlKey         e.g. "phone:96598765432" or "ip:192.168.1.1"
     * @param int    $windowSeconds Rolling window size in seconds
     */
    public function countRecentSends(string $rlKey, int $windowSeconds): int;

    /**
     * Record a send event for rate-limiting purposes.
     *
     * @param string $rlKey         e.g. "phone:96598765432" or "ip:192.168.1.1"
     * @param int    $windowSeconds Events older than this are pruned
     */
    public function recordSend(string $rlKey, int $windowSeconds): void;
}

/** Captcha token verification contract. */
interface CaptchaVerifier
{
    /** Returns true if the token is valid for the given IP. */
    public function verify(string $token, string $remoteIp): bool;
}

// ┌─────────────────────────────────────────────────────────────────────────┐
// │ SECTION 3: STORAGE ADAPTERS                                             │
// └─────────────────────────────────────────────────────────────────────────┘

// ── Option A: SQLite ──────────────────────────────────────────────────────────
//
// Zero-config, file-based. Perfect for dev, small apps, single-server.
// Requires pdo_sqlite (included in most PHP distributions).
// Configure: SQLITE_PATH=/var/www/myapp/storage/otp.db (default: /tmp/otp.db)

class SqliteOtpStorage implements OtpStorageAdapter
{
    /** @var \PDO */
    private $db;

    public function __construct(string $path = '/tmp/otp.db')
    {
        $this->db = new \PDO('sqlite:' . $path);
        $this->db->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $this->db->exec('PRAGMA journal_mode=WAL'); // Better concurrency
        $this->createTables();
    }

    private function createTables(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS otp_codes (
                phone         TEXT    NOT NULL PRIMARY KEY,
                code_hash     TEXT    NOT NULL,
                expires_at    INTEGER NOT NULL,
                attempts      INTEGER NOT NULL DEFAULT 0,
                used          INTEGER NOT NULL DEFAULT 0,
                msg_id        TEXT,
                balance_after REAL,
                created_at    INTEGER NOT NULL
            );
            CREATE TABLE IF NOT EXISTS otp_sends (
                id      INTEGER PRIMARY KEY AUTOINCREMENT,
                rl_key  TEXT    NOT NULL,
                sent_at INTEGER NOT NULL
            );
            CREATE INDEX IF NOT EXISTS idx_otp_sends ON otp_sends (rl_key, sent_at);
        ");
    }

    public function store(string $phone, string $codeHash, int $expiresAt, string $msgId, ?float $balanceAfter): void
    {
        $this->db->prepare("
            INSERT OR REPLACE INTO otp_codes
                (phone, code_hash, expires_at, attempts, used, msg_id, balance_after, created_at)
            VALUES (?, ?, ?, 0, 0, ?, ?, ?)
        ")->execute([$phone, $codeHash, $expiresAt, $msgId, $balanceAfter, time()]);
    }

    public function fetch(string $phone): ?array
    {
        $stmt = $this->db->prepare("
            SELECT code_hash, expires_at, attempts
            FROM otp_codes
            WHERE phone = ? AND used = 0 AND expires_at > ?
        ");
        $stmt->execute([$phone, time()]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if ($row === false) {
            return null;
        }
        return [
            'code_hash'  => (string) $row['code_hash'],
            'expires_at' => (int)    $row['expires_at'],
            'attempts'   => (int)    $row['attempts'],
        ];
    }

    public function incrementAttempts(string $phone): int
    {
        $this->db->prepare("UPDATE otp_codes SET attempts = attempts + 1 WHERE phone = ?")
            ->execute([$phone]);
        $stmt = $this->db->prepare("SELECT attempts FROM otp_codes WHERE phone = ?");
        $stmt->execute([$phone]);
        return (int) $stmt->fetchColumn();
    }

    public function markUsed(string $phone): void
    {
        $this->db->prepare("UPDATE otp_codes SET used = 1 WHERE phone = ?")->execute([$phone]);
    }

    public function countRecentSends(string $rlKey, int $windowSeconds): int
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) FROM otp_sends WHERE rl_key = ? AND sent_at > ?
        ");
        $stmt->execute([$rlKey, time() - $windowSeconds]);
        return (int) $stmt->fetchColumn();
    }

    public function recordSend(string $rlKey, int $windowSeconds): void
    {
        $this->db->prepare("INSERT INTO otp_sends (rl_key, sent_at) VALUES (?, ?)")
            ->execute([$rlKey, time()]);
        // Prune expired rows to prevent table growth
        $this->db->prepare("DELETE FROM otp_sends WHERE rl_key = ? AND sent_at <= ?")
            ->execute([$rlKey, time() - $windowSeconds]);
    }
}

// ── Option B: MySQL / MariaDB ─────────────────────────────────────────────────
//
// Standard production relational DB. Works with MySQL, MariaDB, PlanetScale.
// Drizzle ORM is TypeScript/JavaScript only — not available in PHP.
// For Laravel: wrap these queries in Eloquent. For Symfony: use Doctrine.
// Configure: MYSQL_DSN, MYSQL_USER, MYSQL_PASSWORD in .env

class MysqlOtpStorage implements OtpStorageAdapter
{
    /** @var \PDO */
    private $db;

    public function __construct(string $dsn, string $user, string $password)
    {
        $this->db = new \PDO($dsn, $user, $password, [
            \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_EMULATE_PREPARES   => false,
            \PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8mb4',
        ]);
        $this->createTables();
    }

    private function createTables(): void
    {
        // Run once on first boot — idempotent. In production use a proper migration tool.
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS otp_codes (
                phone         VARCHAR(20)  NOT NULL,
                code_hash     VARCHAR(64)  NOT NULL,
                expires_at    INT UNSIGNED NOT NULL,
                attempts      TINYINT      NOT NULL DEFAULT 0,
                used          TINYINT      NOT NULL DEFAULT 0,
                msg_id        VARCHAR(64),
                balance_after FLOAT,
                created_at    INT UNSIGNED NOT NULL,
                PRIMARY KEY (phone),
                INDEX idx_expires (expires_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

            CREATE TABLE IF NOT EXISTS otp_sends (
                id      BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                rl_key  VARCHAR(80)     NOT NULL,
                sent_at INT UNSIGNED    NOT NULL,
                INDEX idx_rl (rl_key, sent_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");
    }

    public function store(string $phone, string $codeHash, int $expiresAt, string $msgId, ?float $balanceAfter): void
    {
        $this->db->prepare("
            INSERT INTO otp_codes (phone, code_hash, expires_at, attempts, used, msg_id, balance_after, created_at)
            VALUES (?, ?, ?, 0, 0, ?, ?, UNIX_TIMESTAMP())
            ON DUPLICATE KEY UPDATE
                code_hash     = VALUES(code_hash),
                expires_at    = VALUES(expires_at),
                attempts      = 0,
                used          = 0,
                msg_id        = VALUES(msg_id),
                balance_after = VALUES(balance_after),
                created_at    = VALUES(created_at)
        ")->execute([$phone, $codeHash, $expiresAt, $msgId, $balanceAfter]);
    }

    public function fetch(string $phone): ?array
    {
        $stmt = $this->db->prepare("
            SELECT code_hash, expires_at, attempts
            FROM otp_codes
            WHERE phone = ? AND used = 0 AND expires_at > UNIX_TIMESTAMP()
        ");
        $stmt->execute([$phone]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if ($row === false) {
            return null;
        }
        return [
            'code_hash'  => (string) $row['code_hash'],
            'expires_at' => (int)    $row['expires_at'],
            'attempts'   => (int)    $row['attempts'],
        ];
    }

    public function incrementAttempts(string $phone): int
    {
        $this->db->prepare("UPDATE otp_codes SET attempts = attempts + 1 WHERE phone = ?")
            ->execute([$phone]);
        $stmt = $this->db->prepare("SELECT attempts FROM otp_codes WHERE phone = ?");
        $stmt->execute([$phone]);
        return (int) $stmt->fetchColumn();
    }

    public function markUsed(string $phone): void
    {
        $this->db->prepare("UPDATE otp_codes SET used = 1 WHERE phone = ?")->execute([$phone]);
    }

    public function countRecentSends(string $rlKey, int $windowSeconds): int
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) FROM otp_sends
            WHERE rl_key = ? AND sent_at > UNIX_TIMESTAMP() - ?
        ");
        $stmt->execute([$rlKey, $windowSeconds]);
        return (int) $stmt->fetchColumn();
    }

    public function recordSend(string $rlKey, int $windowSeconds): void
    {
        $this->db->prepare("
            INSERT INTO otp_sends (rl_key, sent_at) VALUES (?, UNIX_TIMESTAMP())
        ")->execute([$rlKey]);
        // Prune old rows (for high-volume apps, run a nightly scheduled DELETE instead)
        $this->db->prepare("
            DELETE FROM otp_sends WHERE rl_key = ? AND sent_at <= UNIX_TIMESTAMP() - ?
        ")->execute([$rlKey, $windowSeconds]);
    }
}

// ── Option C: Redis ───────────────────────────────────────────────────────────
//
// Fastest option. Native TTL eliminates all cleanup overhead.
// Best for high-traffic or multi-server deployments.
//
// Requires one of:
//   phpredis extension:  sudo apt install php-redis   (recommended — native C)
//   Predis package:      composer require predis/predis (pure PHP, no extension)
//
// This implementation uses phpredis. For Predis, method names differ slightly
// (lowercase: zadd, zremrangebyscore, zcard) — see Predis docs to adapt.
//
// Configure: REDIS_HOST, REDIS_PORT, REDIS_PASSWORD, REDIS_DB in .env

class RedisOtpStorage implements OtpStorageAdapter
{
    /** @var \Redis */
    private $redis;

    /** @var string */
    private $prefix;

    /**
     * @param array{host?: string, port?: int, password?: string, database?: int} $config
     */
    public function __construct(array $config = [], string $prefix = 'kwtsms:otp:')
    {
        if (!class_exists('Redis')) {
            throw new \RuntimeException(
                'phpredis not found. Install with: sudo apt install php-redis' . PHP_EOL .
                'Or use Predis: composer require predis/predis (and adapt method calls)'
            );
        }

        $r = new \Redis();
        $r->connect(
            $config['host']     ?? '127.0.0.1',
            (int) ($config['port'] ?? 6379)
        );
        $pw = $config['password'] ?? '';
        if ($pw !== '') {
            $r->auth($pw);
        }
        $r->select((int) ($config['database'] ?? 0));
        $r->setOption(\Redis::OPT_SERIALIZER, \Redis::SERIALIZER_NONE);

        $this->redis  = $r;
        $this->prefix = $prefix;
    }

    public function store(string $phone, string $codeHash, int $expiresAt, string $msgId, ?float $balanceAfter): void
    {
        $ttl  = max(1, $expiresAt - time());
        $key  = $this->prefix . 'code:' . $phone;
        $data = json_encode([
            'code_hash'    => $codeHash,
            'expires_at'   => $expiresAt,
            'attempts'     => 0,
            'msg_id'       => $msgId,
            'balance_after'=> $balanceAfter,
        ]);
        $this->redis->setex($key, $ttl, (string) $data);
        // Clear any stale "used" tombstone from a previous code
        $this->redis->del($this->prefix . 'used:' . $phone);
    }

    public function fetch(string $phone): ?array
    {
        // Check "used" tombstone first — avoids deserializing the payload
        if ($this->redis->exists($this->prefix . 'used:' . $phone)) {
            return null;
        }

        $raw = $this->redis->get($this->prefix . 'code:' . $phone);
        if ($raw === false || $raw === null) {
            return null;
        }

        /** @var array{code_hash?: string, expires_at?: int, attempts?: int}|null $data */
        $data = json_decode((string) $raw, true);
        if (!is_array($data)) {
            return null;
        }

        return [
            'code_hash'  => (string) ($data['code_hash']  ?? ''),
            'expires_at' => (int)    ($data['expires_at'] ?? 0),
            'attempts'   => (int)    ($data['attempts']   ?? 0),
        ];
    }

    public function incrementAttempts(string $phone): int
    {
        $key = $this->prefix . 'code:' . $phone;
        $raw = $this->redis->get($key);
        if ($raw === false || $raw === null) {
            return OTP_MAX_ATTEMPTS + 1; // Already expired
        }

        /** @var array<string, mixed>|null $data */
        $data = json_decode((string) $raw, true);
        if (!is_array($data)) {
            return OTP_MAX_ATTEMPTS + 1;
        }

        $data['attempts'] = ((int) ($data['attempts'] ?? 0)) + 1;
        $ttl = (int) $this->redis->ttl($key);
        if ($ttl > 0) {
            $this->redis->setex($key, $ttl, (string) json_encode($data));
        }
        return (int) $data['attempts'];
    }

    public function markUsed(string $phone): void
    {
        $codeKey = $this->prefix . 'code:' . $phone;
        $usedKey = $this->prefix . 'used:' . $phone;
        $ttl     = (int) $this->redis->ttl($codeKey);
        // Tombstone lives as long as the original code TTL so replay is blocked
        $this->redis->setex($usedKey, max(1, $ttl), '1');
    }

    public function countRecentSends(string $rlKey, int $windowSeconds): int
    {
        $key    = $this->prefix . 'rl:' . $rlKey;
        $cutoff = (string) (time() - $windowSeconds);
        $this->redis->zRemRangeByScore($key, '-inf', $cutoff);
        return (int) $this->redis->zCard($key);
    }

    public function recordSend(string $rlKey, int $windowSeconds): void
    {
        $key    = $this->prefix . 'rl:' . $rlKey;
        $now    = time();
        $member = $now . '-' . random_int(0, 999999); // Unique member per event
        $this->redis->zAdd($key, (float) $now, $member);
        $this->redis->expire($key, $windowSeconds + 60);
        $this->redis->zRemRangeByScore($key, '-inf', (string) ($now - $windowSeconds));
    }
}

// ┌─────────────────────────────────────────────────────────────────────────┐
// │ SECTION 4: CAPTCHA VERIFIERS                                            │
// └─────────────────────────────────────────────────────────────────────────┘

// ── Option 1: Cloudflare Turnstile ────────────────────────────────────────────
//
// Privacy-first, zero-friction. No GDPR cookies. No visible challenge in most
// cases (Managed widget). Free for all usage volumes.
//
// Setup (5 minutes):
//   1. dash.cloudflare.com → Turnstile → Add site
//   2. Enter your domain. Widget type: Managed (recommended).
//   3. Copy Site Key  → paste into your HTML (see frontend snippet at end of file)
//   4. Copy Secret Key → TURNSTILE_SECRET in .env

class TurnstileVerifier implements CaptchaVerifier
{
    /** @var string */
    private $secret;

    public function __construct(string $secret)
    {
        $this->secret = $secret;
    }

    public function verify(string $token, string $remoteIp): bool
    {
        if ($token === '' || $this->secret === '') {
            return false;
        }

        $ch = curl_init('https://challenges.cloudflare.com/turnstile/v0/siteverify');
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query([
                'secret'   => $this->secret,
                'response' => $token,
                'remoteip' => $remoteIp,
            ]),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 5,
            CURLOPT_CONNECTTIMEOUT => 3,
        ]);
        $response = curl_exec($ch);
        curl_close($ch);

        if (!is_string($response)) {
            return false;
        }

        /** @var array{success?: bool}|null $data */
        $data = json_decode($response, true);
        return is_array($data) && ($data['success'] ?? false) === true;
    }
}

// ── Option 2: hCaptcha ────────────────────────────────────────────────────────
//
// GDPR-compliant, privacy-first alternative to reCAPTCHA.
// Free tier available; enterprise pricing for high volume.
//
// Setup (5 minutes):
//   1. dashboard.hcaptcha.com → New Site
//   2. Enter your domain. Choose difficulty level.
//   3. Copy Site Key  → paste into your HTML (see frontend snippet at end of file)
//   4. Copy Secret Key → HCAPTCHA_SECRET in .env

class HCaptchaVerifier implements CaptchaVerifier
{
    /** @var string */
    private $secret;

    public function __construct(string $secret)
    {
        $this->secret = $secret;
    }

    public function verify(string $token, string $remoteIp): bool
    {
        if ($token === '' || $this->secret === '') {
            return false;
        }

        $ch = curl_init('https://api.hcaptcha.com/siteverify');
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query([
                'secret'   => $this->secret,
                'response' => $token,
                'remoteip' => $remoteIp,
            ]),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 5,
            CURLOPT_CONNECTTIMEOUT => 3,
        ]);
        $response = curl_exec($ch);
        curl_close($ch);

        if (!is_string($response)) {
            return false;
        }

        /** @var array{success?: bool}|null $data */
        $data = json_decode($response, true);
        return is_array($data) && ($data['success'] ?? false) === true;
    }
}

// ── NullCaptchaVerifier — CLI demo and unit tests ONLY ────────────────────────
// NEVER set CAPTCHA_PROVIDER=none in production.

class NullCaptchaVerifier implements CaptchaVerifier
{
    public function verify(string $token, string $remoteIp): bool
    {
        return true;
    }
}

// ┌─────────────────────────────────────────────────────────────────────────┐
// │ SECTION 5: OTP SERVICE                                                  │
// └─────────────────────────────────────────────────────────────────────────┘

class OtpService
{
    /** @var KwtSMS */
    private $sms;

    /** @var OtpStorageAdapter */
    private $storage;

    /** @var CaptchaVerifier */
    private $captcha;

    /** @var string */
    private $appName;

    /** @var string */
    private $appSecret;

    public function __construct(
        KwtSMS $sms,
        OtpStorageAdapter $storage,
        CaptchaVerifier $captcha,
        string $appName,
        string $appSecret
    ) {
        $this->sms       = $sms;
        $this->storage   = $storage;
        $this->captcha   = $captcha;
        $this->appName   = $appName;
        $this->appSecret = $appSecret;
    }

    /**
     * Send an OTP to the given phone number.
     *
     * Full flow:
     *   1. Validate and normalize phone format (local check, no API call)
     *   2. Verify CAPTCHA (bot detection before any rate limit checks)
     *   3. Check per-phone rate limit
     *   4. Check per-IP rate limit
     *   5. Generate a cryptographically secure random OTP
     *   6. Hash the OTP with HMAC-SHA256 (only the hash is stored)
     *   7. Send SMS via kwtSMS
     *   8. Store the hash + msg-id + balance-after in the DB
     *   9. Record rate-limit events
     *  10. Return success — never return the OTP code in the response
     *
     * @return array{success: bool, message: string, expires_in?: int, msg_id?: string, retry_after?: int}
     */
    public function sendOtp(string $rawPhone, string $captchaToken, string $clientIp): array
    {
        // ── Step 1: Validate & normalize phone ────────────────────────────
        // validate_phone_input does: empty check, email detection, normalize,
        // digit count (7–15), and returns [valid, error, normalizedPhone]
        [$valid, , $phone] = PhoneUtils::validate_phone_input($rawPhone);

        if (!$valid || $phone === null) {
            return [
                'success' => false,
                'message' => 'Please enter a valid phone number in international format (e.g. 96598765432).',
            ];
        }

        // ── Step 2: Verify CAPTCHA ────────────────────────────────────────
        if (!$this->captcha->verify($captchaToken, $clientIp)) {
            return [
                'success' => false,
                'message' => 'CAPTCHA verification failed. Please refresh the page and try again.',
            ];
        }

        // ── Step 3: Per-phone rate limit ──────────────────────────────────
        $phoneKey = 'phone:' . $phone;
        if ($this->storage->countRecentSends($phoneKey, RATE_PHONE_WINDOW) >= RATE_PHONE_MAX) {
            return [
                'success'     => false,
                'message'     => 'Too many verification codes sent to this number. Please wait 10 minutes and try again.',
                'retry_after' => RATE_PHONE_WINDOW,
            ];
        }

        // ── Step 4: Per-IP rate limit ─────────────────────────────────────
        $ipKey = 'ip:' . $clientIp;
        if ($this->storage->countRecentSends($ipKey, RATE_IP_WINDOW) >= RATE_IP_MAX) {
            return [
                'success'     => false,
                'message'     => 'Too many requests from your location. Please try again in an hour.',
                'retry_after' => RATE_IP_WINDOW,
            ];
        }

        // ── Step 5: Generate OTP ──────────────────────────────────────────
        $otp = str_pad(
            (string) random_int(0, (int) (10 ** OTP_DIGITS) - 1),
            OTP_DIGITS,
            '0',
            STR_PAD_LEFT
        );

        // ── Step 6: Hash the OTP ──────────────────────────────────────────
        // Stored hash: HMAC-SHA256(phone:otp, appSecret)
        // A full DB dump cannot reconstruct valid OTP codes.
        $codeHash  = hash_hmac('sha256', $phone . ':' . $otp, $this->appSecret);
        $expiresAt = time() + OTP_TTL_SECONDS;

        // ── Step 7: Send SMS ──────────────────────────────────────────────
        // clean_message() strips emojis, hidden chars, HTML — prevents queue-stuck issues
        $message = MessageUtils::clean_message(
            "Your {$this->appName} verification code is: {$otp}\n" .
            'Valid for 5 minutes. Do not share this code.'
        );

        $result = $this->sms->send($phone, $message);

        if ($result['result'] !== 'OK') {
            $apiErrorCode = (string) ($result['code'] ?? '');
            $maskedPhone = '***' . substr($phone, -4);
            error_log(sprintf(
                '[OTP] Send failed for %s: [%s] %s',
                $maskedPhone,
                $apiErrorCode,
                $result['description'] ?? ''
            ));
            return ['success' => false, 'message' => $this->mapSendError($apiErrorCode)];
        }

        // ── Step 8: Persist OTP hash ──────────────────────────────────────
        // Always save msg-id (needed for /status/ and /dlr/ lookups later)
        // Always save balance-after (avoids extra /balance/ API call)
        $msgId        = (string) ($result['msg-id']       ?? '');
        $balanceAfter = isset($result['balance-after']) ? (float) $result['balance-after'] : null;

        $this->storage->store($phone, $codeHash, $expiresAt, $msgId, $balanceAfter);

        // ── Step 9: Record rate-limit events ──────────────────────────────
        $this->storage->recordSend($phoneKey, RATE_PHONE_WINDOW);
        $this->storage->recordSend($ipKey, RATE_IP_WINDOW);

        // ── Step 10: Return success ───────────────────────────────────────
        // The OTP code is NOT returned — it exists only in the SMS and the DB hash.
        return [
            'success'    => true,
            'message'    => 'Verification code sent. Please check your phone.',
            'expires_in' => OTP_TTL_SECONDS,
            'msg_id'     => $msgId, // Useful for internal support lookups
        ];
    }

    /**
     * Verify an OTP code submitted by the user.
     *
     * Full flow:
     *   1. Normalize phone
     *   2. Fetch active OTP record
     *   3. Check expiry
     *   4. Increment attempts BEFORE comparison (brute-force protection)
     *   5. Check attempt limit
     *   6. Hash the submitted code and compare with hash_equals() (timing-safe)
     *   7. Mark as used on success (replay protection)
     *
     * @return array{success: bool, message: string, phone?: string}
     */
    public function verifyOtp(string $rawPhone, string $submittedCode): array
    {
        // ── Step 1: Normalize phone ───────────────────────────────────────
        [$valid, , $phone] = PhoneUtils::validate_phone_input($rawPhone);
        if (!$valid || $phone === null || $phone === '') {
            return ['success' => false, 'message' => 'Invalid phone number.'];
        }

        // ── Step 2: Fetch OTP record ──────────────────────────────────────
        $record = $this->storage->fetch($phone);
        if ($record === null) {
            return [
                'success' => false,
                'message' => 'No active verification code found. Please request a new one.',
            ];
        }

        // ── Step 3: Check expiry ──────────────────────────────────────────
        if (time() > $record['expires_at']) {
            return [
                'success' => false,
                'message' => 'Your verification code has expired. Please request a new one.',
            ];
        }

        // ── Step 4: Increment attempts BEFORE comparison ──────────────────
        // Counting before the check ensures brute-force protection cannot be
        // bypassed by racing the clock. Correct guesses also consume an attempt.
        $attempts = $this->storage->incrementAttempts($phone);

        // ── Step 5: Check attempt limit ───────────────────────────────────
        if ($attempts > OTP_MAX_ATTEMPTS) {
            return [
                'success' => false,
                'message' => 'Too many incorrect attempts. Please request a new verification code.',
            ];
        }

        // ── Step 6: Hash and compare (constant-time) ──────────────────────
        // Validate format before hashing — must be exactly OTP_DIGITS decimal digits.
        // Rejects empty strings, letters, extra whitespace, and variable-length inputs
        // that could be used to probe the hash function or bypass length checks.
        $submittedCode = trim($submittedCode);
        if (!ctype_digit($submittedCode) || strlen($submittedCode) !== OTP_DIGITS) {
            return ['success' => false, 'message' => 'Invalid verification code format.'];
        }

        $submittedHash = hash_hmac('sha256', $phone . ':' . $submittedCode, $this->appSecret);

        if (!hash_equals($record['code_hash'], $submittedHash)) {
            $remaining = OTP_MAX_ATTEMPTS - $attempts;
            if ($remaining > 0) {
                return [
                    'success' => false,
                    'message' => sprintf(
                        'Incorrect code. %d %s remaining.',
                        $remaining,
                        $remaining === 1 ? 'attempt' : 'attempts'
                    ),
                ];
            }
            return [
                'success' => false,
                'message' => 'Incorrect code. No attempts remaining. Please request a new verification code.',
            ];
        }

        // ── Step 7: Mark as used (prevents replay) ────────────────────────
        $this->storage->markUsed($phone);

        // ── Step 8: Success ───────────────────────────────────────────────
        return [
            'success' => true,
            'message' => 'Phone number verified successfully.',
            'phone'   => $phone, // Normalized — safe to store in your session/DB
        ];
    }

    /** Map kwtSMS error codes to user-safe messages. Never expose raw codes. */
    private function mapSendError(string $code): string
    {
        switch ($code) {
            case 'ERR006':
            case 'ERR025':
                return 'Please enter a valid phone number including the country code.';
            case 'ERR026':
                return 'SMS delivery to this country is not currently available.';
            case 'ERR028':
                return 'Please wait a moment before requesting another code.';
            case 'ERR010':
            case 'ERR011':
                return 'Verification service is temporarily unavailable. Please try again later.';
            case 'ERR013':
                return 'Verification service is busy. Please try again in a moment.';
            case 'ERR031':
            case 'ERR032':
                return 'Your request could not be processed. Please contact support.';
            default:
                return 'Could not send verification code. Please try again.';
        }
    }
}

// ┌─────────────────────────────────────────────────────────────────────────┐
// │ SECTION 6: HTTP HANDLERS                                                │
// └─────────────────────────────────────────────────────────────────────────┘

/**
 * POST /otp/send
 * Body:     {"phone":"96598765432","captcha_token":"<token>"}
 * Success:  {"success":true,"message":"...","expires_in":300}
 * Failure:  {"success":false,"message":"..."}
 */
function handleSendOtp(OtpService $service): void
{
    header('Content-Type: application/json');

    /** @var array{phone?: string, captcha_token?: string}|null $input */
    $input = json_decode((string) file_get_contents('php://input'), true);
    if (!is_array($input)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid JSON body.']);
        return;
    }

    $phone        = trim((string) ($input['phone']         ?? ''));
    $captchaToken = trim((string) ($input['captcha_token'] ?? ''));

    // Real IP: Cloudflare sets CF-Connecting-IP, load balancers set X-Forwarded-For
    $clientIp = (string) (
        $_SERVER['HTTP_CF_CONNECTING_IP'] ??
        $_SERVER['HTTP_X_FORWARDED_FOR']  ??
        $_SERVER['REMOTE_ADDR']           ??
        '0.0.0.0'
    );
    // X-Forwarded-For may be a comma-separated list — take the first (leftmost) IP
    if (strpos($clientIp, ',') !== false) {
        $clientIp = trim(explode(',', $clientIp)[0]);
    }
    // Reject spoofed/malformed IPs so they cannot bypass per-IP rate limiting.
    // filter_var rejects non-IP strings; fall back to a fixed sentinel value.
    if (filter_var($clientIp, FILTER_VALIDATE_IP) === false) {
        $clientIp = '0.0.0.0';
    }

    if ($phone === '') {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Phone number is required.']);
        return;
    }

    $result = $service->sendOtp($phone, $captchaToken, $clientIp);
    http_response_code($result['success'] ? 200 : 422);
    echo json_encode($result);
}

/**
 * POST /otp/verify
 * Body:     {"phone":"96598765432","code":"123456"}
 * Success:  {"success":true,"message":"...","phone":"96598765432"}
 * Failure:  {"success":false,"message":"..."}
 */
function handleVerifyOtp(OtpService $service): void
{
    header('Content-Type: application/json');

    /** @var array{phone?: string, code?: string}|null $input */
    $input = json_decode((string) file_get_contents('php://input'), true);
    if (!is_array($input)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid JSON body.']);
        return;
    }

    $phone = trim((string) ($input['phone'] ?? ''));
    $code  = trim((string) ($input['code']  ?? ''));

    if ($phone === '' || $code === '') {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Phone and code are required.']);
        return;
    }

    $result = $service->verifyOtp($phone, $code);
    http_response_code($result['success'] ? 200 : 422);
    echo json_encode($result);
}

// ┌─────────────────────────────────────────────────────────────────────────┐
// │ SECTION 7: FACTORY                                                      │
// └─────────────────────────────────────────────────────────────────────────┘

/**
 * Wire everything together from .env / environment variables.
 *
 * Required env vars:
 *   KWTSMS_USERNAME    — kwtSMS API username
 *   KWTSMS_PASSWORD    — kwtSMS API password
 *   KWTSMS_SENDER      — Your transactional SenderID (NEVER 'KWT-SMS' for OTP)
 *   APP_SECRET         — 32+ random chars for HMAC key. Generate:
 *                        php -r "echo bin2hex(random_bytes(32));"
 *   APP_NAME           — Shown in the OTP message body, e.g. "MyApp"
 *
 * Storage driver (pick one):
 *   STORAGE_DRIVER=sqlite   SQLITE_PATH=/path/to/otp.db      (default)
 *   STORAGE_DRIVER=mysql    MYSQL_DSN, MYSQL_USER, MYSQL_PASSWORD
 *   STORAGE_DRIVER=redis    REDIS_HOST, REDIS_PORT, REDIS_PASSWORD, REDIS_DB
 *
 * Captcha provider (pick one):
 *   CAPTCHA_PROVIDER=turnstile   TURNSTILE_SECRET=...    (default)
 *   CAPTCHA_PROVIDER=hcaptcha   HCAPTCHA_SECRET=...
 *   CAPTCHA_PROVIDER=none        (local dev / CLI only — never production)
 *
 * Optional:
 *   KWTSMS_TEST_MODE=1   — Queues but does not deliver (set to 0 for production)
 */
function buildOtpService(): OtpService
{
    $sms = KwtSMS::from_env();

    // ── Storage ───────────────────────────────────────────────────────────
    $driver = strtolower((string) (getenv('STORAGE_DRIVER') ?: 'sqlite'));

    switch ($driver) {
        case 'mysql':
            $storage = new MysqlOtpStorage(
                (string) (getenv('MYSQL_DSN')      ?: 'mysql:host=localhost;dbname=myapp;charset=utf8mb4'),
                (string) (getenv('MYSQL_USER')     ?: 'root'),
                (string) (getenv('MYSQL_PASSWORD') ?: '')
            );
            break;

        case 'redis':
            $storage = new RedisOtpStorage([
                'host'     => (string) (getenv('REDIS_HOST')     ?: '127.0.0.1'),
                'port'     => (int)    (getenv('REDIS_PORT')     ?: 6379),
                'password' => (string) (getenv('REDIS_PASSWORD') ?: ''),
                'database' => (int)    (getenv('REDIS_DB')       ?: 0),
            ]);
            break;

        case 'sqlite':
        default:
            $storage = new SqliteOtpStorage(
                (string) (getenv('SQLITE_PATH') ?: '/tmp/otp.db')
            );
            break;
    }

    // ── Captcha ───────────────────────────────────────────────────────────
    $provider = strtolower((string) (getenv('CAPTCHA_PROVIDER') ?: 'turnstile'));

    switch ($provider) {
        case 'hcaptcha':
            $captcha = new HCaptchaVerifier(
                (string) (getenv('HCAPTCHA_SECRET') ?: '')
            );
            break;

        case 'none':
            $captcha = new NullCaptchaVerifier(); // Dev / CLI only
            break;

        case 'turnstile':
        default:
            $captcha = new TurnstileVerifier(
                (string) (getenv('TURNSTILE_SECRET') ?: '')
            );
            break;
    }

    $appName   = (string) (getenv('APP_NAME')   ?: 'MyApp');
    $appSecret = (string) (getenv('APP_SECRET') ?: '');

    if ($appSecret === '') {
        throw new \RuntimeException(
            'APP_SECRET is not set. Generate one with:' . PHP_EOL .
            '  php -r "echo bin2hex(random_bytes(32));"' . PHP_EOL .
            'Then add APP_SECRET=<value> to your .env file.'
        );
    }

    return new OtpService($sms, $storage, $captcha, $appName, $appSecret);
}

// ┌─────────────────────────────────────────────────────────────────────────┐
// │ SECTION 8: FRONTEND HTML SNIPPETS                                       │
// └─────────────────────────────────────────────────────────────────────────┘
/*

════════════════════════════════════════════════════════════════════════════════
FRONTEND SNIPPET — Cloudflare Turnstile (Option 1)
════════════════════════════════════════════════════════════════════════════════
Replace YOUR_CF_SITE_KEY with the sitekey from dash.cloudflare.com → Turnstile.

```html
<!-- In <head> -->
<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>

<!-- OTP request form -->
<form id="otp-form">
  <input type="tel" id="phone" name="phone"
         placeholder="+965 9876 5432" required autocomplete="tel" />
  <div class="cf-turnstile" data-sitekey="YOUR_CF_SITE_KEY"></div>
  <button type="submit">Send Verification Code</button>
  <p id="otp-error" style="color:red;display:none"></p>
</form>

<script>
document.getElementById('otp-form').addEventListener('submit', async (e) => {
  e.preventDefault();
  const token = document.querySelector('[name="cf-turnstile-response"]')?.value || '';
  const phone = document.getElementById('phone').value.trim();

  const res  = await fetch('/otp/send', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ phone, captcha_token: token }),
  });
  const data = await res.json();

  if (data.success) {
    showOtpInput(phone, data.expires_in);
  } else {
    document.getElementById('otp-error').textContent = data.message;
    document.getElementById('otp-error').style.display = 'block';
    if (window.turnstile) turnstile.reset(); // Reset widget for next attempt
  }
});
</script>
```

════════════════════════════════════════════════════════════════════════════════
FRONTEND SNIPPET — hCaptcha (Option 2)
════════════════════════════════════════════════════════════════════════════════
Replace YOUR_HCAPTCHA_SITE_KEY with the sitekey from dashboard.hcaptcha.com.

```html
<!-- In <head> -->
<script src="https://js.hcaptcha.com/1/api.js" async defer></script>

<!-- OTP request form -->
<form id="otp-form">
  <input type="tel" id="phone" name="phone"
         placeholder="+965 9876 5432" required autocomplete="tel" />
  <div class="h-captcha" data-sitekey="YOUR_HCAPTCHA_SITE_KEY"></div>
  <button type="submit">Send Verification Code</button>
  <p id="otp-error" style="color:red;display:none"></p>
</form>

<script>
document.getElementById('otp-form').addEventListener('submit', async (e) => {
  e.preventDefault();
  const token = document.querySelector('[name="h-captcha-response"]')?.value || '';
  const phone = document.getElementById('phone').value.trim();

  const res  = await fetch('/otp/send', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ phone, captcha_token: token }),
  });
  const data = await res.json();

  if (data.success) {
    showOtpInput(phone, data.expires_in);
  } else {
    document.getElementById('otp-error').textContent = data.message;
    document.getElementById('otp-error').style.display = 'block';
    if (window.hcaptcha) hcaptcha.reset();
  }
});
</script>
```

════════════════════════════════════════════════════════════════════════════════
OTP VERIFY FORM (shared — paste after the send form)
════════════════════════════════════════════════════════════════════════════════

```html
<div id="otp-verify" style="display:none">
  <p>Enter the 6-digit code sent to your phone.</p>
  <p>Expires in: <strong id="otp-countdown"></strong></p>

  <input type="text" id="otp-code"
         inputmode="numeric" pattern="[0-9]{6}" maxlength="6"
         placeholder="123456" required autocomplete="one-time-code" />

  <button id="otp-submit">Verify</button>
  <p id="otp-verify-error" style="color:red;display:none"></p>

  <button id="resend-btn" disabled>Resend code (<span id="resend-countdown">60</span>s)</button>
</div>

<script>
let currentPhone = '';

function showOtpInput(phone, expiresIn) {
  currentPhone = phone;
  document.getElementById('otp-form').style.display    = 'none';
  document.getElementById('otp-verify').style.display  = 'block';
  startCountdown('otp-countdown', expiresIn);
  startResendCountdown(60); // Show 60s cooldown to the user (server enforces its own)
}

function startCountdown(elementId, seconds) {
  const el  = document.getElementById(elementId);
  const end = Date.now() + seconds * 1000;
  const t   = setInterval(() => {
    const left = Math.max(0, Math.round((end - Date.now()) / 1000));
    const m = Math.floor(left / 60), s = left % 60;
    el.textContent = `${m}:${s.toString().padStart(2, '0')}`;
    if (left === 0) { clearInterval(t); el.textContent = 'expired'; }
  }, 500);
}

function startResendCountdown(seconds) {
  const btn      = document.getElementById('resend-btn');
  const countEl  = document.getElementById('resend-countdown');
  btn.disabled   = true;
  const end      = Date.now() + seconds * 1000;
  const t        = setInterval(() => {
    const left = Math.max(0, Math.round((end - Date.now()) / 1000));
    countEl.textContent = left;
    if (left === 0) {
      clearInterval(t);
      btn.textContent = 'Resend code';
      btn.disabled    = false;
    }
  }, 500);
}

document.getElementById('otp-submit').addEventListener('click', async () => {
  const code = document.getElementById('otp-code').value.trim();
  const err  = document.getElementById('otp-verify-error');

  const res  = await fetch('/otp/verify', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ phone: currentPhone, code }),
  });
  const data = await res.json();

  if (data.success) {
    window.location.href = '/dashboard'; // Redirect after verified
  } else {
    err.textContent    = data.message;
    err.style.display  = 'block';
  }
});

document.getElementById('resend-btn').addEventListener('click', async () => {
  // Re-submit the send form (or call send endpoint directly)
  // Turnstile / hCaptcha widget must be reset and re-solved before resend
  document.getElementById('otp-form').style.display   = 'block';
  document.getElementById('otp-verify').style.display = 'none';
  document.getElementById('otp-code').value           = '';
  if (window.turnstile) turnstile.reset();
  if (window.hcaptcha)  hcaptcha.reset();
});
</script>
```

*/

// ┌─────────────────────────────────────────────────────────────────────────┐
// │ SECTION 9: CLI DEMO                                                     │
// └─────────────────────────────────────────────────────────────────────────┘

if (php_sapi_name() !== 'cli') {
    // HTTP mode: wire up your router to the handlers above.
    // Example (plain PHP, no framework):
    //
    //   $service = buildOtpService();
    //   $uri     = strtok($_SERVER['REQUEST_URI'], '?');
    //   match ($uri) {
    //       '/otp/send'   => handleSendOtp($service),
    //       '/otp/verify' => handleVerifyOtp($service),
    //       default       => http_response_code(404),
    //   };
    return;
}

// CLI demo — reads KWTSMS_* and APP_SECRET from .env.
// Storage defaults to SQLite (/tmp/otp.db).
// Captcha is bypassed automatically (CAPTCHA_PROVIDER=none forced below).

putenv('CAPTCHA_PROVIDER=none'); // No browser in CLI — skip captcha verification
putenv('APP_NAME=OTPDemo');      // Shown in the SMS body

echo "\n=== kwtSMS Production OTP Demo ===\n\n";

try {
    $service = buildOtpService();
} catch (\RuntimeException $e) {
    echo 'Config error: ' . $e->getMessage() . "\n";
    exit(1);
}

$phone = '96598765432';

// ── Send OTP ──────────────────────────────────────────────────────────────────
echo "Phone:        {$phone}\n";
echo "Sending OTP...\n\n";

$sendResult = $service->sendOtp($phone, '', '127.0.0.1');

if (!$sendResult['success']) {
    echo 'Send failed: ' . $sendResult['message'] . "\n";
    exit(1);
}

echo 'Result:       ' . $sendResult['message'] . "\n";
echo 'Expires in:   ' . $sendResult['expires_in'] . "s\n";
echo 'Msg ID:       ' . $sendResult['msg_id'] . "\n\n";

// ── Wrong code attempt ────────────────────────────────────────────────────────
echo "Testing wrong code (111111)...\n";
$bad = $service->verifyOtp($phone, '111111');
echo 'Result:       ' . $bad['message'] . "\n\n";

// ── Correct code ──────────────────────────────────────────────────────────────
// In test mode (KWTSMS_TEST_MODE=1): check your kwtSMS queue at
//   kwtsms.com → Account → Queue
// In production (KWTSMS_TEST_MODE=0): the code arrives by SMS.

echo 'Enter the code from SMS or kwtSMS queue: ';
$handle  = @fopen('php://stdin', 'r');
$entered = $handle !== false ? trim((string) fgets($handle)) : '';

if ($entered === '') {
    echo "No code entered — demo ends here.\n";
    exit(0);
}

$verifyResult = $service->verifyOtp($phone, $entered);

if ($verifyResult['success']) {
    echo "\nVerified! Normalized phone: " . $verifyResult['phone'] . "\n";
    echo "Store this in your session/DB as the verified phone number.\n";
} else {
    echo "\nVerification failed: " . $verifyResult['message'] . "\n";
}
