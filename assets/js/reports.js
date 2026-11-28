let scholarshipBarChart = null, scholarshipLineChart = null, departmentTrendChart = null;
let dashboardStatusChart = null, dashboardUsageChart = null;
let dashboardDeptChart = null, dashboardCoverageChart = null;
let dashboardChartDetails = {};

const SCHOLARSHIP_CODE_DESCRIPTIONS = {
    "DISSF": "Discounted In-house Student Scholarship Fund",
    "TES": "Tertiary Education Subsidy",
    "TDP": "Tulong Dunong Program",
    "AUTH": "Authorization-based scholarship",
    "ENT": "Entrance Scholarship",
    "EMP": "Employee Dependent Discount",
    "DYH": "Dr. Yanga Hospital Dependent Discount",
    "SA": "Student Assistant Program",
    "PWD": "Person With Disability discount",
    "DISTRICT": "District-level competition award",
    "PROVINCIAL": "Provincial-level competition award",
    "INTERNATIONAL": "International-level competition award",
};

function scholarshipCodeDescription(name) {
    const code = name.replace(/\s*\d.*$/, "").replace(/\s*[—–-]\s*.+$/, "").trim();
    return SCHOLARSHIP_CODE_DESCRIPTIONS[code] || "";
}

function renderDashboardMetrics() {
    const statuses = ["Approved", "Pending", "Not Approved"];
    const counts = new Map(statuses.map(status => [status, 0]));
    for (const {status} of scholars) {
        if (counts.has(status)) counts.set(status, counts.get(status) + 1);
    }
    const [approved, pending, rejected] = counts.values();
    const total = approved + pending + rejected;
    const usage = scholarshipUsageEntries(discountHistory, "").slice(0, 10);

    document.getElementById("dashboardStatusSummary").textContent = total
        ? `${approved} approved, ${pending} pending, ${rejected} not approved` : "No scholar decisions to show.";
    const chartAvailable = typeof Chart !== "undefined";
    document.getElementById("dashboardUsageSummary").textContent = usage.length
        ? (chartAvailable ? "Top ten programs by recorded scholarship assignments." : usage.map(([name, count]) => `${name} (${count})`).join(", "))
        : "No scholarship assignments to show.";

    // Department breakdown
    const deptCounts = Object.create(null);
    for (const s of scholars) {
        const dept = (s.course || "").replace(/\s*[·•–—-]\s*\d+(?:st|nd|rd|th)\s+Year$/i, "").trim() || "Unknown";
        deptCounts[dept] = (deptCounts[dept] || 0) + 1;
    }
    const deptEntries = Object.entries(deptCounts).sort((a, b) => b[1] - a[1]).slice(0, 10);
    document.getElementById("dashboardDeptSummary").textContent = deptEntries.length
        ? `${Object.keys(deptCounts).length} college${Object.keys(deptCounts).length === 1 ? "" : "s"} with scholars.`
        : "No scholars to show.";

    // Coverage breakdown
    const coverageCounts = Object.create(null);
    const catalog = new Map((window.scholarshipCatalog || []).map(s => [s[0], s[3]]));
    for (const row of discountHistory) {
        for (const name of (Array.isArray(row.scholarships) ? row.scholarships : [])) {
            const cov = catalog.get(name) || "Other";
            coverageCounts[cov] = (coverageCounts[cov] || 0) + 1;
        }
    }
    const coverageEntries = Object.entries(coverageCounts).sort((a, b) => b[1] - a[1]).slice(0, 10);
    document.getElementById("dashboardCoverageSummary").textContent = coverageEntries.length
        ? `${coverageEntries.reduce((n, [, c]) => n + c, 0)} availments across ${coverageEntries.length} coverage types.`
        : "No availment records to show.";

    if (!document.getElementById("dashboard").classList.contains("active")) return;

    dashboardChartDetails = {
        status: Object.fromEntries(statuses.map(status => [status, scholars.filter(s => s.status === status)])),
        usage: Object.fromEntries(usage.map(([name]) => [name, discountHistory.filter(row => (Array.isArray(row.scholarships) ? row.scholarships : []).includes(name))])),
        department: Object.fromEntries(deptEntries.map(([name]) => [name, scholars.filter(s => ((s.course || "").replace(/\s*[Â·â€¢â€“â€”—-]\s*\d+(?:st|nd|rd|th)\s+Year$/i, "").trim() || "Unknown") === name)])),
        coverage: Object.fromEntries(coverageEntries.map(([name]) => [name, discountHistory.filter(row => (Array.isArray(row.scholarships) ? row.scholarships : []).some(scholarship => (catalog.get(scholarship) || "Other") === name))]))
    };

    dashboardStatusChart?.destroy();
    dashboardUsageChart?.destroy();
    dashboardDeptChart?.destroy();
    dashboardCoverageChart?.destroy();
    dashboardStatusChart = dashboardUsageChart = dashboardDeptChart = dashboardCoverageChart = null;
    const statusPlot = document.getElementById("dashboardStatusPlot");
    const usagePlot = document.getElementById("dashboardUsagePlot");
    const deptPlot = document.getElementById("dashboardDeptPlot");
    const coveragePlot = document.getElementById("dashboardCoveragePlot");
    statusPlot.hidden = !chartAvailable || !total;
    usagePlot.hidden = !chartAvailable || !usage.length;
    deptPlot.hidden = !chartAvailable || !deptEntries.length;
    coveragePlot.hidden = !chartAvailable || !coverageEntries.length;
    const dark = document.documentElement.classList.contains("dark");
    const ticks = dark ? "#e7e7ea" : "#475569";
    const grid = dark ? "#39393f" : "#e2e8f0";
    const options = {responsive: true, maintainAspectRatio: false, onClick: (_event, elements, chart) => {
        if (!elements.length) return;
        const label = chart.data.labels[elements[0].index];
        const type = chart.canvas.id.replace("dashboard", "").replace("Chart", "").toLowerCase();
        openDashboardChartDetails(type === "dept" ? "department" : type, label);
    }, plugins: {legend: {display: false}}};
    const barOptions = (indexAxis = "y") => ({...options, indexAxis, scales: indexAxis === "x" ? {
        x: {grid: {display: false}, ticks: {color: ticks, autoSkip: false, maxRotation: 45, minRotation: 0, callback(value) {
            const name = this.getLabelForValue(value);
            return name.length > 18 ? name.slice(0, 17) + "\u2026" : name;
        }}},
        y: {beginAtZero: true, grid: {color: grid}, ticks: {color: ticks, precision: 0}}
    } : {
        x: {beginAtZero: true, grid: {color: grid}, ticks: {color: ticks, precision: 0}},
        y: {grid: {display: false}, ticks: {color: ticks, callback(value) {
            const name = this.getLabelForValue(value);
            return name.length > 20 ? name.slice(0, 19) + "\u2026" : name;
        }}}
    }});
    if (!statusPlot.hidden) {
        dashboardStatusChart = new Chart(document.getElementById("dashboardStatusChart"), {
            type: "doughnut",
            data: {
                labels: statuses,
                datasets: [{data: [approved, pending, rejected], backgroundColor: ["#15976f", "#d9a11b", "#d95370"], borderWidth: 0}]
            },
            options: {...options, cutout: "65%", plugins: {legend: {display: true, position: "right",
                labels: {color: ticks, boxWidth: 12, padding: 10, font: {size: 12}}}}}
        });
    }
    if (!usagePlot.hidden) {
        dashboardUsageChart = new Chart(document.getElementById("dashboardUsageChart"), {
            type: "bar",
            data: {
                labels: usage.map(([name]) => name),
                datasets: [{label: "Assignments", data: usage.map(([, count]) => count), backgroundColor: dark ? "#fff" : "#1a3b9c", borderRadius: 4}]
            },
            options: barOptions("x")
        });
    }
    if (!deptPlot.hidden) {
        dashboardDeptChart = new Chart(document.getElementById("dashboardDeptChart"), {
            type: "bar",
            data: {
                labels: deptEntries.map(([name]) => name),
                datasets: [{label: "Scholars", data: deptEntries.map(([, count]) => count), backgroundColor: dark ? "#F4B925" : "#1A3B9C", borderRadius: 4}]
            },
            options: barOptions("x")
        });
    }
    if (!coveragePlot.hidden) {
        const colors = ["#1A3B9C", "#15976f", "#d9a11b", "#d95370", "#7c3aed", "#0891b2"];
        dashboardCoverageChart = new Chart(document.getElementById("dashboardCoverageChart"), {
            type: "doughnut",
            data: {
                labels: coverageEntries.map(([name]) => name),
                datasets: [{data: coverageEntries.map(([, count]) => count),
                    backgroundColor: dark ? colors.map(c => c + "cc") : colors, borderWidth: 0}]
            },
            options: {...options, cutout: "55%", plugins: {legend: {display: true, position: "right",
                labels: {color: ticks, boxWidth: 12, padding: 8, font: {size: 11}}}}}
        });
    }
}

