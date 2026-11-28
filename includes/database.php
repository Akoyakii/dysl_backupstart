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
            if (!is_string($record['semester']) || trim($record['semester']) === '' || strlen($record['semester']) > 64) {
                throw new InvalidArgumentException('Choose a valid semester.');
            }
            $fields[$kind][] = 'semester';
        }
        if (array_key_exists('program', $record) && $record['program'] !== '') {
            if (!is_string($record['program']) || strlen($record['program']) > 64 || preg_match('/[\x00-\x1F\x7F]/', $record['program'])) {
                throw new InvalidArgumentException('The program is invalid.');
            }
            $fields[$kind][] = 'program';
        }
    }
    if ($kind === 'scholars') {
        foreach (['email', 'phone', 'address'] as $field) {
            if (array_key_exists($field, $record)) {
                if (!is_string($record[$field]) || strlen($record[$field]) > 255 || preg_match('/[\x00-\x1F\x7F]/', $record[$field])) {
                    throw new InvalidArgumentException('The student contact information is invalid.');
                }
                $fields[$kind][] = $field;
            }
        }
    }
    if ($kind !== 'activities' && array_key_exists('waivedAmount', $record) && $record['waivedAmount'] !== null && $record['waivedAmount'] !== '') {
        if (!is_numeric($record['waivedAmount']) || (float) $record['waivedAmount'] < 0 || (float) $record['waivedAmount'] > 10000000) {
            throw new InvalidArgumentException('Enter a valid waived amount.');
        }
        $record['waivedAmount'] = round((float) $record['waivedAmount'], 2);
        $fields[$kind][] = 'waivedAmount';
    }
    return array_intersect_key($record, array_flip($fields[$kind]));
}

function scholarshipCatalog(?PDO $db = null): array
{
    $catalog = json_decode(file_get_contents(__DIR__ . '/catalog.json'), true, 512, JSON_THROW_ON_ERROR);
    $byName = array_column($catalog, null, 0);
    foreach ($byName as &$entry) {
        if (!isset($entry[5])) $entry[5] = '';
        $entry[6] = false;
    }
    unset($entry);
    if ($db) {
        $dbCatalog = [];
        foreach ($db->query('SELECT name, category, description, coverage, benefit, disabled FROM scholarship_types ORDER BY name') as $row) {
            $original = $byName[$row['name']] ?? null;
            $description = trim((string) ($row['description'] ?? '')) !== ''
                ? $row['description']
                : ($original && $original[3] === $row['coverage'] && $original[4] === $row['benefit'] ? $original[2] : $row['coverage']);
            $dbCatalog[$row['name']] = [
                $row['name'],
                $row['category'],
                $description,
                $row['coverage'],
                $row['benefit'],
                $original[5] ?? '',
                (bool) $row['disabled'],
            ];
        }
        if ($dbCatalog) return array_values($dbCatalog);
    }
    return array_values($byName);
}

function dataEntryLockStatus(PDO $db): array
{
    $row = $db->query('SELECT data_entry_locked, lock_reason FROM record_write_lock WHERE id = 1')->fetch(PDO::FETCH_ASSOC);
    return [
        'locked' => (bool) ($row['data_entry_locked'] ?? false),
        'reason' => (string) ($row['lock_reason'] ?? ''),
    ];
}

function setDataEntryLock(PDO $db, bool $locked, string $reason): array
{
    $cleanReason = trim($reason);
    if (strlen($cleanReason) > 255 || preg_match('/[\x00-\x1F\x7F]/', $cleanReason)) {
        throw new InvalidArgumentException('Enter a lock reason up to 255 characters.');
    }
    $stmt = $db->prepare('UPDATE record_write_lock SET data_entry_locked = ?, lock_reason = ? WHERE id = 1');
    $stmt->execute([$locked ? 1 : 0, $locked ? $cleanReason : '']);
    return ['locked' => $locked, 'reason' => $locked ? $cleanReason : ''];
}

