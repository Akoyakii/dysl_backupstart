<section id="activity" class="view">
    <div class="mb-6 flex flex-wrap items-start justify-between gap-3">
        <h1 class="text-3xl font-black">Audit Logs</h1>
        <span id="auditDatabaseStatus" role="status" class="rounded-full border px-3 py-1 text-sm font-semibold">Database status pending</span>
    </div>
    <div class="mb-4 flex flex-wrap items-end gap-3">
        <label class="min-w-52 flex-1 text-sm font-semibold">Search audit logs
            <input id="auditSearch" type="search" data-input="renderActivity" class="mt-1 block w-full rounded-xl border bg-white px-3 py-2 dark:border-slate-700 dark:bg-slate-900">
        </label>
        <label class="text-sm font-semibold">Area
            <select id="auditArea" data-change="renderActivity" class="mt-1 block rounded-xl border bg-white px-3 py-2 dark:border-slate-700 dark:bg-slate-900">
                <option value="">All areas</option><option>Scholars</option><option>Accounts</option><option>Backups</option><option>Reports</option><option>System</option><option>Earlier activity</option>
            </select>
        </label>
        <button id="auditRefresh" type="button" data-click="loadAuditLog" class="rounded-xl border px-4 py-2 text-sm font-semibold dark:border-slate-700">Refresh logs</button>
    </div>
    <p id="auditResultCount" class="mb-3 text-sm text-slate-600 dark:text-slate-300" aria-live="polite"></p>
    <p id="auditError" class="mb-4 hidden rounded-xl border border-amber-300 p-3 text-sm" role="alert"></p>
    <div id="activityList" class="space-y-3"></div>
    <nav id="auditPagination" aria-label="Audit log pages" class="table-pagination" hidden></nav>
    <div id="earlierActivityList" class="space-y-3"></div>
    <nav id="earlierActivityPagination" aria-label="Earlier activity pages" class="table-pagination" hidden></nav>
    <dialog id="auditDetailsDialog" aria-labelledby="auditDetailsTitle"
        class="m-auto w-[calc(100%_-_2rem)] max-w-2xl overflow-hidden rounded-2xl border bg-white p-0 text-slate-900 shadow-xl backdrop:bg-slate-950/60 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100">
        <div class="flex items-center justify-between gap-3 border-b px-5 py-4 dark:border-slate-700">
            <h2 id="auditDetailsTitle" class="min-w-0 break-words text-xl font-bold"></h2>
            <form method="dialog"><button type="submit" autofocus class="rounded-lg border px-3 py-2 text-sm font-semibold dark:border-slate-700">Close</button></form>
        </div>
        <div id="auditDetailsBody" class="scholar-form-scroll max-h-[60dvh] space-y-3 overflow-y-auto px-5 py-4 text-sm"></div>
    </dialog>
</section>