function openDashboardChartDetails(type, label) {
    const rows = dashboardChartDetails[type]?.[label] || [];
    const dialog = document.getElementById("dashboardChartDialog");
    if (!dialog) return;
    document.getElementById("dashboardChartDialogTitle").textContent = label;
    document.getElementById("dashboardChartDialogSummary").textContent = `${rows.length} matching record${rows.length === 1 ? "" : "s"}`;
    const body = document.getElementById("dashboardChartDialogBody");
    body.innerHTML = rows.length ? `<div class="overflow-x-auto"><table class="w-full text-left text-sm"><thead><tr class="border-b text-xs uppercase tracking-wide text-slate-500"><th class="px-3 py-2">Student</th><th class="px-3 py-2">Student ID</th><th class="px-3 py-2">Course / year</th><th class="px-3 py-2">School year</th><th class="px-3 py-2">Semester</th></tr></thead><tbody>${rows.map(row => `<tr class="border-b border-slate-100 dark:border-slate-800"><td class="px-3 py-2 font-semibold">${escapeHtml(row.name || row.student_name || "—")}</td><td class="px-3 py-2">${escapeHtml(row.id || row.student_id || "—")}</td><td class="px-3 py-2">${escapeHtml(row.course || "—")}</td><td class="px-3 py-2">${escapeHtml(row.year || row.school_year || "—")}</td><td class="px-3 py-2">${escapeHtml(row.semester || "—")}</td></tr>`).join("")}</tbody></table></div>` : `<p class="py-8 text-center text-sm text-slate-500">No matching details found.</p>`;
    dialog.showModal();
}

