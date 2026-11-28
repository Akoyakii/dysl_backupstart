<section id="scholarships" class="view">
<div class="mb-6 flex flex-col justify-between gap-4 md:flex-row md:items-end">
<div><h1 class="text-3xl font-black">Scholarship Library</h1><p class="text-slate-500">Complete reference list of scholarship and discount programs available to students.</p></div>
<div class="text-sm font-semibold tabular-nums text-slate-600 dark:text-slate-300"><span id="scholarshipCount">0</span> programs</div>
</div>
<?php if ($canManageRecords): ?>
<div id="addScholarshipPanel" class="mb-5 flex justify-end"><button type="button" data-click="openScholarshipCreate" class="rounded-xl bg-dyci-blue px-4 py-2 text-sm font-bold text-white">Add scholarship type</button></div>
<dialog id="editScholarshipDialog" aria-labelledby="editScholarshipHeading" class="m-auto max-h-[90vh] w-[calc(100%_-_2rem)] max-w-2xl overflow-y-auto rounded-2xl border bg-white p-5 text-slate-900 shadow-xl backdrop:bg-slate-950/60 sm:p-6 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100">
    <div class="mb-5 flex items-start justify-between gap-4"><div><h2 id="editScholarshipHeading" class="text-xl font-bold">Add scholarship type</h2><p class="mt-1 text-sm text-slate-500">Define the program, coverage, and benefit.</p></div><button type="button" data-click="resetScholarshipType" class="rounded-lg border px-3 py-1.5 text-sm font-semibold dark:border-slate-700">Close</button></div>
    <form id="scholarshipTypeForm" data-submit="submitScholarshipType" class="mt-4 grid gap-4 sm:grid-cols-2">
        <label class="text-sm font-semibold">Code / category
            <select id="newScholarshipCategory" name="category" required class="mt-1 block w-full rounded-xl border px-3 py-2 dark:border-slate-700 dark:bg-slate-800"><option value="">Select code</option></select>
        </label>
        <label class="text-sm font-semibold">Scholarship name
            <input name="name" required maxlength="120" placeholder="Full program name" class="mt-1 block w-full rounded-xl border px-3 py-2 dark:border-slate-700 dark:bg-slate-800">
        </label>
        <label class="text-sm font-semibold sm:col-span-2">Description
            <textarea name="description" required maxlength="1000" rows="2" placeholder="What this scholarship provides and who it is for" class="mt-1 block w-full rounded-xl border px-3 py-2 dark:border-slate-700 dark:bg-slate-800"></textarea>
        </label>
        <fieldset class="sm:col-span-2"><legend class="text-sm font-semibold">Coverage</legend><div id="scholarshipCoverageOptions" class="mt-2 grid gap-2 sm:grid-cols-3"></div></fieldset>
        <label class="text-sm font-semibold">Benefit type
            <select name="benefitType" required class="mt-1 block w-full rounded-xl border px-3 py-2 dark:border-slate-700 dark:bg-slate-800"><option value="percentage">Percentage</option><option value="amount">Fixed amount</option></select>
        </label>
        <label class="text-sm font-semibold">Benefit / rate
            <select id="scholarshipPercentageValue" name="benefitValue" required class="mt-1 block w-full rounded-xl border px-3 py-2 dark:border-slate-700 dark:bg-slate-800"></select><input id="scholarshipAmountValue" name="benefitValue" maxlength="80" inputmode="decimal" placeholder="Enter amount" class="mt-1 hidden w-full rounded-xl border px-3 py-2 dark:border-slate-700 dark:bg-slate-800" disabled>
        </label>
        <p id="scholarshipTypeError" role="alert" class="text-sm text-rose-700 dark:text-rose-300 sm:col-span-2" hidden></p>
        <div class="flex flex-wrap gap-3 sm:col-span-2">
            <button type="submit" class="rounded-xl bg-dyci-blue px-4 py-2 text-sm font-bold text-white hover:bg-dyci-blueDark disabled:opacity-60">Save scholarship type</button>
            <button id="cancelScholarshipEdit" type="button" data-click="resetScholarshipType" hidden class="rounded-xl border px-4 py-2 text-sm font-bold dark:border-slate-700">Cancel edit</button>
        </div>
    </form>
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
