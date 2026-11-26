const philippineDateTime = new Intl.DateTimeFormat("en-PH", {
    dateStyle: "medium", timeStyle: "short", timeZone: "Asia/Manila"
});

function formatPhilippineTime(value) {
    const parts = /^(\d{4}-\d{2}-\d{2}) (\d{2}:\d{2}(?::\d{2})?) UTC$/.exec(value);
    if (!parts) return value;
    const date = new Date(parts[1] + "T" + parts[2] + "Z");
    return Number.isNaN(date.getTime()) ? value : philippineDateTime.format(date) + " PHT";
}

function tablePage(id, rows, filters, render) {
    const pager = document.getElementById(id);
    const pageSize = 15;
    const key = JSON.stringify(filters);
    const lastPage = Math.max(1, Math.ceil(rows.length / pageSize));
    const page = pager.dataset.filters === key ? Math.min(Number(pager.dataset.page), lastPage) : 1;
    pager.dataset.filters = key;
    pager.dataset.page = page;
    const start = (page - 1) * pageSize;
    const end = Math.min(start + pageSize, rows.length);
    if (!pager.firstElementChild) {
        const button = 'rounded-lg border px-3 py-2 font-semibold dark:border-slate-700 disabled:opacity-50';
        pager.innerHTML = `<span class="tabular-nums" aria-live="polite"></span>
            <div class="flex gap-2"><button type="button" class="${button}">Previous</button>
            <button type="button" class="${button}">Next</button></div>`;
        pager.querySelectorAll("button").forEach((control, index) => {
            control.addEventListener("click", () => {
                pager.dataset.page = Number(pager.dataset.page) + (index === 0 ? -1 : 1);
                render();
            });
        });
    }
    pager.hidden = rows.length <= pageSize;
    pager.firstElementChild.textContent = `Rows ${start + 1}\u2013${end} of ${rows.length} \u00b7 Page ${page} of ${lastPage}`;
    const [previous, next] = pager.querySelectorAll("button");
    previous.disabled = page === 1;
    next.disabled = page === lastPage;
    return rows.slice(start, end);
}

function bindRecordControls() {
    const actions = {
        click: {
            chooseBackup: () => document.getElementById("backupFile").click(),
            clearScholarshipSelection, closeModal, closeViewModal, importBrowserRecords,
            loadAuditLog, resetAnalyticsFilters,
            loadDatabaseRecords: () => loadDatabaseRecords(true),
            openModal: () => openModal(),
            decideScholar: (_event, button) => decideScholar(button.dataset.status),
            editScholarshipType: (_event, button) => editScholarshipType(button.dataset.scholarshipName),
            resetScholarshipType,
            editScholar: (_event, button) => openModal(button.dataset.studentId),
            openRecentScholar: (_event, button) => openRecentScholar(button.dataset.studentId),
            openScholarshipUsage: (_event, button) => openScholarshipUsage(button.dataset.scholarshipName),
            openAuditDetails: (_event, button) => openAuditDetails(Number(button.dataset.auditIndex), button.dataset.auditLabel),
            exportScholarshipUsage,
            openViewModal: (_event, button) => openViewModal(button.dataset.studentId)
        },
        change: {
            renderAnalytics, renderActivity, renderScholars, renderScholarships, updateSelectedScholarships,
            renderScholarshipUsageDetails: () => renderScholarshipUsageDetails(),
            restoreBackup: (_event, input) => restoreBackup(input)
        },
        input: {
            renderActivity, renderScholars, renderScholarshipPicker, renderScholarships,
            renderScholarshipUsageRoster,
            renderScholarshipUsageDetails: () => renderScholarshipUsageDetails(),
            formatStudentId: (_event, input) => formatStudentId(input)
        },
        submit: {submitScholar, submitScholarshipType, importScholarSpreadsheet}
    };
    Object.entries(actions).forEach(([type, handlers]) => {
        document.addEventListener(type, event => {
            const control = event.target.closest(`[data-${type}]`);
            const action = control?.dataset[type];
            if (action && Object.hasOwn(handlers, action)) handlers[action](event, control);
        });
    });
    bindWorkspaceControls();
}

function bindWorkspaceControls() {
    const theme = document.getElementById("themeToggle");
    theme.setAttribute("aria-pressed", String(document.documentElement.classList.contains("dark")));
    theme.addEventListener("click", toggleDark);
    document.getElementById("sidebarToggle").addEventListener("click", toggleSidebar);
    document.getElementById("overlay").addEventListener("click", closeSidebar);
    const sidebar = document.getElementById("sidebar");
    const edge = document.getElementById("sidebarEdge");
    let closeTimer;
    const scheduleClose = () => {
        clearTimeout(closeTimer);
        closeTimer = setTimeout(() => {
            if (!sidebarPinned) closeSidebar();
        }, 250);
    };
    edge.addEventListener("pointerenter", () => {
        clearTimeout(closeTimer);
        setSidebarOpen(true);
    });
    edge.addEventListener("pointerleave", scheduleClose);
    sidebar.addEventListener("pointerenter", () => clearTimeout(closeTimer));
    sidebar.addEventListener("pointerleave", scheduleClose);
    document.addEventListener("keydown", event => {
        if (event.key === "Escape" && sidebar.classList.contains("open")) closeSidebar();
    });
}

