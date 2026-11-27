<?php

function spreadsheetXml(ZipArchive $book, string $part): SimpleXMLElement
{
    $info = $book->statName($part);
    if (!$info || $info['size'] > 8 * 1024 * 1024) {
        throw new InvalidArgumentException('The spreadsheet has a missing or oversized workbook part.');
    }
    $text = $book->getFromName($part);
    if ($text === false || str_contains($text, "\0") || stripos($text, '<!DOCTYPE') !== false) {
        throw new InvalidArgumentException('The workbook XML is invalid.');
    }
    $xml = @simplexml_load_string($text, SimpleXMLElement::class, LIBXML_NONET);
    if ($xml === false) {
        throw new InvalidArgumentException('The workbook XML is invalid.');
    }
    return $xml;
}

function spreadsheetRows(string $path, string $extension): Generator
{
    if ($extension === 'csv') {
        $stream = fopen($path, 'rb');
        try {
            if (fread($stream, 3) !== "\xEF\xBB\xBF") rewind($stream);
            while (($row = fgetcsv($stream, 0, ',', '"', '')) !== false) yield $row;
        } finally {
            fclose($stream);
        }
        return;
    }
    if (!class_exists(ZipArchive::class) || !function_exists('simplexml_load_string')) {
        throw new InvalidArgumentException('This server cannot read XLSX files. Save the sheet as CSV UTF-8 in Excel and retry.');
    }
    $book = new ZipArchive();
    if ($book->open($path, ZipArchive::RDONLY) !== true) {
        throw new InvalidArgumentException('Choose a valid Excel workbook.');
    }
    try {
        $workbook = spreadsheetXml($book, 'xl/workbook.xml');
        $sheet = $workbook->sheets->sheet[0] ?? null;
        $relId = (string) ($sheet?->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')?->id ?? '');
        $relations = spreadsheetXml($book, 'xl/_rels/workbook.xml.rels');
        $part = '';
        foreach ($relations->Relationship as $relation) {
            if ((string) $relation['Id'] === $relId && str_ends_with((string) $relation['Type'], '/worksheet')) {
                $target = (string) $relation['Target'];
                $part = str_starts_with($target, '/') ? substr($target, 1) : 'xl/' . $target;
                break;
            }
        }
        if (!preg_match('~^xl/worksheets/[A-Za-z0-9_.-]+\.xml$~D', $part)) {
            throw new InvalidArgumentException('The first sheet must be an Excel worksheet.');
        }
        $strings = [];
        if ($book->locateName('xl/sharedStrings.xml') !== false) {
            foreach (spreadsheetXml($book, 'xl/sharedStrings.xml')->si as $item) {
                $strings[] = spreadsheetCellText($item);
            }
        }
        foreach (spreadsheetXml($book, $part)->sheetData->row as $row) {
            $cells = [];
            foreach ($row->c as $cell) {
                if (!preg_match('/^([A-Z]{1,3})[0-9]+$/D', (string) $cell['r'], $match)) {
                    throw new InvalidArgumentException('The worksheet contains an invalid cell reference.');
                }
                $column = 0;
                foreach (str_split($match[1]) as $letter) $column = $column * 26 + ord($letter) - 64;
                if ($column > 64) continue;
                $value = (string) $cell->v;
                $cells[$column - 1] = match ((string) $cell['t']) {
                    's' => $strings[(int) $value] ?? '',
                    'inlineStr' => spreadsheetCellText($cell->is),
                    default => $value,
                };
            }
            yield $cells;
        }
    } finally {
        $book->close();
    }
}

function spreadsheetCellText(SimpleXMLElement $item): string
{
    $text = (string) $item->t;
    foreach ($item->r as $run) $text .= (string) $run->t;
    return $text;
}

function spreadsheetScholarBatch(array $file, PDO $db): array
{
    $name = $file['name'] ?? null;
    $path = $file['tmp_name'] ?? null;
    $ext = is_string($name) ? strtolower(pathinfo($name, PATHINFO_EXTENSION)) : '';
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK ||
        !in_array($ext, ['xlsx', 'csv'], true) || ($file['size'] ?? 0) > 5 * 1024 * 1024 ||
        !is_string($path) || !is_uploaded_file($path)) {
        throw new InvalidArgumentException('Choose an XLSX or CSV file up to 5 MB.');
    }
    return buildSpreadsheetScholarBatch(spreadsheetRows($path, $ext), array_column(scholarshipCatalog($db), 0));
}

function buildSpreadsheetScholarBatch(iterable $rows, array $catalog): array
{
    $required = ['student id', 'student name', 'course / year', 'school year', 'semester', 'scholarships', 'status'];
    $batch = ['scholars' => [], 'discountHistory' => [], 'activities' => []];
    $header = null;
    $seen = [];
    $line = 0;
    $updated = gmdate('Y-m-d H:i') . ' UTC';
    foreach ($rows as $row) {
        $line++;
        if ($line > 2001) throw new InvalidArgumentException('Limit each spreadsheet to 2,000 rows plus the header.');
        $row = array_map(static fn($value): string => trim((string) $value), $row);
        if (!array_filter($row, static fn(string $value): bool => $value !== '')) continue;
        if ($header === null) {
            $labels = array_filter(array_map(static fn(string $value): string => strtolower(preg_replace('/^\xEF\xBB\xBF/', '', $value)), $row),
                static fn(string $value): bool => $value !== '');
            if (array_diff($required, $labels) || array_filter(array_count_values($labels), static fn(int $count): bool => $count > 1)) {
                throw new InvalidArgumentException('Use one of each required header from the Scholar Master List CSV.');
            }
            $header = array_flip($labels);
            continue;
        }
        $get = static fn(string $key): string => $row[$header[$key]] ?? '';
        $id = $get('student id');
        $year = canonicalReportYear($get('school year'));
        $programs = $get('scholarships');
        $names = in_array($programs, $catalog, true) ? [$programs] :
            array_values(array_unique(array_map('trim', preg_split(str_contains($programs, ';') ? '/;/' : '/[,|]/', $programs))));
        $semester = $get('semester');
        $status = $get('status');
        try {
            if (isset($seen[$id])) throw new InvalidArgumentException('Duplicate student ID in this file.');
            if (!preg_match('/^(\d{4})\x{2013}(\d{4})$/uD', $year, $years) ||
                (int) $years[2] !== (int) $years[1] + 1) {
                throw new InvalidArgumentException('Enter a school year such as 2025-2026.');
            }
            if (!in_array($semester, SCHOLAR_SEMESTERS, true) || !in_array($status, ['Approved', 'Pending'], true) ||
                array_diff($names, $catalog)) {
                throw new InvalidArgumentException('Choose valid semester, status, and scholarship values from ScholarHub.');
            }
            $scholar = validateRecord('scholars', [
                'id' => $id, 'name' => $get('student name'), 'course' => $get('course / year'),
                'period' => $year, 'semester' => $semester, 'scholarship' => $names,
                'status' => $status, 'updated' => $updated,
            ]);
        } catch (InvalidArgumentException $error) {
            throw new InvalidArgumentException('Spreadsheet row ' . $line . ': ' . $error->getMessage());
        }
        $seen[$id] = true;
        $batch['scholars'][] = $scholar;
        $batch['discountHistory'][] = ['id' => $id, 'name' => $scholar['name'], 'year' => $year,
            'semester' => $semester, 'scholarships' => $names];
    }
    if (!$batch['scholars']) throw new InvalidArgumentException('The spreadsheet has no student records.');
    return $batch;
}
