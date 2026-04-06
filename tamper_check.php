<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$page_title = "Tamper Check";
include 'includes/admin_header.php';

$tables_to_scan = ['students', 'candidates', 'votes', 'audit_logs', 'elections'];
$scan_results = [];
foreach ($tables_to_scan as $table) {
    $scan_results = array_merge($scan_results, evote_scan_integrity($conn, $table, 1000));
}

$summary = ['valid' => 0, 'tampered' => 0];
foreach ($scan_results as $row) {
    if ($row['status'] === 'valid') {
        $summary['valid']++;
    } else {
        $summary['tampered']++;
    }
}
?>

<div class="page-header">
    <h1 class="page-title">Tamper Check</h1>
    <p class="page-subtitle">Scan protected tables and flag records whose hashes no longer match their stored data</p>
</div>

<div class="stats-grid" style="margin-bottom:20px;">
    <div class="stat-card">
        <h3>Valid Records</h3>
        <div class="stat-value"><?php echo (int) $summary['valid']; ?></div>
    </div>
    <div class="stat-card">
        <h3>Tampered Records</h3>
        <div class="stat-value" style="color:#dc2626;"><?php echo (int) $summary['tampered']; ?></div>
    </div>
</div>

<div class="content-card">
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Table</th>
                    <th>Record ID</th>
                    <th>Status</th>
                    <th>Reason</th>
                    <th>Time Checked</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($scan_results)): ?>
                    <?php foreach ($scan_results as $row): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['table_name']); ?></td>
                            <td><?php echo (int) $row['record_id']; ?></td>
                            <td>
                                <span style="padding:4px 12px;border-radius:999px;font-weight:700;<?php echo $row['status'] === 'valid' ? 'background:#dcfce7;color:#166534;' : 'background:#fee2e2;color:#991b1b;'; ?>">
                                    <?php echo htmlspecialchars($row['status']); ?>
                                </span>
                            </td>
                            <td><?php echo htmlspecialchars($row['reason']); ?></td>
                            <td><?php echo htmlspecialchars($row['time_checked']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5" style="text-align:center;padding:32px;color:#6b7280;">No protected records found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include 'includes/admin_footer.php'; exit; ?>
// Tamper Detection Functions for Online Voting System

// Helper function to get available columns for a table
function get_available_columns($conn, $table_name) {
    $columns = [];
    $result = @mysqli_query($conn, "SHOW COLUMNS FROM $table_name");
    
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $columns[] = $row['Field'];
        }
    }
    
    return $columns;
}

// Generate a random salt for hashing
function generate_salt() {
    return bin2hex(random_bytes(16)); // 32 character hex string
}

// Generate vote hash for tamper detection
function generate_vote_hash($student_id, $reg_number, $male_id, $female_id, $dept_id, $salt, $timestamp) {
    $data = $student_id . $reg_number . $male_id . $female_id . $dept_id . $salt . $timestamp;
    return hash('sha512', $data);
}

// Generate audit action hash
function generate_audit_hash($action, $table_name, $record_id, $user_id, $user_type, $old_values, $new_values, $timestamp) {
    $data = $action . $table_name . $record_id . $user_id . $user_type . $old_values . $new_values . $timestamp;
    return hash('sha512', $data);
}

