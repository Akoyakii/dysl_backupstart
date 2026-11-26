<?php
$backupBefore = filter_var($_GET['backup-before'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$backupList = [];
$backupListAvailable = true;
try {
    $query = $db->prepare('SELECT id, backup_type, created_at, row_count FROM database_backups' .
        ($backupBefore ? ' WHERE id < ?' : '') . ' ORDER BY id DESC LIMIT 16');
    $query->execute($backupBefore ? [$backupBefore] : []);
    $backupList = $query->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $error) {
    $backupListAvailable = false;
}
$moreBackups = count($backupList) > 15;
$backupList = array_slice($backupList, 0, 15);
?>
<section id="settings" class="view">
    <h1 class="mb-6 text-balance text-3xl font-black">Settings &amp; Administration</h1>
    <div class="mb-6 rounded-2xl border bg-white p-5 dark:border-slate-800 dark:bg-slate-900 sm:p-6">
        <h2 class="text-lg font-bold">System settings</h2>
        <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">Configure active academic period, pagination, scholarship rules, and workspace defaults.</p>
        <form id="systemSettingsForm" data-submit="submitSystemSettings" class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <label class="text-sm font-semibold">Active school year
                <input name="activeSchoolYear" pattern="(\d{4}[-–]\d{4})?" placeholder="Auto (e.g. 2025-2026)" class="mt-1 block w-full rounded-xl border bg-slate-50 px-3 py-2 text-sm font-normal tabular-nums dark:border-slate-700 dark:bg-slate-800">
            </label>
            <label class="text-sm font-semibold">Active semester
                <select id="settingsActiveSemester" name="activeSemester" class="mt-1 block w-full rounded-xl border bg-slate-50 px-3 py-2 text-sm font-normal dark:border-slate-700 dark:bg-slate-800">
                    <?php foreach (SCHOLAR_SEMESTERS as $semester): ?><option><?= $semester ?></option><?php endforeach; ?>
                </select>
            </label>
            <label class="text-sm font-semibold">Rows per page
                <select name="pageSize" class="mt-1 block w-full rounded-xl border bg-slate-50 px-3 py-2 text-sm font-normal tabular-nums dark:border-slate-700 dark:bg-slate-800">
                    <option value="10">10 rows</option>
                    <option value="15" selected>15 rows (default)</option>
                    <option value="25">25 rows</option>
                    <option value="50">50 rows</option>
                </select>
            </label>
            <label class="text-sm font-semibold">TES / TDP max residency (years)
                <input name="maxResidencyYears" type="number" min="1" max="10" value="5" required class="mt-1 block w-full rounded-xl border bg-slate-50 px-3 py-2 text-sm font-normal tabular-nums dark:border-slate-700 dark:bg-slate-800">
            </label>
            <label class="text-sm font-semibold">Default in-house status
                <select name="inHouseDefaultStatus" class="mt-1 block w-full rounded-xl border bg-slate-50 px-3 py-2 text-sm font-normal dark:border-slate-700 dark:bg-slate-800">
                    <option value="Approved">Approved</option>
                    <option value="Pending">Pending</option>
                </select>
            </label>
            <label class="text-sm font-semibold">Workspace subtitle
                <input name="workspaceSubtitle" required maxlength="120" value="Dr. Yanga Colleges Inc. · Internal scholarship records" class="mt-1 block w-full rounded-xl border bg-slate-50 px-3 py-2 text-sm font-normal dark:border-slate-700 dark:bg-slate-800">
            </label>
            <label class="flex items-center gap-2 text-sm font-semibold sm:col-span-2 lg:col-span-3">
                <input name="externalRequiresApproval" type="checkbox" checked class="accent-dyci-blue">
                <span>Require approval (default to Pending) for external grants (TES / TDP)</span>
            </label>
            <div class="flex flex-wrap gap-3 sm:col-span-2 lg:col-span-3">
                <button type="submit" class="rounded-xl bg-dyci-blue px-4 py-2.5 text-sm font-bold text-white hover:bg-dyci-blueDark disabled:opacity-60">Save system settings</button>
                <button type="button" data-click="resetSystemSettings" class="rounded-xl border px-4 py-2.5 text-sm font-bold hover:border-dyci-blue dark:border-slate-700">Reset to defaults</button>
            </div>
        </form>
    </div>
    <div class="mb-6 rounded-2xl border bg-white p-5 dark:border-slate-800 dark:bg-slate-900 sm:p-6">
        <h2 class="text-lg font-bold">Staff data entry lock</h2>
        <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">Lock staff data entry during reporting periods or at the end of a semester so staff cannot add or edit records. Administrators retain full access.</p>
        <div class="mt-4 flex flex-wrap items-center gap-4">
            <button type="button" data-click="toggleDataLock" id="dataLockToggleBtn" class="rounded-xl bg-dyci-blue px-4 py-2.5 text-sm font-bold text-white hover:bg-dyci-blueDark disabled:opacity-60">Lock staff data entry</button>
            <span id="dataLockStatusText" class="text-sm font-semibold text-slate-600 dark:text-slate-300">Checking lock status…</span>
        </div>
    </div>
    <h2 class="mb-4 text-xl font-black">Database backup and restoration</h2>
    <div class="rounded-2xl border bg-white p-5 dark:border-slate-800 dark:bg-slate-900 sm:p-6">
        <p class="text-sm text-slate-600 dark:text-slate-300">Save accounts, student records, scholarships, signup requests, reports, and audit history. Restoring replaces current records and signs everyone out.</p>
        <?php if ($backupNotice): ?><p role="status" class="mt-4 text-sm font-semibold text-emerald-800 dark:text-emerald-300"><?= htmlspecialchars($backupNotice, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
        <?php if (!$backupListAvailable): ?>
            <p role="alert" class="mt-4 text-sm text-amber-800 dark:text-amber-300">Database backups are unavailable. Check that the backup migration was imported.</p>
        <?php else: ?>
            <div class="my-4 flex flex-wrap gap-3">
                <form method="post" action="database-backup.php">
                    <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                    <button type="submit" name="action" value="create" class="rounded-xl bg-dyci-blue px-4 py-2.5 text-sm font-bold text-white hover:bg-dyci-blueDark">Create manual backup</button>
                </form>
                <form method="get" action="index#settings">
                    <button type="submit" class="rounded-xl border px-4 py-2.5 text-sm font-bold hover:border-dyci-blue dark:border-slate-700">Refresh backups</button>
                </form>
            </div>
            <?php if (!$backupList): ?>
                <p class="text-sm text-slate-600 dark:text-slate-300">No database backups on this page.</p>
            <?php else: ?>
                <div class="overflow-x-auto rounded-xl border dark:border-slate-700">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-slate-50 text-xs font-semibold dark:bg-slate-800">
                            <tr><th scope="col" class="p-3">Backup</th><th scope="col" class="p-3">Created (PHT)</th><th scope="col" class="p-3">Rows</th><th scope="col" class="p-3">Action</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($backupList as $backup): ?>
                                <tr class="border-t dark:border-slate-700">
                                    <td class="p-3 font-semibold"><?= htmlspecialchars(ucfirst($backup['backup_type']), ENT_QUOTES, 'UTF-8') ?> #<?= (int) $backup['id'] ?></td>
                                    <td class="p-3 tabular-nums"><?= htmlspecialchars(formatPhilippineTime($backup['created_at'] . ' UTC'), ENT_QUOTES, 'UTF-8') ?></td>
                                    <td class="p-3 tabular-nums"><?= number_format((int) $backup['row_count']) ?></td>
                                    <td class="p-3"><a href="database-backup.php?id=<?= (int) $backup['id'] ?>" class="font-semibold text-dyci-blue hover:underline dark:text-blue-300">Restore</a></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php if ($backupBefore || $moreBackups): ?>
                        <nav aria-label="Backup pages" class="table-pagination font-semibold text-dyci-blue dark:text-blue-300">
                            <?php if ($backupBefore): ?><a href="index#settings">Newest backups</a><?php endif; ?>
                            <?php if ($moreBackups): ?><a href="index?backup-before=<?= (int) end($backupList)['id'] ?>#settings">Older backups</a><?php endif; ?>
                        </nav>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
        <p class="mt-4 text-xs text-slate-600 dark:text-slate-300">Weekly backups require a hosting scheduler. Site files and configuration require a separate hosting backup.</p>
    </div>
</section>
