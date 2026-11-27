<section id="scholars" class="view">
<div class="mb-6 flex flex-col justify-between gap-4 md:flex-row md:items-end"><h1 class="text-balance text-3xl font-black">Student scholarship records</h1><?php if ($canManageRecords): ?><button type="button" data-click="openModal" title="Create a student record and assign scholarships" class="rounded-xl bg-dyci-blue px-5 py-3 text-sm font-bold text-white hover:bg-dyci-blueDark">Add scholar</button><?php endif; ?></div>
<div class="mb-5 rounded-2xl border bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
    <label for="search" class="block text-sm font-semibold">Search student records</label>
    <input id="search" type="search" data-input="renderScholars" class="mt-1 w-full rounded-xl border bg-slate-50 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-800" placeholder="Name, student ID, course, or scholarship">
    <div class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <label class="text-sm font-semibold">School year
            <select id="scholarYear" data-change="renderScholars" class="mt-1 block w-full rounded-xl border bg-slate-50 px-3 py-2 text-sm font-normal dark:border-slate-700 dark:bg-slate-800"><option value="">All school years</option></select>
        </label>
        <label class="text-sm font-semibold">Semester
            <select id="scholarSemester" data-change="renderScholars" class="mt-1 block w-full rounded-xl border bg-slate-50 px-3 py-2 text-sm font-normal dark:border-slate-700 dark:bg-slate-800"><option value="">All semesters</option><?php foreach (SCHOLAR_SEMESTERS as $semester): ?><option><?= $semester ?></option><?php endforeach; ?></select>
        </label>
        <label class="min-w-0 text-sm font-semibold">College / department
            <select id="scholarDepartment" data-change="renderScholars" class="mt-1 block w-full rounded-xl border bg-slate-50 px-3 py-2 text-sm font-normal dark:border-slate-700 dark:bg-slate-800"><option value="">All colleges / departments</option></select>
        </label>
        <label class="text-sm font-semibold">Status
            <select id="filter" data-change="renderScholars" class="mt-1 block w-full rounded-xl border bg-slate-50 px-3 py-2 text-sm font-normal dark:border-slate-700 dark:bg-slate-800"><option value="All Status">All statuses</option><option>Approved</option><option>Pending</option></select>
        </label>
    </div>
</div>
<div class="overflow-hidden rounded-2xl border bg-white dark:border-slate-800 dark:bg-slate-900"><div class="overflow-x-auto"><table class="w-full text-left text-sm"><thead class="bg-slate-50 text-xs font-semibold text-slate-700 dark:bg-slate-800 dark:text-slate-200"><tr><th class="px-5 py-4">Student</th><th class="px-5 py-4">Course / Year</th><th class="px-5 py-4">Scholarship</th><th class="px-5 py-4">Status</th><th class="px-5 py-4">Updated</th><th class="px-5 py-4">Action</th></tr></thead><tbody id="scholarRows"></tbody></table></div><nav id="scholarPagination" aria-label="Student record pages" class="table-pagination" hidden></nav></div>

