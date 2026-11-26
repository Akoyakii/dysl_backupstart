<section id="scholarships" class="view">
<div class="mb-6 flex flex-col justify-between gap-4 md:flex-row md:items-end">
<div><h1 class="text-3xl font-black">Scholarship Library</h1><p class="text-slate-500">Complete reference list of scholarship and discount programs available to students.</p></div>
<div class="text-sm font-semibold tabular-nums text-slate-600 dark:text-slate-300"><span id="scholarshipCount">0</span> programs</div>
</div>
<?php if ($canManageRecords): ?>
<details id="addScholarshipPanel" class="mb-5 rounded-2xl border bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
    <summary class="cursor-pointer font-bold text-dyci-blue dark:text-blue-300">Add scholarship type</summary>
    <form id="scholarshipTypeForm" data-submit="submitScholarshipType" class="mt-4 grid gap-3 sm:grid-cols-2">
        <label class="text-sm font-semibold">Scholarship name
            <input name="name" required maxlength="120" class="mt-1 block w-full rounded-xl border px-3 py-2 dark:border-slate-700 dark:bg-slate-800">
        </label>
        <label class="text-sm font-semibold">Category
            <select id="newScholarshipCategory" name="category" required class="mt-1 block w-full rounded-xl border px-3 py-2 dark:border-slate-700 dark:bg-slate-800"><option value="">Select category</option></select>
        </label>
        <label class="text-sm font-semibold">Coverage
            <select id="newScholarshipCoverage" name="coverage" required class="mt-1 block w-full rounded-xl border px-3 py-2 dark:border-slate-700 dark:bg-slate-800"><option value="">Select coverage</option></select>
        </label>
        <label class="text-sm font-semibold">Benefit or rate
            <select id="newScholarshipBenefit" name="benefit" required class="mt-1 block w-full rounded-xl border px-3 py-2 dark:border-slate-700 dark:bg-slate-800"><option value="">Select benefit or rate</option></select>
        </label>
        <p id="scholarshipTypeError" role="alert" class="text-sm text-rose-700 dark:text-rose-300 sm:col-span-2" hidden></p>
        <div class="flex flex-wrap gap-3 sm:col-span-2">
            <button type="submit" class="rounded-xl bg-dyci-blue px-4 py-2 text-sm font-bold text-white hover:bg-dyci-blueDark disabled:opacity-60">Save scholarship type</button>
            <button id="cancelScholarshipEdit" type="button" data-click="resetScholarshipType" hidden class="rounded-xl border px-4 py-2 text-sm font-bold dark:border-slate-700">Cancel edit</button>
        </div>
    </form>
</details>
<dialog id="editScholarshipDialog" aria-labelledby="editScholarshipHeading" class="m-auto max-h-[90vh] w-[calc(100%_-_2rem)] max-w-xl overflow-y-auto rounded-2xl border bg-white p-5 text-slate-900 shadow-xl backdrop:bg-slate-950/60 sm:p-6 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100">
    <h2 id="editScholarshipHeading" class="text-xl font-bold">Edit scholarship type</h2>
</dialog>
<?php endif; ?>
<div class="mb-5 rounded-2xl border bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
<div class="grid gap-3 md:grid-cols-[1fr_220px_auto]">
<div class="relative"><span class="absolute left-4 top-1/2 -translate-y-1/2 text-slate-400">⌕</span><input id="scholarshipSearch" data-input="renderScholarships" class="w-full rounded-xl border bg-slate-50 py-3 pl-11 pr-4 text-sm outline-none focus:border-dyci-blue dark:border-slate-700 dark:bg-slate-800" placeholder="Search scholarship name, code, coverage, percentage..."></div>
<select id="scholarshipFilter" aria-label="Scholarship category" data-change="renderScholarships" class="rounded-xl border bg-slate-50 px-4 py-3 text-sm dark:border-slate-700 dark:bg-slate-800">
<option value="">All categories</option>
</select>
<div class="flex rounded-xl border dark:border-slate-700" role="radiogroup" aria-label="View mode">
    <button type="button" id="scholarshipViewTile" data-click="setScholarshipView" data-mode="tile" role="radio" aria-checked="true" class="rounded-l-xl px-3 py-2 text-sm font-semibold transition-colors" title="Tile view">▦ Tiles</button>
    <button type="button" id="scholarshipViewList" data-click="setScholarshipView" data-mode="list" role="radio" aria-checked="false" class="rounded-r-xl px-3 py-2 text-sm font-semibold transition-colors" title="List view">☰ List</button>
</div>
</div>
</div>
<div id="scholarshipCards" class="grid gap-4 md:grid-cols-2 xl:grid-cols-3"></div>
<nav id="scholarshipLibraryPagination" aria-label="Scholarship library pages" class="table-pagination mt-4" hidden></nav>
</section>
