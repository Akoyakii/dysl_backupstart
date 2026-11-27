function normalizeSchoolYear(year) {
    return (year || "").trim().replace(/^(\d{4})\s*[-–—]\s*(\d{4})$/, "$1–$2");
}

function scholarPeriod(scholar, history) {
    const years = [...new Set(history.map(record => normalizeSchoolYear(record.year)).filter(Boolean))];
    const year = normalizeSchoolYear(scholar.period) || (years.length === 1 ? years[0] : "");
    const terms = [...new Set(history.filter(record => normalizeSchoolYear(record.year) === year)
        .map(record => record.semester).filter(Boolean))];
    return {year, semester: scholar.semester || (terms.length === 1 ? terms[0] : "")};
}

function updateSelectOptions(id, label, values) {
    const select = document.getElementById(id);
    if (!select) return "";
    const options = JSON.stringify(values);
    if (select.dataset.options !== options) {
        const selected = select.value;
        select.replaceChildren(new Option(label, ""), ...values.map(value => new Option(value, value)));
        select.value = values.includes(selected) ? selected : "";
        select.dataset.options = options;
    }
    return select.value;
}

function renderScholars(){
    if (!recordsUpdatedAt && document.getElementById("scholarRows").hasAttribute("aria-busy")) return;
    const query = (document.getElementById("search")?.value || "").trim().toLowerCase();
    const status = document.getElementById("filter")?.value || "All Status";
    const semester = document.getElementById("scholarSemester")?.value || "";
    const historyById = new Map();
    for (const record of discountHistory) {
        if (!historyById.has(record.id)) historyById.set(record.id, []);
        historyById.get(record.id).push(record);
    }
    const entries = scholars.map(scholar => ({
        scholar,
        ...scholarPeriod(scholar, historyById.get(scholar.id) || []),
        department: (scholar.course || "").replace(/\s*[·•–—-]\s*\d+(?:st|nd|rd|th)\s+Year$/i, "").trim()
    }));
    const years = [...new Set(entries.map(entry => entry.year).filter(Boolean))].sort().reverse();
    const departments = [...new Set(entries.map(entry => entry.department).filter(Boolean))].sort((a, b) => a.localeCompare(b));
    const year = updateSelectOptions("scholarYear", "All school years", years);
    const department = updateSelectOptions("scholarDepartment", "All colleges / departments", departments);
    const matches = entries.filter(entry =>
        (!year || entry.year === year) && (!semester || entry.semester === semester) &&
        (!department || entry.department === department) &&
        (status === "All Status" || entry.scholar.status === status) &&
        [entry.scholar.name, entry.scholar.id, entry.scholar.course, ...scholarshipNames(entry.scholar)]
            .join(" ").toLowerCase().includes(query)
    );
    
    const canEdit = Boolean(document.getElementById("scholarForm"));
    const page = tablePage("scholarPagination", matches, [query, status, semester, year, department], renderScholars);
    document.getElementById("scholarRows").innerHTML = page.map(({scholar: s, year, semester}) => {
        let names = scholarshipNames(s);
        
        return `<tr class="border-t dark:border-slate-800">
            <td class="px-5 py-4"><b>${escapeHtml(s.name)}</b><div class="text-xs tabular-nums text-slate-600 dark:text-slate-400">${escapeHtml(s.id)}</div></td>
            <td class="px-5 py-4">${escapeHtml(s.course)}${s.program ? ` <span class="ml-1 rounded bg-slate-100 px-1.5 py-0.5 text-xs font-semibold text-slate-700 dark:bg-slate-800 dark:text-slate-300">${escapeHtml(s.program)}</span>` : ""}<div class="mt-1 text-xs text-slate-600 dark:text-slate-300">${escapeHtml([year, semester].filter(Boolean).join(" · ") || "School period not recorded")}</div></td>
            <td class="px-5 py-4"><div class="flex flex-wrap gap-1.5">${names.map(n => `<span class="rounded-full bg-dyci-blue/10 px-2 py-1 text-xs font-bold text-dyci-blue dark:bg-dyci-blue/30 dark:text-blue-300">${escapeHtml(n)}</span>`).join("")}</div></td>
            <td class="px-5 py-4">${scholarStatus(s.status)}</td>
            <td class="px-5 py-4 text-sm tabular-nums text-slate-600 dark:text-slate-400">${s.saving ? "Saving\u2026" : escapeHtml(formatPhilippineTime(s.updated))}</td>
            <td class="px-5 py-4">${s.saving ? "" : `<div class="flex flex-wrap gap-3">
                <button type="button" data-student-id="${escapeHtml(s.id)}" data-click="openViewModal" title="View student details" class="font-bold text-dyci-blue dark:text-blue-400 hover:underline">View</button>
                ${canEdit ? `<button type="button" data-student-id="${escapeHtml(s.id)}" data-click="editScholar" title="Edit student record" class="font-bold text-dyci-blue dark:text-blue-400 hover:underline">Edit</button>` : ""}
            </div>`}</td>
        </tr>`;
    }).join("") || `<tr><td colspan="6" class="p-10 text-center text-slate-500">No matching records.</td></tr>`;
}

