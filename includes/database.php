<?php

class DatabaseConfigurationException extends RuntimeException {}

const SCHOLAR_SEMESTERS = ['1st Semester', '2nd Semester', 'Summer'];

function formatPhilippineTime(string $timestamp): string
{
    if (!preg_match('/\A\d{4}-\d{2}-\d{2} \d{2}:\d{2}(:\d{2})? UTC\z/', $timestamp, $match)) {
        return $timestamp;
    }
    $format = isset($match[1]) ? '!Y-m-d H:i:s \U\T\C' : '!Y-m-d H:i \U\T\C';
    $date = DateTimeImmutable::createFromFormat($format, $timestamp, new DateTimeZone('UTC'));
    $errors = DateTimeImmutable::getLastErrors();
    if (!$date || ($errors && ($errors['warning_count'] || $errors['error_count']))) return $timestamp;
    return $date->setTimezone(new DateTimeZone('Asia/Manila'))->format('M j, Y g:i A') . ' PHT';
}

function privateSettings(): array
{
    $file = __DIR__ . '/database.local.php';
    $config = is_file($file) ? require $file : [];
    if (!is_array($config)) {
        throw new DatabaseConfigurationException('includes/database.local.php must return a PHP settings array.');
    }
    return $config;
}

function recordsDatabase(): PDO
{
    $config = privateSettings();
    $setting = static function (string $key, string $fallback = '') use ($config): string {
        $value = getenv('SCHOLARHUB_DB_' . strtoupper($key));
        return $value === false ? (string) ($config[$key] ?? $fallback) : $value;
    };
    $host = $setting('host');
    $port = $setting('port', '3306');
    $name = $setting('name');
    $user = $setting('user');
    $password = $setting('password');
    if ($host === '' || $name === '' || $user === '') {
        throw new DatabaseConfigurationException(is_file(__DIR__ . '/database.local.php')
            ? 'Database settings are incomplete. Check the host, name, and user in includes/database.local.php.'
            : 'Database settings are missing. Copy includes/database.example.php to includes/database.local.php on the deployed site and fill in your VistaPanel MySQL values.');
    }

    return new PDO(
        "mysql:host=$host;port=$port;dbname=$name;charset=utf8mb4",
        $user,
        $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]
    );
}

function readRecords(PDO $db, bool $includeActivities = true): array
{
    $records = ['scholars' => [], 'discountHistory' => [], 'activities' => []];
    $query = 'SELECT kind, payload FROM records' . ($includeActivities ? '' : " WHERE kind <> 'activities'");
    foreach ($db->query($query . ' ORDER BY record_id DESC') as $row) {
        $records[$row['kind']][] = json_decode($row['payload'], true, 512, JSON_THROW_ON_ERROR);
    }
    return $records;
}

function insertRecord(PDOStatement $insert, string $kind, array $record): void
{
    $insert->execute([$kind, $kind === 'scholars' ? $record['id'] : null, json_encode($record, JSON_THROW_ON_ERROR)]);
}

function validateRecord(string $kind, array $record): array
{
    $fields = [
        'scholars' => ['id', 'name', 'course', 'status', 'updated'],
        'discountHistory' => ['id', 'name', 'year', 'semester'],
        'activities' => [0, 1, 2],
    ];
    foreach ($fields[$kind] as $key) {
        if (!is_string($record[$key] ?? null) || trim($record[$key]) === '' ||
            strlen($record[$key]) > 1024 || preg_match('/[\x00-\x1F\x7F]/', $record[$key])) {
            throw new InvalidArgumentException('A record is missing a required field.');
        }
    }
    if ($kind === 'scholars' && isset($record['period']) &&
        (!is_string($record['period']) || strlen($record['period']) > 64 || preg_match('/[\x00-\x1F\x7F]/', $record['period']))) {
        throw new InvalidArgumentException('The school year is invalid.');
    }
    if ($kind === 'scholars' && strlen($record['id']) > 255) {
        throw new InvalidArgumentException('The student ID exceeds 255 bytes.');
    }
    if ($kind !== 'activities') {
        $field = $kind === 'scholars' ? 'scholarship' : 'scholarships';
        $names = $record[$field] ?? null;
        if (!is_array($names) || !array_is_list($names) || count($names) > 100) {
            throw new InvalidArgumentException('The scholarship list is invalid.');
        }
        foreach ($names as $name) {
            if (!is_string($name) || trim($name) === '' || strlen($name) > 255 || preg_match('/[\x00-\x1F\x7F]/', $name)) {
                throw new InvalidArgumentException('A scholarship name is invalid.');
            }
        }
        $fields[$kind][] = $field;
    }
    if ($kind === 'scholars') {
        $fields[$kind][] = 'period';
        if (array_key_exists('semester', $record)) {
            if (!in_array($record['semester'], SCHOLAR_SEMESTERS, true)) {
                throw new InvalidArgumentException('Choose a valid semester.');
            }
            $fields[$kind][] = 'semester';
        }
    }
    return array_intersect_key($record, array_flip($fields[$kind]));
}

