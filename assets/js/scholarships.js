let scholarshipViewMode = "tile";

function setScholarshipView(mode) {
    scholarshipViewMode = mode;
    document.getElementById("scholarshipViewTile").setAttribute("aria-checked", String(mode === "tile"));
    document.getElementById("scholarshipViewList").setAttribute("aria-checked", String(mode === "list"));
    const tileBtn = document.getElementById("scholarshipViewTile");
    const listBtn = document.getElementById("scholarshipViewList");
    if (mode === "tile") {
        tileBtn.classList.add("bg-dyci-blue", "text-white"); tileBtn.classList.remove("text-slate-600");
        listBtn.classList.remove("bg-dyci-blue", "text-white"); listBtn.classList.add("text-slate-600");
    } else {
        listBtn.classList.add("bg-dyci-blue", "text-white"); listBtn.classList.remove("text-slate-600");
        tileBtn.classList.remove("bg-dyci-blue", "text-white"); tileBtn.classList.add("text-slate-600");
    }
    renderScholarships();
}

function renderScholarships(){
    const q = (document.getElementById("scholarshipSearch")?.value || "").toLowerCase().trim();
    const options = index => [...new Set(window.scholarshipCatalog.map(row => row[index]))]
        .sort((a, b) => a.localeCompare(b, undefined, {numeric: true}));
    const categories = options(1);
    updateSelectOptions("newScholarshipCategory", "Select category", categories);
    updateSelectOptions("newScholarshipCoverage", "Select coverage", options(3));
    updateSelectOptions("newScholarshipBenefit", "Select benefit or rate", options(4));
    const filter = updateSelectOptions("scholarshipFilter", "All categories", categories);
    
    const results = window.scholarshipCatalog.filter(s => 
        (!filter || s[1] === filter) && s.join(" ").toLowerCase().includes(q)
    );
    
    document.getElementById("scholarshipCount").textContent = results.length;
    const page = scholarshipViewMode === "list" ? tablePage("scholarshipLibraryPagination", results, [q, filter], renderScholarships) : results;
    const pagination = document.getElementById("scholarshipLibraryPagination");
    if (pagination && scholarshipViewMode === "tile") pagination.hidden = true;
    
    // Initialize view mode buttons on first render
    if (!document.getElementById("scholarshipViewTile").classList.contains("bg-dyci-blue") &&
        !document.getElementById("scholarshipViewList").classList.contains("bg-dyci-blue")) {
        setScholarshipView(scholarshipViewMode);
        return;
    }
    
    const canEdit = Boolean(document.getElementById("addScholarshipPanel"));
    const container = document.getElementById("scholarshipCards");

    if (scholarshipViewMode === "list") {
        container.className = "divide-y overflow-hidden rounded-2xl border bg-white shadow-sm dark:divide-slate-800 dark:border-slate-800 dark:bg-slate-900";
        container.innerHTML = page.map(s => {
            const codeDesc = scholarshipCodeDescription(s[0]);
            const isDisabled = Boolean(s[6]);
            return `<details class="group ${isDisabled ? "opacity-60" : ""}">
                <summary class="grid cursor-pointer list-none grid-cols-[auto_minmax(0,1fr)_auto] items-center gap-4 px-5 py-4 transition-colors hover:bg-slate-50 [&::-webkit-details-marker]:hidden dark:hover:bg-slate-800/60">
                <span class="text-xs font-black uppercase tracking-widest text-slate-400">${escapeHtml(s[0].replace(/\s*\d.*$/, "").slice(0, 8))}</span>
                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-baseline gap-2">
                        <span class="truncate font-bold">${escapeHtml(s[0])}</span>
                        <span class="text-xs text-slate-500">${escapeHtml(s[1])}</span>
                        ${isDisabled ? `<span class="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-800 dark:bg-amber-950 dark:text-amber-300">Disabled</span>` : ""}
                    </div>
                </div>
                <div class="flex items-center gap-3"><span class="text-xs text-slate-500">${escapeHtml(s[4])}</span><span aria-hidden="true" class="text-slate-400 transition-transform group-open:rotate-180">⌄</span></div>
                </summary>
                <div class="border-t bg-slate-50/60 px-5 pb-5 pt-4 text-sm dark:border-slate-800 dark:bg-slate-800/30">
                    ${codeDesc ? `<p class="text-xs text-slate-500">${escapeHtml(codeDesc)}</p>` : ""}
                    <p class="mt-2 text-slate-600 dark:text-slate-300">${escapeHtml(s[2])}</p>
                    ${s[5] ? `<p class="mt-2 text-xs text-slate-400">${escapeHtml(s[5])}</p>` : ""}
                    <p class="mt-2 text-xs text-slate-500">Coverage: <b class="text-slate-700 dark:text-slate-200">${escapeHtml(s[3])}</b></p>
                    ${canEdit ? `<button type="button" data-click="editScholarshipType" data-scholarship-name="${escapeHtml(s[0])}" aria-label="Edit ${escapeHtml(s[0])}" class="text-sm font-bold text-dyci-blue hover:underline dark:text-blue-400">Edit</button>
                    <button type="button" data-click="toggleScholarshipDisabled" data-scholarship-name="${escapeHtml(s[0])}" data-disabled="${!isDisabled}" class="text-sm font-semibold text-slate-600 hover:underline dark:text-slate-300">${isDisabled ? "Enable" : "Disable"}</button>
                    <button type="button" data-click="deleteScholarship" data-scholarship-name="${escapeHtml(s[0])}" class="text-sm font-semibold text-rose-700 hover:underline dark:text-rose-300">Delete</button>` : ""}
                </div>
            </details>`;
        }).join("") || emptyScholarships();
    } else {
        container.className = "grid items-start gap-4 md:grid-cols-2 xl:grid-cols-3";
        const groups = new Map();
        page.forEach(s => {
            const code = s[0].replace(/\s*\d.*$/, "").replace(/\s*[â€”â€“-].*$/, "").trim() || s[0];
            if (!groups.has(code)) groups.set(code, []);
            groups.get(code).push(s);
        });
        container.innerHTML = [...groups].map(([code, items]) => `<details class="group relative self-start rounded-2xl border bg-white shadow-sm transition-shadow open:shadow-md dark:border-slate-800 dark:bg-slate-900">
            <summary class="flex cursor-pointer list-none items-center justify-between gap-4 bg-slate-50/80 px-5 py-4 [&::-webkit-details-marker]:hidden dark:bg-slate-800/60">
                <span class="min-w-0"><span class="block text-lg font-black tracking-tight">${escapeHtml(code)}</span><span class="mt-1 block text-xs font-medium text-slate-500">${items.length} scholarship option${items.length === 1 ? "" : "s"}</span></span>
                <span aria-hidden="true" class="text-xl text-slate-400 transition-transform group-open:rotate-180">⌄</span>
            </summary>
            <div class="absolute left-0 right-0 top-full z-30 mt-2 max-h-[min(60vh,36rem)] space-y-3 overflow-y-auto rounded-2xl border bg-white p-4 shadow-2xl dark:border-slate-700 dark:bg-slate-900">${items.map(s => {
                const isDisabled = Boolean(s[6]);
                return `<article class="rounded-xl border border-slate-200 bg-white p-4 transition-colors hover:border-dyci-blue/40 dark:border-slate-700 dark:bg-slate-900 ${isDisabled ? "opacity-60" : ""}">
                    <div class="flex items-start justify-between gap-3"><div><h3 class="font-bold">${escapeHtml(s[0])}</h3><p class="text-xs text-slate-500">${escapeHtml(s[1])}</p></div><span class="tabular-nums text-sm font-bold text-dyci-blue dark:text-blue-300">${escapeHtml(s[4])}</span></div>
                    <p class="mt-2 text-sm text-slate-600 dark:text-slate-300">${escapeHtml(s[2])}</p><p class="mt-2 text-xs text-slate-500">Coverage: <b>${escapeHtml(s[3])}</b></p>
                    ${s[5] ? `<p class="mt-2 text-xs text-slate-500">${escapeHtml(s[5])}</p>` : ""}
                    ${canEdit ? `<div class="mt-3 flex flex-wrap gap-3"><button type="button" data-click="editScholarshipType" data-scholarship-name="${escapeHtml(s[0])}" class="text-sm font-bold text-dyci-blue hover:underline dark:text-blue-400">Edit</button><button type="button" data-click="toggleScholarshipDisabled" data-scholarship-name="${escapeHtml(s[0])}" data-disabled="${!isDisabled}" class="text-sm font-semibold text-slate-600 hover:underline dark:text-slate-300">${isDisabled ? "Enable" : "Disable"}</button><button type="button" data-click="deleteScholarship" data-scholarship-name="${escapeHtml(s[0])}" class="text-sm font-semibold text-rose-700 hover:underline dark:text-rose-300">Delete</button></div>` : ""}
                </article>`;
            }).join("")}</div>
        </details>`).join("") || emptyScholarships();
    }
}

