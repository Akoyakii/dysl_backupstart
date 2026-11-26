<section id="reports" class="view">
<div class="mb-6"><h1 class="text-3xl font-black">Reports & Analytics</h1><p class="text-slate-500">Track which scholarship programs are mainly availed and how assignments change over time.</p></div>
<?php if ($canManageRecords): ?>
<a href="report-archive" class="mb-5 inline-block rounded-xl bg-dyci-blue px-4 py-3 text-sm font-bold text-white hover:bg-dyci-blueDark">Generate or open archived reports</a>
<details class="mb-5 rounded-2xl border bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
    <summary class="cursor-pointer font-bold text-dyci-blue dark:text-blue-300">Import student records from Excel</summary>
    <p class="mt-2 text-sm text-slate-600 dark:text-slate-300">Use the Scholar Master List CSV as your column template. The import reads the first sheet and adds new student IDs.</p>
    <form data-submit="importScholarSpreadsheet" enctype="multipart/form-data" method="post" class="mt-3 flex flex-wrap items-end gap-3">
        <label class="text-sm font-semibold">Excel workbook or CSV
            <input name="spreadsheet" type="file" accept=".xlsx,.csv" required class="mt-1 block max-w-full text-sm">
        </label>
        <button type="submit" class="rounded-xl bg-dyci-blue px-4 py-2 text-sm font-bold text-white hover:bg-dyci-blueDark disabled:opacity-60">Import records</button>
    </form>
</details>
<details class="mb-6 rounded-2xl border bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
    <summary class="cursor-pointer font-bold text-dyci-blue dark:text-blue-300">Open reports</summary>
    <p class="mt-2 text-sm text-slate-600 dark:text-slate-300">Select a report to view its rows and export it as CSV.</p>
    <div class="mt-4 grid gap-3 sm:grid-cols-2">
        <a href="report-archive?preview=scholar-master-list#report-preview" class="rounded-xl border p-3 font-semibold text-dyci-blue hover:border-dyci-blue dark:border-slate-700 dark:text-blue-300">Scholar Master List</a>
        <a href="report-archive?preview=scholarship-summary#report-preview" class="rounded-xl border p-3 font-semibold text-dyci-blue hover:border-dyci-blue dark:border-slate-700 dark:text-blue-300">Scholarship Summary</a>
        <a href="report-archive?preview=scholarship-types-summary#report-preview" class="rounded-xl border p-3 font-semibold text-dyci-blue hover:border-dyci-blue dark:border-slate-700 dark:text-blue-300">Scholarship Types Summary <span class="block text-sm font-normal text-slate-600 dark:text-slate-300">Coverage, benefit rates, status counts, and amount waived</span></a>
        <?php if ($isAdmin): ?><a href="report-archive?preview=activity-history#report-preview" class="rounded-xl border p-3 font-semibold text-dyci-blue hover:border-dyci-blue dark:border-slate-700 dark:text-blue-300">Activity History <span class="block text-sm font-normal text-slate-600 dark:text-slate-300">Latest 100 audit events and earlier activity</span></a><?php endif; ?>
        <a href="report-archive?preview=requirement-report#report-preview" class="rounded-xl border p-3 font-semibold text-dyci-blue hover:border-dyci-blue dark:border-slate-700 dark:text-blue-300">Requirement Report <span class="block text-sm font-normal text-slate-600 dark:text-slate-300">Missing student record fields and scholarships</span></a>
    </div>
</details>
<details class="mb-6 rounded-2xl border bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
    <summary class="cursor-pointer font-bold text-dyci-blue dark:text-blue-300">Manage semesters</summary>
    <p class="mt-2 text-sm text-slate-600 dark:text-slate-300">Add a new school year and semester option for student records and reports.</p>
    <form id="addSemesterForm" data-submit="submitNewSemester" class="mt-3 flex flex-wrap items-end gap-3">
        <label class="text-sm font-semibold">School year
            <input name="schoolYear" required pattern="\d{4}[-–]\d{4}" placeholder="2026-2027" class="mt-1 block rounded-xl border bg-slate-50 px-3 py-2 text-sm tabular-nums dark:border-slate-700 dark:bg-slate-800">
        </label>
        <label class="text-sm font-semibold">Semester
            <input name="semester" required maxlength="20" placeholder="e.g. 1st Semester or Midyear" class="mt-1 block rounded-xl border bg-slate-50 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-800">
        </label>
        <button type="submit" class="rounded-xl bg-dyci-blue px-4 py-2 text-sm font-bold text-white hover:bg-dyci-blueDark disabled:opacity-60">Add semester</button>
    </form>