function populateAnalyticsFilters(){
    const years = [...new Set(discountHistory.map(x => x.year))].sort();
    const year = document.getElementById("analyticsYear");
    
    if (year) updateSelectOptions("analyticsYear", "All School Years", years);
    const catalog = window.scholarshipCatalog || [];
    updateSelectOptions("analyticsScholarship", "All Scholarships", catalog.map(item => item[0]));
}

function getFilteredHistory(){
    const y = document.getElementById("analyticsYear")?.value || "All";
    const sem = document.getElementById("analyticsSemester")?.value || "All";
    const sch = document.getElementById("analyticsScholarship")?.value || "";
    
    return discountHistory.filter(r => 
        (y === "All" || r.year === y) && 
        (sem === "All" || r.semester === sem) && 
        (!sch || (Array.isArray(r.scholarships) ? r.scholarships : []).includes(sch))
    );
}

function scholarshipUsageEntries(rows, selectedScholarship) {
    const usage = Object.create(null);
    rows.forEach(row => (Array.isArray(row.scholarships) ? row.scholarships : []).forEach(name => {
        if (!selectedScholarship || name === selectedScholarship) usage[name] = (usage[name] || 0) + 1;
    }));
    return Object.entries(usage).sort((a, b) => b[1] - a[1]);
}

function exportScholarshipUsage() {
    const name = document.getElementById("scholarshipUsageHeading").textContent;
    const students = scholarshipUsageRoster(name);
    if (!students.length) return;
    const fields = ["Scholarship", "Student ID", "Student name", "Course / year", "School year", "Semester", "Current status"];
    openExportDialog({
        title: name + " student roster",
        summary: `${students.length} matching rows across all pages. Your report filters and roster search are included.`,
        fields,
        formats: [["xlsx", "Excel (.xlsx)"], ["csv", "CSV (.csv)"]],
        download(columns, format) {
            const rows = [fields, ...students.map(row => [name, row.id, row.name, row.course, row.year, row.semester, row.status])];
            downloadScholarshipRoster(rows.map(row => columns.map(index => row[index])), format);
        }
    });
}

