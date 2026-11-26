<?php
require __DIR__ . '/includes/security.php';
require __DIR__ . '/includes/audit.php';
require __DIR__ . '/includes/reports.php';
require __DIR__ . '/includes/spreadsheet-import.php';
startRequest();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function respond(int $status, array $body): void
{
    http_response_code($status);
    echo json_encode($body, JSON_THROW_ON_ERROR);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
if ($method !== 'GET' && $method !== 'POST') {
    respond(405, ['error' => 'Method not allowed.']);
}
if (!in_array($_SERVER['HTTP_SEC_FETCH_SITE'] ?? 'none', ['same-origin', 'none'], true)) {
    respond(403, ['error' => 'Cross-origin record requests are blocked.']);
}

try {
    $db = recordsDatabase();
    if (!requestLimit($db, 'records', 120, 60) ||
        ($method === 'POST' && !requestLimit($db, 'record-writes', 30, 60))) {
        respond(429, ['error' => 'Too many requests. Try again shortly.']);
    }
    $account = currentAccount($db);
    if (!$account) {
        respond(401, ['error' => 'Sign in to access student records.']);
    }
    $isAdmin = $account['role'] === 'admin';
    if (!$isAdmin && (isset($_GET['audit']) || ($_GET['report'] ?? null) === 'activity-history')) {
        respond(403, ['error' => 'Audit logs require administrator access.']);
    }
    if (($method === 'POST' || isset($_GET['backup']) || isset($_GET['report'])) && !canManageRecords($account['role'])) {
        respond(403, ['error' => 'This action requires Staff or Administrator access.']);
    }
    if ($method === 'POST' && !validCsrf($_SERVER['HTTP_X_RECORDS_TOKEN'] ?? null)) {
        respond(403, ['error' => 'Reload the page and try again.']);
    }
    $logRead = $method === 'GET' && !isset($_GET['audit']) && !isset($_GET['backup']) && !isset($_GET['report']) &&
        time() - ($_SESSION['last_records_audit'] ?? 0) >= 300;
    if ($logRead) $_SESSION['last_records_audit'] = time();
    session_write_close();
    if ($method === 'GET') {
        if (isset($_GET['audit'])) {
            respond(200, ['audit' => latestAuditEvents($db, $account['role'] === 'admin')]);
        }
        if (isset($_GET['report'])) {
            $report = $_GET['report'];
            if (!is_string($report) || !isset(REPORT_TITLES[$report])) {
                respond(400, ['error' => 'Choose a valid report.']);
            }
            $records = readRecords($db, $report === 'activity-history');
            $audit = $report === 'activity-history' ? latestAuditEvents($db) : [];
            $rows = reportRows($report, $records, $audit);
            $columns = reportExportColumns($_GET['columns'] ?? null, count($rows->current()));
            writeAuditEvent($db, $account, 'report.downloaded', ['Report' => REPORT_TITLES[$report]]);
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $report . '-' . gmdate('Y-m-d') . '.csv"');
            $stream = fopen('php://output', 'wb');
            fwrite($stream, "\xEF\xBB\xBF");
            writeReportCsv($stream, $rows, $columns);
            fclose($stream);
            exit;
        }
        $records = readRecords($db, $isAdmin);
        if (isset($_GET['backup'])) {
            writeAuditEvent($db, $account, 'backup.generated', [
                'Student records' => count($records['scholars']),
                'Discount records' => count($records['discountHistory']),
            ]);
            header('Content-Disposition: attachment; filename="scholarhub-' . gmdate('Y-m-d') . '.json"');
            respond(200, ['version' => 1, 'records' => $records]);
        }
        $records['catalog'] = scholarshipCatalog($db);
        $lock = dataEntryLockStatus($db);
        $records['dataEntryLocked'] = $lock['locked'];
        $records['dataEntryLockReason'] = $lock['reason'];
        $records['semesters'] = availableSemesters($db);
        $records['renewals'] = readRenewals($db);
        $records['systemSettings'] = readSystemSettings($db);
        if ($logRead) writeAccessAuditEvent($db, $account, 'records.viewed');
        respond(200, $records);
    }

    $contentType = strtolower(trim(explode(';', $_SERVER['CONTENT_TYPE'] ?? '', 2)[0]));
    if ($contentType === 'multipart/form-data') {
        $request = ['action' => 'import-spreadsheet'];
    } elseif ($contentType !== 'application/json') {
        respond(415, ['error' => 'Send records as JSON.']);
    } else {
        $maxBytes = 10 * 1024 * 1024;
        if ((int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > $maxBytes) {
            respond(413, ['error' => 'The upload exceeds 10 MB.']);
        }
        $body = file_get_contents('php://input', false, null, 0, $maxBytes + 1);
        if ($body === false || strlen($body) > $maxBytes) {
            respond(413, ['error' => 'The upload exceeds 10 MB.']);
        }
        $request = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
    }
    $action = $request['action'] ?? '';
    if (in_array($action, ['toggle-data-lock', 'save-system-settings'], true) && !$isAdmin) {
        respond(403, ['error' => 'Only administrators can update system settings.']);
    }
    if (!$isAdmin && dataEntryLockStatus($db)['locked']) {
        respond(403, ['error' => 'Staff data entry is currently locked by an administrator.']);
    }
    $batch = null;
    if ($action === 'import-spreadsheet' && $contentType === 'multipart/form-data') {
        $batch = spreadsheetScholarBatch($_FILES['spreadsheet'] ?? [], $db);
    } elseif ($action === 'add' && is_array($request['scholar'] ?? null)) {
        $batch = newScholarRecords($request['scholar'], $db);
    } elseif ($action === 'restore' && ($request['version'] ?? null) === 1 && is_array($request['records'] ?? null)) {
        $batch = $request['records'];
        if (!$isAdmin) $batch['activities'] = [];
    } elseif (!in_array($action, ['decide-scholar', 'edit-scholar', 'carry-over-renewals', 'decide-renewal', 'toggle-data-lock', 'save-system-settings', 'add-semester', 'toggle-scholarship-disabled', 'delete-scholarship'], true) &&
        !(in_array($action, ['add-scholarship', 'edit-scholarship'], true) && is_array($request['type'] ?? null))) {
        throw new InvalidArgumentException('Invalid records request.');
    }
    $kinds = ['scholars', 'discountHistory', 'activities'];
    foreach ($batch === null ? [] : $kinds as $kind) {
        $rows = $batch[$kind] ?? null;
        if (!is_array($rows) || !array_is_list($rows)) {
            throw new InvalidArgumentException('The record collection is invalid.');
        }
        foreach ($rows as $index => $row) {
            if (!is_array($row) || ($kind === 'activities' && !array_is_list($row))) {
                throw new InvalidArgumentException('The record is invalid.');
            }
            $batch[$kind][$index] = validateRecord($kind, $row);
        }
    }

    $db->beginTransaction();
    try {
        if ($db->query('SELECT id FROM record_write_lock WHERE id = 1 FOR UPDATE')->fetchColumn() === false) {
            throw new RuntimeException('The records write lock is missing.');
        }
        $activeAccount = currentAccount($db);
        if (!$activeAccount || !canManageRecords($activeAccount['role'])) {
            $db->rollBack();
            respond(403, ['error' => 'Your session changed. Sign in again.']);
        }
        if ($action === 'restore' && $db->query('SELECT EXISTS(SELECT 1 FROM records)')->fetchColumn()) {
            throw new DomainException('Restore and browser import require an empty database.');
        }
        if ($action === 'decide-scholar') {
            writeAuditEvent($db, $account, 'scholar.status_changed', decidePendingScholar($db, $request));
        } elseif ($action === 'edit-scholar') {
            $changes = editScholarAssignments($db, $request);
            if ($changes !== null) writeAuditEvent($db, $account, 'scholar.assignments_updated', $changes);
        } elseif ($action === 'carry-over-renewals') {
            $summary = carryOverRenewals($db, $request);
            writeAuditEvent($db, $account, 'renewal.carried_over', [
                'Target term' => $summary['targetYear'] . ' · ' . $summary['targetSemester'],
                'Created' => $summary['created'],
                'Already present' => $summary['skipped'],
            ]);
        } elseif ($action === 'decide-renewal') {
            writeAuditEvent($db, $account, 'renewal.status_changed', decideRenewal($db, $request));
        } elseif ($action === 'add-scholarship') {
            $type = saveScholarshipType($db, $request['type']);
            writeAuditEvent($db, $account, 'scholarship.created', ['Name' => $type['name'], 'Category' => $type['category']]);
        } elseif ($action === 'edit-scholarship') {
            $change = saveScholarshipType($db, $request['type'], true);
            if ($change !== null) writeAuditEvent($db, $account, 'scholarship.updated', $change);
        } elseif ($action === 'toggle-scholarship-disabled') {
            $change = toggleScholarshipTypeDisabled($db, (string) ($request['name'] ?? ''), (bool) ($request['disabled'] ?? false));
            writeAuditEvent($db, $account, 'scholarship.updated', $change);
        } elseif ($action === 'delete-scholarship') {
            $name = (string) ($request['name'] ?? '');
            deleteScholarshipType($db, $name);
            writeAuditEvent($db, $account, 'scholarship.deleted', ['Name' => $name]);
        } elseif ($action === 'toggle-data-lock') {
            $lockResult = setDataEntryLock($db, (bool) ($request['locked'] ?? false), (string) ($request['reason'] ?? ''));
            writeAuditEvent($db, $account, 'system.data_lock_changed', [
                'Status' => $lockResult['locked'] ? 'Locked' : 'Unlocked',
                'Reason' => $lockResult['reason'] ?: 'None',
            ]);
        } elseif ($action === 'save-system-settings') {
            writeAuditEvent($db, $account, 'system.settings_updated', saveSystemSettings($db, $request['settings'] ?? []));
        } elseif ($action === 'add-semester') {
            $addedSem = addSemester($db, (string) ($request['schoolYear'] ?? ''), (string) ($request['semester'] ?? ''));
            writeAuditEvent($db, $account, 'system.semester_added', [
                'School year' => $addedSem['school_year'],
                'Semester' => $addedSem['semester'],
            ]);
        } else {
            $insert = $db->prepare('INSERT INTO records (kind, student_id, payload) VALUES (?, ?, ?)');
            foreach ($kinds as $kind) {
                foreach (array_reverse($batch[$kind]) as $row) {
                    insertRecord($insert, $kind, $row);
                }
            }
            auditRecordWrite($db, $account, $action, $batch);
        }
        $db->commit();
    } catch (Throwable $error) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $error;
    }
    try {
        $records = readRecords($db, $isAdmin);
        $records['catalog'] = scholarshipCatalog($db);
        $lock = dataEntryLockStatus($db);
        $records['dataEntryLocked'] = $lock['locked'];
        $records['dataEntryLockReason'] = $lock['reason'];
        $records['semesters'] = availableSemesters($db);
        $records['renewals'] = readRenewals($db);
        $records['systemSettings'] = readSystemSettings($db);
    } catch (Throwable $error) {
        error_log('ScholarHub saved records but could not refresh the response.');
        respond(503, ['error' => 'Changes were saved. Reload the records to see the updated data.']);
    }
    respond(200, $records);
} catch (DomainException $error) {
    respond(409, ['error' => $error->getMessage()]);
} catch (InvalidArgumentException | JsonException $error) {
    respond(400, ['error' => $error instanceof JsonException ? 'Invalid JSON.' : $error->getMessage()]);
} catch (PDOException $error) {
    if ((int) ($error->errorInfo[1] ?? 0) === 1062) {
        respond(409, ['error' => match ($action ?? null) {
            'add-scholarship' => 'A scholarship with that name already exists.',
            'import-spreadsheet' => 'At least one student ID already exists. No spreadsheet rows were imported.',
            default => 'The student ID is already in use.',
        }]);
    }
    error_log('ScholarHub database request failed.');
    $setupMessage = databaseSetupMessage($error);
    respond($setupMessage === null ? 500 : 503, ['error' => $setupMessage ?? 'The database is unavailable.']);
} catch (Throwable $error) {
    error_log('ScholarHub records request failed.');
    respond(500, ['error' => 'The database is unavailable.']);
}