</details>
<?php endif; ?>
<div class="mb-5 rounded-2xl border bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
<div class="mb-3 flex flex-col justify-between gap-2 md:flex-row md:items-center"><div><b>Analytics Filters</b><p class="text-xs text-slate-500">Filter the scholarship usage charts by school year, semester, or scholarship.</p></div><button data-click="resetAnalyticsFilters" class="rounded-xl border px-3 py-2 text-xs font-bold hover:bg-slate-50 dark:border-slate-700 dark:hover:bg-slate-800">Reset filters</button></div>
<div class="grid gap-3 md:grid-cols-3">
<select id="analyticsYear" aria-label="School year" data-change="renderAnalytics" class="rounded-xl border bg-slate-50 px-4 py-3 text-sm dark:border-slate-700 dark:bg-slate-800"><option value="All">All School Years</option></select>
<select id="analyticsSemester" aria-label="Semester" data-change="renderAnalytics" class="rounded-xl border bg-slate-50 px-4 py-3 text-sm dark:border-slate-700 dark:bg-slate-800"><option value="All">All Semesters</option><option>1st Semester</option><option>2nd Semester</option><option>Summer</option></select>
<select id="analyticsScholarship" aria-label="Scholarship" data-change="renderAnalytics" class="rounded-xl border bg-slate-50 px-4 py-3 text-sm dark:border-slate-700 dark:bg-slate-800"><option value="">All Scholarships</option></select>
</div></div>
<div class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
<div class="rounded-2xl border bg-white p-5 dark:border-slate-800 dark:bg-slate-900"><span class="text-sm text-slate-500">Scholarship availments</span><div id="reportTotalScholars" class="mt-2 text-3xl font-black tabular-nums"><span class="sr-only">Loading</span><span class="skeleton" aria-hidden="true"></span></div></div>
<div class="rounded-2xl border bg-white p-5 dark:border-slate-800 dark:bg-slate-900"><span class="text-sm text-slate-500">Students covered</span><div id="reportApprovalRate" class="mt-2 text-3xl font-black tabular-nums"><span class="sr-only">Loading</span><span class="skeleton" aria-hidden="true"></span></div></div>
<div class="rounded-2xl border bg-white p-5 dark:border-slate-800 dark:bg-slate-900"><span class="text-sm text-slate-500">Programs available</span><div id="reportProgramCount" class="mt-2 text-3xl font-black tabular-nums"><span class="sr-only">Loading</span><span class="skeleton" aria-hidden="true"></span></div></div>
<div class="rounded-2xl border bg-white p-5 dark:border-slate-800 dark:bg-slate-900"><span class="text-sm text-slate-500">School years tracked</span><div id="reportActivityCount" class="mt-2 text-3xl font-black tabular-nums"><span class="sr-only">Loading</span><span class="skeleton" aria-hidden="true"></span></div></div>
</div>
<div class="grid gap-6 xl:grid-cols-2">
<div class="min-w-0 rounded-2xl border bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
    <b>Scholarship Usage</b>
    <p id="scholarshipUsageSummary" class="mt-1 text-xs text-slate-600 dark:text-slate-400"></p>
    <div id="scholarshipUsagePlot" class="relative mt-4 h-72" hidden><canvas id="scholarshipPieChart"></canvas></div>