function downloadScholarshipRoster(rows, format) {
    const csv = () => rows.map(row => row.map(value => {
        let cell = String(value);
        if (typeof value === "string" && /^(?:\s*[=+@-]|[\t\r\n])/.test(cell)) cell = "'" + cell;
        return '"' + cell.replaceAll('"', '""') + '"';
    }).join(",")).join("\r\n");
    const file = format === "xlsx" ? buildRosterWorkbook(rows)
        : new Blob(["\uFEFF", csv()], {type: "text/csv;charset=utf-8"});
    const url = URL.createObjectURL(file);
    const link = document.createElement("a");
    link.href = url;
    link.download = format === "xlsx" ? "scholarship-students.xlsx" : "scholarship-students.csv";
    link.click();
    setTimeout(() => URL.revokeObjectURL(url), 1000);
}

function renderScholarshipUsageDetails(usageEntries = scholarshipUsageEntries(getFilteredHistory(),
    document.getElementById("analyticsScholarship")?.value || "")) {
    const availments = usageEntries.reduce((sum, [, count]) => sum + count, 0);
    const filters = ["analyticsYear", "analyticsSemester", "analyticsScholarship"]
        .map(id => document.getElementById(id)?.value || "");
    const query = document.getElementById("scholarshipUsageDetailsSearch").value.trim().toLowerCase();
    const catalog = new Map((window.scholarshipCatalog || []).map(([name, category]) => [name, category]));
    const categoryOf = name => catalog.get(name) || "Uncategorized";
    const categories = [...new Set(usageEntries.map(([name]) => categoryOf(name)))].sort((a, b) => a.localeCompare(b));
    const category = updateSelectOptions("scholarshipUsageCategory", "All categories", categories);
    const matches = usageEntries.filter(([name]) => (!category || categoryOf(name) === category) && name.toLowerCase().includes(query));
    const usagePage = tablePage("scholarshipUsagePagination", matches, [...filters, query, category], renderScholarshipUsageDetails);
    document.getElementById("scholarshipAnalytics").innerHTML = usagePage.length ? usagePage.map(([name, count]) => `
        <button type="button" data-click="openScholarshipUsage" data-scholarship-name="${escapeHtml(name)}" aria-haspopup="dialog"
            class="group flex w-full cursor-pointer items-center justify-between gap-4 rounded-xl border border-slate-100 bg-slate-50 p-4 text-left hover:border-blue-200 hover:bg-blue-50 dark:border-slate-700 dark:bg-slate-800 dark:hover:bg-slate-700">
            <span class="min-w-0"><span class="block break-words font-semibold">${escapeHtml(name)}</span><span class="mt-0.5 block text-xs text-slate-600 dark:text-slate-300">${Math.round(count/Math.max(1,availments)*100)}% of filtered availments</span></span>
            <span class="flex shrink-0 items-center gap-3 font-bold tabular-nums text-dyci-blue dark:text-blue-300">${count} availment${count === 1 ? "" : "s"}
                <span aria-hidden="true" class="text-xl leading-none transition-transform group-hover:translate-x-1 group-focus-visible:translate-x-1">&rsaquo;</span>
            </span>
        </button>`).join("") : `<p class="text-sm text-slate-600 dark:text-slate-300">${query || category ? "No scholarships match your search and category." : "No scholarship records match the selected filters."}</p>`;
}

