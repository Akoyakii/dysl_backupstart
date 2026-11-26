<div id="sidebarEdge" aria-hidden="true"></div>
<aside id="sidebar" inert aria-label="Workspace navigation" class="fixed inset-y-0 left-0 z-50 flex w-72 flex-col border-r border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
    <div class="workspace-sidebar-brand flex h-20 shrink-0 items-center gap-3 border-b border-slate-200 px-6 dark:border-slate-800">
        <div class="h-11 w-11 flex-shrink-0 flex items-center justify-center">
            <img src="<?= $config['logo_path'] ?>" alt="DYCI Logo" class="h-full w-full object-contain drop-shadow-sm">
        </div>
        <div><b>DYSL</b><div class="text-xs text-slate-500">Scholarship Library</div></div>
    </div>
    <div class="min-h-0 flex-1 overflow-y-auto py-4">
        <nav aria-label="Scholarship workspace">
            <?php foreach (['dashboard' => 'Dashboard', 'scholars' => 'Scholars &amp; Discounts', 'scholarships' => 'Scholarship Library', 'reports' => 'Reports &amp; Records'] as $view => $label): ?>
                <?php if (isset($workspacePageTitle)): ?>
                    <a href="index#<?= $view ?>" class="nav<?= ($workspaceActiveView ?? '') === $view ? ' active' : '' ?>"
                        <?= ($workspaceActiveView ?? '') === $view ? 'aria-current="location"' : '' ?>><?= $label ?></a>
                <?php else: ?>
                    <button type="button" data-view="<?= $view ?>" class="nav<?= $view === 'dashboard' ? ' active' : '' ?>"><?= $label ?></button>
                <?php endif; ?>
            <?php endforeach; ?>
        </nav>
        <nav class="mt-5" aria-label="Administration">
            <?php if ($isAdmin): ?>
                <?php if (isset($workspacePageTitle)): ?><a href="index#activity" class="nav">Audit Logs</a>
                <?php else: ?><button type="button" data-view="activity" class="nav">Audit Logs</button><?php endif; ?>
                <a href="/user-management" class="nav<?= ($workspaceActiveView ?? '') === 'user-management' ? ' active' : '' ?>"<?= ($workspaceActiveView ?? '') === 'user-management' ? ' aria-current="page"' : '' ?>>User Management</a>
            <?php endif; ?>
            <?php if ($isAdmin): ?>
                <?php if (isset($workspacePageTitle)): ?><a href="index#settings" class="nav">Settings</a>
                <?php else: ?><button type="button" data-view="settings" class="nav">Settings</button><?php endif; ?>
            <?php endif; ?>
        </nav>
    </div>
<div class="shrink-0 border-t border-slate-200 dark:border-slate-800">
    <div class="flex items-center justify-between bg-slate-50 px-6 py-4 dark:bg-slate-800">
        <div class="flex items-center gap-3">
            <b class="text-sm"><?= accountRoleLabel($account['role']) ?></b>
        </div>
        <form method="post" action="logout">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
        <button type="submit" aria-label="Log out" class="rounded-lg p-2 text-slate-600 hover:text-red-700 dark:text-slate-300" title="Log out">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path></svg>
        </button>
        </form>
    </div>
</div>
</aside>