function scholarshipCatalog(?PDO $db = null): array
{
    $catalog = json_decode(file_get_contents(__DIR__ . '/catalog.json'), true, 512, JSON_THROW_ON_ERROR);
    $byName = array_column($catalog, null, 0);
    if ($db) {
        foreach ($db->query('SELECT name, category, coverage, benefit FROM scholarship_types ORDER BY name') as $row) {
            $original = $byName[$row['name']] ?? null;
            $description = $original && $original[3] === $row['coverage'] && $original[4] === $row['benefit']
                ? $original[2] : $row['coverage'];
            $byName[$row['name']] = [$row['name'], $row['category'], $description, $row['coverage'], $row['benefit']];
            if (isset($original[5])) $byName[$row['name']][5] = $original[5];
        }
    }
    return array_values($byName);
}

function saveScholarshipType(PDO $db, array $type, bool $editing = false): ?array
{
    $values = [];
    foreach (['name' => 120, 'category' => 80, 'coverage' => 255, 'benefit' => 100] as $field => $limit) {
        $value = is_string($type[$field] ?? null) ? trim($type[$field]) : '';
        if (!preg_match('/\A[^\x00-\x1F\x7F]{1,' . $limit . '}\z/u', $value) ||
            ($field === 'name' && strlen($value) > 255)) {
            throw new InvalidArgumentException('Enter a valid scholarship ' . $field . '.');
        }
        $values[$field] = $value;
    }
    $catalog = scholarshipCatalog($db);
    $original = array_column($catalog, null, 0)[$values['name']] ?? null;
    if ($editing && (!$original || $type['name'] !== $original[0])) {
        throw new DomainException('The scholarship type could not be found.');
    }
    foreach (['category' => 1, 'coverage' => 3, 'benefit' => 4] as $field => $column) {
        if (!in_array($values[$field], array_column($catalog, $column), true)) {
            throw new InvalidArgumentException('Select an available scholarship ' . $field . '.');
        }
    }
    foreach ($editing ? [] : $catalog as $item) {
        if (strcasecmp($item[0], $values['name']) === 0) {
            throw new DomainException('A scholarship with that name already exists.');
        }
    }
    if ($editing) {
        $before = ['Category' => $original[1], 'Coverage' => $original[3], 'Benefit' => $original[4]];
        $after = ['Category' => $values['category'], 'Coverage' => $values['coverage'], 'Benefit' => $values['benefit']];
        if ($before === $after) return null;
        $save = $db->prepare('INSERT INTO scholarship_types (name, category, coverage, benefit) VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE category = ?, coverage = ?, benefit = ?');
        $save->execute([...array_values($values), ...array_values($after)]);
        return ['Scholarship' => $values['name'], 'before' => $before, 'after' => $after];
    }
    $save = $db->prepare('INSERT INTO scholarship_types (name, category, coverage, benefit) VALUES (?, ?, ?, ?)');
    $save->execute(array_values($values));
    return $values;
}

