<?php

const REPORT_TITLES = [
    'scholar-master-list' => 'Scholar Master List',
    'scholarship-summary' => 'Scholarship Summary',
    'scholarship-types-summary' => 'Scholarship Types Summary',
    'activity-history' => 'Activity History',
    'requirement-report' => 'Requirement Report',
];

function reportRows(string $report, array $records, array $audit): Generator
{
    switch ($report) {
        case 'scholar-master-list':
            yield ['Student ID', 'Student name', 'Course / year', 'School year', 'Semester', 'Scholarships', 'Status', 'Last updated'];
            foreach ($records['scholars'] as $scholar) {
                yield [$scholar['id'], $scholar['name'], $scholar['course'], $scholar['period'] ?? '', $scholar['semester'] ?? '',
                    implode(', ', $scholar['scholarship']), $scholar['status'], formatPhilippineTime($scholar['updated'])];
            }
            break;
        case 'scholarship-summary':
            yield ['Scholarship', 'School year', 'Semester', 'Assignments', 'Unique students'];
            $groups = [];
            foreach ($records['discountHistory'] as $discount) {
                foreach (array_unique($discount['scholarships']) as $scholarship) {
                    $key = json_encode([$scholarship, $discount['year'], $discount['semester']], JSON_THROW_ON_ERROR);
                    $groups[$key] ??= ['count' => 0, 'students' => []];
                    $groups[$key]['count']++;
                    $groups[$key]['students'][$discount['id']] = true;
                }
            }
            ksort($groups, SORT_NATURAL | SORT_FLAG_CASE);
            foreach ($groups as $key => $group) {
                yield [...json_decode($key, true, 512, JSON_THROW_ON_ERROR), $group['count'], count($group['students'])];
            }
            break;
        case 'scholarship-types-summary':
            yield ['Scholarship type', 'Category', 'Coverage', 'Benefit / rate', 'Availments', 'Unique students', 'Approved', 'Pending', 'Not approved', 'Amount waived (PHP)'];
            $catalog = array_column(scholarshipCatalog(), null, 0);
            $scholarsById = array_column($records['scholars'], null, 'id');
            $summary = [];
            foreach ($records['discountHistory'] as $discount) {
                $status = $scholarsById[$discount['id']]['status'] ?? 'Pending';
                $waived = (float) ($discount['waivedAmount'] ?? 0);
                $list = array_unique($discount['scholarships']);
                $share = count($list) > 0 ? $waived / count($list) : 0;
                foreach ($list as $scholarship) {
                    $summary[$scholarship] ??= ['count' => 0, 'students' => [], 'Approved' => 0, 'Pending' => 0, 'Not Approved' => 0, 'waived' => 0.0];
                    $summary[$scholarship]['count']++;
                    $summary[$scholarship]['students'][$discount['id']] = true;
                    if (isset($summary[$scholarship][$status])) {
                        $summary[$scholarship][$status]++;
                    }
                    $summary[$scholarship]['waived'] += $share;
                }
            }
            ksort($summary, SORT_NATURAL | SORT_FLAG_CASE);
            foreach ($summary as $name => $row) {
                $meta = $catalog[$name] ?? [$name, 'Custom / Other', '—', '—', '—'];
                yield [
                    $name,
                    $meta[1],
                    $meta[3],
                    $meta[4],
                    $row['count'],
                    count($row['students']),
                    $row['Approved'],
                    $row['Pending'],
                    $row['Not Approved'],
                    number_format($row['waived'], 2, '.', ''),
                ];
            }
            break;
        case 'activity-history':
            yield ['Timestamp', 'Event', 'Actor', 'Details'];
            foreach ($audit as $event) {
                $actor = (int) $event['actor_id'] === 0 ? 'Unauthenticated' : accountRoleLabel($event['actor_role']) . ' #' . $event['actor_id'];
                yield [formatPhilippineTime($event['occurred_at'] . ' UTC'), formatReportAuditLabel($event['action']), $actor,
                    formatReportAuditDetails($event['details'])];
            }
            foreach ($records['activities'] as $activity) {
                yield [$activity[0], $activity[1], '', $activity[2]];
            }
            break;
        case 'requirement-report':
            yield ['Student ID', 'Student name', 'Scholar status', 'Record completeness', 'Missing fields'];
            foreach ($records['scholars'] as $scholar) {
                $missing = [];
                foreach (['id' => 'Student ID', 'name' => 'Student name', 'course' => 'Course / year',
                    'period' => 'School year', 'status' => 'Scholar status'] as $field => $label) {
                    if (trim($scholar[$field] ?? '') === '') {
                        $missing[] = $label;
                    }
                }
                if (!$scholar['scholarship']) {
                    $missing[] = 'Scholarship assignment';
                }
                yield [$scholar['id'], $scholar['name'], $scholar['status'],
                    $missing ? 'Incomplete' : 'Complete', implode(', ', $missing)];
            }
            break;
    }
}

