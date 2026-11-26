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
            <select id="filter" data-change="renderScholars" class="mt-1 block w-full rounded-xl border bg-slate-50 px-3 py-2 text-sm font-normal dark:border-slate-700 dark:bg-slate-800"><option value="All Status">All statuses</option><option>Approved</option><option>Pending</option><option>Not Approved</option></select>
        </label>
    </div>
</div>
<div class="overflow-hidden rounded-2xl border bg-white dark:border-slate-800 dark:bg-slate-900"><div class="overflow-x-auto"><table class="w-full text-left text-sm"><thead class="bg-slate-50 text-xs font-semibold text-slate-700 dark:bg-slate-800 dark:text-slate-200"><tr><th class="px-5 py-4">Student</th><th class="px-5 py-4">Course / Year</th><th class="px-5 py-4">Scholarship</th><th class="px-5 py-4">Status</th><th class="px-5 py-4">Updated</th><th class="px-5 py-4">Action</th></tr></thead><tbody id="scholarRows"></tbody></table></div><nav id="scholarPagination" aria-label="Student record pages" class="table-pagination" hidden></nav></div>
</section>
