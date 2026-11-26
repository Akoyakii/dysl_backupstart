function buildRosterWorkbook(rows) {
    const namespace = "http://schemas.openxmlformats.org/spreadsheetml/2006/main";
    const relationships = "http://schemas.openxmlformats.org/officeDocument/2006/relationships";
    const packageRelationships = "http://schemas.openxmlformats.org/package/2006/relationships";
    const widths = {"Scholarship": 27, "Student ID": 18, "Student name": 33, "Course / year": 48,
        "School year": 16, "Semester": 19, "Current status": 20};
    const xmlText = value => String(value).replace(/[\u0000-\u0008\u000B\u000C\u000E-\u001F\uFFFE\uFFFF]/g, "")
        .replace(/_x[\da-f]{4}_/gi, match => "_x005F_" + match.slice(1))
        .replaceAll("&", "&amp;").replaceAll("<", "&lt;").replaceAll(">", "&gt;");
    const columnName = index => String.fromCharCode(65 + index);
    const range = "A1:" + columnName(rows[0].length - 1) + rows.length;
    const columns = rows[0].map((field, index) =>
        `<col min="${index + 1}" max="${index + 1}" width="${widths[field] || 24}" customWidth="1"/>`).join("");
    const sheetRows = rows.map((row, index) => {
        const style = index === 0 ? 1 : index % 2 ? 2 : 3;
        const cells = row.map((value, column) =>
            `<c r="${columnName(column)}${index + 1}" s="${style}" t="inlineStr"><is><t xml:space="preserve">${xmlText(value)}</t></is></c>`).join("");
        return `<row r="${index + 1}"${index === 0 ? ' ht="26" customHeight="1"' : ""}>${cells}</row>`;
    }).join("");
    const files = {
        "[Content_Types].xml": `<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
            <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
            <Default Extension="xml" ContentType="application/xml"/>
            <Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
            <Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
            <Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>
        </Types>`,
        "_rels/.rels": `<Relationships xmlns="${packageRelationships}"><Relationship Id="rId1" Type="${relationships}/officeDocument" Target="xl/workbook.xml"/></Relationships>`,
        "xl/workbook.xml": `<workbook xmlns="${namespace}" xmlns:r="${relationships}"><bookViews><workbookView/></bookViews><sheets><sheet name="Scholarship Roster" sheetId="1" r:id="rId1"/></sheets></workbook>`,
        "xl/_rels/workbook.xml.rels": `<Relationships xmlns="${packageRelationships}">
            <Relationship Id="rId1" Type="${relationships}/worksheet" Target="worksheets/sheet1.xml"/>
            <Relationship Id="rId2" Type="${relationships}/styles" Target="styles.xml"/>
        </Relationships>`,
        "xl/worksheets/sheet1.xml": `<worksheet xmlns="${namespace}">
            <dimension ref="${range}"/><sheetViews><sheetView showGridLines="0" workbookViewId="0">
                <pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/>
                <selection pane="bottomLeft" activeCell="A2" sqref="A2"/>
            </sheetView></sheetViews><sheetFormatPr defaultRowHeight="20"/>
            <cols>${columns}</cols><sheetData>${sheetRows}</sheetData><autoFilter ref="${range}"/>
        </worksheet>`,
        "xl/styles.xml": `<styleSheet xmlns="${namespace}">
            <fonts count="2"><font><sz val="11"/><color rgb="FF182332"/><name val="Calibri"/></font>
                <font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font></fonts>
            <fills count="4"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill>
                <fill><patternFill patternType="solid"><fgColor rgb="FF1A3B9C"/><bgColor indexed="64"/></patternFill></fill>
                <fill><patternFill patternType="solid"><fgColor rgb="FFEEF4FF"/><bgColor indexed="64"/></patternFill></fill></fills>
            <borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>
            <cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>
            <cellXfs count="4"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>
                <xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1" applyAlignment="1"><alignment vertical="center"/></xf>
                <xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0" applyAlignment="1"><alignment vertical="center" wrapText="1"/></xf>
                <xf numFmtId="0" fontId="0" fillId="3" borderId="0" xfId="0" applyFill="1" applyAlignment="1"><alignment vertical="center" wrapText="1"/></xf>
            </cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>
        </styleSheet>`
    };
    return packageRosterWorkbook(files);
}

// XLSX uses a ZIP container. Store the small XML parts without compression.
function packageRosterWorkbook(files) {
    const encoder = new TextEncoder();
    const parts = [];
    const directory = [];
    let offset = 0;
    let directorySize = 0;
    for (const [name, xml] of Object.entries(files)) {
        const filename = encoder.encode(name);
        const data = encoder.encode(xml);
        let crc = -1;
        for (const byte of data) {
            crc ^= byte;
            for (let bit = 0; bit < 8; bit++) {
                crc = (crc >>> 1) ^ ((crc & 1) ? 0xedb88320 : 0);
            }
        }
        crc ^= -1;
        const local = new DataView(new ArrayBuffer(30));
        local.setUint32(0, 0x04034b50, true);
        local.setUint16(4, 20, true);
        local.setUint16(12, 33, true);
        local.setUint32(14, crc, true);
        local.setUint32(18, data.length, true);
        local.setUint32(22, data.length, true);
        local.setUint16(26, filename.length, true);
        const central = new DataView(new ArrayBuffer(46));
        central.setUint32(0, 0x02014b50, true);
        central.setUint16(4, 20, true);
        new Uint8Array(central.buffer, 6, 26).set(new Uint8Array(local.buffer, 4, 26));
        central.setUint32(42, offset, true);
        parts.push(local.buffer, filename, data);
        directory.push(central.buffer, filename);
        offset += 30 + filename.length + data.length;
        directorySize += 46 + filename.length;
    }
    const end = new DataView(new ArrayBuffer(22));
    end.setUint32(0, 0x06054b50, true);
    end.setUint16(8, directory.length / 2, true);
    end.setUint16(10, directory.length / 2, true);
    end.setUint32(12, directorySize, true);
    end.setUint32(16, offset, true);
    return new Blob([...parts, ...directory, end.buffer],
        {type: "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"});
}