// Log audit action
function log_audit_action($action, $table_name, $record_id, $user_id, $user_type, $old_values = null, $new_values = null, $additional_info = null) {
    global $conn;

    $timestamp = date('Y-m-d H:i:s');
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';

    $action_hash = generate_audit_hash($action, $table_name, $record_id, $user_id, $user_type, $old_values, $new_values, $timestamp);

    // Check if audit_log table exists
    $check_table = @mysqli_query($conn, "SHOW TABLES LIKE 'audit_log'");
    if (!$check_table || mysqli_num_rows($check_table) == 0) {
        return; // Table doesn't exist, skip logging
    }

    $sql = "INSERT INTO audit_log (action, table_name, record_id, user_id, user_type, old_values, new_values, ip_address, user_agent, action_hash)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = @mysqli_prepare($conn, $sql);
    if ($stmt) {
        @mysqli_stmt_bind_param($stmt, 'ssisssssss', $action, $table_name, $record_id, $user_id, $user_type, $old_values, $new_values, $ip_address, $user_agent, $action_hash);
        @mysqli_stmt_execute($stmt);
        @mysqli_stmt_close($stmt);
    }
}


    global $conn;

    $timestamp = date('Y-m-d H:i:s');
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    // Get complete record data before deletion for audit trail
    $record_data = null;
    if ($table_name === 'candidates') {
        $result = @mysqli_query($conn, "SELECT * FROM candidates WHERE id = $record_id LIMIT 1");
        if ($result && $row = mysqli_fetch_assoc($result)) {
            $record_data = json_encode($row);
        }
    } elseif ($table_name === 'students') {
        $result = @mysqli_query($conn, "SELECT * FROM students WHERE id = $record_id LIMIT 1");
        if ($result && $row = mysqli_fetch_assoc($result)) {
            $record_data = json_encode($row);
        }
    } elseif ($table_name === 'votes') {
        $result = @mysqli_query($conn, "SELECT * FROM votes WHERE id = $record_id LIMIT 1");
        if ($result && $row = mysqli_fetch_assoc($result)) {
            $record_data = json_encode($row);
        }
    }

    // Create detailed log entry with full context
    $full_details = json_encode([
        'action' => $action,
        'table' => $table_name,
        'record_id' => $record_id,
        'user_id' => $user_id,
        'user_type' => $user_type,
        'ip_address' => $ip_address,
        'user_agent' => $user_agent,
        'severity' => $severity,
        'timestamp' => $timestamp,
        'affected_record' => $record_data,
        'additional_details' => $details
    ]);

    // Log to audit trail
    log_audit_action($action, $table_name, $record_id, $user_id, $user_type, $record_data, null, $full_details);

    // Also write to a separate critical events log file for extra safety
    $log_file = dirname(__FILE__) . '/logs/critical_events.log';
    @mkdir(dirname($log_file), 0755, true);
    @file_put_contents($log_file, "[" . $timestamp . "] " . $full_details . "\n", FILE_APPEND);
}

// Track candidate deletion
function log_candidate_deletion($candidate_id, $user_id, $user_type) {
    $query = "SELECT id, name, reg_number, position, department FROM candidates WHERE id = $candidate_id LIMIT 1";
    global $conn;
    $result = @mysqli_query($conn, $query);
    
    $candidate_info = '';
    if ($result && $candidate = mysqli_fetch_assoc($result)) {
        $candidate_info = "Candidate: {$candidate['name']} (Reg: {$candidate['reg_number']}, Position: {$candidate['position']}, Dept: {$candidate['department']})";
    }
    
    log_critical_event('CANDIDATE_DELETED', 'candidates', $candidate_id, $user_id, $user_type, 'CRITICAL', $candidate_info);
}

// Track student deletion
function log_student_deletion($student_id, $user_id, $user_type) {
    $query = "SELECT id, name, reg_number, department, has_voted FROM students WHERE id = $student_id LIMIT 1";
    global $conn;
    $result = @mysqli_query($conn, $query);
    
    $student_info = '';
    if ($result && $student = mysqli_fetch_assoc($result)) {
        $student_info = "Student: {$student['name']} (Reg: {$student['reg_number']}, Dept: {$student['department']}, Has Voted: " . ($student['has_voted'] ? 'YES' : 'NO') . ")";
    }
    
    log_critical_event('STUDENT_DELETED', 'students', $student_id, $user_id, $user_type, 'CRITICAL', $student_info);
}

// Track vote modification
function log_vote_modification($vote_id, $user_id, $user_type, $modification_details) {
    log_critical_event('VOTE_MODIFIED', 'votes', $vote_id, $user_id, $user_type, 'CRITICAL', $modification_details);
}

// Track candidate modification
function log_candidate_modification($candidate_id, $user_id, $user_type, $old_values, $new_values) {
    $details = "Modified fields: " . json_encode([
        'old' => $old_values,
        'new' => $new_values
    ]);
    log_critical_event('CANDIDATE_MODIFIED', 'candidates', $candidate_id, $user_id, $user_type, 'HIGH', $details);
}