function emptyScholarships() {
    return `<div class="col-span-full rounded-2xl border bg-white p-12 text-center dark:border-slate-800 dark:bg-slate-900">
        <div class="text-4xl">⌕</div>
        <h2 class="mt-3 font-black">No scholarships found</h2>
        <p class="mt-1 text-sm text-slate-500">Try another scholarship name, code, category, coverage, or percentage.</p>
    </div>`;
}

function editScholarshipType(name) {
    const dialog = document.getElementById("editScholarshipDialog");
    const type = window.scholarshipCatalog.find(item => item[0] === name);
    const form = document.getElementById("scholarshipTypeForm");
    if (!type || !form || dialog.open || form.querySelector('[type="submit"]').disabled) return;
    dialog.append(form);
    dialog.oncancel = event => {
        event.preventDefault();
        if (!form.querySelector('[type="submit"]').disabled) resetScholarshipType();
    };
    const fields = form.elements;
    form.dataset.editName = type[0];
    fields.namedItem("name").value = type[0];
    fields.namedItem("name").readOnly = true;
    fields.namedItem("category").value = type[1];
    fields.namedItem("coverage").value = type[3];
    fields.namedItem("benefit").value = type[4];
    form.querySelector('[type="submit"]').textContent = "Save changes";
    document.getElementById("cancelScholarshipEdit").hidden = false;
    dialog.showModal();
    fields.namedItem("category").focus();
}

