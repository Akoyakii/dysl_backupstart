<?php
require __DIR__ . '/includes/security.php';
require __DIR__ . '/includes/audit.php';
startRequest();

$method = $_SERVER['REQUEST_METHOD'];
if (!in_array($method, ['GET', 'POST'], true)) stopRequest(405, 'Method not allowed.');

try {
    $db = recordsDatabase();
    $admin = currentAccount($db);
    if (!$admin || $admin['role'] !== 'admin') stopRequest(403, 'Administrator access is required.');
    if (!requestLimit($db, 'user-management', 60, 60, (string) $admin['id'])) {
        stopRequest(429, 'Too many requests. Try again shortly.');
    }
    $search = is_string($_GET['q'] ?? null) ? substr(trim($_GET['q']), 0, 254) : '';
    $page = filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 1000000]]) ?: 1;
    $returnQuery = http_build_query(['q' => $search, 'page' => $page]);
    $pending = 'EXISTS (SELECT 1 FROM signup_requests
        WHERE signup_requests.email = CONVERT(accounts.email USING utf8mb4) COLLATE utf8mb4_unicode_ci)';
    if ($method === 'POST') {
        if (!validCsrf($_POST['csrf'] ?? null)) stopRequest(403, 'Reload the page and try again.');
        $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $version = filter_var($_POST['version'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
        $action = $_POST['action'] ?? null;
        if (!$id || $version === false || !in_array($action, ['toggle', 'temporary-password'], true)) {
            stopRequest(400, 'Choose a valid account action.');
        }
        $db->beginTransaction();
        $lookup = $db->prepare("SELECT email, disabled_at, access_version, verified_at, password_hash IS NOT NULL AS has_password
            FROM accounts WHERE id = ? AND role = 'staff' AND NOT $pending FOR UPDATE");
        $lookup->execute([$id]);
        $target = $lookup->fetch(PDO::FETCH_ASSOC);
        if (!$target || (int) $target['access_version'] !== $version) {
            $db->rollBack();
            stopRequest(409, 'This account cannot be edited or has changed. Reload the list and try again.');
        }
        $temporary = null;
        if ($action === 'toggle') {
            $disabled = $target['disabled_at'] === null;
            $save = $db->prepare('UPDATE accounts SET disabled_at = IF(?, UTC_TIMESTAMP(), NULL),
                access_version = access_version + 1 WHERE id = ?');
            $save->execute([(int) $disabled, $id]);
            writeAuditEvent($db, $admin, 'account.access_updated', [
                'before' => ['Account ID' => $id, 'Status' => $disabled ? 'Enabled' : 'Disabled'],
                'after' => ['Account ID' => $id, 'Status' => $disabled ? 'Disabled' : 'Enabled'],
            ]);
        } else {
            if ($target['disabled_at'] !== null || !$target['verified_at'] || !$target['has_password']) {
                $db->rollBack();
                stopRequest(409, 'Temporary passwords are available for enabled, activated Staff accounts.');
            }
            $temporary = 'Aa1!' . bin2hex(random_bytes(12));
            $save = $db->prepare('UPDATE accounts SET password_hash = ?, temporary_password_expires = UTC_TIMESTAMP() + INTERVAL 30 MINUTE,
                access_version = access_version + 1 WHERE id = ?');
            $save->execute([password_hash($temporary, PASSWORD_ARGON2ID), $id]);
            writeAuditEvent($db, $admin, 'account.temporary_password_issued', ['Account ID' => $id]);
        }
        $db->commit();
        $_SESSION['user_management_notice'] = $temporary === null ? 'Account access saved.' : 'Temporary password created.';
        unset($_SESSION['user_management_password']);
        if ($temporary !== null) $_SESSION['user_management_password'] = [$target['email'], $temporary, time() + 60];
        header('Location: ?' . $returnQuery, true, 303);
        exit;
    }

    $csrf = csrfToken();
    $notice = $_SESSION['user_management_notice'] ?? null;
    $temporary = $_SESSION['user_management_password'] ?? null;
    $offset = ($page - 1) * 15;
    $list = $db->prepare("SELECT id, email, role, verified_at, password_hash IS NOT NULL AS has_password,
        disabled_at, access_version, $pending AS pending FROM accounts
        WHERE LOCATE(?, email) > 0 OR ? = '' ORDER BY id DESC LIMIT 16 OFFSET $offset");
    $list->execute([$search, $search]);
    $accounts = $list->fetchAll(PDO::FETCH_ASSOC);
    $more = count($accounts) > 15;
    $accounts = array_slice($accounts, 0, 15);
    unset($_SESSION['user_management_notice'], $_SESSION['user_management_password']);
    session_write_close();
} catch (Throwable $error) {
    if (isset($db) && $db->inTransaction()) $db->rollBack();
    $code = $error instanceof PDOException ? (int) ($error->errorInfo[1] ?? 0) : 0;
    error_log('ScholarHub User Management request failed' . ($code ? ' (MySQL ' . $code . ')' : '') . '.');
    stopRequest(503, databaseSetupMessage($error) ?? 'User Management is temporarily unavailable.');
}

function userText(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
$workspacePageTitle = 'User Management';
$workspaceActiveView = 'user-management';
$account = $admin;
$isAdmin = true;
$canManageRecords = true;
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
    <div class="space-y-5 p-4 md:p-8">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <h1 class="text-balance text-3xl font-black tracking-tight">User Management</h1>
        <a href="signup-requests" class="rounded-xl bg-dyci-blue px-4 py-3 text-sm font-bold text-white hover:bg-dyci-blueDark">Review signup requests</a>
    </div>
    <?php if ($notice): ?><p role="status" class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900"><?= userText($notice) ?></p><?php endif; ?>
    <?php if ($temporary && $temporary[2] > time()): ?>
        <div class="rounded-xl border bg-white p-4 dark:border-slate-700 dark:bg-slate-900">
            <p class="font-semibold"><?= userText($temporary[0]) ?></p>
            <p class="mt-2 select-all break-all font-mono"><?= userText($temporary[1]) ?></p>
            <p class="mt-2 text-sm text-slate-600 dark:text-slate-300">Copy this password and share it privately. It is shown once and expires 30 minutes after creation.</p>
        </div>
    <?php endif; ?>
    <div class="rounded-2xl border bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
        <form method="get" class="flex flex-wrap items-end gap-2">
            <label class="min-w-0 flex-1 text-sm font-semibold">Search by email
                <input type="search" name="q" value="<?= userText($search) ?>" maxlength="254" class="mt-1 block w-full rounded-xl border bg-slate-50 px-3 py-2 font-normal dark:border-slate-700 dark:bg-slate-800">
            </label>
            <button class="rounded-xl border px-3 py-2 text-sm font-semibold dark:border-slate-700">Search</button>
        </form>
    </div>
    <section class="overflow-hidden rounded-2xl border bg-white dark:border-slate-800 dark:bg-slate-900" aria-label="Registered accounts">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-50 text-xs font-semibold text-slate-700 dark:bg-slate-800 dark:text-slate-200"><tr><th scope="col" class="px-5 py-4">Email</th><th scope="col" class="px-5 py-4">Role</th><th scope="col" class="px-5 py-4">Status</th><th scope="col" class="px-5 py-4">Manage access</th></tr></thead>
                <tbody>
                    <?php foreach ($accounts as $user):
                        $state = $user['disabled_at'] ? 'Disabled' : ($user['pending'] ? 'Pending approval' :
                            (!$user['verified_at'] || !$user['has_password'] ? 'Awaiting activation' : 'Active'));
                        ?>
                        <tr class="border-t dark:border-slate-800">
                            <th scope="row" class="p-4 font-semibold"><?= userText($user['email']) ?></th>
                            <td class="p-4"><?= userText(accountRoleLabel($user['role'])) ?></td>
                            <td class="p-4"><?= userText($state) ?></td>
                            <td class="p-4">
                                <?php if ($user['role'] === 'staff' && !$user['pending']): ?>
                                    <form method="post" action="?<?= userText($returnQuery) ?>" class="flex flex-wrap gap-2" aria-label="Manage <?= userText($user['email']) ?>">
                                        <input type="hidden" name="csrf" value="<?= userText($csrf) ?>">
                                        <input type="hidden" name="id" value="<?= (int) $user['id'] ?>">
                                        <input type="hidden" name="version" value="<?= (int) $user['access_version'] ?>">
                                        <button name="action" value="toggle" class="rounded-lg border px-3 py-1.5 font-semibold dark:border-slate-700"><?= $state === 'Disabled' ? 'Enable' : 'Disable' ?></button>
                                        <?php if ($state === 'Active'): ?>
                                            <button name="action" value="temporary-password" class="rounded-lg border px-3 py-1.5 font-semibold text-dyci-blue dark:border-slate-700 dark:text-blue-300">Temporary password</button>
                                        <?php endif; ?>
                                    </form>
                                <?php elseif ($state === 'Pending approval'): ?><a class="font-semibold text-dyci-blue" href="signup-requests">Review signup</a>
                                <?php else: ?><span class="text-slate-500"><?= $user['role'] === 'admin' ? 'Administrator account' : 'Awaiting activation' ?></span><?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$accounts): ?><tr><td colspan="4" class="p-5 text-slate-600">No matching accounts on this page.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php if ($page > 1 || $more): ?>
            <nav aria-label="Account pages" class="table-pagination">
                <span class="tabular-nums">Page <?= $page ?></span>
                <div class="flex gap-3 font-semibold text-dyci-blue">
                    <?php if ($page > 1): ?><a href="?<?= userText(http_build_query(['q' => $search, 'page' => $page - 1])) ?>">Previous</a><?php endif; ?>
                    <?php if ($more): ?><a href="?<?= userText(http_build_query(['q' => $search, 'page' => $page + 1])) ?>">Next</a><?php endif; ?>
                </div>
            </nav>
        <?php endif; ?>
    </section>
    </div>
</main>
<script nonce="<?= cspNonce() ?>" src="assets/js/core.js?v=<?= filemtime(__DIR__ . '/assets/js/core.js') ?>"></script>
<script nonce="<?= cspNonce() ?>">bindWorkspaceControls();</script>
</body>
</html>
