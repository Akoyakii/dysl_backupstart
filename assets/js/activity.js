async function loadAuditLog() {
    const status = document.getElementById("auditDatabaseStatus");
    if (!status) return;
    const refresh = document.getElementById("auditRefresh");
    const errorBox = document.getElementById("auditError");
    if (refresh.disabled) return;
    refresh.disabled = true;
    errorBox.classList.add("hidden");
    status.textContent = "Checking database…";
    status.classList.remove("text-emerald-700", "dark:text-emerald-300", "text-amber-700", "dark:text-amber-300");
    try {
        const response = await fetch("records?audit=1", {cache: "no-store"});
        const result = await response.json();
        if (!response.ok || !Array.isArray(result.audit)) {
            throw new Error(result.error || "The server did not return audit logs.");
        }
        audit = result.audit;
        status.textContent = "Database connected";
        status.classList.add("text-emerald-700", "dark:text-emerald-300");
        renderActivity();
    } catch (error) {
        status.textContent = "Audit logs unavailable";
        status.classList.add("text-amber-700", "dark:text-amber-300");
        errorBox.textContent = error.message;
        errorBox.classList.remove("hidden");
    } finally {
        refresh.disabled = false;
    }
}

function renderActivity(){
    if (!document.getElementById("activityList")) return;
    const labels = {
        "scholar.created": "Scholar record created",
        "scholar.status_changed": "Scholar status decided",
        "scholar.assignments_updated": "Scholar record edited",
        "scholarship.created": "Scholarship type created",
        "scholarship.updated": "Scholarship type edited",
        "scholarship.deleted": "Scholarship type deleted",
        "system.data_lock_changed": "Staff data entry lock updated",
        "system.semester_added": "Semester added",
        "account.signup_approved": "Signup request approved",
        "account.access_updated": "Account access updated",
        "account.temporary_password_issued": "Temporary password issued",
        "account.temporary_password_changed": "Temporary password replaced",
        "account.activated": "Account activated",
        "account.signed_in": "Successful sign-in",
        "account.sign_in_failed": "Failed sign-in",
        "account.signed_out": "Signed out",
        "report.downloaded": "Report downloaded",
        "report.archived": "Report archived",
        "records.restored": "Records restored or imported",
        "records.viewed": "Student records loaded",
        "records.spreadsheet_imported": "Student spreadsheet imported",
        "backup.generated": "Backup generated",
        "backup.database_created": "Database backup created",
        "backup.database_restored": "Database backup restored"
    };
    const areas = {scholar: "Scholars", scholarship: "Scholars", account: "Accounts", records: "Backups", backup: "Backups", report: "Reports"};
    const high = new Set(["account.signup_approved", "records.restored", "backup.generated", "backup.database_restored"]);
    const dateFormat = new Intl.DateTimeFormat("en-PH", {dateStyle: "short", timeStyle: "short", timeZone: "Asia/Manila"});
    const search = document.getElementById("auditSearch").value.trim().toLowerCase();
    const area = document.getElementById("auditArea").value;
    const matches = (entryArea, text) => (!area || area === entryArea) && text.toLowerCase().includes(search);
    const events = audit.filter(event => {
        const eventArea = areas[event.action.split(".")[0]] || "System";
        return matches(eventArea, [eventArea, labels[event.action], event.action, event.actor_role, event.actor_id, event.actor_email,
            high.has(event.action) ? "High" : "Normal",
            event.occurred_at, JSON.stringify(event.details)].join(" "));
    });
    const earlier = activities.filter(entry => matches("Earlier activity", entry.join(" ")));
    document.getElementById("auditResultCount").textContent =
        `${events.length + earlier.length} of ${audit.length + activities.length} entries shown · Latest 100 audit events`;
    const page = tablePage("auditPagination", events, [search, area], renderActivity);
    const entries = page.map(event => {
        const eventArea = areas[event.action.split(".")[0]] || "System";
        const role = ({admin: "Administrator", staff: "Staff", viewer: "Viewer"})[event.actor_role] || "Viewer";
        const timestamp = new Date(event.occurred_at.replace(" ", "T") + "Z");
        const when = Number.isNaN(timestamp.getTime()) ? event.occurred_at : dateFormat.format(timestamp);
        const priority = high.has(event.action) ? "High" : "Normal";
        const badge = priority === "High" ? "bg-amber-100 text-amber-900 dark:bg-amber-950 dark:text-amber-200"
            : "bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-200";
        return `
            <tr class="border-t dark:border-slate-800">
                <td class="px-4 py-3"><span class="rounded-md px-2 py-1 text-xs font-semibold ${badge}">${priority}</span></td>
                <td class="whitespace-nowrap px-4 py-3 tabular-nums">${escapeHtml(when)}</td>
                <td class="px-4 py-3">${eventArea}</td>
                <td class="px-4 py-3 break-all">
                    ${event.actor_email ? `<span class="block font-semibold">${escapeHtml(event.actor_email)}</span>` : ""}
                    <span class="text-xs text-slate-600 dark:text-slate-300">${Number(event.actor_id) === 0 ? "Unauthenticated" : `${role} #${escapeHtml(event.actor_id)}`}</span>
                </td>
                <td class="px-4 py-3 font-semibold">${escapeHtml(labels[event.action] ?? event.action)}</td>
                <td class="min-w-40 px-4 py-3">
                    <button type="button" aria-haspopup="dialog" data-click="openAuditDetails"
                        data-audit-index="${audit.indexOf(event)}" data-audit-label="${escapeHtml(labels[event.action] ?? event.action)}"
                        class="font-semibold text-dyci-blue hover:underline dark:text-blue-300">View details</button>
                </td>
            </tr>`;
    }).join("");
    const earlierPage = tablePage("earlierActivityPagination", earlier, [search, area], renderActivity);
    const legacy = earlierPage.length ? '<h2 class="pt-4 text-lg font-bold">Earlier activity</h2>' + earlierPage.map(a => `
        <div class="rounded-2xl border bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
            <small class="font-bold text-slate-400">${escapeHtml(a[0])}</small>
            <div class="mt-1 font-bold">${escapeHtml(a[1])}</div>
            <div class="text-sm text-slate-500">${escapeHtml(a[2])}</div>
        </div>`).join("") : "";
    const table = entries ? `<div class="overflow-x-auto rounded-2xl border bg-white dark:border-slate-800 dark:bg-slate-900">
        <table class="w-full text-left text-sm">
            <thead class="bg-slate-50 text-xs uppercase text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                <tr>${["Priority", "When (PHT)", "Business area", "Performed by", "What happened", "Details"]
                    .map(label => `<th scope="col" class="px-4 py-3">${label}</th>`).join("")}</tr>
            </thead>
            <tbody class="align-top">${entries}</tbody>
        </table>
    </div>` : "";
    document.getElementById("activityList").innerHTML = table || (legacy ? "" : `<div class="rounded-2xl border bg-white p-10 text-center text-slate-500 dark:border-slate-800 dark:bg-slate-900">${search || area ? "No logs match these filters." : "No audit entries yet."}</div>`);
    document.getElementById("earlierActivityList").innerHTML = legacy;
}

function openAuditDetails(index, label) {
    const event = audit[index];
    const dialog = document.getElementById("auditDetailsDialog");
    if (!event || !dialog || dialog.open) return;
    const details = event.details ?? {};
    const fields = values => {
        if (values === null) return '<p class="text-slate-600 dark:text-slate-300">No prior record.</p>';
        const entries = Object.entries(values ?? {});
        if (!entries.length) return '<p class="text-slate-600 dark:text-slate-300">No additional details recorded.</p>';
        return '<dl class="space-y-2">' + entries.map(([key, value]) => `
            <div><dt class="text-slate-600 dark:text-slate-300">${escapeHtml(key)}</dt>
            <dd class="break-words font-medium">${escapeHtml(Array.isArray(value) ? value.join(", ") : value)}</dd></div>
        `).join("") + '</dl>';
    };
    document.getElementById("auditDetailsTitle").textContent = label;
    const body = document.getElementById("auditDetailsBody");
    body.innerHTML = Object.hasOwn(details, "before") ? `
        ${details.reference ? `<p class="font-semibold">Student ID ${escapeHtml(details.reference)}</p>` : ""}
        ${details.Scholarship ? `<p class="font-semibold">${escapeHtml(details.Scholarship)}</p>` : ""}
        <div class="grid gap-4 sm:grid-cols-2">
            <section class="rounded-xl border p-3 dark:border-slate-700"><h3 class="mb-2 font-bold">Before</h3>${fields(details.before)}</section>
            <section class="rounded-xl border p-3 dark:border-slate-700"><h3 class="mb-2 font-bold">After</h3>${fields(details.after)}</section>
        </div>` : fields(details);
    dialog.showModal();
    body.scrollTop = 0;
}
