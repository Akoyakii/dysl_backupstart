<?php if ($canManageRecords): ?>
<div id="modal" class="fixed inset-0 z-[80] hidden items-center justify-center bg-slate-950/60 p-4">
<div class="scholar-form-panel flex w-full max-w-2xl flex-col overflow-hidden rounded-3xl border border-white/20 bg-white shadow-2xl dark:border-slate-700 dark:bg-slate-900">
<div class="shrink-0 border-b px-5 py-4 sm:px-6 dark:border-slate-800">
<div class="flex items-center justify-between gap-4"><div><h2 class="text-xl font-black tracking-tight">Add Student Scholar</h2><p class="mt-1 text-sm text-slate-500">Enter the student details below.</p></div><button type="button" data-click="closeModal" aria-label="Close dialog" class="rounded-xl border px-3 py-2 text-slate-500 hover:bg-slate-50 dark:border-slate-700 dark:hover:bg-slate-800">✕</button></div>
</div>
<form id="scholarForm" data-submit="submitScholar" class="flex min-h-0 flex-col">
<div id="scholarFormScroll" class="scholar-form-scroll min-h-0 space-y-5 overflow-y-auto p-5 sm:p-6">
<fieldset id="scholarIdentity" class="min-w-0 rounded-2xl border bg-slate-50/70 p-5 dark:border-slate-800 dark:bg-slate-800/40"><h3 class="mb-4 text-lg font-black">Student information</h3><div class="grid gap-4 md:grid-cols-2">
    
    <label class="block text-sm font-bold">First Name *<input name="firstName" required class="mt-2 w-full rounded-xl border bg-white px-4 py-3 font-normal dark:border-slate-700 dark:bg-slate-900" placeholder="e.g. Juan"></label>
    <label class="block text-sm font-bold">Middle Name<input name="middleName" class="mt-2 w-full rounded-xl border bg-white px-4 py-3 font-normal dark:border-slate-700 dark:bg-slate-900" placeholder="e.g. Santos"></label>
    <label class="block text-sm font-bold">Last Name *<input name="lastName" required class="mt-2 w-full rounded-xl border bg-white px-4 py-3 font-normal dark:border-slate-700 dark:bg-slate-900" placeholder="e.g. Dela Cruz"></label>
    <label class="block text-sm font-bold">Student ID *
        <input name="id" required inputmode="numeric" pattern="[0-9]{4}-[0-9]{5}" maxlength="10" data-input="formatStudentId"
            class="mt-2 w-full rounded-xl border bg-white px-4 py-3 font-normal tabular-nums dark:border-slate-700 dark:bg-slate-900" placeholder="2026-00125">
        <p id="studentIdDuplicateWarning" class="mt-2 hidden text-xs font-semibold text-rose-700 dark:text-rose-300" role="alert"></p>
    </label>

    <label class="block text-sm font-bold md:col-span-2">College / Department *
        <select name="course" data-change="updateProgramOptions" required class="mt-2 w-full rounded-xl border bg-white px-4 py-3 font-normal dark:border-slate-700 dark:bg-slate-900">
            <option value="" disabled selected>Select College</option>
            <option value="College of Accountancy">College of Accountancy</option>
            <option value="College of Art and Sciences">College of Art and Sciences</option>
            <option value="College of Business Administration">College of Business Administration</option>
            <option value="College of Computer Studies">College of Computer Studies</option>
            <option value="College of Education">College of Education</option>
            <option value="College of Health and Sciences">College of Health and Sciences</option>
            <option value="College of Hospitality Management and Tourism">College of Hospitality Management and Tourism</option>
            <option value="College of Maritime Education">College of Maritime Education</option>
            <option value="School of Mechanical Engineering">School of Mechanical Engineering</option>
            <option value="School of Psychology">School of Psychology</option>
        </select>
    </label>
    <label class="block text-sm font-bold"><span>Program / Course</span>
        <select name="program" class="mt-2 w-full rounded-xl border bg-white px-4 py-3 font-normal dark:border-slate-700 dark:bg-slate-900">
            <option value="">Select Program (Optional)</option>
            <option value="BSIT">BSIT — BS Information Technology</option>
            <option value="BSCS">BSCS — BS Computer Science</option>
            <option value="BSA">BSA — BS Accountancy</option>
            <option value="BSBA">BSBA — BS Business Administration</option>
            <option value="BSED">BSED — Bachelor of Secondary Education</option>
            <option value="BEED">BEED — Bachelor of Elementary Education</option>
            <option value="BSN">BSN — BS Nursing</option>
            <option value="BSHM">BSHM — BS Hospitality Management</option>
            <option value="BSTM">BSTM — BS Tourism Management</option>
            <option value="BSMT">BSMT — BS Marine Transportation</option>
            <option value="BSMarE">BSMarE — BS Marine Engineering</option>
            <option value="BSME">BSME — BS Mechanical Engineering</option>
            <option value="BS Psych">BS Psych — BS Psychology</option>
            <option value="SHS">SHS — Senior High School</option>
            <option value="JHS">JHS — Junior High School</option>
        </select>
    </label>
    <label class="block text-sm font-bold">Year Level *
        <select name="year" required class="mt-2 w-full rounded-xl border bg-white px-4 py-3 font-normal dark:border-slate-700 dark:bg-slate-900">
            <option value="" disabled selected>Select Year</option>
            <option value="1st Year">1st Year</option>
            <option value="2nd Year">2nd Year</option>
            <option value="3rd Year">3rd Year</option>
            <option value="4th Year">4th Year</option>
        </select>
    </label>

    <label class="block text-sm font-bold">School year
        <input name="period" value="<?= htmlspecialchars(currentSchoolYear($db), ENT_QUOTES, 'UTF-8') ?>" readonly class="mt-2 w-full rounded-xl border bg-slate-100 px-4 py-3 font-normal dark:border-slate-700 dark:bg-slate-800">
    </label>
    <label class="block text-sm font-bold">Semester *
        <?php $activeSemester = readSystemSettings($db)['activeSemester'] ?? '1st Semester'; ?>
        <select name="semester" disabled aria-readonly="true" class="mt-2 w-full rounded-xl border bg-slate-100 px-4 py-3 font-normal dark:border-slate-700 dark:bg-slate-800">
            <option value="" disabled>Select semester</option>
            <?php if (!in_array($activeSemester, SCHOLAR_SEMESTERS, true)): ?><option selected><?= htmlspecialchars($activeSemester, ENT_QUOTES, 'UTF-8') ?></option><?php endif; ?>
            <?php foreach (SCHOLAR_SEMESTERS as $semester): ?>
                <option <?= $semester === $activeSemester ? 'selected' : '' ?>><?= htmlspecialchars($semester, ENT_QUOTES, 'UTF-8') ?></option>
            <?php endforeach; ?>
        </select>
        <input type="hidden" name="semester" value="<?= htmlspecialchars($activeSemester, ENT_QUOTES, 'UTF-8') ?>">
    </label>
