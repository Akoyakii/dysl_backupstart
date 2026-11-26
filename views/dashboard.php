    <section id="dashboard" class="view active">
        <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
            <h1 class="text-balance text-3xl font-black tracking-tight sm:text-4xl">Scholarship overview</h1>
            <?php if ($canManageRecords): ?>
            <button type="button" data-click="openModal" class="rounded-xl bg-dyci-blue px-5 py-3 text-sm font-bold text-white hover:bg-dyci-blueDark" title="Create a student profile and assign scholarships">Add scholar</button>
            <?php endif; ?>
        </div>
        <div class="mb-6 grid gap-4 sm:grid-cols-3">
            <div class="rounded-2xl border bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                <span class="text-sm font-medium text-slate-600 dark:text-slate-300">Student scholars</span>
                <div id="statScholars" class="mt-2 text-3xl font-bold tabular-nums"><span class="sr-only">Loading</span><span class="skeleton" aria-hidden="true"></span></div>
            </div>
            <div class="rounded-2xl border bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                <span class="text-sm font-medium text-slate-600 dark:text-slate-300">Scholarship programs</span>
                <div id="dashboardScholarshipCount" class="mt-2 text-3xl font-bold tabular-nums"><span class="sr-only">Loading</span><span class="skeleton" aria-hidden="true"></span></div>
            </div>
            <div class="rounded-2xl border bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                <span class="text-sm font-medium text-slate-600 dark:text-slate-300">Discount records</span>
                <div id="dashboardDiscountCount" class="mt-2 text-3xl font-bold tabular-nums"><span class="sr-only">Loading</span><span class="skeleton" aria-hidden="true"></span></div>
            </div>
        </div>
        <div class="mb-6 grid gap-4 sm:grid-cols-2">
            <section class="rounded-2xl border bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                <h2 class="font-bold">Scholar approval rate</h2>
                <p id="dashboardApprovalRate" class="mt-2 text-3xl font-bold tabular-nums"><span class="skeleton" aria-label="Loading"></span></p>
                <p id="dashboardApprovalSummary" class="mt-1 text-sm text-slate-600 dark:text-slate-300"></p>
            </section>
            <section class="rounded-2xl border bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                <h2 class="font-bold">Pending decisions</h2>
                <p id="dashboardPendingCount" class="mt-2 text-3xl font-bold tabular-nums"><span class="skeleton" aria-label="Loading"></span></p>
                <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">Students awaiting a scholarship decision</p>
            </section>
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
        <div class="overflow-hidden rounded-2xl border bg-white dark:border-slate-800 dark:bg-slate-900">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b p-5 dark:border-slate-800">
                <h2 class="text-xl font-bold">Recent scholar records</h2>
                <button type="button" data-view="scholars" class="text-sm font-bold text-dyci-blue hover:underline dark:text-blue-300">View all scholars</button>
            </div>
            <div id="recentTable" aria-live="polite"></div>
        </div>
    </section>