function resetScholarshipType() {
    const panel = document.getElementById("addScholarshipPanel");
    const dialog = document.getElementById("editScholarshipDialog");
    const form = document.getElementById("scholarshipTypeForm");
    form.reset();
    delete form.dataset.editName;
    form.elements.namedItem("name").readOnly = false;
    form.querySelector('[type="submit"]').textContent = "Save scholarship type";
    document.getElementById("cancelScholarshipEdit").hidden = true;
    const errorBox = document.getElementById("scholarshipTypeError");
    errorBox.hidden = true;
    errorBox.textContent = "";
    if (dialog.open) {
        dialog.close();
        panel.append(form);
    }
}

async function submitScholarshipType(event) {
    event.preventDefault();
    const form = event.target;
    const button = form.querySelector('button[type="submit"]');
    if (button.disabled) return;
    const editName = form.dataset.editName;
    const type = Object.fromEntries(new FormData(form));
    const errorBox = document.getElementById("scholarshipTypeError");
    errorBox.hidden = true;
    const label = button.textContent;
    button.textContent = "Saving\u2026";
    button.disabled = true;
    document.getElementById("cancelScholarshipEdit").disabled = true;
    try {
        await requestRecords({action: editName ? "edit-scholarship" : "add-scholarship",
            type: editName ? {...type, name: editName} : type});
        resetScholarshipType();
        if (!editName) {
            document.getElementById("addScholarshipPanel").open = false;
            document.getElementById("scholarshipFilter").value = "";
            document.getElementById("scholarshipSearch").value = "";
            renderScholarships();
        } else {
            const cardButton = [...document.querySelectorAll('[data-click="editScholarshipType"]')]
                .find(control => control.dataset.scholarshipName === editName);
            (cardButton || document.getElementById("scholarshipSearch")).focus();
        }
        showToast(editName ? "Scholarship type updated." : "Scholarship type added.");
    } catch (error) {
        button.textContent = label;
        errorBox.textContent = error.message;
        errorBox.hidden = false;
    } finally {
        button.disabled = false;
        document.getElementById("cancelScholarshipEdit").disabled = false;
    }
}