<?php if ($canManageRecords): ?>
<details id="semesterRenewalPanel" class="mt-6 rounded-2xl border bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
    <summary class="flex cursor-pointer flex-wrap items-center justify-between gap-2 font-bold text-dyci-blue dark:text-blue-300">
        <span>Semester renewal &amp; carry-over queue</span>
        <span id="renewalPendingBadge" class="rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-bold tabular-nums text-amber-800 dark:bg-amber-950 dark:text-amber-300">0 pending</span>
    </summary>
    <p class="mt-2 text-sm text-slate-600 dark:text-slate-300">Carry selected assignments into the next semester as Pending, creating a separate record for each student, scholarship, and term while preserving past approvals.</p>

    <div class="mt-5 grid gap-6 xl:grid-cols-2">
        <section class="rounded-2xl border bg-slate-50/70 p-4 dark:border-slate-800 dark:bg-slate-800/40">
            <h2 class="font-bold">1. Carry over assignments</h2>
            <div class="mt-3 grid gap-3 sm:grid-cols-3">
                <label class="text-xs font-semibold">Source school year
                    <select id="renewalSourceYear" data-change="renderRenewalSources" class="mt-1 block w-full rounded-xl border bg-white px-3 py-2 text-sm font-normal dark:border-slate-700 dark:bg-slate-900"><option value="">All school years</option></select>
                </label>
                <label class="text-xs font-semibold">Source semester
                    <select id="renewalSourceSemester" data-change="renderRenewalSources" class="mt-1 block w-full rounded-xl border bg-white px-3 py-2 text-sm font-normal dark:border-slate-700 dark:bg-slate-900"><option value="">All semesters</option><?php foreach (SCHOLAR_SEMESTERS as $semester): ?><option><?= $semester ?></option><?php endforeach; ?></select>
                </label>
                <label class="text-xs font-semibold">Search student / scholarship
                    <input id="renewalSourceSearch" type="search" data-input="renderRenewalSources" placeholder="Name, ID, or scholarship" class="mt-1 block w-full rounded-xl border bg-white px-3 py-2 text-sm font-normal dark:border-slate-700 dark:bg-slate-900">
                </label>
            </div>
            <div class="mt-3 flex flex-wrap items-center justify-between gap-2 text-xs">
                <label class="inline-flex cursor-pointer items-center gap-2 font-semibold">
                    <input id="renewalSelectAll" type="checkbox" data-change="toggleAllRenewalSources" class="accent-dyci-blue">
                    <span>Select all visible</span>
                </label>
                <span id="renewalSelectedCount" class="font-semibold tabular-nums text-slate-600 dark:text-slate-300">0 selected</span>
            </div>
            <div id="renewalSourceList" class="scholar-form-scroll mt-2 max-h-64 overflow-y-auto rounded-xl border bg-white dark:border-slate-700 dark:bg-slate-900"></div>
            <form id="carryOverRenewalForm" data-submit="submitCarryOverRenewals" class="mt-4 grid items-end gap-3 sm:grid-cols-3">
                <label class="text-xs font-semibold">Target school year *
                    <input name="targetYear" required pattern="\d{4}[-–]\d{4}" value="<?= currentSchoolYear() ?>" placeholder="2026-2027" class="mt-1 block w-full rounded-xl border bg-white px-3 py-2 text-sm font-normal tabular-nums dark:border-slate-700 dark:bg-slate-900">
                </label>
                <label class="text-xs font-semibold">Target semester *
                    <select name="targetSemester" required class="mt-1 block w-full rounded-xl border bg-white px-3 py-2 text-sm font-normal dark:border-slate-700 dark:bg-slate-900">
                        <?php foreach (SCHOLAR_SEMESTERS as $semester): ?><option><?= $semester ?></option><?php endforeach; ?>
                    </select>
                </label>
                <button type="submit" class="rounded-xl bg-dyci-blue px-4 py-2.5 text-sm font-bold text-white hover:bg-dyci-blueDark disabled:opacity-60">Carry over as Pending</button>
            </form>
        </section>

        <section class="rounded-2xl border bg-slate-50/70 p-4 dark:border-slate-800 dark:bg-slate-800/40">
            <h2 class="font-bold">2. Renewal queue</h2>
            <div class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <label class="text-xs font-semibold">Target year
                    <select id="renewalQueueYear" data-change="renderRenewalQueue" class="mt-1 block w-full rounded-xl border bg-white px-3 py-2 text-sm font-normal dark:border-slate-700 dark:bg-slate-900"><option value="">All years</option></select>
                </label>
                <label class="text-xs font-semibold">Target semester
                    <select id="renewalQueueSemester" data-change="renderRenewalQueue" class="mt-1 block w-full rounded-xl border bg-white px-3 py-2 text-sm font-normal dark:border-slate-700 dark:bg-slate-900"><option value="">All semesters</option><?php foreach (SCHOLAR_SEMESTERS as $semester): ?><option><?= $semester ?></option><?php endforeach; ?></select>
                </label>
                <label class="text-xs font-semibold">Status
                    <select id="renewalQueueStatus" data-change="renderRenewalQueue" class="mt-1 block w-full rounded-xl border bg-white px-3 py-2 text-sm font-normal dark:border-slate-700 dark:bg-slate-900"><option value="Pending" selected>Pending</option><option value="">All statuses</option><option>Approved</option></select>
                </label>
                <label class="text-xs font-semibold">Search queue
                    <input id="renewalQueueSearch" type="search" data-input="renderRenewalQueue" placeholder="Name, ID, scholarship" class="mt-1 block w-full rounded-xl border bg-white px-3 py-2 text-sm font-normal dark:border-slate-700 dark:bg-slate-900">
                </label>
            </div>
            <div class="mt-3 overflow-hidden rounded-xl border bg-white dark:border-slate-700 dark:bg-slate-900">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs sm:text-sm">
                        <thead class="bg-slate-50 text-xs font-semibold text-slate-700 dark:bg-slate-800 dark:text-slate-200">
                            <tr><th class="px-3 py-3">Student</th><th class="px-3 py-3">Scholarship</th><th class="px-3 py-3">Target term</th><th class="px-3 py-3">Status</th><th class="px-3 py-3">Action</th></tr>
                        </thead>
                        <tbody id="renewalQueueRows"></tbody>
                    </table>
                </div>
                <nav id="renewalPagination" aria-label="Renewal queue pages" class="table-pagination" hidden></nav>
            </div>
        </section>
    </div>
</details>
<?php endif; ?>
</section>
