<?php
if (!defined('EVOTE_SYSTEM_HELPERS_LOADED')) {
    define('EVOTE_SYSTEM_HELPERS_LOADED', true);

    function evote_now(): string {
        return date('Y-m-d H:i:s');
    }

    function evote_client_ip(): string {
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }

    function evote_user_agent(): string {
        return $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    }

    function evote_secret(): string {
        return EVOTE_HASH_SECRET;
    }

    function evote_table_exists(mysqli $conn, string $table): bool {
        $sql = "SELECT COUNT(*) AS c
                FROM information_schema.tables
                WHERE table_schema = DATABASE()
                  AND table_name = ?";
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            return false;
        }
        mysqli_stmt_bind_param($stmt, 's', $table);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $exists = false;
        if ($result && ($row = mysqli_fetch_assoc($result))) {
            $exists = ((int) ($row['c'] ?? 0)) > 0;
        }
        mysqli_stmt_close($stmt);
        return $exists;
    }

    function evote_has_column(mysqli $conn, string $table, string $column): bool {
        $sql = "SELECT COUNT(*) AS c
                FROM information_schema.columns
                WHERE table_schema = DATABASE()
                  AND table_name = ?
                  AND column_name = ?";
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            return false;
        }
        mysqli_stmt_bind_param($stmt, 'ss', $table, $column);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $exists = false;
        if ($result && ($row = mysqli_fetch_assoc($result))) {
            $exists = ((int) ($row['c'] ?? 0)) > 0;
        }
        mysqli_stmt_close($stmt);
        return $exists;
    }

    function evote_normalize_value($value) {
        if (is_bool($value)) {
            return $value ? 1 : 0;
        }
        if ($value === null) {
            return '';
        }
        if (is_array($value) || is_object($value)) {
            return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }
        return (string) $value;
    }

    function evote_canonical_payload(array $fields): string {
        ksort($fields);
        $normalized = [];
        foreach ($fields as $key => $value) {
            $normalized[$key] = evote_normalize_value($value);
        }
        return json_encode($normalized, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    function evote_hash_payload(array $fields, string $previous_hash = ''): string {
        return hash('sha256', evote_canonical_payload($fields) . '|' . $previous_hash . '|' . evote_secret());
    }

    function evote_latest_hash(mysqli $conn, string $table, string $hashColumn = 'record_hash'): string {
        if (!evote_table_exists($conn, $table)) {
            return '';
        }

        $sql = "SELECT `{$hashColumn}` AS h FROM `{$table}` WHERE `{$hashColumn}` IS NOT NULL AND `{$hashColumn}` <> '' ORDER BY id DESC LIMIT 1";
        $result = mysqli_query($conn, $sql);
        if ($result && ($row = mysqli_fetch_assoc($result))) {
            return (string) ($row['h'] ?? '');
        }
        return '';
    }

    function evote_statement_execute(mysqli_stmt $stmt): bool {
        return $stmt && mysqli_stmt_execute($stmt);
    }

    function evote_password_matches(string $inputPassword, string $storedPassword): bool {
        $inputPassword = (string) $inputPassword;
        $storedPassword = (string) $storedPassword;

        if ($inputPassword === '' || trim($storedPassword) === '') {
            return false;
        }

        $info = password_get_info($storedPassword);
        if (!empty($info['algo'])) {
            return password_verify($inputPassword, $storedPassword);
        }

        return hash_equals($storedPassword, $inputPassword);
    }

    function evote_session_role(): ?string {
        $role = $_SESSION['role'] ?? null;
        $hasAdmin = !empty($_SESSION['admin_id']);
        $hasStudent = !empty($_SESSION['student_id']);

        if ($role === 'admin' || $role === 'student') {
            return $role;
        }

        if ($hasAdmin && !$hasStudent) {
            return 'admin';
        }

        if ($hasStudent && !$hasAdmin) {
            return 'student';
        }

        return null;
    }

    function evote_session_is_contaminated(): bool {
        $role = $_SESSION['role'] ?? null;
        $hasAdmin = !empty($_SESSION['admin_id']);
        $hasStudent = !empty($_SESSION['student_id']);

        if ($hasAdmin && $hasStudent) {
            return true;
        }

        if ($role !== null && $role !== 'admin' && $role !== 'student') {
            return true;
        }

        if ($role === 'admin' && $hasStudent) {
            return true;
        }

        if ($role === 'student' && $hasAdmin) {
            return true;
        }

        return false;
    }

    function evote_fetch_admin_by_id(mysqli $conn, int $adminId): ?array {
        if ($adminId <= 0 || !evote_table_exists($conn, 'admins')) {
            return null;
        }

        $columns = ['id', 'username'];
        if (evote_has_column($conn, 'admins', 'password_hash')) {
            $columns[] = 'password_hash';
        }
        if (evote_has_column($conn, 'admins', 'password')) {
            $columns[] = 'password';
        }

        $quotedColumns = array_map(
            static fn(string $column): string => '`' . str_replace('`', '``', $column) . '`',
            $columns
        );

        $stmt = mysqli_prepare(
            $conn,
            'SELECT ' . implode(', ', $quotedColumns) . ' FROM admins WHERE id = ? LIMIT 1'
        );
        if (!$stmt) {
            return null;
        }

        mysqli_stmt_bind_param($stmt, 'i', $adminId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $admin = $result ? mysqli_fetch_assoc($result) : null;
        mysqli_stmt_close($stmt);

        return $admin ?: null;
    }

    function evote_fetch_student_by_id(mysqli $conn, int $studentId): ?array {
        if ($studentId <= 0 || !evote_table_exists($conn, 'students')) {
            return null;
        }

        $sql = "SELECT * FROM students WHERE id = ? LIMIT 1";
        if (evote_has_column($conn, 'students', 'is_active')) {
            $sql = "SELECT * FROM students WHERE id = ? AND (is_active = 1 OR is_active IS NULL) LIMIT 1";
        }

        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            return null;
        }

        mysqli_stmt_bind_param($stmt, 'i', $studentId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $student = $result ? mysqli_fetch_assoc($result) : null;
        mysqli_stmt_close($stmt);

        return $student ?: null;
    }

    function evote_fetch_students_by_reg_number(mysqli $conn, string $regNumber): array {
        $rows = [];
        if ($regNumber === '' || !evote_table_exists($conn, 'students')) {
            return $rows;
        }

        $sql = "SELECT * FROM students WHERE reg_number = ?";
        if (evote_has_column($conn, 'students', 'is_active')) {
            $sql .= " AND (is_active = 1 OR is_active IS NULL)";
        }
        $sql .= " ORDER BY id DESC";
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            return $rows;
        }

        mysqli_stmt_bind_param($stmt, 's', $regNumber);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $rows[] = $row;
            }
        }
        mysqli_stmt_close($stmt);

        return $rows;
    }

    function evote_student_password_column(mysqli $conn): string {
        if (evote_has_column($conn, 'students', 'password')) {
            return 'password';
        }

        if (evote_has_column($conn, 'students', 'password_hash')) {
            return 'password_hash';
        }

        return 'password';
    }

    function evote_student_password_from_row(array $row): string {
        foreach (['stored_password', 'password', 'password_hash'] as $key) {
            if (isset($row[$key])) {
                $value = trim((string) $row[$key]);
                if ($value !== '') {
                    return $value;
                }
            }
        }

        return '';
    }

    function evote_student_is_active(array $row): bool {
        if (!array_key_exists('is_active', $row)) {
            return true;
        }

        $value = $row['is_active'];
        if ($value === null) {
            return true;
        }

        return (string) $value === '1' || (int) $value === 1;
    }

    function evote_student_password_columns(mysqli $conn): array {
        $columns = [];
        if (evote_has_column($conn, 'students', 'password')) {
            $columns[] = 'password';
        }
        if (evote_has_column($conn, 'students', 'password_hash')) {
            $columns[] = 'password_hash';
        }
        return $columns;
    }

    function evote_normalize_auth_session(mysqli $conn): ?string {
        if (evote_session_is_contaminated()) {
            evote_clear_auth_session();
            return null;
        }

        $role = $_SESSION['role'] ?? null;
        $adminId = (int) ($_SESSION['admin_id'] ?? 0);
        $studentId = (int) ($_SESSION['student_id'] ?? 0);
        $userId = (int) ($_SESSION['user_id'] ?? 0);
        $previewVote = $_SESSION['preview_vote'] ?? null;
        $voteSubmitted = $_SESSION['vote_submitted'] ?? null;

        if ($role === 'admin') {
            $admin = evote_fetch_admin_by_id($conn, $adminId > 0 ? $adminId : $userId);
            if (!$admin) {
                evote_clear_auth_session();
                return null;
            }

            evote_clear_auth_session();
            $_SESSION['admin_id'] = (int) $admin['id'];
            $_SESSION['admin_username'] = (string) $admin['username'];
            $_SESSION['user_id'] = (int) $admin['id'];
            $_SESSION['username'] = (string) $admin['username'];
            $_SESSION['role'] = 'admin';
            return 'admin';
        }

        if ($role === 'student') {
            $student = evote_fetch_student_by_id($conn, $studentId > 0 ? $studentId : $userId);
            if (!$student) {
                evote_clear_auth_session();
                return null;
            }

            evote_clear_auth_session();
            $_SESSION['student_id'] = (int) $student['id'];
            $_SESSION['reg_number'] = (string) $student['reg_number'];
            $_SESSION['username'] = (string) $student['reg_number'];
            $_SESSION['user_id'] = (int) $student['id'];
            $_SESSION['role'] = 'student';
            $_SESSION['department'] = (string) ($student['department'] ?? '');
            $_SESSION['has_voted'] = (int) ($student['has_voted'] ?? 0);
            $_SESSION['student_name'] = (string) ($student['full_name'] ?? '');
            if ($previewVote !== null) {
                $_SESSION['preview_vote'] = $previewVote;
            }
            if ($voteSubmitted !== null) {
                $_SESSION['vote_submitted'] = $voteSubmitted;
            }
            return 'student';
        }

        if ($adminId > 0) {
            $admin = evote_fetch_admin_by_id($conn, $adminId);
            if ($admin) {
                evote_clear_auth_session();
                $_SESSION['admin_id'] = (int) $admin['id'];
                $_SESSION['admin_username'] = (string) $admin['username'];
                $_SESSION['user_id'] = (int) $admin['id'];
                $_SESSION['username'] = (string) $admin['username'];
                $_SESSION['role'] = 'admin';
                return 'admin';
            }
        }

        if ($studentId > 0) {
            $student = evote_fetch_student_by_id($conn, $studentId);
            if ($student) {
                evote_clear_auth_session();
                $_SESSION['student_id'] = (int) $student['id'];
                $_SESSION['reg_number'] = (string) $student['reg_number'];
                $_SESSION['username'] = (string) $student['reg_number'];
                $_SESSION['user_id'] = (int) $student['id'];
                $_SESSION['role'] = 'student';
                $_SESSION['department'] = (string) ($student['department'] ?? '');
                $_SESSION['has_voted'] = (int) ($student['has_voted'] ?? 0);
                $_SESSION['student_name'] = (string) ($student['full_name'] ?? '');
                if ($previewVote !== null) {
                    $_SESSION['preview_vote'] = $previewVote;
                }
                if ($voteSubmitted !== null) {
                    $_SESSION['vote_submitted'] = $voteSubmitted;
                }
                return 'student';
            }
        }

        return null;
    }

    function evote_clear_auth_session(): void {
        foreach ([
            'admin_id',
            'admin_username',
            'student_id',
            'reg_number',
            'department',
            'has_voted',
            'student_name',
            'username',
            'role',
            'user_id',
            'preview_vote',
            'vote_submitted',
        ] as $key) {
            unset($_SESSION[$key]);
        }
    }

    function evote_logAuditAction(
        mysqli $conn,
        string $userRole,
        $userId,
        string $usernameOrReg,
        string $actionType,
        string $actionDescription,
        ?string $affectedTable = null,
        $affectedRecordId = null
    ): bool {
        if (!evote_table_exists($conn, 'audit_logs')) {
            return false;
        }

        $previous_hash = evote_latest_hash($conn, 'audit_logs', 'record_hash');
        $created_at = evote_now();
        $ip_address = evote_client_ip();
        $user_agent = evote_user_agent();

        $payload = [
            'user_role' => $userRole,
            'user_id' => $userId,
            'username_or_reg' => $usernameOrReg,
            'action_type' => $actionType,
            'action_description' => $actionDescription,
            'affected_table' => $affectedTable,
            'affected_record_id' => $affectedRecordId,
            'ip_address' => $ip_address,
            'user_agent' => $user_agent,
            'created_at' => $created_at,
        ];

        $record_hash = evote_hash_payload($payload, $previous_hash);

        $sql = "INSERT INTO audit_logs
            (user_role, user_id, username_or_reg, action_type, action_description, affected_table, affected_record_id, ip_address, user_agent, previous_hash, record_hash, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            return false;
        }

        $userIdParam = $userId === null ? null : (int) $userId;
        $recordIdParam = $affectedRecordId === null ? null : (int) $affectedRecordId;
        mysqli_stmt_bind_param(
            $stmt,
            'sissssssssss',
            $userRole,
            $userIdParam,
            $usernameOrReg,
            $actionType,
            $actionDescription,
            $affectedTable,
            $recordIdParam,
            $ip_address,
            $user_agent,
            $previous_hash,
            $record_hash,
            $created_at
        );

        $ok = evote_statement_execute($stmt);
        mysqli_stmt_close($stmt);
        return $ok;
    }

    function logAuditAction(
        mysqli $conn,
        string $userRole,
        $userId,
        string $usernameOrReg,
        string $actionType,
        string $actionDescription,
        ?string $affectedTable = null,
        $affectedRecordId = null
    ): bool {
        return evote_logAuditAction($conn, $userRole, $userId, $usernameOrReg, $actionType, $actionDescription, $affectedTable, $affectedRecordId);
    }

    function log_audit_action(
        string $action,
        string $table_name,
        $record_id,
        $user_id,
        string $user_type,
        $old_values = null,
        $new_values = null,
        $additional_info = null
    ): bool {
        global $conn;
        $description = $additional_info ?: trim(
            ($old_values !== null ? 'Old: ' . evote_normalize_value($old_values) . '. ' : '') .
            ($new_values !== null ? 'New: ' . evote_normalize_value($new_values) . '. ' : '') .
            ($table_name ? 'Table: ' . $table_name . '.' : '')
        );

        return evote_logAuditAction(
            $conn,
            $user_type,
            $user_id,
            $user_type === 'student' ? (string) ($GLOBALS['student_reg'] ?? $GLOBALS['reg_number'] ?? '') : (string) ($GLOBALS['admin_username'] ?? 'admin'),
            $action,
            $description,
            $table_name,
            $record_id
        );
    }

    function evote_last_previous_hash(mysqli $conn, string $table): string {
        if (!evote_table_exists($conn, $table)) {
            return '';
        }
        $result = mysqli_query($conn, "SELECT previous_hash, record_hash FROM `{$table}` ORDER BY id DESC LIMIT 1");
        if ($result && ($row = mysqli_fetch_assoc($result))) {
            return (string) ($row['record_hash'] ?? $row['previous_hash'] ?? '');
        }
        return '';
    }

    function evote_compute_entity_hash(array $fields, string $previous_hash = ''): string {
        return evote_hash_payload($fields, $previous_hash);
    }

    function evote_election_state(mysqli $conn): array {
        $state = [
            'exists' => false,
            'id' => null,
            'title' => 'Current Election',
            'start_datetime' => null,
            'end_datetime' => null,
            'status' => 'closed',
            'live_results_enabled' => false,
            'is_active' => false,
            'opens_at' => null,
            'closes_at' => null,
            'message' => 'No election is configured.',
        ];

        if (!evote_table_exists($conn, 'elections')) {
            return $state;
        }

        $sql = "SELECT * FROM elections WHERE is_active = 1 ORDER BY id DESC LIMIT 1";
        $result = mysqli_query($conn, $sql);
        if (!$result || mysqli_num_rows($result) === 0) {
            $result = mysqli_query($conn, "SELECT * FROM elections ORDER BY id DESC LIMIT 1");
        }

        if (!$result || mysqli_num_rows($result) === 0) {
            return $state;
        }

        $election = mysqli_fetch_assoc($result);
        if (!$election) {
            return $state;
        }

        $state['exists'] = true;
        $state['id'] = (int) $election['id'];
        $state['title'] = $election['title'] ?? 'Current Election';
        $state['start_datetime'] = $election['start_datetime'] ?? null;
        $state['end_datetime'] = $election['end_datetime'] ?? null;
        $state['status'] = $election['status'] ?? 'closed';
        $state['manual_override'] = $election['manual_override'] ?? 'none';
        $state['live_results_enabled'] = !empty($election['live_results_enabled']);
        $state['is_active'] = !empty($election['is_active']);
        $state['opens_at'] = $state['start_datetime'];
        $state['closes_at'] = $state['end_datetime'];

        $now = new DateTime('now', new DateTimeZone(date_default_timezone_get()));
        $start = !empty($state['start_datetime']) ? new DateTime($state['start_datetime']) : null;
        $end = !empty($state['end_datetime']) ? new DateTime($state['end_datetime']) : null;

        $calculated = 'scheduled';
        $force_open = $state['manual_override'] === 'force_open';
        $force_close = $state['manual_override'] === 'force_close';

        if ($end && $now > $end) {
            $calculated = 'closed';
            $state['message'] = 'Election closed at ' . $end->format('M d, Y h:i A');
            if ($state['manual_override'] !== 'none') {
                $state['manual_override'] = 'none';
                $election['manual_override'] = 'none';
            }
        } elseif ($force_close) {
            $calculated = 'closed';
            $state['message'] = 'Election closed by administrator.';
        } elseif ($force_open) {
            $calculated = 'open';
            $state['message'] = 'Election is live.';
        } elseif ($start && $now < $start) {
            $calculated = 'scheduled';
            $state['message'] = 'Election opens at ' . $start->format('M d, Y h:i A');
        } elseif ($start && $end && $now >= $start && $now <= $end) {
            $calculated = 'open';
            $state['message'] = 'Election is live.';
        } elseif ($end && $now > $end) {
            $calculated = 'closed';
            $state['message'] = 'Election closed at ' . $end->format('M d, Y h:i A');
        } else {
            $state['message'] = 'Election timing is not fully configured.';
        }

        if ($state['status'] !== $calculated) {
            $election['status'] = $calculated;
            $new_hash = evote_compute_entity_hash(evote_hash_fields_from_row('elections', $election));
            $update = mysqli_prepare($conn, "UPDATE elections SET status = ?, record_hash = ?, updated_at = NOW() WHERE id = ?");
            if ($update) {
                mysqli_stmt_bind_param($update, 'ssi', $calculated, $new_hash, $state['id']);
                mysqli_stmt_execute($update);
                mysqli_stmt_close($update);
            }
            $state['status'] = $calculated;
        }

        if (($state['manual_override'] ?? 'none') !== ($election['manual_override'] ?? ($state['manual_override'] ?? 'none'))) {
            $state['manual_override'] = $election['manual_override'] ?? 'none';
        }

        return $state;
    }

    function evote_can_vote(mysqli $conn): array {
        $state = evote_election_state($conn);
        $allowed = $state['exists'] && $state['status'] === 'open';
        return [$allowed, $state];
    }

    function evote_hash_fields_from_row(string $table, array $row): array {
        switch ($table) {
            case 'students':
                return [
            'reg_number' => $row['reg_number'] ?? '',
            'full_name' => $row['full_name'] ?? '',
            'password' => evote_student_password_from_row($row),
            'department' => $row['department'] ?? '',
            'email' => $row['email'] ?? '',
            'has_voted' => (int) ($row['has_voted'] ?? 0),
            'is_active' => (int) ($row['is_active'] ?? 1),
                ];
            case 'candidates':
                return [
                    'name' => $row['name'] ?? '',
                    'reg_number' => $row['reg_number'] ?? '',
                    'department' => $row['department'] ?? '',
                    'position' => $row['position'] ?? '',
                    'gender' => $row['gender'] ?? '',
                    'manifesto' => $row['manifesto'] ?? '',
                    'photo' => $row['photo'] ?? '',
                ];
            case 'elections':
                return [
                    'title' => $row['title'] ?? '',
                    'start_datetime' => $row['start_datetime'] ?? '',
                    'end_datetime' => $row['end_datetime'] ?? '',
                    'status' => $row['status'] ?? '',
                    'manual_override' => $row['manual_override'] ?? 'none',
                    'live_results_enabled' => (int) ($row['live_results_enabled'] ?? 0),
                    'is_active' => (int) ($row['is_active'] ?? 0),
                ];
            case 'votes':
                return [
                    'student_id' => $row['student_id'] ?? '',
                    'reg_number' => $row['reg_number'] ?? '',
                    'department' => $row['department'] ?? '',
                    'male_delegate_candidate_id' => $row['male_delegate_candidate_id'] ?? '',
                    'female_delegate_candidate_id' => $row['female_delegate_candidate_id'] ?? '',
                    'departmental_delegate_candidate_id' => $row['departmental_delegate_candidate_id'] ?? '',
                    'ip_address' => $row['ip_address'] ?? '',
                    'created_at' => $row['created_at'] ?? '',
                ];
            case 'audit_logs':
                return [
                    'user_role' => $row['user_role'] ?? '',
                    'user_id' => $row['user_id'] ?? '',
                    'username_or_reg' => $row['username_or_reg'] ?? '',
                    'action_type' => $row['action_type'] ?? '',
                    'action_description' => $row['action_description'] ?? '',
                    'affected_table' => $row['affected_table'] ?? '',
                    'affected_record_id' => $row['affected_record_id'] ?? '',
                    'ip_address' => $row['ip_address'] ?? '',
                    'user_agent' => $row['user_agent'] ?? '',
                    'created_at' => $row['created_at'] ?? '',
                ];
        }

        return $row;
    }

    function evote_verify_row_hash(string $table, array $row): array {
        $expected = '';
        $status = 'valid';
        $reason = 'Hash matches.';
        $hashColumn = 'record_hash';
        $previousHash = '';
        $fields = evote_hash_fields_from_row($table, $row);

        if ($table === 'votes' || $table === 'audit_logs') {
            $hashColumn = 'record_hash';
            $previousHash = (string) ($row['previous_hash'] ?? '');
        }

        if (!isset($row[$hashColumn]) || $row[$hashColumn] === '') {
            return [
                'status' => 'tampered',
                'reason' => 'Missing stored hash.',
                'expected_hash' => null,
                'stored_hash' => null,
            ];
        }

        $expected = evote_hash_payload($fields, $previousHash);
        if (!hash_equals((string) $row[$hashColumn], $expected)) {
            $status = 'tampered';
            $reason = 'Stored hash does not match the record contents.';
        }

        return [
            'status' => $status,
            'reason' => $reason,
            'expected_hash' => $expected,
            'stored_hash' => (string) $row[$hashColumn],
        ];
    }

    function evote_scan_integrity(mysqli $conn, string $table, int $limit = 500): array {
        $report = [];
        if (!evote_table_exists($conn, $table)) {
            return $report;
        }

        $sql = "SELECT * FROM `{$table}` ORDER BY id ASC LIMIT " . (int) $limit;
        $result = mysqli_query($conn, $sql);
        if (!$result) {
            return $report;
        }

        $previous_hash = '';
        while ($row = mysqli_fetch_assoc($result)) {
            $verified = evote_verify_row_hash($table, $row);
            if (($table === 'votes' || $table === 'audit_logs') && !empty($row['previous_hash']) && $previous_hash !== '' && $row['previous_hash'] !== $previous_hash) {
                $verified['status'] = 'tampered';
                $verified['reason'] = 'Previous hash chain mismatch.';
            }

            $report[] = [
                'table_name' => $table,
                'record_id' => (int) ($row['id'] ?? 0),
                'status' => $verified['status'],
                'reason' => $verified['reason'],
                'time_checked' => evote_now(),
            ];

            $previous_hash = (string) ($row['record_hash'] ?? '');
        }

        return $report;
    }

    function evote_fetch_all_logs(mysqli $conn, array $filters = []): array {
        $logs = [];
        if (!evote_table_exists($conn, 'audit_logs')) {
            return $logs;
        }

        $sql = "SELECT * FROM audit_logs WHERE 1=1";
        $types = '';
        $params = [];

        if (!empty($filters['user_role'])) {
            $sql .= " AND user_role = ?";
            $types .= 's';
            $params[] = $filters['user_role'];
        }
        if (!empty($filters['action_type'])) {
            $sql .= " AND action_type = ?";
            $types .= 's';
            $params[] = $filters['action_type'];
        }
        if (!empty($filters['date_from'])) {
            $sql .= " AND DATE(created_at) >= ?";
            $types .= 's';
            $params[] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $sql .= " AND DATE(created_at) <= ?";
            $types .= 's';
            $params[] = $filters['date_to'];
        }
        if (!empty($filters['keyword'])) {
            $sql .= " AND (username_or_reg LIKE ? OR action_description LIKE ? OR affected_table LIKE ?)";
            $types .= 'sss';
            $keyword = '%' . $filters['keyword'] . '%';
            array_push($params, $keyword, $keyword, $keyword);
        }

        $sql .= " ORDER BY created_at DESC, id DESC LIMIT 1000";
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            return $logs;
        }

        if (!empty($params)) {
            $bind = [];
            $bind[] = &$types;
            foreach ($params as $index => $value) {
                $bind[] = &$params[$index];
            }
            call_user_func_array([$stmt, 'bind_param'], $bind);
        }

        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $logs[] = $row;
            }
        }
        mysqli_stmt_close($stmt);
        return $logs;
    }

    function evote_safe_int($value, int $default = 0): int {
        return is_numeric($value) ? (int) $value : $default;
    }

    function evote_position_storage_value(string $position): string {
        $normalized = strtolower(trim($position));
        $normalized = str_replace([' ', '-'], '_', $normalized);
        $normalized = preg_replace('/[^a-z0-9_]/', '', $normalized);

        $map = [
            'male_delegate' => 'male_delegate',
            'male delegate' => 'male_delegate',
            'female_delegate' => 'female_delegate',
            'female delegate' => 'female_delegate',
            'departmental_delegate' => 'departmental_delegate',
            'departmental delegate' => 'departmental_delegate',
        ];

        if (isset($map[$normalized])) {
            return $map[$normalized];
        }

        return $normalized;
    }

    function evote_position_display_label(string $position): string {
        $slug = evote_position_storage_value($position);
        $labels = [
            'male_delegate' => 'Male Delegate',
            'female_delegate' => 'Female Delegate',
            'departmental_delegate' => 'Departmental Delegate',
        ];

        return $labels[$slug] ?? ucwords(str_replace('_', ' ', $slug));
    }

    function evote_position_match_candidates(string $position): array {
        $slug = evote_position_storage_value($position);
        $label = evote_position_display_label($slug);
        return array_values(array_unique([$slug, $label]));
    }

    function evote_add_column_if_missing(mysqli $conn, string $table, string $columnSql, string $columnName): void {
        if (!evote_has_column($conn, $table, $columnName)) {
            mysqli_query($conn, "ALTER TABLE `{$table}` ADD COLUMN {$columnSql}");
        }
    }

    function evote_index_exists(mysqli $conn, string $table, string $indexName): bool {
        if (!evote_table_exists($conn, $table)) {
            return false;
        }

        $sql = "SELECT COUNT(*) AS c
                FROM information_schema.statistics
                WHERE table_schema = DATABASE()
                  AND table_name = ?
                  AND index_name = ?";
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            return false;
        }

        mysqli_stmt_bind_param($stmt, 'ss', $table, $indexName);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $exists = false;
        if ($result && ($row = mysqli_fetch_assoc($result))) {
            $exists = ((int) ($row['c'] ?? 0)) > 0;
        }
        mysqli_stmt_close($stmt);
        return $exists;
    }

    function evote_column_has_duplicates(mysqli $conn, string $table, string $column): bool {
        if (!evote_table_exists($conn, $table) || !evote_has_column($conn, $table, $column)) {
            return false;
        }

        $sql = "SELECT 1 FROM `{$table}` WHERE `{$column}` IS NOT NULL AND `{$column}` <> '' GROUP BY `{$column}` HAVING COUNT(*) > 1 LIMIT 1";
        $result = mysqli_query($conn, $sql);
        return $result && mysqli_num_rows($result) > 0;
    }

    function evote_add_unique_index_if_possible(mysqli $conn, string $table, string $indexName, array $columns): void {
        if (!evote_table_exists($conn, $table) || evote_index_exists($conn, $table, $indexName)) {
            return;
        }

        $duplicateFound = false;
        foreach ($columns as $column) {
            if (evote_column_has_duplicates($conn, $table, $column)) {
                $duplicateFound = true;
                break;
            }
        }

        if ($duplicateFound) {
            return;
        }

        $escapedColumns = array_map(static function ($column) {
            return '`' . $column . '`';
        }, $columns);

        mysqli_query($conn, "ALTER TABLE `{$table}` ADD UNIQUE KEY `{$indexName}` (" . implode(', ', $escapedColumns) . ")");
    }

    function evote_backfill_hashes(mysqli $conn): void {
        foreach (['students', 'candidates', 'elections'] as $table) {
            if (!evote_table_exists($conn, $table) || !evote_has_column($conn, $table, 'record_hash')) {
                continue;
            }

            $result = mysqli_query($conn, "SELECT * FROM `{$table}` WHERE record_hash IS NULL OR record_hash = '' ORDER BY id ASC");
            if (!$result) {
                continue;
            }

            $update = mysqli_prepare($conn, "UPDATE `{$table}` SET record_hash = ? WHERE id = ?");
            if (!$update) {
                continue;
            }

            while ($row = mysqli_fetch_assoc($result)) {
                $hash = evote_compute_entity_hash(evote_hash_fields_from_row($table, $row));
                $id = (int) ($row['id'] ?? 0);
                mysqli_stmt_bind_param($update, 'si', $hash, $id);
                mysqli_stmt_execute($update);
            }

            mysqli_stmt_close($update);
        }

        foreach (['votes', 'audit_logs'] as $table) {
            if (!evote_table_exists($conn, $table) || !evote_has_column($conn, $table, 'record_hash')) {
                continue;
            }

            $result = mysqli_query($conn, "SELECT * FROM `{$table}` ORDER BY id ASC");
            if (!$result) {
                continue;
            }

            $update = mysqli_prepare($conn, "UPDATE `{$table}` SET previous_hash = ?, record_hash = ? WHERE id = ?");
            if (!$update) {
                continue;
            }

            $previous_hash = '';
            while ($row = mysqli_fetch_assoc($result)) {
                $stored_hash = (string) ($row['record_hash'] ?? '');
                $stored_previous = (string) ($row['previous_hash'] ?? '');
                $hash = $stored_hash;

                if ($stored_hash === '') {
                    $hash = evote_compute_entity_hash(evote_hash_fields_from_row($table, $row), $previous_hash);
                }

                if ($stored_previous === '') {
                    $stored_previous = $previous_hash;
                }

                $id = (int) ($row['id'] ?? 0);
                mysqli_stmt_bind_param($update, 'ssi', $stored_previous, $hash, $id);
                mysqli_stmt_execute($update);
                $previous_hash = $hash;
            }

            mysqli_stmt_close($update);
        }
    }

    function evote_ensure_schema(mysqli $conn): void {
        mysqli_query($conn, "CREATE TABLE IF NOT EXISTS `audit_logs` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `user_role` ENUM('admin','student') NOT NULL,
            `user_id` INT NULL,
            `username_or_reg` VARCHAR(100) NOT NULL,
            `action_type` VARCHAR(120) NOT NULL,
            `action_description` TEXT NOT NULL,
            `affected_table` VARCHAR(64) NULL,
            `affected_record_id` INT NULL,
            `ip_address` VARCHAR(45) NOT NULL,
            `user_agent` TEXT NOT NULL,
            `previous_hash` CHAR(64) NOT NULL DEFAULT '',
            `record_hash` CHAR(64) NOT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        mysqli_query($conn, "CREATE TABLE IF NOT EXISTS `elections` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `title` VARCHAR(255) NOT NULL,
            `start_datetime` DATETIME NOT NULL,
            `end_datetime` DATETIME NOT NULL,
            `status` ENUM('scheduled','open','closed') NOT NULL DEFAULT 'scheduled',
            `manual_override` ENUM('none','force_open','force_close') NOT NULL DEFAULT 'none',
            `live_results_enabled` TINYINT(1) NOT NULL DEFAULT 0,
            `is_active` TINYINT(1) NOT NULL DEFAULT 1,
            `record_hash` CHAR(64) NOT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        foreach (['students', 'candidates'] as $table) {
            evote_add_column_if_missing($conn, $table, "`record_hash` CHAR(64) NOT NULL DEFAULT ''", 'record_hash');
        }

        evote_add_column_if_missing($conn, 'votes', "`previous_hash` CHAR(64) NOT NULL DEFAULT ''", 'previous_hash');
        evote_add_column_if_missing($conn, 'votes', "`record_hash` CHAR(64) NOT NULL DEFAULT ''", 'record_hash');
        evote_add_column_if_missing($conn, 'students', "`updated_at` TIMESTAMP NULL DEFAULT NULL", 'updated_at');
        evote_add_column_if_missing($conn, 'candidates', "`updated_at` TIMESTAMP NULL DEFAULT NULL", 'updated_at');
        evote_add_column_if_missing($conn, 'elections', "`updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP", 'updated_at');
        evote_add_column_if_missing($conn, 'elections', "`live_results_enabled` TINYINT(1) NOT NULL DEFAULT 0", 'live_results_enabled');
        evote_add_column_if_missing($conn, 'elections', "`is_active` TINYINT(1) NOT NULL DEFAULT 1", 'is_active');
        evote_add_column_if_missing($conn, 'elections', "`manual_override` ENUM('none','force_open','force_close') NOT NULL DEFAULT 'none'", 'manual_override');
        evote_add_column_if_missing($conn, 'elections', "`record_hash` CHAR(64) NOT NULL DEFAULT ''", 'record_hash');

        evote_add_unique_index_if_possible($conn, 'students', 'uniq_students_reg_number', ['reg_number']);
        if (evote_has_column($conn, 'students', 'email')) {
            evote_add_unique_index_if_possible($conn, 'students', 'uniq_students_email', ['email']);
        }

        // Preserve any legacy table, but ensure the new schema is available.
        evote_backfill_hashes($conn);
    }
}
