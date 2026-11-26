<?php

const BACKUP_COLUMNS = [
    'accounts' => 'id,email,role,disabled_at,access_version,temporary_password_expires,password_hash,verified_at',
    'records' => 'record_id,kind,student_id,payload',
    'signup_requests' => 'email,requested_at',
    'scholarship_types' => 'name,category,coverage,benefit',
    'report_archive' => 'id,report_type,generated_by,generated_at,payload',
    'audit_log' => 'id,actor_id,actor_role,action,details,occurred_at',
];

function lockDatabaseBackup(PDO $db): void
{
    if ($db->query('SELECT session_epoch FROM record_write_lock WHERE id = 1 FOR UPDATE')->fetchColumn() === false) {
        throw new RuntimeException('Database write lock unavailable.');
    }
}

function createDatabaseBackup(PDO $db, string $type): string
{
    $tables = [];
    $count = 0;
    foreach (BACKUP_COLUMNS as $table => $columns) {
        $tables[$table] = $db->query("SELECT $columns FROM $table")->fetchAll(PDO::FETCH_ASSOC);
        $count += count($tables[$table]);
    }
    $payload = gzencode(json_encode(['version' => 1, 'tables' => $tables], JSON_THROW_ON_ERROR));
    if ($payload === false) throw new RuntimeException('Backup compression failed.');
    $insert = $db->prepare('INSERT INTO database_backups (backup_type, created_at, row_count, payload)
        VALUES (?, UTC_TIMESTAMP(), ?, ?)');
    $insert->execute([$type, $count, $payload]);
    return $db->lastInsertId();
}

function restoreDatabaseBackup(PDO $db, int $id, array $admin): string
{
    $lookup = $db->prepare('SELECT payload FROM database_backups WHERE id = ?');
    $lookup->execute([$id]);
    $compressed = $lookup->fetchColumn();
    if ($compressed === false) throw new DomainException('Backup not found.');
    $json = gzdecode($compressed);
    $backup = $json === false ? [] : json_decode($json, true, 512, JSON_THROW_ON_ERROR);
    $tables = $backup['tables'] ?? [];
    if (($backup['version'] ?? null) !== 1 || array_keys($tables) !== array_keys(BACKUP_COLUMNS)) {
        throw new DomainException('This backup format cannot be restored.');
    }
    $auth = $db->prepare("SELECT password_hash FROM accounts WHERE id = ? AND role = 'admin' FOR UPDATE");
    $auth->execute([$admin['id']]);
    $currentHash = $auth->fetchColumn();
    $containsAdmin = false;
    foreach ($tables['accounts'] as $account) {
        if ((string) $account['id'] === (string) $admin['id'] && $account['email'] === $admin['email'] &&
            $account['role'] === 'admin' && $account['disabled_at'] === null && $account['verified_at'] &&
            $account['temporary_password_expires'] === null && $currentHash && $account['password_hash'] === $currentHash) {
            $containsAdmin = true;
            break;
        }
    }
    if (!$containsAdmin) throw new DomainException('This backup does not preserve your current administrator sign-in.');

    $recovery = createDatabaseBackup($db, 'recovery');
    $db->exec('DELETE FROM signup_verifications');
    foreach (array_reverse(array_keys(BACKUP_COLUMNS)) as $table) $db->exec("DELETE FROM $table");
    foreach (BACKUP_COLUMNS as $table => $columns) {
        $fields = explode(',', $columns);
        $params = implode(',', array_fill(0, count($fields), '?'));
        $insert = $db->prepare("INSERT INTO $table ($columns) VALUES ($params)");
        foreach ($tables[$table] as $row) {
            if ($table === 'accounts' && $row['temporary_password_expires'] !== null) {
                $row['temporary_password_expires'] = '2000-01-01 00:00:00';
            }
            $insert->execute(array_map(static fn(string $field) => $row[$field], $fields));
        }
    }
    $db->exec('UPDATE record_write_lock SET session_epoch = session_epoch + 1 WHERE id = 1');
    return $recovery;
}