</div></fieldset>
<fieldset id="scholarEditIdentity" hidden disabled class="min-w-0 rounded-2xl border bg-slate-50/70 p-5 dark:border-slate-800 dark:bg-slate-800/40">
    <div class="grid gap-4 md:grid-cols-2">
        <label class="block text-sm font-bold">Full name
            <input name="editName" required maxlength="1024" class="mt-2 w-full rounded-xl border bg-white px-4 py-3 font-normal dark:border-slate-700 dark:bg-slate-900">
        </label>
        <label class="block text-sm font-bold">Student ID
            <input name="newId" required inputmode="numeric" data-input="formatStudentId" class="mt-2 w-full rounded-xl border bg-white px-4 py-3 font-normal tabular-nums dark:border-slate-700 dark:bg-slate-900" placeholder="2026-00125">
        </label>
    </div>
</fieldset>
<div class="rounded-2xl border bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
<h3 class="text-lg font-bold">Scholarship assignment *</h3>
<p id="selectedScholarshipCount" class="mt-1 text-sm text-slate-600 dark:text-slate-300" aria-live="polite">0 scholarships selected</p>
<details id="scholarshipDisclosure" class="group mt-3">
<summary class="inline-flex cursor-pointer list-none rounded-xl border px-4 py-2 text-sm font-semibold text-dyci-blue hover:bg-dyci-blue/5 dark:border-slate-700 dark:text-blue-300">
    <span class="group-open:hidden">Open scholarship picker</span><span class="hidden group-open:inline">Close scholarship picker</span>
</summary>
<div class="mt-3 rounded-xl border bg-slate-50 p-3 dark:border-slate-700 dark:bg-slate-800/40">
<div class="relative mb-3"><span class="absolute left-4 top-1/2 -translate-y-1/2 text-slate-400">⌕</span><input id="studentScholarshipSearch" data-input="renderScholarshipPicker" class="w-full rounded-xl border bg-slate-50 py-3 pl-11 pr-4 text-sm outline-none focus:border-dyci-blue dark:border-slate-700 dark:bg-slate-800" placeholder="Search scholarship by name, category, or benefit..."></div>
<div id="studentScholarshipPicker" class="scholarship-picker rounded-xl border bg-slate-50 dark:border-slate-700 dark:bg-slate-800"></div>
<button type="button" data-click="clearScholarshipSelection" class="mt-3 text-xs font-bold text-dyci-blue dark:text-blue-400">Clear selection</button>
</div>
</details>
<div id="selectedScholarshipInfo" class="mt-4 hidden rounded-2xl border border-dyci-blue/30 bg-dyci-blue/5 p-4"><div class="text-[10px] font-black uppercase tracking-widest text-dyci-blue dark:text-blue-400">Selected scholarships</div><div id="selectedScholarshipNames" class="mt-2 flex flex-wrap gap-2"></div></div>
</div>
<div class="rounded-2xl border bg-slate-50/70 p-5 dark:border-slate-800 dark:bg-slate-800/40"><div class="grid gap-4 sm:grid-cols-2"><label class="block text-sm font-bold">Scholarship status *<select name="status" required class="mt-2 w-full rounded-xl border bg-white px-4 py-3 dark:border-slate-700 dark:bg-slate-900"><option selected>Approved</option><option>Pending</option></select></label><label class="block text-sm font-bold">Amount waived (PHP)<input name="waivedAmount" type="number" min="0" step="0.01" placeholder="e.g. 12500.00" class="mt-2 w-full rounded-xl border bg-white px-4 py-3 font-normal tabular-nums dark:border-slate-700 dark:bg-slate-900"></label></div><p id="scholarshipRuleHint" class="mt-2 text-xs text-slate-500 dark:text-slate-400">In-house scholarships do not require approval; external grants (TES/TDP) require approval.</p></div>
</div>
<div class="flex shrink-0 flex-wrap items-center justify-end gap-3 border-t px-5 py-3 sm:px-6 dark:border-slate-800"><button type="button" data-click="closeModal" class="rounded-xl border px-4 py-2.5 font-bold dark:border-slate-700">Cancel</button><button type="submit" class="rounded-xl bg-dyci-blue px-5 py-2.5 font-bold text-white hover:bg-dyci-blueDark disabled:opacity-60">Save Student</button></div>
</form></div></div>

