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
    const page = tablePage("scholarshipLibraryPagination", results, [q, filter], renderScholarships);
    
    const canEdit = Boolean(document.getElementById("addScholarshipPanel"));
    document.getElementById("scholarshipCards").innerHTML = page.map(s => {
        return `<div class="rounded-2xl border bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
            <div class="flex items-start justify-between gap-3">
                <h2 class="text-lg font-bold">${escapeHtml(s[0])}</h2>
                ${canEdit ? `<button type="button" data-click="editScholarshipType" data-scholarship-name="${escapeHtml(s[0])}" aria-label="Edit ${escapeHtml(s[0])}" class="shrink-0 text-sm font-bold text-dyci-blue hover:underline dark:text-blue-400">Edit</button>` : ""}
            </div>
            <div class="mt-3 rounded-xl bg-slate-50 p-3 text-sm dark:bg-slate-800">
                <span class="text-xs font-bold uppercase tracking-wide text-slate-400">Coverage</span>
                <p class="mt-1 leading-5">${escapeHtml(s[2])}</p>
                ${s[5] ? `<p class="mt-2 text-sm leading-5 text-slate-600 dark:text-slate-300">${escapeHtml(s[5])}</p>` : ""}
            </div>
            <div class="mt-4 flex items-end justify-between gap-3">
                <div>
                    
                    <div class="text-sm font-bold">${escapeHtml(s[1])}</div>
                </div>
                <div class="text-right">
                    <span class="text-xs text-slate-400">Benefit</span>
                    <div class="tabular-nums text-xl font-bold text-dyci-blue dark:text-blue-400">${escapeHtml(s[4])}</div>
                </div>
            </div>
        </div>`;
    }).join("") || `<div class="col-span-full rounded-2xl border bg-white p-12 text-center dark:border-slate-800 dark:bg-slate-900">
        <div class="text-4xl">⌕</div>
        <h2 class="mt-3 font-black">No scholarships found</h2>
        <p class="mt-1 text-sm text-slate-500">Try another scholarship name, category, coverage, or percentage.</p>
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
    const catalog = window.scholarshipCatalog || [];
    const known = new Set(catalog.map(item => item[0]));
    const previous = [...selected].filter(name => !known.has(name)).map(name => [name, "Existing assignments", "", "", ""]);
    [...catalog, ...previous].forEach(s => {
        if(s.join(" ").toLowerCase().includes(q)) {
            hiddenSelections.delete(s[0]);
            if(!groups[s[1]]) groups[s[1]] = [];
            groups[s[1]].push(s);
        }
    });
    
    const visibleOptions = Object.entries(groups).map(([category, items]) => `
        <div class="border-b last:border-b-0 dark:border-slate-700">
            <div class="sticky top-0 bg-slate-100 px-4 py-2 text-[10px] font-black uppercase tracking-widest text-slate-500 dark:bg-slate-800">${escapeHtml(category)}</div>
            ${items.map(s => `
                <label class="scholarship-option flex cursor-pointer items-start gap-3 px-4 py-3">
                    <input type="checkbox" name="scholarship[]" value="${escapeHtml(s[0])}" ${selected.has(s[0]) ? "checked" : ""} data-change="updateSelectedScholarships">
                    <span class="min-w-0 flex-1">
                        <span class="block text-sm font-bold">${escapeHtml(s[0])}</span>
                        <span class="mt-0.5 block text-xs text-slate-500">${escapeHtml(s[2])}</span>
                    </span>
                    <span class="shrink-0 rounded-full bg-dyci-blue/10 px-2 py-1 text-[10px] font-black text-dyci-blue dark:bg-dyci-blue/30 dark:text-blue-300">${escapeHtml(s[4])}</span>
                </label>
            `).join("")}
        </div>
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
}

function clearScholarshipSelection(){
    document.querySelectorAll('input[name="scholarship[]"]').forEach(x => x.checked = false);
    updateSelectedScholarships();
}