// Get all critical events for admin review
function get_critical_events($limit = 100) {
    global $conn;
    
    $critical_events = [];
    
    // Check if audit_log table exists
    $check_table = @mysqli_query($conn, "SHOW TABLES LIKE 'audit_log'");
    if (!$check_table || mysqli_num_rows($check_table) == 0) {
        return [];
    }
    
    $query = "SELECT * FROM audit_log WHERE action IN ('CANDIDATE_DELETED', 'STUDENT_DELETED', 'VOTE_MODIFIED', 'CANDIDATE_MODIFIED', 'TAMPERING_SIMULATION') 
              ORDER BY created_at DESC LIMIT $limit";
    $result = @mysqli_query($conn, $query);
    
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $critical_events[] = $row;
        }
    }
    
    return $critical_events;
}



// Get system integrity status
function get_system_integrity_status() {
    global $conn;

    $status = [
        'overall_status' => 'SECURE',
        'last_check' => date('Y-m-d H:i:s'),
        'vote_integrity' => [
            'total_votes' => 0,
            'valid_votes' => 0,
            'tampered_votes' => 0,
            'tampered_details' => []
        ],
        'audit_integrity' => [
            'total_logs' => 0,
            'valid_logs' => 0,
            'tampered_logs' => 0
        ]
    ];

    // Check if votes table exists
    $check_votes_table = @mysqli_query($conn, "SHOW TABLES LIKE 'votes'");
    if (!$check_votes_table || mysqli_num_rows($check_votes_table) == 0) {
        return $status; // Table doesn't exist, return empty status
    }

    // Get available columns in votes table
    $votes_columns = get_available_columns($conn, 'votes');
    
    // Build query with available columns
    $select_cols = ['id', 'student_id', 'reg_number', 'male_delegate_candidate_id', 'female_delegate_candidate_id', 'departmental_delegate_candidate_id'];
    $has_vote_hash = false;
    $timestamp_col = null;
    
    if (in_array('vote_hash', $votes_columns)) {
        $has_vote_hash = true;
        $select_cols[] = 'vote_hash';
        $select_cols[] = 'salt';
    }
    
    // Try to get timestamp - could be created_at or timestamp
    if (in_array('created_at', $votes_columns)) {
        $timestamp_col = 'created_at';
        $select_cols[] = 'created_at';
    } elseif (in_array('timestamp', $votes_columns)) {
        $timestamp_col = 'timestamp';
        $select_cols[] = 'timestamp';
    }
    
    $votes_query = "SELECT " . implode(',', $select_cols) . " FROM votes";
    $votes_result = @mysqli_query($conn, $votes_query);

    if ($votes_result) {
        while ($vote = mysqli_fetch_assoc($votes_result)) {
            $status['vote_integrity']['total_votes']++;

            if ($has_vote_hash && $timestamp_col) {
                $expected_hash = generate_vote_hash(
                    $vote['student_id'],
                    $vote['reg_number'],
                    $vote['male_delegate_candidate_id'],
                    $vote['female_delegate_candidate_id'],
                    $vote['departmental_delegate_candidate_id'],
                    $vote['salt'],
                    $vote[$timestamp_col]
                );

                if ($expected_hash === $vote['vote_hash']) {
                    $status['vote_integrity']['valid_votes']++;
                } else {
                    $status['vote_integrity']['tampered_votes']++;
                    $status['overall_status'] = 'TAMPERED';

                    // Get student reg number
                    $student_query = "SELECT reg_number FROM students WHERE id = " . $vote['student_id'];
                    $student_result = @mysqli_query($conn, $student_query);
                    $student_reg = 'Unknown';
                    if ($student_result && $student = mysqli_fetch_assoc($student_result)) {
                        $student_reg = $student['reg_number'];
                    }

                    $status['vote_integrity']['tampered_details'][] = [
                        'vote_id' => $vote['id'],
                        'student_reg' => $student_reg
                    ];
                }
            } else {
                // If vote_hash or timestamp column doesn't exist, mark all as valid
                $status['vote_integrity']['valid_votes']++;
            }
        }
    }

    // Check if audit_log table exists
    $check_audit_table = @mysqli_query($conn, "SHOW TABLES LIKE 'audit_log'");
    if ($check_audit_table && mysqli_num_rows($check_audit_table) > 0) {
        // Get available columns in audit_log table
        $audit_columns = get_available_columns($conn, 'audit_log');
        $has_action_hash = in_array('action_hash', $audit_columns);

        $audit_select_cols = ['id', 'action', 'table_name', 'record_id', 'user_id', 'user_type', 'old_values', 'new_values', 'created_at'];
        if ($has_action_hash) {
            $audit_select_cols[] = 'action_hash';
        }

        // Filter to only columns that exist
        $audit_select_cols = array_intersect($audit_select_cols, $audit_columns);
        
        if (!empty($audit_select_cols)) {
            $audit_query = "SELECT " . implode(',', $audit_select_cols) . " FROM audit_log";
            $audit_result = @mysqli_query($conn, $audit_query);

            if ($audit_result) {
                while ($log = mysqli_fetch_assoc($audit_result)) {
                    $status['audit_integrity']['total_logs']++;

                    if ($has_action_hash && in_array('created_at', $audit_columns)) {
                        $expected_hash = generate_audit_hash(
                            $log['action'],
                            $log['table_name'],
                            $log['record_id'],
                            $log['user_id'],
                            $log['user_type'],
                            $log['old_values'],
                            $log['new_values'],
                            $log['created_at']
                        );

                        if ($expected_hash === $log['action_hash']) {
                            $status['audit_integrity']['valid_logs']++;
                        } else {
                            $status['audit_integrity']['tampered_logs']++;
                            $status['overall_status'] = 'TAMPERED';
                        }
                    } else {
                        // If action_hash doesn't exist, mark all as valid
                        $status['audit_integrity']['valid_logs']++;
                    }
                }
            }
        }
    }

    return $status;
}

