<?php
require __DIR__ . '/includes/security.php';
startRequest();
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    stopRequest(405, 'Method not allowed.');
}
try {
    $db = recordsDatabase();
    if (!requestLimit($db, 'dashboard', 60, 60)) {
        stopRequest(429, 'Too many requests. Try again shortly.');
    }
    $account = currentAccount($db);
} catch (Throwable $error) {
    stopRequest(503, databaseSetupMessage($error) ?? 'The dashboard is temporarily unavailable.');
}
if (!$account) {
    header('Location: login');
    exit;
}
try {
    $catalog = scholarshipCatalog();
} catch (Throwable $error) {
    stopRequest(503, 'The scholarship catalog could not be loaded. Verify that includes/catalog.json is present and valid.');
}
$isAdmin = $account['role'] === 'admin';
$canManageRecords = canManageRecords($account['role']);
$csrf = csrfToken();
$backupNotice = $_SESSION['backup_notice'] ?? null;
unset($_SESSION['backup_notice']);
session_write_close();
require __DIR__ . '/config.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <script nonce="<?= cspNonce() ?>">
        try {
            document.documentElement.classList.toggle("dark", localStorage.getItem("scholarhub_dark") === "true");
        } catch {}
    </script>
    <?php include 'includes/head.php'; ?>
</head>
<body class="scholar-workspace bg-slate-50 text-slate-800 dark:bg-slate-950 dark:text-slate-100">

    <div id="toast" role="status" class="toast fixed bottom-5 right-5 z-[100] rounded-xl bg-dyci-blue px-5 py-3 text-sm font-bold text-white shadow-2xl"></div>

    <?php include 'includes/sidebar.php'; ?>

    <div id="overlay" data-click="toggleSidebar" class="fixed inset-0 z-40 hidden bg-black/40 lg:hidden"></div>

    <main class="min-h-screen">
        <?php include 'includes/header.php'; ?>

        <div class="p-4 md:p-8">
            <div id="recordsStatus" role="status" class="mb-4 hidden rounded-xl border border-amber-300 px-4 py-3 text-sm">
                <span>Student records could not be loaded.</span>
                <button type="button" data-click="loadDatabaseRecords" class="ml-2 font-semibold underline">Retry</button>
            </div>
            <?php include 'views/dashboard.php'; ?>
            <?php include 'views/scholars.php'; ?>
            <?php include 'views/scholarships.php'; ?>
            <?php include 'views/reports.php'; ?>
            <?php if ($isAdmin) include 'views/activity.php'; ?>
            <?php if ($isAdmin) include 'views/settings.php'; ?>
        </div>
    </main>

    <?php include 'includes/modal.php'; ?>

    <script nonce="<?= cspNonce() ?>">window.scholarshipCatalog = <?= json_encode($catalog, JSON_HEX_TAG | JSON_THROW_ON_ERROR) ?>;</script>
    <script nonce="<?= cspNonce() ?>" src="assets/js/data.js?v=<?= filemtime(__DIR__ . '/assets/js/data.js') ?>"></script>
    <script nonce="<?= cspNonce() ?>" src="assets/js/core.js?v=<?= filemtime(__DIR__ . '/assets/js/core.js') ?>"></script>
    <script nonce="<?= cspNonce() ?>" src="assets/js/scholars.js?v=<?= filemtime(__DIR__ . '/assets/js/scholars.js') ?>"></script>
    <script nonce="<?= cspNonce() ?>" src="assets/js/scholarships.js?v=<?= filemtime(__DIR__ . '/assets/js/scholarships.js') ?>"></script>
    <script nonce="<?= cspNonce() ?>" src="assets/js/reports.js?v=<?= filemtime(__DIR__ . '/assets/js/reports.js') ?>"></script>
    <script nonce="<?= cspNonce() ?>" src="assets/js/activity.js?v=<?= filemtime(__DIR__ . '/assets/js/activity.js') ?>"></script>

    <script nonce="<?= cspNonce() ?>">
        const recordsToken = <?= json_encode($csrf) ?>;
        bindRecordControls();
        const initialView = [...document.querySelectorAll("#sidebar [data-view]")]
            .find(button => "#" + button.dataset.view === location.hash);
        if (initialView) go(initialView.dataset.view);
        renderScholarships();
        loadDatabaseRecords();
    </script>
    <script nonce="<?= cspNonce() ?>" src="assets/js/roster-workbook.js?v=<?= filemtime(__DIR__ . '/assets/js/roster-workbook.js') ?>"></script>
    <?php include __DIR__ . '/includes/export-dialog.php'; ?>
</body>
</html>