function availableSemesters(PDO $db): array
{
    $rows = $db->query('SELECT school_year, semester FROM semesters ORDER BY school_year DESC, id ASC')->fetchAll(PDO::FETCH_ASSOC);
    return $rows;
}

function addSemester(PDO $db, string $schoolYear, string $semester): array
{
    $year = canonicalReportYear($schoolYear);
    if (!preg_match('/^\d{4}\x{2013}\d{4}$/uD', $year)) {
        throw new InvalidArgumentException('Enter a school year such as 2025-2026.');
    }
    $term = trim($semester);
    if (!preg_match('/\A[^\x00-\x1F\x7F]{1,20}\z/u', $term)) {
        throw new InvalidArgumentException('Enter a valid semester name (up to 20 characters).');
    }
    $stmt = $db->prepare('INSERT INTO semesters (school_year, semester, created_at) VALUES (?, ?, UTC_TIMESTAMP())');
    $stmt->execute([$year, $term]);
    return ['school_year' => $year, 'semester' => $term];
}

function toggleScholarshipTypeDisabled(PDO $db, string $name, bool $disabled): array
{
    $catalog = array_column(scholarshipCatalog($db), null, 0);
    $item = $catalog[$name] ?? null;
    if (!$item) {
        throw new DomainException('The scholarship type could not be found.');
    }
    $stmt = $db->prepare('INSERT INTO scholarship_types (name, category, description, coverage, benefit, disabled) VALUES (?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE disabled = ?');
    $flag = $disabled ? 1 : 0;
    $stmt->execute([$item[0], $item[1], $item[2], $item[3], $item[4], $flag, $flag]);
    return ['Scholarship' => $item[0], 'Status' => $disabled ? 'Disabled' : 'Enabled'];
}