async function importScholarSpreadsheet(event) {
    event.preventDefault();
    const form = event.target;
    const button = form.querySelector('button[type="submit"]');
    if (button.disabled) return;
    const file = form.elements.namedItem("spreadsheet").files[0];
    if (!file || file.size > 5 * 1024 * 1024) {
        showToast("Choose an XLSX or CSV file up to 5 MB.");
        return;
    }
    button.disabled = true;
    try {
        await requestRecords(new FormData(form));
        form.reset();
        showToast("Spreadsheet imported. Reports now include the new records.");
    } catch (error) {
        showToast(error.message);
    } finally {
        button.disabled = false;
    }
}

function formatStudentId(input) {
    const beforeCaret = input.value.slice(0, input.selectionStart).replace(/[^0-9]/g, "").length;
    const digits = input.value.replace(/[^0-9]/g, "").slice(0, 9);
    const formatted = digits.length > 4 ? digits.slice(0, 4) + "-" + digits.slice(4) : digits;
    if (formatted === input.value) return;
    input.value = formatted;
    const caret = Math.min(formatted.length, beforeCaret + (beforeCaret > 4 ? 1 : 0));
    input.setSelectionRange(caret, caret);
}

async function submitScholar(e) {
    e.preventDefault();
    const form = e.target;
    const button = form.querySelector('[type="submit"]');
    if (button.disabled) return;
    const f = new FormData(form);
    const selected = [...new Set(f.getAll("scholarship[]"))];
    const editId = form.dataset.editId;
    if (editId) {
        const name = (f.get("editName") || "").trim();
        const newId = (f.get("newId") || "").trim();
        if (!name) {
            showToast("Enter the student's full name.");
            form.elements.namedItem("editName").focus();
            return;
        }
        if (newId !== editId && !/^[0-9]{4}-[0-9]{5}$/.test(newId)) {
            showToast("Enter a student ID in the format 2026-00125.");
            form.elements.namedItem("newId").focus();
            return;
        }
        if (!selected.length) {
            showToast("Select at least one scholarship.");
            return;
        }
        button.disabled = true;
        const label = button.textContent;
        button.textContent = "Saving\u2026";
        try {
            const student = scholars.find(scholar => scholar.id === editId);
            const status = f.get("status");
            await requestRecords({action: "edit-scholar", id: editId, newId, name, scholarship: selected, status},
                student && newId === editId ? {...student, name, scholarship: selected, status} : undefined);
            const search = document.getElementById("search");
            if (newId !== editId && search.value.trim() === editId) {
                search.value = newId;
                renderScholars();
            }
            if (form.dataset.editId === editId) closeModal();
            showToast("Student record saved.");
        } catch (error) {
            showToast(error.message);
        } finally {
            button.disabled = false;
            button.textContent = label;
        }
        return;
    }
    
    // 1. Validate Required Fields
    let firstName = (f.get("firstName") || "").trim();
    let middleName = (f.get("middleName") || "").trim();
    let lastName = (f.get("lastName") || "").trim();
    let studentId = (f.get("id") || "").trim();
    let course = (f.get("course") || "").trim();
    let year = (f.get("year") || "").trim();
    let period = (f.get("period") || "").trim();
    let semester = (f.get("semester") || "").trim();
    let status = (f.get("status") || "").trim();

    if (!firstName || !lastName || !studentId || !course || !year || !period || !semester || !status) {
        showToast("Please fill in all required fields.");
        return;
    }
    if (!/^[0-9]{4}-[0-9]{5}$/.test(studentId)) {
        showToast("Enter a student ID in the format 2026-00125.");
        form.elements.namedItem("id").focus();
        return;
    }

    if (!selected.length) {
        document.getElementById("scholarshipDisclosure").open = true;
        document.getElementById("studentScholarshipSearch").focus();
        showToast("Select at least one scholarship.");
        return;
    }
    
    // 2. Format the Name
    let fullName = lastName + ", " + firstName + (middleName ? " " + middleName.charAt(0) + "." : "");
    let courseYear = course + " · " + year;

    let s = {
        name: fullName, 
        id: studentId, 
        course: courseYear, 
        period: period, 
        semester,
        scholarship: selected, 
        status: status, 
        updated: "Saving…"
    };
    
    button.disabled = true;
    const label = button.textContent;
    button.textContent = "Saving…";
    try {
        await requestRecords({action: "add", scholar: s}, s);
    } catch (error) {
        showToast(error.message);
        return;
    } finally {
        button.disabled = false;
        button.textContent = label;
    }
    
    // 6. Clean up UI and re-render
    closeModal();
    form.reset();
    clearScholarshipSelection();
    document.getElementById("scholarshipDisclosure").open = false;
    document.getElementById("studentScholarshipSearch").value = "";
    renderScholarshipPicker();
    showToast("Scholar added successfully.");
}