function scholarStatus(status) {
    const colors = {
        Approved: "text-emerald-800 dark:text-emerald-300",
        Pending: "text-amber-800 dark:text-amber-300",
    };
    return `<span class="text-xs font-semibold ${colors[status] || ""}">${escapeHtml(status)}</span>`;
}

function renderRecent(){
    document.getElementById("recentTable").innerHTML = scholars.length ? scholars.slice(0,4).map(s => `
        <button type="button" data-click="openRecentScholar" data-student-id="${escapeHtml(s.id)}" ${s.saving ? "disabled" : ""}
            class="recent-scholar-row group flex w-full items-center justify-between gap-4 border-b p-5 text-left last:border-b-0 dark:border-slate-800">
            <span class="min-w-0">
                <span class="block font-bold text-dyci-blue group-hover:underline group-disabled:no-underline dark:text-blue-300">${escapeHtml(s.name)}</span>
                <span class="block text-xs text-slate-500">${escapeHtml(s.id)} · ${escapeHtml(s.course)}</span>
                <span class="mt-1 block text-xs text-dyci-blue dark:text-blue-400">${escapeHtml(scholarshipNames(s).join(" · "))}</span>
            </span>
            <span class="flex shrink-0 items-center gap-3">
                ${s.saving ? '<span class="text-sm text-slate-600 dark:text-slate-400">Saving&hellip;</span>' :
                    scholarStatus(s.status) + '<span class="recent-scholar-arrow" aria-hidden="true">&rsaquo;</span>'}
            </span>
        </button>`
    ).join("") : `<div class="p-10 text-center text-slate-500">No scholar records yet.</div>`;
}

function openRecentScholar(id) {
    document.getElementById("search").value = id;
    for (const filter of ["scholarYear", "scholarSemester", "scholarDepartment"]) {
        document.getElementById(filter).value = "";
    }
    document.getElementById("filter").value = "All Status";
    document.getElementById("scholarPagination").dataset.page = "1";
    go("scholars");
    openViewModal(id);
}