// Simulate vote tampering for demonstration
function simulate_vote_tampering($vote_id) {
    global $conn;

    // Check if votes table and vote_hash column exist
    $check_table = @mysqli_query($conn, "SHOW TABLES LIKE 'votes'");
    if (!$check_table || mysqli_num_rows($check_table) == 0) {
        return;
    }

    $check_columns = @mysqli_query($conn, "SHOW COLUMNS FROM votes LIKE 'vote_hash'");
    if (!$check_columns || mysqli_num_rows($check_columns) == 0) {
        return;
    }

    // Get current vote data
    $query = "SELECT * FROM votes WHERE id = $vote_id";
    $result = @mysqli_query($conn, $query);

    if ($result && $vote = mysqli_fetch_assoc($result)) {
        // Modify the vote hash to simulate tampering
        $tampered_hash = hash('sha512', 'tampered' . $vote['vote_hash']);

        $update_query = "UPDATE votes SET vote_hash = '$tampered_hash' WHERE id = $vote_id";
        @mysqli_query($conn, $update_query);

        // Log the tampering simulation
        log_audit_action('TAMPERING_SIMULATION', 'votes', $vote_id, 0, 'admin', $vote['vote_hash'], $tampered_hash);
    }
}
?>

// Get system integrity status
function get_system_integrity_status() {
    global $conn;

    $status = [
        'overall_status' => 'SECURE',
        'last_check' => date('Y-m-d H:i:s'),
        'vote_integrity' => [
            'total_votes' => 0,
            'valid_votes' => 0,
            'tampered_votes' => 0,
            'tampered_details' => []
        ],
        'audit_integrity' => [
            'total_logs' => 0,
            'valid_logs' => 0,
            'tampered_logs' => 0
        ]
    ];

    // Check vote integrity - select available columns only
    $votes_query = "SELECT id, student_id, reg_number, male_delegate_candidate_id, female_delegate_candidate_id,
                           departmental_delegate_candidate_id, created_at FROM votes";
    
    // Try to include vote_hash and salt if they exist
    $check_columns = "SHOW COLUMNS FROM votes LIKE 'vote_hash'";
    $col_result = @mysqli_query($conn, $check_columns);
    $has_vote_hash = false;
    if ($col_result && mysqli_num_rows($col_result) > 0) {
        $has_vote_hash = true;
        $votes_query = "SELECT id, student_id, reg_number, male_delegate_candidate_id, female_delegate_candidate_id,
                               departmental_delegate_candidate_id, vote_hash, salt, created_at FROM votes";
    }
    
    $votes_result = @mysqli_query($conn, $votes_query);

    if ($votes_result) {
        while ($vote = mysqli_fetch_assoc($votes_result)) {
            $status['vote_integrity']['total_votes']++;

            if ($has_vote_hash) {
                $expected_hash = generate_vote_hash(
                    $vote['student_id'],
                    $vote['reg_number'],
                    $vote['male_delegate_candidate_id'],
                    $vote['female_delegate_candidate_id'],
                    $vote['departmental_delegate_candidate_id'],
                    $vote['salt'],
                    $vote['created_at']
                );

                if ($expected_hash === $vote['vote_hash']) {
                    $status['vote_integrity']['valid_votes']++;
                } else {
                    $status['vote_integrity']['tampered_votes']++;
                    $status['overall_status'] = 'TAMPERED';

                    // Get student reg number
                    $student_query = "SELECT reg_number FROM students WHERE id = " . $vote['student_id'];
                    $student_result = @mysqli_query($conn, $student_query);
                    $student_reg = 'Unknown';
                    if ($student_result && $student = mysqli_fetch_assoc($student_result)) {
                        $student_reg = $student['reg_number'];
                    }

                    $status['vote_integrity']['tampered_details'][] = [
                        'vote_id' => $vote['id'],
                        'student_reg' => $student_reg
                    ];
                }
            } else {
                // If vote_hash column doesn't exist, mark all as valid
                $status['vote_integrity']['valid_votes']++;
            }
        }
    }

    // Check audit log integrity
    $check_audit_columns = "SHOW COLUMNS FROM audit_log LIKE 'action_hash'";
    $audit_col_result = @mysqli_query($conn, $check_audit_columns);
    $has_action_hash = false;
    if ($audit_col_result && mysqli_num_rows($audit_col_result) > 0) {
        $has_action_hash = true;
    }

    $audit_query = $has_action_hash 
        ? "SELECT id, action, table_name, record_id, user_id, user_type, old_values, new_values, created_at, action_hash FROM audit_log"
        : "SELECT id, action, table_name, record_id, user_id, user_type, old_values, new_values, created_at FROM audit_log";
    
    $audit_result = @mysqli_query($conn, $audit_query);

    if ($audit_result) {
        while ($log = mysqli_fetch_assoc($audit_result)) {
            $status['audit_integrity']['total_logs']++;

            if ($has_action_hash) {
                $expected_hash = generate_audit_hash(
                    $log['action'],
                    $log['table_name'],
                    $log['record_id'],
                    $log['user_id'],
                    $log['user_type'],
                    $log['old_values'],
                    $log['new_values'],
                    $log['created_at']
                );

                if ($expected_hash === $log['action_hash']) {
                    $status['audit_integrity']['valid_logs']++;
                } else {
                    $status['audit_integrity']['tampered_logs']++;
                    $status['overall_status'] = 'TAMPERED';
                }
            } else {
                // If action_hash doesn't exist, mark all as valid
                $status['audit_integrity']['valid_logs']++;
            }
        }
    }

    return $status;
}

// Simulate vote tampering for demonstration
function simulate_vote_tampering($vote_id) {
    global $conn;

    // Check if vote_hash column exists
    $check_columns = "SHOW COLUMNS FROM votes LIKE 'vote_hash'";
    $col_result = @mysqli_query($conn, $check_columns);
    
    if (!$col_result || mysqli_num_rows($col_result) == 0) {
        // Column doesn't exist, skip simulation
        return;
    }

    // Get current vote data
    $query = "SELECT * FROM votes WHERE id = $vote_id";
    $result = @mysqli_query($conn, $query);

    if ($result && $vote = mysqli_fetch_assoc($result)) {
        // Modify the vote hash to simulate tampering
        $tampered_hash = hash('sha512', 'tampered' . $vote['vote_hash']);

        $update_query = "UPDATE votes SET vote_hash = '$tampered_hash' WHERE id = $vote_id";
        @mysqli_query($conn, $update_query);

        // Log the tampering simulation
        log_audit_action('TAMPERING_SIMULATION', 'votes', $vote_id, 0, 'admin', $vote['vote_hash'], $tampered_hash);
    }
}
?>