function renderAnalytics(){
    const rows = getFilteredHistory(), selectedScholarship = document.getElementById("analyticsScholarship")?.value || "";
    const dark = document.documentElement.classList.contains("dark");
    const tickColor = dark ? "#c5c5cc" : "#475569";
    const gridColor = dark ? "#39393f" : "#e2e8f0";
    
    const usageEntries = scholarshipUsageEntries(rows, selectedScholarship);
    const uniqueStudents = new Set(rows.map(r => r.id));
    const years = [...new Set(rows.map(r => r.year))].sort();
    const availments = usageEntries.reduce((sum, [, count]) => sum + count, 0);
    
    // Update Stat Cards
    document.getElementById("reportTotalScholars").textContent = availments;
    document.getElementById("reportApprovalRate").textContent = uniqueStudents.size;
    document.getElementById("reportProgramCount").textContent = (window.scholarshipCatalog || []).length;
    document.getElementById("reportActivityCount").textContent = years.length;
    
    renderScholarshipUsageDetails(usageEntries);

    const topUsage = usageEntries.slice(0, 8);
    const usageSummary = document.getElementById("scholarshipUsageSummary");
    const usagePlot = document.getElementById("scholarshipUsagePlot");
    if(scholarshipBarChart) scholarshipBarChart.destroy();
    scholarshipBarChart = null;
    usagePlot.hidden = !topUsage.length || typeof Chart === "undefined";
    usageSummary.textContent = !topUsage.length ? "No scholarship records match the selected filters."
        : usagePlot.hidden ? "See Scholarship Usage Details for the filtered counts."
        : usageEntries.length > topUsage.length
            ? `Showing the top ${topUsage.length} of ${usageEntries.length} scholarships by availments. See details for all programs.`
            : "Availments per scholarship for the selected filters.";
    if (!usagePlot.hidden) {
        scholarshipBarChart = new Chart(document.getElementById("scholarshipPieChart"), {
            type: "bar", 
            data: {
                labels: topUsage.map(([name]) => name),
                datasets: [{
                    label: 'Availments',
                    data: topUsage.map(([, count]) => count),
                    backgroundColor: dark ? "#fff" : "#1A3B9C",
                    borderRadius: 6
                }]
            }, 
            options: {
                indexAxis: "y",
                responsive: true, 
                maintainAspectRatio: false, 
                plugins: { legend: { display: false } },
                scales: {
                    x: { beginAtZero: true, grid: { color: gridColor }, ticks: { color: tickColor, precision: 0, maxTicksLimit: 6 } },
                    y: {
                        grid: { display: false },
                        ticks: {
                            color: tickColor,
                            autoSkip: false,
                            callback(value) {
                                const name = this.getLabelForValue(value);
                                return name.length > 20 ? name.slice(0, 19) + "\u2026" : name;
                            }
                        }
                    }
                }
            }
        });
    }
    
    // Line Chart
    const lineCtx = document.getElementById("scholarshipLineChart")?.getContext("2d");
    if(scholarshipLineChart) scholarshipLineChart.destroy();
    scholarshipLineChart = null;
    if(lineCtx && years.length && typeof Chart !== "undefined") scholarshipLineChart = new Chart(lineCtx, {
        type:"line", 
        data:{
            labels:years, 
            datasets:[{
                label:"Availments", 
                data:years.map(y => rows.filter(r => r.year === y).reduce((n,r) => n + (Array.isArray(r.scholarships) ? r.scholarships : []).filter(s => !selectedScholarship || s === selectedScholarship).length, 0)), 
                tension:.3, fill:false, borderWidth:3, pointRadius:4, borderColor:'#F4B925', backgroundColor:'#F4B925'
            }]
        }, 
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {legend: {display: false}},
            scales: {
                x: {grid: {display: false}, ticks: {color: tickColor}},
                y: {beginAtZero: true, grid: {color: gridColor}, ticks: {color: tickColor, precision: 0}}
            }
        }
    });

    // Department trend chart
    const deptPlot = document.getElementById("departmentTrendPlot");
    const deptSummary = document.getElementById("departmentTrendSummary");
    if (departmentTrendChart) departmentTrendChart.destroy();
    departmentTrendChart = null;
    if (deptPlot && deptSummary) {
        const studentDepts = new Map(scholars.map(s => [
            s.id,
            (s.course || "").replace(/\s*[·•–—-]\s*\d+(?:st|nd|rd|th)\s+Year$/i, "").trim() || "Unknown"
        ]));
        const deptCounts = Object.create(null);
        for (const r of rows) {
            const dept = studentDepts.get(r.id) || "Unknown";
            const count = (Array.isArray(r.scholarships) ? r.scholarships : []).filter(s => !selectedScholarship || s === selectedScholarship).length;
            deptCounts[dept] = (deptCounts[dept] || 0) + count;
        }
        const deptEntries = Object.entries(deptCounts).sort((a, b) => b[1] - a[1]);
        deptPlot.hidden = !deptEntries.length || typeof Chart === "undefined";
        deptSummary.textContent = deptEntries.length
            ? `Filtered availments across ${deptEntries.length} college${deptEntries.length === 1 ? "" : "s"} / department${deptEntries.length === 1 ? "" : "s"}.`
            : "No department data matches the selected filters.";
        if (!deptPlot.hidden) {
            departmentTrendChart = new Chart(document.getElementById("departmentTrendChart"), {
                type: "bar",
                data: {
                    labels: deptEntries.map(([name]) => name),
                    datasets: [{label: "Availments", data: deptEntries.map(([, count]) => count), backgroundColor: dark ? "#F4B925" : "#1A3B9C", borderRadius: 4}]
                },
                options: {
                    indexAxis: "y",
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {legend: {display: false}},
                    scales: {
                        x: {beginAtZero: true, grid: {color: gridColor}, ticks: {color: tickColor, precision: 0}},
                        y: {grid: {display: false}, ticks: {color: tickColor}}
                    }
                }
            });
        }
    }
}

