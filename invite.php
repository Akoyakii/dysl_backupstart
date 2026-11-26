<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require __DIR__ . '/includes/security.php';
ini_set('display_errors', '0');

[$script, $email, $role] = array_pad($argv, 3, null);
$sender = getenv('SCHOLARHUB_MAIL_FROM');
if (!validAccountEmail($email ?? '', true) ||
    !in_array($role, ['admin', 'staff'], true) || !filter_var($sender, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "Set SCHOLARHUB_MAIL_FROM and run php invite.php yourname@gmail.com admin|staff.\n");
    exit(1);
}

try {
    $db = recordsDatabase();
    $code = bin2hex(random_bytes(24));
    $hash = hash('sha256', $code);
    $expires = time() + 3600;
    $db->beginTransaction();
    $lookup = $db->prepare('SELECT verified_at FROM accounts WHERE email = ? FOR UPDATE');
    $lookup->execute([$email]);
    $account = $lookup->fetch(PDO::FETCH_ASSOC);
    if ($account && $account['verified_at'] !== null) {
        throw new DomainException('This account is already verified.');
    }
    $statement = $account
        ? $db->prepare('UPDATE accounts SET role = ?, invitation_hash = ?, invitation_expires = ? WHERE email = ?')
        : $db->prepare('INSERT INTO accounts (role, invitation_hash, invitation_expires, email) VALUES (?, ?, ?, ?)');
    $statement->execute([$role, $hash, $expires, $email]);
    if (!@mail($email, 'Activate your ScholarHub account', "Open /login?activate=1 on your ScholarHub site and enter this code within one hour:\n\n$code", ['From' => $sender])) {
        throw new RuntimeException('Mail delivery could not be queued.');
    }
    $db->commit();
    fwrite(STDOUT, "Invitation queued for delivery.\n");
} catch (Throwable $error) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    fwrite(STDERR, $error instanceof DomainException ? $error->getMessage() . "\n" : "Invitation failed. Check database and mail configuration.\n");
    exit(1);
}
