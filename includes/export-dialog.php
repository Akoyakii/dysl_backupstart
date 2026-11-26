<dialog id="exportDialog" aria-labelledby="exportDialogTitle"
    class="m-auto max-h-[90dvh] w-[calc(100%_-_2rem)] max-w-xl overflow-auto rounded-2xl border bg-white p-5 text-slate-900 shadow-xl backdrop:bg-slate-950/60 sm:p-6 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100">
    <header class="flex items-center justify-between gap-3 border-b pb-4 dark:border-slate-700">
        <h2 id="exportDialogTitle" class="text-xl font-bold"></h2>
        <button type="button" id="closeExportDialog" class="rounded-lg border px-3 py-2 text-sm font-semibold dark:border-slate-700">Close</button>
    </header>
    <form id="exportOptionsForm" class="mt-4 space-y-4">
        <p id="exportDialogSummary" class="text-sm text-slate-600 dark:text-slate-300"></p>
        <div id="exportFieldOptions" class="space-y-3" hidden>
            <div class="flex flex-wrap gap-2">
                <button type="button" id="selectExportFields" class="rounded-lg border px-3 py-1.5 text-sm dark:border-slate-700">Select all</button>
                <button type="button" id="clearExportFields" class="rounded-lg border px-3 py-1.5 text-sm dark:border-slate-700">Clear selection</button>
            </div>
            <fieldset class="rounded-xl border p-3 dark:border-slate-700">
                <legend class="px-1 text-sm font-semibold">Fields to include</legend>
                <div id="exportFields" class="grid gap-3 text-sm sm:grid-cols-2"></div>
            </fieldset>
            <p id="exportFieldsHint" class="text-sm text-slate-600 dark:text-slate-300" hidden>Select at least one field.</p>
        </div>
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div class="text-sm">
                <label for="exportFormatChoice" class="block font-semibold">File format</label>
                <span id="exportDialogFormat" class="text-slate-600 dark:text-slate-300"></span>
                <select id="exportFormatChoice" class="mt-1 rounded-lg border px-3 py-2 dark:border-slate-700" hidden></select>
            </div>
            <button id="downloadExport" type="submit" class="rounded-xl bg-dyci-blue px-4 py-2 text-sm font-bold text-white hover:bg-dyci-blueDark disabled:opacity-50">Download</button>
        </div>
    </form>
</dialog>
<script nonce="<?= cspNonce() ?>" src="assets/js/export.js?v=<?= filemtime(__DIR__ . '/../assets/js/export.js') ?>"></script>
