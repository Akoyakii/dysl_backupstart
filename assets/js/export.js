const exportDialog = document.getElementById("exportDialog");
const exportFields = document.getElementById("exportFields");
let downloadSelectedExport;

function updateExportSelection() {
    const empty = exportFields.children.length > 0 && !exportFields.querySelector("input:checked");
    document.getElementById("downloadExport").disabled = empty;
    document.getElementById("exportFieldsHint").hidden = !empty;
}

function openExportDialog({title, summary, fields = [], format = "CSV (.csv)", formats = [], download}) {
    document.getElementById("exportDialogTitle").textContent = "Export " + title;
    document.getElementById("exportDialogSummary").textContent = summary;
    const formatText = document.getElementById("exportDialogFormat");
    const formatChoice = document.getElementById("exportFormatChoice");
    formatText.textContent = format;
    formatText.hidden = formats.length > 0;
    formatChoice.hidden = !formats.length;
    formatChoice.replaceChildren(...formats.map(([value, label]) => new Option(label, value)));
    document.getElementById("exportFieldOptions").hidden = !fields.length;
    exportFields.replaceChildren(...fields.map((name, index) => {
        const label = document.createElement("label");
        label.className = "flex items-start gap-2";
        const checkbox = document.createElement("input");
        checkbox.type = "checkbox";
        checkbox.value = index;
        checkbox.checked = true;
        checkbox.className = "mt-1 accent-blue-700";
        label.append(checkbox, name);
        return label;
    }));
    downloadSelectedExport = download;
    updateExportSelection();
    exportDialog.showModal();
}

document.addEventListener("click", event => {
    const trigger = event.target.closest("[data-export-url]");
    if (!trigger) return;
    const data = trigger.dataset;
    openExportDialog({
        title: data.exportTitle,
        summary: data.exportSummary,
        fields: data.exportFields ? JSON.parse(data.exportFields) : [],
        format: data.exportFormat,
        download(columns) {
            const url = new URL(data.exportUrl, location.href);
            if (columns.length) url.searchParams.set("columns", columns.join(","));
            location.assign(url.href);
        }
    });
});

exportFields.addEventListener("change", updateExportSelection);
for (const [id, checked] of [["selectExportFields", true], ["clearExportFields", false]]) {
    document.getElementById(id).addEventListener("click", () => {
        exportFields.querySelectorAll("input").forEach(input => { input.checked = checked; });
        updateExportSelection();
    });
}
document.getElementById("closeExportDialog").addEventListener("click", () => exportDialog.close());
document.getElementById("exportOptionsForm").addEventListener("submit", event => {
    event.preventDefault();
    const columns = [...exportFields.querySelectorAll("input:checked")].map(input => Number(input.value));
    if (!columns.length && exportFields.children.length) return;
    exportDialog.close();
    downloadSelectedExport(columns, document.getElementById("exportFormatChoice").value);
});