// VIEW MODAL FUNCTIONS
function openViewModal(id) {
    const student = scholars.find(s => s.id === id);
    if (!student) return;

    document.getElementById("viewName").textContent = student.name;
    document.getElementById("viewId").textContent = student.id;
    document.getElementById("viewCourse").textContent = student.course + (student.program ? ` (${student.program})` : "");
    document.getElementById("viewStatus").textContent = student.status;
    document.getElementById("viewUpdated").textContent = formatPhilippineTime(student.updated);
    const studentHistory = discountHistory.filter(record => record.id === student.id);
    const {year, semester} = scholarPeriod(student, studentHistory);
    document.getElementById("viewPeriod").textContent = year || "—";
    renderViewTermTabs(student, studentHistory);

    const statusEl = document.getElementById("viewStatus");
    if (student.status === "Approved") {
        statusEl.className = "rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-bold text-emerald-700";
    } else if (student.status === "Pending") {
        statusEl.className = "rounded-full bg-amber-100 px-2.5 py-1 text-xs font-bold text-amber-700";
    } else {
        statusEl.className = "rounded-full bg-red-100 px-2.5 py-1 text-xs font-bold text-red-700";
    }

    const schContainer = document.getElementById("viewScholarships");
    const names = scholarshipNames(student);
    const catalog = new Map((window.scholarshipCatalog || []).map(s => [s[0], s]));
    schContainer.innerHTML = names.map(n => {
        const type = catalog.get(n);
        const codeDesc = scholarshipCodeDescription(n);
        return `<div class="rounded-xl bg-dyci-blue/10 px-3 py-2 dark:bg-dyci-blue/30">
            <span class="block text-sm font-bold text-dyci-blue dark:text-blue-300">${escapeHtml(n)}</span>
            ${codeDesc ? `<span class="block text-xs text-slate-500">${escapeHtml(codeDesc)}</span>` : ""}
            ${type ? `<span class="block text-xs text-slate-600 dark:text-slate-300">Covers: ${escapeHtml(type[3])} · ${escapeHtml(type[4])}</span>` : ""}
        </div>`;
    }).join("");

    // Build discount history table
    const historyContainer = document.getElementById("viewDiscountHistory");
    if (historyContainer) {
        if (studentHistory.length) {
            const semesters = [...new Set(studentHistory.map(record => record.semester).filter(Boolean))];
            const years = [...new Set(studentHistory.map(record => record.year).filter(Boolean))];
            const cell = (year, semester) => {
                const record = studentHistory.find(item => item.year === year && item.semester === semester);
                return record ? record.scholarships.map(n => `<span class="mb-1 mr-1 inline-block rounded-full bg-dyci-blue/10 px-2 py-0.5 text-xs font-semibold text-dyci-blue dark:bg-dyci-blue/30 dark:text-blue-300">${escapeHtml(n)}</span>`).join("") : `<span class="text-slate-400">—</span>`;
            };
            const rows = years.map(year => `<tr class="border-t dark:border-slate-700"><td class="px-3 py-3 font-semibold tabular-nums">${escapeHtml(year)}</td>${semesters.map(semester => `<td class="min-w-32 px-3 py-3 align-top">${cell(year, semester)}</td>`).join("")}</tr>`).join("");
            historyContainer.innerHTML = `<table class="w-full text-left text-sm"><thead class="bg-slate-50 text-xs font-semibold text-slate-600 dark:bg-slate-800 dark:text-slate-300"><tr><th class="px-3 py-3">School year</th>${semesters.map(semester => `<th class="px-3 py-3">${escapeHtml(semester)}</th>`).join("")}</tr></thead><tbody>${rows}</tbody></table>`;
        } else {
            historyContainer.innerHTML = `<p class="text-sm text-slate-500">No discount history found.</p>`;
        }
    }

    const modal = document.getElementById("viewModal");
    const decision = document.getElementById("scholarDecision");
    if (decision) {
        decision.dataset.studentId = student.id;
        decision.classList.toggle("hidden", student.status !== "Pending");
        decision.classList.toggle("flex", student.status === "Pending");
    }
    modal.classList.remove("hidden");
    modal.classList.add("flex");
}

function renderViewTermTabs(student, history, activeYear = null, activeTerm = null) {
    const years = [...new Set(history.map(row => row.year).filter(Boolean))].sort().reverse();
    const yearBox = document.getElementById("viewSchoolYears");
    const semesterBox = document.getElementById("viewSemesters");
    const details = document.getElementById("viewTermDetails");
    if (!yearBox || !semesterBox || !details) return;
    const legacyHistory = document.getElementById("viewDiscountHistory");
    if (legacyHistory) legacyHistory.hidden = true;
    const selectedYear = activeYear && years.includes(activeYear) ? activeYear : (years[0] || student.period || "—");
    const terms = [...new Set(history.filter(row => row.year === selectedYear).map(row => row.semester).filter(Boolean))];
    const selectedTerm = activeTerm && terms.includes(activeTerm) ? activeTerm : (terms[0] || "—");
    yearBox.innerHTML = years.length ? years.map(year => `<button type="button" class="rounded-lg border px-3 py-1.5 text-xs font-bold ${year === selectedYear ? "bg-dyci-blue text-white" : ""}" data-view-term-year="${escapeHtml(year)}">${escapeHtml(year)}</button>`).join("") : "";
    semesterBox.innerHTML = terms.length ? terms.map(term => `<button type="button" class="rounded-lg border px-4 py-1.5 text-xs font-bold ${term === selectedTerm ? "bg-dyci-blue text-white" : ""}" data-view-term-semester="${escapeHtml(term)}">${escapeHtml(term)}</button>`).join("") : "";
    const record = history.find(row => row.year === selectedYear && row.semester === selectedTerm);
    const names = record?.scholarships || scholarshipNames(student);
    const external = names.filter(name => ["TES", "TDP"].includes(name));
    const internal = names.filter(name => !external.includes(name) && !["District", "Provincial", "International"].some(type => name.startsWith(type)));
    details.innerHTML = `<div class="divide-y text-sm dark:divide-slate-700"><div class="flex items-center justify-between gap-4 py-3"><span class="font-semibold text-slate-500">External:</span><span class="text-right font-bold">${escapeHtml(external.join(", ") || "—")}</span></div><div class="flex items-center justify-between gap-4 py-3"><span class="font-semibold text-slate-500">Internal:</span><span class="text-right font-bold">${escapeHtml(internal.join(", ") || "—")}</span></div><div class="flex items-center justify-between gap-4 py-3"><span class="font-semibold text-slate-500">Amount:</span><span class="text-right font-bold tabular-nums">${record?.waivedAmount != null ? `₱${Number(record.waivedAmount).toLocaleString("en-PH", {minimumFractionDigits: 2})}` : "—"}</span></div></div>`;
    yearBox.querySelectorAll("[data-view-term-year]").forEach(button => button.addEventListener("click", () => renderViewTermTabs(student, history, button.dataset.viewTermYear, null)));
    semesterBox.querySelectorAll("[data-view-term-semester]").forEach(button => button.addEventListener("click", () => {
        renderViewTermTabs(student, history, selectedYear, button.dataset.viewTermSemester);
    }));
}