</div>
<div class="rounded-2xl border bg-white p-6 dark:border-slate-800 dark:bg-slate-900"><b>Scholarship Availments Over Time</b><p class="mt-1 text-xs text-slate-500">Filtered assignment count by school year.</p><div class="chart-wrap mt-4"><canvas id="scholarshipLineChart"></canvas></div></div>
<div class="min-w-0 rounded-2xl border bg-white p-6 dark:border-slate-800 dark:bg-slate-900 xl:col-span-2">
    <h2 class="font-bold">Trends by Department</h2>
    <p id="departmentTrendSummary" class="mt-1 text-xs text-slate-600 dark:text-slate-400"></p>
    <div id="departmentTrendPlot" class="relative mt-4 h-64" hidden><canvas id="departmentTrendChart"></canvas></div>
</div>
<div class="min-w-0 rounded-2xl border bg-white p-6 dark:border-slate-800 dark:bg-slate-900 xl:col-span-2">
    <h2 class="font-bold">Scholarship Usage Details</h2>
    <div class="mt-4 grid gap-3 sm:grid-cols-[minmax(0,1fr)_minmax(0,16rem)]">
        <input id="scholarshipUsageDetailsSearch" type="search" aria-label="Search scholarship usage" data-input="renderScholarshipUsageDetails"
            placeholder="Search scholarships by name" class="w-full rounded-xl border bg-slate-50 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-800">
        <select id="scholarshipUsageCategory" aria-label="Scholarship category" data-change="renderScholarshipUsageDetails"
            class="w-full rounded-xl border bg-slate-50 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-800">
            <option value="">All categories</option>
        </select>
    </div>
    <div id="scholarshipAnalytics" class="mt-5 space-y-3"></div>
    <nav id="scholarshipUsagePagination" aria-label="Scholarship usage pages" class="table-pagination mt-4" hidden></nav>
</div>
</div>
<dialog id="scholarshipUsageDialog" aria-labelledby="scholarshipUsageHeading" aria-describedby="scholarshipUsageRosterCount"
    class="m-auto w-[calc(100%_-_2rem)] max-w-4xl overflow-hidden rounded-2xl border bg-white p-0 text-slate-900 shadow-xl backdrop:bg-slate-950/60 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100">
    <div class="flex items-start justify-between gap-3 border-b p-4 sm:px-6 dark:border-slate-700">
        <div class="min-w-0">
            <h2 id="scholarshipUsageHeading" class="break-words text-xl font-bold"></h2>
            <p id="scholarshipUsageRosterCount" class="mt-1 text-sm tabular-nums text-slate-600 dark:text-slate-300"></p>
        </div>
        <form method="dialog"><button type="submit" class="rounded-lg border px-3 py-2 text-sm font-semibold dark:border-slate-600">Close</button></form>
    </div>
    <div class="flex flex-wrap items-center gap-3 border-b px-4 py-3 sm:px-6 dark:border-slate-700">
        <input id="scholarshipUsageSearch" type="search" autofocus aria-label="Search roster" data-input="renderScholarshipUsageRoster"
            placeholder="Search name, student ID, course, or status" class="min-w-0 flex-auto rounded-lg border px-3 py-2 text-sm dark:border-slate-700">
        <button type="button" id="exportScholarshipUsage" data-click="exportScholarshipUsage" disabled
            class="rounded-lg bg-dyci-blue px-3 py-2 text-sm font-semibold text-white hover:bg-dyci-blueDark disabled:opacity-50">Export CSV</button>
    </div>
    <div id="scholarshipUsageRosterScroll" tabindex="0" aria-label="Scholarship students" class="scholar-form-scroll max-h-[60dvh] overflow-auto">
        <table class="w-full text-left text-sm">
            <thead class="sticky top-0 bg-slate-50 text-slate-700 dark:bg-slate-800 dark:text-slate-200">
                <tr><th scope="col" class="px-4 py-3">Student</th><th scope="col" class="px-4 py-3">Course / year</th><th scope="col" class="px-4 py-3">School year</th><th scope="col" class="px-4 py-3">Semester</th><th scope="col" class="px-4 py-3">Current status</th></tr>
            </thead>
            <tbody id="scholarshipUsageRoster"></tbody>
        </table>
    </div>
    <nav id="scholarshipUsageRosterPages" aria-label="Scholarship student pages" class="table-pagination" hidden></nav>
</dialog>
</section>
