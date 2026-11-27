<?php
require_once __DIR__ . '/database.php';

function startRequest(bool $loadGoogleFonts = false): void
{
    ini_set('display_errors', '0');
    ini_set('zend.exception_ignore_args', '1');
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: no-referrer');
    header('Cross-Origin-Resource-Policy: same-origin');
    $nonce = cspNonce();
    $fontStyles = $loadGoogleFonts ? ' https://fonts.googleapis.com' : '';
    $fontFiles = $loadGoogleFonts ? "font-src 'self' https://fonts.gstatic.com; " : '';
    header("Content-Security-Policy: default-src 'self'; script-src 'nonce-$nonce' 'strict-dynamic' 'unsafe-eval'; script-src-attr 'none'; style-src 'self' 'unsafe-inline'$fontStyles; {$fontFiles}img-src 'self' data:; object-src 'none'; base-uri 'none'; frame-ancestors 'none'; form-action 'self'");
    header("Content-Security-Policy: default-src 'self'; script-src 'nonce-$nonce' 'strict-dynamic' 'unsafe-eval'; script-src-attr 'none'; style-src 'self' 'unsafe-inline'$fontStyles; {$fontFiles}img-src 'self' data:; object-src 'none'; base-uri 'none'; frame-ancestors 'none'; form-action 'self'");
    $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    if (!$secure && preg_match('/^dysl\.byethost7\.com(?::(?:80|443))?$/iD', $_SERVER['HTTP_HOST'] ?? '')) {
        stopRequest(403, 'Use the HTTPS address to access ScholarHub.');
    }
    if ($secure) {
        header('Strict-Transport-Security: max-age=31536000');
    }
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_set_cookie_params(['secure' => $secure, 'httponly' => true, 'samesite' => 'Lax']);
    session_start();
}

function cspNonce(): string
{   
    static $nonce;
    return $nonce ??= base64_encode(random_bytes(18));
}

