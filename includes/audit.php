<?php

function writeAuditEvent(PDO $db, array $actor, string $action, array $details = []): void
{
    $insert = $db->prepare('INSERT INTO audit_log (actor_id, actor_role, action, details, occurred_at)
        VALUES (?, ?, ?, ?, UTC_TIMESTAMP())');
    $insert->execute([$actor['id'], $actor['role'], $action, json_encode($details, JSON_THROW_ON_ERROR)]);
}

function writeSessionAuditEvent(PDO $db, array $actor, string $action): void
{
    try {
        writeAuditEvent($db, $actor, $action, [
            'Role' => accountRoleLabel($actor['role']),
            'Result' => 'Success',
        ]);
    } catch (Throwable $error) {
        error_log('ScholarHub could not write a session audit event.');
    }
}

function writeAccessAuditEvent(PDO $db, array $actor, string $action): void
{
    try {
        writeAuditEvent($db, $actor, $action);
    } catch (Throwable $error) {
        error_log('ScholarHub could not write an access audit event.');
    }
}

function latestAuditEvents(PDO $db, bool $showActorEmail = false): array
{
    $columns = 'logs.actor_id, logs.actor_role, logs.action, logs.details, logs.occurred_at';
    $source = 'audit_log logs';
    if ($showActorEmail) {
        $columns .= ', accounts.email AS actor_email';
        $source .= ' LEFT JOIN accounts ON accounts.id = logs.actor_id';
    }
    $rows = $db->query("SELECT $columns FROM $source ORDER BY logs.id DESC LIMIT 100")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as &$row) {
        $row['details'] = json_decode($row['details'], true, 512, JSON_THROW_ON_ERROR);
    }
    return $rows;
}

function auditRecordWrite(PDO $db, array $actor, string $action, array $batch): void
{
    if ($action === 'import-spreadsheet') {
        writeAuditEvent($db, $actor, 'records.spreadsheet_imported', ['Student records' => count($batch['scholars'])]);
        return;
    }
    if ($action === 'restore') {
        writeAuditEvent($db, $actor, 'records.restored', [
            'before' => ['Student records' => 0, 'Discount records' => 0, 'Legacy activity entries' => 0],
            'after' => [
                'Student records' => count($batch['scholars']),
                'Discount records' => count($batch['discountHistory']),
                'Legacy activity entries' => count($batch['activities']),
            ],
        ]);
        return;
    }
    $scholar = $batch['scholars'][0];
    writeAuditEvent($db, $actor, 'scholar.created', [
        'reference' => $scholar['id'],
        'before' => null,
        'after' => [
            'School year' => $scholar['period'],
            'Semester' => $batch['discountHistory'][0]['semester'],
            'Status' => $scholar['status'],
            'Scholarships' => $scholar['scholarship'],
        ],
    ]);
}