function openScholarshipUsage(name) {
    const dialog = document.getElementById("scholarshipUsageDialog");
    if (dialog.open) return;
    document.getElementById("scholarshipUsageRosterPages").dataset.page = "1";
    document.getElementById("scholarshipUsageSearch").value = "";
    document.getElementById("scholarshipUsageHeading").textContent = name;
    renderScholarshipUsageRoster();
    dialog.showModal();
}

function scholarshipUsageRoster(name) {
    const students = new Map(scholars.map(student => [student.id, student]));
    const query = document.getElementById("scholarshipUsageSearch").value.trim().toLowerCase();
    return getFilteredHistory().filter(row => (Array.isArray(row.scholarships) ? row.scholarships : []).includes(name)).map(row => {
        const student = students.get(row.id);
        return {...row, name: student?.name || row.name || row.id, course: student?.course || "", status: student?.status || ""};
    }).filter(row => [row.name, row.id, row.course, row.year, row.semester, row.status].join(" ").toLowerCase().includes(query));
}

function renderScholarshipUsageRoster() {
    const name = document.getElementById("scholarshipUsageHeading").textContent;
    const search = document.getElementById("scholarshipUsageSearch").value.trim().toLowerCase();
    const rows = scholarshipUsageRoster(name);
    const count = new Set(rows.map(row => row.id)).size;
    document.getElementById("exportScholarshipUsage").disabled = !rows.length;
    document.getElementById("scholarshipUsageRosterCount").textContent =
        `${count} student${count === 1 ? "" : "s"} across ${rows.length} matching availment${rows.length === 1 ? "" : "s"}`;
    const page = tablePage("scholarshipUsageRosterPages", rows, [name, search], renderScholarshipUsageRoster);
    document.getElementById("scholarshipUsageRoster").innerHTML = page.map(row =>
        `<tr class="border-t dark:border-slate-700">
            <td class="px-4 py-3"><span class="block font-semibold">${escapeHtml(row.name)}</span>
                <span class="tabular-nums text-xs text-slate-600 dark:text-slate-300">${escapeHtml(row.id)}</span></td>
            <td class="px-4 py-3">${escapeHtml(row.course || "\u2014")}</td>
            <td class="whitespace-nowrap px-4 py-3 tabular-nums">${escapeHtml(row.year)}</td>
            <td class="whitespace-nowrap px-4 py-3">${escapeHtml(row.semester)}</td>
            <td class="px-4 py-3">${row.status ? scholarStatus(row.status) : "\u2014"}</td>
        </tr>`).join("") || '<tr><td colspan="5" class="px-4 py-6 text-slate-600 dark:text-slate-300">No students match your search and filters.</td></tr>';
    document.getElementById("scholarshipUsageRosterScroll").scrollTop = 0;
}

function resetAnalyticsFilters(){
    document.getElementById("analyticsYear").value = "All";
    document.getElementById("analyticsSemester").value = "All";
    document.getElementById("analyticsScholarship").value = "";
    renderAnalytics();
}

function renderReports(){
    populateAnalyticsFilters();
    renderAnalytics();
}