async function decideScholar(status) {
    const controls = document.getElementById("scholarDecision");
    const id = controls.dataset.studentId;
    const buttons = [...controls.querySelectorAll("button")];
    if (buttons.some(button => button.disabled)) return;
    buttons.forEach(button => button.disabled = true);
    try {
        const student = scholars.find(scholar => scholar.id === id);
        await requestRecords({action: "decide-scholar", id, status}, student && {...student, status});
        if (!document.getElementById("viewModal").classList.contains("hidden") &&
            controls.dataset.studentId === id) openViewModal(id);
        showToast(status === "Approved" ? "Scholar approved." : "Scholar marked not approved.");
    } catch (error) {
        showToast(error.message);
    } finally {
        buttons.forEach(button => button.disabled = false);
    }
}

function closeViewModal() {
    const modal = document.getElementById("viewModal");
    modal.classList.add("hidden");
    modal.classList.remove("flex");
}

function renewalSourceEntries() {
    const byKey = new Map();
    const scholarById = new Map(scholars.map(s => [s.id, s]));
    for (const row of discountHistory) {
        const scholar = scholarById.get(row.id);
        const year = normalizeSchoolYear(row.year);
        const semester = row.semester || "";
        for (const scholarship of new Set(row.scholarships || [])) {
            const key = `${row.id}\0${scholarship}\0${year}\0${semester}`;
            if (!byKey.has(key)) {
                byKey.set(key, {
                    studentId: row.id,
                    name: scholar?.name || row.name || row.id,
                    course: scholar?.course || "",
                    scholarship,
                    year,
                    semester,
                });
            }
        }
    }
    for (const r of renewals) {
        if (r.status !== "Approved") continue;
        const key = `${r.student_id}\0${r.scholarship}\0${r.school_year}\0${r.semester}`;
        if (!byKey.has(key)) {
            byKey.set(key, {
                studentId: r.student_id,
                name: r.student_name,
                course: r.course,
                scholarship: r.scholarship,
                year: r.school_year,
                semester: r.semester,
            });
        }
    }
    return [...byKey.values()].sort((a, b) => a.name.localeCompare(b.name) || a.scholarship.localeCompare(b.scholarship));
}

function renderRenewalSources() {
    const list = document.getElementById("renewalSourceList");
    if (!list) return;
    const entries = renewalSourceEntries();
    const years = [...new Set(entries.map(e => e.year).filter(Boolean))].sort().reverse();
    const year = updateSelectOptions("renewalSourceYear", "All school years", years);
    const semester = document.getElementById("renewalSourceSemester")?.value || "";
    const query = (document.getElementById("renewalSourceSearch")?.value || "").trim().toLowerCase();
    const matches = entries.filter(e =>
        (!year || e.year === year) &&
        (!semester || e.semester === semester) &&
        [e.name, e.studentId, e.scholarship, e.course].join(" ").toLowerCase().includes(query)
    );
    document.getElementById("renewalSelectAll").checked = false;
    list.innerHTML = matches.length ? matches.map(e => {
        const value = JSON.stringify({
            studentId: e.studentId,
            scholarship: e.scholarship,
            sourceYear: e.year,
            sourceSemester: e.semester,
        });
        return `<label class="flex cursor-pointer items-start gap-3 border-b px-3 py-2.5 text-xs last:border-b-0 hover:bg-slate-50 dark:border-slate-800 dark:hover:bg-slate-800">
            <input type="checkbox" name="renewalSourceItem" value="${escapeHtml(value)}" data-change="updateRenewalSelectionCount" class="mt-0.5 accent-dyci-blue">
            <span class="min-w-0 flex-1">
                <span class="block font-bold">${escapeHtml(e.name)} <span class="font-normal tabular-nums text-slate-500">(${escapeHtml(e.studentId)})</span></span>
                <span class="mt-0.5 block text-slate-600 dark:text-slate-300">${escapeHtml(e.scholarship)} · ${escapeHtml([e.year, e.semester].filter(Boolean).join(" · ") || "—")}</span>
            </span>
        </label>`;
    }).join("") : '<p class="p-4 text-center text-xs text-slate-500">No matching assignments found.</p>';
    updateRenewalSelectionCount();
}