<?php endif; ?>
<!-- VIEW SCHOLAR MODAL -->
<div id="viewModal" class="fixed inset-0 z-[80] hidden items-center justify-center bg-slate-900/25 p-2 backdrop-blur-sm sm:p-4">
    <div class="max-h-[96vh] w-full max-w-6xl overflow-y-auto rounded-3xl border border-slate-200 bg-white shadow-2xl dark:border-slate-700 dark:bg-slate-900">
        <div class="sticky top-0 z-10 flex items-start justify-between border-b bg-white/95 px-5 py-3 backdrop-blur sm:px-7 dark:border-slate-200/10 dark:bg-slate-900/95">
            <div>
                <div class="flex items-center gap-2"><h2 class="text-base font-extrabold tracking-tight text-slate-800 dark:text-white">Scholar Details</h2><span class="rounded bg-slate-100 px-1.5 py-0.5 text-[9px] font-bold text-slate-500 dark:bg-slate-800">AY 2025–2026</span></div>
                <p class="mt-0.5 text-[10px] text-slate-500">Complete student scholarship profile and grant status overview</p>
            </div>
            <button type="button" data-click="closeViewModal" class="rounded-xl border px-3 py-2 text-slate-500 hover:bg-slate-50 dark:border-slate-700 dark:hover:bg-slate-800">✕</button>
        </div>
        <div class="px-6 py-5 sm:px-8">
            <div class="flex flex-wrap items-center justify-between gap-4 border-b border-slate-100 pb-4 dark:border-slate-800">
                <label class="flex items-center gap-3 text-xs font-bold uppercase tracking-wide text-slate-500">School year <select id="viewSchoolYears" class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-bold shadow-sm dark:border-slate-700 dark:bg-slate-800"></select></label>
                <div class="flex items-center gap-3"><span class="text-xs font-bold uppercase tracking-wide text-slate-400">Term</span><div id="viewSemesters" class="flex flex-wrap gap-2"></div></div>
            </div>
            <div class="mt-4 grid overflow-hidden border border-slate-100 dark:border-slate-800 sm:grid-cols-[1.12fr_.88fr]">
                <div class="space-y-4 border-b p-4 sm:border-b-0 sm:border-r sm:p-5">
                    <h3 class="text-lg font-black" id="viewName"></h3>
                    <div class="grid gap-x-6 gap-y-4 text-sm sm:grid-cols-2"><div><span class="text-slate-500">First Name:</span><div id="viewFirstName" class="font-bold"></div></div><div><span class="text-slate-500">Middle Name:</span><div id="viewMiddleName" class="font-bold"></div></div><div><span class="text-slate-500">Last Name:</span><div id="viewLastName" class="font-bold"></div></div><div><span class="text-slate-500">School ID:</span><div id="viewId" class="font-bold"></div></div><div><span class="text-slate-500">Email:</span><div class="font-bold">—</div></div><div><span class="text-slate-500">Phone Number:</span><div class="font-bold">—</div></div><div class="sm:col-span-2"><span class="text-slate-500">Address:</span><div class="font-bold">—</div></div><div class="sm:col-span-2"><span class="text-slate-500">Course / Year:</span><div id="viewCourse" class="font-bold"></div></div><div><span class="text-slate-500">Status:</span><div><span id="viewStatus" class="font-bold"></span></div></div><div><span class="text-slate-500">Last updated:</span><div id="viewUpdated" class="font-bold"></div></div></div>
                </div>
                <div class="bg-slate-50/50 p-5 dark:bg-slate-800/20 sm:p-6"><div id="viewTermDetails" class="h-full space-y-3"></div><div id="viewDiscountHistory" hidden></div></div>
            </div>
        </div>
    </div>
</div>