function formatReportAuditLabel(string $action): string
{
    $parts = explode('.', $action, 2);
    return count($parts) === 2
        ? ucfirst($parts[0]) . ' · ' . ucfirst(str_replace('_', ' ', $parts[1]))
        : ucfirst(str_replace('_', ' ', $action));
}

function formatReportAuditDetails(array $details): string
{
    $items = [];
    foreach ($details as $label => $value) {
        $text = is_array($value)
            ? (array_is_list($value) ? implode(', ', $value) : formatReportAuditDetails($value))
            : ($value === null ? 'None' : (is_bool($value) ? ($value ? 'Yes' : 'No') : (string) $value));
        $items[] = ucfirst((string) $label) . ': ' . (is_array($value) && !array_is_list($value) ? '(' . $text . ')' : $text);
    }
    return implode(' | ', $items);
}

function reportExportColumns(mixed $value, int $count): ?array
{
    if ($value === null) return null;
    if (!is_string($value) || strlen($value) > 100 || !preg_match('/\A\d+(?:,\d+)*\z/', $value)) {
        throw new InvalidArgumentException('Select at least one valid export field.');
    }
    $columns = array_map('intval', explode(',', $value));
    if (max($columns) >= $count || count(array_unique($columns)) !== count($columns)) {
        throw new InvalidArgumentException('Select valid export fields.');
    }
    return $columns;
}

function writeReportCsv($stream, iterable $rows, ?array $columns = null): void
{
    foreach ($rows as $row) {
        if ($columns !== null) $row = array_map(static fn(int $column) => $row[$column] ?? '', $columns);
        // Keep spreadsheet applications from evaluating stored text as formulas.
        $cells = array_map(static function ($value) {
            return is_string($value) && preg_match('/^(?:\s*[=+@-]|[\t\r\n])/', $value)
                ? "'" . $value : $value;
        }, $row);
        fputcsv($stream, $cells, ',', '"', '', "\r\n");
    }
}

function canonicalReportYear(string $year): string
{
    return preg_replace('/^(\d{4})\s*[-–—]\s*(\d{4})$/u', '$1–$2', trim($year));
}

function buildReportSnapshot(string $type, array $records, array $audit, string $year, string $semester): array
{
    if ($type === 'activity-history') {
        $year = $semester = '';
    } elseif ($year !== '' || $semester !== '') {
        $historyById = [];
        foreach ($records['discountHistory'] as $row) {
            $historyById[$row['id']][] = $row;
        }
        $matches = static fn(string $period, string $term): bool =>
            ($year === '' || canonicalReportYear($period) === $year) && ($semester === '' || $term === $semester);
        $records['discountHistory'] = array_filter($records['discountHistory'],
            static fn(array $row): bool => $matches($row['year'], $row['semester']));
        $records['scholars'] = array_filter($records['scholars'], static function (array $row) use ($matches, $historyById): bool {
            $period = canonicalReportYear($row['period'] ?? '');
            $term = $row['semester'] ?? '';
            if ($period === '' || $term === '') {
                $periods = [];
                foreach ($historyById[$row['id']] ?? [] as $history) {
                    $historyYear = canonicalReportYear($history['year']);
                    if (($period === '' || $period === $historyYear) && ($term === '' || $term === $history['semester'])) {
                        $periods[$historyYear . ':' . $history['semester']] = [$historyYear, $history['semester']];
                    }
                }
                if (count($periods) === 1) {
                    [$period, $term] = reset($periods);
                }
            }
            return $matches($period, $term);
        });
    }
    $rows = iterator_to_array(reportRows($type, $records, $audit), false);
    $columns = array_shift($rows);
    return ['year' => $year, 'semester' => $semester, 'columns' => $columns, 'rows' => $rows];
}