function toggleAllRenewalSources() {
    const checked = Boolean(document.getElementById("renewalSelectAll")?.checked);
    document.querySelectorAll('input[name="renewalSourceItem"]').forEach(box => { box.checked = checked; });
    updateRenewalSelectionCount();
}

function updateRenewalSelectionCount() {
    const count = document.querySelectorAll('input[name="renewalSourceItem"]:checked').length;
    const label = document.getElementById("renewalSelectedCount");
    if (label) label.textContent = `${count} selected`;
}

async function submitCarryOverRenewals(event) {
    event.preventDefault();
    const form = event.target;
    const button = form.querySelector('[type="submit"]');
    if (button.disabled) return;
    const items = [...document.querySelectorAll('input[name="renewalSourceItem"]:checked')]
        .map(box => JSON.parse(box.value));
    if (!items.length) {
        showToast("Select at least one assignment to carry over.");
        return;
    }
    const f = new FormData(form);
    const targetYear = (f.get("targetYear") || "").trim();
    const targetSemester = (f.get("targetSemester") || "").trim();
    button.disabled = true;
    const label = button.textContent;
    button.textContent = "Carrying over…";
    try {
        await requestRecords({action: "carry-over-renewals", targetYear, targetSemester, items});
        document.getElementById("renewalSelectAll").checked = false;
        showToast(`Carried over ${items.length} assignment${items.length === 1 ? "" : "s"} as Pending.`);
    } catch (error) {
        showToast(error.message);
    } finally {
        button.disabled = false;
        button.textContent = label;
    }
}

function renderRenewalQueue() {
    const tbody = document.getElementById("renewalQueueRows");
    if (!tbody) return;
    const pendingTotal = renewals.filter(r => r.status === "Pending").length;
    const badge = document.getElementById("renewalPendingBadge");
    if (badge) badge.textContent = `${pendingTotal} pending`;

    const years = [...new Set(renewals.map(r => r.school_year).filter(Boolean))].sort().reverse();
    const year = updateSelectOptions("renewalQueueYear", "All years", years);
    const semester = document.getElementById("renewalQueueSemester")?.value || "";
    const status = document.getElementById("renewalQueueStatus")?.value ?? "Pending";
    const query = (document.getElementById("renewalQueueSearch")?.value || "").trim().toLowerCase();

    const matches = renewals.filter(r =>
        (!year || r.school_year === year) &&
        (!semester || r.semester === semester) &&
        (!status || r.status === status) &&
        [r.student_name, r.student_id, r.scholarship, r.school_year, r.semester].join(" ").toLowerCase().includes(query)
    );
    const page = tablePage("renewalPagination", matches, [year, semester, status, query], renderRenewalQueue);
    tbody.innerHTML = page.length ? page.map(r => `<tr class="border-t dark:border-slate-800">
        <td class="px-3 py-2.5"><b class="block">${escapeHtml(r.student_name)}</b><span class="tabular-nums text-xs text-slate-500">${escapeHtml(r.student_id)}</span></td>
        <td class="px-3 py-2.5 font-semibold text-dyci-blue dark:text-blue-300">${escapeHtml(r.scholarship)}</td>
        <td class="px-3 py-2.5 whitespace-nowrap"><span class="block tabular-nums">${escapeHtml(r.school_year)}</span><span class="text-xs text-slate-500">${escapeHtml(r.semester)}</span></td>
        <td class="px-3 py-2.5">${scholarStatus(r.status)}</td>
        <td class="px-3 py-2.5">${r.status === "Pending" ? `<div class="flex flex-wrap gap-2">
            <button type="button" data-click="decideRenewal" data-renewal-id="${r.id}" data-status="Approved" class="rounded-lg bg-emerald-700 px-2.5 py-1 text-xs font-bold text-white hover:bg-emerald-800 disabled:opacity-60">Approve</button>
        </div>` : `<span class="text-xs tabular-nums text-slate-500">${escapeHtml(formatPhilippineTime(r.updated))}</span>`}</td>
    </tr>`).join("") : '<tr><td colspan="5" class="p-6 text-center text-slate-500">No renewal records match these filters.</td></tr>';
}

async function decideRenewal(id, status) {
    try {
        await requestRecords({action: "decide-renewal", id, status});
        showToast(status === "Approved" ? "Renewal approved." : "Renewal marked not approved.");
    } catch (error) {
        showToast(error.message);
    }
}
