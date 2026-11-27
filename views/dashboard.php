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
        </div>
        <div class="mb-6 grid gap-4 xl:grid-cols-2">
            <section class="dashboard-chart-card min-w-0 rounded-2xl border bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                <h2 class="font-bold">Scholar decisions</h2>
                <p id="dashboardStatusSummary" class="mt-1 text-sm text-slate-600 dark:text-slate-300"></p>
                <p class="mt-1 text-xs font-medium text-slate-400">Source: current student scholar records</p>
                <div id="dashboardStatusPlot" class="relative mt-5 h-60" hidden><canvas id="dashboardStatusChart" aria-hidden="true"></canvas></div>
            </section>
            <section class="dashboard-chart-card min-w-0 rounded-2xl border bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                <h2 class="font-bold">Most-used scholarships</h2>
                <p id="dashboardUsageSummary" class="mt-1 text-sm text-slate-600 dark:text-slate-300"></p>
                <p class="mt-1 text-xs font-medium text-slate-400">Source: scholarship availment records</p>
                <div id="dashboardUsagePlot" class="relative mt-5 h-72" hidden><canvas id="dashboardUsageChart" aria-hidden="true"></canvas></div>
            </section>
        </div>
        <div class="mb-6 grid gap-4 xl:grid-cols-2">
            <section class="dashboard-chart-card min-w-0 rounded-2xl border bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                <h2 class="font-bold">Scholars by department</h2>
                <p id="dashboardDeptSummary" class="mt-1 text-sm text-slate-600 dark:text-slate-300"></p>
                <p class="mt-1 text-xs font-medium text-slate-400">Source: current student scholar records</p>
                <div id="dashboardDeptPlot" class="relative mt-5 h-72" hidden><canvas id="dashboardDeptChart" aria-hidden="true"></canvas></div>
            </section>
            <section class="dashboard-chart-card min-w-0 rounded-2xl border bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                <h2 class="font-bold">Coverage breakdown</h2>
                <p id="dashboardCoverageSummary" class="mt-1 text-sm text-slate-600 dark:text-slate-300"></p>
                <div id="dashboardCoveragePlot" class="relative mt-5 h-60" hidden><canvas id="dashboardCoverageChart" aria-hidden="true"></canvas></div>
            </section>
        </div>
        <div class="overflow-hidden rounded-2xl border bg-white dark:border-slate-800 dark:bg-slate-900">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b p-5 dark:border-slate-800">
                <h2 class="text-xl font-bold">Recent scholar records</h2>
                <button type="button" data-view="scholars" class="text-sm font-bold text-dyci-blue hover:underline dark:text-blue-300">View all scholars</button>
            </div>
            <div id="recentTable" aria-live="polite"></div>
        </div>
        <dialog id="dashboardChartDialog" class="w-[min(94vw,58rem)] rounded-2xl border border-slate-200 bg-white p-0 text-slate-800 shadow-2xl backdrop:bg-slate-950/50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100">
            <div class="flex items-start justify-between gap-4 border-b border-slate-200 p-5 dark:border-slate-700">
                <div><h2 id="dashboardChartDialogTitle" class="text-xl font-bold"></h2><p id="dashboardChartDialogSummary" class="mt-1 text-sm text-slate-500 dark:text-slate-300"></p></div>
                <button type="button" data-click="closeDashboardChartDetails" class="rounded-lg border px-3 py-1.5 text-sm font-semibold">Close</button>
            </div>
            <div id="dashboardChartDialogBody" class="max-h-[65vh] overflow-auto p-5"></div>
        </dialog>
    </section>