function decidePendingScholar(PDO $db, array $request): array
{
    $id = $request['id'] ?? null;
    $status = $request['status'] ?? null;
    if (!is_string($id) || trim($id) === '' || strlen($id) > 255 ||
        !in_array($status, ['Approved', 'Not Approved'], true)) {
        throw new InvalidArgumentException('Choose a student and a valid decision.');
    }
    $select = $db->prepare("SELECT payload FROM records WHERE kind = 'scholars' AND student_id = ? FOR UPDATE");
    $select->execute([$id]);
    $payload = $select->fetchColumn();
    if ($payload === false) {
        throw new DomainException('The student record could not be found.');
    }
    $scholar = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
    if ($scholar['status'] !== 'Pending') {
        throw new DomainException('This record has already been decided. Refresh records to see its current status.');
    }
    $scholar['status'] = $status;
    $scholar['updated'] = gmdate('Y-m-d H:i') . ' UTC';
    $update = $db->prepare("UPDATE records SET payload = ? WHERE kind = 'scholars' AND student_id = ?");
    $update->execute([json_encode($scholar, JSON_THROW_ON_ERROR), $id]);
    return ['reference' => $id, 'before' => ['Status' => 'Pending'], 'after' => ['Status' => $status]];
}

function currentSchoolYear(): string
{
    $year = (int) (new DateTimeImmutable('now', new DateTimeZone('Asia/Manila')))->format('Y');
    return $year . '–' . ($year + 1);
}

