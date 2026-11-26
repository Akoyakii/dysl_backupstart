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
            <td class="px-5 py-4">${escapeHtml(s.course)}<div class="mt-1 text-xs text-slate-600 dark:text-slate-300">${escapeHtml([year, semester].filter(Boolean).join(" · ") || "School period not recorded")}</div></td>
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
        "Not Approved": "text-rose-800 dark:text-rose-300"
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
    document.getElementById("viewCourse").textContent = student.course;
    document.getElementById("viewStatus").textContent = student.status;
    document.getElementById("viewUpdated").textContent = formatPhilippineTime(student.updated);
    const {year, semester} = scholarPeriod(student, discountHistory.filter(record => record.id === student.id));
    document.getElementById("viewPeriod").textContent = [year, semester].filter(Boolean).join(" · ") || "—";

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
    schContainer.innerHTML = names.map(n => 
        `<span class="rounded-xl bg-dyci-blue/10 px-3 py-1.5 text-sm font-bold text-dyci-blue dark:bg-dyci-blue/30 dark:text-blue-300">${escapeHtml(n)}</span>`
    ).join("");

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
