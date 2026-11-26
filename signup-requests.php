<?php
require __DIR__ . '/includes/security.php';
require __DIR__ . '/includes/audit.php';
startRequest();

$method = $_SERVER['REQUEST_METHOD'];
if (!in_array($method, ['GET', 'POST'], true)) {
    stopRequest(405, 'Method not allowed.');
}
try {
    $db = recordsDatabase();
    $account = currentAccount($db);
    if (!$account || $account['role'] !== 'admin') {
        stopRequest(403, 'Administrator access is required.');
    }
    if (!requestLimit($db, 'signup-review', 60, 60)) {
        stopRequest(429, 'Too many requests. Try again shortly.');
    }
    if ($method === 'POST') {
        if (!validCsrf($_POST['csrf'] ?? null)) {
            stopRequest(403, 'Reload the page and try again.');
        }
        $email = $_POST['email'] ?? null;
        if (!is_string($email) || !validAccountEmail($email, true)) {
            stopRequest(400, 'Choose a valid signup request.');
        }
        $db->beginTransaction();
        $lookup = $db->prepare("SELECT id FROM accounts WHERE email = ? AND role = 'staff'
            AND verified_at IS NOT NULL AND password_hash IS NOT NULL FOR UPDATE");
        $lookup->execute([$email]);
        $requesterId = $lookup->fetchColumn();
        if ($requesterId !== false) {
            $delete = $db->prepare('DELETE FROM signup_requests WHERE email = ?');
            $delete->execute([$email]);
            if ($delete->rowCount() === 1) {
                writeAuditEvent($db, $account, 'account.signup_approved', [
                    'Account ID' => $requesterId, 'Role' => 'Staff',
                ]);
            }
        }
        $db->commit();
        header('Location: signup-requests', true, 303);
        exit;
    }
    $page = filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 1000000]]) ?: 1;
    $offset = ($page - 1) * 15;
    $requests = $db->query("SELECT signup_requests.email, requested_at FROM signup_requests
        JOIN accounts ON signup_requests.email = CONVERT(accounts.email USING utf8mb4) COLLATE utf8mb4_unicode_ci
        WHERE accounts.role = 'staff' AND accounts.verified_at IS NOT NULL AND accounts.password_hash IS NOT NULL
        ORDER BY requested_at ASC, signup_requests.email LIMIT 16 OFFSET $offset")
        ->fetchAll(PDO::FETCH_ASSOC);
    $more = count($requests) > 15;
    $requests = array_slice($requests, 0, 15);
    $csrf = csrfToken();
    session_write_close();
} catch (Throwable $error) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    stopRequest(503, databaseSetupMessage($error) ?? 'Signup requests are temporarily unavailable.');
}
?>
<!DOCTYPE html>
<html lang="en">
<head><?php require __DIR__ . '/includes/head.php'; ?></head>
<body class="min-h-screen bg-slate-50 text-slate-900">
    <div class="mx-auto max-w-3xl px-4 py-10 sm:px-6">
        <a href="index" class="text-sm font-semibold text-dyci-blue">Back to dashboard</a>
        <h1 class="mb-2 mt-5 text-3xl font-black">Signup requests</h1>
        <p class="mb-6 text-sm text-slate-600">Approve a verified account to grant Staff access.</p>
        <?php if (!$requests): ?>
            <p class="rounded-2xl border bg-white p-6 text-slate-600"><?= $page === 1 ? 'No pending signup requests.' : 'No pending requests on this page.' ?></p>
        <?php else: ?>
            <ul class="divide-y rounded-2xl border bg-white">
                <?php foreach ($requests as $request): ?>
                    <li class="flex flex-wrap items-center justify-between gap-3 p-4 sm:p-5">
                        <div class="min-w-0">
                            <p class="break-all font-semibold"><?= htmlspecialchars($request['email'], ENT_QUOTES, 'UTF-8') ?></p>
                            <p class="text-sm tabular-nums text-slate-600"><?= htmlspecialchars(formatPhilippineTime($request['requested_at'] . ' UTC'), ENT_QUOTES, 'UTF-8') ?></p>
                        </div>
                        <form method="post" action="signup-requests" class="flex flex-wrap items-center gap-2">
                            <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                            <input type="hidden" name="email" value="<?= htmlspecialchars($request['email'], ENT_QUOTES, 'UTF-8') ?>">
                            <button class="rounded-xl bg-dyci-blue px-4 py-2 text-sm font-bold text-white hover:bg-dyci-blueDark">Approve</button>
                        </form>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
        <?php if ($page > 1 || $more): ?>
            <nav aria-label="Signup request pages" class="table-pagination mt-4">
                <span class="tabular-nums">Page <?= $page ?></span>
                <div class="flex gap-3 font-semibold text-dyci-blue">
                    <?php if ($page > 1): ?><a href="?page=<?= $page - 1 ?>">Previous</a><?php endif; ?>
                    <?php if ($more): ?><a href="?page=<?= $page + 1 ?>">Next</a><?php endif; ?>
                </div>
            </nav>
        <?php endif; ?>
    </div>
</body>
</html>