function editScholarAssignments(PDO $db, array $request): ?array
{
    $id = $request['id'] ?? null;
    if (!is_string($id) || trim($id) === '' || strlen($id) > 255) {
        throw new InvalidArgumentException('Choose a valid student.');
    }
    $select = $db->prepare("SELECT record_id, payload FROM records WHERE kind = 'scholars' AND student_id = ? FOR UPDATE");
    $select->execute([$id]);
    $row = $select->fetch(PDO::FETCH_ASSOC);
    if (!$row) throw new DomainException('The student record could not be found.');

    $scholar = json_decode($row['payload'], true, 512, JSON_THROW_ON_ERROR);
    $originalNames = (array) ($scholar['scholarship'] ?? []);
    $updated = array_replace($scholar, [
        'id' => array_key_exists('newId', $request) ? $request['newId'] : $id,
        'name' => array_key_exists('name', $request) ? $request['name'] : $scholar['name'],
        'scholarship' => $request['scholarship'] ?? null,
        'status' => $request['status'] ?? null,
        'updated' => gmdate('Y-m-d H:i') . ' UTC',
    ]);
    if ($updated['id'] !== $id &&
        (!is_string($updated['id']) || !preg_match('/\A[0-9]{4}-[0-9]{5}\z/', $updated['id']))) {
        throw new InvalidArgumentException('Enter a student ID in the format 2026-00125.');
    }
    $validated = validateRecord('scholars', $updated);
    $names = $validated['scholarship'];
    $allowed = array_merge($originalNames, array_column(scholarshipCatalog($db), 0));
    if (!$names || array_diff($names, $allowed) || count(array_unique($names)) !== count($names) ||
        !in_array($validated['status'], ['Approved', 'Pending', 'Not Approved'], true)) {
        throw new InvalidArgumentException('Choose valid scholarships and a status.');
    }

    $assignmentsChanged = (bool) (array_diff($names, $originalNames) || array_diff($originalNames, $names));
    $identityChanged = $updated['id'] !== $id || $updated['name'] !== $scholar['name'];
    if (!$assignmentsChanged && !$identityChanged && $updated['status'] === $scholar['status']) return null;
    $historyId = "BINARY JSON_UNQUOTE(JSON_EXTRACT(payload, '$.id'))";
    if ($updated['id'] !== $id) {
        $check = $db->prepare("SELECT 1 FROM records WHERE kind = 'discountHistory' AND $historyId = ? LIMIT 1");
        $check->execute([$updated['id']]);
        if ($check->fetchColumn()) throw new DomainException('The student ID is already in use by a discount record.');
    }
    $save = $db->prepare('UPDATE records SET student_id = ?, payload = ? WHERE record_id = ?');
    if (!$assignmentsChanged) $updated['scholarship'] = $originalNames;
    $save->execute([$updated['id'], json_encode($updated, JSON_THROW_ON_ERROR), $row['record_id']]);
    if ($assignmentsChanged) {
        $saveHistory = $db->prepare('UPDATE records SET payload = ? WHERE record_id = ?');
        updateScholarDiscountHistory($db, $saveHistory, array_replace($updated, ['id' => $id]));
    }
    if ($identityChanged) {
        $rename = $db->prepare("UPDATE records SET payload = JSON_SET(payload, '$.id', ?, '$.name', ?)
            WHERE kind = 'discountHistory' AND $historyId = ?");
        $rename->execute([$updated['id'], $updated['name'], $id]);
    }
    return [
        'reference' => $id,
        'before' => ['Student ID' => $id, 'Status' => $scholar['status'], 'Scholarships' => implode('; ', $originalNames)],
        'after' => ['Student ID' => $updated['id'], 'Name changed' => $updated['name'] !== $scholar['name'] ? 'Yes' : 'No',
            'Status' => $updated['status'], 'Scholarships' => implode('; ', $updated['scholarship'])],
    ];
}

function updateScholarDiscountHistory(PDO $db, PDOStatement $save, array $scholar): void
{
    $history = $db->prepare("SELECT record_id, payload FROM records WHERE kind = 'discountHistory'
        AND BINARY JSON_UNQUOTE(JSON_EXTRACT(payload, '$.id')) = ? FOR UPDATE");
    $history->execute([$scholar['id']]);
    $year = canonicalReportYear($scholar['period'] ?? '');
    $semester = $scholar['semester'] ?? '';
    $matching = [];
    $periods = [];
    foreach ($history->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $payload = json_decode($row['payload'], true, 512, JSON_THROW_ON_ERROR);
        $historyYear = canonicalReportYear($payload['year']);
        if (($year !== '' && $historyYear !== $year) || ($semester !== '' && $payload['semester'] !== $semester)) continue;
        $periods[$historyYear][$payload['semester']] = true;
        $matching[$row['record_id']] = $payload;
    }
    if (count($periods) > 1 || ($periods && count(reset($periods)) > 1)) {
        throw new DomainException('This student has more than one matching school period. The assignments were not changed.');
    }
    foreach ($matching as $recordId => $payload) {
        $payload['scholarships'] = $scholar['scholarship'];
        $save->execute([json_encode($payload, JSON_THROW_ON_ERROR), $recordId]);
    }
    if (!$matching) {
        if ($year === '' || $semester === '') {
            throw new DomainException('This student has no matching school period. The assignments were not changed.');
        }
        $payload = [
            'id' => $scholar['id'], 'name' => $scholar['name'], 'year' => $year,
            'semester' => $semester, 'scholarships' => $scholar['scholarship'],
        ];
        if (isset($scholar['_sampleBatch'])) $payload['_sampleBatch'] = $scholar['_sampleBatch'];
        $insert = $db->prepare('INSERT INTO records (kind, student_id, payload) VALUES (?, ?, ?)');
        insertRecord($insert, 'discountHistory', $payload);
    }
}

function newScholarRecords(array $scholar, ?PDO $db = null): array
{
    if (!is_string($scholar['id'] ?? null) || !preg_match('/\A[0-9]{4}-[0-9]{5}\z/', $scholar['id'])) {
        throw new InvalidArgumentException('Enter a student ID in the format 2026-00125.');
    }
    $updated = gmdate('Y-m-d H:i') . ' UTC';
    $scholar['updated'] = $updated;
    $period = currentSchoolYear();
    $scholar['period'] = $period;
    $scholar = validateRecord('scholars', $scholar);
    $names = $scholar['scholarship'];
    $knownNames = array_column(scholarshipCatalog($db), 0);
    if (!$names || array_diff($names, $knownNames) || count(array_unique($names)) !== count($names) ||
        !in_array($scholar['status'], ['Approved', 'Pending', 'Not Approved'], true)) {
        throw new InvalidArgumentException('Choose valid scholarships and a status.');
    }
    return [
        'scholars' => [$scholar],
        'discountHistory' => [[
            'id' => $scholar['id'], 'name' => $scholar['name'], 'year' => $period,
            'semester' => $scholar['semester'] ?? '1st Semester', 'scholarships' => $names,
        ]],
        'activities' => [],
    ];
}