function requestLimit(PDO $db, string $scope, int $maximum, int $seconds, ?string $identity = null, ?int $windowStart = null): bool
{
    $now = time();
    $start = $windowStart ?? intdiv($now, $seconds) * $seconds;
    $bucket = hash('sha256', $scope . ':' . ($identity ?? ($_SERVER['REMOTE_ADDR'] ?? 'unknown')));
    $statement = $db->prepare('INSERT INTO request_limits (bucket, window_start, requests) VALUES (?, ?, 1)
        ON DUPLICATE KEY UPDATE requests = requests + 1');
    $statement->execute([$bucket, $start]);
    $lookup = $db->prepare('SELECT requests FROM request_limits WHERE bucket = ? AND window_start = ?');
    $lookup->execute([$bucket, $start]);
    $count = (int) $lookup->fetchColumn();
    if (random_int(1, 100) === 1) {
        $cleanup = $db->prepare('DELETE FROM request_limits WHERE window_start < ?');
        $cleanup->execute([$now - 3600]);
    }
    if ($count < 1) {
        throw new RuntimeException('The request limit did not return a count.');
    }
    if ($count > $maximum) {
        http_response_code(429);
        header('Retry-After: ' . max(1, $start + $seconds - $now));
        return false;
    }
    return true;
}

function csrfToken(): string
{
    return $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
}

function validCsrf($token): bool
{
    return is_string($token) && isset($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $token);
}

function validAccountEmail(string $email, bool $requireGmail): bool
{
    return strlen($email) <= 254 && filter_var($email, FILTER_VALIDATE_EMAIL) !== false &&
        (!$requireGmail || str_ends_with(strtolower($email), '@gmail.com'));
}

const MIN_NEW_PASSWORD_LENGTH = 12;
const NEW_PASSWORD_REQUIREMENTS = 'Use at least ' . MIN_NEW_PASSWORD_LENGTH . ' characters, including an uppercase letter, a lowercase letter, a number, and a symbol.';

function validNewPassword(string $password): bool
{
    return preg_match('/\A(?=.{'. MIN_NEW_PASSWORD_LENGTH . ',}\z)(?=.*[A-Z])(?=.*[a-z])(?=.*[0-9])(?=.*[\p{P}\p{S}]).*\z/us', $password) === 1;
}

const EMAIL_CODE_LIFETIME = 600;
const EMAIL_CODE_RESEND_DELAY = 60;

function verifyActivationCode(PDO $db, ?array $account, mixed $code): bool
{
    if (!$account || $account['verified_at'] !== null || !is_string($code) ||
        (int) $account['invitation_expires'] <= time()) {
        return false;
    }
    $hash = $account['invitation_hash'] ?? '';
    if (strlen($hash) === 64) {
        return preg_match('/^[a-f0-9]{48}$/D', $code) === 1 &&
            hash_equals($hash, hash('sha256', $code));
    }
    if (preg_match('/^[0-9]{6}$/D', $code) !== 1) {
        return false;
    }
    if (!requestLimit($db, 'activation-code', 5, EMAIL_CODE_LIFETIME, $account['id'] . ':' . $hash,
        (int) $account['invitation_expires'] - EMAIL_CODE_LIFETIME)) {
        stopRequest(429, 'Too many verification attempts. Request a new code.');
    }
    return password_verify($code, $hash);
}

function requestActivationCode(PDO $db, string $email, bool $signup = false): string
{
    $settings = privateSettings();
    $brevoKey = getenv('BREVO_API_KEY') ?: ($settings['brevo_api_key'] ?? '');
    $sender = getenv('SCHOLARHUB_MAIL_FROM') ?: ($settings['mail_from'] ?? '');
    if (!is_string($brevoKey) || trim($brevoKey) === '') {
        error_log('ScholarHub verification email skipped: Brevo API key is missing.');
        return 'Email delivery is unavailable. Ask your administrator for an activation code.';
    }
    if (!filter_var($sender, FILTER_VALIDATE_EMAIL) || !function_exists('curl_init')) {
        error_log('ScholarHub verification email skipped: sender configuration or PHP cURL is unavailable.');
        return 'Email delivery is unavailable. Ask your administrator for an activation code.';
    }
    $notice = $signup
        ? 'If this Gmail address is eligible for signup, check your inbox. Wait at least 60 seconds before requesting another code.'
        : 'If this email has a pending invitation, check your inbox. If no code arrives, ask your administrator. Wait at least 60 seconds before requesting another code.';
    if ($signup && !requestLimit($db, 'signup-email', 3, 600, strtolower($email))) {
        return 'Too many requests for this email address. Try again later.';
    }
    $db->beginTransaction();
    try {
        if ($signup) {
            $create = $db->prepare('INSERT INTO signup_verifications (email)
                SELECT ? WHERE NOT EXISTS (SELECT 1 FROM accounts WHERE email = ?)
                ON DUPLICATE KEY UPDATE email = signup_verifications.email');
            $create->execute([$email, $email]);
            $query = $db->prepare('SELECT email, invitation_hash, invitation_expires
                FROM signup_verifications WHERE email = ?
                AND NOT EXISTS (SELECT 1 FROM accounts
                    WHERE CONVERT(accounts.email USING utf8mb4) COLLATE utf8mb4_unicode_ci = signup_verifications.email)
                FOR UPDATE');
        } else {
            $query = $db->prepare('SELECT id, email, invitation_hash, invitation_expires
                FROM accounts WHERE email = ? AND verified_at IS NULL FOR UPDATE');
        }
        $query->execute([$email]);
        $account = $query->fetch(PDO::FETCH_ASSOC);
        $reserved = false;
        if (!$account) {
            error_log('ScholarHub verification email skipped: no pending invitation.');
        } elseif (strlen($account['invitation_hash'] ?? '') === 60 &&
            (int) $account['invitation_expires'] - EMAIL_CODE_LIFETIME + EMAIL_CODE_RESEND_DELAY > time()) {
            error_log('ScholarHub verification email skipped: resend cooldown.');
        } else {
            $code = (string) random_int(100000, 999999);
            $update = $db->prepare($signup
                ? 'UPDATE signup_verifications SET invitation_hash = ?, invitation_expires = ? WHERE email = ?'
                : 'UPDATE accounts SET invitation_hash = ?, invitation_expires = ? WHERE id = ?');
            $update->execute([password_hash($code, PASSWORD_BCRYPT), time() + EMAIL_CODE_LIFETIME,
                $signup ? $account['email'] : $account['id']]);
            $reserved = true;
        }
        $db->commit();
        if ($reserved && sendActivationEmail($sender, $account['email'], $code, $brevoKey)) {
            error_log('ScholarHub verification email accepted by Brevo.');
        }
        return $notice;
    } catch (Throwable $error) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $error;
    }
}

function sendActivationEmail(string $sender, string $recipient, string $code, string $brevoKey): bool
{
    $message = "Your ScholarHub verification code is " . $code .
        ".\n\nIt expires in 10 minutes. If you did not request it, ignore this email.";
    return sendBrevoEmail($sender, $recipient, 'ScholarHub verification code', $message, $brevoKey);
}

function completeSignup(PDO $db, string $email, string $password, mixed $code): bool
{
    $lookup = $db->prepare('SELECT email AS id, invitation_hash, invitation_expires, NULL AS verified_at
        FROM signup_verifications WHERE email = ?');
    $lookup->execute([$email]);
    $verification = $lookup->fetch(PDO::FETCH_ASSOC);
    if (!verifyActivationCode($db, $verification ?: null, $code)) {
        return false;
    }

    $passwordHash = password_hash($password, PASSWORD_ARGON2ID);
    $db->beginTransaction();
    try {
        $consume = $db->prepare('DELETE FROM signup_verifications
            WHERE email = ? AND invitation_hash = ? AND invitation_expires > ?');
        $consume->execute([$email, $verification['invitation_hash'], time()]);
        if ($consume->rowCount() !== 1) {
            $db->rollBack();
            return false;
        }
        $create = $db->prepare("INSERT INTO accounts (email, role, password_hash, verified_at)
            VALUES (?, 'staff', ?, UTC_TIMESTAMP())");
        $create->execute([$email, $passwordHash]);
        $queue = $db->prepare('INSERT INTO signup_requests (email, requested_at) VALUES (?, UTC_TIMESTAMP())
            ON DUPLICATE KEY UPDATE requested_at = UTC_TIMESTAMP()');
        $queue->execute([$email]);
        $db->commit();
        return true;
    } catch (Throwable $error) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        if ($error instanceof PDOException && (int) ($error->errorInfo[1] ?? 0) === 1062) {
            return false;
        }
        throw $error;
    }
}

function sendBrevoEmail(string $sender, string $recipient, string $subject, string $message, string $brevoKey): bool
{
    $request = curl_init('https://api.brevo.com/v3/smtp/email');
    curl_setopt_array($request, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_TIMEOUT => 8,
        CURLOPT_HTTPHEADER => ['api-key: ' . $brevoKey, 'Content-Type: application/json', 'Accept: application/json'],
        CURLOPT_POSTFIELDS => json_encode([
            'sender' => ['name' => 'ScholarHub', 'email' => $sender],
            'to' => [['email' => $recipient]], 'subject' => $subject, 'textContent' => $message,
        ], JSON_THROW_ON_ERROR),
    ]);
    $response = curl_exec($request);
    $transportError = curl_errno($request);
    $status = curl_getinfo($request, CURLINFO_HTTP_CODE);
    curl_close($request);
    if ($response === false) {
        error_log('ScholarHub could not reach Brevo (cURL ' . $transportError . ').');
        return false;
    }
    if ($status < 200 || $status >= 300) {
        $error = json_decode($response, true);
        $message = $error['message'] ?? '';
        $reason = is_string($message) && preg_match('/unrecogni[sz]ed IP|unauthori[sz]ed IP|IP address|whitelist/i', $message)
            ? ': source IP restriction' : '';
        error_log('ScholarHub Brevo delivery request failed (HTTP ' . $status . $reason . ').');
        return false;
    }
    return true;
}

function canManageRecords(string $role): bool
{
    return in_array($role, ['admin', 'staff'], true);
}

function accountRoleLabel(string $role): string
{
    return match ($role) {
        'admin' => 'Administrator',
        'staff' => 'Staff',
        'viewer' => 'Viewer',
        default => 'Unknown role',
    };
}

function currentAccount(PDO $db): ?array
{
    if (!isset($_SESSION['account_id']) || time() - ($_SESSION['signed_in_at'] ?? 0) >= 8 * 3600) {
        return null;
    }
    $lookup = $db->prepare("SELECT id, email, role FROM accounts WHERE id = ? AND verified_at IS NOT NULL
        AND disabled_at IS NULL AND temporary_password_expires IS NULL AND access_version = ?
        AND (SELECT session_epoch FROM record_write_lock WHERE id = 1) = ?
        AND (role = 'admin' OR NOT EXISTS (SELECT 1 FROM signup_requests
            WHERE signup_requests.email = CONVERT(accounts.email USING utf8mb4) COLLATE utf8mb4_unicode_ci))");
    $lookup->execute([$_SESSION['account_id'], $_SESSION['access_version'] ?? 0, $_SESSION['session_epoch'] ?? 0]);
    return $lookup->fetch(PDO::FETCH_ASSOC) ?: null;
}

function stopRequest(int $status, string $message): void
{
    http_response_code($status);
    header('Content-Type: text/plain; charset=utf-8');
    exit($message);
}

function databaseSetupMessage(Throwable $error): ?string
{
    if ($error instanceof DatabaseConfigurationException) {
        return $error->getMessage();
    }
    if (!$error instanceof PDOException) {
        return null;
    }
    if ((int) ($error->errorInfo[1] ?? 0) === 1054 &&
        preg_match("/Unknown column '(?:[a-z_]+\\.)?(session_epoch|temporary_password_expires|disabled_at|access_version)'/i",
            $error->errorInfo[2] ?? '', $missing)) {
        $migration = match (strtolower($missing[1])) {
            'session_epoch' => 'migrate-database-backups.sql',
            'temporary_password_expires' => 'migrate-temporary-passwords.sql',
            default => 'migrate-user-management.sql',
        };
        return "The ScholarHub database needs an upgrade. Import includes/$migration into the existing database, then reload.";
    }
    return match ((int) ($error->errorInfo[1] ?? 0)) {
        1054, 1146 => 'The ScholarHub database schema is out of date. Import includes/schema.sql into the app database, then reload.',
        1049 => 'The ScholarHub database is missing. Import includes/schema.sql and verify the configured database name.',
        1044, 1045, 1142, 1143 => 'ScholarHub cannot access the database. Check the configured database credentials and permissions.',
        2002, 2003, 2005 => 'ScholarHub cannot reach MySQL. Check the MySQL hostname and port shown in your hosting panel.',
        default => null,
    };
}
