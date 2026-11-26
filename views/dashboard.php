    <section id="dashboard" class="view active">
        <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
            <h1 class="text-balance text-3xl font-black tracking-tight sm:text-4xl">Scholarship overview</h1>
            <?php if ($canManageRecords): ?>
            <button type="button" data-click="openModal" class="rounded-xl bg-dyci-blue px-5 py-3 text-sm font-bold text-white hover:bg-dyci-blueDark" title="Create a student profile and assign scholarships">Add scholar</button>
            <?php endif; ?>
        </div>
        <div id="dashboardLockBanner" class="mb-4 hidden rounded-xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm dark:border-amber-700 dark:bg-amber-950" role="status"></div>
        <div class="mb-6 grid gap-4 sm:grid-cols-3">
            <button type="button" data-click="dashboardCardNav" data-target="scholars" class="dashboard-card rounded-2xl border bg-white p-5 text-left transition-colors hover:bg-blue-50 dark:border-slate-800 dark:bg-slate-900 dark:hover:bg-slate-800" title="View all student scholars">
                <span class="text-sm font-medium text-slate-600 dark:text-slate-300">Student scholars</span>
                <div id="statScholars" class="mt-2 text-3xl font-bold tabular-nums"><span class="sr-only">Loading</span><span class="skeleton" aria-hidden="true"></span></div>
                <p class="mt-1 text-xs text-slate-400">Distinct students with scholarship records</p>
            </button>
            <button type="button" data-click="dashboardCardNav" data-target="scholarships" class="dashboard-card rounded-2xl border bg-white p-5 text-left transition-colors hover:bg-blue-50 dark:border-slate-800 dark:bg-slate-900 dark:hover:bg-slate-800" title="View scholarship library">
                <span class="text-sm font-medium text-slate-600 dark:text-slate-300">Scholarship programs</span>
                <div id="dashboardScholarshipCount" class="mt-2 text-3xl font-bold tabular-nums"><span class="sr-only">Loading</span><span class="skeleton" aria-hidden="true"></span></div>
                <p class="mt-1 text-xs text-slate-400">Available scholarship and discount types</p>
            </button>
            <button type="button" data-click="dashboardCardNav" data-target="reports" class="dashboard-card rounded-2xl border bg-white p-5 text-left transition-colors hover:bg-blue-50 dark:border-slate-800 dark:bg-slate-900 dark:hover:bg-slate-800" title="View reports and analytics">
                <span class="text-sm font-medium text-slate-600 dark:text-slate-300">Discount records</span>
                <div id="dashboardDiscountCount" class="mt-2 text-3xl font-bold tabular-nums"><span class="sr-only">Loading</span><span class="skeleton" aria-hidden="true"></span></div>
                <p class="mt-1 text-xs text-slate-400">Scholarship availment entries across all periods</p>
            </button>
        </div>
        <div class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <button type="button" data-click="dashboardCardNav" data-target="scholars" data-filter="Approved" class="dashboard-card rounded-2xl border bg-white p-5 text-left transition-colors hover:bg-emerald-50 dark:border-slate-800 dark:bg-slate-900 dark:hover:bg-slate-800" title="View approved scholars">
                <span class="text-sm font-medium text-slate-600 dark:text-slate-300">Approved</span>
                <p id="dashboardApprovedCount" class="mt-2 text-3xl font-bold tabular-nums text-emerald-700 dark:text-emerald-300"><span class="skeleton" aria-label="Loading"></span></p>
                <p id="dashboardApprovalRate" class="mt-1 text-xs text-slate-400"></p>
            </button>
            <button type="button" data-click="dashboardCardNav" data-target="scholars" data-filter="Pending" class="dashboard-card rounded-2xl border bg-white p-5 text-left transition-colors hover:bg-amber-50 dark:border-slate-800 dark:bg-slate-900 dark:hover:bg-slate-800" title="View pending scholars">
                <span class="text-sm font-medium text-slate-600 dark:text-slate-300">Pending decisions</span>
                <p id="dashboardPendingCount" class="mt-2 text-3xl font-bold tabular-nums text-amber-700 dark:text-amber-300"><span class="skeleton" aria-label="Loading"></span></p>
                <p class="mt-1 text-xs text-slate-400">Students awaiting a scholarship decision</p>
            </button>
            <button type="button" data-click="dashboardCardNav" data-target="scholars" data-filter="Not Approved" class="dashboard-card rounded-2xl border bg-white p-5 text-left transition-colors hover:bg-rose-50 dark:border-slate-800 dark:bg-slate-900 dark:hover:bg-slate-800" title="View not approved scholars">
                <span class="text-sm font-medium text-slate-600 dark:text-slate-300">Not approved</span>
                <p id="dashboardRejectedCount" class="mt-2 text-3xl font-bold tabular-nums text-rose-700 dark:text-rose-300"><span class="skeleton" aria-label="Loading"></span></p>
                <p class="mt-1 text-xs text-slate-400">Students whose applications were not approved</p>
            </button>
            <div class="rounded-2xl border bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                <span class="text-sm font-medium text-slate-600 dark:text-slate-300">School years tracked</span>
                <p id="dashboardYearCount" class="mt-2 text-3xl font-bold tabular-nums"><span class="skeleton" aria-label="Loading"></span></p>
                <p class="mt-1 text-xs text-slate-400">Distinct school years with scholarship records</p>
            </div>
        </div>
        <div class="mb-6 grid gap-4 xl:grid-cols-2">
            <section class="min-w-0 rounded-2xl border bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                <h2 class="font-bold">Scholar decisions</h2>
                <p id="dashboardStatusSummary" class="mt-1 text-sm text-slate-600 dark:text-slate-300"></p>
                <div id="dashboardStatusPlot" class="relative mt-4 h-52" hidden><canvas id="dashboardStatusChart" aria-hidden="true"></canvas></div>
            </section>
            <section class="min-w-0 rounded-2xl border bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                <h2 class="font-bold">Most-used scholarships</h2>
                <p id="dashboardUsageSummary" class="mt-1 text-sm text-slate-600 dark:text-slate-300"></p>
                <div id="dashboardUsagePlot" class="relative mt-4 h-52" hidden><canvas id="dashboardUsageChart" aria-hidden="true"></canvas></div>
            </section>
        </div>
        <div class="mb-6 grid gap-4 xl:grid-cols-2">
            <section class="min-w-0 rounded-2xl border bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                <h2 class="font-bold">Scholars by department</h2>
                <p id="dashboardDeptSummary" class="mt-1 text-sm text-slate-600 dark:text-slate-300"></p>
                <div id="dashboardDeptPlot" class="relative mt-4 h-52" hidden><canvas id="dashboardDeptChart" aria-hidden="true"></canvas></div>
            </section>
            <section class="min-w-0 rounded-2xl border bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                <h2 class="font-bold">Coverage breakdown</h2>
                <p id="dashboardCoverageSummary" class="mt-1 text-sm text-slate-600 dark:text-slate-300"></p>
                <div id="dashboardCoveragePlot" class="relative mt-4 h-52" hidden><canvas id="dashboardCoverageChart" aria-hidden="true"></canvas></div>
            </section>
        </div>
        <div class="overflow-hidden rounded-2xl border bg-white dark:border-slate-800 dark:bg-slate-900">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b p-5 dark:border-slate-800">
                <h2 class="text-xl font-bold">Recent scholar records</h2>
                <button type="button" data-view="scholars" class="text-sm font-bold text-dyci-blue hover:underline dark:text-blue-300">View all scholars</button>
            </div>
            <div id="recentTable" aria-live="polite"></div>
        </div>
    </section>