function populateScholarshipSelect(list){ 
    renderScholarshipPicker(); 
}

function renderScholarshipPicker(initialSelection){
    const box = document.getElementById("studentScholarshipPicker");
    if(!box) return;
    
    const q = (document.getElementById("studentScholarshipSearch")?.value || "").toLowerCase().trim();
    const selected = new Set(Array.isArray(initialSelection) ? initialSelection :
        [...document.querySelectorAll('input[name="scholarship[]"]:checked')].map(x => x.value));
    
    const groups = Object.create(null);
    const hiddenSelections = new Set(selected);
    const catalog = (window.scholarshipCatalog || []).filter(item => !item[6] || selected.has(item[0]));
    const known = new Set(catalog.map(item => item[0]));
    const previous = [...selected].filter(name => !known.has(name)).map(name => [name, "Existing assignments", "", "", ""]);
    [...catalog, ...previous].forEach(s => {
        if(s.join(" ").toLowerCase().includes(q)) {
            hiddenSelections.delete(s[0]);
            if(!groups[s[1]]) groups[s[1]] = [];
            groups[s[1]].push(s);
        }
    });
    
    const selectedExternal = [...selected].some(name => groups && (catalog.find(item => item[0] === name)?.[1] === "External and Government Grants"));
    const selectedInternal = [...selected].some(name => {
        const category = catalog.find(item => item[0] === name)?.[1];
        return category && category !== "External and Government Grants" && !["DISTRICT", "PROVINCIAL", "INTERNATIONAL"].includes(category);
    });
    const selectedAthletic = [...selected].some(name => ["DISTRICT", "PROVINCIAL", "INTERNATIONAL"].includes(catalog.find(item => item[0] === name)?.[1]));
    const selectedFull = [...selected].some(name => catalog.find(item => item[0] === name)?.[4] === "100%");
    const categoryFor = item => item[1] === "External and Government Grants" ? "external" : (["DISTRICT", "PROVINCIAL", "INTERNATIONAL"].includes(item[1]) ? "athletic" : "internal");
    const visibleOptions = Object.entries(groups).map(([category, items]) => `
        <details class="border-b last:border-b-0 dark:border-slate-700">
            <summary class="cursor-pointer list-none bg-slate-100 px-4 py-2 text-[10px] font-black uppercase tracking-widest text-slate-500 dark:bg-slate-800">${escapeHtml(category)} <span class="float-right text-xs">⌄</span></summary>
            ${items.map(s => `
                <label class="scholarship-option flex cursor-pointer items-start gap-3 px-4 py-3">
                    <input type="checkbox" name="scholarship[]" value="${escapeHtml(s[0])}" ${selected.has(s[0]) ? "checked" : ""} ${(!selected.has(s[0]) && ((categoryFor(s) === "external" && selectedExternal) || (categoryFor(s) === "internal" && selectedInternal) || (categoryFor(s) === "athletic" && selectedAthletic) || (categoryFor(s) === "athletic" && selectedFull) || (selectedExternal && selectedInternal))) ? "disabled" : ""} data-change="updateSelectedScholarships">
                    <span class="min-w-0 flex-1">
                        <span class="block text-sm font-bold">${escapeHtml(s[0])}</span>
                        <span class="mt-0.5 block text-xs text-slate-500">${escapeHtml(s[2])}</span>
                    </span>
                    <span class="shrink-0 rounded-full bg-dyci-blue/10 px-2 py-1 text-[10px] font-black text-dyci-blue dark:bg-dyci-blue/30 dark:text-blue-300">${escapeHtml(s[4])}</span>
                </label>
            `).join("")}
        </details>
    `).join("") || `<div class="p-8 text-center text-sm text-slate-500">No scholarships match your search.</div>`;
    box.innerHTML = visibleOptions + [...hiddenSelections].map(name => `
        <input type="checkbox" name="scholarship[]" value="${escapeHtml(name)}" checked hidden>
    `).join("");
    
    updateSelectedScholarships();
}

