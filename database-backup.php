<?php
require __DIR__ . '/includes/security.php';
require __DIR__ . '/includes/audit.php';
require __DIR__ . '/includes/database-backups.php';

if (PHP_SAPI === 'cli') {
    if (($argv[1] ?? '') !== '--weekly') exit(2);
    try {
        $db = recordsDatabase();
        $db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
        $db->beginTransaction();
        lockDatabaseBackup($db);
        $id = createDatabaseBackup($db, 'weekly');
        $db->commit();
        echo "Weekly backup #$id saved.\n";
    } catch (Throwable $error) {
        if (isset($db) && $db->inTransaction()) $db->rollBack();
        fwrite(STDERR, "Weekly backup could not be saved.\n");
        exit(1);
    }
    exit;
}

startRequest();
$method = $_SERVER['REQUEST_METHOD'];
if (!in_array($method, ['GET', 'POST'], true)) stopRequest(405, 'Method not allowed.');
try {
    $db = recordsDatabase();
    $admin = currentAccount($db);
    if (!$admin || $admin['role'] !== 'admin') stopRequest(403, 'Administrator access is required.');
    if (!requestLimit($db, 'database-backups', 20, 60, (string) $admin['id'])) {
        stopRequest(429, 'Too many requests. Try again shortly.');
    }
    $id = filter_var(($method === 'POST' ? $_POST : $_GET)['id'] ?? null, FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]);
    if ($method === 'POST') {
        if (!validCsrf($_POST['csrf'] ?? null)) stopRequest(403, 'Reload the page and try again.');
        $action = $_POST['action'] ?? '';
        if ($action !== 'create' && ($action !== 'restore' || !$id || ($_POST['confirmation'] ?? '') !== 'RESTORE')) {
            stopRequest(400, 'Choose a backup and type RESTORE to confirm.');
        }
        $db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
        $db->beginTransaction();
        lockDatabaseBackup($db);
        if (currentAccount($db) === null) {
            $db->rollBack();
            stopRequest(403, 'Your session changed. Sign in again.');
        }
        if ($action === 'create') {
            $backupId = createDatabaseBackup($db, 'manual');
            writeAuditEvent($db, $admin, 'backup.database_created', ['Backup ID' => $backupId]);
            $db->commit();
            $_SESSION['backup_notice'] = 'Database backup saved.';
            header('Location: index#settings', true, 303);
        } else {
            $recoveryId = restoreDatabaseBackup($db, $id, $admin);
            writeAuditEvent($db, $admin, 'backup.database_restored', ['Backup ID' => $id, 'Recovery backup ID' => $recoveryId]);
            $db->commit();
            session_regenerate_id(true);
            $_SESSION = ['database_restored' => true];
            header('Location: login', true, 303);
        }
        exit;
    }
    if (!$id) stopRequest(404, 'Backup not found.');
    $lookup = $db->prepare('SELECT backup_type, created_at, row_count FROM database_backups WHERE id = ?');
    $lookup->execute([$id]);
    $backup = $lookup->fetch(PDO::FETCH_ASSOC);
    if (!$backup) stopRequest(404, 'Backup not found.');
    $csrf = csrfToken();
    session_write_close();
} catch (DomainException $error) {
    if ($db->inTransaction()) $db->rollBack();
    stopRequest(409, $error->getMessage());
} catch (Throwable $error) {
    if (isset($db) && $db->inTransaction()) $db->rollBack();
    error_log('ScholarHub database backup operation failed.');
    stopRequest(503, 'The database backup operation could not be completed.');
}
?>
<!DOCTYPE html>
<html lang="en">
<head><?php require __DIR__ . '/includes/head.php'; ?></head>
<body class="text-slate-900">
<main class="mx-auto max-w-xl px-4 py-10">
    <a href="index#settings" class="text-sm font-semibold text-dyci-blue hover:underline">Back to backups</a>
    <section class="mt-4 rounded-2xl border bg-white p-6">
        <h1 class="text-2xl font-black">Restore database backup #<?= $id ?></h1>
        <p class="mt-2 text-sm text-slate-600"><?= htmlspecialchars(formatPhilippineTime($backup['created_at'] . ' UTC'), ENT_QUOTES, 'UTF-8') ?> · <?= (int) $backup['row_count'] ?> saved rows</p>
        <p class="mt-4 text-sm">Restoring replaces current accounts, scholarship records, signup requests, reports, and audit history. A recovery backup is saved first and everyone is signed out.</p>
        <form method="post" class="mt-5 space-y-4">
            <input type="hidden" name="action" value="restore">
            <input type="hidden" name="id" value="<?= $id ?>">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
            <label class="block text-sm font-semibold">Type RESTORE to confirm
                <input name="confirmation" required pattern="RESTORE" maxlength="7" autocomplete="off" class="mt-1 block w-full rounded-lg border px-3 py-2">
            </label>
            <button type="submit" class="rounded-xl bg-rose-700 px-4 py-2 text-sm font-bold text-white hover:bg-rose-800">Restore backup</button>
        </form>
    </section>
</main>
</body>
</html>
