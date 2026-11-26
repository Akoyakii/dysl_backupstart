<header class="sticky top-0 z-30 flex h-20 items-center justify-between border-b border-slate-200 bg-white/90 px-4 backdrop-blur md:px-8 dark:border-slate-800 dark:bg-slate-900/90">
    <div class="flex items-center gap-3">
        <button type="button" id="sidebarToggle" aria-label="Toggle navigation" aria-controls="sidebar" aria-expanded="false" class="workspace-icon-button rounded-xl border">
            <svg aria-hidden="true" viewBox="0 0 24 24"><path d="M5 7h14M5 12h14M5 17h14"/></svg>
        </button>
        <div>
            <b id="pageTitle"><?= htmlspecialchars($workspacePageTitle ?? 'Dashboard', ENT_QUOTES, 'UTF-8') ?></b>
            <div class="hidden text-xs text-slate-500 sm:block">Dr. Yanga Colleges Inc. · Internal scholarship records</div>
        </div>
    </div>
    <div class="flex items-center gap-2">
        <button type="button" id="themeToggle" class="workspace-icon-button rounded-xl border" title="Dark mode" aria-label="Dark mode" aria-pressed="false">
            <svg class="theme-icon-moon" viewBox="0 0 24 24" aria-hidden="true"><path d="M20.9 13.3A9 9 0 0 1 10.7 3.1a9 9 0 1 0 10.2 10.2Z"/></svg>
            <svg class="theme-icon-sun" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="4"/><path d="M12 2v2m0 16v2M2 12h2m16 0h2M5 5l1.5 1.5m11 11L19 19M5 19l1.5-1.5m11-11L19 5"/></svg>
        </button>

    </div>
</header>
