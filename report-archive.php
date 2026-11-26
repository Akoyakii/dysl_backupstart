<?php
require __DIR__ . '/includes/security.php';
require __DIR__ . '/includes/audit.php';
require __DIR__ . '/includes/reports.php';
startRequest();

$method = $_SERVER['REQUEST_METHOD'];
if (!in_array($method, ['GET', 'POST'], true)) {
    stopRequest(405, 'Method not allowed.');
}

try {
    $db = recordsDatabase();
    $account = currentAccount($db);
    if (!$account || !canManageRecords($account['role'])) {
        stopRequest(403, 'Staff or Administrator access is required.');
    }
    $isAdmin = $account['role'] === 'admin';
    $reportTitles = REPORT_TITLES;
    if (!$isAdmin) unset($reportTitles['activity-history']);
    if (!requestLimit($db, 'report-archive', 60, 60)) {
        stopRequest(429, 'Too many requests. Try again shortly.');
    }

    if ($method === 'POST') {
        if (!validCsrf($_POST['csrf'] ?? null)) {
            stopRequest(403, 'Reload the page and try again.');
        }
        $type = $_POST['type'] ?? '';
        $year = $_POST['year'] ?? '';
        $semester = $_POST['semester'] ?? '';
        if (!$isAdmin && $type === 'activity-history') {
            stopRequest(403, 'Audit logs require administrator access.');
        }
        $customTerms = array_column(availableSemesters($db), 'semester');
        $allTerms = array_values(array_unique([...SCHOLAR_SEMESTERS, ...$customTerms]));
        if (!is_string($type) || !isset(REPORT_TITLES[$type]) || !is_string($year) || strlen($year) > 64 ||
            !is_string($semester) || ($semester !== '' && !in_array($semester, $allTerms, true))) {
            stopRequest(400, 'Choose valid report filters.');
        }
        session_write_close();
        $records = readRecords($db, $type === 'activity-history');
        $audit = $type === 'activity-history' ? latestAuditEvents($db) : [];
        $snapshot = buildReportSnapshot($type, $records, $audit, canonicalReportYear($year), $semester);
        $db->beginTransaction();
        $insert = $db->prepare('INSERT INTO report_archive (report_type, generated_by, generated_at, payload)
            VALUES (?, ?, UTC_TIMESTAMP(), ?)');
        $insert->execute([$type, $account['id'], json_encode($snapshot, JSON_THROW_ON_ERROR)]);
        $id = $db->lastInsertId();
        writeAuditEvent($db, $account, 'report.archived', ['Report' => REPORT_TITLES[$type], 'Archive ID' => $id]);
        $db->commit();
        header('Location: report-archive?id=' . $id, true, 303);
        exit;
    }

    $csrf = csrfToken();
    session_write_close();
    $selected = null;
    $snapshot = null;
    if (isset($_GET['id'])) {
        $id = filter_var($_GET['id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($id === false) {
            stopRequest(400, 'Choose a valid archived report.');
        }
        $lookup = $db->prepare('SELECT archive.*, accounts.email AS actor_email
            FROM report_archive archive LEFT JOIN accounts ON accounts.id = archive.generated_by
            WHERE archive.id = ?');
        $lookup->execute([$id]);
        $selected = $lookup->fetch(PDO::FETCH_ASSOC);
        if (!$selected || !isset($reportTitles[$selected['report_type']])) {
            stopRequest(404, 'Archived report not found.');
        }
        $snapshot = json_decode($selected['payload'], true, 512, JSON_THROW_ON_ERROR);
        $timestampColumn = match ($selected['report_type']) {
            'scholar-master-list' => array_search('Last updated', $snapshot['columns'], true),
            'activity-history' => array_search('Timestamp', $snapshot['columns'], true),
            default => false,
        };
        if ($timestampColumn !== false) {
            foreach ($snapshot['rows'] as &$row) {
                if (is_string($row[$timestampColumn] ?? null)) {
                    $row[$timestampColumn] = formatPhilippineTime($row[$timestampColumn]);
                }
            }
            unset($row);
        }
        if ($selected['report_type'] === 'activity-history') {
            $detailsColumn = array_search('Details', $snapshot['columns'], true);
            $eventColumn = array_search('Event', $snapshot['columns'], true);
            if ($detailsColumn !== false && $eventColumn !== false) {
                foreach ($snapshot['rows'] as &$row) {
                    $details = json_decode((string) ($row[$detailsColumn] ?? ''), true);
                    if (!is_array($details)) continue;
                    $row[$detailsColumn] = formatReportAuditDetails($details);
                    $row[$eventColumn] = formatReportAuditLabel($row[$eventColumn]);
                }
                unset($row);
            }
        }
        $title = REPORT_TITLES[$selected['report_type']];
        $previewQuery = 'id=' . $id;
        $downloadUrl = 'report-archive?' . $previewQuery . '&download=1';
        if (isset($_GET['download'])) {
            try {
                $columns = reportExportColumns($_GET['columns'] ?? null, count($snapshot['columns']));
            } catch (InvalidArgumentException $error) {
                stopRequest(400, $error->getMessage());
            }
            writeAuditEvent($db, $account, 'report.downloaded', ['Report' => $title, 'Archive ID' => $id]);
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="scholarhub-report-' . $id . '.csv"');
            $stream = fopen('php://output', 'wb');
            fwrite($stream, "\xEF\xBB\xBF");
            writeReportCsv($stream, [$snapshot['columns']], $columns);
            writeReportCsv($stream, $snapshot['rows'], $columns);
            fclose($stream);
            exit;
        }
    } elseif (isset($_GET['preview'])) {
        $type = $_GET['preview'];
        if (!is_string($type) || !isset($reportTitles[$type])) {
            stopRequest(404, 'Report not found.');
        }
        $previewRecords = readRecords($db, $type === 'activity-history');
        $audit = $type === 'activity-history' ? latestAuditEvents($db) : [];
        $snapshot = buildReportSnapshot($type, $previewRecords, $audit, '', '');
        $title = $reportTitles[$type];
        $previewQuery = 'preview=' . rawurlencode($type);
        $downloadUrl = 'records?report=' . rawurlencode($type);
    }
    if ($snapshot !== null) {
        $reportRowCount = count($snapshot['rows']);
        $reportPageSize = 15;
        $reportPageCount = max(1, (int) ceil($reportRowCount / $reportPageSize));
        $requestedPage = filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $reportPage = min($requestedPage ?: 1, $reportPageCount);
        $reportOffset = ($reportPage - 1) * $reportPageSize;
    }

    $before = filter_var($_GET['before'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $list = $db->prepare("SELECT id, report_type, generated_at FROM report_archive
        WHERE (? = 1 OR report_type <> 'activity-history')" .
        ($before !== false ? ' AND id < ?' : '') . ' ORDER BY id DESC LIMIT 16');
    $list->execute($before !== false ? [(int) $isAdmin, $before] : [(int) $isAdmin]);
    $archives = $list->fetchAll(PDO::FETCH_ASSOC);
    $hasOlder = count($archives) > 15;
    $archives = array_slice($archives, 0, 15);
    $years = [];
    foreach ($previewRecords ?? readRecords($db, false) as $kind => $rows) {
        if ($kind === 'activities') continue;
        foreach ($rows as $row) {
            $year = canonicalReportYear($row['year'] ?? $row['period'] ?? '');
            if ($year !== '') $years[$year] = true;
        }
    }
    krsort($years, SORT_NATURAL);
    $allSemesters = array_values(array_unique([...SCHOLAR_SEMESTERS, ...array_column(availableSemesters($db), 'semester')]));
} catch (Throwable $error) {
    if (isset($db) && $db->inTransaction()) $db->rollBack();
    stopRequest(503, databaseSetupMessage($error) ?? 'The report archive is temporarily unavailable.');
}

function reportText(string|int $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}
$workspacePageTitle = 'Report Archive';
$workspaceActiveView = 'reports';
require __DIR__ . '/config.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <script nonce="<?= cspNonce() ?>">
        try { document.documentElement.classList.toggle("dark", localStorage.getItem("scholarhub_dark") === "true"); } catch {}
    </script>
    <?php require __DIR__ . '/includes/head.php'; ?>
</head>
<body class="scholar-workspace bg-slate-50 text-slate-800 dark:bg-slate-950 dark:text-slate-100">
<?php require __DIR__ . '/includes/sidebar.php'; ?>
<div id="overlay" class="fixed inset-0 z-40 hidden bg-black/40 lg:hidden"></div>
<main class="min-h-screen">
    <?php require __DIR__ . '/includes/header.php'; ?>
    <div class="space-y-6 p-4 md:p-8">
    <div class="space-y-4">
        <a href="index#reports" class="group inline-flex min-h-11 items-center gap-2 rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-dyci-blue transition-colors hover:border-dyci-blue hover:bg-blue-50 dark:border-slate-700 dark:bg-slate-900 dark:text-blue-300 dark:hover:bg-slate-800">
            <svg aria-hidden="true" class="h-4 w-4 transition-transform group-hover:-translate-x-0.5 group-focus-visible:-translate-x-0.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5m7-7-7 7 7 7"/></svg>
            Back to reports
        </a>
        <h1 class="text-balance text-3xl font-black tracking-tight">Report archive</h1>
    </div>
    <section class="rounded-2xl border bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
        <h2 class="font-bold">Generate a report</h2>
        <p class="mt-1 text-sm text-slate-600">Each report saves a copy of its results. School year and semester apply to academic reports.</p>
        <form method="post" action="report-archive" class="mt-4 grid items-end gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <input type="hidden" name="csrf" value="<?= reportText($csrf) ?>">
            <label class="text-sm font-semibold">Report
                <select name="type" class="mt-1 block w-full rounded-xl border px-3 py-2">
                    <?php foreach ($reportTitles as $key => $label): ?>
                        <option value="<?= reportText($key) ?>"><?= reportText($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="text-sm font-semibold">School year
                <select name="year" class="mt-1 block w-full rounded-xl border px-3 py-2">
                    <option value="">All school years</option>
                    <?php foreach (array_keys($years) as $year): ?><option><?= reportText($year) ?></option><?php endforeach; ?>
                </select>
            </label>
            <label class="text-sm font-semibold">Semester
                <select name="semester" class="mt-1 block w-full rounded-xl border px-3 py-2">
                    <option value="">All semesters</option>
                    <?php foreach ($allSemesters as $term): ?><option><?= reportText($term) ?></option><?php endforeach; ?>
                </select>
            </label>
            <button type="submit" class="rounded-xl bg-dyci-blue px-4 py-2.5 text-sm font-bold text-white hover:bg-dyci-blueDark">Generate report</button>
        </form>
    </section>
    <?php if ($snapshot !== null): ?>
        <section id="report-preview" class="scroll-mt-24 overflow-hidden rounded-2xl border bg-white dark:border-slate-800 dark:bg-slate-900">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b p-5 dark:border-slate-800">
                <div>
                    <h2 class="text-xl font-bold"><?= reportText($title) ?></h2>
                    <?php if ($selected): ?>
                        <p class="mt-1 text-sm text-slate-600"><?= reportText(formatPhilippineTime($selected['generated_at'] . ' UTC')) ?> · <?= reportText($selected['actor_email'] ?? ('Account #' . $selected['generated_by'])) ?></p>
                    <?php else: ?>
                        <p class="mt-1 text-sm text-slate-600">Live preview. The CSV uses the latest data at download time.</p>
                    <?php endif; ?>
                    <p class="text-sm text-slate-600"><?= reportText($snapshot['year'] ?: 'All school years') ?> · <?= reportText($snapshot['semester'] ?: 'All semesters') ?> · <?= count($snapshot['rows']) ?> rows</p>
                </div>
                <button type="button" data-export-url="<?= reportText($downloadUrl) ?>" data-export-title="<?= reportText($title) ?>"
                    data-export-fields="<?= reportText(json_encode($snapshot['columns'], JSON_THROW_ON_ERROR)) ?>"
                    data-export-summary="<?= reportText(($snapshot['year'] ?: 'All school years') . ' · ' . ($snapshot['semester'] ?: 'All semesters') . ' · ' . count($snapshot['rows']) . ' rows in preview') ?>"
                    class="rounded-xl bg-dyci-blue px-4 py-2.5 text-sm font-bold text-white hover:bg-dyci-blueDark">Export CSV</button>
            </div>
            <div class="scholar-form-scroll max-h-[55dvh] overflow-auto" role="region" aria-label="Report preview rows" tabindex="0">
                <table class="w-full text-left text-sm">
                    <thead class="sticky top-0 bg-slate-50 text-xs font-semibold text-slate-700 dark:bg-slate-800 dark:text-slate-200"><tr><?php foreach ($snapshot['columns'] as $column): ?><th scope="col" class="px-4 py-3"><?= reportText($column) ?></th><?php endforeach; ?></tr></thead>
                    <tbody class="divide-y dark:divide-slate-800">
                        <?php if (!$snapshot['rows']): ?>
                            <tr><td colspan="<?= count($snapshot['columns']) ?>" class="p-5 text-slate-600">No records match these filters.</td></tr>
                        <?php endif; ?>
                        <?php foreach (array_slice($snapshot['rows'], $reportOffset, $reportPageSize) as $row): ?>
                            <tr><?php foreach ($row as $cell): ?><td class="min-w-24 px-4 py-3 align-top"><?= reportText($cell) ?></td><?php endforeach; ?></tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php if ($reportPageCount > 1): ?>
                <nav aria-label="Archived report pages" class="table-pagination text-slate-700 dark:text-slate-200">
                    <span class="tabular-nums">Rows <?= $reportOffset + 1 ?>&ndash;<?= min($reportOffset + $reportPageSize, $reportRowCount) ?> of <?= $reportRowCount ?> &middot; Page <?= $reportPage ?> of <?= $reportPageCount ?></span>
                    <div class="flex gap-2">
                        <?php if ($reportPage > 1): ?><a class="rounded-lg border px-3 py-2 font-semibold" href="?<?= reportText($previewQuery . '&page=' . ($reportPage - 1)) ?>#report-preview">Previous</a><?php endif; ?>
                        <?php if ($reportPage < $reportPageCount): ?><a class="rounded-lg border px-3 py-2 font-semibold" href="?<?= reportText($previewQuery . '&page=' . ($reportPage + 1)) ?>#report-preview">Next</a><?php endif; ?>
                    </div>
                </nav>
            <?php endif; ?>
        </section>
    <?php endif; ?>
    <section class="rounded-2xl border bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
        <h2 class="mb-3 font-bold">Saved reports</h2>
        <?php if (!$archives): ?><p class="text-sm text-slate-600">No reports on this page.</p><?php endif; ?>
        <ul class="divide-y dark:divide-slate-800">
            <?php foreach ($archives as $archive): ?>
                <li class="flex flex-wrap justify-between gap-x-4 gap-y-1 py-3 text-sm">
                    <a class="font-semibold text-dyci-blue hover:underline" href="report-archive?id=<?= reportText($archive['id']) ?>"><?= reportText(REPORT_TITLES[$archive['report_type']]) ?></a>
                    <time class="tabular-nums text-slate-600"><?= reportText(formatPhilippineTime($archive['generated_at'] . ' UTC')) ?></time>
                </li>
            <?php endforeach; ?>
        </ul>
        <div class="mt-3 flex gap-4 text-sm font-semibold text-dyci-blue">
            <?php if ($before !== false): ?><a href="report-archive">Newest reports</a><?php endif; ?>
            <?php if ($hasOlder): ?><a href="report-archive?before=<?= reportText(end($archives)['id']) ?>">Older reports</a><?php endif; ?>
        </div>
    </section>
    </div>
</main>
<script nonce="<?= cspNonce() ?>" src="assets/js/core.js?v=<?= filemtime(__DIR__ . '/assets/js/core.js') ?>"></script>
<script nonce="<?= cspNonce() ?>">bindWorkspaceControls();</script>
<?php include __DIR__ . '/includes/export-dialog.php'; ?>
</body>
</html>