function updateSelectedScholarships(){
    const selected = [...document.querySelectorAll('input[name="scholarship[]"]:checked')].map(x => x.value);
    const count = document.getElementById("selectedScholarshipCount");
    const info = document.getElementById("selectedScholarshipInfo");
    const names = document.getElementById("selectedScholarshipNames");
    
    if(count) count.textContent = `${selected.length} scholarship${selected.length === 1 ? "" : "s"} selected`;
    if(info && names) {
        info.classList.toggle("hidden", selected.length === 0);
        names.innerHTML = selected.map(n => `<span class="rounded-full bg-white px-3 py-1.5 text-xs font-bold text-dyci-blue shadow-sm dark:bg-slate-800 dark:text-blue-300">${escapeHtml(n)}</span>`).join("");
    }
    const form = document.getElementById("scholarForm");
    const waivedInput = form?.elements.namedItem("waivedAmount");
    if (waivedInput && !selected.length) waivedInput.closest("label")?.classList.add("hidden");
    if (form && selected.length) {
        const byName = new Map((window.scholarshipCatalog || []).map(item => [item[0], item]));
        const hasExternal = selected.some(name => byName.get(name)?.[1] === "External and Government Grants");
        const hasTesOrTdp = selected.some(name => name === "TES" || name === "TDP");
        const statusSelect = form.elements.namedItem("status");
        if (waivedInput) waivedInput.closest("label")?.classList.toggle("hidden", !hasTesOrTdp);
        const extRequiresApproval = window._systemSettings?.externalRequiresApproval !== "0";
        const inHouseDefault = window._systemSettings?.inHouseDefaultStatus || "Approved";
        if (statusSelect) {
            const pending = statusSelect.querySelector('option[value="Pending"]') || [...statusSelect.options].find(option => option.textContent.trim() === "Pending");
            if (pending) pending.disabled = !hasExternal;
            if (!hasExternal || !statusSelect.value || statusSelect.value === "Pending" && !hasExternal) {
                statusSelect.value = "Approved";
            } else if (!form.dataset.editId) {
                statusSelect.value = hasExternal && extRequiresApproval ? "Pending" : inHouseDefault;
            }
        }
    }
    const byName = new Map((window.scholarshipCatalog || []).map(item => [item[0], item]));
    const hasExternal = selected.some(name => byName.get(name)?.[1] === "External and Government Grants");
    const hasInternal = selected.some(name => {
        const category = byName.get(name)?.[1];
        return category && category !== "External and Government Grants" && !["DISTRICT", "PROVINCIAL", "INTERNATIONAL"].includes(category);
    });
    const hasAthletic = selected.some(name => ["DISTRICT", "PROVINCIAL", "INTERNATIONAL"].includes(byName.get(name)?.[1]));
    const hasFull = selected.some(name => byName.get(name)?.[4] === "100%");
    document.querySelectorAll('input[name="scholarship[]"]').forEach(input => {
        if (input.checked) return;
        const category = byName.get(input.value)?.[1];
        const external = category === "External and Government Grants";
        const internal = category && !external && !["DISTRICT", "PROVINCIAL", "INTERNATIONAL"].includes(category);
        const athletic = ["DISTRICT", "PROVINCIAL", "INTERNATIONAL"].includes(category);
        input.disabled = (external && hasExternal) || (internal && hasInternal) || (athletic && hasAthletic) || (athletic && hasFull) || (hasExternal && hasInternal);
    });
}

async function toggleScholarshipDisabled(name, disabled) {
    try {
        await requestRecords({action: "toggle-scholarship-disabled", name, disabled});
        showToast(disabled ? `${name} disabled.` : `${name} enabled.`);
    } catch (error) {
        showToast(error.message);
    }
}

async function deleteScholarship(name) {
    if (!confirm(`Delete scholarship type "${name}"?`)) return;
    try {
        await requestRecords({action: "delete-scholarship", name});
        showToast(`${name} deleted.`);
    } catch (error) {
        showToast(error.message);
    }
}

function clearScholarshipSelection(){
    document.querySelectorAll('input[name="scholarship[]"]').forEach(x => x.checked = false);
    updateSelectedScholarships();
}
