let scholars = [], activities = [], discountHistory = [], audit = [], renewals = [];
let recordsQueue = Promise.resolve();
let recordsUpdatedAt = 0;
let recordsRefresh = null;
const recordsCacheAge = 30_000;

function scholarshipNames(scholar) {
    const value = scholar.scholarship;
    if (Array.isArray(value)) return value.filter(name => typeof name === "string" && name.trim());
    return typeof value === "string" && value.trim() ? [value] : [];
}

function requestRecords(request, preview) {
    const pending = recordsQueue.then(async () => {
        const savedScholars = scholars;
        let records;
        try {
            if (preview) {
                const optimistic = {...preview, saving: true};
                const existing = scholars.some(scholar => scholar.id === preview.id);
                scholars = request.action === "add"
                    ? (existing ? scholars : [optimistic, ...scholars])
                    : scholars.map(scholar => scholar.id === preview.id ? optimistic : scholar);
                renderScholars();
                renderRecent();
            }
            const response = await fetch("records", request ? {
                method: "POST",
                headers: request instanceof FormData ? {"X-Records-Token": recordsToken}
                    : {"Content-Type": "application/json", "X-Records-Token": recordsToken},
                body: request instanceof FormData ? request : JSON.stringify(request)
            } : {cache: "no-store"});
            records = await response.json().catch(() => null);
            if (!response.ok) {
                if (!request && [401, 403].includes(response.status)) {
                    scholars = activities = discountHistory = audit = [];
                    recordsUpdatedAt = 0;
                }
                throw new Error(records?.error || `The records request failed (HTTP ${response.status}).`);
            }
            if (!Array.isArray(records?.scholars) || !Array.isArray(records.activities) || !Array.isArray(records.discountHistory)) {
                throw new Error(request ? "The server did not confirm the save. Refresh records before retrying." : "The server did not return the records list.");
            }
        } catch (error) {
            if (preview) {
                scholars = savedScholars;
                renderScholars();
                renderRecent();
            }
            throw error;
        }
        ({scholars, activities, discountHistory} = records);
        recordsUpdatedAt = Date.now();
        if (Array.isArray(records.catalog)) window.scholarshipCatalog = records.catalog;
        if (typeof records.dataEntryLocked === "boolean") {
            window._dataEntryLocked = records.dataEntryLocked;
            window._dataEntryLockReason = records.dataEntryLockReason || "";
        }
        if (Array.isArray(records.semesters)) {
            window._semesters = records.semesters;
        }
        renewals = Array.isArray(records.renewals) ? records.renewals : [];
        const status = document.getElementById("recordsStatus");
        try {
            renderAll();
            renderScholarshipPicker();
            status.classList.add("hidden");
        } catch (error) {
            console.error("Unable to render ScholarHub records.", error);
            status.querySelector("span").textContent = "Records are up to date, but the display could not refresh.";
            status.classList.remove("hidden");
        }
    });
    recordsQueue = pending.catch(() => {});
    return pending;
}

function showRecordsLoading() {
    document.getElementById("recordsStatus").classList.add("hidden");
    const bars = '<span class="skeleton" aria-hidden="true"></span>'.repeat(3);
    const recent = document.getElementById("recentTable");
    recent.setAttribute("aria-busy", "true");
    recent.innerHTML = '<div class="px-5 py-3"><span class="sr-only">Loading student records</span>' + bars + '</div>';
    const rows = document.getElementById("scholarRows");
    rows.setAttribute("aria-busy", "true");
    rows.innerHTML = '<tr><td colspan="6" class="px-5 py-3"><span class="sr-only">Loading student records</span>' + bars + '</td></tr>';
}

function loadDatabaseRecords(force = false) {
    if (recordsRefresh) return recordsRefresh;
    if (!force && recordsUpdatedAt && Date.now() - recordsUpdatedAt < recordsCacheAge) return;
    recordsRefresh = refreshDatabaseRecords().finally(() => { recordsRefresh = null; });
    return recordsRefresh;
}

async function refreshDatabaseRecords() {
    if (!recordsUpdatedAt) showRecordsLoading();
    try {
        await requestRecords();
    } catch (error) {
        document.getElementById("scholarRows").removeAttribute("aria-busy");
        renderAll();
        const status = document.getElementById("recordsStatus");
        status.querySelector("span").textContent = error.message;
        status.classList.remove("hidden");
    } finally {
        document.getElementById("recentTable").removeAttribute("aria-busy");
        document.getElementById("scholarRows").removeAttribute("aria-busy");
    }
}

function escapeHtml(value) {
    return String(value).replace(/[&<>"']/g, character => ({
        "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;"
    })[character]);
}

async function restoreRecords(readBackup, successMessage) {
    try {
        const backup = await readBackup();
        if (backup?.version !== 1 || !backup.records) throw new Error("Choose a valid ScholarHub backup.");
        await requestRecords({action: "restore", version: backup.version, records: backup.records});
        showToast(successMessage);
    } catch (error) {
        showToast(error.message);
    }
}

function restoreBackup(input) {
    const file = input.files[0];
    input.value = "";
    if (file) restoreRecords(async () => {
        if (file.size > 10 * 1024 * 1024) throw new Error("The backup exceeds 10 MB.");
        if (!/\.json$/i.test(file.name)) throw new Error("Choose a JSON backup file.");
        try {
            return JSON.parse(await file.text());
        } catch {
            throw new Error("The backup is not valid JSON.");
        }
    }, "Backup restored.");
}

const sampleStudents = new Map([
    ["2024-00124", "Maria Santos"],
    ["2024-00087", "John Dela Cruz"],
    ["2025-00152", "Angela Reyes"],
    ["2023-00045", "Mark Villanueva"],
    ["2025-00211", "Sofia Mendoza"]
]);
function importBrowserRecords() {
    return restoreRecords(() => {
        const fromBrowser = key => JSON.parse(localStorage.getItem("scholarhub_" + key) || "null") || [];
        const isActualStudent = record => !sampleStudents.has(record.id) || sampleStudents.get(record.id) !== record.name;
        const sampleNames = [...sampleStudents.values()];
        const records = {
            scholars: fromBrowser("scholars").filter(isActualStudent).map(record => ({
                ...record, scholarship: scholarshipNames(record)
            })),
            discountHistory: fromBrowser("discounts").filter(isActualStudent),
            activities: fromBrowser("activity").filter(activity => !sampleNames.some(name => activity[2]?.includes(name)))
        };
        if (!Object.values(records).some(rows => rows.length)) throw new Error("No browser records to import.");
        return {version: 1, records};
    }, "Browser records imported.");
}

const titles = {
    dashboard:"Dashboard", 
    scholars:"Student Scholars", 
    scholarships:"Scholarship Library", 
    reports:"Reports & Records", 
    activity:"Audit Logs",
    settings:"Settings"
};
