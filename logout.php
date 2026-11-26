<?php
require __DIR__ . '/includes/security.php';
require __DIR__ . '/includes/audit.php';
startRequest();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    stopRequest(405, 'Method not allowed.');
}
if (!validCsrf($_POST['csrf'] ?? null)) {
    stopRequest(403, 'Reload the page and try again.');
}
try {
    $db = recordsDatabase();
    if (!requestLimit($db, 'logout', 30, 60)) {
        stopRequest(429, 'Too many requests. Try again shortly.');
    }
    $account = currentAccount($db);
    if ($account) {
        writeSessionAuditEvent($db, $account, 'account.signed_out');
    }
} catch (Throwable $error) {
    error_log('ScholarHub could not look up the account for sign-out auditing.');
}
$_SESSION = [];
$cookie = session_get_cookie_params();
setcookie(session_name(), '', [
    'expires' => time() - 3600, 'path' => $cookie['path'],
    'domain' => $cookie['domain'], 'secure' => $cookie['secure'],
    'httponly' => true, 'samesite' => 'Lax'
]);
session_destroy();
header('Location: login', true, 303);
exit;
?>