// Navigation
document.querySelectorAll("[data-view]").forEach(b => b.addEventListener("click", () => go(b.dataset.view)));

function go(v){
    document.querySelectorAll(".view").forEach(x => x.classList.remove("active"));
    document.getElementById(v).classList.add("active");
    document.querySelectorAll(".nav").forEach(x => x.classList.toggle("active", x.dataset.view === v));
    document.getElementById("pageTitle").textContent = titles[v];
    if (v === "scholars" && !document.getElementById("scholarRows").hasAttribute("aria-busy")) renderScholars();
    if (v === "reports" && recordsUpdatedAt) renderAnalytics();
    if (v === "dashboard" && recordsUpdatedAt) renderDashboardMetrics();
    if (v === "activity") loadAuditLog();
    if (["dashboard", "scholars", "scholarships", "reports"].includes(v)) loadDatabaseRecords();
    closeSidebar();
}

// Dark mode, sidebar, toast
function toggleDark(){
    const dark = document.documentElement.classList.toggle("dark");
    document.getElementById("themeToggle").setAttribute("aria-pressed", String(dark));
    try { localStorage.setItem("scholarhub_dark", String(dark)); } catch {}
    if (document.getElementById("reports")?.classList.contains("active") && recordsUpdatedAt) renderAnalytics();
    if (document.getElementById("dashboard")?.classList.contains("active") && recordsUpdatedAt) renderDashboardMetrics();
}
let sidebarPinned = false;

function setSidebarOpen(open) {
    const sidebar = document.getElementById("sidebar");
    const button = document.getElementById("sidebarToggle");
    if (!open && sidebar.contains(document.activeElement)) button.focus();
    sidebar.inert = !open;
    sidebar.classList.toggle("open", open);
    document.getElementById("overlay").classList.toggle("hidden", !open);
    button.setAttribute("aria-expanded", String(open));
}
function toggleSidebar() {
    if (document.getElementById("sidebar").classList.contains("open")) {
        closeSidebar();
        return;
    }
    sidebarPinned = true;
    setSidebarOpen(true);
    document.querySelector("#sidebar .nav.active")?.focus();
}
function closeSidebar() {
    sidebarPinned = false;
    setSidebarOpen(false);
}
function showToast(m){ 
    let t = document.getElementById("toast"); 
    t.textContent = m; 
    t.classList.add("show"); 
    clearTimeout(window.tt); 
    window.tt = setTimeout(() => t.classList.remove("show"), 2500); 
}

// Modal Controls
function openModal(id){
    const modal = document.getElementById("modal");
    const form = document.getElementById("scholarForm");
    const student = id && scholars.find(scholar => scholar.id === id);
    if (!form || (id && !student) || form.querySelector('[type="submit"]').disabled) return;
    form.reset();
    form.dataset.editId = student ? student.id : "";
    const identity = document.getElementById("scholarIdentity");
    identity.hidden = identity.disabled = Boolean(student);
    const editIdentity = document.getElementById("scholarEditIdentity");
    editIdentity.hidden = editIdentity.disabled = !student;
    modal.querySelector("h2").textContent = student ? "Edit Student Scholar" : "Add Student Scholar";
    modal.querySelector("h2 + p").textContent = student ? student.name : "Enter the student details below.";
    form.querySelector('[type="submit"]').textContent = student ? "Save changes" : "Save Student";
    if (student) {
        form.elements.namedItem("editName").value = student.name;
        form.elements.namedItem("newId").value = student.id;
        form.elements.namedItem("status").value = student.status;
    }
    document.getElementById("scholarshipDisclosure").open = Boolean(student);
    document.getElementById("studentScholarshipSearch").value = "";
    document.getElementById("studentScholarshipPicker").replaceChildren();
    renderScholarshipPicker(student ? scholarshipNames(student) : []);
    modal.classList.add("open");
    document.getElementById("scholarFormScroll").scrollTop = 0;
}
function closeModal(){ 
    document.getElementById("modal").classList.remove("open"); 
}

// Render everything
function renderAll(){
    renderScholars();
    renderRecent();
    renderScholarships();
    renderActivity();
    renderReports();
    // FIXED: Removed Math.max(24, ...) so it shows the actual count
    document.getElementById("statScholars").textContent = scholars.length;
    document.getElementById("dashboardScholarshipCount").textContent = window.scholarshipCatalog?.length || 0;
    document.getElementById("dashboardDiscountCount").textContent = discountHistory.length;
    renderDashboardMetrics();
}