function deleteScholarshipType(PDO $db, string $name): void
{
    $catalog = array_column(scholarshipCatalog($db), null, 0);
    if (!isset($catalog[$name])) {
        throw new DomainException('The scholarship type could not be found.');
    }
    $check = $db->prepare("SELECT 1 FROM records WHERE kind IN ('scholars', 'discountHistory')
        AND (JSON_CONTAINS(payload, JSON_QUOTE(?), '$.scholarship') OR JSON_CONTAINS(payload, JSON_QUOTE(?), '$.scholarships')) LIMIT 1");
    $check->execute([$name, $name]);
    if ($check->fetchColumn()) {
        throw new DomainException('This scholarship type is linked to existing student or discount records. Disable it instead of deleting.');
    }
    $del = $db->prepare('DELETE FROM scholarship_types WHERE name = ?');
    $del->execute([$name]);
    if ($del->rowCount() === 0) {
        throw new DomainException('Built-in scholarship types cannot be deleted. Disable it instead.');
    }
}

function validateScholarshipRules(array $names, array $catalog, array $existingHistory = [], string $targetYear = '', int $maxResidency = 5): void
{
    $byName = array_column($catalog, null, 0);
    $external = 0;
    $inHouse = 0;
    $athletic = 0;
    $hasFull = false;
    foreach ($names as $name) {
        $item = $byName[$name] ?? null;
        if (!$item) continue;
        $cat = $item[1];
        $benefit = $item[4];
        if ($benefit === '100%') $hasFull = true;
        if ($cat === 'External and Government Grants') {
            $external++;
        } elseif (in_array($cat, ['DISTRICT', 'PROVINCIAL', 'INTERNATIONAL'], true)) {
            $athletic++;
        } else {
            $inHouse++;
        }
    }
    if ($hasFull && count($names) > 1) {
        throw new InvalidArgumentException('A student with a 100% full scholarship cannot receive an additional scholarship.');
    }
    if ($external > 1) {
        throw new InvalidArgumentException('A student may receive at most one external scholarship.');
    }
    if ($athletic > 1) {
        throw new InvalidArgumentException('A student may receive at most one athletic scholarship.');
    }
    if ($inHouse > 1) {
        throw new InvalidArgumentException('A student may receive at most one in-house scholarship.');
    }
    foreach (['TES', 'TDP'] as $grant) {
        if (!in_array($grant, $names, true)) continue;
        $years = [];
        foreach ($existingHistory as $row) {
            if (in_array($grant, $row['scholarships'] ?? [], true)) {
                $y = canonicalReportYear($row['year'] ?? '');
                if ($y !== '') $years[$y] = true;
            }
        }
        if ($targetYear !== '') $years[canonicalReportYear($targetYear)] = true;
        if (count($years) > $maxResidency) {
            throw new InvalidArgumentException("$grant has a maximum residency of $maxResidency years.");
        }
    }
}

function scholarshipRequiresApproval(array $names, array $catalog): bool
{
    $byName = array_column($catalog, null, 0);
    foreach ($names as $name) {
        if (($byName[$name][1] ?? null) === 'External and Government Grants') return true;
    }
    return false;
}

function hasTesOrTdp(array $names): bool
{
    return (bool) array_intersect(['TES', 'TDP'], $names);
}

function validateScholarshipStatus(array $names, string $status, array $catalog): void
{
    if (!in_array($status, ['Approved', 'Pending'], true)) {
        throw new InvalidArgumentException('Choose a valid scholarship status.');
    }
    if (!scholarshipRequiresApproval($names, $catalog) && $status !== 'Approved') {
        throw new InvalidArgumentException('Only external scholarships can be Pending. In-house scholarships are Approved by default.');
    }
}

function saveScholarshipType(PDO $db, array $type, bool $editing = false): ?array
{
    $values = [];
    foreach (['name' => 120, 'category' => 80, 'description' => 1000, 'coverage' => 255, 'benefit' => 100] as $field => $limit) {
        $value = is_string($type[$field] ?? null) ? trim($type[$field]) : '';
        if (!preg_match('/\A[^\x00-\x1F\x7F]{1,' . $limit . '}\z/u', $value) ||
            ($field === 'name' && strlen($value) > 255)) {
            throw new InvalidArgumentException('Enter a valid scholarship ' . $field . '.');
        }
        $values[$field] = $value;
    }
    $catalog = scholarshipCatalog($db);
    $originalName = is_string($type['originalName'] ?? null) ? trim($type['originalName']) : $values['name'];
    $original = array_column($catalog, null, 0)[$originalName] ?? null;
    if ($editing && !$original) {
        throw new DomainException('The scholarship type could not be found.');
    }
    foreach ($catalog as $item) {
        if (strcasecmp($item[0], $values['name']) === 0 && (!$editing || strcasecmp($item[0], $originalName) !== 0)) {
            throw new DomainException('A scholarship with that name already exists.');
        }
    }
    if ($editing) {
        $before = ['Category' => $original[1], 'Description' => $original[2], 'Coverage' => $original[3], 'Benefit' => $original[4]];
        $after = ['Category' => $values['category'], 'Description' => $values['description'], 'Coverage' => $values['coverage'], 'Benefit' => $values['benefit']];
        if ($before === $after) return null;
        $save = $db->prepare('UPDATE scholarship_types SET name = ?, category = ?, description = ?, coverage = ?, benefit = ? WHERE name = ?');
        $save->execute([$values['name'], $values['category'], $values['description'], $values['coverage'], $values['benefit'], $originalName]);
        return ['Scholarship' => $values['name'], 'before' => $before, 'after' => $after];
    }
    $save = $db->prepare('INSERT INTO scholarship_types (name, category, description, coverage, benefit) VALUES (?, ?, ?, ?, ?)');
    $save->execute(array_values($values));
    return $values;
}

function decidePendingScholar(PDO $db, array $request): array
{
    $id = $request['id'] ?? null;
    $status = $request['status'] ?? null;
    if (!is_string($id) || trim($id) === '' || strlen($id) > 255 ||
        !in_array($status, ['Approved'], true)) {
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
    if (!scholarshipRequiresApproval((array) ($scholar['scholarship'] ?? []), scholarshipCatalog($db))) {
        throw new DomainException('In-house scholarships do not require approval.');
    }
    $scholar['status'] = $status;
    $scholar['updated'] = gmdate('Y-m-d H:i') . ' UTC';
    $update = $db->prepare("UPDATE records SET payload = ? WHERE kind = 'scholars' AND student_id = ?");
    $update->execute([json_encode($scholar, JSON_THROW_ON_ERROR), $id]);
    return ['reference' => $id, 'before' => ['Status' => 'Pending'], 'after' => ['Status' => $status]];
}

function currentSchoolYear(?PDO $db = null): string
{
    if ($db) {
        $settings = readSystemSettings($db);
        if ($settings['activeSchoolYear'] !== '') {
            return $settings['activeSchoolYear'];
        }
    }
    $year = (int) (new DateTimeImmutable('now', new DateTimeZone('Asia/Manila')))->format('Y');
    return $year . '–' . ($year + 1);
}

function defaultSystemSettings(): array
{
    return [
        'activeSchoolYear' => '',
        'activeSemester' => '1st Semester',
        'pageSize' => '15',
        'maxResidencyYears' => '5',
        'inHouseDefaultStatus' => 'Approved',
        'externalRequiresApproval' => '1',
        'workspaceSubtitle' => 'Dr. Yanga Colleges Inc. · Internal scholarship records',
    ];
}

function readSystemSettings(PDO $db): array
{
    $settings = defaultSystemSettings();
    try {
        foreach ($db->query('SELECT setting_key, setting_value FROM system_settings') as $row) {
            if (array_key_exists($row['setting_key'], $settings)) {
                $settings[$row['setting_key']] = (string) $row['setting_value'];
            }
        }
    } catch (PDOException $error) {
        if ((int) ($error->errorInfo[1] ?? 0) !== 1146) {
            throw $error;
        }
    }
    return $settings;
}

function academicLists(PDO $db): array
{
    try {
        $departments = $db->query('SELECT id, name, disabled FROM college_departments ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);
        $programs = $db->query('SELECT id, code, name, department_id, disabled FROM academic_programs ORDER BY code')->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $error) {
        if ((int) ($error->errorInfo[1] ?? 0) === 1146) return ['departments' => [], 'programs' => []];
        throw $error;
    }
    return ['departments' => $departments, 'programs' => $programs];
}

function saveAcademicListItem(PDO $db, string $kind, array $input): array
{
    $value = trim((string) ($input['value'] ?? ''));
    if ($kind === 'department') {
        if ($value === '' || strlen($value) > 160) throw new InvalidArgumentException('Enter a college or department name up to 160 characters.');
        $stmt = $db->prepare('INSERT INTO college_departments (name, created_at) VALUES (?, UTC_TIMESTAMP())');
        $stmt->execute([$value]);
        return ['type' => 'department', 'value' => $value];
    }
    $code = strtoupper(trim((string) ($input['code'] ?? '')));
    if ($code === '' || !preg_match('/\A[A-Z0-9][A-Z0-9-]{1,31}\z/', $code) || $value === '' || strlen($value) > 160) {
        throw new InvalidArgumentException('Enter a valid program code and name.');
    }
    $stmt = $db->prepare('INSERT INTO academic_programs (code, name, created_at) VALUES (?, ?, UTC_TIMESTAMP())');
    $stmt->execute([$code, $value]);
    return ['type' => 'program', 'value' => $code . ' — ' . $value];
}

function toggleAcademicListItem(PDO $db, string $kind, int $id, bool $disabled): void
{
    $table = $kind === 'department' ? 'college_departments' : 'academic_programs';
    if (!in_array($kind, ['department', 'program'], true) || $id < 1) throw new InvalidArgumentException('Choose a valid academic item.');
    $stmt = $db->prepare("UPDATE $table SET disabled = ? WHERE id = ?");
    $stmt->execute([$disabled ? 1 : 0, $id]);
    if ($stmt->rowCount() === 0) throw new DomainException('The academic item could not be found.');
}

function deleteAcademicListItem(PDO $db, string $kind, int $id): void
{
    if (!in_array($kind, ['department', 'program'], true) || $id < 1) throw new InvalidArgumentException('Choose a valid academic item.');
    $table = $kind === 'department' ? 'college_departments' : 'academic_programs';
    $column = $kind === 'department' ? 'name' : 'code';
    $find = $db->prepare("SELECT $column FROM $table WHERE id = ?");
    $find->execute([$id]);
    $value = $find->fetchColumn();
    if ($value === false) throw new DomainException('The academic item could not be found.');
    $used = $db->prepare("SELECT 1 FROM records WHERE kind = 'scholars' AND (JSON_UNQUOTE(JSON_EXTRACT(payload, '$.program')) = ? OR JSON_UNQUOTE(JSON_EXTRACT(payload, '$.course')) LIKE CONCAT(?, ' %') OR JSON_UNQUOTE(JSON_EXTRACT(payload, '$.course')) = ?) LIMIT 1");
    $used->execute([(string) $value, (string) $value, (string) $value]);
    if ($used->fetchColumn()) throw new DomainException('This item is used by student records. Disable it instead of removing it.');
    $remove = $db->prepare("DELETE FROM $table WHERE id = ?");
    $remove->execute([$id]);
}

function saveSystemSettings(PDO $db, array $input): array
{
    $defaults = defaultSystemSettings();
    $year = preg_replace('/^(\d{4})\s*[-–—]\s*(\d{4})$/u', '$1–$2', trim((string) ($input['activeSchoolYear'] ?? '')));
    if ($year !== '' && (!preg_match('/^(\d{4})\x{2013}(\d{4})$/uD', $year, $m) || (int) $m[2] !== (int) $m[1] + 1)) {
        throw new InvalidArgumentException('Enter an active school year such as 2025-2026, or leave blank for automatic.');
    }
    $semester = trim((string) ($input['activeSemester'] ?? $defaults['activeSemester']));
    $validTerms = array_values(array_unique([...SCHOLAR_SEMESTERS, ...array_column(availableSemesters($db), 'semester')]));
    if (!in_array($semester, $validTerms, true)) {
        throw new InvalidArgumentException('Choose a valid active semester.');
    }
    $pageSize = (string) ($input['pageSize'] ?? $defaults['pageSize']);
    if (!in_array($pageSize, ['10', '15', '25', '50'], true)) {
        throw new InvalidArgumentException('Choose 10, 15, 25, or 50 rows per page.');
    }
    $residency = filter_var($input['maxResidencyYears'] ?? $defaults['maxResidencyYears'], FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1, 'max_range' => 10],
    ]);
    if ($residency === false) {
        throw new InvalidArgumentException('TES / TDP maximum residency must be between 1 and 10 years.');
    }
    $inHouseStatus = (string) ($input['inHouseDefaultStatus'] ?? $defaults['inHouseDefaultStatus']);
    if (!in_array($inHouseStatus, ['Approved', 'Pending'], true)) {
        throw new InvalidArgumentException('Choose Approved or Pending for in-house default status.');
    }
    $externalApproval = !empty($input['externalRequiresApproval']) && $input['externalRequiresApproval'] !== '0' ? '1' : '0';
    $subtitle = trim((string) ($input['workspaceSubtitle'] ?? ''));
    if ($subtitle === '' || strlen($subtitle) > 120 || preg_match('/[\x00-\x1F\x7F]/', $subtitle)) {
        throw new InvalidArgumentException('Enter a workspace subtitle up to 120 characters.');
    }

    $values = [
        'activeSchoolYear' => $year,
        'activeSemester' => $semester,
        'pageSize' => $pageSize,
        'maxResidencyYears' => (string) $residency,
        'inHouseDefaultStatus' => $inHouseStatus,
        'externalRequiresApproval' => $externalApproval,
        'workspaceSubtitle' => $subtitle,
    ];
    $before = readSystemSettings($db);
    $stmt = $db->prepare('INSERT INTO system_settings (setting_key, setting_value, updated_at) VALUES (?, ?, UTC_TIMESTAMP())
        ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = UTC_TIMESTAMP()');
    foreach ($values as $key => $val) {
        $stmt->execute([$key, $val]);
    }
    return ['before' => $before, 'after' => $values];
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
    foreach (['email', 'phone', 'address'] as $field) {
        if (array_key_exists($field, $request)) $updated[$field] = trim((string) $request[$field]);
    }
    if (array_key_exists('waivedAmount', $request)) {
        $updated['waivedAmount'] = $request['waivedAmount'];
    }
    if ($updated['id'] !== $id &&
        (!is_string($updated['id']) || !preg_match('/\A[0-9]{4}-[0-9]{5}\z/', $updated['id']))) {
        throw new InvalidArgumentException('Enter a student ID in the format 2026-00125.');
    }
    $validated = validateRecord('scholars', $updated);
    $names = $validated['scholarship'];
    $catalog = scholarshipCatalog($db);
    $allowed = array_merge($originalNames, array_column($catalog, 0));
    if (!$names || array_diff($names, $allowed) || count(array_unique($names)) !== count($names) ||
        !in_array($validated['status'], ['Approved', 'Pending'], true)) {
        throw new InvalidArgumentException('Choose valid scholarships and a status.');
    }
    $historyStmt = $db->prepare("SELECT payload FROM records WHERE kind = 'discountHistory' AND BINARY JSON_UNQUOTE(JSON_EXTRACT(payload, '$.id')) = ?");
    $historyStmt->execute([$id]);
    $existingHistory = array_map(static fn($p) => json_decode($p, true, 512, JSON_THROW_ON_ERROR), $historyStmt->fetchAll(PDO::FETCH_COLUMN));
    $maxResidency = $db ? (int) (readSystemSettings($db)['maxResidencyYears'] ?? 5) : 5;
    validateScholarshipRules($names, $catalog, $existingHistory, $scholar['period'] ?? '', $maxResidency);
    validateScholarshipStatus($names, $validated['status'], $catalog);
    if (array_key_exists('waivedAmount', $updated) && $updated['waivedAmount'] !== null && $updated['waivedAmount'] !== '' && !hasTesOrTdp($names)) {
        throw new InvalidArgumentException('Amount waived is available only for TES or TDP records.');
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
    if ($db) {
        $duplicate = $db->prepare("SELECT payload FROM records WHERE kind = 'scholars' AND student_id = ? LIMIT 1");
        $duplicate->execute([$scholar['id']]);
        $existingPayload = $duplicate->fetchColumn();
        if ($existingPayload !== false) {
            $existing = json_decode($existingPayload, true, 512, JSON_THROW_ON_ERROR);
            throw new DomainException('Student ID ' . $scholar['id'] . ' is already used by ' . ($existing['name'] ?? 'another student') . '.');
        }
    }
    $scholar['updated'] = $updated;
    $period = currentSchoolYear($db);
    $scholar['period'] = $period;
    $scholar = validateRecord('scholars', $scholar);
    $names = $scholar['scholarship'];
    $catalog = scholarshipCatalog($db);
    $knownNames = array_column(array_filter($catalog, static fn($item) => empty($item[6])), 0);
    if (!$names || array_diff($names, $knownNames) || count(array_unique($names)) !== count($names) ||
        !in_array($scholar['status'], ['Approved', 'Pending'], true)) {
        throw new InvalidArgumentException('Choose valid, active scholarships and a status.');
    }
    $maxResidency = $db ? (int) (readSystemSettings($db)['maxResidencyYears'] ?? 5) : 5;
    validateScholarshipRules($names, $catalog, [], $period, $maxResidency);
    validateScholarshipStatus($names, $scholar['status'], $catalog);
    if (isset($scholar['waivedAmount']) && !hasTesOrTdp($names)) {
        throw new InvalidArgumentException('Amount waived is available only for TES or TDP records.');
    }
    $historyEntry = [
        'id' => $scholar['id'], 'name' => $scholar['name'], 'year' => $period,
        'semester' => $scholar['semester'] ?? '1st Semester', 'scholarships' => $names,
    ];
    if (isset($scholar['waivedAmount'])) {
        $historyEntry['waivedAmount'] = $scholar['waivedAmount'];
    }
    return [
        'scholars' => [$scholar],
        'discountHistory' => [$historyEntry],
        'activities' => [],
    ];
}

function readRenewals(PDO $db): array
{
    try {
        $rows = $db->query('SELECT id, student_id, student_name, course, scholarship, school_year, semester,
            status, source_year, source_semester,
            DATE_FORMAT(updated_at, "%Y-%m-%d %H:%i UTC") AS updated
            FROM scholarship_assignments
            ORDER BY updated_at DESC, id DESC')->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$row) {
            $row['id'] = (int) $row['id'];
        }
        return $rows;
    } catch (PDOException $error) {
        if ((int) ($error->errorInfo[1] ?? 0) === 1146) {
            return [];
        }
        throw $error;
    }
}

function carryOverRenewals(PDO $db, array $request): array
{
    $targetYear = preg_replace('/^(\d{4})\s*[-–—]\s*(\d{4})$/u', '$1–$2', trim((string) ($request['targetYear'] ?? '')));
    $targetSemester = trim((string) ($request['targetSemester'] ?? ''));
    $items = $request['items'] ?? null;
    if (!preg_match('/^(\d{4})\x{2013}(\d{4})$/uD', $targetYear, $years) || (int) $years[2] !== (int) $years[1] + 1) {
        throw new InvalidArgumentException('Enter a target school year such as 2026-2027.');
    }
    $validTerms = array_values(array_unique([...SCHOLAR_SEMESTERS, ...array_column(availableSemesters($db), 'semester')]));
    if (!in_array($targetSemester, $validTerms, true)) {
        throw new InvalidArgumentException('Choose a valid target semester.');
    }
    if (!is_array($items) || !$items || count($items) > 500) {
        throw new InvalidArgumentException('Select between 1 and 500 assignments to carry over.');
    }

    $catalog = scholarshipCatalog($db);
    $knownCatalog = array_flip(array_column($catalog, 0));
    $findScholar = $db->prepare("SELECT payload FROM records WHERE kind = 'scholars' AND student_id = ?");
    $insert = $db->prepare('INSERT IGNORE INTO scholarship_assignments
        (student_id, student_name, course, scholarship, school_year, semester, status, source_year, source_semester, created_at, updated_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP())');

    $created = 0;
    $skipped = 0;
    foreach ($items as $item) {
        if (!is_array($item)) {
            throw new InvalidArgumentException('Invalid renewal item.');
        }
        $studentId = trim((string) ($item['studentId'] ?? ''));
        $scholarship = trim((string) ($item['scholarship'] ?? ''));
        $sourceYear = preg_replace('/^(\d{4})\s*[-–—]\s*(\d{4})$/u', '$1–$2', trim((string) ($item['sourceYear'] ?? '')));
        $sourceSemester = trim((string) ($item['sourceSemester'] ?? ''));
        if ($studentId === '' || strlen($studentId) > 64 || $scholarship === '' || strlen($scholarship) > 120) {
            throw new InvalidArgumentException('Each renewal item requires a valid student ID and scholarship.');
        }
        $findScholar->execute([$studentId]);
        $payload = $findScholar->fetchColumn();
        if ($payload === false) {
            throw new DomainException("Student $studentId could not be found.");
        }
        $scholar = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
        $allowedForStudent = array_merge((array) ($scholar['scholarship'] ?? []), array_keys($knownCatalog));
        if (!in_array($scholarship, $allowedForStudent, true)) {
            throw new InvalidArgumentException("Scholarship $scholarship is not valid.");
        }
        $renewalStatus = scholarshipRequiresApproval([$scholarship], $catalog) ? 'Pending' : 'Approved';
        $insert->execute([
            $scholar['id'],
            $scholar['name'],
            $scholar['course'],
            $scholarship,
            $targetYear,
            $targetSemester,
            $renewalStatus,
            $sourceYear !== '' ? $sourceYear : null,
            $sourceSemester !== '' ? $sourceSemester : null,
        ]);
        if ($insert->rowCount() === 1) {
            $created++;
        } else {
            $skipped++;
        }
    }
    if ($created === 0) {
        throw new DomainException("All selected assignments already exist in $targetYear · $targetSemester.");
    }
    return [
        'created' => $created,
        'skipped' => $skipped,
        'targetYear' => $targetYear,
        'targetSemester' => $targetSemester,
    ];
}

function decideRenewal(PDO $db, array $request): array
{
    $id = filter_var($request['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $status = $request['status'] ?? null;
    if ($id === false || !in_array($status, ['Approved'], true)) {
        throw new InvalidArgumentException('Choose a valid renewal record and decision.');
    }
    $select = $db->prepare('SELECT * FROM scholarship_assignments WHERE id = ? FOR UPDATE');
    $select->execute([$id]);
    $row = $select->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        throw new DomainException('The renewal record could not be found.');
    }
    if ($row['status'] !== 'Pending') {
        throw new DomainException('This renewal has already been decided. Refresh records to see its current status.');
    }
    if (!scholarshipRequiresApproval([(string) $row['scholarship']], scholarshipCatalog($db))) {
        throw new DomainException('In-house scholarship renewals do not require approval.');
    }
    $update = $db->prepare('UPDATE scholarship_assignments SET status = ?, updated_at = UTC_TIMESTAMP() WHERE id = ?');
    $update->execute([$status, $id]);

    if ($status === 'Approved') {
        $history = $db->prepare("SELECT record_id, payload FROM records WHERE kind = 'discountHistory'
            AND BINARY JSON_UNQUOTE(JSON_EXTRACT(payload, '$.id')) = ? FOR UPDATE");
        $history->execute([$row['student_id']]);
        $matchedRecordId = null;
        $matchedPayload = null;
        foreach ($history->fetchAll(PDO::FETCH_ASSOC) as $histRow) {
            $payload = json_decode($histRow['payload'], true, 512, JSON_THROW_ON_ERROR);
            $histYear = preg_replace('/^(\d{4})\s*[-–—]\s*(\d{4})$/u', '$1–$2', trim((string) ($payload['year'] ?? '')));
            if ($histYear === $row['school_year'] && ($payload['semester'] ?? '') === $row['semester']) {
                $matchedRecordId = $histRow['record_id'];
                $matchedPayload = $payload;
                break;
            }
        }
        if ($matchedRecordId !== null) {
            $scholarships = (array) ($matchedPayload['scholarships'] ?? []);
            if (!in_array($row['scholarship'], $scholarships, true)) {
                $scholarships[] = $row['scholarship'];
                $matchedPayload['scholarships'] = $scholarships;
                $saveHist = $db->prepare('UPDATE records SET payload = ? WHERE record_id = ?');
                $saveHist->execute([json_encode($matchedPayload, JSON_THROW_ON_ERROR), $matchedRecordId]);
            }
        } else {
            $insertHist = $db->prepare('INSERT INTO records (kind, student_id, payload) VALUES (?, ?, ?)');
            insertRecord($insertHist, 'discountHistory', [
                'id' => $row['student_id'],
                'name' => $row['student_name'],
                'year' => $row['school_year'],
                'semester' => $row['semester'],
                'scholarships' => [$row['scholarship']],
            ]);
        }
    }

    return [
        'reference' => $row['student_id'],
        'Scholarship' => $row['scholarship'],
        'Term' => $row['school_year'] . ' · ' . $row['semester'],
        'before' => ['Status' => 'Pending'],
        'after' => ['Status' => $status],
    ];
}
